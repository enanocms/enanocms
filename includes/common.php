<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.3 (Caoineag alpha 3)
 * Copyright (C) 2006-2007 Dan Fuhry
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
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>Hacking Attempt</title><meta http-equiv="Content-type" content="text/html; charset=utf-8" /></head><style type="text/css">body{background-color:#000;color:#CCC;font-family:trebuchet ms,sans-serif;font-size:9pt;}a{color:#FFF;}</style><body><p>Hacking attempt using <a href="http://www.hardened-php.net/index.76.html">PHP $GLOBALS overwrite vulnerability</a> detected, reported to admin</p><p>You're worse than this guy! Unless you are this guy...</p><p id="billp"><img alt=" " src="about:blank" id="billi" /></p><script type="text/javascript">// <![CDATA[
  window.onload=function(){counter();setInterval('counter();', 1000);};var text=false;var cnt=10;function counter(){if(!text){text=document.createElement('span');text.id='billc';text.innerHTML=cnt;text.style.fontSize='96pt';text.style.color='#FF0000';p=document.getElementById('billp');p.appendChild(text);}else{if(cnt==1){document.getElementById('billi').src='http://upload.wikimedia.org/wikipedia/commons/7/7f/Bill_Gates_2004_cr.jpg';document.getElementById('billc').innerHTML='';return;}cnt--;document.getElementById('billc').innerHTML=cnt+' ';}}
  // ]]>
  </script><p><span style="color:black;">You been f***ed by Enano | valid XHTML 1.1</span></p></body></html>
  <?php
  exit;
}

// Our version number
// This needs to match the version number in the database. This number should
// be the expected output of enano_version(), which will always be in the
// format of 1.0.2, 1.0.2a1, 1.0.2b1, 1.0.2RC1
// You'll want to change this for custom distributions.
$version = '1.1.3';

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

// Start including files. LOTS of files. Yeah!
require_once(ENANO_ROOT.'/includes/constants.php');
require_once(ENANO_ROOT.'/includes/functions.php');
require_once(ENANO_ROOT.'/includes/dbal.php');
require_once(ENANO_ROOT.'/includes/paths.php');
require_once(ENANO_ROOT.'/includes/sessions.php');
require_once(ENANO_ROOT.'/includes/template.php');
require_once(ENANO_ROOT.'/includes/plugins.php');
require_once(ENANO_ROOT.'/includes/lang.php');
require_once(ENANO_ROOT.'/includes/comment.php');
require_once(ENANO_ROOT.'/includes/wikiformat.php');
require_once(ENANO_ROOT.'/includes/diff.php');
require_once(ENANO_ROOT.'/includes/render.php');
require_once(ENANO_ROOT.'/includes/stats.php');
require_once(ENANO_ROOT.'/includes/pageutils.php');
require_once(ENANO_ROOT.'/includes/js-compressor.php');
require_once(ENANO_ROOT.'/includes/rijndael.php');
require_once(ENANO_ROOT.'/includes/email.php');
require_once(ENANO_ROOT.'/includes/search.php');
require_once(ENANO_ROOT.'/includes/json.php');
require_once(ENANO_ROOT.'/includes/json2.php');
require_once(ENANO_ROOT.'/includes/math.php');
require_once(ENANO_ROOT.'/includes/wikiengine/Tables.php');
require_once(ENANO_ROOT.'/includes/pageprocess.php');
require_once(ENANO_ROOT.'/includes/tagcloud.php');

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

// Divert to CLI loader if running from CLI
if ( isset($argc) && isset($argv) )
{
  if ( is_int($argc) && is_array($argv) && !isset($_SERVER['REQUEST_URI']) )
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

//
// MAIN API INITIALIZATION
//

// The first thing we need to do is start the database connection. At this point, for all we know, Enano might not
// even be installed. If this connection attempt fails and it's because of a missing or corrupt config file, the
// user will be redirected (intelligently) to install.php.

$config_file = ( defined('IN_ENANO_INSTALL') ) ? '/config.new.php' : '/config.php';
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

// Sometimes there are critical failures triggered by initialization functions in the Enano API that are recurring
// and cannot be fixed except for manual intervention. This is where that code should go.
if ( isset($_GET['do']) && $_GET['do'] == 'diag' && isset($_GET['sub']) )
{
  switch($_GET['sub'])
  {
    case 'cookie_destroy':
      unset($_COOKIE['sid']);
      setcookie('sid', '', time()-3600*24, scriptPath);
      setcookie('sid', '', time()-3600*24, scriptPath.'/');
      die('Session cookie cleared. <a href="'.htmlspecialchars($_SERVER['PHP_SELF']).'">Continue</a>');
      break;
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

// Now that we have the config, check the Enano version.
if ( enano_version(false, true) != $version && !defined('IN_ENANO_UPGRADE') )
{
  grinding_halt('Version mismatch', '<p>It seems that the Enano release we\'re trying to run ('.$version.') is different from the version specified in your database ('.enano_version().'). Perhaps you need to <a href="'.scriptPath.'/install/upgrade.php">upgrade</a>?</p>');
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
$_GET["title"] = "langinstall";
require("includes/common.php");
install_language("eng", "English", "English", ENANO_ROOT . "/language/english/enano.json");</pre>');
  }
  $row = $db->fetchrow();
  setConfig('default_language', $row['lang_id']);
}

profiler_log('Ran checks');

// Load plugin manager
$plugins = new pluginLoader();

//
// Mainstream API boot-up
//

// Obtain list of plugins
$plugins->loadAll();

global $plugins;

// Load plugins from common because we can't give plugins full abilities in object context
foreach ( $plugins->load_list as $f )
{
  if ( file_exists($f) )
    include_once $f;
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
  
  // For special and administration pages, sometimes there is a "preloader" function that must be run
  // before the session manager and/or path manager get the init signal. Call it here.  
  $p = RenderMan::strToPageId($paths->get_pageid_from_url());
  if( ( $p[1] == 'Admin' || $p[1] == 'Special' ) && function_exists('page_'.$p[1].'_'.$p[0].'_preloader'))
  {
    @call_user_func('page_'.$p[1].'_'.$p[0].'_preloader');
  }
  
  profiler_log('Checked for preloader');
  
  // One quick security check...
  if ( !is_valid_ip($_SERVER['REMOTE_ADDR']) )
  {
    die('SECURITY: spoofed IP address');
  }

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
  
  $paths->init();
  
  // We're ready for whatever life throws us now.
  define('ENANO_MAINSTREAM', '');
  
  // If the site is disabled, bail out, unless we're trying to log in or administer the site
  if(getConfig('site_disabled') == '1' && $session->user_level < USER_LEVEL_ADMIN)
  {
    if ( $paths->namespace == 'Admin' || ( $paths->namespace == 'Special' && ( $paths->page_id == 'CSS' || $paths->page_id == 'Administration' || $paths->page_id == 'Login' ) ) )
    {
      // do nothing; allow execution to continue
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
  
  if ( isset($_GET['noheaders']) )
    $template->no_headers = true;
}

profiler_log('common finished');

// That's the end. Enano should be loaded now :-)

?>
