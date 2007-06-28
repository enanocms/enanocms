<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 (Banshee)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if(isset($_REQUEST['GLOBALS']))
{
  ?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"><html><head><title>Hacking Attempt</title><meta http-equiv="Content-type" content="text/html; charset=utf-8" /></head><style type="text/css">body{background-color:#000;color:#CCC;font-family:trebuchet ms,sans-serif;font-size:9pt;}a{color:#FFF;}</style><body><p>Hacking attempt using <a href="http://www.hardened-php.net/index.76.html">PHP $GLOBALS overwrite vulnerability</a> detected, reported to admin</p><p>You're worse than this guy! Unless you are this guy...</p><p id="billp"><img alt=" " src="about:blank" id="billi" /></p><script type="text/javascript">// <![CDATA[
  window.onload=function(){counter();setInterval('counter();', 1000);};var text=false;var cnt=10;function counter(){if(!text){text=document.createElement('span');text.id='billc';text.innerHTML=cnt;text.style.fontSize='96pt';text.style.color='#FF0000';p=document.getElementById('billp');p.appendChild(text);}else{if(cnt==1){document.getElementById('billi').src='http://upload.wikimedia.org/wikipedia/commons/7/7f/Bill_Gates_2004_cr.jpg';document.getElementById('billc').innerHTML='';return;}cnt--;document.getElementById('billc').innerHTML=cnt+' ';}}
  // ]]>
  </script><p><span style="color:black;">You been f***ed by Enano | valid XHTML 1.1</span></p></body></html>
  <?php
  exit;
}

$version = '1.0';

function microtime_float()
{
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

global $_starttime;
$_starttime = microtime_float();

error_reporting(E_ALL);

// Determine directory (special case for development servers)
if ( strpos(__FILE__, '/repo/') && ( file_exists('.enanodev') || file_exists('../.enanodev') ) )
{
  $filename = str_replace('/repo/', '/', __FILE__);
}
else
{
  $filename = __FILE__;
}

if(!defined('ENANO_ROOT')) // ENANO_ROOT is sometimes defined by plugins like AjIM that need the constant before the Enano API is initialized
  define('ENANO_ROOT', dirname(dirname($filename)));

if(defined('ENANO_DEBUG') && version_compare(PHP_VERSION, '5.0.0') < 0)
{
  die(__FILE__.':'.__LINE__.': The debugConsole requires PHP 5.x.x or greater. Please comment out the ENANO_DEBUG constant in your index.php.');
}

if(defined('ENANO_DEBUG'))
{
  require_once(ENANO_ROOT.'/includes/debugger/debugConsole.php');
} else {
  function dc_here($m)     { return false; }
  function dc_dump($a, $g) { return false; }
  function dc_watch($n)    { return false; }
  function dc_start_timer($u) { return false; }
  function dc_stop_timer($m) { return false; }
}

if ( file_exists( ENANO_ROOT . '/_nightly.php') )
  require(ENANO_ROOT.'/_nightly.php');

// Start including files. LOTS of files. Yeah!
require_once(ENANO_ROOT.'/includes/constants.php');
dc_here('Enano CMS '.$version.' (dev) - debug window<br />Powered by debugConsole');
dc_here('common: including files');
require_once(ENANO_ROOT.'/includes/functions.php');
require_once(ENANO_ROOT.'/includes/dbal.php');
require_once(ENANO_ROOT.'/includes/paths.php');
require_once(ENANO_ROOT.'/includes/sessions.php');
require_once(ENANO_ROOT.'/includes/template.php');
require_once(ENANO_ROOT.'/includes/plugins.php');
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
require_once(ENANO_ROOT.'/includes/wikiengine/Tables.php');
require_once(ENANO_ROOT.'/includes/pageprocess.php');

strip_magic_quotes_gpc();

// Enano has five parts: the database abstraction layer (DBAL), the session manager, the path/URL manager, the template engine, and the plugin manager.
// Each part has its own class and a global var; nearly all Enano functions are handled by one of these five components.

global $db, $session, $paths, $template, $plugins; // Common objects
global $enano_config; // A global used to cache config information without making loads of queries ;-)
                      // In addition, $enano_config is used to fetch config information if die_semicritical() is called.
                      
global $email;

if(!isset($_SERVER['HTTP_HOST'])) grinding_halt('Cannot get hostname', '<p>Your web browser did not provide the HTTP Host: field. This site requires a modern browser that supports the HTTP 1.1 standard.</p>');
                     
$db = new mysql();
dc_here('common: calling $db->connect();');
$db->connect(); // Redirects to install.php if an installation is not detected

if(strstr(contentPath, '?')) $sep = '&';
else $sep = '?';
define('urlSeparator', $sep);
unset($sep); // save 10 bytes of memory...

// See if any diagnostic actions have been requested
if ( isset($_GET['do']) && $_GET['do'] == 'diag' && isset($_GET['sub']) )
{
  switch($_GET['sub'])
  {
    case 'cookie_destroy':
      unset($_COOKIE['sid']);
      setcookie('sid', '', time()-3600*24, scriptPath);
      setcookie('sid', '', time()-3600*24, scriptPath.'/');
      die('Session cookie cleared. <a href="'.$_SERVER['PHP_SELF'].'">Continue</a>');
      break;
  }
}

// Select and fetch the site configuration
dc_here('common: selecting global config data');
$e = $db->sql_query('SELECT config_name, config_value FROM '.table_prefix.'config;');
if(!$e) $db->_die('Some critical configuration information could not be selected.');
else define('ENANO_CONFIG_FETCHED', ''); // Used in die_semicritical to figure out whether to call getConfig() or not

dc_here('common: fetching $enano_config');
$enano_config = Array();
while($r = $db->fetchrow())
{
  $enano_config[$r['config_name']] = $r['config_value'];
}

$db->free_result();

if(enano_version(false, true) != $version)
{
  grinding_halt('Version mismatch', '<p>It seems that the Enano release we\'re trying to run ('.$version.') is different from the version specified in your database ('.enano_version().'). Perhaps you need to <a href="'.scriptPath.'/upgrade.php">upgrade</a>?</p>');
}

// Our list of tables included in Enano
$system_table_list = Array(
    table_prefix.'categories',
    table_prefix.'comments',
    table_prefix.'config',
    table_prefix.'logs',
    table_prefix.'page_text',
    table_prefix.'session_keys',
    table_prefix.'pages',
    table_prefix.'users',
    table_prefix.'users_extra',
    table_prefix.'themes',
    table_prefix.'buddies',
    table_prefix.'banlist',
    table_prefix.'files',
    table_prefix.'privmsgs',
    table_prefix.'sidebar',
    table_prefix.'hits',
    table_prefix.'search_index',
    table_prefix.'groups',
    table_prefix.'group_members',
    table_prefix.'acl',
    table_prefix.'search_cache'
  );

dc_here('common: initializing base classes');
$plugins = new pluginLoader();

// So where does the majority of Enano get executed? How about the next nine lines of code :)
dc_here('common: ok, we\'re set up, starting mainstream execution');

$plugins->loadAll();
dc_here('common: loading plugins');
  global $plugins;
  foreach($plugins->load_list as $f) { include_once $f; } // Can't be in object context when this is done

$session = new sessionManager();
$paths = new pathManager();
$template = new template();
$email = new EmailEncryptor();

define('ENANO_BASE_CLASSES_INITIALIZED', '');

$code = $plugins->setHook('base_classes_initted');
foreach ( $code as $cmd )
{
  eval($cmd);
}
  
$p = RenderMan::strToPageId($paths->get_pageid_from_url());
if( ( $p[1] == 'Admin' || $p[1] == 'Special' ) && function_exists('page_'.$p[1].'_'.$p[0].'_preloader'))
{
  @call_user_func('page_'.$p[1].'_'.$p[0].'_preloader');
}

$session->start();
$paths->init();

define('ENANO_MAINSTREAM', '');

// If the site is disabled, bail out, unless we're trying to log in or administer the site
if(getConfig('site_disabled') == '1' && $session->user_level < USER_LEVEL_ADMIN)
{
  if ( $paths->namespace == 'Admin' || ( $paths->namespace == 'Special' && ( $paths->cpage['urlname_nons'] == 'CSS' || $paths->cpage['urlname_nons'] == 'Administration' || $paths->cpage['urlname_nons'] == 'Login' ) ) )
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
      If you have an administrative account, you may <a href="'.makeUrlNS('Special', 'Login').'">log in</a> to the site or <a href="'.makeUrlNS('Special', 'Administration').'">use the administration panel</a>.
    </div>';
    $paths->wiki_mode = 0;
    die_semicritical('Site disabled', $text);
  }
}
else if(getConfig('site_disabled') == '1' && $session->user_level >= USER_LEVEL_ADMIN)
{
  $template->site_disabled = true;
}

$code = $plugins->setHook('session_started');
foreach ( $code as $cmd )
{
  eval($cmd);
}

if(isset($_GET['noheaders'])) $template->no_headers = true;

?>
