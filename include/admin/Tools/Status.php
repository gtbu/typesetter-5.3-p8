<?php

declare(strict_types=1); // Enforce strict types for better code quality

namespace gp\admin\Tools;

// Ensure this script is run within the context of the application
defined('is_running') or die('Not an entry point...');

class Status extends \gp\special\Base
{
	protected int $check_dir_len = 0;
	protected int $failed_count = 0;
	/** @var list<string> */
	protected array $failed = [];
	protected int $passed_count = 0;
	protected int $show_failed_max = 50;
	/** @var list<string> */
	protected array $deletable = [];
	protected string|int|null $euid = null; // Can be string '?', int from posix_geteuid, or null initially
        
	public function __construct()
	{
		// Constructor remains empty if no initialization is needed here
	}

	public function RunScript(): void
	{
		global $langmessage, $dataDir; // Access global variable

		if (!is_array($langmessage)) {
			// Handle missing/invalid global gracefully
			$langmessage = ['Site Status' => 'Site Status']; // Provide default
			trigger_error('$langmessage global variable not available or not an array.', E_USER_WARNING);
		}

		echo '<h2>' . htmlspecialchars($langmessage['Site Status'] ?? 'Site Status') . '</h2>';

echo '<p class="gp_notice">';
global $gpversion;
$currentVersion = isset($gpversion) ? $gpversion : (defined('gpversion') ? gpversion : 'Unnknown');
echo '<b>Website-Status:</b> <span style="color:green;">Online</span><br>';
echo '<b>CMS-Version:</b> ' . htmlspecialchars($currentVersion) . "<br>\n";
echo '<b>System-Zeit:</b> ' . date('d.m.Y H:i:s') . "<br>\n";
echo '</p>';

                $cmd = \gp\tool::GetCommand();
		switch ($cmd) {
			case 'FixOwner':
				$this->FixOwner();
				break;
		}
		$this->CheckDataDir();
		$this->DefaultDisplay();
	}

	public function CheckDataDir(): void
	{
		global $dataDir; // Access global variable

		if (!is_string($dataDir) || $dataDir === '') {
			// Handle missing/invalid global gracefully
			trigger_error('$dataDir global variable not available or empty.', E_USER_ERROR);
			echo '<p class="gp_error">Configuration error: dataDir is not set.</p>';
			return;
		}

		$this->check_dir_len = 0;
		$this->failed_count = 0;
		$this->passed_count = 0;
		$this->failed = [];
		$this->deletable = [];
		$this->show_failed_max = 50;

		$check_dir = $dataDir . '/data';
		$this->check_dir_len = strlen($check_dir);
		$this->euid = '?'; // Default string value

		// Check if POSIX functions are available and get effective user ID
		if (function_exists('posix_geteuid')) {
			$euid = posix_geteuid();
			// posix_geteuid returns int, assign directly
			$this->euid = $euid;
		}

		if (is_dir($check_dir)) {
			$this->CheckDir($check_dir);
		} else {
			echo '<p class="gp_error">Error: Data directory does not exist: ' . htmlspecialchars($check_dir) . '</p>';
			$this->failed_count = 1; // Mark as failed if base dir doesn't exist
			$this->failed[] = $check_dir;
		}
	}

	public function DefaultDisplay(): void
	{
		global $langmessage, $dataDir; // Access global variables

		if (!is_array($langmessage)) {
			$langmessage = []; // Prevent errors if not set
		}
		if (!is_string($dataDir)) {
			$dataDir = ''; // Prevent errors if not set
		}

		$check_dir = $dataDir . '/data';
		$checked = $this->passed_count + $this->failed_count;

		if ($this->failed_count === 0) {
			echo '<p class="gp_passed">';
			echo sprintf(
				htmlspecialchars($langmessage['data_check_passed'] ?? 'Passed %d checks out of %d.'),
				$checked,
				$checked
			);
			echo '</p>';
			$this->ShowDeletable();
			return;
		}

		echo '<p class="gp_notice">';
		echo sprintf(
			htmlspecialchars($langmessage['data_check_failed'] ?? 'Failed %d checks out of %d.'),
			$this->failed_count,
			$checked
		);
		echo '</p>';

		// Special message if only the main /data directory isn't writable/accessible
		if (count($this->failed) === 1 && $this->failed[0] === $check_dir) {
			echo '<p class="gp_notice">';
			echo '<b>WARNING:</b> Your data directory is not accessible or writable: ' . htmlspecialchars($check_dir);
			echo '</p>';
			// Don't show the table if only the root data dir failed
			return;
		}

		if ($this->failed_count > $this->show_failed_max) {
			echo '<p class="gp_notice">';
			echo sprintf(
				htmlspecialchars($langmessage['showing_max_failed'] ?? 'Showing first %d failed items.'),
				$this->show_failed_max
			);
			echo '</p>';
		}

		echo '<table class="bordered">';
		echo '<thead><tr><th>';
		echo htmlspecialchars($langmessage['file_name'] ?? 'File/Folder');
		echo '</th><th>';
		echo htmlspecialchars($langmessage['File Owner'] ?? 'Berechtigungen');
		echo '<br/>';
		echo htmlspecialchars($langmessage['Current_Value'] ?? 'Aktuell (Rechte)');
		echo '</th><th>';
		echo '<br/>';
		echo htmlspecialchars($langmessage['Expected_Value'] ?? 'Erwartet (Rechte)');
		echo '</th><th> &nbsp;'; // Actions column
		echo '</th></tr></thead>';
		echo '<tbody>';

		// Sort by path length (directories often shorter, appear first)
		usort($this->failed, fn (string $a, string $b): int => strlen($a) <=> strlen($b));

		$shown_count = 0;
		foreach ($this->failed as $path) {
			if ($shown_count >= $this->show_failed_max) {
				break;
			}

			$readable_path = substr($path, $this->check_dir_len);
			if ($readable_path === '') {
				$readable_path = '/'; // Represent the base data dir
			}

			// Show actual filesystem mode and the mode expected by TypesetterCMS.
			$current_mode = @fileperms($path);
			$current_value = $current_mode === false ? 'Nicht lesbar' : substr(sprintf('%o', $current_mode), -4);
			$expected_value = 'Nicht verfügbar';
			if (class_exists('\\gp\\install\\FilePermissions')) {
				try {
					if (is_dir($path) && method_exists('\\gp\\install\\FilePermissions', 'getExpectedPerms')) {
						$expected_value = (string) \gp\install\FilePermissions::getExpectedPerms($path);
					} elseif (is_file($path) && method_exists('\\gp\\install\\FilePermissions', 'getExpectedPerms_file')) {
						$expected_value = (string) \gp\install\FilePermissions::getExpectedPerms_file($path);
					}
				} catch (\Throwable $e) {
					$expected_value = 'Nicht verfügbar';
				}
			}

			echo '<tr><td>';
			echo htmlspecialchars($readable_path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			echo '</td><td><code>' . htmlspecialchars($current_value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></td>';
			echo '<td><code>' . htmlspecialchars($expected_value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></td>';
			// Intentionally no automatic Fix action: changing ownership by copying/moving
			// files can break data, and chmod/chown behavior depends on server configuration.
			echo '<td><span title="Manuelle Prüfung erforderlich">—</span></td></tr>';
			$shown_count++;
		}

		echo '</tbody></table>';

		// Only check for orphans if the primary checks didn't completely fail
		if (!(count($this->failed) === 1 && $this->failed[0] === $check_dir)) {
			$this->CheckPageFiles();
		}

		$this->ShowDeletable();
	}

	/**
	 * Show Deletable Files found during scan
	 */
	protected function ShowDeletable(): void
	{
		if (empty($this->deletable)) {
			return;
		}
		echo '<h3>Deletable Files</h3>';
		echo '<p>The following files or folders were marked as deletable during the scan:</p>';
		echo '<ol>';
		foreach ($this->deletable as $file) {
			echo '<li>' . htmlspecialchars($file) . '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Check page files for orphaned data files
	 */
	protected function CheckPageFiles(): void
	{
		global $dataDir, $gp_index; // Access global variables

		if (!is_string($dataDir) || $dataDir === '') {
			trigger_error('$dataDir global variable not available or empty for CheckPageFiles.', E_USER_WARNING);
			return;
		}
		if (!is_array($gp_index)) {
			trigger_error('$gp_index global variable not available or not an array for CheckPageFiles.', E_USER_WARNING);
			$gp_index = []; // Prevent errors
		}

		$pages_dir = $dataDir . '/data/_pages';
		if (!is_dir($pages_dir)) {
			// If _pages dir doesn't exist, nothing to check
			return;
		}

		// Use Files helper if available, otherwise fallback or skip
		if (!class_exists('\gp\tool\Files') || !method_exists('\gp\tool\Files', 'ReadDir')) {
			echo '<p class="gp_notice">Cannot check for orphaned files: Files helper is unavailable.</p>';
			return;
		}

		$all_files_in_dir = \gp\tool\Files::ReadDir($pages_dir, 'php');
		$all_page_data_files = [];
		foreach ($all_files_in_dir as $file) {
			// Assuming ReadDir returns filenames without extension
			$all_page_data_files[] = $pages_dir . '/' . $file . '.php';
		}

		$active_page_files = [];
		if (method_exists('\gp\tool\Files', 'PageFile')) {
			foreach (array_keys($gp_index) as $slug) {
				if(is_string($slug)){ // Ensure slug is a string
					$active_page_files[] = \gp\tool\Files::PageFile($slug);
				}
			}
		} else {
			echo '<p class="gp_notice">Cannot check for orphaned files: PageFile helper is unavailable.</p>';
			return;
		}


		// Find files present in the directory but not in the active page index
		$diff = array_diff($all_page_data_files, $active_page_files);

		if (empty($diff)) {
			return;
		}

		echo '<h2>Orphaned Data Files</h2>';
		echo '<p>The following data files exist in the <code>_pages</code> directory but do not seem to correspond to any currently active page. They might be remnants of deleted pages. Before removing them, consider backing them up.</p>';
		echo '<table class="bordered"><thead><tr><th>File Path</th></tr></thead>';
		echo '<tbody>';
		foreach ($diff as $file) {
			echo '<tr><td>' . htmlspecialchars($file) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Check the ownership/permissions of a directory and recursively its contents.
	 */
	protected function CheckDir(string $dir): void
	{
		// First, check the directory itself
		if (!$this->CheckFile($dir, 'dir')) {
			// If the directory check failed (e.g., permissions), maybe don't recurse?
			// Original logic continued, so we keep that. If dir isn't readable, opendir will fail.
			// $this->failed[] is already populated by CheckFile if it fails.
		}

		// Attempt to open the directory
		// Use error suppression carefully, consider try-catch for FilesystemIterator later if refactoring
		$dh = @opendir($dir);
		if ($dh === false) {
			// If opendir failed after CheckFile potentially passed (e.g., race condition or complex ACLs)
			// ensure it's marked as failed if not already.
			if (!in_array($dir, $this->failed, true)) {
				$this->failed_count++;
				$this->failed[] = $dir;
			}
			// Cannot proceed further into this directory
			return;
		}

		while (($file = readdir($dh)) !== false) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$full_path = $dir . '/' . $file;

			// Skip symbolic links to avoid issues, unless specifically needed
			if (is_link($full_path)) {
				continue;
			}

			// Check for specially named deletable files/folders
			if (preg_match('#/x-deletable-[0-9]+$#', $full_path)) {
				if (!in_array($full_path, $this->deletable, true)) {
					$this->deletable[] = $full_path;
				}
				continue; // Don't check permissions on deletable items
			}

			if (is_dir($full_path)) {
				$this->CheckDir($full_path); // Recurse into subdirectory
			} else {
				$this->CheckFile($full_path, 'file'); // Check file
			}
		}

		closedir($dh); // Always close the directory handle
	}

	/**
	 * Check the ownership/permissions of a single file or directory.
	 * Returns true if checks pass, false otherwise.
	 */
	protected function CheckFile(string $path, string $type = 'dir'): bool
	{
		// Use FilePermissions helper if available
		if (class_exists('\gp\install\FilePermissions') && method_exists('\gp\install\FilePermissions', 'HasFunctions')) {
			if (\gp\install\FilePermissions::HasFunctions()) {
				$perms = @fileperms($path);
				if ($perms === false) {
					// Cannot get permissions, count as failed
					$this->failed_count++;
					$this->failed[] = $path;
					return false;
				}
				// Get last 3 digits of octal representation
				$current_perms = substr(sprintf('%o', $perms), -3);

				if ($type === 'file') {
					$expected_perms = \gp\install\FilePermissions::getExpectedPerms_file($path);
				} else {
					$expected_perms = \gp\install\FilePermissions::getExpectedPerms($path);
				}

				// Compare permissions using helper
				if (\gp\install\FilePermissions::perm_compare($expected_perms, $current_perms)) {
					$this->passed_count++;
					return true;
				} else {
					// Permission mismatch, count as failed
					$this->failed_count++;
					$this->failed[] = $path;
					return false;
				}
			}
		}

		// Fallback check: Check if writable (less precise but better than nothing)
		// Use standard is_writable if gp_is_writable doesn't exist
		$is_writable_func = function_exists('gp_is_writable') ? 'gp_is_writable' : 'is_writable';

		if ($is_writable_func($path)) {
			$this->passed_count++;
			return true;
		}

		// Fallback check failed
		$this->failed_count++;
		$this->failed[] = $path;
		return false;
	}

	/**
	 * Display a user name and UID if possible.
	 */
	protected function ShowUser(int|string|null $uid): string
	{
		// If UID is not an integer or posix functions are not available, just return the UID as string
		if (!is_int($uid) || !function_exists('posix_getpwuid')) {
			return (string) $uid; // Cast null or string '?' to string
		}

		// Attempt to get user info only if we have an integer UID
		$user_info = posix_getpwuid($uid);

		if (is_array($user_info) && isset($user_info['name'])) {
			// Found user info, return name and UID
			return $user_info['name'] . ' (' . $uid . ')';
		} else {
			// Function failed or user not found, return only UID
			return (string) $uid;
		}
	}

	/**
	 * Attempt to fix the ownership/permissions issue of the posted file/folder.
	 * This is a potentially risky operation.
	 */
	public function FixOwner(): void
	{
		// Safe by default: do not copy, rename, delete, chmod or chown files from a
		// GET-style admin link. Display a message and let the administrator review
		// the Current/Expected permission modes shown in the table.
		$message = 'Automatic repair has been disabled for security reasons. Please check the current and expected permissions, and correct them if necessary via hosting/SSH.';
		echo '<div class="gp_notice">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
	}}

// Helper function 'msg' needs to be defined globally or within the scope where this class is used.
// Example definition:
if (!function_exists('msg')) {
	function msg(string $message, bool $isSuccess = false): void {
		echo '<div class="gp_' . ($isSuccess ? 'passed' : 'error') . '">' . htmlspecialchars($message) . '</div>';
	}
}


