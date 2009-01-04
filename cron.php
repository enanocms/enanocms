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

//
// cron.php - Maintenance tasks that should be run periodically
//

// The cron script is triggered by way of an image. This is a 1x1px transparent GIF.
define('ENANO_GIF_SPACER', base64_decode('R0lGODlhAQABAIAAAP///////yH+FUNyZWF0ZWQgd2l0aCBUaGUgR0lNUAAh+QQBCgABACwAAAAAAQABAAACAkwBADs='));

// Don't need a page to load, all we should need is the Enano API
require('includes/common.php');

global $db, $session, $paths, $template, $plugins; // Common objects

// Hope now that plugins are loaded :-)
global $cron_tasks;

foreach ( $cron_tasks as $interval => $tasks )
{
  $interval = doubleval($interval);
  $last_run = intval(getConfig("cron_lastrun_ivl_$interval"));
  $last_run_threshold = doubleval(time()) - ( 3600.0 * $interval );
  if ( $last_run_threshold >= $last_run )
  {
    foreach ( $tasks as $task )
    {
      @call_user_func($task);
    }
    setConfig("cron_lastrun_ivl_$interval", strval(time()));
  }
}

$expiry_date = date('r', get_cron_next_run());

$etag = sha1($expiry_date);

if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
{
  if ( "\"$etag\"" == $_SERVER['HTTP_IF_NONE_MATCH'] )
  {
    header('HTTP/1.1 304 Not Modified');
    exit();
  }
}

header("ETag: $etag");

header('Expires: ' . $expiry_date);
header('Content-type: image/gif');

echo ENANO_GIF_SPACER;

?>
