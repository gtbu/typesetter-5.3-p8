<?php

declare(strict_types=1);
/** Add this code to the very top of your sanitizer.php (before using the class). 
/* The autoloader will automatically translate the namespace Masterminds\... into the corresponding file path:
**/

// PSR-4 compliant autoloader for the "Masterminds\" namespace

spl_autoload_register(function (string $class): void {
    // The library's namespace prefix
    $prefix = 'Masterminds\\';

    // The base directory in which the Masterminds files are located
    $baseDir = __DIR__ . '/src/Masterminds/';

    // Check whether the invoked class has the prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Giving precedence to other autoloaders
    }

    // Extract relative class names (e.g., "HTML5" or "Parser\HTML5")
    $relativeClass = substr($class, $len);

    // Convert namespace separators into file separators (/) and append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Load the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});

// Ab hier kann die Klasse direkt genutzt werden:
use Masterminds\HTML5;

$html5Parser = new HTML5();
// ... Restlicher Code ...