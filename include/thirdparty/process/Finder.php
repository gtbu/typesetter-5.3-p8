<?php

/**
 * Original Author: Mehrdad Dadkhah <https://github.com/Mehrdad-Dadkhah/php-file-finder>
 * Copyright (c) 2016 Mehrdad Dadkhah
 * 
 * Modified by: [github.com/gtbu]
 * Modification Date: 12/2025
 * Changes: 
 * - Updated namespace to gp_file\file
 * - Updated for Symfony Process 7.4 compatibility
 * - Replaced shell piping with PHP filtering for security
 * - Added explicit 'finder' case handling in getFindCommand
 * - Added configurable process timeouts with validation
 * - Added strict type hinting
 * 
 * Licensed under the GNU General Public License v3.0
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\ProcessFailedException;

class Finder
{
    private string $finder = 'find';
    
    /**
     * @var int Process timeout in seconds
     */
    private int $timeout;

    /**
     * @param int $timeout The timeout in seconds. Must be > 0.
     * @throws \InvalidArgumentException If timeout is not positive.
     */
    public function __construct(int $timeout = 60)
    {
        $this->validateTimeout($timeout);
        $this->timeout = $timeout;
    }

    public function setFinder(string $finder): self
    {
        $this->finder = $finder;
        return $this;
    }
    
    /**
     * Update the timeout after instantiation.
     */
    public function setTimeout(int $timeout): self
    {
        $this->validateTimeout($timeout);
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Internal helper to validate timeout values.
     */
    private function validateTimeout(int $timeout): void
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be a positive integer to prevent indefinite blocking.');
        }
    }

    public function getFindCommand(string $path, string $fileName): array
    {
        switch ($this->finder) {
            case 'locate':
                return ['locate', $fileName];
            
            case 'finder':
            case 'find':
            default:
                return ['find', $path, '-name', $fileName];
        }
    }

    /**
     * Added strict type hint for $info
     */
    public function findFile(string $file, string $searchPath = '/home', bool $info = true): array
    {
        $fileInfo = new \SplFileInfo($file);
        
        // Logic to handle searching inside a specific found directory
        if (!empty($fileInfo->getPath()) && $this->finder == 'finder') {
            $foundPaths = $this->findDirectoryPath($fileInfo->getPath(), $searchPath);
            
            if (!empty($foundPaths)) {
                $searchPath = reset($foundPaths); 
            }
            
            $file = $fileInfo->getBaseName();
        }

        $commandArgs = $this->getFindCommand($searchPath, $file);

        $process = new Process($commandArgs);
        $process->setTimeout($this->timeout); 
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $files = $this->listResult($process->getOutput());

        if ($info) {
            return $this->makeData($files);
        }

        return $files;
    }

    public function findDirectoryPath(string $path, string $searchPath = '/'): array
    {
        $directories = explode('/', $path);
        $directoryName = end($directories);

        $commandArgs = $this->getFindCommand($searchPath, $directoryName);
        
        $process = new Process($commandArgs);
        $process->setTimeout($this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $allResults = $this->listResult($process->getOutput());

        // Filter results in PHP to ensure path matches
        $filteredResults = array_filter($allResults, function($resultLine) use ($path) {
            return str_contains($resultLine, $path);
        });

        return array_values($filteredResults);
    }

    private function listResult(string $output): array
    {
        $output = trim($output);
        
        if (empty($output)) {
            return [];
        }

        return explode("\n", $output);
    }

    public function makeData(array $files): array
    {
        $data = [];

        foreach ($files as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            $fileInfo = new \SplFileInfo($filePath);

            $data[] = [
                'path' => $fileInfo->getPath(),
                'filename' => $fileInfo->getFilename(),
                'realpath' => $fileInfo->getRealpath(),
                'extension' => $fileInfo->getExtension(),
                'type' => $fileInfo->getType(),
                'mime_type' => @mime_content_type($filePath) ?: 'unknown', 
                'size' => $fileInfo->getSize(),
                'isFile' => $fileInfo->isFile(),
                'isDir' => $fileInfo->isDir(),
                'isLink' => $fileInfo->isLink(),
                'writable' => $fileInfo->isWritable(),
                'readable' => $fileInfo->isReadable(),
                'executable' => $fileInfo->isExecutable(),
            ];
        }

        return $data;
    }
}
