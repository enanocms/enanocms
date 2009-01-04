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
 
function page_Admin_SecurityLog()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  // if ( defined('ENANO_DEMO_MODE') && substr($_SERVER['REMOTE_ADDR'], 0, 8) != '192.168.' )
  // {
  //   die('Security log is disabled in demo mode.');
  // }
  
  echo '<h3>' . $lang->get('acpsl_heading_main') . '</h3>';
  
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
       <tr>
         <th style="width: 60%;">' . $lang->get('acpsl_col_type') . '</th>
         <th>' . $lang->get('acpsl_col_date') . '</th>
         <th>' . $lang->get('acpsl_col_username') . '</th>
         <th>' . $lang->get('acpsl_col_ip') . '</th>
       </tr>',
      '</table></div>'
    );
  
  echo $html;
  
}

function get_security_log($num = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( $session->auth_level < USER_LEVEL_ADMIN )
  {
    $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'seclog_unauth\',' . time() . ',"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '");');
    if ( !$q )
      $db->_die();
    die('Security log: unauthorized attempt to fetch. Call has been logged and reported to the administrators.');
  }
  
  $return = '<div class="tblholder" style="/* max-height: 500px; clip: rect(0px,auto,auto,0px); overflow: auto; */"><table border="0" cellspacing="1" cellpadding="4" width="100%">';
  $cls = 'row2';                                                                                               
  $return .= '<tr><th style="width: 60%;">' . $lang->get('acpsl_col_type') . '</th><th>' . $lang->get('acpsl_col_date') . '</th><th>' . $lang->get('acpsl_col_username') . '</th><th>' . $lang->get('acpsl_col_ip') . '</th></tr>';
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
    while($r = $db->fetchrow($q))
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
  global $lang;
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
    case "admin_auth_good" : $return .= $lang->get('acpsl_entry_admin_auth_good'  , array('level' => $session->userlevel_to_string( intval($r['page_text']) ))); break;
    case "admin_auth_bad"  : $return .= $lang->get('acpsl_entry_admin_auth_bad'   , array('level' => $session->userlevel_to_string( intval($r['page_text']) ))); break;
    case "activ_good"      : $return .= $lang->get('acpsl_entry_activ_good')      ; break;
    case "auth_good"       : $return .= $lang->get('acpsl_entry_auth_good')       ; break;
    case "activ_bad"       : $return .= $lang->get('acpsl_entry_activ_bad')       ; break;
    case "auth_bad"        : $return .= $lang->get('acpsl_entry_auth_bad')        ; break;
    case "sql_inject"      : $return .= $lang->get('acpsl_entry_sql_inject'       , array('query' => htmlspecialchars($r['page_text']))); break;
    case "db_backup"       : $return .= $lang->get('acpsl_entry_db_backup'        , array('tables' => $r['page_text']))       ; break;
    case "install_enano"   : $return .= $lang->get('acpsl_entry_install_enano'    , array('version' => $r['page_text'])); break; // version is in $r['page_text']
    case "upgrade_enano"   : $return .= $lang->get('acpsl_entry_upgrade_enano'    , array('version' => $r['page_text'])); break; // version is in $r['page_text']
    case "illegal_page"    : $return .= $lang->get('acpsl_entry_illegal_page'     , array('illegal_link' => $illegal_link))    ; break;
    case "upload_enable"   : $return .= $lang->get('acpsl_entry_upload_enable')   ; break;
    case "upload_disable"  : $return .= $lang->get('acpsl_entry_upload_disable')  ; break;
    case "magick_enable"   : $return .= $lang->get('acpsl_entry_magick_enable')   ; break;
    case "magick_disable"  : $return .= $lang->get('acpsl_entry_magick_disable')  ; break;
    case "filehist_enable" : $return .= $lang->get('acpsl_entry_filehist_enable') ; break;
    case "filehist_disable": $return .= $lang->get('acpsl_entry_filehist_disable'); break;
    case "magick_path"     : $return .= $lang->get('acpsl_entry_magick_path')     ; break;
    case "plugin_disable"  : $return .= $lang->get('acpsl_entry_plugin_disable'   , array('plugin' => $r['page_text'])); break;
    case "plugin_enable"   : $return .= $lang->get('acpsl_entry_plugin_enable'    , array('plugin' => $r['page_text'])); break;
    case "plugin_install"  : $return .= $lang->get('acpsl_entry_plugin_install'   , array('plugin' => $r['page_text'])); break;
    case "plugin_uninstall": $return .= $lang->get('acpsl_entry_plugin_uninstall' , array('plugin' => $r['page_text'])); break;
    case "plugin_upgrade"  : $return .= $lang->get('acpsl_entry_plugin_upgrade'   , array('plugin' => $r['page_text'])); break;
    case "seclog_unauth"   : $return .= $lang->get('acpsl_entry_seclog_unauth')   ; break;
    case "u_from_admin"    : $return .= $lang->get('acpsl_entry_u_from_admin'     , array('username' => $r['page_text'])); break;
    case "u_from_mod"      : $return .= $lang->get('acpsl_entry_u_from_mod'       , array('username' => $r['page_text'])); break;
    case "u_to_admin"      : $return .= $lang->get('acpsl_entry_u_to_admin'       , array('username' => $r['page_text'])); break;
    case "u_to_mod"        : $return .= $lang->get('acpsl_entry_u_to_mod'         , array('username' => $r['page_text'])); break;
    case "view_comment_ip" : $return .= $lang->get('acpsl_entry_view_comment_ip'  , array('username' => htmlspecialchars($r['page_text']))); break;
  }
  $return .= '</td><td class="'.$cls.'">'.enano_date('d M Y h:i a', $r['time_id']).'</td><td class="'.$cls.'">'.$r['author'].'</td><td class="'.$cls.'" style="cursor: pointer;" onclick="ajaxReverseDNS(this);" title="' . $lang->get('acpsl_tip_reverse_dns') . '">'.$r['edit_summary'].'</td></tr>';
  return $return;
}

?>
