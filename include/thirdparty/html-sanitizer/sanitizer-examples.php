The sanitizer.php is, of course, incomplete; it is based on DOMDocument and mbstring
more comprehensive options include HTML Purifier (both versions ! - as required in Composer) 
or the sanitizer from Masterminds.
usage : 

$sanitizer = new HtmlSanitizer([
    'max_input_bytes' => 500_000,
    'max_nodes' => 10_000,
    'max_depth' => 80,
    'max_attributes' => 40,

    // For maximal security deactivated.
    'allow_inline_style' => false,
    'allow_data_images' => false,

    'allow_data_attributes' => true,
    'allow_aria_attributes' => true,
]);

$dirtyHtml = <<<'HTML'
<p class="intro" onclick="alert(1)">
    Hallo <strong>Welt</strong>
    <script>alert('XSS')</script>
    <a href="javascript:alert(1)">Klick mich</a>
    <img src="https://example.com/image.jpg" alt="Beispiel">
</p>
HTML;

echo $sanitizer->sanitize($dirtyHtml);

------------------------------------------------------------------------------------------
Example : declare(strict_types=1);

require_once __DIR__ . '/HtmlSanitizer.php';

$customConfig = [
    'attributes' => [
        'img' => ['src', 'srcset', 'width', 'data-id'],
    ],

    'validators' => [
        'img' => [
            // Only allow numeric widths up to 500.
            'width' => static function ($value): string {
                if (
                    ctype_digit((string) $value)
                    && (int) $value >= 1
                    && (int) $value <= 500
                ) {
                    return (string) (int) $value;
                }

                return '500';
            },

            // Keep only alphanumeric characters and hyphens.
            'data-id' => static function ($value): string {
                return preg_replace(
                    '/[^a-zA-Z0-9-]/',
                    '',
                    (string) $value
                ) ?? '';
            },
        ],
    ],

    // Allow URLs such as //example.com/image.jpg.
    'allow_protocol_relative' => true,

    // Keep data-* attributes enabled.
    'allow_data_attributes' => true,
];

$dirtyHtml = <<<'HTML'
<img
    src="/logo.png"
    width="900"
    data-id="user-profile-123!@#"
    srcset="
        //example.com/small.jpg 300w,
        javascript:alert(1) 600w,
        /large.jpg 1000w
    "
    onclick="alert('XSS')"
>
HTML;

$sanitizer = new HtmlSanitizer($customConfig);
$cleanHtml = $sanitizer->sanitize($dirtyHtml);

// Show the output as text so it cannot execute in the browser.
echo '<h2>Original HTML</h2>';
echo '<pre>' . htmlspecialchars(
    $dirtyHtml,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . '</pre>';

echo '<h2>Sanitized HTML</h2>';
echo '<pre>' . htmlspecialchars(
    $cleanHtml,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . '</pre>';

echo '<h2>Rendered sanitized HTML</h2>';
echo $cleanHtml;

------------------------------------------------------------------------------------------



