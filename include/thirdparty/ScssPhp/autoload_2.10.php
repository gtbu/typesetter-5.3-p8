<?php

/**
 * Custom Autoloader for ScssPhp and dependencies
 * Maps namespaces to the standard Composer vendor directory structure.
 */
spl_autoload_register(function ($class) {
    // Define the path to your vendor directory.
    // If this script is in the root, __DIR__ . '/vendor' is correct.
    $vendorDir = __DIR__ . '/vendor';

    // Map Namespaces to their specific paths within the vendor folder
    $prefixes = [
        // Main ScssPhp Compiler
        'ScssPhp\\ScssPhp\\' => [
            $vendorDir . '/scssphp/scssphp/src'
        ],
        
        // Dependency: SourceSpan (Fixes your specific error)
        'SourceSpan\\' => [
            $vendorDir . '/scssphp/source-span/src'
        ],

        // Dependency: League URI (Interfaces and Implementation)
        // Both packages use the same namespace "League\Uri"
        'League\\Uri\\' => [
            $vendorDir . '/league/uri',
            $vendorDir . '/league/uri-interfaces'
        ],

        // Dependency: Symfony Filesystem
        'Symfony\\Component\\Filesystem\\' => [
            $vendorDir . '/symfony/filesystem'
        ],
        
        // Polyfills (Optional: usually loaded via files, but mapped here just in case)
        'Symfony\\Polyfill\\Ctype\\' => [
            $vendorDir . '/symfony/polyfill-ctype'
        ],
        'Symfony\\Polyfill\\Mbstring\\' => [
            $vendorDir . '/symfony/polyfill-mbstring'
        ],
    ];

    // PSR-4 Autoloading Logic
    foreach ($prefixes as $prefix => $baseDirs) {
        // Does the class use this namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Try to find the file in the mapped directories
        foreach ($baseDirs as $baseDir) {
            // Replace namespace separators with directory separators
            $file = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// Polyfills often require loading specific bootstrap files manually 
// because they define functions, not just classes.
// If you encounter errors about missing functions like 'mb_strlen', uncomment these:

if (file_exists(__DIR__ . '/vendor/symfony/polyfill-mbstring/bootstrap.php')) {
    require_once __DIR__ . '/vendor/symfony/polyfill-mbstring/bootstrap.php';
}
if (file_exists(__DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php')) {
    require_once __DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php';
}
