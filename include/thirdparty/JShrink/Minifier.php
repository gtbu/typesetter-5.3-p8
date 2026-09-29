<?php
/**
 * Typesetter Safe JShrink-compatible Minifier
 *
 * https://github.com/tedious/JShrink  BSD-3-Clause license
 * @author     Robert Hafner <tedivm@tedivm.com>
 * 
 * Kompatible API:
 *   JShrink\Minifier::minify($js, $options = [])
 */

namespace JShrink;

class Minifier
{
    /**
     * Public JShrink-compatible API.
     */
    public static function minify($js, $options = [])
    {
        if (!is_string($js) || $js === '') {
            return $js;
        }

        try {
            $minifier = new self($js, is_array($options) ? $options : []);
            $result = $minifier->process();

            // Safety first: never replace the source with a larger result.
            if (strlen($result) >= strlen($js)) {
                return $js;
            }

            return $result;
        } catch (\Throwable $e) {
            // A safe minifier must fail open.
            return $js;
        }
    }

    protected $input;
    protected $length;
    protected $index = 0;
    protected $options = [];

    protected $output = '';
    protected $lastSignificant = '';
    protected $pendingSpace = false;
    protected $pendingNewline = false;

    protected $keywords = [
        'break', 'case', 'catch', 'continue', 'debugger', 'default',
        'delete', 'do', 'else', 'finally', 'for', 'function', 'if',
        'in', 'instanceof', 'new', 'return', 'switch', 'throw',
        'try', 'typeof', 'var', 'void', 'while', 'with', 'yield',
        'const', 'let', 'class', 'extends', 'super', 'import',
        'export', 'await'
    ];

    public function __construct($js, array $options = [])
    {
        $this->input = $js;
        $this->length = strlen($js);
        $this->options = $options;
    }

    /**
     * Main scanner.
     *
     * This intentionally does much less than a traditional optimizer.
     * It removes only whitespace/comments that can be handled safely.
     */
    protected function process()
    {
        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            // Whitespace outside protected constructs.
            if ($this->isWhitespace($c)) {
                $this->consumeWhitespace();
                continue;
            }

            // Comments.
            if ($c === '/' && $this->index + 1 < $this->length) {
                $next = $this->input[$this->index + 1];

                if ($next === '/') {
                    $this->consumeLineComment();
                    continue;
                }

                if ($next === '*') {
                    $this->consumeBlockComment();
                    continue;
                }
            }

            // Quoted strings.
            if ($c === "'" || $c === '"') {
                $this->flushPending();
                $literal = $this->readQuotedString($c);
                $this->appendRaw($literal);
                continue;
            }

            // Template literals must be preserved byte-for-byte.
            if ($c === '`') {
                $this->flushPending();
                $literal = $this->readTemplateLiteral();
                $this->appendRaw($literal);
                continue;
            }

            // Regex literal, handled conservatively.
            if ($c === '/' && $this->looksLikeRegexStart()) {
                $this->flushPending();
                $regex = $this->readRegexLiteral();

                if ($regex === null) {
                    throw new \RuntimeException('Unable to safely parse regular expression literal.');
                }

                $this->appendRaw($regex);
                continue;
            }

            // Ordinary source character.
            $this->flushPending();
            $this->appendRaw($c);
            $this->lastSignificant = $c;
            $this->index++;
        }

        // Trailing whitespace is never necessary in minified JS.
        $this->pendingSpace = false;
        $this->pendingNewline = false;

        return $this->output;
    }

    protected function isWhitespace($c)
    {
        return $c === " " || $c === "\t" || $c === "\r" || $c === "\n" ||
               $c === "\f" || $c === "\v";
    }

    protected function consumeWhitespace()
    {
        $hasNewline = false;

        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            if ($c === "\n" || $c === "\r") {
                $hasNewline = true;

                if ($c === "\r" && $this->index + 1 < $this->length &&
                    $this->input[$this->index + 1] === "\n") {
                    $this->index += 2;
                    continue;
                }

                $this->index++;
                continue;
            }

            if ($c === " " || $c === "\t" || $c === "\f" || $c === "\v") {
                $this->index++;
                continue;
            }

            break;
        }

        if ($hasNewline) {
            // Keep one newline. This preserves ASI-sensitive line boundaries,
            // while collapsing runs of blank lines.
            $this->pendingNewline = true;
            $this->pendingSpace = false;
        } else {
            $this->pendingSpace = true;
        }
    }

    protected function consumeLineComment()
    {
        $start = $this->index;
        $this->index += 2;

        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            if ($c === "\r" || $c === "\n") {
                break;
            }

            $this->index++;
        }

        // Preserve special line comments such as sourceURL/sourceMappingURL.
        $comment = substr($this->input, $start, $this->index - $start);

        if ($this->isProtectedLineComment($comment)) {
            $this->flushPending();
            $this->appendRaw($comment);
            $this->lastSignificant = '/';

            if ($this->index < $this->length) {
                $this->consumeWhitespace();
            }
            return;
        }

        // Ordinary line comments can safely be replaced by a single newline.
        $this->pendingNewline = true;
        $this->pendingSpace = false;
    }

    protected function consumeBlockComment()
    {
        $start = $this->index;
        $end = strpos($this->input, '*/', $this->index + 2);

        if ($end === false) {
            throw new \RuntimeException('Unterminated block comment.');
        }

        $comment = substr($this->input, $start, $end + 2 - $start);
        $this->index = $end + 2;

        if ($this->isProtectedBlockComment($comment)) {
            $this->flushPending();
            $this->appendRaw($comment);
            $this->lastSignificant = '*';
            return;
        }

        // If a removed block comment contained newlines, retain one newline.
        // This is enough for source separation/ASI while avoiding blank-line spam.
        if (strpos($comment, "\n") !== false || strpos($comment, "\r") !== false) {
            $this->pendingNewline = true;
            $this->pendingSpace = false;
        } else {
            $this->pendingSpace = true;
        }
    }

    protected function isProtectedLineComment($comment)
    {
        $trimmed = ltrim($comment);

        return stripos($trimmed, '//# sourceURL=') === 0 ||
               stripos($trimmed, '//@ sourceURL=') === 0 ||
               stripos($trimmed, '//# sourceMappingURL=') === 0 ||
               stripos($trimmed, '//@ sourceMappingURL=') === 0;
    }

    protected function isProtectedBlockComment($comment)
    {
        // Same convention used by JShrink for flagged comments.
        if (isset($this->options['flaggedComments']) && $this->options['flaggedComments'] === false) {
            return false;
        }

        return isset($comment[2]) &&
               ($comment[2] === '!' || (isset($comment[3]) && $comment[2] === '*' && $comment[3] === '@'));
    }

    protected function readQuotedString($quote)
    {
        $start = $this->index;
        $this->index++;

        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            if ($c === '\\') {
                $this->index += 2;
                continue;
            }

            $this->index++;

            if ($c === $quote) {
                return substr($this->input, $start, $this->index - $start);
            }

            if ($c === "\r" || $c === "\n") {
                throw new \RuntimeException('Unterminated string literal.');
            }
        }

        throw new \RuntimeException('Unterminated string literal.');
    }

    /**
     * Preserve the entire template literal exactly.
     *
     * This is deliberately conservative. We do not attempt to minify
     * expressions inside ${...}; doing so safely requires a JS parser.
     */
    protected function readTemplateLiteral()
    {
        $start = $this->index;
        $this->index++;
        $escaped = false;

        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            if ($escaped) {
                $escaped = false;
                $this->index++;
                continue;
            }

            if ($c === '\\') {
                $escaped = true;
                $this->index++;
                continue;
            }

            $this->index++;

            if ($c === '`') {
                return substr($this->input, $start, $this->index - $start);
            }
        }

        throw new \RuntimeException('Unterminated template literal.');
    }

    /**
     * Conservative regex detection.
     *
     * A slash is considered a regex start only after tokens that cannot
     * legally end an expression. If uncertain, return false and let the
     * slash be emitted as ordinary JavaScript.
     */
    protected function looksLikeRegexStart()
    {
        $prev = $this->previousSignificantCharacter();

        if ($prev === '') {
            return true;
        }

        if (strpos("([{=,:;!&|?+-*%^~<>", $prev) !== false) {
            return true;
        }

        // Keywords after which an expression is expected.
        $word = $this->previousWord();

        if ($word !== '' && in_array($word, $this->keywords, true)) {
            return true;
        }

        return false;
    }

    protected function previousSignificantCharacter()
    {
        $i = $this->index - 1;

        while ($i >= 0) {
            $c = $this->input[$i];

            if (!$this->isWhitespace($c)) {
                return $c;
            }

            $i--;
        }

        return '';
    }

    protected function previousWord()
    {
        $i = $this->index - 1;

        while ($i >= 0 && $this->isWhitespace($this->input[$i])) {
            $i--;
        }

        $end = $i + 1;

        while ($i >= 0 && preg_match('/[A-Za-z0-9_$]/', $this->input[$i])) {
            $i--;
        }

        $start = $i + 1;

        if ($start >= $end) {
            return '';
        }

        return substr($this->input, $start, $end - $start);
    }

    /**
     * Read a regex literal without modifying its contents.
     */
    protected function readRegexLiteral()
    {
        $start = $this->index;
        $this->index++; // opening /

        $inClass = false;
        $escaped = false;

        while ($this->index < $this->length) {
            $c = $this->input[$this->index];

            if ($escaped) {
                $escaped = false;
                $this->index++;
                continue;
            }

            if ($c === '\\') {
                $escaped = true;
                $this->index++;
                continue;
            }

            if ($c === '[') {
                $inClass = true;
                $this->index++;
                continue;
            }

            if ($c === ']' && $inClass) {
                $inClass = false;
                $this->index++;
                continue;
            }

            if ($c === '/' && !$inClass) {
                $this->index++;

                // Regex flags.
                while ($this->index < $this->length &&
                       preg_match('/[A-Za-z]/', $this->input[$this->index])) {
                    $this->index++;
                }

                return substr($this->input, $start, $this->index - $start);
            }

            // A literal newline cannot occur inside a regular expression.
            if ($c === "\r" || $c === "\n") {
                return null;
            }

            $this->index++;
        }

        return null;
    }

    /**
     * Emit pending whitespace conservatively.
     */
    protected function flushPending()
    {
        if ($this->pendingNewline) {
            // A newline is only useful if both sides contain code.
            // Never emit multiple newlines.
            if ($this->output !== '') {
                $this->output .= "\n";
            }

            $this->pendingNewline = false;
            $this->pendingSpace = false;
            return;
        }

        if ($this->pendingSpace) {
            $next = $this->input[$this->index] ?? '';

            if ($this->needsSpace($this->lastSignificant, $next)) {
                $this->output .= ' ';
            }

            $this->pendingSpace = false;
        }
    }

    protected function needsSpace($previous, $next)
    {
        if ($previous === '' || $next === '') {
            return false;
        }

        // Word/identifier boundaries.
        if ($this->isIdentifierCharacter($previous) &&
            $this->isIdentifierCharacter($next)) {
            return true;
        }

        // Prevent accidental ++ / -- creation.
        if (($previous === '+' && $next === '+') ||
            ($previous === '-' && $next === '-')) {
            return true;
        }

        // Prevent HTML-comment-like sequences.
        if ($previous === '<' && $next === '!') {
            return true;
        }

        return false;
    }

    protected function isIdentifierCharacter($c)
    {
        return $c !== '' && preg_match('/[A-Za-z0-9_$]/', $c);
    }

    protected function appendRaw($text)
    {
        $this->output .= $text;

        if ($text !== '') {
            $last = $text[strlen($text) - 1];

            if (!$this->isWhitespace($last)) {
                $this->lastSignificant = $last;
            }
        }
    }
}
