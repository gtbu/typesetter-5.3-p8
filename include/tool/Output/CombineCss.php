<?php

namespace gp\tool\Output;

defined('is_running') or die('Not an entry point...');

class CombineCSS
{
    public $combined_content_raw = '';
    public $final_content = '';
    public $prepended_imports = '';
    public $processed_files = [];

    private $entry_file_path;
    private $minify_output;

    private $install_root_real;
    private $public_base_path;

    public function __construct($file, $minify = true)
    {
        global $dataDir;

        $this->minify_output = (bool)$minify;

        $data_dir_norm = rtrim(str_replace('\\', '/', (string)$dataDir), '/');

        $this->install_root_real = realpath($data_dir_norm);

        if ($this->install_root_real === false || !is_dir($this->install_root_real)) {
            trigger_error(
                'CombineCSS: Installation root could not be resolved: '
                . htmlspecialchars((string)$dataDir),
                E_USER_WARNING
            );

            $this->final_content = '/* CombineCSS Error: Installation root could not be resolved. */';
            return;
        }

        $this->install_root_real = str_replace('\\', '/', $this->install_root_real);

        $this->public_base_path = $this->detectPublicBasePath();

        $this->entry_file_path = ltrim(str_replace('\\', '/', (string)$file), '/');

        $full_path = $this->install_root_real . '/' . $this->entry_file_path;
        $real_full_path = realpath($full_path);

        if ($real_full_path === false || !is_file($real_full_path)) {
            trigger_error(
                'CombineCSS: Entry file not found: ' . htmlspecialchars($full_path),
                E_USER_WARNING
            );

            $this->final_content = '/* CombineCSS Error: Entry file not found: '
                . htmlspecialchars((string)$file) . ' */';
            return;
        }

        if ($this->minify_output && !class_exists('\\cssmin')) {
            includeFile('thirdparty/cssmin_v.1.0.php');

            if (!class_exists('\\cssmin')) {
                trigger_error(
                    'CombineCSS: cssmin class not found; minification disabled.',
                    E_USER_WARNING
                );

                $this->minify_output = false;
            }
        }

        $this->processed_files = [];
        $this->prepended_imports = '';

        $this->combined_content_raw = $this->processFile($real_full_path);
        $this->combined_content_raw = $this->prepended_imports . $this->combined_content_raw;

        if ($this->minify_output && class_exists('\\cssmin')) {
            try {
                $content_size_bytes = strlen($this->combined_content_raw);
                $first_chunk = substr($this->combined_content_raw, 0, 15);
                $space_count = substr_count($first_chunk, ' ');

                if ($content_size_bytes >= 10 * 1024 && $space_count < 3) {
                    $this->final_content = $this->combined_content_raw;
                } else {
                    $this->final_content = \cssmin::minify($this->combined_content_raw);
                }
            } catch (\Throwable $e) {
                error_log('CSS Minification failed: ' . $e->getMessage());
                $this->final_content = $this->combined_content_raw;
            }
        } else {
            $this->final_content = $this->combined_content_raw;
        }
    }

    /**
     * Read a CSS file, inline local imports where possible,
     * and rewrite relative url() references.
     */
    private function processFile($real_path)
    {
        $real_path = realpath($real_path);

        if ($real_path === false) {
            return '/* CombineCSS Error: CSS file path could not be resolved. */';
        }

        $file_key = str_replace('\\', '/', $real_path);

        if (isset($this->processed_files[$file_key])) {
            trigger_error(
                'CombineCSS: Recursive import detected and skipped: '
                . htmlspecialchars($file_key),
                E_USER_WARNING
            );

            return '/* CombineCSS Error: Recursive import skipped: '
                . htmlspecialchars(basename($file_key)) . ' */';
        }

        $this->processed_files[$file_key] = true;

        $content = @file_get_contents($real_path);

        if ($content === false) {
            trigger_error(
                'CombineCSS: Could not read file: ' . htmlspecialchars($real_path),
                E_USER_WARNING
            );

            return '/* CombineCSS Error: Could not read file: '
                . htmlspecialchars(basename($real_path)) . ' */';
        }

        $source_dir = dirname($real_path);
        $output_buffer = '';
        $offset = 0;

        $import_pattern = '/@import\s+(?:url\(\s*([\'\"]?)(.*?)\1\s*\)|([\'\"])(.*?)\3)\s*([^;]*);/i';

        while (preg_match($import_pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {

            $full_match = $matches[0][0];
            $match_start = $matches[0][1];

            $content_before = substr($content, $offset, $match_start - $offset);
            $output_buffer .= $this->fixUrlsInBlock($content_before, $source_dir);

            $url_from_url_function = isset($matches[2][0]) ? $matches[2][0] : '';
            $url_from_quotes = isset($matches[4][0]) ? $matches[4][0] : '';

            $import_url = trim(
                $url_from_url_function !== ''
                    ? $url_from_url_function
                    : $url_from_quotes
            );

            $media_query = trim(isset($matches[5][0]) ? $matches[5][0] : '');

            $is_remote = (bool)preg_match('#^(https?:|//)#i', $import_url);
            $has_media = ($media_query !== '');

            if ($is_remote || $has_media) {

                $resolved_import_url = $import_url;

                if (!$is_remote && !$this->isAbsoluteUrl($import_url)) {
                    $resolved_import_url = $this->resolvePath($import_url, $source_dir);
                }

                $this->prepended_imports .= '@import url("'
                    . $resolved_import_url . '")'
                    . ($has_media ? ' ' . $media_query : '')
                    . ";\n";

            } else {

                if ($this->isRootRelativeUrl($import_url)) {
                    $import_full_path = $this->install_root_real . '/' . ltrim($import_url, '/');
                } else {
                    $import_full_path = $source_dir . '/' . $import_url;
                }

                $import_real_path = realpath($this->normalizePath($import_full_path));

                if ($import_real_path !== false && is_file($import_real_path)) {
                    $output_buffer .= $this->processFile($import_real_path);
                } else {
                    trigger_error(
                        'CombineCSS: Imported file not found: '
                        . htmlspecialchars($import_full_path)
                        . ' (referenced in ' . htmlspecialchars($real_path) . ')',
                        E_USER_WARNING
                    );

                    $output_buffer .= '/* CombineCSS Error: Import not found: '
                        . htmlspecialchars($import_url) . ' */';
                }
            }

            $offset = $match_start + strlen($full_match);
        }

        $content_after = substr($content, $offset);
        $output_buffer .= $this->fixUrlsInBlock($content_after, $source_dir);

        return $output_buffer;
    }

    /**
     * Rewrite relative url() references to public URLs.
     */
    private function fixUrlsInBlock($content_block, $source_dir)
    {
        if ($content_block === '' || stripos($content_block, 'url(') === false) {
            return $content_block;
        }

        $url_pattern = '/url\(\s*([\'\"]?)(.*?)\1\s*\)/i';

        return preg_replace_callback(
            $url_pattern,
            function ($matches) use ($source_dir) {

                $original_match = $matches[0];
                $url = trim($matches[2]);

                if (
                    $url === ''
                    || $this->isAbsoluteUrl($url)
                    || strncasecmp($url, 'data:', 5) === 0
                ) {
                    return $original_match;
                }

                return 'url("' . $this->resolvePath($url, $source_dir) . '")';
            },
            $content_block
        );
    }

    /**
     * Resolve a CSS-relative URL to a public URL.
     */
    private function resolvePath($relative_url, $source_dir)
    {
        $query_fragment = '';
        $path_only = $relative_url;

        $query_pos = strpos($relative_url, '?');
        $fragment_pos = strpos($relative_url, '#');

        if ($query_pos !== false && ($fragment_pos === false || $query_pos < $fragment_pos)) {
            $split_pos = $query_pos;
        } elseif ($fragment_pos !== false) {
            $split_pos = $fragment_pos;
        } else {
            $split_pos = false;
        }

        if ($split_pos !== false) {
            $query_fragment = substr($relative_url, $split_pos);
            $path_only = substr($relative_url, 0, $split_pos);
        }

        if ($this->isAbsoluteUrl($path_only)) {
            return $relative_url;
        }

        $combined_path = $source_dir . '/' . $path_only;

        $reduced_path = $this->reducePath($this->normalizePath($combined_path));
        $real_path = realpath($reduced_path);

        if ($real_path === false || !is_file($real_path)) {
            return $path_only . $query_fragment;
        }

        $real_path = str_replace('\\', '/', $real_path);

        $relative_to_install = $this->relativePathFromInstallRoot($real_path);

        if ($relative_to_install === false) {
            /*
             * Datei liegt außerhalb der Typesetter-Installation.
             * Die ursprüngliche relative URL bleibt erhalten.
             */
            return $path_only . $query_fragment;
        }

        return $this->public_base_path
            . $this->encodeUrlPath($relative_to_install)
            . $query_fragment;
    }

    /**
     * Determine the public URL prefix of the Typesetter installation.
     *
     * Examples:
     *   https://example.com/index.php       -> /
     *   https://example.com/blog/index.php  -> /blog/
     */
    private function detectPublicBasePath()
    {
        $script = '';

        if (isset($_SERVER['SCRIPT_NAME']) && $_SERVER['SCRIPT_NAME'] !== '') {
            $script = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] !== '') {
            $script = $_SERVER['PHP_SELF'];
        }

        if ($script === '') {
            return '/';
        }

        $script = str_replace('\\', '/', $script);

        $base = dirname($script);

        if ($base === '.' || $base === '/' || $base === '\\') {
            return '/';
        }

        return rtrim($base, '/') . '/';
    }

    /**
     * Get a file path relative to the Typesetter installation root.
     */
    private function relativePathFromInstallRoot($target_path)
    {
        $target_path = str_replace('\\', '/', $target_path);
        $install_root = rtrim(str_replace('\\', '/', $this->install_root_real), '/');

        if (strpos($target_path, $install_root . '/') === 0) {
            return substr($target_path, strlen($install_root) + 1);
        }

        return false;
    }

    /**
     * Encode each path segment for safe use in a URL.
     */
    private function encodeUrlPath($path)
    {
        $parts = explode('/', $path);

        foreach ($parts as $i => $part) {
            $parts[$i] = rawurlencode($part);
        }

        return implode('/', $parts);
    }

    private function normalizePath($path)
    {
        return preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    }

    private function reducePath($path)
    {
        $parts = explode('/', $path);
        $result = [];

        $is_absolute = isset($path[0]) && $path[0] === '/';

        foreach ($parts as $part) {

            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {

                if (!empty($result) && end($result) !== '..') {
                    array_pop($result);
                } elseif (!$is_absolute) {
                    $result[] = '..';
                }

            } else {
                $result[] = $part;
            }
        }

        $final_path = implode('/', $result);

        if ($is_absolute) {
            return '/' . $final_path;
        }

        return $final_path === '' ? '.' : $final_path;
    }

    private function isAbsoluteUrl($url)
    {
        return (bool)preg_match('#^(//|https?:|data:)#i', $url);
    }

    private function isRootRelativeUrl($url)
    {
        return isset($url[0]) && $url[0] === '/';
    }
}