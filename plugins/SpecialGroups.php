<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialgroups_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialgroups_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.5",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// $plugins->attachHook('session_started', 'SpecialGroups_paths_init();');

function SpecialGroups_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_groupcp',
    'urlname'=>'Usergroups',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

function page_Special_Usergroups()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $email; // Import e-mail encryption functions
  global $lang;
  
  if ( !$session->user_logged_in )
  {
    header('Location: ' . makeUrlComplete('Special', 'Login/' . $paths->page));
    $db->close();
    exit;
  }
  
  $template->header();
  userprefs_show_menu();
  if ( isset($_POST['do_view']) || isset($_POST['do_view_n']) || ( isset($_GET['act']) && isset($_POST['group_id']) ) )
  {
    $gid = ( isset ( $_POST['do_view_n'] ) ) ? intval($_POST['group_id_n']) : intval($_POST['group_id']);
    if ( empty($gid) || $gid < 1 )
    {
      die_friendly('Error', '<p>Hacking attempt</p>');
    }
    $q = $db->sql_query('SELECT group_name,group_type,system_group FROM '.table_prefix.'groups WHERE group_id=' . $gid . ';');
    if ( !$q )
    {
      $db->_die('SpecialGroups.php, line ' . __LINE__);
    }
    $row = $db->fetchrow();
    $db->free_result();
    $members = array();
    $pending = array();
    $q = $db->sql_query('SELECT u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod,m.pending,COUNT(c.comment_id) AS num_comments
                           FROM '.table_prefix.'users AS u
                           LEFT JOIN '.table_prefix.'group_members AS m
                             ON ( m.user_id = u.user_id )
                           LEFT JOIN '.table_prefix.'comments AS c
                             ON ( c.name = u.username )
                           WHERE m.group_id=' . $gid . '
                           GROUP BY u.user_id,u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod,m.pending
                           ORDER BY m.is_mod DESC,u.username ASC;');
    if ( !$q )
    {
      $db->_die('SpecialGroups.php, line ' . __LINE__);
    }
    
    $is_member = false;
    $is_mod = false;
    $is_pending = false;
    
    while ( $mr = $db->fetchrow() )
    {
      if ( $mr['pending'] == 1 )
      {
        $pending[] = $mr;
        if ( $mr['user_id'] == $session->user_id )
        {
          $is_pending = true;
        }
      }
      else
      {
        $members[] = $mr;
        if ( $mr['user_id'] == $session->user_id )
        {
          $is_member = true;
          if ( $mr['is_mod'] == 1 )
          {
            $is_mod = true;
          }
        }
      }
    }
    
    $status = ( $is_member && $is_mod )
      ? $lang->get('groupcp_status_mod')
      : ( ( $is_member && !$is_mod ) 
        ? $lang->get('groupcp_status_member')
        : $lang->get('groupcp_status_not_member')
        );
      
    $can_do_admin_stuff = ( $is_mod || $session->user_level >= USER_LEVEL_ADMIN );
      
    switch ( $row['group_type'] )
    {
      case GROUP_HIDDEN:  $g_state = $lang->get('groupcp_type_hidden'); break;
      case GROUP_CLOSED:  $g_state = $lang->get('groupcp_type_closed'); break;
      case GROUP_REQUEST: $g_state = $lang->get('groupcp_type_request'); break;
      case GROUP_OPEN:    $g_state = $lang->get('groupcp_type_open'); break;
    }
    
    if ( isset($_GET['act']) && $can_do_admin_stuff )
    {
      switch($_GET['act'])
      {
        case 'update':
          if(!in_array(intval($_POST['group_state']), Array(GROUP_CLOSED, GROUP_OPEN, GROUP_HIDDEN, GROUP_REQUEST)))
          {
            die_friendly('ERROR', '<p>Hacking attempt</p>');
          }
          $q = $db->sql_query('SELECT group_type, system_group FROM '.table_prefix.'groups WHERE group_id=' . intval( $_POST['group_id']) . ';');
          if ( !$q )
            $db->_die('SpecialGroups.php, line ' . __LINE__);
          $error = false;
          if ( $db->numrows() < 1 )
          {
            echo '<div class="error-box" style="margin-left: 0;">The group you selected does not exist.</div>';
            $error = true;
          }
          $r = $db->fetchrow();
          if ( $r['system_group'] == 1 && ( intval($_POST['group_state']) == GROUP_OPEN || intval($_POST['group_state']) == GROUP_REQUEST ) )
          {
            echo '<div class="error-box" style="margin-left: 0;">' . $lang->get('groupcp_err_state_system_group') . '</div>';
            $error = true;
          }
          if ( !$error )
          {
            $q = $db->sql_query('UPDATE '.table_prefix.'groups SET group_type=' . intval($_POST['group_state']) . ' WHERE group_id=' . intval( $_POST['group_id']) . ';');
            if (!$q)
              $db->_die('SpecialGroups.php, line ' . __LINE__);
            $row['group_type'] = $_POST['group_state'];
            echo '<div class="info-box" style="margin-left: 0;">' . $lang->get('groupcp_msg_state_updated') . '</div>';
          }
          break;
        case 'adduser':
          $username = $_POST['add_username'];
          $mod = ( isset($_POST['add_mod']) ) ? '1' : '0';
          
          $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\'' . $db->escape($username) . '\';');
          if (!$q)
            $db->_die('SpecialGroups.php, line ' . __LINE__);
          if ($db->numrows() < 1)
          {
            echo '<div class="error-box">' . $lang->get('groupcp_err_user_not_found') . '</div>';
            break;
          }
          $r = $db->fetchrow();
          $db->free_result();
          $uid = intval($r['user_id']);

          // Check if the user is already in the group, and if so, only update modship
          $q = $db->sql_query('SELECT member_id,is_mod FROM '.table_prefix.'group_members WHERE user_id=' . $uid . ' AND group_id=' . intval($_POST['group_id']) . ';');
          if ( !$q )
            $db->_die('SpecialGroups.php, line ' . __LINE__);
          if ( $db->numrows() > 0 )
          {
            $r = $db->fetchrow();
            if ( (string) $r['is_mod'] != $mod )
            {
              $q = $db->sql_query('UPDATE '.table_prefix.'group_members SET is_mod=' . $mod . ' WHERE member_id=' . $r['member_id'] . ';');
              if ( !$q )
                $db->_die('SpecialGroups.php, line ' . __LINE__);
              foreach ( $members as $i => $member )
              {
                if ( $member['member_id'] == $r['member_id'] )
                  $members[$i]['is_mod'] = (int)$mod;
              }
              echo '<div class="info-box">' . $lang->get('groupcp_msg_user_already_in_mod_updated', array('username' => $username)) . '</div>';
            }
            else
            {
              echo '<div class="info-box">' . $lang->get('groupcp_msg_user_already_in', array('username' => $username)) . '</div>';
            }
            break;
          }
          
          $db->free_result();
          
          $q = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,is_mod) VALUES(' . intval($_POST['group_id']) . ', ' . $uid . ', ' . $mod . ');');
          if (!$q)
            $db->_die('SpecialGroups.php, line ' . __LINE__);
          echo '<div class="info-box">' . $lang->get('groupcp_msg_user_added', array('username' => $username)) . '</div>';
          
          $q = $db->sql_query('SELECT u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod,COUNT(c.comment_id) AS num_comments
                                 FROM '.table_prefix.'users AS u
                                 LEFT JOIN '.table_prefix.'group_members AS m
                                   ON ( m.user_id = u.user_id )
                                 LEFT JOIN '.table_prefix.'comments AS c
                                   ON ( c.name = u.username )
                                 WHERE m.group_id=' . $gid . '
                                   AND m.pending!=1
                                   AND u.user_id=' . $uid . '
                                 GROUP BY u.user_id,u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod
                                 ORDER BY m.is_mod DESC,u.username ASC
                                 LIMIT 1;');
          if ( !$q )
            $db->_die('SpecialGroups.php, line ' . __LINE__);
          
          $r = $db->fetchrow();
          $members[] = $r;
          $db->free_result();
          
          // just added a user to the group, so regenerate the ranks cache
          generate_cache_userranks();
          
          break;
        case 'del_users':
          foreach ( $members as $i => $member )
          {
            if ( isset($_POST['del_user'][$member['member_id']]) )
            {
              $q = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE member_id=' . $member['member_id'] . ';');
              if (!$q)
                $db->_die('SpecialGroups.php, line ' . __LINE__);
              unset($members[$i]);
            }
          }
          // regenerate the ranks cache
          generate_cache_userranks();
          
          break;
        case 'pending':
          foreach ( $pending as $i => $member )
          {
            if ( isset( $_POST['with_user'][$member['member_id']]) )
            {
              if ( isset ( $_POST['do_appr_pending'] ) )
              {
                $q = $db->sql_query('UPDATE '.table_prefix.'group_members SET pending=0 WHERE member_id=' . $member['member_id'] . ';');
                if (!$q)
                  $db->_die('SpecialGroups.php, line ' . __LINE__);
                $members[] = $member;
                unset($pending[$i]);
                continue;
              }
              elseif ( isset ( $_POST['do_reject_pending'] ) )
              {
                $q = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE member_id=' . $member['member_id'] . ';');
                if (!$q)
                  $db->_die('SpecialGroups.php, line ' . __LINE__);
                unset($pending[$i]);
              }
            }
          }
          // memberships updated/changed, regenerate ranks cache
          generate_cache_userranks();
          
          echo '<div class="info-box">' . $lang->get('groupcp_msg_pending_updated') . '</div>';
          break;
      }
    }
    
    if ( isset($_GET['act']) && $_GET['act'] == 'update' && !$is_member && $row['group_type'] == GROUP_OPEN && !$can_do_admin_stuff )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id) VALUES(' . $gid . ', ' . $session->user_id . ');');
      if (!$q)
        $db->_die('SpecialGroups.php, line ' . __LINE__);
      echo '<div class="info-box">' . $lang->get('groupcp_msg_self_added') . '</div>';
      
      $q = $db->sql_query('SELECT u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod,COUNT(c.comment_id) AS num_comments
                             FROM '.table_prefix.'users AS u
                             LEFT JOIN '.table_prefix.'group_members AS m
                               ON ( m.user_id = u.user_id )
                             LEFT JOIN '.table_prefix.'comments AS c
                               ON ( c.name = u.username )
                             WHERE m.group_id=' . $gid . '
                               AND m.pending!=1
                               AND u.user_id=' . $session->user_id . '
                             GROUP BY u.user_id,u.username,u.email,u.reg_time,m.member_id,m.user_id,m.is_mod
                             ORDER BY m.is_mod DESC,u.username ASC
                             LIMIT 1;');
      if ( !$q )
        $db->_die('SpecialGroups.php, line ' . __LINE__);
      
      $r = $db->fetchrow();
      $members[] = $r;
      $db->free_result();
      
    }
    
    if ( isset($_GET['act']) && $_GET['act'] == 'update' && !$is_member && $row['group_type'] == GROUP_REQUEST && !$is_pending && !$can_do_admin_stuff )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,pending) VALUES(' . $gid . ', ' . $session->user_id . ', 1);');
      if (!$q)
        $db->_die('SpecialGroups.php, line ' . __LINE__);
      echo '<div class="info-box">' . $lang->get('groupcp_msg_membership_requested') . '</div>';
    }
    
    $state_btns = ( $can_do_admin_stuff ) ?
                  '<label><input type="radio" name="group_state" value="' . GROUP_HIDDEN . '" ' . (( $row['group_type'] == GROUP_HIDDEN ) ? 'checked="checked"' : '' ) . ' /> ' . $lang->get('groupcp_type_hidden') . '</label>
                   <label><input type="radio" name="group_state" value="' . GROUP_CLOSED . '" ' . (( $row['group_type'] == GROUP_CLOSED ) ? 'checked="checked"' : '' ) . ' /> ' . $lang->get('groupcp_type_closed') . '</label>
                   <label><input type="radio" name="group_state" value="' . GROUP_REQUEST. '" ' . (( $row['group_type'] == GROUP_REQUEST) ? 'checked="checked"' : '' ) . ' /> ' . $lang->get('groupcp_type_request') . '</label>
                   <label><input type="radio" name="group_state" value="' . GROUP_OPEN   . '" ' . (( $row['group_type'] == GROUP_OPEN   ) ? 'checked="checked"' : '' ) . ' /> ' . $lang->get('groupcp_type_open') . '</label>'
                   : $g_state;
    if ( !$can_do_admin_stuff && $row['group_type'] == GROUP_REQUEST && !$is_member )
    {
      if ( $is_pending )
        $state_btns .= ' ' . $lang->get('groupcp_msg_status_pending');
      else
        $state_btns .= ' <input type="submit" value="' . $lang->get('groupcp_btn_request_join') . '" />';
    }
    
    if ( !$can_do_admin_stuff && $row['group_type'] == GROUP_OPEN && !$is_member )
    {
      $state_btns .= ' <input type="submit" value="' . $lang->get('groupcp_btn_join') . '" />';
    }
    
    $g_name_local = 'groupcp_grp_' . strtolower($row['group_name']);
    $str = $lang->get($g_name_local);
    if ( $str != $g_name_local )
      $row['group_name'] = $str;
    
    echo '<form action="' . makeUrl($paths->page, 'act=update') . '" method="post" enctype="multipart/form-data">
          <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th colspan="2">' . $lang->get('groupcp_th_group_info') . '</th>
              </tr>
              <tr>
                <td class="row2">' . $lang->get('groupcp_lbl_group_name') . '</td>
                <td class="row1">' . $row['group_name'] . ( $row['system_group'] == 1 ? ' ' . $lang->get('groupcp_msg_system_group') : '' ) . '</td>
              </tr>
              <tr>
                <td class="row2">' . $lang->get('groupcp_lbl_status') . '</td>
                <td class="row1">' . $status . '</td>
              </tr>
              <tr>
                <td class="row2">' . $lang->get('groupcp_lbl_state') . '</td>
                <td class="row1">' . $state_btns . '</td>
              </tr>   
              ' . ( ( $is_mod || $session->user_level >= USER_LEVEL_ADMIN ) ? '
              <tr>
                <th class="subhead" colspan="2">
                  <input type="submit" value="' . $lang->get('etc_save_changes') . '" />
                </th>
              </tr>
              ' : '' ) . '
            </table>
          </div>
          <input name="group_id" value="' . $gid . '" type="hidden" />
          </form>';
    if ( sizeof ( $pending ) > 0 && $can_do_admin_stuff )
    {
      echo '<form action="' . makeUrl($paths->page, 'act=pending') . '" method="post" enctype="multipart/form-data">
            <input name="group_id" value="' . $gid . '" type="hidden" />
            <h2>' . $lang->get('groupcp_th_pending_memberships') . '</h2>
            <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>' . $lang->get('groupcp_th_username') . '</th>
                <th>' . $lang->get('groupcp_th_email') . '</th>
                <th>' . $lang->get('groupcp_th_reg_time') . '</th>
                <th>' . $lang->get('groupcp_th_comments') . '</th>
                <th>' . $lang->get('groupcp_th_select') . '</th>
              </tr>';
      $cls = 'row2';
      foreach ( $pending as $member )
      {
        
        $date = enano_date('F d, Y', $member['reg_time']);
        $cls = ( $cls == 'row2' ) ? 'row1' : 'row2';
        $addy = $email->encryptEmail($member['email']);
        
        echo "<tr>
                <td class='{$cls}'>{$member['username']}</td>
                <td class='{$cls}'>{$addy}</td>
                <td class='{$cls}'>{$date}</td>
                <td class='{$cls}'>{$member['num_comments']}</td>
                <td class='{$cls}' style='text-align: center;'><input type='checkbox' name='with_user[{$member['member_id']}]' /></td>
              </tr>";
      }
      echo '</table>
            </div>
            <div style="margin: 10px 0 0 auto;">
              With selected: 
              <input type="submit" name="do_appr_pending" value="' . $lang->get('groupcp_btn_approve_pending') . '" />
              <input type="submit" name="do_reject_pending" value="' . $lang->get('groupcp_btn_reject_pending') . '" />
            </div>
            </form>';
    }
    echo '<form action="' . makeUrl($paths->page, 'act=del_users') . '" method="post" enctype="multipart/form-data">
          <h2>' . $lang->get('groupcp_th_group_members') . '</h2>
          <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>' . $lang->get('groupcp_th_username') . '</th>
                <th>' . $lang->get('groupcp_th_email') . '</th>
                <th>' . $lang->get('groupcp_th_reg_time') . '</th>
                <th>' . $lang->get('groupcp_th_comments') . '</th>
                ' . ( ( $can_do_admin_stuff ) ? '
                <th>' . $lang->get('groupcp_th_remove') . '</th>
                ' : '' ) . '
              </tr>
              <tr>
                <th colspan="5" class="subhead">' . $lang->get('groupcp_th_group_mods') . '</th>
              </tr>';
    $mod_printed = false;
    $mem_printed = false;
    $cls = 'row2';
    
    foreach ( $members as $member )
    {
      if ( $member['is_mod'] != 1 )
        break;
      
      $date = enano_date('F d, Y', $member['reg_time']);
      $cls = ( $cls == 'row2' ) ? 'row1' : 'row2';
      $addy = $email->encryptEmail($member['email']);
      
      $mod_printed = true;
      
      echo "<tr>
              <td class='{$cls}'>{$member['username']}</td>
              <td class='{$cls}'>{$addy}</td>
              <td class='{$cls}'>{$date}</td>
              <td class='{$cls}'>{$member['num_comments']}</td>
              " . ( ( $can_do_admin_stuff ) ? "
              <td class='{$cls}' style='text-align: center;'><input type='checkbox' name='del_user[{$member['member_id']}]' /></td>
              " : '' ) . "
            </tr>";
    }
    if (!$mod_printed)
      echo '<tr><td class="' . $cls . '" colspan="5">' . $lang->get('groupcp_msg_no_mods') . '</td></th>';
    echo '<tr><th class="subhead" colspan="5">' . $lang->get('groupcp_th_group_members') . '</th></tr>';
    foreach ( $members as $member )
    {
      if ( $member['is_mod'] == 1 )
        continue;
      
      $date = enano_date('F d, Y', $member['reg_time']);
      $cls = ( $cls == 'row2' ) ? 'row1' : 'row2';
      $addy = $email->encryptEmail($member['email']);
      
      $mem_printed = true;
      
      echo "<tr>
              <td class='{$cls}'>{$member['username']}</td>
              <td class='{$cls}'>{$addy}</td>
              <td class='{$cls}'>{$date}</td>
              <td class='{$cls}'>{$member['num_comments']}</td>
              " . ( ( $can_do_admin_stuff ) ? "
              <td class='{$cls}' style='text-align: center;'><input type='checkbox' name='del_user[{$member['member_id']}]' /></td>
              " : '' ) . "
            </tr>";
    }
    if (!$mem_printed)
      echo '<tr><td class="' . $cls . '" colspan="5">' . $lang->get('groupcp_msg_no_members') . '</td></th>';
    echo '  </table>
          </div>';
    if ( $can_do_admin_stuff )
    {
      echo "<div style='margin: 10px 0 0 auto;'><input type='submit' name='do_del_user' value=\"" . $lang->get('groupcp_btn_remove_selected') . "\" /></div>";
    }
    echo '<input name="group_id" value="' . $gid . '" type="hidden" />
          </form>';
    if ( $can_do_admin_stuff )
    {
      echo '<form action="' . makeUrl($paths->page, 'act=adduser') . '" method="post" enctype="multipart/form-data" onsubmit="if(!submitAuthorized) return false;">
              <div class="tblholder">
                <table border="0" cellspacing="1" cellpadding="4">
                  <tr>
                    <th colspan="2">' . $lang->get('groupcp_th_add_member') . '</th>
                  </tr>
                  <tr>
                    <td class="row2">' . $lang->get('groupcp_lbl_username') . '</td><td class="row1">' . $template->username_field('add_username') . '</td>
                  </tr>
                  <tr>
                    <td class="row2">' . $lang->get('groupcp_lbl_moderator') . '</td><td class="row1"><label><input type="checkbox" name="add_mod" /> ' . $lang->get('groupcp_lbl_make_mod') . '</label></td>
                  </tr>
                  <tr>
                    <th class="subhead" colspan="2">
                      <input type="submit" value="' . $lang->get('groupcp_btn_add_member') . '" />
                    </th>
                  </tr>
                </table>
              </div>
              <input name="group_id" value="' . $gid . '" type="hidden" />
            </form>';
    }
  }
  else
  {
    echo '<form action="'.makeUrlNS('Special', 'Usergroups').'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">' . $lang->get('groupcp_th_select_group') . '</th>
            </tr>
            <tr>
              <td class="row2" style="text-align: right; width: 50%;">
                ' . $lang->get('groupcp_lbl_current_memberships') . '
              </td>
              <td class="row1" style="width: 50%;">';
    $taboo = Array('Everyone');
    if ( sizeof ( $session->groups ) > count($taboo) )
    {
      echo '<select name="group_id">';
      foreach ( $session->groups as $id => $group )
      {
        $taboo[] = $db->escape($group);
        $group = htmlspecialchars($group);
        if ( $group != 'Everyone' )
        {
          $g_name_local = 'groupcp_grp_' . strtolower($group);
          $str = $lang->get($g_name_local);
          if ( $str != $g_name_local )
            $group = $str;
          echo '<option value="' . $id . '">' . $group . '</option>';
        }
      }
      echo '</select>
            <input type="submit" name="do_view" value="' . $lang->get('groupcp_btn_view') . '" />';
    }
    else
    {
      echo 'None';
    }
    
    echo '</td>
        </tr>';
    $taboo = 'WHERE group_name != \'' . implode('\' AND group_name != \'', $taboo) . '\'';
    $q = $db->sql_query('SELECT group_id,group_name FROM '.table_prefix.'groups '.$taboo.' AND group_type != ' . GROUP_HIDDEN . ' ORDER BY group_name ASC;');
    if(!$q)
    {
      echo $db->get_error();
      $template->footer();
      return;
    }
    if($db->numrows() > 0)
    {
      echo '<tr>
              <td class="row2" style="text-align: right;">
                ' . $lang->get('groupcp_lbl_non_memberships') . '
              </td>
              <td class="row1">
              <select name="group_id_n">';
      while ( $row = $db->fetchrow() )
      {
        if ( $row['group_name'] != 'Everyone' )
        {
          echo '<option value="' . $row['group_id'] . '">' . htmlspecialchars($row['group_name']) . '</option>';
        }
      }
      echo '</select>
            <input type="submit" name="do_view_n" value="' . $lang->get('groupcp_btn_view') . '" />
          </td>
        </tr>
      ';
    }
    $db->free_result();
    echo '</table>
        </div>
        </form>';
  }
  $template->footer();
}

?>
