<?php

/**
 * Front-end of Register_XH.
 *
 * Copyright (c) 2007 Carsten Heinelt (http://cmsimple.heinelt.eu)
 * Copyright (c) 2010-2012 Gert Ebersbach (http://www.ge-webdesign.de/cmsimpleplugins/)
 * Copyright (c) 2012-2017 Christoph M. Becker
 */


if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}


define('REGISTER_VERSION', '@REGISTER_VERSION@');


/****************************************************************************
 *	Direct Calls															*
 ****************************************************************************/


if ($plugin_cf['register']['login_all_subsites']) {
	define('REGISTER_SESSION_NAME', CMSIMPLE_ROOT);
} else {
	define('REGISTER_SESSION_NAME', CMSIMPLE_ROOT . $sl);
}

if (function_exists('XH_startSession')) {
    XH_startSession();
} elseif (session_id() === '') {
    session_start();
}


function Register_dataFolder()
{
	global $sl, $cf, $plugin_cf, $pth;

	if ($plugin_cf['register']['login_all_subsites']) {
		if ($sl === $cf['language']['default']) {
			$folder = "{$pth['folder']['content']}register/";
		} else {
			$folder = dirname($pth['folder']['content']) . "/register/";
		}
	} else {
		$folder = "{$pth['folder']['content']}register/";
	}
	if (!file_exists($folder)) {
		mkdir($folder, 0777, true);
		chmod($folder, 0777);
		(new Register\DbService($folder))->writeUsers([]);
		(new Register\DbService($folder))->writeGroups(
			[(object) ['groupname' => $plugin_cf['register']['group_default'], 'loginpage' => '']]
		);
	}
	return $folder;
}

/**
 * @return bool
 */
function Register_isLoggedIn()
{
	return (bool) Register_currentUser();
}

// Handling of login/logout =====================================================

if ($plugin_cf['register']['remember_user']
		&& isset($_COOKIE['register_username'], $_COOKIE['register_password']) && !Register_isLoggedIn()) {
	$function = "registerlogin";
}

if (!($edit&&$adm) && $plugin_cf['register']['hide_pages'])
{
	if ($temp = Register_currentUser()) {
		registerRemoveHiddenPages($temp->accessgroups);
	} else {
		registerRemoveHiddenPages([]);
	}
}

/****************************************************************************
 *	Function Definitions					*
 ****************************************************************************/

/*
 * Remove access restricted pages. Supported are multiple groups per page and
 * multiple user groups.
 */
function registerRemoveHiddenPages($userGroups) {
    global $cl, $c;

    for ($i = 0; $i < $cl; $i++) {
	if (preg_match('/(?:#CMSimple\s+|{{{.*?)access\((.*?)\)\s*;?\s*(?:#|}}})/isu', $c[$i], $matches)) {
            if ($arg = trim($matches[1], "\"'")) {
		$groups = array_map('trim', explode(',', $arg));
		if (count(array_intersect($groups, $userGroups)) == 0) {
		    $c[$i]= "#CMSimple hide# {{{PLUGIN:access('$arg');}}}";
		}
	    }
	}
    }
}

/*
 * Access function to be called from inside CMSimple scripting tag.
 */
function access($groupString)
{
	global $plugin_tx, $sn, $function;

	// remove spaces etc.
	$groupString = preg_replace("/[ \t\r\n]*/", '', $groupString);
	$groupNames = explode(",", $groupString);

	$user = Register_currentUser();
	if ($function !== 'search'
			&& (!Register_isLoggedIn() || !count(array_intersect($groupNames, $user->accessgroups)))) {
		// go to access error page
		$pageTitle = uenc($plugin_tx['register']['access_error']);
		header('Location: '.CMSIMPLE_URL.'?'. $pageTitle);
		exit;
	}
	return '';
}


function Register_groupLoginPage($group)
{
    global $pth, $plugin_tx;

    $groups = (new Register\DbService(Register_dataFolder()))->readGroups();
    foreach ($groups as $rec) {
	if ($rec->groupname == $group) {
	    return $rec->loginpage;
	}
    }
    return false;
}

/*
 *  Add new user to array.
 */
function registerAddUser(array $array, $username, $password, $accessgroups, $name, $email, $status)
{
	$entry = (object) array(
	'username' => $username,
	'password' => $password,
	'accessgroups' => $accessgroups,
	'name' => $name,
	'email' => $email,
	'status' => $status);

	$array[] = $entry;
	return $array;
}

/*
 *  Search array of user entries for key and value.
 *  Arguments:
 *   $array		array of user entries
 *   $key		key in user entry to look for
 *   $value		value to match user entry key
 *
 *  Returns:
 *   false		in case of no value found
 *   $entry		found user entry
 */
function registerSearchUserArray(array $array, $key, $value)
{
	foreach($array as $entry)
	{
		if(isset($entry->{$key}) && $entry->{$key} == $value)
		return $entry;
	}
	return false;
}

/*
 *  Replace user entry in array.
 *  Arguments:
 *   $array		array of user entries
 *   $newentry	user entry to replace
 *
 *  Returns:
 *   $newarray	updated array
 */
function registerReplaceUserEntry(array $array, stdClass $newentry)
{
	$newarray = array();
	$username = $newentry->username;
	foreach($array as $entry)
	{
	if(isset($entry->username) && $entry->username == $username) $newarray[] = $newentry;
	else
	$newarray[] = $entry;
	}
	return $newarray;
}

/*
 *  Delete user entry in array.
 *  Arguments:
 *   $array		array of user entries
 *   $username	username for which entry should get removed in array
 *
 *  Returns:
 *   $newarray	updated array
 */
function registerDeleteUserEntry(array $array, $username)
{
	$newarray = array();
	foreach($array as $entry)
	{
		if(isset($entry->username) && $entry->username != $username) $newarray[] = $entry;
	}
	return $newarray;
}


/**
 * Returns the user record, if the user is logged in, otherwise null.
 *
 * @return ?object
 */
function Register_currentUser()
{
    static $user = null;

	if (!$user) {
		if (isset($_SESSION['username'], $_SESSION['register_sn'])
				&& $_SESSION['register_sn'] == REGISTER_SESSION_NAME) {
			(new Register\DbService(Register_dataFolder()))->lock(LOCK_SH);
			$users = (new Register\DbService(Register_dataFolder()))->readUsers();
			$rec = registerSearchUserArray($users, 'username', $_SESSION['username']);
			(new Register\DbService(Register_dataFolder()))->lock(LOCK_UN);
			if ($rec) {
				$user = $rec;
			} else {
				Register_logout();
				$user = null;
			}
		} else {
			$user = null;
		}
	}
	return $user;
}

function Register_logout()
{
	session_regenerate_id(true);
	unset($_SESSION['username'], $_SESSION['register_sn']);
	if (isset($_COOKIE['register_username'], $_COOKIE['register_password'])) {
		setcookie('register_username', '', 0, CMSIMPLE_ROOT);
		setcookie('register_password', '', 0, CMSIMPLE_ROOT);
	}
}

/**
 * @param string $name
 * @param string $username
 * @param string $password1
 * @param string $password2
 * @param string $email
 * @return string[]
 */
function registerCheckEntry($name, $username, $password1, $password2, $email)
{
	global $plugin_tx;

	$errors = [];

	// check for empty or illegal/wrong fields
	if (empty($name)) {
		$errors[] = $plugin_tx['register']['err_name'];
	}
	if ($username == '') {
		$errors[] = $plugin_tx['register']['err_username'];
	} elseif (!preg_match("/^[A-Za-z0-9_]+$/", $username)) {
		$errors[] = $plugin_tx['register']['err_username_illegal'];
	}
	if ($password1 == '') {
		$errors[] = $plugin_tx['register']['err_password'];
	} elseif (!preg_match("/^[A-Za-z0-9_]+$/", $password1)) {
		$errors[] = $plugin_tx['register']['err_password_illegal'];
	}
	if ($password2 == '' || $password1 != $password2) {
		$errors[] = $plugin_tx['register']['err_password2'];
	}
	if ($email == '') {
		$errors[] = $plugin_tx['register']['err_email'];
	} elseif (!preg_match("/^[^\s()<>@,;:\"\/\[\]?=]+@\w[\w-]*(\.\w[\w-]*)*\.[a-z]{2,}$/i",$email)) {
		$errors[] = $plugin_tx['register']['err_email_invalid'];
	}
	return $errors;
}

/**
 * @param string $name
 * @param string $username
 * @param string $password1
 * @param string $email
 * @return string[]
 */
function registerCheckColons($name, $username, $password, $email)
{
	global $plugin_tx;

	$errors = [];
	foreach (['name', 'username', 'password', 'email'] as $field) {
		if (strpos($$field, ":") !== false) {
			$errors[] = $plugin_tx['register'][$field] . ' ' . $plugin_tx['register']['err_colon'];
		}
	}
	return $errors;
}

/*
 * Function to create and handle register form (Top Level Function).
 *
 */
function registerUser()
{
	// In case user is logged in, no registration page is shown
	if (Register_isLoggedIn()) {
		header('Location: ' . CMSIMPLE_URL);
		exit;
	}
	$controller = new Register\RegistrationController;
	if (isset($_POST['action']) && $_POST['action'] === 'register_user') {
		$action = 'registerUserAction';
	} elseif (isset($_GET['action']) && $_GET['action'] === 'register_activate_user') {
		$action = 'activateUserAction';
	} else {
		$action = 'defaultAction';
	}
	ob_start();
	$controller->{$action}();
	return ob_get_clean();
}

/*
 * Function to create and handle forgotten password form (Top Level Function)
 */
function registerForgotPassword()
{
	// In case user is logged in, no password forgotten page is shown
	if (Register_isLoggedIn()) {
		header('Location: ' . CMSIMPLE_URL);
		exit;
	}
	$controller = new Register\ForgotPasswordController;
	if (isset($_POST['action']) && $_POST['action'] === 'forgotten_password') {
		$action = 'passwordForgottenAction';
	} elseif (isset($_GET['action']) && $_GET['action'] === 'registerResetPassword') {
		$action = 'resetPasswordAction';
	} else {
		$action = 'defaultAction';
	}
	ob_start();
	$controller->{$action}();
	return ob_get_clean();
}

/*
 * Function to create and handle user preferences form (Top Level Function).
 *
 */
function registerUserPrefs()
{
	global $plugin_tx;

	if (!Register_isLoggedIn()) {
		return XH_message('fail', $plugin_tx['register']['access_error_text']);
	}
	$controller = new Register\UserPrefsController;
	if (isset($_POST['action']) && $_POST['action'] === 'edit_user_prefs' && isset($_POST['submit'])) {
		$action = 'editAction';
	} elseif (isset($_POST['action']) && $_POST['action'] === 'edit_user_prefs' && isset($_POST['delete'])) {
		$action = 'deleteAction';
	} else {
		$action = 'defaultAction';
	}
	ob_start();
	$controller->{$action}();
	return ob_get_clean();
}

/*
 *  This function creates a link to the "Registration" page (Top Level Function).
 */
function registerloginform()
{
	global $plugin_cf, $plugin_tx, $sn, $su;

	// If logged in show user preferences link, otherwise register and forgot email links.

	if (!Register_isLoggedIn()) {
		// Begin register- and loginarea and user fields
		$view = new Register\View('loginform');
		$view->isHorizontal = $plugin_cf['register']['login_layout'] === 'horizontal';
		$view->actionUrl = sv('REQUEST_URI');
		$forgotPasswordUrl = uenc($plugin_tx['register']['forgot_password']);
		$view->hasForgotPasswordLink = $plugin_cf['register']['password_forgotten']
			&& isset($su) && urldecode($su) != $forgotPasswordUrl;
		$view->forgotPasswordUrl = "$sn?$forgotPasswordUrl";
		$view->hasRememberMe = $plugin_cf['register']['remember_user'];
		$view->isRegisterAllowed = $plugin_cf['register']['allowed_register'];
		$registerUrl = uenc($plugin_tx['register']['register']);
		$view->registerUrl = "$sn?$registerUrl";
	} else {
		// Logout Link and Preferences Link
		$view = new Register\View('loggedin-area');
		$view->isHorizontal = $plugin_cf['register']['login_layout'] === 'horizontal';
		$user = Register_currentUser();
		$view->fullName = $user->name;
		$currentUser = $user;
		$userPrefUrl = uenc($plugin_tx['register']['user_prefs']);
		$view->hasUserPrefs = $currentUser->status == 'activated' && isset($su)
		    && urldecode($su) != $userPrefUrl;
		$view->userPrefUrl = "?$userPrefUrl";
		$view->logoutUrl = "$sn?&function=registerlogout";
	}
	return (string) $view;
}


/**
 * Returns the logged in form, if user is logged in.
 *
 * @since 1.5rc1
 *
 * @return  string
 */
function Register_loggedInForm()
{
    return Register_isLoggedIn() ? registerloginform() : '';
}


/*
 * This function outputs the full name of the current user (Top Level Function).
 */
function registeradminmodelink()
{
    trigger_error('registeradminmodelink() is deprecated', E_USER_WARNING);
    return FALSE;
}

(new Register\Plugin)->run();

?>
