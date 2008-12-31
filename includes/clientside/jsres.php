<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 * jsres.php - the Enano client-side runtime, a.k.a. AJAX on steroids
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// define('ENANO_JS_DEBUG', 1);

// if Enano's already loaded, we've been included from a helper script
if ( defined('ENANO_CONFIG_FETCHED') )
  define('ENANO_JSRES_SETUP_ONLY', 1);

if ( !defined('ENANO_JSRES_SETUP_ONLY') ):

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

$local_start = microtime_float();

// Disable for IE, it causes problems.
if ( ( strstr(@$_SERVER['HTTP_USER_AGENT'], 'MSIE') || defined('ENANO_JS_DEBUG') ) && !isset($_GET['early']) )
{
  header('HTTP/1.1 302 Redirect');
  header('Location: static/enano-lib-basic.js');
  exit();
}

// Setup Enano

//
// Determine the location of Enano as an absolute path.
//

// We need to see if this is a specially marked Enano development server. You can create an Enano
// development server by cloning the Mercurial repository into a directory named repo, and then
// using symlinks to reference the original files so as to segregate unique files from non-unique
// and distribution-standard ones. Enano will pivot its root directory accordingly if the file
// .enanodev is found in the Enano root (not /repo/).
if ( strpos(__FILE__, '/repo/') && ( file_exists('../../.enanodev') || file_exists('../../../.enanodev') ) )
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
  define('ENANO_ROOT', dirname(dirname(dirname($filename))));

chdir(ENANO_ROOT);

// fetch only the site config
define('ENANO_EXIT_AFTER_CONFIG', 1);
require('includes/common.php');

endif; // ENANO_JSRES_SETUP_ONLY

// CONFIG

// Files safe to run full (aggressive) compression on
$full_compress_safe = array(
  // Sorted by file size, descending (du -b *.js | sort -n)
  'crypto.js',
  'ajax.js',
  'editor.js',
  'functions.js',
  'login.js',
  'acl.js',
  'misc.js',
  'comments.js',
  'autofill.js',
  'dropdown.js',
  'paginate.js',
  'enano-lib-basic.js',
  'pwstrength.js',
  'flyin.js',
  'rank-manager.js',
  'userpage.js',
  'template-compiler.js',
  'toolbar.js',
);

// Files that should NOT be compressed due to already being compressed, licensing, or invalid produced code
$compress_unsafe = array('json.js', 'fat.js', 'admin-menu.js', 'autofill.js', 'jquery.js', 'jquery-ui.js');

require_once('includes/js-compressor.php');

// try to gzip the output
if ( !defined('ENANO_JSRES_SETUP_ONLY') ):
$do_gzip = false;
if ( isset($_SERVER['HTTP_ACCEPT_ENCODING']) )
{
  $acceptenc = str_replace(' ', '', strtolower($_SERVER['HTTP_ACCEPT_ENCODING']));
  $acceptenc = explode(',', $acceptenc);
  if ( in_array('gzip', $acceptenc) )
  {
    $do_gzip = true;
    ob_start();
  }
}

// Output format will always be JS
header('Content-type: text/javascript');

endif; // ENANO_JSRES_SETUP_ONLY

$everything = "/* The code represented in this file is compressed for optimization purposes. The full source code is available in includes/clientside/static/. */\n\nvar ENANO_JSRES_COMPRESSED = true;\n\n";

// if we only want the tiny version of the API (just enough to get by until the full one is loaded), send that
// with a simple ETag and far future expires header

// note - obfuscated for optimization purposes. The exact same code except properly indented is in enano-lib-basic.
if ( isset($_GET['early']) )
{
  header('ETag: enanocms-lib-early-r2');
  header('Expires: Wed, 1 Jan 2020 00:00:00 GMT');
  
  echo <<<JSEOF
var onload_hooks = new Array();function addOnloadHook(func){if ( typeof ( func ) == 'function' ){if ( typeof(onload_hooks.push) == 'function' ){onload_hooks.push(func);}else{onload_hooks[onload_hooks.length] = func;};};}
JSEOF;
  
  exit();
}

// Load and parse enano_lib_basic
$file = @file_get_contents('includes/clientside/static/enano-lib-basic.js');

$pos_start_includes = strpos($file, '/*!START_INCLUDER*/');
$pos_end_includes = strpos($file, '/*!END_INCLUDER*/');

if ( !$pos_start_includes || !$pos_end_includes )
{
  die('// Error: enano-lib-basic does not have required metacomments');
}

$pos_end_includes += strlen('/*!END_INCLUDER*/');

preg_match('/var thefiles = (\[([^\]]+?)\]);/', $file, $match);

if ( empty($match) )
  die('// Error: could not retrieve file list from enano-lib-basic');

// Decode file list
try
{
  $file_list = enano_json_decode($match[1]);
}
catch ( Exception $e )
{
  die("// Exception caught during file list parsing");
}

$apex = filemtime('includes/clientside/static/enano-lib-basic.js');

$before_includes = substr($file, 0, $pos_start_includes);
$after_includes = substr($file, $pos_end_includes);

if ( isset($_GET['f']) )
{
  // requested a single file
  $js_file =& $_GET['f'];
  if ( strstr($js_file, ',') )
  {
    $filelist = explode(',', $js_file);
    unset($js_file);
    $everything = '';
    foreach ( $filelist as $js_file )
    {
      if ( !preg_match('/^[a-z0-9_-]+\.js$/i', $js_file) )
      {
        header('HTTP/1.1 404 Not Found');
        exit('Not found');
      }
      
      $apex = filemtime("includes/clientside/static/$js_file");
      
      $file_contents = file_get_contents("includes/clientside/static/$js_file");
      $everything .= jsres_cache_check($js_file, $file_contents) . ' loaded_components[\'' . $js_file . '\'] = true; if ( onload_complete ) { runOnloadHooks(); onload_hooks = []; };';
    }
  }
  else
  {
    if ( !preg_match('/^[a-z0-9_-]+\.js$/i', $js_file) )
    {
      header('HTTP/1.1 404 Not Found');
      exit('Not found');
    }
    
    $apex = filemtime("includes/clientside/static/$js_file");
    
    $file_contents = file_get_contents("includes/clientside/static/$js_file");
    $everything = jsres_cache_check($js_file, $file_contents) . ' loaded_components[\'' . $js_file . '\'] = true; if ( onload_complete ) { runOnloadHooks(); onload_hooks = []; };';
  }
}
else
{
  // compress enano-lib-basic
  $libbasic = "$before_includes\n$after_includes";
  $libbasic = jsres_cache_check('enano-lib-basic.js', $libbasic);
  $everything .= $libbasic;
  
  // $everything .= $before_includes;
  // $everything .= $after_includes;
  
  foreach ( $file_list as $js_file )
  {
    $file_contents = file_get_contents("includes/clientside/static/$js_file");
    $time = filemtime("includes/clientside/static/$js_file");
    if ( $time > $apex )
      $apex = $time;
    
    $file_contents = jsres_cache_check($js_file, $file_contents);
    
    $everything .= "\n\n// $js_file\n";
    $everything .= "\n" . $file_contents;
  }
}

// generate ETag
$etag = base64_encode(hexdecode(sha1($everything)));

if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
{
  if ( "\"$etag\"" == $_SERVER['HTTP_IF_NONE_MATCH'] )
  {
    header('HTTP/1.1 304 Not Modified');
    exit();
  }
}

// generate expires header
$expires = date('r', mktime(0, 0, 0, intval(date('m')), intval(date('d')), intval(date('y'))+1));

$everything = str_replace('/* JavaScriptCompressor 0.8 [www.devpro.it], thanks to Dean Edwards for idea [dean.edwards.name] */' . "\r\n", '', $everything);

$date = date('r', $apex);

if ( defined('ENANO_JSRES_SETUP_ONLY') )
{
  return; // we're done setting up, break out
}

header("Date: $date");
header("Last-Modified: $date");
header("ETag: \"$etag\"");
header("Expires: $expires");
header("Content-Length: " . strlen($everything));

$local_end = microtime_float();
$local_gentime = $local_end - $local_start;
$local_gentime = round($local_gentime, 5);
header("X-Performance: generated in $local_gentime seconds");

echo $everything;

if ( $do_gzip )
{
  gzip_output();
}

/**
 * Check the cache for the given JS file and return the best-compressed version.
 * @param string Javascript file (acl.js)
 * @param string Default/current contents
 * @return string
 */

function jsres_cache_check($js_file, $file_contents)
{
  global $full_compress_safe, $compress_unsafe;
  
  $file_md5 = md5($file_contents);
  
  // Is this file cached?
  $cache_path = ENANO_ROOT . "/cache/jsres_$js_file.json";
  $loaded_cache = false;
  
  if ( file_exists($cache_path) )
  {
    // Load the cache file and parse it.
    $cache_file = file_get_contents($cache_path);
    try
    {
      $cache_file = enano_json_decode($cache_file);
    }
    catch ( Exception $e )
    {
      // Don't do anything - let our fallbacks come into place
    }
    if ( is_array($cache_file) && isset($cache_file['md5']) && isset($cache_file['src']) )
    {
      if ( $cache_file['md5'] === $file_md5 )
      {
        @header("X-Cache-Status: cache HIT, hash $file_md5");
        $loaded_cache = true;
        $file_contents = $cache_file['src'];
      }
    }
  }
  if ( !$loaded_cache && getConfig('cache_thumbs') == '1' )
  {
    // Try to open the cache file and write to it. If we can't do that, just don't compress the code.
    $handle = @fopen($cache_path, 'w');
    if ( $handle )
    {
      $aggressive = in_array($js_file, $full_compress_safe);
      if ( !in_array($js_file, $compress_unsafe) )
        $file_contents = perform_js_compress($file_contents, $aggressive);
      
      $payload = enano_json_encode(array(
          'md5' => $file_md5,
          'src' => $file_contents
        ));
      fwrite($handle, $payload);
      fclose($handle);
      @header("X-Cache-Status: cache MISS, new generated");
    }
    else
    {
      @header("X-Cache-Status: cache MISS, not generated");
    }
  }
  else if ( !$loaded_cache )
  {
    @header("X-Cache-Status: cache MISS, not generated");
  }
  
  return $file_contents;
}

