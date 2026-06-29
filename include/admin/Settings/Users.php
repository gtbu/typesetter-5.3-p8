<?php

namespace gp\admin\Settings;

defined('is_running') or die('Not an entry point...');

class Users extends \gp\special\Base{

	public $users;
	public $possible_permissions	= array();
	public $has_weak_pass			= false;

	protected $cmds					= [
										'NewUserForm'		=> '',
										'ChangePass'		=> '',
										'Details'			=> 'ChangeDetails',
									];

	protected $cmds_post			= [
										'CreateNewUser'		=> 'NewUserForm',
										'RemoveUser'		=> 'DefaultDisplay',
										'ResetPass'			=> 'ChangePass',
										'SaveChanges'		=> 'ChangeDetails',
									];


	public function __construct($args){
		global $langmessage;

		parent::__construct($args);

		$this->page->head_js[]			= '/include/js/admin_users.js';
		$this->possible_permissions		= $this->PossiblePermissions();
		$this->GetUsers();
	}


	/**
	 * Sanitize username input
	 *
	 */
	protected function SanitizeUsername($username){
		// Remove dots and underscores, then check if alphanumeric
		$testu = str_replace(array('.','_'), array(''), $username);
		if(empty($testu) || !ctype_alnum($testu)){
			return false;
		}
		// Limit length to reasonable amount
		if(strlen($username) > 50){
			return false;
		}
		return $username;
	}

	/**
	 * Validate email address
	 *
	 */
	protected function ValidateEmail($email){
		if(empty($email)){
			return true; // Email is optional
		}
		// Use filter_var for proper email validation
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
			return false;
		}
		// Limit length
		if(strlen($email) > 254){
			return false;
		}
		return $email;
	}

	/**
	 * Return an array of possible permissions
	 *
	 */
	public static function PossiblePermissions(){
		$possible	= array();
		$scripts	= \gp\admin\Tools::AdminScripts();

		foreach($scripts as $script => $info){

			if(isset($info['permission'])){
				continue;
			}

			if(!isset($info['label'])){
				continue;
			}
			$script = str_replace('/','_',$script);
			$possible[$script] = $info['label'];
		}

		return $possible;
	}


	/**
	 * Save changes made to an existing user's permissions
	 *
	 */
	public function SaveChanges(){
		global $langmessage,$gpAdmin;

		// Safely get and validate username
		$username = isset($_REQUEST['username']) ? (string)$_REQUEST['username'] : '';
		$username = $this->SanitizeUsername($username);

		if($username === false || !isset($this->users[$username])){
			msg($langmessage['OOPS']);
			return false;
		}

		// Validate and save email
		if(!empty($_POST['email'])){
			$email = $this->ValidateEmail($_POST['email']);
			if($email === false){
				msg($langmessage['OOPS']);
				return false;
			}
			$this->users[$username]['email'] = $email;
		}

		$this->users[$username]['granted'] = $this->GetPostedPermissions($username);
		$this->users[$username]['editing'] = $this->GetEditingPermissions();

		// Update the session file
		$userinfo = $this->users[$username];
		$userinfo = \gp\tool\Session::SetSessionFileName($userinfo,$username);
		$this->users[$username] = $userinfo;

		if(!$this->SaveUserFile()){
			return false;
		}

		// Update the user's session file
		$this->UserFileDetails($username);
		return true;
	}

	/**
	 * Update the users session file with new permission data
	 *
	 */
	public function UserFileDetails($username){
		global $dataDir, $gpAdmin;

		// Validate username
		$username = $this->SanitizeUsername($username);
		if($username === false){
			return false;
		}

		if(!isset($this->users[$username])){
			return false;
		}

		$user_info			= $this->users[$username];
		$user_file			= $dataDir.'/data/_sessions/'.$user_info['file_name'];

		if($gpAdmin['username'] === $username){
			$new_info = $gpAdmin;
		}else{
			$new_info = \gp\tool\Files::Get($user_file,'gpAdmin');
		}

		if(!$new_info){
			return false;
		}

		$new_info['granted'] = $user_info['granted'];
		$new_info['editing'] = $user_info['editing'];
		return \gp\tool\Files::SaveData($user_file,'gpAdmin',$new_info);
	}

	/**
	 * Display the permissions of a single user
	 *
	 */
	public function ChangeDetails(){
		global $langmessage;

		$username = isset($_REQUEST['username']) ? (string)$_REQUEST['username'] : '';
		$username = $this->SanitizeUsername($username);

		if($username === false || !isset($this->users[$username])){
			msg($langmessage['OOPS']);
			return false;
		}

		echo '<h2>'.htmlspecialchars($langmessage['user_permissions']).'</h2>';

		$userinfo = $this->users[$username];

		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post" id="permission_form">';
		echo '<input type="hidden" name="cmd" value="SaveChanges" />';
		echo '<input type="hidden" name="username" value="'.htmlspecialchars($username).'" />';

		echo '<table class="bordered">';
		echo '<tr>';
			echo '<th colspan="2">';
			echo htmlspecialchars($langmessage['details']);
			echo ' - ';
			echo htmlspecialchars($username);
			echo '</th>';
			echo '</tr>';

		$this->DetailsForm($userinfo);

		echo '<tr><td>';
			echo '</td><td>';
			echo ' <input type="submit" name="aaa" value="'.htmlspecialchars($langmessage['save']).'" class="gpsubmit"/>';
			echo ' <input type="reset" class="gpsubmit" />';
			echo ' <input type="submit" name="cmd" value="'.htmlspecialchars($langmessage['cancel']).'" class="gpcancel"/>';
			echo '</td>';
			echo '</tr>';

		echo '</table>';
		echo '</form>';
	}

	/**
	 * Remove a user from the installation
	 *
	 */
	public function RemoveUser(){
		global $langmessage;
		$username = $this->CheckUser();

		if($username === false){
			return false;
		}

		unset($this->users[$username]);
		return $this->SaveUserFile();
	}

	/**
	 * Make sure the submitted username exists
	 *
	 */
	public function CheckUser(){
		global $langmessage,$gpAdmin;

		$username = isset($_POST['username']) ? (string)$_POST['username'] : '';
		$username = $this->SanitizeUsername($username);

		if($username === false || !isset($this->users[$username])){
			msg($langmessage['OOPS']);
			return false;
		}

		// Don't allow deleting self
		if($username === $gpAdmin['username']){
			msg($langmessage['OOPS']);
			return false;
		}

		return $username;
	}

	/**
	 * Validate password strength
	 *
	 */
	protected function ValidatePasswordStrength($password){
		// Minimum length check
		if(strlen($password) < 8){
			return false;
		}
		// Could add more complexity checks here if needed
		return true;
	}

	/**
	 * Create a new user
	 *
	 */
	public function CreateNewUser(){
		global $langmessage;

		$_POST = array_merge(array('grant'=>'', 'email'=>''), $_POST);

		// Validate password
		if(empty($_POST['password']) || ($_POST['password'] !== $_POST['password1'])){
			msg($langmessage['invalid_password']);
			return false;
		}

		// Validate password strength
		if(!$this->ValidatePasswordStrength($_POST['password'])){
			msg('Password must be at least 8 characters long');
			return false;
		}

		$newname = (string)$_POST['username'];
		$newname = $this->SanitizeUsername($newname);

		if($newname === false){
			msg($langmessage['invalid_username']);
			return false;
		}

		if(isset($this->users[$newname])){
			msg($langmessage['OOPS']);
			return false;
		}

		// Validate and save email
		if(!empty($_POST['email'])){
			$email = $this->ValidateEmail($_POST['email']);
			if($email === false){
				msg($langmessage['OOPS']);
				return false;
			}
			$this->users[$newname]['email'] = $email;
		}

		$this->users[$newname]['granted']	= $this->GetPostedPermissions($newname);
		$this->users[$newname]['editing']	= $this->GetEditingPermissions();

		$this->SetUserPass($newname, $_POST['password']);

		if($this->SaveUserFile()){
			$url = \gp\tool::GetUrl('Admin/Users','',false);
			\gp\tool::Redirect($url);
		}

		return false;
	}


	/**
	 * Set the user password and password hash algorithm
	 *
	 */
	public function SetUserPass($username, $password){

		if(!isset($this->users[$username])){
			return false;
		}

		$user_info = &$this->users[$username];

		if(function_exists('password_hash') && isset($_REQUEST['algo']) && $_REQUEST['algo'] === 'password_hash'){
			$temp					= \gp\tool::hash($password,'sha512',50);
			$user_info['password']	= password_hash($temp,PASSWORD_DEFAULT);
			$user_info['passhash']	= 'password_hash';

		}else{
			$user_info['password']	= \gp\tool::hash($password,'sha512');
			$user_info['passhash']	= 'sha512';
		}

		return true;
	}


	/**
	 * Return the posted admin permissions
	 *
	 */
	public function GetPostedPermissions($username){
		global $gpAdmin;

		if(isset($_POST['grant_all']) && $_POST['grant_all'] === 'all'){
			return 'all';
		}

		$_POST = array_merge(array('grant'=>array()), $_POST);
		$array = $_POST['grant'];

		// Cannot remove self from Admin/Users
		if($username === $gpAdmin['username']){
			$array = array_merge($array, array('Admin/Users'));
		}

		if(!is_array($array)){
			return '';
		}

		$keys = array_keys($this->possible_permissions);
		$array = array_intersect($keys,$array);
		return implode(',',$array);
	}


	/**
	 * Return the posted file editing permissions
	 *
	 */
	public function GetEditingPermissions(){
		global $gp_titles;

		if(isset($_POST['editing_all']) && $_POST['editing_all'] === 'all'){
			return 'all';
		}

		$_POST = array_merge(array('titles'=>array()), $_POST);
		$array = $_POST['titles'];

		if(!is_array($array)){
			return '';
		}

		$keys = array_keys($gp_titles);
		$array = array_intersect($keys,$array);
		if(count($array) > 0){
			return ','.implode(',',$array).',';
		}
		return '';
	}


	/**
	 * Save user file
	 *
	 */
	public function SaveUserFile($refresh = true){
		global $langmessage;

		if(!\gp\tool\Files::SaveData('_site/users','users',$this->users)){
			msg($langmessage['OOPS']);
			return false;
		}

		if($refresh && isset($_GET['gpreq']) && $_GET['gpreq'] === 'json'){
			msg($langmessage['SAVED'].' '.$langmessage['REFRESH']);
		}else{
			msg($langmessage['SAVED']);
		}
		return true;
	}


	/**
	 * Show all users and their permissions
	 *
	 */
	public function DefaultDisplay(){
		global $langmessage;

		echo '<h2>'.htmlspecialchars($langmessage['user_permissions']).'</h2>';

		ob_start();
		echo '<table class="bordered full_width">';
		echo '<tr><th>';
		echo htmlspecialchars($langmessage['username']);
		echo '</th><th>';
		echo htmlspecialchars($langmessage['Password Algorithm']);
		echo '</th><th>';
		echo htmlspecialchars($langmessage['permissions']);
		echo '</th><th>';
		echo htmlspecialchars($langmessage['file_editing']);
		echo '</th><th>';
		echo htmlspecialchars($langmessage['options']);
		echo '</th></tr>';

		foreach($this->users as $username => $userinfo){

			echo '<tr><td>';
			echo htmlspecialchars($username);

			// Algorithm
			echo '</td><td>';
			$this->PassAlgo($userinfo);

			// Admin permissions
			echo '</td><td>';
				if($userinfo['granted'] === 'all'){
					echo htmlspecialchars('all');
				}elseif(!empty($userinfo['granted'])){

					$permissions = explode(',',$userinfo['granted']);
					$list = array();
					foreach($permissions as $permission){
						if(isset($this->possible_permissions[$permission])){
							$list[] = strip_tags($this->possible_permissions[$permission]);
						}
					}
					if(count($list)){
						echo htmlspecialchars(implode(', ',$list));
					}else{
						echo htmlspecialchars($langmessage['None']);
					}
				}else{
					echo htmlspecialchars($langmessage['None']);
				}

			echo '</td>';

			// File editing
			echo '<td>';

			if($userinfo['editing'] === 'all'){
				echo htmlspecialchars($langmessage['All']);
			}else{

				$count = preg_match_all('#,#',$userinfo['editing']) - 1;
				if($count > 0){
					echo htmlspecialchars(sprintf($langmessage['%s Pages'],$count));
				}else{
					echo htmlspecialchars($langmessage['None']);
				}
			}

			echo '</td>';

			// Options
			echo '<td>';
			echo \gp\tool::Link('Admin/Users',$langmessage['details'],'cmd=details&username='.urlencode($username));
			echo ' &nbsp; ';
			echo \gp\tool::Link('Admin/Users',$langmessage['password'],'cmd=changepass&username='.urlencode($username));
			echo ' &nbsp; ';

			$title = sprintf($langmessage['generic_delete_confirm'],htmlspecialchars($username));
			echo \gp\tool::Link('Admin/Users',$langmessage['delete'],'cmd=RemoveUser&username='.urlencode($username),array('data-cmd'=>'postlink','title'=>$title,'class'=>'gpconfirm'));
			echo '</td>';
			echo '</tr>';
		}
		echo '<tr><th colspan="5">';
		echo \gp\tool::Link('Admin/Users',$langmessage['new_user'],'cmd=newuserform');
		echo '</th>';

		echo '</table>';

		$content = ob_get_clean();

		if($this->has_weak_pass){
			echo '<p class="gp_notice"><b>Warning:</b> ';
			echo 'Weak password algorithms are being used for one or more users. To fix this issue, reset the user\'s password. ';
			echo '</p>';
		}

		echo $content;
	}


	/**
	 * Display the password algorithm being used for the user
	 *
	 */
	public function PassAlgo($userinfo){

		$algo = \gp\tool\Session::PassAlgo($userinfo);
		switch($algo){
			case 'md5':
			case 'sha1':
				$this->has_weak_pass = true;
				echo '<span style="color:red">'.htmlspecialchars($algo).'</span>';
				return;
		}
		echo htmlspecialchars($algo);
	}


	/**
	 * Display form for adding new admin user
	 *
	 */
	public function NewUserForm(){
		global $langmessage;

		echo '<h2>'.htmlspecialchars($langmessage['user_permissions']).'</h2>';

		$_POST = array_merge(array('username'=>'','email'=>'','grant'=>array(),'grant_all'=>'all','editing_all'=>'all'), $_POST);

		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post" id="permission_form">';
		echo '<table class="bordered" style="width:95%">';
		echo '<tr><th colspan="2">';
			echo htmlspecialchars($langmessage['new_user']);
			echo '</th></tr>';
		echo '<tr><td>';
			echo htmlspecialchars($langmessage['username']);
			echo '</td><td>';
			echo '<input type="text" name="username" value="'.htmlspecialchars($_POST['username']).'" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo htmlspecialchars($langmessage['password']);
			echo '</td><td>';
			echo '<input type="password" name="password" value="" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo str_replace(' ','&nbsp;',htmlspecialchars($langmessage['repeat_password']));
			echo '</td><td>';
			echo '<input type="password" name="password1" value="" class="gpinput"/>';
			echo '</td></tr>';

		$this->AlgoSelect();

		$_POST['granted'] = $this->GetPostedPermissions(false);
		$_POST['editing'] = $this->GetEditingPermissions();
		$this->DetailsForm($_POST);


		echo '<tr><td>';
			echo '</td><td>';
			echo '<input type="hidden" name="cmd" value="CreateNewUser" />';
			echo ' <input type="submit" name="aaa" value="'.htmlspecialchars($langmessage['save']).'" class="gpsubmit"/>';
			echo ' <input type="reset" class="gpsubmit"/>';
			echo ' <input type="submit" name="cmd" value="'.htmlspecialchars($langmessage['cancel']).'" class="gpcancel"/>';
			echo '</td></tr>';

		echo '</table>';
		echo '</form>';
	}

	/**
	 * Display <select> for password algorithm
	 *
	 */
	public function AlgoSelect(){
		global $langmessage;

		$algos						= array();
		if(function_exists('password_hash')){
			$algos['password_hash']		= true;
			$algos['sha512']			= true;
		}else{
			$algos['sha512']			= true;
			$algos['password_hash']		= false;
		}

		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',htmlspecialchars($langmessage['Password Algorithm']));
		echo '</td><td>';
		echo '<select name="algo" class="gpselect">';
		foreach($algos as $algo => $avail){

			$attr = '';
			if(!$avail){
				$attr .= 'disabled';
			}
			if(isset($_REQUEST['algo']) && $algo === $_REQUEST['algo']){
				$attr .= ' selected';
			}
			echo '<option value="'.htmlspecialchars($algo).'" '.$attr.'>'.htmlspecialchars($algo).'</option>';
		}
		echo '</select>';

		echo ' &nbsp; <span class="sm text-muted">password_hash requires PHP 5.5+</span>';

		echo '</td></tr>';
	}


	/**
	 * Display permission options
	 *
	 */
	public function DetailsForm($values=array()){
		global $langmessage, $gp_titles;

		$values = array_merge(array('granted'=>'','email'=>''), $values);

		// Email address
		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',htmlspecialchars($langmessage['email_address']));
		echo '</td><td>';
		echo '<input type="text" name="email" value="'.htmlspecialchars($values['email']).'" class="gpinput"/>';
		echo ' - DMARC compliant address !</td></tr>';


		// Admin permissions
		echo '<tr><td>';
		echo str_replace(' ','&nbsp;',htmlspecialchars($langmessage['grant_usage']));
		echo '</td><td class="all_checkboxes">';

		$all = false;
		$current = $values['granted'];
		$checked = '';
		if($current === 'all'){
			$all = true;
			$checked = ' checked="checked" ';
		}else{
			$current = ','.$current.',';
		}

		echo '<p><label class="select_all">';
		echo '<input type="checkbox" class="select_all" name="grant_all" value="all" '.$checked.'/>';
		echo htmlspecialchars($langmessage['All']);
		echo '</label></p>';

		foreach($this->possible_permissions as $permission => $label){
			$checked = '';
			if($all){
				$checked = ' checked="checked" ';
			}elseif(strpos($current,','.$permission.',') !== false){
				$checked = ' checked="checked" ';
			}

			echo '<label class="all_checkbox">';
			echo '<input type="checkbox" name="grant[]" value="'.htmlspecialchars($permission).'" '.$checked.'/>';
			$title_attr = trim(strip_tags($label));
			preg_match('/title="(.*?)".*?>/si', $label, $matches); 
			if(isset($matches[1])){
				$title_attr = htmlspecialchars($matches[1]).': '.$title_attr;
			}
			echo '<span title="'.htmlspecialchars($title_attr).'">'.htmlspecialchars($label).'</span>';
			echo '</label> ';
		}

		echo '</td></tr>';

		// File editing
		echo '<tr><td>';
		echo htmlspecialchars($langmessage['file_editing']);
		echo '</td><td class="all_checkboxes">';

		$editing_values = $values['editing'];
		$all = ($editing_values === 'all');
		$checked = $all ? ' checked="checked" ' : '';
		echo '<p><label class="select_all">';
		echo '<input type="checkbox" class="select_all" name="editing_all" value="all" '.$checked.'/> ';
		echo htmlspecialchars($langmessage['All']);
		echo '</label></p>';

		echo '<div style="max-height:168px;overflow:auto;">';

		$ordered = array();
		foreach($gp_titles as $index => $info){
			$ordered[$index] = strip_tags(\gp\tool::GetLabelIndex($index));
		}

		uasort($ordered,'strnatcasecmp');

		foreach($ordered as $index => $label){
			$label = strip_tags($label);
			$checked = '';
			if($all){
				$checked = ' checked="checked" ';
			}elseif(strpos($editing_values,','.$index.',') !== false){
				$checked = ' checked="checked" ';
			}

			echo '<label class="all_checkbox">';
			echo '<input type="checkbox" name="titles[]" value="'.htmlspecialchars($index).'" '.$checked.'/>';
			echo '<span title="'.htmlspecialchars($label).'">'.htmlspecialchars($label).'</span>';
			echo '</label> ';
		}

		echo '</div>';
		echo '</td></tr>';
	}

	/**
	 * Display form for changing a user password
	 *
	 */
	public function ChangePass(){
		global $langmessage;

		$username = isset($_REQUEST['username']) ? (string)$_REQUEST['username'] : '';
		$username = $this->SanitizeUsername($username);

		if($username === false || !isset($this->users[$username])){
			msg($langmessage['OOPS']);
			return false;
		}

		echo '<form action="'.\gp\tool::GetUrl('Admin/Users').'" method="post">';
		echo '<input type="hidden" name="cmd" value="resetpass" />';
		echo '<input type="hidden" name="username" value="'.htmlspecialchars($username).'" />';

		echo '<table class="bordered">';
		echo '<tr><th colspan="2">';
			echo htmlspecialchars($langmessage['change_password']);
			echo ' - ';
			echo htmlspecialchars($username);
			echo '</th></tr>';
		echo '<tr><td>';
			echo htmlspecialchars($langmessage['new_password']);
			echo '</td><td>';
			echo '<input type="password" name="password" value="" class="gpinput"/>';
			echo '</td></tr>';
		echo '<tr><td>';
			echo str_replace(' ','&nbsp;',htmlspecialchars($langmessage['repeat_password']));
			echo '</td><td>';
			echo '<input type="password" name="password1" value="" class="gpinput"/>';
			echo '</td></tr>';

		$this->AlgoSelect();

		echo '<tr><td>';
			echo '</td><td>';
			echo '<input type="submit" name="aaa" value="'.htmlspecialchars($langmessage['save']).'" class="gpsubmit" />';
			echo ' <input type="submit" name="cmd" value="'.htmlspecialchars($langmessage['cancel']).'" class="gpcancel" />';
			echo '</td></tr>';
		echo '</table>';
		echo '</form>';
	}

	/**
	 * Save a user's new password
	 *
	 */
	public function ResetPass(){
		global $langmessage, $config;

		if(!$this->CheckPasswords()){
			return false;
		}

		$username = isset($_POST['username']) ? (string)$_POST['username'] : '';
		$username = $this->SanitizeUsername($username);

		if($username === false || !isset($this->users[$username])){
			msg($langmessage['OOPS']);
			return false;
		}

		if(!$this->ValidatePasswordStrength($_POST['password'])){
			msg('Password must be at least 8 characters long');
			return false;
		}

		$this->SetUserPass($username, $_POST['password']);

		return $this->SaveUserFile();
	}

	/**
	 * Check the posted passwords
	 * Make sure they're not empty and match each other
	 *
	 */
	public function CheckPasswords(){
		global $langmessage;

		if(empty($_POST['password']) || $_POST['password'] !== $_POST['password1']){
			msg($langmessage['invalid_password']);
			return false;
		}
		return true;
	}

	/**
	 * Get users from storage
	 *
	 */
	public function GetUsers(){

		$this->users = \gp\tool\Files::Get('_site/users');

		if(!is_array($this->users)){
			$this->users = array();
		}

		// Fix the editing value
		foreach($this->users as $username => $userinfo){
			$userinfo = array_merge(array('granted'=>''), $userinfo);
			\gp\admin\Tools::EditingValue($userinfo);
			$this->users[$username] = $userinfo;
		}
	}


	/**
	 * Get the menu indexes
	 *
	 */
	public function RequestedIndexes(){
		global $langmessage, $gp_titles;

		$_REQUEST = array_merge(array('index'=>''), $_REQUEST);
		$indexes = explode(',',$_REQUEST['index']);

		if(empty($indexes)){
			msg($langmessage['OOPS'].' Invalid Title (1)');
			return false;
		}

		$cleaned = array();
		foreach($indexes as $index){
			if(!isset($gp_titles[$index])){
				continue;
			}
			$cleaned[] = $index;
		}

		if(empty($cleaned)){
			msg($langmessage['OOPS'].' Invalid Title (2)');
			return false;
		}

		return $cleaned;
	}
}