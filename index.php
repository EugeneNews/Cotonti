<?php
/**
 * Index loader
 *
 * @package Cotonti
 * @version 0.9.4
 * @author Neocrome, Cotonti Team
 * @copyright Copyright (c) Cotonti Team 2008-2011
 * @license BSD
 */

// Redirect to install if config is missing
if (!file_exists('./datas/config.php'))
{
	header('Location: install.php');
	exit;
}

// Let the include files know that we are Cotonti
define('COT_CODE', true);

// Load vital core configuration from file
require_once './datas/config.php';

// If it is a new install, redirect
if (isset($cfg['new_install']) && $cfg['new_install'])
{
	header('Location: install.php');
	exit;
}

// Load the Core API, the template engine
require_once $cfg['system_dir'] . '/functions.php';
require_once $cfg['system_dir'] . '/cotemplate.php';

// Bootstrap
require_once $cfg['system_dir'] . '/common.php';

// Support for ajax and popup hooked plugins
if (empty($_GET['e']) && !empty($_GET['r']))
{
	$_GET['e'] = $_GET['r'];
}
if (empty($_GET['e']) && !empty($_GET['o']))
{
	$_GET['e'] = $_GET['o'];
}

// Detect selected extension
if (empty($_GET['e']))
{
	// Default environment for index module
	define('COT_MODULE', true);
	$env['type'] = 'module';
	$env['ext'] = 'index';
}
else
{
	$found = false;
	if (preg_match('`^\w+$`', $_GET['e']))
	{
		if (file_exists($cfg['modules_dir'] . '/' . $_GET['e']))
		{
			$found = true;
			$env['type'] = 'module';
			define('COT_MODULE', true);
		}
		elseif (file_exists($cfg['plugins_dir'] . '/' . $_GET['e']))
		{
			$found = true;
			$env['type'] = 'plug'; 
			$env['location'] = 'plugins';
			define('COT_PLUG', true);
		}
	}
	if ($found)
	{
		$env['ext'] = $_GET['e'];
	}
	else
	{
		// Redirect to error page
		header('Location: ' .  $cfg['mainurl'] . '/message.php?msg=404', null, 404);
		exit;
	}
}


// Load the requested extension
if ($env['type'] == 'plug')
{
	require_once $cfg['system_dir'] . '/plugin.php';
}
else
{
	require_once $cfg['modules_dir'] . '/' . $env['ext'] . '/' . $env['ext'] . '.php';
}

?>
