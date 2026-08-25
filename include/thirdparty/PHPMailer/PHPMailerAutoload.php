<?php

/**
 * PHPMailer SPL autoloader.
 * @param string $classname The name of the class to load
 * but new : namespace PHPMailer\PHPMailer; 
 */
function PHPMailerAutoload($classname) {
    $prefix = 'PHPMailer\\PHPMailer\\';

    if (strncmp($classname, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($classname, strlen($prefix));
    $filename = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_readable($filename)) {
        require $filename;
    }
}

spl_autoload_register('PHPMailerAutoload', true, true);