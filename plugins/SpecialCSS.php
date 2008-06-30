<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialcss_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialcss_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.4",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('session_started', 'SpecialCSS_paths_init();');

function SpecialCSS_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_css',
    'urlname'=>'CSS',
    'namespace'=>'Special',
    'special'=>0,'visible'=>0,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

function page_Special_CSS()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  header('Content-type: text/css');
  if ( isset($_GET['printable']) || $paths->getParam(0) == 'printable' )
  {
    echo $template->get_css('_printable.css');
  }
  else
  {
    echo $template->get_css();
  }
}

?>