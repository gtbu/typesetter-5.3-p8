<?php

namespace gp\tool\Output;

use ScssPhp\ScssPhp\Compiler as ScssCompiler;
use ScssPhp\ScssPhp\Type;

class Scss {
    private ScssCompiler $compiler;
    public $url_root = '';

    public function __construct(array $options = []) {
        $this->compiler = new ScssCompiler($options);
    }

    /**
     * Intercept compileValue() to fix background:url(path)
     */
    public function compileValue($value, $quote = true) {
        if (!is_array($value) || $value[0] != Type::T_FUNCTION || strtolower($value[1]) != 'url') {
            return $this->compiler->compileValue($value, $quote);
        }

        $arg = !empty($value[2]) ? $this->compileValue($value[2]) : '';
        $arg = trim($arg);

        if (empty($arg)) {
            return "{$value[1]}($arg)";
        }

        $arg = $this->FixRelative($arg);

        return "{$value[1]}($arg)";
    }

    /**
     * Fix a relative path
     */
    public function FixRelative($path) {
        $quote = '';
        if ($path[0] == '"') {
            $quote = '"';
            $path = trim($path, '"');
        } elseif ($path[0] == "'") {
            $quote = "'";
            $path = trim($path, "'");
        }

        if (self::isPathRelative($path)) {
            $path = $this->url_root . '/' . $path;
            $path = self::normalizePath($path);
        }

        return $quote . $path . $quote;
    }

    public static function isPathRelative($path) {
        return !preg_match('/^(?:[a-z-]+:|\/)/', $path);
    }

    /**
     * Canonicalize a path by resolving references to '/./', '/../'
     * Does not remove leading "../"
     * @param string path or url
     * @return string Canonicalized path
     */
    public static function normalizePath($path) {
        $segments = explode('/', $path);
        $segments = array_reverse($segments);

        $path = array();
        $path_len = 0;

        while ($segments) {
            $segment = array_pop($segments);
            switch ($segment) {
                case '.':
                    break;

                case '..':
                    if (!$path_len || ($path[$path_len - 1] === '..')) {
                        $path[] = $segment;
                        $path_len++;
                    } else {
                        array_pop($path);
                        $path_len--;
                    }
                    break;

                default:
                    $path[] = $segment;
                    $path_len++;
                    break;
            }
        }

        return implode('/', $path);
    }

    // Essential delegations - add more as needed
    public function compile(string $source): string {
        return $this->compiler->compile($source);
    }

    public function setImportPaths(array $paths): void {
        $this->compiler->setImportPaths($paths);
    }

    public function addImportPath(string $path): void {
        $this->compiler->addImportPath($path);
    }

    public function setNumberPrecision(int $numberPrecision): void {
        $this->compiler->setNumberPrecision($numberPrecision);
    }

    // Forward any other Compiler methods you use to $this->compiler
    public function __call($method, $args) {
        if (method_exists($this->compiler, $method)) {
            return $this->compiler->$method(...$args);
        }
        throw new \BadMethodCallException("Method $method does not exist");
    }
	
}
