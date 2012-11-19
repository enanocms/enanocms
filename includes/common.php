<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
/**
 * The main loader script that initializes everything about Enano in the proper order. Prepare to get
 * redirected if you don't have $_GET['title'] or $_SERVER['PATH_INFO'] set up.
 * @package Enano
 * @subpackage Core
 * @copyright See header block
 */

// Make sure we don't have an attempt to inject globals (register_globals on)
if ( isset($_REQUEST['GLOBALS']) )
{
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><title>Hacking Attempt</title><meta http-equiv="Content-type" content="text/html; charset=utf-8" /><style type="text/css">body{background-color:#000;color:#CCC;font-family:trebuchet ms,sans-serif;font-size:9pt;}a{color:#FFF;}</style></head><body><p>Hacking attempt using <a href="http://www.hardened-php.net/index.76.html">PHP $GLOBALS overwrite vulnerability</a> detected</p></body></html>
	<?php
	exit;
}

// only do this if it hasn't been done yet
if ( !defined('ENANO_COMMON_ROOT_LOADED') )
{

// log this
define('ENANO_COMMON_ROOT_LOADED', 1);

// Our version number
// This needs to match the version number in the database. This number should
// be the expected output of enano_version(), which will always be in the
// format of 1.0.2, 1.0.2a1, 1.0.2b1, 1.0.2RC1
// You'll want to change this for custom distributions.
$version = '1.1.8';

// Database schema version
// This is incremented each time a change to the database structure is made.
// If it doesn't match the version in the DB, the user will be asked to upgrade.
// This must match install/includes/common.php!
$db_version = 1126;

/**
 * Returns a floating-point number with the current UNIX timestamp in microseconds. Defined very early because we gotta call it
 * from very early on in the script to measure the starting time of Enano.
 * @return float
 */

// First check to see if something already declared this function.... it happens often.
if ( !function_exists('microtime_float') )
{
	function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
}

// Determine starting time
global $_starttime;
$_starttime = microtime_float();

// Verbose error reporting
if ( defined('E_STRICT') )
{
	// PHP5, PHP6
	error_reporting(E_ALL & ~E_STRICT);
}
else
{
	// PHP4
	error_reporting(E_ALL);
}

//
// Determine the location of Enano as an absolute path.
//

// We need to see if this is a specially marked Enano development server. You can create an Enano
// development server by cloning the Mercurial repository into a directory named repo, and then
// using symlinks to reference the original files so as to segregate unique files from non-unique
// and distribution-standard ones. Enano will pivot its root directory accordingly if the file
// .enanodev is found in the Enano root (not /repo/).
if ( strpos(__FILE__, '/repo/') && file_exists(dirname(__FILE__) . '/../../.enanodev') )
{
	// We have a development directory. Remove /repo/ from the picture.
	$filename = str_replace('/repo/', '/', __FILE__);
}
else
{
	// Standard Enano installation
	$filename = __FILE__;
}

// ENANO_ROOT is sometimes defined by plugins like AjIM that need the constant before the Enano API is initialized
if ( !defined('ENANO_ROOT') )
	define('ENANO_ROOT', dirname(dirname($filename)));

// We deprecated debugConsole in 1.0.2 because it was never used and there were a lot of unneeded debugging points in the code.

// _nightly.php is used to tag non-Mercurial-generated nightly builds
if ( file_exists( ENANO_ROOT . '/_nightly.php') )
	require(ENANO_ROOT.'/_nightly.php');

// List of scheduled tasks (don't change this manually, use register_cron_task())
$cron_tasks = array();

} // check for ENANO_COMMON_ROOT_LOADED
else
{
	// loading a second time
	if ( !defined('ENANO_COMMON_ROOT_LOADED_MULTI') )
	{
		define('ENANO_COMMON_ROOT_LOADED_MULTI', 1);
	}
}

// If all we really need is the root directory, just leave now
// checking for ENANO_COMMON_ROOT_LOADED_MULTI here means that if common
// is included a second time, the rest of Enano will load.
if ( defined('ENANO_COMMON_ROOTONLY') && !defined('ENANO_COMMON_ROOT_LOADED_MULTI') )
{
	return true;
}

// Start including files. LOTS of files. Yeah!
require_once(ENANO_ROOT.'/includes/constants.php');
require_once(ENANO_ROOT.'/includes/functions.php');
require_once(ENANO_ROOT.'/includes/dbal.php');
require_once(ENANO_ROOT.'/includes/paths.php');
require_once(ENANO_ROOT.'/includes/sessions.php');
require_once(ENANO_ROOT.'/includes/template.php');
require_once(ENANO_ROOT.'/includes/output.php');
require_once(ENANO_ROOT.'/includes/plugins.php');
require_once(ENANO_ROOT.'/includes/cache.php');
require_once(ENANO_ROOT.'/includes/lang.php');
require_once(ENANO_ROOT.'/includes/render.php');
require_once(ENANO_ROOT.'/includes/rijndael.php');
require_once(ENANO_ROOT.'/includes/email.php');
require_once(ENANO_ROOT.'/includes/json2.php');
require_once(ENANO_ROOT.'/includes/pageprocess.php');
require_once(ENANO_ROOT.'/includes/namespaces/default.php');
require_once(ENANO_ROOT.'/includes/tagcloud.php');
require_once(ENANO_ROOT.'/includes/hmac.php');

strip_magic_quotes_gpc();

profiler_log('Files included and magic_quotes_gpc reversed if applicable');

// Enano has five main components: the database abstraction layer (DBAL), the session manager,
// the path/URL manager, the template engine, and the plugin manager.
// Each part has its own class and a global object; nearly all Enano functions are handled by one of these five components.
// All of these classes are singletons and are designed to carry as much data as possible within the object
// to make data access and function calling easy.

global $db, $session, $paths, $template, $plugins; // Common objects
global $enano_config; // A global used to cache config information without making loads of queries ;-)
											// In addition, $enano_config is used to fetch config information if die_semicritical() is called.

// Jim Tucek's e-mail encryption code                      
global $email;

// Language object
global $lang;

// Timezone offset
global $timezone;
$timezone = 0;

// DST settings
global $dst_params;
$dst_params = array(0, 0, 0, 0, 60);

// Establish HTTPS
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';

// Divert to CLI loader if running from CLI
if ( defined('ENANO_CLI') || ( isset($argc) && isset($argv) ) )
{
	if ( defined('ENANO_CLI') || ( is_int($argc) && is_array($argv) && !isset($_SERVER['REQUEST_URI']) ) )
	{
		require(ENANO_ROOT . '/includes/common_cli.php');
		return;
	}
}

// Because Enano sends out complete URLs in several occasions, we need to know what hostname the user is requesting the page from.
// In future versions we may include a fallback "safety" host to use, but that's too much to worry about now
if ( !isset($_SERVER['HTTP_HOST']) )
	grinding_halt('Cannot get hostname', '<p>Your web browser did not provide the HTTP Host: field. This site requires a modern browser that supports the HTTP 1.1 standard.</p>');

//
// END BACKGROUND AND ENVIRONMENT CHECKS
//

profiler_log('Background/environment checks done');

//
// MAIN API INITIALIZATION
//

// The first thing we need to do is start the database connection. At this point, for all we know, Enano might not
// even be installed. If this connection attempt fails and it's because of a missing or corrupt config file, the
// user will be redirected (intelligently) to install.php.

$config_file = ( defined('IN_ENANO_INSTALL') && !defined('IN_ENANO_UPGRADE') ) ? '/config.new.php' : '/config.php';
@include(ENANO_ROOT . $config_file);
unset($dbuser, $dbpasswd);
if ( !isset($dbdriver) )
	$dbdriver = 'mysql';

$db = new $dbdriver();
$db->connect();

profiler_log('Database connected');

// The URL separator is the character appended to contentPath + url_title type strings.
// If the contentPath has a ? in it, this should be an ampersand; else, it should be a
// question mark.
$sep = ( strstr(contentPath, '?') ) ? '&' : '?';
define('urlSeparator', $sep);
unset($sep); // save 10 bytes of memory...

// Build the list of system tables (this is mostly done in constants.php, but that's before table_prefix is known)
if ( defined('table_prefix') && !defined('ENANO_TABLELIST_PREFIXED') )
{
	define('ENANO_TABLELIST_PREFIXED', 1);
	foreach ( $system_table_list as $i => $_ )
	{
		$system_table_list[$i] = table_prefix . $system_table_list[$i];
	}
}

// Select and fetch the site configuration
$e = $db->sql_query('SELECT config_name, config_value FROM '.table_prefix.'config;');
if ( !$e )
{
	$db->_die('Some critical configuration information could not be selected.');
}
// Used in die_semicritical to figure out whether to call getConfig() or not
define('ENANO_CONFIG_FETCHED', '');

// Initialize and fetch the site configuration array, which is used to cache the config
$enano_config = Array();
while($r = $db->fetchrow())
{
	$enano_config[$r['config_name']] = $r['config_value'];
}

$db->free_result();

profiler_log('Config fetched');

do_xff_check();

if ( defined('ENANO_EXIT_AFTER_CONFIG') )
{
	return true;
}

// Now that we have the config, check the database version.
// ...We do have a database version, right? This was only added in 1.1.8, so assign
// a database revision number if there isn't one in the config already.
if ( getConfig('db_version') === false )
{
	generate_db_version();
}
if ( ($current_db_revision = getConfig('db_version', 0)) < $db_version && !defined('IN_ENANO_UPGRADE') )
{
	grinding_halt('Database out of date', '<p>Your Enano database is out of date and needs to be upgraded. To do this, use the <a href="'.scriptPath.'/install/index.php">upgrade script</a>.</p>'
		. "<p>Your database version: $current_db_revision<br />Latest version: $db_version</p>");
}
else if ( $current_db_revision > $db_version )
{
	grinding_halt('Database newer than Enano', '<p>Your Enano database is a newer revision than what this Enano release calls for. Please upgrade your Enano files.</p>'
		. "<p>Your database version: $current_db_revision<br />Latest version: $db_version</p>");
}

// If we made it here, DB is up to date.
if ( getConfig('enano_version') !== $version && !preg_match('/^upg-/', getConfig('enano_version')) && !defined('IN_ENANO_UPGRADE') )
{
	setConfig('enano_version', $version);
	setConfig('newly_upgraded', 1);
	$q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,author_uid,page_text,edit_summary) VALUES'
			. '(\'security\', \'upgrade_enano\', ' . time() . ', \'[DEPRECATED]\', \'\', 0, \'' . $db->escape($version) . '\', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\');');
}

// Set our CDN path
if ( !defined('cdnPath') )
{
	$cdnpath = getConfig('cdn_path', scriptPath);
	if ( empty($cdnpath) )
		$cdnpath = scriptPath;
	define('cdnPath', $cdnpath);
}

//
// Low level maintenance
//

// If the AES key size has been changed, bail out and fast
if ( !getConfig('aes_key_size') )
{
	setConfig('aes_key_size', AES_BITS);
}
else if ( $ks = getConfig('aes_key_size') )
{
	if ( intval($ks) != AES_BITS )
	{
		grinding_halt('AES key size changed', '<p>Enano has detected that the AES key size in constants.php has been changed. This change cannot be performed after installation, otherwise the private key would have to be re-generated and all passwords would have to be re-encrypted.</p><p>Please change the key size back to ' . $ks . ' bits and reload this page.</p>');
	}
}

// Same for AES block size
if ( !getConfig('aes_block_size') )
{
	setConfig('aes_block_size', AES_BLOCKSIZE);
}
else if ( $ks = getConfig('aes_block_size') )
{
	if ( intval($ks) != AES_BLOCKSIZE )
	{
		grinding_halt('AES block size changed', '<p>Enano has detected that the AES block size in constants.php has been changed. This change cannot be performed after installation, otherwise all passwords would have to be re-encrypted.</p><p>Please change the block size back to ' . $ks . ' bits and reload this page.</p>');
	}
}

// Is there no default language?
if ( getConfig('default_language') === false && !defined('IN_ENANO_MIGRATION') )
{
	$q = $db->sql_query('SELECT lang_id FROM '.table_prefix.'language LIMIT 1;');
	if ( !$q )
		$db->_die('common.php - setting default language');
	if ( $db->numrows() < 1 && !defined('ENANO_ALLOW_LOAD_NOLANG') )
	{
		grinding_halt('No languages', '<p>There are no languages installed on this site.</p>
				<p>If you are the website administrator, you may install a language by writing and executing a simple PHP script to install it:</p>
				<pre>
&lt;?php
define("ENANO_ALLOW_LOAD_NOLANG", 1);
require("includes/common.php");
install_language("eng", "English", "English", ENANO_ROOT . "/language/english/core.json");</pre>');
	}
	$row = $db->fetchrow();
	setConfig('default_language', $row['lang_id']);
}

profiler_log('Ran checks');

// Init cache
$cache = CacheManager::factory();

// Load plugin manager
$plugins = new pluginLoader();

//
// Mainstream API boot-up
//

// Obtain list of plugins
$plugins->loadAll();

profiler_log('Fetched plugin list');

global $plugins;

// Load plugins from common because we can't give plugins full abilities in object context
foreach ( $plugins->load_list as $f )
{
	if ( file_exists(ENANO_ROOT . '/plugins/' . $f) )
		include_once ENANO_ROOT . '/plugins/' . $f;
}

profiler_log('Loaded plugins');

// Three fifths of the Enano API gets the breath of life right here.
$session = new sessionManager();
$paths = new pathManager();
$template = new template();
$email = new EmailEncryptor();

profiler_log('Instanciated important singletons');

// We've got the five main objects - flick on the switch so if a problem occurs, we can have a "friendly" UI
define('ENANO_BASE_CLASSES_INITIALIZED', '');

// From here on out, none of this functionality is needed during the installer stage.
// Once $paths->init() is called, we could be redirected to the main page, so we don't want
// that if the installer's running. Don't just go and define IN_ENANO_INSTALL from your
// script though, because that will make the DBAL look in the wrong place for the config file.
if ( !defined('IN_ENANO_INSTALL') )
{
	// And here you have it, the de facto way to place a hook. Plugins can place hooks and hook
	// into other plugins. You just never know.
	$code = $plugins->setHook('base_classes_initted');
	foreach ( $code as $cmd )
	{
		eval($cmd);
	}
	
	profiler_log('Finished base_classes_initted hook');
	
	// One quick security check...
	if ( !is_valid_ip($_SERVER['REMOTE_ADDR']) )
	{
		die('SECURITY: spoofed IP address: ' . htmlspecialchars($_SERVER['REMOTE_ADDR']));
	}
	
	// For special and administration pages, sometimes there is a "preloader" function that must be run
	// before the session manager and/or path manager get the init signal. Call it here.
	$urlname = get_title(true);
	list($page_id, $namespace) = RenderMan::strToPageID($urlname);
	list($page_id_top) = explode('/', $page_id);
	$fname = "page_{$namespace}_{$page_id_top}_preloader";
	if( ( $namespace == 'Admin' || $namespace == 'Special' ) && function_exists($fname))
	{
		call_user_func($fname);
	}
	
	profiler_log('Checked for (and ran, if applicable) preloader');
	
	// Add all of our built in special pages
	foreach ( array('SpecialUserFuncs', 'SpecialPageFuncs', 'SpecialAdmin', 'SpecialCSS', 'SpecialUpDownload', 'SpecialSearch', 'PrivateMessages', 'SpecialGroups', 'SpecialLog') as $plugin )
	{
		$funcname = "{$plugin}_paths_init";
		if ( function_exists($funcname) )
		{
			$funcname();
		}
	}
	profiler_log('Added special pages');
	
	// All checks passed! Start the main components up.  
	$session->start();
	
	// This is where plugins will want to add pages from 1.1.x on out. You can still add
	// pages at base_classes_initted but the titles won't be localized. This is because
	// the session manager has to be started before localization will work in the user's
	// preferred language.
	$code = $plugins->setHook('session_started');
	foreach ( $code as $cmd )
	{
		eval($cmd);
	}
	
	profiler_log('Ran session_started hook');
	
	$paths->init($urlname);
	
	// setup output format
	if ( defined('ENANO_OUTPUT_FORMAT') )
		$class = 'Output_' . ENANO_OUTPUT_FORMAT;
	else
		$class = ( isset($_GET['noheaders']) ) ? 'Output_Naked' : 'Output_HTML';
		
	$output = new $class();
	
	// Are we running from the API? If so, did the page set a title?
	if ( !defined('ENANO_INTERFACE_INDEX') && !defined('ENANO_INTERFACE_AJAX') && isset($title) )
	{
		$output->set_title($title);
	}
	
	// We're ready for whatever life throws us now, at least from an API point of view.
	define('ENANO_MAINSTREAM', '');
	
	// If the site is disabled, bail out, unless we're trying to log in or administer the site
	if(getConfig('site_disabled') == '1' && $session->user_level < USER_LEVEL_ADMIN)
	{
		// is this one of the more critical special pages?
		if ( $paths->namespace == 'Admin' || ( $paths->namespace == 'Special' && ( $paths->page_id == 'CSS' || $paths->page_id == 'Administration' || $paths->page_id == 'Login' ) ) )
		{
			// yeah, we need to keep this page available. do nothing; allow execution to continue
		}
		else
		{
			if(!$n = getConfig('site_disabled_notice')) 
			{
				$n = 'The administrator has disabled the site. Please check back later.';
			}
			
			$text = RenderMan::render($n) . '
			<div class="info-box">
				If you have an administrative account, you may <a href="'.makeUrlNS('Special', 'Login').'">log in</a> to the site.
			</div>';
			$paths->wiki_mode = 0;
			die_semicritical('Site disabled', $text);
		}
	}
	else if ( getConfig('site_disabled') == '1' && $session->user_level >= USER_LEVEL_ADMIN )
	{
		// If the site is disabled but the user has admin rights, allow browsing
		// and stuff, but display the orange box notifying the admin.
		$template->site_disabled = true;
	}
	
	// At this point all of Enano is fully initialized and running and you're ready to do whatever you want.
	$code = $plugins->setHook('common_post');
	foreach ( $code as $cmd )
	{
		eval($cmd);
	}
	
	profiler_log('Ran disabled-site checks and common_post');
	
	load_rank_data();
	
	profiler_log('Loaded user rank data');
	
	if ( isset($_GET['noheaders']) )
		$template->no_headers = true;
}

profiler_log('common finished');

// That's the end. Enano should be loaded now :-)

?>
