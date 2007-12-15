<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
function page_Admin_SecurityLog()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    echo '<h3>Error: Not authenticated</h3><p>It looks like your administration session is invalid or you are not authorized to access this administration page. Please <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true) . '">re-authenticate</a> to continue.</p>';
    return;
  }
  
  // if ( defined('ENANO_DEMO_MODE') && substr($_SERVER['REMOTE_ADDR'], 0, 8) != '192.168.' )
  // {
  //   die('Security log is disabled in demo mode.');
  // }
  
  echo '<h3>System security log</h3>';
  
  // Not calling the real fetcher because we have to paginate the results
  $offset = ( isset($_GET['offset']) ) ? intval($_GET['offset']) : 0;
  $q = $db->sql_query('SELECT COUNT(time_id) as num FROM '.table_prefix.'logs WHERE log_type=\'security\' ORDER BY time_id DESC, action ASC;');
  if ( !$q )
    $db->_die();
  $row = $db->fetchrow();
  $db->free_result();
  $count = intval($row['num']);
  $q = $db->sql_unbuffered_query('SELECT action,date_string,author,edit_summary,time_id,page_text FROM '.table_prefix.'logs WHERE log_type=\'security\' ORDER BY time_id DESC, action ASC;');
  if ( !$q )
    $db->_die();
   
  $html = paginate(
      $q,
      '{time_id}',
      $count,
      makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'SecurityLog&offset=%s'),
      $offset,
      50,
      array('time_id' => 'seclog_format_inner'),
      '<div class="tblholder" style="/* max-height: 500px; clip: rect(0px,auto,auto,0px); overflow: auto; */"><table border="0" cellspacing="1" cellpadding="4" width="100%">
       <tr><th style="width: 60%;">Type</th><th>Date</th><th>Username</th><th>IP Address</th></tr>',
      '</table></div>'
    );
  
  echo $html;
  
}

function get_security_log($num = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $session->auth_level < USER_LEVEL_ADMIN )
  {
    $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'seclog_unauth\',' . time() . ',"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '");');
    if ( !$q )
      $db->_die();
    die('Security log: unauthorized attempt to fetch. Call has been logged and reported to the administrators.');
  }
  
  $return = '<div class="tblholder" style="/* max-height: 500px; clip: rect(0px,auto,auto,0px); overflow: auto; */"><table border="0" cellspacing="1" cellpadding="4" width="100%">';
  $cls = 'row2';                                                                                               
  $return .= '<tr><th style="width: 60%;">Type</th><th>Date</th><th>Username</th><th>IP Address</th></tr>';
  $hash = sha1(microtime());
  if ( defined('ENANO_DEMO_MODE') )
  {
    require('config.php');
    $hash = md5($dbpasswd);
    unset($dbname, $dbhost, $dbuser, $dbpasswd);
    unset($dbname, $dbhost, $dbuser, $dbpasswd); // PHP5 Zend bug
  }
  // if ( defined('ENANO_DEMO_MODE') && !isset($_GET[ $hash ]) && substr($_SERVER['REMOTE_ADDR'], 0, 8) != '192.168.' )
  // {
  //   $return .= '<tr><td class="row1" colspan="4">Logs are recorded but not displayed for privacy purposes in the demo.</td></tr>';
  // }
  // else
  // {
    if(is_int($num))
    {
      $l = 'SELECT action,date_string,author,edit_summary,time_id,page_text FROM '.table_prefix.'logs WHERE log_type=\'security\' ORDER BY time_id DESC, action ASC LIMIT '.$num.';';
    }
    else
    {
      $l = 'SELECT action,date_string,author,edit_summary,time_id,page_text FROM '.table_prefix.'logs WHERE log_type=\'security\' ORDER BY time_id DESC, action ASC;';
    }
    $q = $db->sql_query($l);
    while($r = $db->fetchrow())
    {
      $return .= seclog_format_inner($r);
    }
    $db->free_result();
  // }
  $return .= '</table></div>';
  
  return $return;
}

function seclog_format_inner($r, $f = false)
{
  if ( is_array($f) )
  {
    unset($r);
    $r =& $f;
  }
  global $db, $session, $paths, $template, $plugins; // Common objects
  $return = '';
  static $cls = 'row2';
  if ( substr($_SERVER['REMOTE_ADDR'], 0, 8) != '192.168.' && defined('ENANO_DEMO_MODE') )
  {
    $r['edit_summary'] = preg_replace('/([0-9])/', 'x', $r['edit_summary']);
  }
  if ( $r['action'] == 'illegal_page' )
  {
    list($illegal_id, $illegal_ns) = unserialize($r['page_text']);
    $url = makeUrlNS($illegal_ns, $illegal_id, false, true);
    $title = get_page_title_ns($illegal_id, $illegal_ns);
    $class = ( isPage($paths->nslist[$illegal_ns] . $illegal_id) ) ? '' : ' class="wikilink-nonexistent"';
    $illegal_link = '<a href="' . $url . '"' . $class . ' onclick="window.open(this.href); return false;">' . $title . '</a>';
  }
  else if ( $r['action'] == 'plugin_enable' || $r['action'] == 'plugin_disable' )
  {
    $row['page_text'] = htmlspecialchars($row['page_text']);
  }
  $cls = ( $cls == 'row2' ) ? 'row1' : 'row2';
  $return .= '<tr><td class="'.$cls.'">';
  switch($r['action'])
  {
    case "admin_auth_good":  $return .= 'Successful elevated authentication'; if ( !empty($r['page_text']) ) { $level = $session->userlevel_to_string( intval($r['page_text']) ); $return .= "<br /><small>Authentication level: $level</small>"; } break;
    case "admin_auth_bad":   $return .= 'Failed elevated authentication'; if ( !empty($r['page_text']) ) { $level = $session->userlevel_to_string( intval($r['page_text']) ); $return .= "<br /><small>Attempted auth level: $level</small>"; } break;
    case "activ_good":       $return .= 'Successful account activation'; break;
    case "auth_good":        $return .= 'Successful regular user logon'; break;
    case "activ_bad":        $return .= 'Failed account activation'; break;
    case "auth_bad":         $return .= 'Failed regular user logon'; break;
    case "sql_inject":       $return .= 'SQL injection attempt<div style="max-width: 90%; clip: rect(0px,auto,auto,0px); overflow: auto; display: block; font-size: smaller;">Offending query: ' . htmlspecialchars($r['page_text']) . '</div>'; break;
    case "db_backup":        $return .= 'Database backup created<br /><small>Tables: ' . $r['page_text'] . '</small>'; break;
    case "install_enano":    $return .= "Installed Enano version {$r['page_text']}"; break;
    case "upgrade_enano":    $return .= "Upgraded Enano to version {$r['page_text']}"; break;
    case "illegal_page":     $return .= "Unauthorized viewing attempt<br /><small>Page: {$illegal_link}</small>"; break;
    case "upload_enable":    $return .= "Enabled file uploads"; break;
    case "upload_disable":   $return .= "Disabled file uploads"; break;
    case "magick_enable":    $return .= "Enabled ImageMagick for uploaded images"; break;
    case "magick_disable":   $return .= "Disabled ImageMagick for uploaded images"; break;
    case "filehist_enable":  $return .= "Enabled revision tracking for uploaded files"; break;
    case "filehist_disable": $return .= "Disabled revision tracking for uploaded files"; break;
    case "magick_path":      $return .= "Changed path to ImageMagick executable"; break;
    case "plugin_disable":   $return .= "Disabled plugin: {$r['page_text']}"; break;
    case "plugin_enable":    $return .= "Enabled plugin: {$r['page_text']}"; break;
    case "seclog_unauth":    $return .= "Unauthorized attempt to call security log fetcher"; break;
    case "u_from_admin":     $return .= "User {$r['page_text']} demoted from Administrators group"; break;
    case "u_from_mod":       $return .= "User {$r['page_text']} demoted from Moderators group"; break;
    case "u_to_admin":       $return .= "User {$r['page_text']} added to Administrators group"; break;
    case "u_to_mod":         $return .= "User {$r['page_text']} added to Moderators group"; break;
  }
  $return .= '</td><td class="'.$cls.'">'.date('d M Y h:i a', $r['time_id']).'</td><td class="'.$cls.'">'.$r['author'].'</td><td class="'.$cls.'" style="cursor: pointer;" onclick="ajaxReverseDNS(this);" title="Click for reverse DNS info">'.$r['edit_summary'].'</td></tr>';
  return $return;
}

?>
