<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * jsres.php - the Enano client-side runtime, a.k.a. AJAX on steroids
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

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

require('includes/common.php');
if ( !defined('ENANO_CLI') )
{
  die_friendly('Not for web use', '<p>This script is designed to be run from a command-line environment.</p>');
}

// if ( !getConfig('cdn_path') )
// {
//   die_semicritical('CDN support not enabled', 'This script is for compressing the Enano Javascript runtimes for CDN use.');
// }

echo "\x1B[1mCreating zip file with compressed Javascript runtimes.\x1B[0m\n";
echo "\x1B[0;32mWhen finished, upload the contents of enano-lib.zip to:\n\x1B[1;34m  " . cdnPath . "/includes/clientside/static/\x1B[0m\n";

echo "\x1B[0;33mChecking for zip support...";

// do we have zip file support?
$have_zip = false;
$path = ( isset($_SERVER['PATH']) ) ? $_SERVER['PATH'] : false;
if ( !$path )
{
  die_semicritical('Can\'t get your PATH', 'Unable to get the PATH environment variable');
}

$path = ( strtolower(PHP_OS) === 'win32' ) ? explode(';', $path) : explode(':', $path);
$pathext = ( strtolower(PHP_OS) === 'win32' ) ? '.exe' : '';

foreach ( $path as $dir )
{
  if ( file_exists("$dir/zip$pathext") )
  {
    $have_zip = true;
    break;
  }
}

if ( !$have_zip )
{
  // no zupport zor zipping ziles
  echo "\x1B[31;1mnot found\x1B[0m\n\x1B[1mPlease install the zip utility using your distribution's package manager\nand then rerun this script.\x1B[0m";
  exit(1);
}

echo "\x1B[1mall good\x1B[0m\n";
echo "\x1B[0;33mMinifying Javascript files...";

if ( !@mkdir('includes/clientside/staticmin') )
{
  echo "\x1B[31;1mcouldn't create temp directory\x1B[0m\n\x1B[1mCheck permissions please, we couldn't create includes/clientside/staticmin.\x1B[0m";
  exit(1);
}

require('includes/clientside/jsres.php');

// $everything now contains the core runtimes
// hack to lie about compression, this keeps load_component() from doing jsres.php?f=...
$everything = str_replace('ENANO_JSRES_COMPRESSED = true', 'ENANO_JSRES_COMPRESSED = false', $everything);

chdir('includes/clientside/staticmin');
$handle = @fopen('./enano-lib-basic.js', 'w');
if ( !$handle )
{
  echo "\x1B[31;1mcouldn't open file\x1B[0m\n\x1B[1mCheck permissions please, we couldn't create a file inside includes/clientside/staticmin.\x1B[0m";
  exit(1);
}

fwrite($handle, $everything);
fclose($handle);

// for each JS file in includes/clientside/static, compress & write
if ( $dr = @opendir('../static') )
{
  while ( $dh = @readdir($dr) )
  {
    if ( !preg_match('/\.js$/', $dh) || $dh === 'enano-lib-basic.js' )
      continue;
    
    $contents = @file_get_contents("../static/$dh");
    $compressed = jsres_cache_check($dh, $contents);
    $compressed = str_replace('/* JavaScriptCompressor 0.8 [www.devpro.it], thanks to Dean Edwards for idea [dean.edwards.name] */' . "\r\n", '', $compressed);
    
    $handle = @fopen("./$dh", 'w');
    if ( !$handle )
    {
      echo "\x1B[31;1mcouldn't open file\x1B[0m\n\x1B[1mCheck permissions please, we couldn't create a file inside includes/clientside/staticmin.\x1B[0m";
      exit(1);
    }
    fwrite($handle, $compressed);
    fclose($handle);
  }
}
else
{
  echo "\x1B[31;1mcouldn't open includes directory\x1B[0m\n\x1B[1mUnable to get our hands into includes/clientside/static/ to compress everything.\x1B[0m";
  exit(1);
}

echo "\x1B[1mdone\x1B[0m\n";
echo "\x1B[0;33mCompressing into enano-lib.zip...";

$result = system('zip -yrq9 ../enano-lib.zip *.js');
if ( $result != 0 )
{
  // failure
  echo "\x1B[31;1mzip creation failed\x1B[0m\n\x1B[1mzip returned result $result\x1B[0m";
  exit(1);
}

echo "\x1B[1mdone\x1B[0m\n";

// done, clean up

echo "\x1B[0;33mCleaning up...";
chdir('..');

if ( $dr = @opendir('./staticmin') )
{
  while ( $dh = @readdir($dr) )
  {
    if ( preg_match('/\.js$/', $dh) )
      unlink("./staticmin/$dh");
  }
}

@rmdir('./staticmin');

echo "\x1B[1mdone\x1B[0m\n";
