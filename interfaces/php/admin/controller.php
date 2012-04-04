<?php
if(strrpos($_SERVER['REQUEST_URI'],'controller.php') !== false) {
	header('Location: ./');
	exit;
}
require_once('./constants.php');

require_once(CASH_PLATFORM_PATH);
$pages_path = ADMIN_BASE_PATH . '/components/pages/';
$admin_primary_cash_request = new CASHRequest();
$request_parameters = null;

// admin-specific autoloader
function cash_admin_autoloadCore($classname) {
	$file = ADMIN_BASE_PATH . '/classes/'.$classname.'.php';
	if (file_exists($file)) {
		require_once($file);
	}
}
spl_autoload_register('cash_admin_autoloadCore');

// grab path from .htaccess redirect
if ($_REQUEST['p'] && ($_REQUEST['p'] != realpath(ADMIN_BASE_PATH))) {
	$parsed_request = str_replace('/','_',trim($_REQUEST['p'],'/'));
	if (file_exists($pages_path . 'definitions/' . $parsed_request . '.php') && file_exists($pages_path . 'markup/' . $parsed_request . '.php')) {
		define('BASE_PAGENAME', $parsed_request);
		$include_filename = BASE_PAGENAME.'.php';
	} else {
		// cascade through a "failure" to see if it is a true bad request, or a page requested
		// with parameters requested — always show the last good true filename and push the
		// remaining request portions into te request_parameters array
		if (strpos($parsed_request,'_') !== false) {
			$fails_at_level = 0;
			$successful_request = '';
			$exploded_request = explode('_',$parsed_request);
			for($i = 0, $a = sizeof($exploded_request); $i < $a; ++$i) {
				if ($i > 0) {
					$test_request = $successful_request . '_' . $exploded_request[$i];
				} else {
					$test_request = $successful_request . $exploded_request[$i];
				}
				if (file_exists($pages_path . 'definitions/' . $test_request . '.php') && file_exists($pages_path . 'markup/' . $test_request . '.php')) {
					$successful_request = $test_request;
				} else {
					$fails_at_level = $i;
					break;
				}
			}
			if ($fails_at_level == 0) {
				define('BASE_PAGENAME', '');
				$include_filename = 'error.php';
			} else {
				// define page as successful request
				define('BASE_PAGENAME', $successful_request);
				$include_filename = BASE_PAGENAME.'.php';
				// turn the rest of the request into the parameters array
				$request_parameters = array_slice($exploded_request, 0 - (sizeof($exploded_request) - ($fails_at_level)));
			}
		} else {
			define('BASE_PAGENAME', '');
			$include_filename = 'error.php';
		}
	}
} else {
	define('BASE_PAGENAME','mainpage');
	$include_filename = 'mainpage.php';
}

$run_login_scripts = false;

// if a login needs doing, do it
$login_message = "Log In";
if (isset($_POST['login'])) {
	$login_details = AdminHelper::doLogin($_POST['address'],$_POST['password']);
	if ($login_details !== false) {
		$admin_primary_cash_request->sessionSet('cash_actual_user',$login_details);
		$admin_primary_cash_request->sessionSet('cash_effective_user',$login_details);
		$admin_primary_cash_request->sessionSet('cash_effective_user_email',$_POST['address']);
		
		$run_login_scripts = true;
		
		if ($include_filename == 'logout.php') {
			header('Location: ' . ADMIN_WWW_BASE_PATH);
			exit;
		}
	} else {
		$admin_primary_cash_request->sessionClearAll();
		$login_message = "Try Again";
	}
}

// make an object to use throughout the pages
$cash_admin = new AdminCore($admin_primary_cash_request->sessionGet('cash_effective_user'));

if ($run_login_scripts) {
	// handle initial login chores
	$cash_admin->runAtLogin();
}

// handle the banner hiding
if (isset($_GET['hidebanner'])) {
	$current_settings = $cash_admin->getUserSettings();
	if (isset($current_settings['banners'][BASE_PAGENAME])) {
		$current_settings['banners'][BASE_PAGENAME] = false;
		$cash_admin->setUserSettings($current_settings);
	}
}

// finally, output the template and page-specific markup (checking for current login)
if ($admin_primary_cash_request->sessionGet('cash_actual_user')) {
	include($pages_path . 'definitions/' . $include_filename);
	include(ADMIN_BASE_PATH . '/ui/default/top.php');
	include($pages_path . 'markup/' . $include_filename);
	include(ADMIN_BASE_PATH . '/ui/default/bottom.php');
} else {
	include(ADMIN_BASE_PATH . '/ui/default/login.php');
}
?>