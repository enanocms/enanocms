<?php
/**!info**
{
  "Plugin Name"  : "plugin_speciallog_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_speciallog_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.6",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

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

function SpecialLog_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_log',
    'urlname'=>'Log',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

function page_Special_Log()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $output;
  
  require_once(ENANO_ROOT . '/includes/log.php');
  $log = new LogDisplay();
  $page = 1;
  $pagesize = 50;
  
  if ( $params = explode('/', $paths->getAllParams()) )
  {
    foreach ( $params as $param )
    {
      if ( preg_match('/^(user|page|within|resultpage|size)=(.+?)$/', $param, $match) )
      {
        $name =& $match[1];
        $value =& $match[2];
        switch($name)
        {
          case 'resultpage':
            $page = intval($value);
            break;
          case 'size':
            $pagesize = intval($value);
            break;
          default:
            $log->add_criterion($name, $value);
            break;
        }
      }
    }
  }

  $page--;
  $rowcount = $log->get_row_count();  
  $result_url = makeUrlNS('Special', 'Log/' . rtrim(preg_replace('|/?resultpage=(.+?)/?|', '/', $paths->getAllParams()), '/') . '/resultpage=%s', false, true);
  $paginator = generate_paginator($page, ceil($rowcount / $pagesize), $result_url);
  
  $dataset = $log->get_data($page * $pagesize, $pagesize);
  
  $output->header();
  echo $paginator;
  foreach ( $dataset as $row )
  {
    echo LogDisplay::render_row($row) . '<br />';
  }
  $output->footer();
} 
