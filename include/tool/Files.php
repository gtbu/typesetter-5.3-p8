<?php
//gai-56-2
namespace gp\tool{

	defined('is_running') or die('Not an entry point...');

	/**
	 * Contains functions for working with data files and directories.
	 * Enhanced with explicit error handling, path boundary verification,
	 * and strict symlink safety checks.
	 *
	 */
	class Files{

		public static $last_modified;						// the modified time of the last file retrieved with gp\tool\Files::Get();
		public static $last_version;						// the version of the last file retrieved with gp\tool\Files::Get();
		public static $last_stats			= array();		// the stats of the last file retrieved with gp\tool\Files::Get();
		public static $last_meta			= array();		// the meta data of the last file retrieved with gp\tool\Files::Get();


		/**
		 * Make sure the $path is a subdirectory or file within $parent.
		 * Resolves symlinks and checks canonical path boundaries to prevent traversal attacks.
		 *
		 * @param string $path The file or directory path to check
		 * @param string|null $parent The parent file path to check against, null to check against $dataDir
		 * @return bool True if $path is safely contained within $parent, false otherwise
		 */
		public static function CheckPath( $path, $parent = null ){
			global $dataDir;

			if( !is_string($path) || $path === '' ){
				return false;
			}

			if( is_null($parent) ){
				$parent = $dataDir;
			}

			if( !is_string($parent) || $parent === '' ){
				return false;
			}

			// Remove NULL bytes before doing any path operation.
			$path	= self::NoNull($path);
			$parent	= self::NoNull($parent);
			if( $path === '' || $parent === '' ){
				return false;
			}

			// 1. Textual canonicalization of BOTH paths.
			$canonPath	= self::Canonicalize($path);
			$canonParent	= self::Canonicalize($parent);

			if( $canonPath === '' || $canonParent === '' ){
				return false;
			}

			$cleanParent = ($canonParent === '/') ? '/' : rtrim($canonParent, '/');

			// Enforce a real directory boundary, not a simple string prefix.
			$insideCanonical = (
				$canonPath === $cleanParent ||
				strpos($canonPath, $cleanParent . '/') === 0
			);

			if( !$insideCanonical ){
				return false;
			}

			// 2. Resolve the parent. If it cannot be resolved, keep the
			// textual boundary check as the applicable check.
			$realParent = realpath($parent);
			if( $realParent === false ){
				return true;
			}

			$realParent = str_replace('\\', '/', $realParent);
			$cleanRealParent = ($realParent === '/') ? '/' : rtrim($realParent, '/');

			// If the target exists, resolve it directly.
			$realPath = realpath($path);
			if( $realPath !== false ){
				$realPath = str_replace('\\', '/', $realPath);

				return (
					$realPath === $cleanRealParent ||
					strpos($realPath, $cleanRealParent . '/') === 0
				);
			}

			// If the target does not exist yet, resolve the nearest existing
			// ancestor. This also catches symlinked directories in the path.
			$dir = $path;
			while( $dir !== '' && $dir !== '.' && $dir !== '/' && $dir !== '\\' && !file_exists($dir) ){
				$parentDir = dirname($dir);
				if( $parentDir === $dir ){
					break;
				}
				$dir = $parentDir;
			}

			if( !file_exists($dir) ){
				return true;
			}

			$realDir = realpath($dir);
			if( $realDir === false ){
				return false;
			}

			$realDir = str_replace('\\', '/', $realDir);
			$cleanRealDir = ($realDir === '/') ? '/' : rtrim($realDir, '/');

			return (
				$cleanRealDir === $cleanRealParent ||
				strpos($cleanRealDir, $cleanRealParent . '/') === 0
			);
		}

		/**
		 * Return Canonicalized absolute pathname
		 * Similar to http://php.net/manual/en/function.realpath.php but does not check file existence
		 *
		 * @param string $path
		 * @return string
		 */
		public static function Canonicalize($path) {
			if( !is_string($path) ){
				return '';
			}

			$path			= self::NoNull($path);
			$path			= \gp\tool\Editing::Sanitize($path);
			$path			= str_replace( '\\', '/', $path);
			$start_slash	= (isset($path[0]) && $path[0] == '/') ? '/' : '';
			$parts			= explode('/', $path);
			$parts			= array_filter($parts);
			$absolutes		= array();

			foreach( $parts as $part ){
				if( '.' == $part ){
					continue;
				}
				if( '..' == $part ){
					array_pop($absolutes);
				}else{
					$absolutes[] = $part;
				}
			}
			return $start_slash . implode('/', $absolutes);
		}



		/**
		 * Get array from data file
		 * Example:
		 * $config = gp\tool\Files::Get('_site/config','config'); or $config = gp\tool\Files::Get('_site/config');
		 * @since 4.4b1
		 *
		 */
		public static function Get( $file, $var_name=null ){

			self::$last_modified	= null;
			self::$last_version		= null;
			self::$last_stats		= array();
			self::$last_meta		= array();
			$file_stats				= array();
			$fileModTime			= time();
			$fileVersion			= gpversion;
			$meta_data				= array();

			if( empty($file) || !is_string($file) ){
				return array();
			}

			if( !$var_name ){
				$var_name	= basename($file);
			}

			$file = self::FilePath($file);

			// json
			if( defined('gp_data_type') && gp_data_type === '.json' ){
				return self::Get_Json($file, $var_name);
			}

			if( !file_exists($file) || !is_readable($file) ){
				return array();
			}

			try{
				include($file);
			}catch(\Throwable $e){
				trigger_error('Error loading data file [' . $file . ']: ' . $e->getMessage(), E_USER_WARNING);
				return array();
			}

			if( !isset(${$var_name}) || !is_array(${$var_name}) ){
				return array();
			}

			// For data files older than 3.0
			if( !isset($file_stats['modified']) ){
				$file_stats['modified'] = $fileModTime;
			}
			if( !isset($file_stats['gpversion']) ){
				$file_stats['gpversion'] = $fileVersion;
			}

			// File stats
			self::$last_modified		= $fileModTime;
			self::$last_version			= $fileVersion;
			self::$last_stats			= $file_stats;
			if( isset($meta_data) && is_array($meta_data) ){
				self::$last_meta		= $meta_data;
			}

			return ${$var_name};
		}



		/**
		 * Get JSON formatted data file
		 *
		 */
		private static function Get_Json($file, $var_name){

			if( !file_exists($file) || !is_readable($file) ){
				return array();
			}

			$contents = @file_get_contents($file);
			if( $contents === false ){
				trigger_error('Failed to read file contents for [' . $file . ']', E_USER_WARNING);
				return array();
			}

			$data = json_decode($contents, true);
			if( json_last_error() !== JSON_ERROR_NONE ){
				trigger_error('JSON decode error in [' . $file . ']: ' . json_last_error_msg(), E_USER_WARNING);
				return array();
			}

			if( !is_array($data) || !isset($data[$var_name]) || !is_array($data[$var_name]) ){
				return array();
			}

			// File stats
			if( isset($data['file_stats']) && is_array($data['file_stats']) ){
				self::$last_modified	= isset($data['file_stats']['modified']) ? $data['file_stats']['modified'] : null;
				self::$last_version		= isset($data['file_stats']['gpversion']) ? $data['file_stats']['gpversion'] : null;
				self::$last_stats		= $data['file_stats'];
			}

			if( isset($data['meta_data']) && is_array($data['meta_data']) ){
				self::$last_meta		= $data['meta_data'];
			}

			return $data[$var_name];
		}



		/**
		 * Get the raw contents of a data file
		 *
		 */
		public static function GetRaw($file){
			if( empty($file) ){
				return false;
			}

			$file = self::FilePath($file);

			if( !file_exists($file) || !is_readable($file) ){
				return false;
			}

			$contents = @file_get_contents($file);
			if( $contents === false ){
				trigger_error('GetRaw() failed to read file: ' . $file, E_USER_WARNING);
			}

			return $contents;
		}



		/**
		 * Return true if the data file exists
		 *
		 */
		public static function Exists($file){
			if( empty($file) ){
				return false;
			}

			$file = self::FilePath($file);

			return file_exists($file);
		}



		/**
		 * Read directory and return an array with files corresponding to $filetype
		 *
		 * @param string $dir The path of the directory to be read
		 * @param mixed $filetype If false, all files in $dir will be included. false=all,1=directories,'php'='.php' files
		 * @return array List of files in $dir
		 */
		public static function ReadDir($dir, $filetype='php'){
			$files = array();

			if( empty($dir) || !is_dir($dir) || !is_readable($dir) ){
				return $files;
			}

			$dh = @opendir($dir);
			if( !$dh ){
				trigger_error('ReadDir() failed to open directory handle for: ' . $dir, E_USER_WARNING);
				return $files;
			}

			while( ($file = readdir($dh)) !== false ){
				if( $file == '.' || $file == '..' ){
					continue;
				}

				// get all
				if( $filetype === false ){
					$files[$file] = $file;
					continue;
				}

				// get directories
				if( $filetype === 1 ){
					$fullpath = $dir . '/' . $file;
					if( is_dir($fullpath) ){
						$files[$file] = $file;
					}
					continue;
				}

				$dot = strrpos($file, '.');
				if( $dot === false ){
					continue;
				}

				$type = substr($file, $dot + 1);

				// if $filetype is an array
				if( is_array($filetype) ){
					if( in_array($type, $filetype, true) ){
						$files[$file] = $file;
					}
					continue;
				}

				// if $filetype is a string
				if( $type == $filetype ){
					$file = substr($file, 0, $dot);
					$files[$file] = $file;
				}

			}
			closedir($dh);

			return $files;
		}



		/**
		 * Read all of the folders and files within $dir and return them in an organized array
		 *
		 * @param string $dir The directory to be read
		 * @return array The folders and files within $dir
		 *
		 */
		public static function ReadFolderAndFiles($dir){
			if( empty($dir) || !is_dir($dir) || !is_readable($dir) ){
				return array(array(), array());
			}

			$dh = @opendir($dir);
			if( !$dh ){
				trigger_error('ReadFolderAndFiles() failed to open directory handle: ' . $dir, E_USER_WARNING);
				return array(array(), array());
			}

			$folders = array();
			$files = array();
			while( ($file = readdir($dh)) !== false ){
				if( strpos($file, '.') === 0 ){
					continue;
				}

				$fullPath = $dir . '/' . $file;
				if( is_dir($fullPath) ){
					$folders[] = $file;
				}else{
					$files[] = $file;
				}
			}
			closedir($dh);

			natcasesort($folders);
			natcasesort($files);
			return array(array_values($folders), array_values($files));
		}



		/**
		 * Get the Section Clipboard
		 * @since 5.1-b1
		 *
		 */
		public static function GetSectionClipboard(){
			global $dataDir;

			$clipboard_dir = $dataDir . '/data/_clipboard';
			self::CheckDir($clipboard_dir);
			$clipboard_data = self::Get($clipboard_dir . '/clipboard_data.php', 'clipboard_data');

			return $clipboard_data;
		}



		/**
		 * Save the Section Clipboard
		 * @since 5.1-b1
		 *
		 */
		public static function SaveSectionClipboard($clipboard_data=array()){
			global $dataDir;

			$clipboard_dir = $dataDir . '/data/_clipboard';
			self::CheckDir($clipboard_dir);

			return self::SaveData($clipboard_dir . '/clipboard_data.php', 'clipboard_data', $clipboard_data);
		}



		/**
		 * Clean a string for use as a page label (displayed title)
		 * Similar to CleanTitle() but less restrictive
		 *
		 * @param string $title The title to be cleansed
		 * @return string The cleansed title
		 */
		public static function CleanLabel($title=''){
			if( !is_string($title) ){
				return '';
			}

			$title = self::NoNull($title);
			$title = str_replace(array('"'), array(''), $title);
			$title = str_replace(array('<', '>'), array('_'), $title);
			$title = trim($title);

			// Remove control characters
			$cleaned = preg_replace('#[[:cntrl:]]#u', '', $title);
			return ($cleaned !== null) ? $cleaned : '';
		}



		/**
		 * Clean a string of html that may be used as file content
		 *
		 * @param string $text The string to be cleansed. Passed by reference
		 */
		public static function CleanText(&$text){
			if( !is_string($text) ){
				return;
			}
			$text = self::NoNull($text);
			\gp\tool\Editing::tidyFix($text);
			self::rmPHP($text);
			self::FixTags($text);
			$text = \gp\tool\Plugins::Filter('CleanText', array($text));
		}



		/**
		 * Use html parser to check the validity of $text
		 *
		 * @param string $text The html content to be checked. Passed by reference
		 */
		public static function FixTags(&$text){
			if( !is_string($text) ){
				return;
			}
			$gp_html_output = new \gp\tool\Editing\HTML($text);
			$text = $gp_html_output->result;
		}



		/**
		 * Remove php tags from $text
		 *
		 * @param string $text The html content to be checked. Passed by reference
		 */
		public static function rmPHP(&$text){
			if( !is_string($text) ){
				return;
			}
			$search = array('<?', '<?php', '?>');
			$replace = array('&lt;?', '&lt;?php', '?&gt;');
			$text = str_replace($search, $replace, $text);
		}



		/**
		 * Removes any NULL characters in $string.
		 * @since 3.0.2
		 * @param string $string
		 * @return string
		 */
		public static function NoNull($string){
			if( !is_string($string) ){
				return '';
			}
			$string = preg_replace('/\0+/', '', $string);
			$cleaned = preg_replace('/(\\\\0)+/', '', $string);
			return ($cleaned !== null) ? $cleaned : '';
		}



		/**
		 * Save the content for a new page in /data/_pages/<title>
		 * @since 1.8a1
		 *
		 */
		public static function NewTitle($title, $section_content=false, $type='text'){
			if( empty($title) || !is_string($title) ){
				return false;
			}

			$file = self::PageFile($title);
			if( !$file ){
				return false;
			}

			// organize section data
			$file_sections = array();
			if( is_array($section_content) && isset($section_content['type']) ){
				$file_sections[0]	= $section_content;
			}elseif( is_array($section_content) ){
				$file_sections		= $section_content;
			}else{
				$file_sections[0] = array(
					'type'			=> $type,
					'content'		=> $section_content,
				);
			}

			// add meta data
			$meta_data = array(
				'file_number'	=> self::NewFileNumber(),
				'file_type'		=> $type,
			);

			return self::SaveData($file, 'file_sections', $file_sections, $meta_data);
		}



		/**
		 * Return the data file location for a title
		 * Since v4.6, page files are within a subfolder
		 * As of v2.3.4, it defaults to an index based file name but falls back on title based file name for backwards compatibility
		 *
		 * @param string $title
		 * @return string The path of the data file
		 */
		public static function PageFile($title){
			global $dataDir, $config, $gp_index;

			if( !is_string($title) ){
				return '';
			}

			$title = self::NoNull($title);
			$index_path = false;

			// filename based on title index
			if( defined('gp_index_filenames') && gp_index_filenames && isset($gp_index[$title]) && isset($config['gpuniq']) ){
				$index_path = $dataDir . '/data/_pages/' . substr($config['gpuniq'], 0, 7) . '_' . $gp_index[$title] . '/page.php';
			}

			// using file name instead of index
			$normal_path = $dataDir . '/data/_pages/' . str_replace('/', '_', $title) . '/page.php';
			if( !$index_path || self::Exists($normal_path) ){
				return $normal_path;
			}

			return $index_path;
		}



		public static function NewFileNumber(){
			global $config;

			if( !isset($config['file_count']) || !is_numeric($config['file_count']) ){
				$config['file_count'] = 0;
			}
			$config['file_count']++;

			if( class_exists('\gp\admin\Tools') && method_exists('\gp\admin\Tools', 'SaveConfig') ){
				\gp\admin\Tools::SaveConfig();
			}

			return $config['file_count'];
		}



		/**
		 * Get the meta data for the specified file
		 *
		 * @param string $file
		 * @return array
		 */
		public static function GetTitleMeta($file){
			if( empty($file) ){
				return array();
			}
			self::Get($file, 'meta_data');
			return self::$last_meta;
		}



		/**
		 * Return an array of info about the data file
		 *
		 */
		public static function GetFileStats($file){
			if( empty($file) ){
				return array('created' => time());
			}

			$file_stats = self::Get($file, 'file_stats');
			if( is_array($file_stats) && !empty($file_stats) ){
				return $file_stats;
			}

			return array('created' => time());
		}



		/**
		 * Save a file with content and data to the server
		 *
		 * @param string $file The path of the file to be saved
		 * @param string $contents The contents of the file to be saved
		 * @param string $code The data to be saved
		 * @param string $time The unix timestamp to be used for the $fileVersion
		 * @return bool True on success
		 */
		public static function SaveFile($file, $contents, $code=false, $time=false){
			$result = self::FileStart($file, $time);
			if( $result !== false ){
				$result .= "\n" . $code;
			}
			$result .= "\n\n?" . ">\n";
			$result .= $contents;

			return self::Save($file, $result);
		}



		/**
		 * Save raw content to a file to the server
		 *
		 * @param string $file The path of the file to be saved
		 * @param string $contents The contents of the file to be saved
		 * @return bool True on success
		 */
		public static function Save($file, $contents){
			global $gp_not_writable;

			if( empty($file) || !is_string($file) ){
				trigger_error('Save() called with invalid or empty filename', E_USER_WARNING);
				return false;
			}

			$file	= self::NoNull($file);
			$exists = self::Exists($file);

			// make sure directory exists
			if( !$exists ){
				$dir = \gp\tool::DirName($file);
				if( !file_exists($dir) ){
					if( !self::CheckDir($dir) ){
						trigger_error('Save() failed to create target directory: ' . $dir, E_USER_WARNING);
						return false;
					}
				}
			}

			$fp = @fopen($file, 'wb');
			if( $fp === false ){
				if( is_array($gp_not_writable) ){
					$gp_not_writable[] = $file;
				}
				trigger_error('Save() failed to open file for writing: ' . $file, E_USER_WARNING);
				return false;
			}

			if( !flock($fp, LOCK_EX) ){
				fclose($fp);
				trigger_error('Save() flock could not be obtained for: ' . $file, E_USER_WARNING);
				return false;
			}

			$chmod_val = defined('gp_chmod_file') ? gp_chmod_file : 0666;
			if( !$exists ){
				@chmod($file, $chmod_val);
			}elseif( function_exists('opcache_invalidate') && substr($file, -4) === '.php' ){
				@opcache_invalidate($file, true);
			}

			$return = fwrite($fp, $contents);

			flock($fp, LOCK_UN);
			fclose($fp);

			if( $return === false ){
				trigger_error('Save() failed during fwrite() execution for: ' . $file, E_USER_WARNING);
				return false;
			}

			return true;
		}



		/**
		 * Rename a file
		 * @since 4.6
		 */
		public static function Rename($from, $to){
			global $gp_not_writable;

			if( empty($from) || empty($to) ){
				return false;
			}

			if( !self::WriteLock() ){
				trigger_error('Rename() aborted because write lock could not be acquired.', E_USER_WARNING);
				return false;
			}

			// make sure destination directory exists
			$dir = \gp\tool::DirName($to);
			if( !file_exists($dir) && !self::CheckDir($dir) ){
				trigger_error('Rename() failed to create destination directory: ' . $dir, E_USER_WARNING);
				return false;
			}

			$res = @rename($from, $to);
			if( !$res ){
				trigger_error('Rename() failed from [' . $from . '] to [' . $to . ']', E_USER_WARNING);
			}

			return $res;
		}



		/**
		 * Replace $to with $from
		 *
		 */
		public static function Replace($from, $to){
			if( empty($from) || empty($to) ){
				return false;
			}

			$temp_dir = '';

			// move the $to out of the way if it exists
			if( file_exists($to) ){
				$temp_dir = $to . '_' . time() . '_' . mt_rand(1000, 9999);
				if( !self::Rename($to, $temp_dir) ){
					trigger_error('Replace() failed to backup existing target to temporary path.', E_USER_WARNING);
					return false;
				}
			}

			// rename $from -> $to
			if( !self::Rename($from, $to) ){
				if( $temp_dir && file_exists($temp_dir) ){
					self::Rename($temp_dir, $to);
				}
				trigger_error('Replace() failed to move source file to target location.', E_USER_WARNING);
				return false;
			}

			if( !empty($temp_dir) && file_exists($temp_dir) ){
				self::RmAll($temp_dir);
			}

			return true;
		}



		/**
		 * Get a write lock to prevent simultaneous writing
		 * @since 3.5.3
		 */
		public static function WriteLock(){

			if( defined('gp_has_lock') ){
				return gp_has_lock;
			}

			$expires	= defined('gp_write_lock_time') ? gp_write_lock_time : 10;
			$randomVal	= defined('gp_random') ? gp_random : uniqid('', true);

			if( self::Lock('write', $randomVal, $expires) ){
				define('gp_has_lock', true);
				return true;
			}

			trigger_error('CMS write lock could not be obtained.', E_USER_WARNING);
			define('gp_has_lock', false);

			return false;
		}



		/**
		 * Get a lock
		 * Loop and delay to wait for the removal of existing locks (maximum of about .2 of a second)
		 *
		 */
		public static function Lock($file, $value, &$expires){
			global $dataDir;

			if( empty($file) ){
				return false;
			}

			$tries			= 0;
			$lock_file		= $dataDir . '/data/_lock_' . sha1($file);
			$file_time		= 0;
			$elapsed		= 0;

			while( $tries < 1000 ){

				if( !file_exists($lock_file) ){
					@file_put_contents($lock_file, $value);
					usleep(100);
				}elseif( !$file_time ){
					$file_time = @filemtime($lock_file);
				}

				$contents = @file_get_contents($lock_file);
				if( $value === $contents ){
					@touch($lock_file);
					return true;
				}

				if( $file_time ){
					$elapsed = time() - $file_time;
					if( $elapsed > $expires ){
						@unlink($lock_file);
					}
				}

				clearstatcache();
				usleep(100);
				$tries++;
			}

			if( $file_time ){
				$expires -= $elapsed;
			}

			return false;
		}



		/**
		 * Remove a lock file if the value matches
		 *
		 */
		public static function Unlock($file, $value){
			global $dataDir;

			if( empty($file) ){
				return false;
			}

			$lock_file = $dataDir . '/data/_lock_' . sha1($file);
			if( !file_exists($lock_file) ){
				return true;
			}

			$contents = @file_get_contents($lock_file);
			if( $contents === false ){
				return true;
			}
			if( $value === $contents ){
				@unlink($lock_file);
				return true;
			}
			return false;
		}



		/**
		 * Save array(s) to a $file location
		 * Takes 2n+3 arguments
		 *
		 * @param string $file The location of the file to be saved
		 * @param string $varname The name of the variable being saved
		 * @param array $array The value of $varname to be saved
		 *
		 * @deprecated 4.3.5
		 */
		public static function SaveArray(){

			if( defined('gp_data_type') && gp_data_type === '.json' ){
				throw new \Exception('SaveArray() cannot be used for json data. Use SaveData() instead');
			}

			$args = func_get_args();
			$count = count($args);
			if( ($count % 2 !== 1) || ($count < 3) ){
				trigger_error('Wrong argument count ' . $count . ' for \gp\tool\Files::SaveArray() ', E_USER_WARNING);
				return false;
			}
			$file = array_shift($args);

			$file_stats = array();
			$data = '';
			while( count($args) ){
				$varname = array_shift($args);
				$array = array_shift($args);
				if( $varname == 'file_stats' ){
					$file_stats = (array)$array;
				}else{
					$data .= self::ArrayToPHP($varname, $array);
					$data .= "\n\n";
				}
			}

			$data = self::FileStart($file, time(), $file_stats) . $data;

			return self::Save($file, $data);
		}



		/**
		 * Save array to a $file location
		 *
		 * @param string $file The location of the file to be saved
		 * @param string $varname The name of the variable being saved
		 * @param array $array The value of $varname to be saved
		 * @param array $meta meta data to be saved along with $array
		 *
		 */
		public static function SaveData($file, $varname, $array, $meta=array()){

			if( empty($file) || !is_string($file) ){
				trigger_error('SaveData() called with invalid or empty file path.', E_USER_WARNING);
				return false;
			}

			$file = self::FilePath($file);

			if( defined('gp_data_type') && gp_data_type === '.json' ){
				$json				= self::FileStart_Json($file);
				$json[$varname]		= $array;
				$json['meta_data']	= $meta;
				$content			= json_encode($json);
				if( $content === false ){
					trigger_error('SaveData() failed json_encode: ' . json_last_error_msg(), E_USER_WARNING);
					return false;
				}
			}else{
				$content			= self::FileStart($file);
				$content			.= self::ArrayToPHP($varname, $array);
				$content			.= "\n\n";
				$content			.= self::ArrayToPHP('meta_data', $meta);
			}

			return self::Save($file, $content);
		}



		/**
		 * Experimental JSON File Header metadata generator
		 *
		 */
		private static function FileStart_Json($file, $time=null ){
			global $gpAdmin;

			if( is_null($time) ){
				$time = time();
			}

			// file stats
			$file_stats					= self::GetFileStats($file);
			$file_stats['gpversion']	= defined('gpversion') ? gpversion : 'unknown';
			$file_stats['modified']		= $time;
			$file_stats['username']		= false;

			if( class_exists('\gp\tool') && method_exists('\gp\tool', 'loggedIn') && \gp\tool::loggedIn() ){
				if( isset($gpAdmin['username']) ){
					$file_stats['username'] = $gpAdmin['username'];
				}
			}

			$json						= array();
			$json['file_stats']			= $file_stats;

			return $json;
		}



		/**
		 * Return the beginning content of a data file
		 *
		 */
		public static function FileStart($file, $time=null, $file_stats=array()){
			global $gpAdmin;

			if( is_null($time) ){
				$time = time();
			}

			$version = defined('gpversion') ? gpversion : 'unknown';

			// file stats
			$file_stats 				= (array)$file_stats + self::GetFileStats($file);
			$file_stats['gpversion']	= $version;
			$file_stats['modified']		= $time;

			if( class_exists('\gp\tool') && method_exists('\gp\tool', 'loggedIn') && \gp\tool::loggedIn() ){
				if( isset($gpAdmin['username']) ){
					$file_stats['username'] = $gpAdmin['username'];
				}else{
					$file_stats['username'] = false;
				}
			}else{
				$file_stats['username']	= false;
			}

			return '<' . '?' . 'php'
					. "\ndefined('is_running') or die('Not an entry point...');"
					. "\n" . '$fileVersion = \'' . addslashes($version) . '\';'	// @deprecated 3.0
					. "\n" . '$fileModTime = \'' . addslashes((string)$time) . '\';'		// @deprecated 3.0
					. "\n" . self::ArrayToPHP('file_stats', $file_stats)
					. "\n\n";
		}



		public static function ArrayToPHP($varname, &$array){
			return '$' . $varname . ' = ' . var_export($array, true) . ';';
		}



		/**
		 * Insert a key-value pair into an associative array
		 *
		 * @param mixed $search_key Value to search for in existing array to insert before
		 * @param mixed $new_key Key portion of key-value pair to insert
		 * @param mixed $new_value Value portion of key-value pair to insert
		 * @param array $array Array key-value pair will be added to
		 * @param int $offset Offset distance from where $search_key was found. A value of 1 would insert after $search_key, a value of 0 would insert before $search_key
		 * @param int $length If length is omitted, nothing is removed from $array. If positive, then that many elements will be removed starting with $search_key + $offset
		 * @return bool True on success
		 */
		public static function ArrayInsert($search_key, $new_key, $new_value, &$array, $offset=0, $length=0){
			if( !is_array($array) ){
				return false;
			}

			$array_keys		= array_keys($array);
			$array_values	= array_values($array);

			$insert_key		= array_search($search_key, $array_keys, true);
			if( $insert_key === null || $insert_key === false ){
				return false;
			}

			array_splice($array_keys, $insert_key + $offset, $length, $new_key);
			array_splice($array_values, $insert_key + $offset, $length, 'fill'); // use fill in case $new_value is an array
			$array = array_combine($array_keys, $array_values);
			if( $array === false ){
				trigger_error('ArrayInsert() failed array_combine.', E_USER_WARNING);
				return false;
			}
			$array[$new_key] = $new_value;

			return true;
		}



		/**
		 * Replace a key-value pair in an associative array
		 * ArrayReplace() is a shortcut for using \gp\tool\Files::ArrayInsert() with $offset = 0 and $length = 1
		 */
		public static function ArrayReplace($search_key, $new_key, $new_value, &$array){
			return self::ArrayInsert($search_key, $new_key, $new_value, $array, 0, 1);
		}



		/**
		 * Check recursively to see if a directory exists, if it doesn't attempt to create it
		 *
		 * @param string $dir The directory path
		 * @param bool $index Whether or not to add an index.html file in the directory
		 * @return bool True on success
		 */
		public static function CheckDir($dir, $index=true){
			global $config;

			if( empty($dir) || !is_string($dir) ){
				return false;
			}

			$dir = self::NoNull($dir);

			if( !file_exists($dir) ){
				$parent = \gp\tool::DirName($dir);
				if( !empty($parent) && $parent !== $dir && !file_exists($parent) ){
					if( !self::CheckDir($parent, $index) ){
						trigger_error('CheckDir() failed to create parent directory: ' . $parent, E_USER_WARNING);
						return false;
					}
				}

				$chmod_dir = defined('gp_chmod_dir') ? gp_chmod_dir : 0755;

				// mkdir attempt
				if( !@mkdir($dir, $chmod_dir) ){
					if( !is_dir($dir) ){ // concurrency check
						trigger_error('CheckDir() failed to mkdir: ' . $dir, E_USER_WARNING);
						return false;
					}
				}
				@chmod($dir, $chmod_dir); // some systems need explicit chmod after mkdir

				// make sure there's an index.html file
				if( $index && (!defined('gp_dir_index') || gp_dir_index) ){
					$indexFile = $dir . '/index.html';
					if( !file_exists($indexFile) ){
						$chmod_file = defined('gp_chmod_file') ? gp_chmod_file : 0666;
						if( @file_put_contents($indexFile, '<html></html>') !== false ){
							@chmod($indexFile, $chmod_file);
						}
					}
				}
			}

			return is_dir($dir);
		}



		/**
		 * Remove a directory
		 * Will only work if directory is empty
		 *
		 */
		public static function RmDir($dir){
			if( empty($dir) || !is_dir($dir) ){
				return false;
			}

			$dir = self::NoNull($dir);
			$res = @rmdir($dir);
			if( !$res ){
				trigger_error('RmDir() failed to remove directory: ' . $dir, E_USER_WARNING);
			}

			return $res;
		}



		/**
		 * Remove a file or directory and its contents
		 * Strictly verifies path boundaries and symlink targets to avoid unintended deletions.
		 *
		 * @param string $path The target file or directory path
		 * @return bool True on success, false on failure
		 */
		public static function RmAll($path){
			global $dataDir;

			if( empty($path) || !is_string($path) ){
				return false;
			}

			$path = self::NoNull($path);

			// Normalize and trim trailing slashes to accurately detect symlinks
			$trimmedPath = str_replace('\\', '/', $path);
			if( strlen($trimmedPath) > 1 ){
				$trimmedPath = rtrim($trimmedPath, '/');
			}

			// Safety check: Never allow deleting root '/' or empty path or $dataDir root directly
			if( $trimmedPath === '' || $trimmedPath === '/' || (isset($dataDir) && $trimmedPath === str_replace('\\', '/', $dataDir)) ){
				trigger_error('RmAll() safety check blocked attempt to delete critical root path: ' . $path, E_USER_WARNING);
				return false;
			}

			// Check if path is a symbolic link (must check without trailing slash)
			if( is_link($trimmedPath) ){
				$result = @unlink($trimmedPath);
				if( !$result ){
					trigger_error('RmAll() failed to unlink symbolic link: ' . $trimmedPath, E_USER_WARNING);
				}
				return $result;
			}

			// Check existence
			if( !file_exists($trimmedPath) ){
				return true; // Already removed
			}

			// Regular file removal
			if( !is_dir($trimmedPath) ){
				$result = @unlink($trimmedPath);
				if( !$result ){
					trigger_error('RmAll() failed to delete file: ' . $trimmedPath, E_USER_WARNING);
				}
				return $result;
			}

			// Directory content removal
			$success	= true;
			$subDirs	= array();
			$files		= self::ReadDir($trimmedPath, false);

			if( $files === false ){
				trigger_error('RmAll() unable to read directory contents for: ' . $trimmedPath, E_USER_WARNING);
				return false;
			}

			foreach( $files as $file ){
				$full_path = $trimmedPath . '/' . $file;

				// Handle sub-symlinks directly without entering target directory
				if( is_link($full_path) ){
					if( !@unlink($full_path) ){
						trigger_error('RmAll() failed to unlink nested symlink: ' . $full_path, E_USER_WARNING);
						$success = false;
					}
					continue;
				}

				if( is_dir($full_path) ){
					$subDirs[] = $full_path;
					continue;
				}

				if( !@unlink($full_path) ){
					trigger_error('RmAll() failed to delete nested file: ' . $full_path, E_USER_WARNING);
					$success = false;
				}
			}

			foreach( $subDirs as $subDir ){
				if( !self::RmAll($subDir) ){
					$success = false;
				}
			}

			if( $success ){
				return self::RmDir($trimmedPath);
			}

			return false;
		}


        /**
		 * Get the correct path for the data file
		 * Two valid methods to get a data file path:
		 *  Full path: /var/www/html/site/data/_site/config.php
		 *  Relative:  _site/config
		 *
		 * @param string $path
		 * @return string The formatted file path
		 */
		public static function FilePath($path){
			global $dataDir;

			if( !is_string($path) || $path === '' ){
				return '';
			}

			$path			= self::NoNull($path);
			$normPath		= str_replace('\\', '/', $path);
			$dataDirNorm	= isset($dataDir) ? str_replace('\\', '/', $dataDir) : '';

			$ext = pathinfo($normPath, PATHINFO_EXTENSION);

			if( $ext === 'gpjson' ){
				$normPath = substr($normPath, 0, -7);

			}elseif( $ext === 'php' ){
				$normPath = substr($normPath, 0, -4);

			}else{
				// Check if absolute path or starts with $dataDir
				$isAbsolute = (
					(isset($normPath[0]) && $normPath[0] === '/') ||
					(strlen($normPath) > 1 && $normPath[1] === ':') ||
					($dataDirNorm !== '' && strpos($normPath, $dataDirNorm) === 0)
				);

				if( !$isAbsolute ){
					$normPath = $dataDirNorm . '/data/' . ltrim($normPath, '/');
				}
			}

			$targetExt = (defined('gp_data_type') && gp_data_type === '.json') ? '.gpjson' : '.php';
			$finalPath = $normPath . $targetExt;

			// --- SECURITY FIX: Path Boundary Check ---
			// Stellt sicher, dass auch explizit absolute Pfade nicht aus dem $dataDir ausbrechen
			if( !self::CheckPath($finalPath) ){
				trigger_error('Security restriction: Invalid path access in FilePath()', E_USER_WARNING);
				return '';
			}

			return $finalPath;
		}


		/**
		 * @deprecated 3.0
		 * Use \gp\tool\Editing::CleanTitle() instead
		 * Used by Simple_Blog1
		 */
		public static function CleanTitle($title, $spaces='_'){
			trigger_error('Deprecated Function: \gp\tool\Files::CleanTitle()', E_USER_DEPRECATED);
			return \gp\tool\Editing::CleanTitle($title, $spaces);
		}

	}

}

namespace{
	class gpFiles extends gp\tool\Files{}
}