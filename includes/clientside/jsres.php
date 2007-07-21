<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * Copyright (C) 2006-2007 Dan Fuhry
 * jsres.php - the Enano client-side runtime, a.k.a. AJAX on steroids
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if(!isset($_GET['title'])) $_GET['title'] = 'null';
require('../common.php');

define('ENABLE_COMPRESSION', '');

ob_start();
header('Content-type: text/javascript');

$file = ( isset($_GET['file']) ) ? $_GET['file'] : 'enano-lib-basic.js';

if(!preg_match('/^([a-z0-9_-]+)\.js$/i', $file))
  die('// ERROR: Hacking attempt');

$fname = './static/' . $file;
if ( !file_exists($fname) )
  die('// ERROR: File not found: ' . $file);

$everything = file_get_contents($fname);

$mtime = filemtime($fname);
header('Last-Modified: '.date('D, d M Y H:i:s T', $mtime));
header('Content-disposition: attachment; filename=' . $file);

if(defined('ENABLE_COMPRESSION'))
{
  echo "/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * [Aggressively compressed] Javascript client code
 * Copyright (C) 2006-2007 Dan Fuhry
 * Enano is Free Software, licensed under the GNU General Public License; see http://enanocms.org/ for details.
 */

";
  
  $cache_file = ENANO_ROOT . '/cache/jsres-' . $file . '.php';
  
  if ( file_exists($cache_file) )
  {
    $cached = file_get_contents ( $cache_file );
    $data = unserialize ( $cached );
    if ( $data['md5'] == md5 ( $everything ) )
    {
      echo "// The code in this file was fetched from cache\n\n";
      echo $data['code'];
      exit;
    }
  }
  
  if ( getConfig('cache_thumbs') == '1' )
  {
    $js_compressor = new JavascriptCompressor();
    $packed = $js_compressor->getPacked($everything);
    $data = Array(
      'md5' => md5 ( $everything ),
      'code' => $packed
      );
    echo "// The code in this file was fetched from the static scripts and compressed (packed code cached)\n\n";
    echo $packed;
    
    $fh = @fopen($cache_file, 'w');
    if (!$fh)
      die('// ERROR: Can\'t open cache file for writing');
    fwrite($fh, serialize ( $data ) );
    fclose($fh);
    
    exit;
  }
  
  echo "// The code in this file was not compressed because packed-script caching is disabled\n\n";
  echo $everything;
  
}
else
{
  echo "// The code in this file was not compressed because all script compression is disabled\n\n";
  echo $everything;
}
?>
