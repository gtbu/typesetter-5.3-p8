
<?php
// autoloader for process
// require __DIR__ . '/autoload.php';
// use Symfony\Component\Process\Pipes\WindowsPipes;
// use Symfony\Component\Process\Exception\ProcessFailedException;
// use Symfony\Component\Process\Process;
// $process = new Process(['ls']);
// $process->run();

spl_autoload_register(function (string $class): void {
    $prefix = 'Symfony\\Component\\Process\\';
    $procdir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // Example: Symfony\Component\Process\Exception\ProcessFailedException
    // becomes process/Exception/ProcessFailedException.php
    $file = $procdir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use Symfony\Component\Process\Pipes\WindowsPipes;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;