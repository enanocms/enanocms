<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function page_Admin_Home()
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
  
  if ( $paths->getParam(0) == 'updates.xml' )
  {
    return acphome_process_updates();
  }
  
  // Welcome
  echo '<h2>' . $lang->get('acphome_heading_main') . '</h2>';
  echo '<p>' . $lang->get('acphome_welcome_line1') . '</p>';
  
  // Stats
  acphome_show_stats();
  
  //
  // Alerts
  //
  
  echo '<h3>' . $lang->get('acphome_heading_alerts') . '</h3>';
  
  // Demo mode
  if ( defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="acphome-box info">';
      echo '<h3>' . $lang->get('acphome_msg_demo_title') . '</h3>
            <p>' . $lang->get('acphome_msg_demo_body', array('reset_url' => makeUrlNS('Special', 'DemoReset', false, true))) . '</p>';
    echo '</div>';
  }
  
  // Check for the installer scripts
  if( file_exists(ENANO_ROOT.'/install/install.php') && !defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="acphome-box warning">
            <h3>' . $lang->get('acphome_msg_install_files_title') . '</h3>
            <p>' . $lang->get('acphome_msg_install_files_body') . '</p>
          </div>';
  }
  
  // Inactive users
  $q = $db->sql_query('SELECT time_id FROM '.table_prefix.'logs WHERE log_type=\'admin\' AND action=\'activ_req\';');
  if ( $q )
  {
    if ( $db->numrows() > 0 )
    {
      $n = $db->numrows();
      $um_flags = 'href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'UserManager\'); return false;"';
      if ( $n == 1 )
        $s = $lang->get('acphome_msg_inactive_users_one', array('um_flags' => $um_flags));
      else
        $s = $lang->get('acphome_msg_inactive_users_plural', array('um_flags' => $um_flags, 'num_users' => $n));
      echo '<div class="acphome-box notice">
              <h3>' . $lang->get('acphome_heading_inactive_users') . '</h3>
              ' . $s . '
            </div>';
    }
  }
  $db->free_result();
  
  // Update checker
  echo '<div class="acphome-box info">';
    echo '<h3>' . $lang->get('acphome_heading_updates') . '</h3>';
    echo '<p>' . $lang->get('acphome_msg_updates_info', array('updates_url' => 'http://ktulu.enanocms.org/meta/updates.xml')) . '</p>';
    echo '<div id="update_check_container"><input type="button" onclick="ajaxUpdateCheck(this.parentNode.id);" value="' . $lang->get('acphome_btn_check_updates') . '" /></div>';
  echo '</div>';
  
  // Docs
  echo '<div class="acphome-box info halfwidth">';
  echo '<h3>' . $lang->get('acphome_heading_docs') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_docs_info') . '</p>';
  echo '</div>';
  
  // Support
  echo '<div class="acphome-box info halfwidth">';
  echo '<h3>' . $lang->get('acphome_heading_support') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_support_info') . '</p>';
  echo '</div>';
  
  echo '<span class="menuclear"></span>';
  
  //
  // Stats
  //
  
  if(getConfig('log_hits') == '1')
  {
    require_once(ENANO_ROOT . '/includes/stats.php');
    $stats = stats_top_pages(10);
    //die('<pre>'.print_r($stats,true).'</pre>');
    $c = 0;
    $cls = 'row2';
    echo '<h3>' . $lang->get('acphome_heading_top_pages') . '</h3>
          <div class="tblholder">
            <table style="width: 100%;" border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>' . $lang->get('acphome_th_toppages_page') . '</th>
                <th>' . $lang->get('acphome_th_toppages_hits') . '</th>
              </tr>';
    foreach($stats as $data)
    {
      echo   '<tr>';
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      echo     '<td class="'.$cls.'">
                  <a href="'.makeUrl($data['page_urlname']).'">'.$data['page_title'].'</a></td><td style="text-align: center;" class="'.$cls.'">'.$data['num_hits']
             . '</td>';
      echo   '</tr>';
    }
    echo '  </table>
          </div>';
  }
  
  // Any hooks?
  $code = $plugins->setHook('acp_home');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
  
  //
  // Security log
  //
  
  echo '<h3>' . $lang->get('acphome_heading_seclog') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_seclog_info') . '</p>';
  $seclog = get_security_log(5);
  echo $seclog;
  
  echo '<p><a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'SecurityLog\'); return false;">' . $lang->get('acphome_btn_seclog_full') . '</a></p>';
  
}

function acphome_process_updates()
{
  require_once(ENANO_ROOT . '/includes/http.php');
  
  try
  {
    $req = new Request_HTTP('ktulu.enanocms.org', '/meta/updates.xml');
    $response = $req->get_response_body();
    header('Content-type: application/xml');
  }
  catch ( Exception $e )
  {
    header('Content-type: application/xml');
    echo '<enano><error><![CDATA[
Cannot connect to server: ' . $e->getMessage() . '
]]></error></enano>';
    return true;
  }
  if ( $req->response_code != HTTP_OK )
  {
    // Error in response
    echo '<enano><error><![CDATA[
Did not properly receive response from server. Response code: ' . $req->response_code . ' ' . $req->response_string . '
]]></error></enano>';
  }
  else
  {
    // Retrieve first update
    $first_update = preg_match('/<release tag="([^"]+)" version="([^"]+)" (codename="([^"]+)" )?relnotes="([^"]+)" ?\/>/', $response, $match);
    if ( !$first_update )
    {
      echo '<enano><error><![CDATA[
Received invalid XML response.
]]></error></enano>';
    }
    else
    {
      if ( version_compare(enano_version(true), $match[2], '<') )
      {
        $response = str_replace_once('</latest>', "  <haveupdates />\n  </latest>", $response);
      }
      echo $response;
    }
  }
  return true;
}

function acphome_show_stats()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  // Page count
  $q = $db->sql_query('SELECT COUNT(*) FROM ' . table_prefix . "pages");
  if ( !$q )
    $db->_die();
  list($page_count) = $db->fetchrow_num();
  $db->free_result();
  
  // Edits per day
  $q = $db->sql_query('SELECT ( COUNT(*) - 1 ) AS edit_count, MIN(time_id) AS install_date FROM ' . table_prefix . 'logs WHERE ( log_type = \'page\' AND action = \'edit\' ) OR ( log_type = \'security\' AND action = \'install_enano\' );');
  if ( !$q )
    $db->_die();
  $edit_info = $db->fetchrow();
  $install_date =& $edit_info['install_date'];
  $db->free_result();
  
  $days_installed = round( (time() / 86400) - ($install_date / 86400) );
  if ( $days_installed < 1 )
    $days_installed = 1;
  
  // Comments
  $q = $db->sql_query('SELECT COUNT(*) FROM ' . table_prefix . "comments");
  if ( !$q )
    $db->_die();
  list($comment_count) = $db->fetchrow_num();
  $db->free_result();
  
  // Users
  $q = $db->sql_query('SELECT ( COUNT(*) - 1 ) FROM ' . table_prefix . "users");
  if ( !$q )
    $db->_die();
  list($user_count) = $db->fetchrow_num();
  $db->free_result();
  
  // Cache size
  $cache_size = 0;
  if ( $dr = @opendir(ENANO_ROOT . '/cache/') )
  {
    while ( $dh = @readdir($dr) )
    {
      $file = ENANO_ROOT . "/cache/$dh";
      if ( @is_file($file) )
        $cache_size += filesize($file);
    }
    closedir($dr);
  }
  $cache_size = humanize_filesize($cache_size);
  
  // Files directory size
  $files_size = 0;
  if ( $dr = @opendir(ENANO_ROOT . '/files/') )
  {
    while ( $dh = @readdir($dr) )
    {
      $file = ENANO_ROOT . "/files/$dh";
      if ( @is_file($file) )
        $files_size += filesize($file);
    }
    closedir($dr);
  }
  $files_size = humanize_filesize($files_size);
  
  // Avatar directory size
  $avatar_size = 0;
  if ( $dr = @opendir(ENANO_ROOT . '/files/avatars/') )
  {
    while ( $dh = @readdir($dr) )
    {
      $file = ENANO_ROOT . "/files/avatars/$dh";
      if ( @is_file($file) )
        $avatar_size += filesize($file);
    }
    closedir($dr);
  }
  $avatar_size = humanize_filesize($avatar_size);
  
  // Database size
  $db_size = $lang->get('acphome_stat_dbsize_unsupported');
  if ( ENANO_DBLAYER == 'MYSQL' )
  {
    $q = $db->sql_query('SHOW TABLE STATUS;');
    if ( $q )
    {
      $db_size = 0;
      while ( $row = $db->fetchrow() )
      {
        if ( preg_match('/^' . table_prefix . '/', $row['Name']) )
        {
          $db_size += $row['Data_length'] + $row['Index_length'];
        }
      }
      $db_size = humanize_filesize($db_size);
    }
  }
  else if ( ENANO_DBLAYER == 'PGSQL' )
  {
    require(ENANO_ROOT . '/config.php');
    global $dbname, $dbuser, $dbpasswd;
    $dbuser = false;
    $dbpasswd = false;
    
    $q = $db->sql_query('SELECT pg_database_size(\'' . $db->escape($dbname) . '\');');
    if ( $q )
    {
      list($db_size) = $db->fetchrow_num();
      $db_size = humanize_filesize($db_size);
      $db->free_result();
    }
  }
  
  // Install date
  $install_date_human = MemberlistFormatter::format_date($install_date);
  
  // Last upgrade
  $q = $db->sql_query('SELECT time_id FROM ' . table_prefix . "logs WHERE log_type = 'security' AND action = 'upgrade_enano' ORDER BY time_id DESC LIMIT 1;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    $last_upgrade = $lang->get('acphome_stat_lastupdate_never');
  }
  else
  {
    list($last_upgrade) = $db->fetchrow_num();
    $last_upgrade = MemberlistFormatter::format_date($last_upgrade);
  }
  $db->free_result();
  
  ?>
  <div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4">
    <tr>
      <th colspan="4">
        <?php echo $lang->get('acphome_stat_header'); ?>
      </th>
    </tr>
    
    <tr>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_numpages'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo strval($page_count); ?>
      </td>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_edits'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_edits_data', array('edit_count' => $edit_info['edit_count'], 'per_day' => number_format($edit_info['edit_count'] / $days_installed, 2))); ?>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_comments'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_comments_data', array('comment_count' => $comment_count, 'per_day' => number_format($comment_count / $days_installed, 2))); ?>
      </td>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_users'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo strval($user_count); ?>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_filesize'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $files_size; ?>
      </td>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_cachesize'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $cache_size; ?>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_avatarsize'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $avatar_size; ?>
      </td>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_dbsize'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $db_size; ?>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_installdate'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $install_date_human; ?>
      </td>
      <td class="row2" style="width: 25%;">
        <?php echo $lang->get('acphome_stat_lastupdate'); ?>
      </td>
      <td class="row1" style="width: 25%;">
        <?php echo $last_upgrade; ?>
      </td>
    </tr>
    
    <tr>
      <th colspan="4" class="subhead systemversion">
        <?php echo $lang->get('acphome_stat_enano_version', array(
            'version' => enano_version(true),
            'releasename' => enano_codename(),
            'aboutlink' => makeUrlNS('Special', 'About_Enano')
          )); ?>
      </th>
    </tr>
    
  </table>
  </div>
  <?php
}
