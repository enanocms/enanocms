<?php
/*
Plugin Name: plugin_specialrecentchanges_title
Plugin URI: http://enanocms.org/
Description: plugin_specialrecentchanges_desc
Author: Dan Fuhry
Version: 1.1.1
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('session_started', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'specialpage_recent_changes\',
      \'urlname\'=>\'RecentChanges\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

function page_Special_RecentChanges()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  // One super-loaded SQL query to fetch all the info we need:
  // (theoretical)
  //   SELECT ( CHAR_LENGTH(l1.page_text) - CHAR_LENGTH(l2.page_text) ) AS size_change, l1.author, l1.page_id, l1.namespace, l1.edit_summary,
  //       l1.time_id AS currev_time, l2.time_id AS oldrev_time
  //     FROM logs AS l1
  //     LEFT JOIN logs AS l2                                                    
  //       ON ( l1.log_type = l2.log_type AND l1.action = 'edit' AND l1.action = l2.action AND l2.time_id < l1.time_id AND l1.page_id = l2.page_id AND l1.namespace = l2.namespace )
  //     WHERE l2.time_id IS NOT NULL
  //     GROUP BY l1.page_id, l1.namespace
  //     ORDER BY l2.time_id DESC, l1.time_id DESC;
  // (the actual query is generated based on filter criteria)
  // How it works:
  //  * Join the logs table with itself
  //  * Select the size_change virtual column, which is based on current_rev_length - old_rev_length
  //  * Use GROUP BY to group rows from the same page together
  //  * Make sure that the time_id in the second instance (l2) of enano_logs is LESS than the time_id in the first instance (l1)
  //  * Use ORDER BY to ensure that the latest revision before current is selected
  
  $where_extra = '';
  if ( isset($_GET['filter_author']) && is_array($_GET['filter_author']) )
  {
    $f_author = $_GET['filter_author'];
    foreach ( $f_author as &$author )
    {
      $author = $db->escape($author);
    }
    $f_author = "\n    AND (\n      l1.author = '" . implode("'\n      OR l1.author = '", $f_author) . "'\n    )";
    $where_extra .= $f_author;
  }
  
  if ( ENANO_DBLAYER == 'MYSQL' )
  {
    $sql = 'SELECT ( CHAR_LENGTH(l1.page_text) - CHAR_LENGTH(l2.page_text) ) AS size_change, l1.author, l1.page_id, l1.namespace, l1.edit_summary,
    l1.time_id AS currev_time, l2.time_id AS oldrev_time
  FROM ' . table_prefix . 'logs AS l1
  LEFT JOIN ' . table_prefix . 'logs AS l2                                                    
    ON ( l1.log_type = l2.log_type AND l1.action = \'edit\' AND l1.action = l2.action AND l2.time_id < l1.time_id AND l1.page_id = l2.page_id AND l1.namespace = l2.namespace )
  WHERE l2.time_id IS NOT NULL' . $where_extra . '
  GROUP BY oldrev_time
  ORDER BY l1.time_id DESC, l2.time_id DESC;';
  }
  else
  {
    $sql = 'SELECT DISTINCT ON (l1.time_id) ( CHAR_LENGTH(l1.page_text) - CHAR_LENGTH(l2.page_text) ) AS size_change, l1.author, l1.page_id, l1.namespace, l1.edit_summary,
    l1.time_id AS currev_time, l2.time_id AS oldrev_time
  FROM ' . table_prefix . 'logs AS l1
  LEFT JOIN ' . table_prefix . 'logs AS l2                                                    
    ON ( l1.log_type = l2.log_type AND l1.action = \'edit\' AND l1.action = l2.action AND l2.time_id < l1.time_id AND l1.page_id = l2.page_id AND l1.namespace = l2.namespace )
  WHERE l2.time_id IS NOT NULL' . $where_extra . '
  GROUP BY l1.time_id, l1.page_id, l1.namespace, l1.author, l1.edit_summary, l2.time_id, l1.page_text, l2.page_text
  ORDER BY l1.time_id DESC, l2.time_id DESC;';
  }
  
  $template->header();
  
  $q = $db->sql_unbuffered_query($sql);
  if ( !$q )
    $db->_die();
  
  if ( $row = $db->fetchrow($q) )
  {
    echo '<p>';
    do
    {
      $css = rch_get_css($row['size_change']);
      $pagekey = ( isset($paths->nslist[$row['namespace']]) ) ? $paths->nslist[$row['namespace']] . $row['page_id'] : $row['namespace'] . ':' . $row['page_id'];
      $pagekey = sanitize_page_id($pagekey);
      
      // diff button
      echo '(';
      if ( isPage($pagekey) )
      {
        echo '<a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=diff&diff1={$row['oldrev_time']}&diff2={$row['currev_time']}", true) . '">';
      }
      echo $lang->get('pagetools_rc_btn_diff');
      if ( isPage($pagekey) )
      {
        echo '</a>';
      }
      echo ') ';
      
      // hist button
      echo '(';
      if ( isPage($pagekey) )
      {
        echo '<a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=history", true) . '">';
      }
      echo $lang->get('pagetools_rc_btn_hist');
      if ( isPage($pagekey) )
      {
        echo '</a>';
      }
      echo ') . . ';
      
      // link to the page
      $cls = ( isPage($pagekey) ) ? '' : ' class="wikilink-nonexistent"';
      echo '<a href="' . makeUrlNS($row['namespace'], $row['page_id']) . '"' . $cls . '>' . htmlspecialchars(get_page_title_ns($row['page_id'], $row['namespace'])) . '</a>; ';
      
      // date
      $today = time() - ( time() % 86400 );
      $date = ( $row['currev_time'] > $today ) ? '' : MemberlistFormatter::format_date($row['currev_time']) . ' ';
      $date .= date('h:i s', $row['currev_time']);
      echo "$date . . ";
      
      // size counter
      $size_change = number_format($row['size_change']);
      if ( substr($size_change, 0, 1) != '-' )
        $size_change = "+$size_change";
      
      echo "<span style=\"$css\">({$size_change})</span>";
      
      // link to userpage
      echo ' . . ';
      $cls = ( isPage($paths->nslist['User'] . $row['author']) ) ? '' : ' class="wikilink-nonexistent"';
      echo '<a href="' . makeUrlNS('User', sanitize_page_id($row['author']), false, true) . '"' . $cls . '>' . htmlspecialchars($row['author']) . '</a> ';
      echo '(';
      echo '<a href="' . makeUrlNS('Special', 'PrivateMessages/Compose/To/' . sanitize_page_id($row['author']), false, true) . '">';
      echo $lang->get('pagetools_rc_btn_pm');
      echo '</a>, ';
      echo '<a href="' . makeUrlNS('User', sanitize_page_id($row['author']), false, true) . '#do:comments">';
      echo $lang->get('pagetools_rc_btn_usertalk');
      echo '</a>';
      echo ') . . ';
      
      // Edit summary
      echo '<i>(';
      if ( empty($row['edit_summary']) )
      {
        echo '<span style="color: #808080;">' . $lang->get('history_summary_none_given') . '</span>';
      }
      else
      {
        echo RenderMan::parse_internal_links(htmlspecialchars($row['edit_summary']));
      }
      echo ')</i>';
      
      echo '<br />';
    }
    while ( $row = $db->fetchrow($q) );
    echo '</p>';
  }
  
  $template->footer();
}

function rch_get_css($change_size)
{
  // Hardly changed at all? Return a gray
  if ( $change_size <= 5 && $change_size >= -5 )
    return 'color: #808080;';
  // determine saturation based on size of change (1-500 bytes)
  $change_abs = abs($change_size);
  $index = 0x70 * ( $change_abs / 500 );
  if ( $index > 0x70 )
    $index = 0x70;
  $index = $index + 0x40;
  $index = dechex($index);
  if ( strlen($index) < 2 )
    $index = "0$index";
  $css = ( $change_size > 0 ) ? "color: #00{$index}00;" : "color: #{$index}0000;";
  if ( $change_abs > 500 )
    $css .= ' font-weight: bold;';
  return $css;
}

?>
