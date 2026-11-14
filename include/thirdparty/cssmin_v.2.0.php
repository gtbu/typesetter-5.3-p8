<?php

/**
 * cssmin.php - A simple, conservative CSS minifier.
 * --
 * Provides basic CSS minification by removing comments and unnecessary whitespace.
 *
 * <code>
 * include("cssmin.php");
 * $minifiedCss = cssmin::minify(file_get_contents("path/to/source.css"));
 * file_put_contents("path/to/target.css", $minifiedCss);
 * </code>
 * --
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * --
 *
 * @package     cssmin
 * @author      Joe Scylla <joe.scylla@gmail.com>
 * @copyright   2008 Joe Scylla <joe.scylla@gmail.com> (Modernized 2023)
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 * @version     2.0
 * modified 11/2026 by github.com/gtbu / AI Optimized
 */

class cssmin
{
    /**
     * Minifies CSS conservatively using placeholder extraction.
     *
     * @param mixed $css CSS content as a string.
     * @param bool $debug Optional flag for logging errors.
     * @param bool $keepImportantComments Keep /*! ... *\/ comments.
     * @return string Minified CSS, or an empty string if input is invalid.
     */
    public static function minify($css, $debug = false, $keepImportantComments = true)
    {
        if (!is_string($css)) {
            if ($debug) {
                error_log('cssmin::minify() expected string, got ' . gettype($css));
            }

            return '';
        }

        $css = trim($css);

        if ($css === '') {
            return '';
        }

        // Normalize line endings.
        $css = str_replace(["\r\n", "\r"], "\n", $css);

        // Remove UTF-8 BOM.
        if (strncmp($css, "\xEF\xBB\xBF", 3) === 0) {
            $css = substr($css, 3);
        }

        $placeholders = [];

        try {
            $phPrefix = '___CSSMIN_PH_' . bin2hex(random_bytes(8)) . '_';
        } catch (Throwable $exception) {
            $phPrefix = '___CSSMIN_PH_' . bin2hex(uniqid('', true)) . '_';
        }

        $savePlaceholder = function ($match) use (&$placeholders, $phPrefix) {
            $key = $phPrefix . count($placeholders) . '___';
            $placeholders[$key] = $match[0];

            return $key;
        };

        /*
         * 1. Process comments and quoted strings in one pass.
         *
         * This order avoids the common problem caused by apostrophes
         * inside comments, for example:
         *
         *     /* don't remove this comment *\/
         *
         * Quoted strings are protected before comments are removed.
         * Important comments, written as /*! ... *\/, can be retained.
         */
        $css = preg_replace_callback(
            '~"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|/\*![\s\S]*?\*/|/\*[\s\S]*?\*/~',
            function ($match) use (&$placeholders, $phPrefix, $keepImportantComments) {
                $token = $match[0];

                // Protect quoted strings.
                if ($token[0] === '"' || $token[0] === "'") {
                    $key = $phPrefix . count($placeholders) . '___';
                    $placeholders[$key] = $token;

                    return $key;
                }

                // Optionally preserve important comments.
                if ($keepImportantComments && strpos($token, '/*!') === 0) {
                    return $token;
                }

                // Remove normal comments.
                return '';
            },
            $css
        );

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in comment/string processing: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 2. Protect url(...) values.
         *
         * This prevents changes to data URIs and URLs, for example:
         *
         *     background-image: url(data:image/svg+xml;base64,...);
         */
        $css = preg_replace_callback(
            '~url\(\s*(?:[\'"](?:[^\'"]|\\\\.)*[\'"]|[^)"\'\s][^)]*?)\s*\)~is',
            $savePlaceholder,
            $css
        );

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in url protection: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 3. Protect every CSS function call and functional pseudo-class,
         * including nested parentheses.
         *
         * Examples:
         *
         *     calc(100% - 2 * 10px)
         *     clamp(1rem, 2vw, 3rem)
         *     color-mix(in srgb, red 50%, blue)
         *     linear-gradient(to right, red, blue)
         *     :is(a, b)
         *     :not(.example)
         *     :nth-child(2n + 1)
         */
        $css = preg_replace_callback(
            '/[A-Za-z_-][\w-]*(\((?:[^()]+|(?1))*\))/is',
            $savePlaceholder,
            $css
        );

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in function protection: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 4. Collapse all remaining whitespace to one space.
         *
         * Whitespace is intentionally not removed around ":" globally,
         * because selector whitespace can be meaningful:
         *
         *     a :hover { }
         *
         * is not equivalent to:
         *
         *     a:hover { }
         */
        $css = preg_replace('/\s+/', ' ', $css);

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in whitespace normalization: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 5. Remove whitespace around structural characters.
         *
         * The colon is intentionally not handled globally here.
         */
        $replacements = [
            '/\s*{\s*/' => '{',
            '/\s*}\s*/' => '}',
            '/\s*;\s*/' => ';',
            '/\s*,\s*/' => ',',

            // Remove the final semicolon before a closing brace.
            '/;}/' => '}',

            '/\s*!important\s*/i' => '!important',
        ];

        $css = preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $css
        );

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in structural replacements: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 6. Context-safe declaration colon compression.
         *
         * Removes whitespace only after a colon that starts a declaration:
         *
         *     { color: red; }
         *     ; margin: 0;
         *
         * It does not alter selector colons such as:
         *
         *     a :hover
         *     input :checked
         *     div :not(.example)
         *
         * It also supports custom properties:
         *
         *     { --main-color: blue; }
         */
        $css = preg_replace(
            '/([;{])\s*(--[\w-]+|[A-Za-z_-][\w-]*)\s*:\s*/',
            '$1$2:',
            $css
        );

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in declaration colon compression: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 7. Conservative numeric optimizations.
         *
         * 0.5 -> .5
         * 1.00 -> 1
         *
         * The potentially unsafe optimization "0px -> 0" is intentionally omitted.
         */
        $css = preg_replace('/(?<=[:\s,])0+(\.\d+)/', '$1', $css);
        $css = preg_replace('/(?<=[:\s,])(\d+)\.0+(?=[^\d]|$)/', '$1', $css);

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in numeric optimizations: ' . self::pregLastErrorMessage());
            }

            return '';
        }

        /*
         * 8. Restore protected strings, URLs and function values.
         *
         * Reverse order handles nested placeholders correctly.
         */
        $placeholders = array_reverse($placeholders, true);

        foreach ($placeholders as $key => $value) {
            $css = str_replace($key, $value, $css);
        }

        return trim($css);
    }

    /**
     * Turns preg_last_error() into a readable message.
     *
     * @return string
     */
    protected static function pregLastErrorMessage()
    {
        $code = function_exists('preg_last_error') ? preg_last_error() : null;

        if ($code === null) {
            return 'Unknown PCRE error';
        }

        switch ($code) {
            case PREG_NO_ERROR:
                return 'No error';

            case PREG_INTERNAL_ERROR:
                return 'Internal PCRE error';

            case PREG_BACKTRACK_LIMIT_ERROR:
                return 'Backtrack limit reached';

            case PREG_RECURSION_LIMIT_ERROR:
                return 'Recursion limit reached';

            case PREG_BAD_UTF8_ERROR:
                return 'Malformed UTF-8 data';

            case PREG_BAD_UTF8_OFFSET_ERROR:
                return 'Bad UTF-8 offset';

            case defined('PREG_JIT_STACKLIMIT_ERROR') ? PREG_JIT_STACKLIMIT_ERROR : -1:
                return 'JIT stack limit reached';

            default:
                return 'PCRE error code ' . $code;
        }
    }
}