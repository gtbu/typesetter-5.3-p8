
<?php

declare(strict_types=1);

/**
 * HTML fragment sanitizer for PHP 8.1+.
 * Version 0.5
 * Security model:
 * - Explicit HTML element and attribute allowlists
 * - Dangerous elements are removed with their entire subtree
 * - URL schemes are validated
 * - CSS is opt-in and restricted
 * - Resource limits are applied before and during DOM traversal
 *
 * Note: DOMDocument uses libxml's HTML parser, not a fully conforming
 * HTML5 parser. For maximum HTML5 parsing compatibility, consider
 * masterminds/html5.
 */
final class HtmlSanitizer
{
    private DOMDocument $dom;

    /** @var array<string, true> */
    private array $elements = [];

    /** @var array<string, true> */
    private array $dropContentElements = [];

    /** @var array<string, array<string, true>> */
    private array $attributes = [];

    /** @var array<string, true> */
    private array $globalAttributes = [];

    /** @var array<string, true> */
    private array $schemes = [];

    /** @var array<string, true> */
    private array $cssProperties = [];

    /** @var array<string, callable> */
    private array $validators = [];

    private bool $allowDataAttributes;
    private bool $allowAriaAttributes;
    private bool $allowInlineStyle;
    private bool $allowProtocolRelative;
    private bool $allowDataImages;
    private int $maxInputBytes;
    private int $maxNodes;
    private int $maxDepth;
    private int $maxAttributes;
    private int $maxAttributeLength;

    private const DROP_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset',
        'object', 'embed', 'applet', 'template', 'noscript',
        'noembed', 'noframes', 'xmp', 'plaintext',
        'svg', 'math',
    ];

    /**
     * Broad HTML5 element allowlist.
     *
     * Active or externally embedded content is deliberately excluded.
     * Some allowed elements can still have security or UX implications,
     * so applications should narrow this list for their use case.
     */
    private const ELEMENTS = [
        'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo',
        'blockquote', 'br', 'caption', 'cite', 'code', 'col', 'colgroup',
        'data', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em',
        'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5',
        'h6', 'header', 'hgroup', 'hr', 'i', 'ins', 'kbd', 'li', 'main',
        'mark', 'menu', 'meter', 'nav', 'ol', 'p', 'pre', 'progress',
        'q', 'rp', 'rt', 'ruby', 's', 'samp', 'search', 'section',
        'small', 'span', 'strong', 'sub', 'summary', 'sup', 'time',
        'u', 'ul', 'var', 'wbr',

        'audio', 'img', 'picture', 'source', 'track', 'video',

        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr',

        'button', 'datalist', 'fieldset', 'form', 'input', 'label',
        'legend', 'optgroup', 'option', 'output', 'select', 'textarea',

        'canvas', 'map', 'area',

        'dialog',

        // Legacy text/inline elements still encountered in real HTML.
        'font', 'center', 'strike', 'tt', 'big',
    ];

    private const GLOBAL_ATTRIBUTES = [
        'id', 'class', 'title', 'lang', 'dir', 'role',
        'hidden', 'tabindex', 'accesskey', 'translate',
        'draggable', 'contenteditable', 'spellcheck',
        'inert', 'slot',
    ];

    private const TAG_ATTRIBUTES = [
        'a' => [
            'href', 'target', 'rel', 'hreflang', 'type',
            'download', 'referrerpolicy',
        ],
        'area' => [
            'alt', 'coords', 'shape', 'href', 'target',
            'rel', 'download', 'referrerpolicy',
        ],
        'audio' => [
            'src', 'controls', 'autoplay', 'loop', 'muted',
            'preload',
        ],
        'blockquote' => ['cite'],
        'button' => ['type', 'disabled', 'name', 'value', 'form'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'data' => ['value'],
        'del' => ['cite', 'datetime'],
        'details' => ['open', 'name'],
        'form' => ['method', 'enctype', 'autocomplete', 'name'],
        'input' => [
            'type', 'name', 'value', 'placeholder', 'checked',
            'disabled', 'readonly', 'required', 'multiple',
            'min', 'max', 'step', 'minlength', 'maxlength',
            'size', 'pattern', 'autocomplete', 'accept',
            'alt', 'width', 'height', 'src', 'list', 'form',
        ],
        'ins' => ['cite', 'datetime'],
        'label' => ['for', 'form'],
        'li' => ['value'],
        'map' => ['name'],
        'meter' => ['value', 'min', 'max', 'low', 'high', 'optimum'],
        'ol' => ['start', 'reversed', 'type'],
        'optgroup' => ['disabled', 'label'],
        'option' => ['disabled', 'selected', 'value', 'label'],
        'output' => ['for', 'form', 'name'],
        'progress' => ['value', 'max'],
        'q' => ['cite'],
        'select' => [
            'name', 'disabled', 'required', 'multiple',
            'size', 'autocomplete', 'form',
        ],
        'source' => ['src', 'srcset', 'sizes', 'type', 'media'],
        'td' => ['colspan', 'rowspan', 'headers'],
        'textarea' => [
            'name', 'placeholder', 'rows', 'cols', 'minlength',
            'maxlength', 'required', 'disabled', 'readonly',
            'wrap', 'form',
        ],
        'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr'],
        'time' => ['datetime'],
        'track' => ['kind', 'src', 'srclang', 'label', 'default'],
        'video' => [
            'src', 'poster', 'controls', 'autoplay', 'loop',
            'muted', 'preload', 'width', 'height', 'playsinline',
        ],
        'img' => [
            'src', 'srcset', 'sizes', 'alt', 'width', 'height',
            'loading', 'decoding', 'fetchpriority', 'usemap',
            'ismap', 'referrerpolicy',
        ],
    ];

    private const CSS_PROPERTIES = [
        'color', 'background-color',
        'font-family', 'font-size', 'font-style', 'font-weight',
        'text-align', 'text-decoration', 'text-transform',
        'white-space', 'overflow-wrap', 'word-break',
        'line-height', 'letter-spacing',
        'width', 'height', 'max-width', 'max-height',
        'min-width', 'min-height',
        'margin', 'margin-top', 'margin-right',
        'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right',
        'padding-bottom', 'padding-left',
        'border', 'border-color', 'border-style', 'border-width',
        'border-collapse', 'border-spacing', 'border-radius',
        'display', 'vertical-align',
        'list-style-type',
    ];

    public function __construct(array $config = [])
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');

        $this->allowDataAttributes =
            (bool) ($config['allow_data_attributes'] ?? true);

        $this->allowAriaAttributes =
            (bool) ($config['allow_aria_attributes'] ?? true);

        $this->allowInlineStyle =
            (bool) ($config['allow_inline_style'] ?? false);

        $this->allowProtocolRelative =
            (bool) ($config['allow_protocol_relative'] ?? false);

        $this->allowDataImages =
            (bool) ($config['allow_data_images'] ?? false);

        $this->maxInputBytes =
            $this->positiveInt($config, 'max_input_bytes', 1_000_000);

        $this->maxNodes =
            $this->positiveInt($config, 'max_nodes', 20_000);

        $this->maxDepth =
            $this->positiveInt($config, 'max_depth', 100);

        $this->maxAttributes =
            $this->positiveInt($config, 'max_attributes', 50);

        $this->maxAttributeLength =
            $this->positiveInt($config, 'max_attribute_length', 10_000);

        $elementList = $config['elements'] ?? self::ELEMENTS;
        $globalList = $config['global_attributes'] ?? self::GLOBAL_ATTRIBUTES;
        $tagAttributes = array_replace_recursive(
            self::TAG_ATTRIBUTES,
            $config['attributes'] ?? []
        );

        $this->elements = $this->toSet($elementList);
        $this->globalAttributes = $this->toSet($globalList);
        $this->dropContentElements = $this->toSet(self::DROP_CONTENT);

        foreach ($tagAttributes as $tag => $list) {
            $this->attributes[strtolower((string) $tag)] =
                $this->toSet($list);
        }

        $this->schemes = $this->toSet(
            $config['schemes'] ?? ['http', 'https', 'mailto', 'tel']
        );

        $this->cssProperties = $this->toSet(
            $config['css_properties'] ?? self::CSS_PROPERTIES
        );

        $this->validators = $config['validators'] ?? [];

        foreach ($this->validators as $tag => $validators) {
            if (!is_array($validators)) {
                throw new InvalidArgumentException(
                    "Validators for '{$tag}' must be an array."
                );
            }

            foreach ($validators as $attribute => $validator) {
                if (!is_callable($validator)) {
                    throw new InvalidArgumentException(
                        "Validator '{$tag}.{$attribute}' must be callable."
                    );
                }
            }
        }
    }

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (strlen($html) > $this->maxInputBytes) {
            throw new LengthException('HTML input exceeds the configured limit.');
        }

        if (!mb_check_encoding($html, 'UTF-8')) {
            throw new InvalidArgumentException('HTML input must be valid UTF-8.');
        }

        // A unique wrapper lets us preserve multiple top-level fragments.
        $wrapperId = '__sanitizer_root__';

        $document = '<?xml encoding="UTF-8">'
            . '<!DOCTYPE html><html><body>'
            . '<div id="' . $wrapperId . '">'
            . $html
            . '</div></body></html>';

        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $this->dom->loadHTML(
                $document,
                LIBXML_NONET | LIBXML_COMPACT
            );

            if (!$loaded) {
                return '';
            }

            $xpath = new DOMXPath($this->dom);
            $wrappers = $xpath->query(
                '//*[@id="' . $wrapperId . '"]'
            );

            $wrapper = $wrappers?->item(0);

            if (!$wrapper instanceof DOMElement) {
                return '';
            }

            $this->sanitizeTree($wrapper);

            $result = '';

            // Serialize the sanitized wrapper's children only.
            foreach (iterator_to_array($wrapper->childNodes) as $child) {
                $serialized = $this->dom->saveHTML($child);

                if ($serialized !== false) {
                    $result .= $serialized;
                }
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function sanitizeTree(DOMNode $root): void
    {
        $count = 0;
        $stack = [];

        // Iterative traversal avoids PHP call-stack exhaustion.
        foreach (iterator_to_array($root->childNodes) as $child) {
            $stack[] = [$child, 1];
        }

        while ($stack !== []) {
            [$node, $depth] = array_pop($stack);

            if (++$count > $this->maxNodes) {
                throw new LengthException('HTML node limit exceeded.');
            }

            if ($depth > $this->maxDepth) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            if (!$node instanceof DOMElement) {
                if (
                    !$node instanceof DOMText
                    && !$node instanceof DOMComment
                ) {
                    $node->parentNode?->removeChild($node);
                } elseif ($node instanceof DOMComment) {
                    $node->parentNode?->removeChild($node);
                }

                continue;
            }

            $tag = strtolower($node->tagName);

            // Never unwrap active/embedded content: remove its subtree.
            if (isset($this->dropContentElements[$tag])) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            // Process children before deciding whether to unwrap the tag.
            $children = iterator_to_array($node->childNodes);

            foreach ($children as $child) {
                $stack[] = [$child, $depth + 1];
            }

            if (!isset($this->elements[$tag])) {
                $this->unwrap($node);
                continue;
            }

            $this->sanitizeElementAttributes($node, $tag);
        }
    }

    private function sanitizeElementAttributes(
        DOMElement $element,
        string $tag
    ): void {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        if (count($attributes) > $this->maxAttributes) {
            foreach ($attributes as $attribute) {
                $element->removeAttributeNode($attribute);
            }

            return;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if (strlen($value) > $this->maxAttributeLength) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            // All event handlers and namespace declarations are forbidden.
            if (
                str_starts_with($name, 'on')
                || str_starts_with($name, 'xmlns')
                || str_contains($name, ':')
            ) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (!$this->isAttributeAllowed($tag, $name)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $validator = $this->validators[$tag][$name]
                ?? $this->validators['*'][$name]
                ?? null;

            if ($validator !== null) {
                try {
                    $validated = $validator($value, $element, $tag, $name);
                } catch (Throwable) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                if (!is_string($validated) && !is_int($validated)
                    && !is_float($validated) && !is_bool($validated)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                $value = (string) $validated;
            }

            if ($this->isUrlAttribute($tag, $name)) {
                $url = $this->validateUrl($value, $tag, $name);

                if ($url === null) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                $value = $url;
            } elseif ($name === 'srcset') {
                $value = $this->sanitizeSrcset($value);

                if ($value === '') {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif ($name === 'style') {
                $value = $this->sanitizeStyle($value);

                if ($value === '') {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif ($name === 'target') {
                if (!in_array($value, ['_self', '_blank'], true)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif ($name === 'rel') {
                $tokens = preg_split('/\s+/', strtolower(trim($value))) ?: [];
                $tokens = array_intersect(
                    $tokens,
                    ['nofollow', 'noopener', 'noreferrer', 'ugc', 'sponsored']
                );
                $value = implode(' ', array_unique($tokens));

                if ($value === '') {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif ($name === 'id' || $name === 'name') {
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_:.-]{0,127}$/', $value)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif (
                in_array($name, ['width', 'height', 'rows', 'cols', 'size',
                    'colspan', 'rowspan', 'span', 'start', 'tabindex',
                    'minlength', 'maxlength'], true)
            ) {
                if (!preg_match('/^-?\d{1,6}$/', $value)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                $number = (int) $value;

                if (abs($number) > 100_000) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                $value = (string) $number;
            } elseif (in_array($name, ['checked', 'disabled', 'readonly',
                'required', 'multiple', 'selected', 'hidden', 'open',
                'autoplay', 'controls', 'loop', 'muted', 'reversed',
                'default', 'playsinline', 'ismap'], true)) {
                // Normalize boolean attributes to their HTML representation.
                $value = $name;
            } elseif ($name === 'dir') {
                if (!in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                $value = strtolower($value);
            } elseif ($name === 'lang') {
                if (!preg_match('/^[a-zA-Z0-9-]{1,35}$/', $value)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif (str_starts_with($name, 'aria-')) {
                if (!preg_match('/^[a-z][a-z0-9-]{0,63}$/', substr($name, 5))) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            } elseif (str_starts_with($name, 'data-')) {
                if (!preg_match('/^[a-z][a-z0-9_.:-]{0,63}$/', substr($name, 5))) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }
            }

            // DOM handles HTML escaping during serialization.
            $element->setAttribute($name, $value);
        }

        // Avoid opener access when links open a new browsing context.
        if (
            $tag === 'a'
            && strtolower($element->getAttribute('target')) === '_blank'
        ) {
            $rel = preg_split(
                '/\s+/',
                strtolower($element->getAttribute('rel'))
            ) ?: [];

            $rel = array_unique(array_merge(
                $rel,
                ['noopener', 'noreferrer']
            ));

            $element->setAttribute('rel', implode(' ', $rel));
        }
    }

    private function isAttributeAllowed(string $tag, string $name): bool
    {
        if (isset($this->globalAttributes[$name])) {
            return true;
        }

        if (in_array($name, $this->attributes[$tag] ?? [], true)) {
            return true;
        }

        // Custom per-tag configuration may also be supplied as a set.
        if (isset($this->attributes[$tag][$name])) {
            return true;
        }

        if ($name === 'style') {
            return $this->allowInlineStyle;
        }

        if ($this->allowDataAttributes && str_starts_with($name, 'data-')) {
            return true;
        }

        if ($this->allowAriaAttributes && str_starts_with($name, 'aria-')) {
            return true;
        }

        return false;
    }

    private function isUrlAttribute(string $tag, string $name): bool
    {
        if (in_array($name, ['href', 'src', 'action', 'formaction', 'cite'], true)) {
            return true;
        }

        return $name === 'poster'
            || ($tag === 'object' && $name === 'data');
    }

    private function validateUrl(
        string $url,
        string $tag = '',
        string $attribute = ''
    ): ?string {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        // Reject backslashes to avoid parser-dependent URL interpretation.
        if (str_contains($url, '\\')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return $this->allowProtocolRelative ? $url : null;
        }

        // Data URLs are disabled unless explicitly enabled for image sources.
        if (preg_match('/^data:/i', $url)) {
            if (
                !$this->allowDataImages
                || $tag !== 'img'
                || $attribute !== 'src'
            ) {
                return null;
            }

            if (!preg_match(
                '~^data:image/(?:png|gif|jpeg|webp);base64,'
                . '[a-z0-9+/]+=*$~i',
                $url
            )) {
                return null;
            }

            return $url;
        }

        // Detect a scheme even when whitespace is inserted before the colon.
        if (preg_match('/^([a-z][a-z0-9+.-]*)\s*:/i', $url, $matches)) {
            $scheme = strtolower($matches[1]);

            if (!isset($this->schemes[$scheme])) {
                return null;
            }

            // Mail and telephone URLs are valid only in suitable attributes.
            if ($scheme === 'mailto' || $scheme === 'tel') {
                if (!in_array($attribute, ['href'], true)) {
                    return null;
                }
            }

            // Disallow credentials and malformed authority URLs.
            if (in_array($scheme, ['http', 'https'], true)) {
                if (!preg_match('~^https?://~i', $url)) {
                    return null;
                }

                $parts = parse_url($url);

                if (
                    $parts === false
                    || empty($parts['host'])
                    || isset($parts['user'])
                    || isset($parts['pass'])
                ) {
                    return null;
                }
            }

            return $url;
        }

        // Relative, fragment, and query URLs are permitted.
        return $url;
    }

    private function sanitizeSrcset(string $srcset): string
    {
        $result = [];

        // This deliberately conservative parser does not support data URLs
        // inside srcset. Commas in data URLs make naive splitting ambiguous.
        if (stripos($srcset, 'data:') !== false) {
            return '';
        }

        foreach (explode(',', $srcset) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $candidate, 2);

            $url = $this->validateUrl($parts[0], 'img', 'src');

            if ($url === null) {
                continue;
            }

            $descriptor = $parts[1] ?? '';

            if (
                $descriptor !== ''
                && !preg_match(
                    '/^(?:[1-9]\d{0,4}w|(?:\d+(?:\.\d+)?|\.\d+)x)$/',
                    $descriptor
                )
            ) {
                continue;
            }

            $result[] = $url
                . ($descriptor !== '' ? ' ' . $descriptor : '');
        }

        return implode(', ', $result);
    }

    private function sanitizeStyle(string $css): string
    {
        if (
            preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/', $css)
            || preg_match('/url\s*\(|expression|@import|behavior|binding/i', $css)
        ) {
            return '';
        }

        $result = [];

        foreach (explode(';', $css) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);

            $property = strtolower(trim($property));
            $value = trim($value);

            if (
                $value === ''
                || !isset($this->cssProperties[$property])
                || preg_match('/[{}<>\\\\]/', $value)
                || preg_match('/!\s*important/i', $value)
            ) {
                continue;
            }

            // Conservative value grammar: allow common CSS values only.
            if (!preg_match(
                '/^[a-zA-Z0-9#(),.%\s"\'-]{1,200}$/',
                $value
            )) {
                continue;
            }

            $result[] = $property . ': ' . $value;
        }

        return implode('; ', $result);
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function positiveInt(
        array $config,
        string $key,
        int $default
    ): int {
        $value = $config[$key] ?? $default;

        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(
                "{$key} must be a positive integer."
            );
        }

        return $value;
    }

    /**
     * @param array<int, string> $values
     * @return array<string, true>
     */
    private function toSet(array $values): array
    {
        $set = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    'Allowlist entries must be strings.'
                );
            }

            $value = strtolower(trim($value));

            if ($value !== '') {
                $set[$value] = true;
            }
        }

        return $set;
    }
}
