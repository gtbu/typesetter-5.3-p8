<?php

/**
 * cssmin.php - A simple CSS minifier.
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
 * @version     1.0.2
 * modified 2026 by github.com/gtbu
 */

class cssmin
{
    /**
     * Minifies CSS safely using a placeholder extraction pattern.
     *
     * Design goals:
     * - Never touch content inside strings, url(...), calc()/var()/etc.
     * - Conservative whitespace removal (favor correctness over maximum compression).
     * @param mixed $css CSS content as a string.
     * @param bool $debug Optional flag for logging errors.
     * @param bool $keepImportantComments Keep comments  as / *! ... * / 
     * @return string Minified CSS or an empty string if input invalid.
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

        // Normalize line endings
        $css = str_replace(["\r\n", "\r"], "\n", $css);

        // Remove UTF-8 BOM
        if (strncmp($css, "\xEF\xBB\xBF", 3) === 0) {
            $css = substr($css, 3);
        }

        $placeholders = [];
        $phPrefix = '___CSSMIN_PH_' . uniqid('', true) . '_';

        $savePlaceholder = function ($match) use (&$placeholders, $phPrefix) {
            $key = $phPrefix . count($placeholders) . '___';
            $placeholders[$key] = $match[0];
            return $key;
        };

        // 1. Protect strings and url(...)
        // Slightly tightened url() pattern; still conservative.
        $css = preg_replace_callback(
            '~(?:url\(\s*(?:[\'"](?:[^\'"]|\\\\.)*[\'"]|[^)"\'\s][^)]*?)\s*\)|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')~is',
            $savePlaceholder,
            $css
        );
        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in string/url protection: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 2. Protect calc(), clamp(), min(), max(), var(), env() (with nested parentheses)
        $css = preg_replace_callback(
            '/(?:calc|clamp|min|max|var|env)(\((?:[^()]+|(?1))*\))/is',
            $savePlaceholder,
            $css
        );
        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in function protection: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 3. Remove comments
        if ($keepImportantComments) {
            // Keep /*! ... */ comments, remove others.
            $css = preg_replace_callback(
                '/\/\*![\s\S]*?\*\/|\/\*[\s\S]*?\*\//',
                function ($m) {
                    // If it starts with /*! keep it, otherwise remove
                    return (strpos($m[0], '/*!') === 0) ? $m[0] : '';
                },
                $css
            );
        } else {
            // Remove all comments (non-greedy to avoid backtracking explosions)
            $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
        }
        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in comment removal: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 4. Whitespace cleanup
        $css = preg_replace('/\s+/', ' ', $css);
        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in whitespace normalization: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 5. Remove spaces around structural characters
        $replacements = [
            '/\s*{\s*/'         => '{',
            '/\s*}\s*/'         => '}',
            '/\s*;\s*/'         => ';',
            '/\s*,\s*/'         => ',',
            '/\s*:\s*/'         => ':',
            '/;}/'              => '}',       // Remove last semicolon in a block
            '/\s*!important\s*/i' => '!important',
        ];
        $css = preg_replace(array_keys($replacements), array_values($replacements), $css);
        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in structural replacements: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 6. Micro-optimizations (conservative)
        // 0.5 -> .5
        $css = preg_replace('/(?<=[:\s,])0+(\.\d+)/', '$1', $css);
        // 1.00 -> 1
        $css = preg_replace('/(?<=[:\s,])(\d+)\.0+(?=[^\d]|$)/', '$1', $css);

        if ($css === null) {
            if ($debug) {
                error_log('cssmin::minify() failed in numeric optimizations: ' . self::pregLastErrorMessage());
            }
            return '';
        }

        // 7. Restore placeholders in reverse order
        $placeholders = array_reverse($placeholders, true);
        foreach ($placeholders as $key => $value) {
            $css = str_replace($key, $value, $css);
        }

        return trim($css);
    }

    /**
     * Helper to turn preg_last_error() into a readable message.
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
