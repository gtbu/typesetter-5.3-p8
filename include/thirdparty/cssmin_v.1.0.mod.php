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
     * Minifies CSS definitions.
     *
     * @param mixed $css CSS content as a string. Accepts mixed for basic type check.
     * @return string Minified CSS definitions, or an empty string if input is invalid or empty.
     */
    public static function minify($css)
    {
        // Basic input validation: Ensure it's a string.
        if (!is_string($css)) {
             // error_log('cssmin::minify() expected a string, got ' . gettype($css)); // Optional logging
             return ''; // Return empty string for invalid input
        }

        // 1. Initial cleanup: Remove leading/trailing whitespace and normalize line endings.
        $css = trim($css);
        if ($css === '') {
            return ''; // Return early if string is empty after trimming
        }
        $css = str_replace("\r\n", "\n", $css); // Normalize line endings to LF

        // 2. First round of regex replacements:
        //    - Remove comments (/* ... */)
        //    - Remove tabs
        //    - Collapse multiple whitespace chars into a single space
        //    - Remove whitespace after '}' but add a newline (for structure before next step)
        $search = array(
            "/\/\*[\s\S]*?\*\//", // Remove /* ... */ comments. [\s\S] matches any char incl. newline. *? is non-greedy.
            "/\t+/",             // Remove tabs.
            "/\s+/",             // Collapse whitespace (includes space, tab, newline) into a single space.
            "/\}\s+/"            // Remove whitespace following a '}' and add a newline.
        );
        $replace = array(
            "",                  // Remove comments.
            "",                  // Remove tabs.
            " ",                 // Collapse whitespace to a single space.
            "}\n"                // Add newline after closing brace.
        );
        $css = preg_replace($search, $replace, $css);

        // Check if preg_replace failed (returned null)
        if ($css === null) {
            // error_log('cssmin::minify() preg_replace step 1 failed'); // Optional logging
            return ''; // Return empty on regex error
        }


        // 3. Second round of regex replacements:
        //    - Remove whitespace around critical CSS characters: ;, {, :, #, ,, '
        //    - Remove whitespace between ':' and simple values (keywords, numbers).
        $search = array(
            "/;\s+/",                 // Remove whitespace after semicolons (e.g., "; " => ";").
            "/\s*\{\s*/",            // Remove whitespace around opening braces (e.g., " { " => "{").
            "/:\s+#/",               // Remove whitespace after colon before # (e.g., ": #" => ":#").
            "/,\s+/",                // Remove whitespace after commas (e.g., ", " => ",").
            "/:\s+'/",               // Remove whitespace after colon before single quotes (e.g., ": '" => ":'").
            "/:\s+\"/",              // Remove whitespace after colon before double quotes (e.g., ': "' => ':"'). (Added for consistency)
            "/:\s+([a-zA-Z0-9\-]+)/i" // Remove whitespace after colon before common values (keywords, numbers, units like 'px'). Case-insensitive.
                                     // (e.g., "color: red" => "color:red", "margin: 10px" => "margin:10px"). Uses backreference $1.
        );
        $replace = array(
            ";",                     // ;
            "{",                     // {
            ":#",                    // :#
            ",",                     // ,
            ":'",                    // :'
            ":\"",                   // :" (Added)
            ":$1"                    // :value (using backreference)
        );
        $css = preg_replace($search, $replace, $css);

        // Check if preg_replace failed (returned null)
        if ($css === null) {
            // error_log('cssmin::minify() preg_replace step 2 failed'); // Optional logging
            return ''; // Return empty on regex error
        }

        // 4. Final step: Remove all remaining newline characters.
        //    This contradicts step 2 adding newlines after '}', but matches the original code's behavior
        //    resulting in a single-line output.
        $css = str_replace("\n", "", $css);

        // Final trim just in case (though unlikely needed after previous steps)
        return trim($css);
    }
}