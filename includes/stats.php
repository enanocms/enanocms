<?php
/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * Copyright (C) 2006-2007 Dan Fuhry
 * stats.php - handles statistics for pages (disablable in the admin CP)
 *
 *   ***** UNFINISHED ***** 
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function doStats( $page_id = false, $namespace = false )
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(getConfig('log_hits') == '1')
  {
    if(!$page_id || !$namespace)
    {
      $page_id = $paths->cpage['urlname_nons'];
      $namespace = $paths->namespace;
    }
    if($namespace == 'Special' || $namespace == 'Admin') 
    {
      return false;
    }
    static $stats_done = false;
    static $stats_data = Array();
    if(!$stats_done)
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'hits (username,time,page_id,namespace) VALUES(\''.$db->escape($session->username).'\', '.time().', \''.$db->escape($page_id).'\', \''.$db->escape($namespace).'\')');
      if(!$q)
      {
        echo $db->get_error();
        return false;
      }
      $db->free_result();
      $stats_done = true;
      return true;
    }
  }
}

/**
 * Fetch a list of the most-viewed pages
 * @param int the number of pages to return, send -1 to get all pages (suicide for large sites)
 * @return array key names are a string set to the page ID/namespace, and values are an int with the number of hits
 */

function stats_top_pages($num = 5)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!is_int($num)) 
  {
    return false;
  }
  
  $data = array();
  $q = $db->sql_query('SELECT COUNT(hit_id) AS num_hits, page_id, namespace FROM '.table_prefix.'hits GROUP BY page_id, namespace ORDER BY num_hits DESC LIMIT ' . $num . ';');
  
  while ( $row = $db->fetchrow() )
  {
    if ( isset($paths->pages[ $paths->nslist[ $row['namespace'] ] . $row['page_id'] ]) )
    {
      $page_data =& $paths->pages[ $paths->nslist[ $row['namespace'] ] . $row['page_id'] ];
      $title = $page_data['name'];
      $page_id = $page_data['urlname'];
    }
    else if ( !isset($paths->nslist[$row['namespace']]) )
    {
      $title = $row['namespace'] . ':' . $row['page_id'];
      $page_id = sanitize_page_id($title);
    }
    else
    {
      $title = dirtify_page_id( $paths->nslist[$row['namespace']] . $row['page_id'] );
      $title = str_replace('_', ' ', $title);
      $page_id = sanitize_page_id($title);
    }
    $data[] = array(
        'page_urlname' => $page_id,
        'page_title' => $title,
        'num_hits' => $row['num_hits']
      );
  }
  
  return $data;
}

?>
