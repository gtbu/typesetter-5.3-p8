<?php
/**
 * https://github.com/tedious/JShrink  BSD-3-Clause license
 * @author     Robert Hafner <tedivm@tedivm.com>
 * Compatibility goal:
 *   - Keeps the public JShrink API:
 *       \JShrink\Minifier::minify($js, $options)
 *   - Conservative: correctness is more important than compression.
 *   - Never performs semantic optimisations.
 *   - On any unexpected parsing problem, returns the original JavaScript.
 *
 *   modified for Typesetter5 Cms 9/2026
 */

namespace JShrink;

class Minifier
{
    protected $input = '';
    protected $len = 0;
    protected $index = 0;
    protected $options = [];

    protected static $defaultOptions = [
        'flaggedComments' => true,
    ];

    /**
     * Public API compatible with JShrink.
     *
     * The safe policy is:
     *   - If minification succeeds, return the conservatively minified code.
     *   - If anything unexpected happens, return the original input.
     */
    public static function minify($js, $options = [])
    {
        if (!is_string($js) || $js === '') {
            return $js;
        }

        $original = $js;

        try {
            $minifier = new self();
            $result = $minifier->process($js, $options);
            $minifier->clean();

            if (!is_string($result) || $result === '') {
                return $original;
            }

            return $result;
        } catch (\Throwable $e) {
            // PHP 7/8: catch both Exception and Error.
            // Correctness is more important than compression.
            if (isset($minifier)) {
                $minifier->clean();
            }

            return $original;
        }
    }

    protected function process($js, $options)
    {
        $this->initialize($js, $options);

        $out = '';
        $pendingSpace = false;
        $lastWasNewline = false;

        while ($this->index < $this->len) {
            $char = $this->input[$this->index];

            // Normalize CRLF/CR to LF outside of protected constructs.
            if ($char === "\r") {
                $this->index++;
                if ($this->index < $this->len && $this->input[$this->index] === "\n") {
                    $this->index++;
                }
                $out .= "\n";
                $pendingSpace = false;
                $lastWasNewline = true;
                continue;
            }

            // Preserve strings exactly.
            if ($char === "'" || $char === '"') {
                $this->flushPendingSpace($out, $pendingSpace, $lastWasNewline);
                $out .= $this->readQuotedString($char);
                $lastWasNewline = false;
                continue;
            }

            // Preserve template literals exactly. This deliberately does not
            // attempt to minify expressions inside ${...}; that is safer.
            if ($char === '`') {
                $this->flushPendingSpace($out, $pendingSpace, $lastWasNewline);
                $out .= $this->readTemplateLiteral();
                $lastWasNewline = false;
                continue;
            }

            // Comments.
            if ($char === '/' && $this->peek(1) === '/') {
                $comment = $this->readLineComment();

                // A line comment is replaced by its line ending. This preserves
                // automatic-semicolon-insertion boundaries.
                if ($comment['newline']) {
                    $out .= "\n";
                    $pendingSpace = false;
                    $lastWasNewline = true;
                } else {
                    // End-of-file line comment: preserve a separating space.
                    $pendingSpace = true;
                }
                continue;
            }

            if ($char === '/' && $this->peek(1) === '*') {
                $comment = $this->readBlockComment();

                if ($comment['flagged'] && $this->options['flaggedComments']) {
                    $this->flushPendingSpace($out, $pendingSpace, $lastWasNewline);
                    $out .= $comment['text'];
                    $lastWasNewline = $this->endsWithNewline($comment['text']);
                    continue;
                }

                // A block comment is replaced with whitespace equivalent to
                // its line structure. Never concatenate tokens accidentally.
                if ($comment['newlines'] > 0) {
                    $out .= str_repeat("\n", $comment['newlines']);
                    $pendingSpace = false;
                    $lastWasNewline = true;
                } else {
                    $pendingSpace = true;
                }
                continue;
            }

            if ($char === "\n") {
                $this->index++;
                $out .= "\n";
                $pendingSpace = false;
                $lastWasNewline = true;
                continue;
            }

            // Horizontal whitespace: collapse it, but do not remove it yet.
            // The final decision is made using the neighbouring tokens.
            if ($this->isWhitespace($char)) {
                $this->index++;
                $pendingSpace = true;
                continue;
            }

            /*
             * Regex literals need special handling because // and /* inside a
             * regex are not comments. We only enter regex mode when the
             * preceding significant token makes a regex literal reasonably
             * unambiguous. If it is not unambiguous, we do NOT guess.
             */
            if ($char === '/' && $this->looksLikeRegexStart($out)) {
                $this->flushPendingSpace($out, $pendingSpace, $lastWasNewline);
                $out .= $this->readRegexLiteral();
                $lastWasNewline = false;
                continue;
            }

            $this->appendNormalChar(
                $out,
                $char,
                $pendingSpace,
                $lastWasNewline
            );

            $this->index++;
        }

        // Trailing horizontal whitespace has no value.
        $out = rtrim($out, " \t");

        return $out;
    }

    protected function initialize($js, $options)
    {
        $this->input = $js;
        $this->len = strlen($js);
        $this->index = 0;
        $this->options = array_merge(static::$defaultOptions, is_array($options) ? $options : []);
    }

    protected function clean()
    {
        $this->input = '';
        $this->len = 0;
        $this->index = 0;
        $this->options = [];
    }

    protected function appendNormalChar(&$out, $char, &$pendingSpace, &$lastWasNewline)
    {
        if ($pendingSpace) {
            $previous = $this->lastSignificantChar($out);

            /*
             * Keep a space when two identifier-like characters would otherwise
             * become one token. This also covers $, Unicode letters and digits.
             *
             * For all other punctuation we remove the whitespace. This is safe
             * for the conservative transformations performed here.
             */
            if ($this->needsSeparator($previous, $char)) {
                $out .= ' ';
            }

            $pendingSpace = false;
        }

        $out .= $char;
        $lastWasNewline = false;
    }

    protected function flushPendingSpace(&$out, &$pendingSpace, &$lastWasNewline)
    {
        if (!$pendingSpace) {
            return;
        }

        $previous = $this->lastSignificantChar($out);

        // Before a quoted/template string a separator is required when the
        // previous character could merge with its opening delimiter only in
        // syntactically meaningful contexts. Keep it when in doubt.
        if ($previous !== '' && $this->isIdentifierChar($previous)) {
            $out .= ' ';
        }

        $pendingSpace = false;
        $lastWasNewline = false;
    }

    protected function needsSeparator($left, $right)
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($this->isIdentifierChar($left) && $this->isIdentifierChar($right)) {
            return true;
        }

        // Numeric literal followed by a dot can change meaning.
        if ($this->isDigit($left) && ($right === '.' || $right === 'e' || $right === 'E')) {
            return true;
        }

        // + + and - - must not become ++ / --.
        if (($left === '+' && $right === '+') || ($left === '-' && $right === '-')) {
            return true;
        }

        // A slash next to another slash can start a comment.
        if ($left === '/' && $right === '/') {
            return true;
        }

        return false;
    }

    protected function readQuotedString($quote)
    {
        $start = $this->index;
        $this->index++;

        while ($this->index < $this->len) {
            $c = $this->input[$this->index];

            if ($c === '\\') {
                $this->index++;
                if ($this->index < $this->len) {
                    $this->index++;
                }
                continue;
            }

            if ($c === $quote) {
                $this->index++;
                return substr($this->input, $start, $this->index - $start);
            }

            if ($c === "\n" || $c === "\r") {
                throw new \RuntimeException('Unclosed JavaScript string at position ' . $start);
            }

            $this->index++;
        }

        throw new \RuntimeException('Unclosed JavaScript string at position ' . $start);
    }

    protected function readTemplateLiteral()
    {
        $start = $this->index;
        $this->index++;

        while ($this->index < $this->len) {
            $c = $this->input[$this->index];

            if ($c === '\\') {
                $this->index++;
                if ($this->index < $this->len) {
                    $this->index++;
                }
                continue;
            }

            if ($c === '`') {
                $this->index++;
                return substr($this->input, $start, $this->index - $start);
            }

            $this->index++;
        }

        throw new \RuntimeException('Unclosed JavaScript template literal at position ' . $start);
    }

    protected function readLineComment()
    {
        $start = $this->index;
        $this->index += 2;

        while ($this->index < $this->len) {
            $c = $this->input[$this->index];

            if ($c === "\n" || $c === "\r") {
                return [
                    'text' => substr($this->input, $start, $this->index - $start),
                    'newline' => true,
                ];
            }

            $this->index++;
        }

        return [
            'text' => substr($this->input, $start, $this->index - $start),
            'newline' => false,
        ];
    }

    protected function readBlockComment()
    {
        $start = $this->index;
        $this->index += 2;
        $newlines = 0;

        while ($this->index < $this->len) {
            $c = $this->input[$this->index];

            if ($c === "\r") {
                $newlines++;
                $this->index++;
                if ($this->index < $this->len && $this->input[$this->index] === "\n") {
                    $this->index++;
                }
                continue;
            }

            if ($c === "\n") {
                $newlines++;
                $this->index++;
                continue;
            }

            if ($c === '*' && $this->peek(1) === '/') {
                $this->index += 2;
                $text = substr($this->input, $start, $this->index - $start);

                return [
                    'text' => $text,
                    'newlines' => $newlines,
                    'flagged' => isset($text[2]) && ($text[2] === '!' || $text[2] === '@'),
                ];
            }

            $this->index++;
        }

        throw new \RuntimeException('Unclosed multiline JavaScript comment at position ' . $start);
    }

    protected function readRegexLiteral()
    {
        $start = $this->index;
        $this->index++; // leading /

        $inClass = false;

        while ($this->index < $this->len) {
            $c = $this->input[$this->index];

            if ($c === '\\') {
                $this->index++;
                if ($this->index < $this->len) {
                    $this->index++;
                }
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

                // Preserve all regexp flags exactly.
                while ($this->index < $this->len && $this->isRegexFlag($this->input[$this->index])) {
                    $this->index++;
                }

                return substr($this->input, $start, $this->index - $start);
            }

            if ($c === "\n" || $c === "\r") {
                throw new \RuntimeException('Unclosed regular expression at position ' . $start);
            }

            $this->index++;
        }

        throw new \RuntimeException('Unclosed regular expression at position ' . $start);
    }

    protected function looksLikeRegexStart($out)
    {
        $token = $this->previousToken($out);

        if ($token === '') {
            return true;
        }

        // Tokens after which JavaScript grammar permits a regexp literal.
        static $regexAfter = [
            '(' => true,
            '[' => true,
            '{' => true,
            ',' => true,
            ';' => true,
            ':' => true,
            '=' => true,
            '==' => true,
            '===' => true,
            '!=' => true,
            '!==' => true,
            '!' => true,
            '&' => true,
            '&&' => true,
            '|' => true,
            '||' => true,
            '?' => true,
            '??' => true,
            '=>' => true,
            '+' => true,
            '-' => true,
            '*' => true,
            '%' => true,
            '&=' => true,
            '|=' => true,
            '^=' => true,
            '+=' => true,
            '-=' => true,
            '*=' => true,
            '/=' => true,
            '%=' => true,
            'return' => true,
            'throw' => true,
            'case' => true,
            'delete' => true,
            'void' => true,
            'typeof' => true,
            'instanceof' => true,
            'in' => true,
            'of' => true,
            'yield' => true,
            'await' => true,
            'new' => true,
        ];

        return isset($regexAfter[$token]);
    }

    protected function previousToken($out)
    {
        $s = rtrim($out);

        if ($s === '') {
            return '';
        }

        // Identifier / keyword.
        if (preg_match('/(?:[$A-Z_a-z\x80-\xFF][\w$]*|\d+(?:\.\d+)?)$/u', $s, $m)) {
            return $m[0];
        }

        // Operators / punctuation, longest first.
        foreach ([
            '===', '!==', '>>>', '**=', '&&=', '||=', '??=',
            '=>', '==', '!=', '<=', '>=', '++', '--', '&&', '||',
            '??', '+=', '-=', '*=', '/=', '%=', '&=', '|=', '^=',
            '**', '<<', '>>', '?.'
        ] as $op) {
            if (substr($s, -strlen($op)) === $op) {
                return $op;
            }
        }

        return substr($s, -1);
    }

    protected function lastSignificantChar($out)
    {
        $out = rtrim($out, " \t");
        return $out === '' ? '' : substr($out, -1);
    }

    protected function peek($offset = 1)
    {
        $pos = $this->index + $offset;
        return $pos < $this->len ? $this->input[$pos] : false;
    }

    protected function isWhitespace($char)
    {
        return $char === ' ' || $char === "\t" || $char === "\f" || $char === "\v";
    }

    protected function isDigit($char)
    {
        return $char !== '' && $char >= '0' && $char <= '9';
    }

    protected function isIdentifierChar($char)
    {
        if ($char === '' || $char === false) {
            return false;
        }

        return preg_match('/^[\w$]$/u', $char) === 1 || ord($char) >= 128;
    }

    protected function isRegexFlag($char)
    {
        return $char !== false && preg_match('/^[A-Za-z]$/', $char) === 1;
    }

    protected function endsWithNewline($text)
    {
        return $text !== '' && substr($text, -1) === "\n";
    }
}
