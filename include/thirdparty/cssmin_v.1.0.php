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
 * modified 2025 by github.com/gtbu
 */
 
class cssmin
{
    /**
     * Minifies CSS safely using a placeholder extraction pattern.
     *
     * @param mixed $css CSS content as a string.
     * @param bool $debug Optional flag for logging errors.
     * @return string Minified CSS or an empty string if input invalid.
     */
    public static function minify($css, $debug = false)
    {
        if (!is_string($css)) {
            if ($debug) error_log('cssmin::minify() expected string, got ' . gettype($css));
            return '';
        }

        $css = trim($css);
        if ($css === '') return '';

        $placeholders =[];
        // Unique prefix to prevent accidental overlaps
        $phPrefix = '___CSSMIN_PH_' . uniqid() . '_';

        // HELPFUL FUNCTION: Saves matches and returns placeholders
        $savePlaceholder = function ($match) use (&$placeholders, $phPrefix) {
            $key = $phPrefix . count($placeholders) . '___';
            $placeholders[$key] = $match[0];
            return $key;
        };

        // 1. SCHUTZ: Extracts Strings ("..." und '...') and url(...) 
        // This protects content such as `content: " : "` or Data-URIs (base64)
        $css = preg_replace_callback(
            '/(?:url\(\s*[\'"]?(?:[^\'"\)]+)[\'"]?\s*\)|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/is',
            $savePlaceholder,
            $css
        );

        // 2. PROTECTION: Extract CSS functions containing mathematical or variable content
        // Protects calc(), clamp(), min(), max(), var(), env() including nested brackets! 
        // (?1) is a recursive regex call for nested brackets in PCRE.
        $css = preg_replace_callback(
            '/(?:calc|clamp|min|max|var|env)(\((?:[^)(]+|(?1))*\))/is',
            $savePlaceholder,
            $css
        );

        // 3. REMOVE COMMENTS
        // Since strings are now safe, we can safely delete all comments.
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // 4. WHITESPACES BEREINIGEN
        // Make all whitespace into a single space
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around structural characters
        $replacements =[
            '/\s*{\s*/' => '{',
            '/\s*}\s*/' => '}',
            '/\s*;\s*/' => ';',
            '/\s*,\s*/' => ',',
            '/\s*:\s*/' => ':',
            '/;}/'      => '}', // Remove the last semicolon
            '/\s*!important\s*/i' => '!important',
        ];
        $css = preg_replace(array_keys($replacements), array_values($replacements), $css);

        // 5. MICRO-OPTIMIZATIONS (Safe)
        // 0.5 -> .5 (safe)
        $css = preg_replace('/(?<=[:\s,])0+\.(\d+)/', '.$1', $css);
        // 1.00 -> 1 (sicher)
        $css = preg_replace('/(?<=[:\s,])(\d+)\.0+(?=[^\d]|$)/', '$1', $css);
        
        // NOTE: The rule "0px -> 0" has been intentionally REMOVED!
        // It saves hardly any bytes, but breaks CSS variables and Flexbox in older browsers.


        // 6. RESTORE PLACEHOLDER
        // In reverse order, if placeholders were nested within each other.
        $placeholders = array_reverse($placeholders, true);
        foreach ($placeholders as $key => $value) {
            $css = str_replace($key, $value, $css);
        }

        return trim($css);
    }
}