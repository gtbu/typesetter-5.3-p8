<?php

/**
 * Original Author: Mehrdad Dadkhah <https://github.com/Mehrdad-Dadkhah/php-file-finder>
 * Copyright (c) 2016 Mehrdad Dadkhah
 * Licensed under the GNU General Public License v3.0
 * Modified by: [github.com/gtbu]
 * Modification Date: 12/2025
 * Changes: 
 * - Updated namespace to gp_file\file
 * - Updated for Symfony Process 7.4 compatibility
 * - Replaced shell piping with PHP filtering for security
 * - Added explicit 'finder' case handling in getFindCommand
 * - Added configurable process timeouts with validation
 * - Added strict type hinting
 * - Added "Jail" (Root Directory) enforcement to prevent Path Traversal.
 * - Replaced shell piping with PHP filtering.
 * - strict typing and modern PHP 8 features.
 * - Uses 'finfo' for reliable MIME detection.
 * - Graceful handling of non-existent search paths.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\ProcessFailedException;

class Finder
{
    private string $finder = 'find';
    
    /**
     * @var int Process timeout in seconds.
     * Non-nullable to ensure the process always has a limit.
     */
    private int $timeout;

    /**
     * @var string The absolute path that acts as a "Jail". 
     * Searches cannot go above this directory.
     */
    private string $rootDirectory;

    /**
     * @param string|null $rootDirectory The allowed base directory. Defaults to current working dir.
     * @param int $timeout Timeout in seconds.
     */
    public function __construct(?string $rootDirectory = null, int $timeout = 60)
    {
        $this->validateTimeout($timeout);
        $this->timeout = $timeout;

        // SECURITY: Default to the current directory if none specified.
        // We resolve this immediately to ensure the object is always in a valid state.
        $this->setRootDirectory($rootDirectory ?? getcwd());
    }
    
    public function setRootDirectory(string $path): self
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_dir($realPath)) {
            throw new \InvalidArgumentException("Root directory does not exist: $path");
        }
        $this->rootDirectory = $realPath;
        return $this;
    }

    public function setFinder(string $finder): self
    {
        // STRICT SECURITY: Only allow 'find' or 'finder'.
        // This prevents executing arbitrary system binaries.
        $allowed = ['find', 'finder'];

        if (!in_array($finder, $allowed)) {
           throw new \InvalidArgumentException("Security Error: The finder type '$finder' is not allowed.");
        }

        $this->finder = $finder;
        return $this;
    }   
    
    public function setTimeout(int $timeout): self
    {
        $this->validateTimeout($timeout);
        $this->timeout = $timeout;
        return $this;
    }

    private function validateTimeout(int $timeout): void
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be a positive integer.');
        }
    }

    /**
     * Internal security check to prevent Path Traversal.
     * Returns the absolute path if valid, or NULL if the directory doesn't exist.
     * Throws Exception if path exists but is outside the root.
     */
    private function resolveSafePath(string $path): ?string
    {
        // 1. Resolve absolute path (handles ../../)
        $realPath = realpath($path);

        // 2. Graceful Fail: If directory doesn't exist, we can't search it.
        // Returning null allows the caller to return [] instead of crashing.
        if ($realPath === false) {
             return null;
        }

        // 3. SECURITY: Check if the resolved path starts with the Root Directory
        if (!str_starts_with($realPath, $this->rootDirectory)) {
            throw new \RuntimeException("Access Denied: Cannot search outside the allowed root directory.");
        }

        return $realPath;
    }

    public function getFindCommand(string $path, string $fileName): array
    {
        // SECURITY: Ensure filename is just a name, not a path.
        // This prevents commands like: find /var/www -name ../../etc/passwd
        $cleanFileName = basename($fileName);

        // Use $this->finder so setFinder() works
        return [$this->finder, $path, '-name', $cleanFileName];
    }

    public function findFile(string $file, string $searchPath = '.', bool $info = true): array
    {
        // 1. Validate Path
        $safeSearchPath = $this->resolveSafePath($searchPath);

        // If directory is missing, return empty result immediately
        if ($safeSearchPath === null) {
            return [];
        }

        // 2. Sanitize Filename
        $fileBaseName = basename($file);

        $commandArgs = $this->getFindCommand($safeSearchPath, $fileBaseName);

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

    public function findDirectoryPath(string $path, string $searchPath = '.'): array
    {
        $safeSearchPath = $this->resolveSafePath($searchPath);

        if ($safeSearchPath === null) {
            return [];
        }
        
        // Extract just the folder name we are looking for
        $directoryName = basename($path);

        $commandArgs = $this->getFindCommand($safeSearchPath, $directoryName);
        
        $process = new Process($commandArgs);
        $process->setTimeout($this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $allResults = $this->listResult($process->getOutput());

        // Filter results: Ensure the path ends with the requested structure.
        // Example: search "foo/bar" -> find -name "bar" -> returns "/root/foo/bar" -> str_ends_with match.
        $filteredResults = array_filter($allResults, function($resultLine) use ($path) {
            $resultLine = rtrim($resultLine, '/');
            $path = rtrim($path, '/');
            return str_ends_with($resultLine, $path);
        });

        return array_values($filteredResults);
    }

    private function listResult(string $output): array
    {
        $output = trim($output);
        return empty($output) ? [] : explode("\n", $output);
    }

    public function makeData(array $files): array
    {
        $data = [];
        
        // Initialize Finfo once for performance
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($files as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            $fileInfo = new \SplFileInfo($filePath);

            $mimeType = $finfo->file($filePath);
            if ($mimeType === false) {
                $mimeType = 'unknown';
            }

            $data[] = [
                'path' => $fileInfo->getPath(),
                'filename' => $fileInfo->getFilename(),
                'realpath' => $fileInfo->getRealpath(),
                'extension' => $fileInfo->getExtension(),
                'type' => $fileInfo->getType(),
                'mime_type' => $mimeType, 
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