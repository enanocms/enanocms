<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
/**
 * The main loader script that initializes everything about Enano in the proper order. This is only loaded
 * if PHP is running via CLI.
 * @package Enano
 * @subpackage Core
 * @copyright See header block
 */

//
// MAIN API INITIALIZATION
//

// Note to important functions and the template class that we're running via CLI
define('ENANO_CLI', 1);

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
  grinding_halt('Version mismatch', 'Trying to run Enano version '.$version.' on database version '.enano_version().', you might need to upgrade.');
}

// Set our CDN path
if ( !defined('cdnPath') )
  define('cdnPath', getConfig('cdn_path', scriptPath));

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
    grinding_halt('AES key size changed', 'Enano has detected that the AES key size in constants.php has been changed. This change cannot be performed after installation, otherwise the private key would have to be re-generated and all passwords would have to be re-encrypted.' . "\n\n" . 'Please change the key size back to ' . $ks . ' bits and rerun this script.');
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
    grinding_halt('AES block size changed', "Enano has detected that the AES block size in constants.php has been changed. This change cannot be performed after installation, otherwise all passwords would have to be re-encrypted.\n\nPlease change the block size back to $ks bits and rerun this script.");
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
    grinding_halt('No languages', 'No languages are installed on the site, load from web interface for instructions on how to fix this.');
  }
  $row = $db->fetchrow();
  setConfig('default_language', $row['lang_id']);
}

profiler_log('Ran checks');

// Init cache
$cache = new CacheManager();

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
if ( !defined('ENANO_NO_PLUGINS') )
{
  foreach ( $plugins->load_list as $f )
  {
    if ( file_exists(ENANO_ROOT . '/plugins/' . $f) )
      include_once ENANO_ROOT . '/plugins/' . $f;
  }
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
  if ( isset($_SERVER['REMOTE_ADDR']) )
  {
    grinding_halt('REMOTE_ADDR detected', 'Detected a REMOTE_ADDR, this should not happen in CLI mode.');
  }
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

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
  
  load_rank_data();
  
  profiler_log('Loaded user rank data');
  
  if ( isset($_GET['noheaders']) )
    $template->no_headers = true;
}

profiler_log('common finished');

// That's the end. Enano should be loaded now :-)

?>
