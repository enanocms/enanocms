<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.2 (Caoineag alpha 2)
 * Copyright (C) 2006-2007 Dan Fuhry
 * jsres.php - the Enano client-side runtime, a.k.a. AJAX on steroids
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// Disable for IE, it causes problems.
if ( strstr(@$_SERVER['HTTP_USER_AGENT'], 'MSIE') )
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

// CONFIG

// Files safe to run full (aggressive) compression on
$full_compress_safe = array(
  // Sorted by file size, descending (du -b *.js | sort -n)
  'libbigint.js',
  'ajax.js',
  'editor.js',
  'acl.js',
  'misc.js',
  'comments.js',
  'rijndael.js',
  'autofill.js',
  'dropdown.js',
  'paginate.js',
  'autocomplete.js',
  'md5.js',
  'sha256.js',
  'flyin.js',
  'template-compiler.js',
  'toolbar.js',
  'diffiehellman.js',
  'enanomath.js'
);

// Files that should NOT be compressed due to already being compressed, licensing, or invalid produced code
$compress_unsafe = array('SpryEffects.js', 'json.js', 'fat.js', 'admin-menu.js');

require('includes/functions.php');
require('includes/json2.php');
require('includes/js-compressor.php');

// Output format will always be JS
header('Content-type: text/javascript');
$everything = '';

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

$everything .= $before_includes;
$everything .= $after_includes;

foreach ( $file_list as $js_file )
{
  $file_contents = file_get_contents("includes/clientside/static/$js_file");
  $file_md5 = md5($file_contents);
  $time = filemtime("includes/clientside/static/$js_file");
  if ( $time > $apex )
    $apex = $time;
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
        $loaded_cache = true;
        $file_contents = $cache_file['src'];
      }
    }
  }
  if ( !$loaded_cache )
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
    }
  }
  
  $everything .= "\n // $js_file\n";
  $everything .= "\n" . $file_contents;
}

$date = date('r', $apex);
header("Date: $date");
header("Last-Modified: $date");

echo $everything;

