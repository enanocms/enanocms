<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.2 (Caoineag alpha 2)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// Usergroup editor

function page_Admin_GroupManager()
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
  
  if(isset($_POST['do_create_stage1']))
  {
    if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['create_group_name']))
    {
      echo '<p>' . $lang->get('acpug_err_group_name_invalid') . '</p>';
      return;
    }
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">' . $lang->get('acpug_heading_creating_group') . ' '.htmlspecialchars($_POST['create_group_name']).'</th></tr>
          <tr>
            <td class="row1">' . $lang->get('acpug_field_group_mod') . '</td><td class="row1">' . $template->username_field('group_mod') . '</td>
          </tr>
          <tr><td class="row2">' . $lang->get('acpug_field_group_type') . '</td><td class="row2">
            <label><input type="radio" name="group_status" value="'.GROUP_CLOSED.'" checked="checked" /> ' . $lang->get('groupcp_type_hidden') . '</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_REQUEST.'" /> ' . $lang->get('groupcp_type_closed') . '</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_OPEN.'" /> ' . $lang->get('groupcp_type_request') . '</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_HIDDEN.'" /> ' . $lang->get('groupcp_type_open') . '</label>
          </td></tr>
          <tr>
            <th class="subhead" colspan="2">
              <input type="hidden" name="create_group_name" value="'.htmlspecialchars($_POST['create_group_name']).'" />
              <input type="submit" name="do_create_stage2" value="' . $lang->get('acpug_btn_create_stage2') . '" />
            </th>
          </tr>
          </table>
          </div>';
    echo '</form>';
    return;
  }
  elseif(isset($_POST['do_create_stage2']))
  {
    if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['create_group_name']))
    {
      echo '<p>' . $lang->get('acpug_err_group_name_invalid') . '</p>';
      return;
    }
    if(!in_array(intval($_POST['group_status']), Array(GROUP_CLOSED, GROUP_OPEN, GROUP_HIDDEN, GROUP_REQUEST)))
    {
      echo '<p>Hacking attempt</p>';
      return;
    }
    $e = $db->sql_query('SELECT group_id FROM '.table_prefix.'groups WHERE group_name=\''.$db->escape($_POST['create_group_name']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() > 0)
    {
      echo '<p>' . $lang->get('acpug_err_already_exist') . '</p>';
      return;
    }
    $db->free_result();
    $q = $db->sql_query('INSERT INTO '.table_prefix.'groups(group_name,group_type) VALUES( \''.$db->escape($_POST['create_group_name']).'\', ' . intval($_POST['group_status']) . ' )');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    $e = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($_POST['group_mod']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>' . $lang->get('acpug_err_bad_username') . '</p>';
      return;
    }
    $row = $db->fetchrow();
    $id = $row['user_id'];
    $db->free_result();
    $e = $db->sql_query('SELECT group_id FROM '.table_prefix.'groups WHERE group_name=\''.$db->escape($_POST['create_group_name']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>' . $lang->get('acpug_err_bad_insert_id') . '</p>';
      return;
    }
    $row = $db->fetchrow();
    $gid = $row['group_id'];
    $db->free_result();
    $e = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,is_mod) VALUES('.$gid.', '.$id.', 1);');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    $g_name = htmlspecialchars($_POST['create_group_name']);
    echo "<div class='info-box'>
            <b>" . $lang->get('acpug_heading_info') . "</b><br />
            " . $lang->get('acpug_msg_create_success', array('g_name' => $g_name)) . "
          </div>";
  }
  if(isset($_POST['do_edit']) || isset($_POST['edit_do']))
  {
    // Fetch the group name
    $q = $db->sql_query('SELECT group_name,system_group FROM '.table_prefix.'groups WHERE group_id='.intval($_POST['group_edit_id']).';');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>Error: couldn\'t look up group name</p>';
    }
    $row = $db->fetchrow();
    $name = htmlspecialchars($row['group_name']);
    $db->free_result();
    if(isset($_POST['edit_do']))
    {
      if(isset($_POST['edit_do']['del_group']))
      {
        if ( $row['system_group'] == 1 )
        {
          echo '<div class="error-box">' . $lang->get('acpug_err_nodelete_system_group', array('g_name' => $name)) . '</div>';
        }
        else
        {
          $q = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE group_id='.intval($_POST['group_edit_id']).';');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          $q = $db->sql_query('DELETE FROM '.table_prefix.'groups WHERE group_id='.intval($_POST['group_edit_id']).';');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          echo '<div class="info-box">' . $lang->get('acpug_msg_delete_success', array('g_name' => $name, 'a_flags' => 'href="javascript:ajaxPage(\'' . $paths->nslist['Admin'] . 'GroupManager\');"')) . '</div>';
          return;
        }
      }
      if(isset($_POST['edit_do']['save_name']))
      {
        if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['group_name']))
        {
          echo '<p>' . $lang->get('acpug_err_group_name_invalid') . '</p>';
          return;
        }
        $q = $db->sql_query('UPDATE '.table_prefix.'groups SET group_name=\''.$db->escape($_POST['group_name']).'\'
            WHERE group_id='.intval($_POST['group_edit_id']).';');
        if(!$q)
        {
          echo $db->get_error();
          return;
        }
        else
        {
          echo '<div class="info-box" style="margin: 0 0 10px 0;"">
                  ' . $lang->get('acpug_msg_name_update_success') . '
                </div>';
        }
        $name = htmlspecialchars($_POST['group_name']);
        
      }
      $q = $db->sql_query('SELECT member_id FROM '.table_prefix.'group_members
                             WHERE group_id='.intval($_POST['group_edit_id']).';');
      if(!$q)
      {
        echo $db->get_error();
        return;
      }
      if($db->numrows() > 0)
      {
        while($row = $db->fetchrow($q))
        {
          if(isset($_POST['edit_do']['del_' . $row['member_id']]))
          {
            $e = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE member_id='.$row['member_id']);
            if(!$e)
            {
              echo $db->get_error();
              return;
            }
          }
        }
      }
      $db->free_result();
      if(isset($_POST['edit_do']['add_member']))
      {
        $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($_POST['edit_add_username']).'\';');
        if(!$q)
        {
          echo $db->get_error();
          return;
        }
        if($db->numrows() > 0)
        {
          $row = $db->fetchrow();
          $user_id = $row['user_id'];
          $is_mod = ( isset( $_POST['add_mod'] ) ) ? '1' : '0';
          $q = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,is_mod) VALUES('.intval($_POST['group_edit_id']).','.$user_id.','.$is_mod.');');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          else
          {
            echo '<div class="info-box" style="margin: 0 0 10px 0;"">
                    ' . $lang->get('acpug_msg_user_added', array('username' => htmlspecialchars($_POST['edit_add_username']))) . '
                  </div>';
          }
        }
        else
          echo '<div class="warning-box">' . $lang->get('acpug_err_username_not_exist', array('username' => htmlspecialchars($_POST['edit_add_username']))) . '</div>';
      }
    }
    $sg_disabled = ( $row['system_group'] == 1 ) ?
             ' value="' . $lang->get('acpug_btn_cant_delete') . '" disabled="disabled" style="color: #FF9773" ' :
             ' value="' . $lang->get('acpug_btn_delete_group') . '" style="color: #FF3713" ';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th>' . $lang->get('acpug_heading_edit_name') . '</th></tr>
          <tr>
            <td class="row1">
              ' . $lang->get('acpug_field_group_name') . ' <input type="text" name="group_name" value="'.$name.'" />
            </td>
          </tr>
          <tr>
            <th class="subhead">
              <input type="submit" name="edit_do[save_name]" value="' . $lang->get('acpug_btn_save_name') . '" />
              <input type="submit" name="edit_do[del_group]" '.$sg_disabled.' />
            </th>
          </tr>
          </table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.htmlspecialchars($_POST['group_edit_id']).'" />';
    echo '</form>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="3">' . $lang->get('acpug_heading_edit_members') . '</th></tr>';
    $q = $db->sql_query('SELECT m.member_id,m.is_mod,u.username FROM '.table_prefix.'group_members AS m
                           LEFT JOIN '.table_prefix.'users AS u
                             ON u.user_id=m.user_id
                             WHERE m.group_id='.intval($_POST['group_edit_id']).'
                           ORDER BY m.is_mod DESC, u.username ASC;');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<tr><td colspan="3" class="row1">' . $lang->get('acpug_msg_no_members') . '</td></tr>';
    }
    else
    {
      $cls = 'row2';
      while($row = $db->fetchrow())
      {
        $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
        $mod = ( $row['is_mod'] == 1 ) ? $lang->get('acpug_lbl_member_mod') : '';
        echo '<tr>
                <td class="'.$cls.'" style="width: 100%;">
                  ' . $row['username'] . '
                </td>
                <td class="'.$cls.'">
                  '.$mod.'
                </td>
                <td class="'.$cls.'">
                  <input type="submit" name="edit_do[del_'.$row['member_id'].']" value="' . $lang->get('acpug_btn_remove_member') . '" />
                </td>
              </tr>';
      }
    }
    $db->free_result();
    echo '</table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.htmlspecialchars($_POST['group_edit_id']).'" />';
    echo '</form>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
            <tr>
              <th>' . $lang->get('acpug_heading_add_member') . '</th>
            </tr>
            <tr>
              <td class="row1">
                ' . $lang->get('acpug_field_username') . ' ' . $template->username_field('edit_add_username') . '
              </td>
            </tr>
            <tr>
              <td class="row2">
                <label><input type="checkbox" name="add_mod" /> ' . $lang->get('acpug_field_make_mod') . '</label>
                ' . $lang->get('acpug_field_make_mod_hint') . '
              </td>
            </tr>
            <tr>
              <th class="subhead">
                <input type="submit" name="edit_do[add_member]" value="' . $lang->get('acpug_btn_add_user') . '" />
              </th>
            </tr>
          </table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.htmlspecialchars($_POST['group_edit_id']).'" />';
    echo '</form>';
    return;
  }
  echo '<h3>' . $lang->get('acpug_heading_main') . '</h3>';
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  $q = $db->sql_query('SELECT group_id,group_name FROM '.table_prefix.'groups ORDER BY group_name ASC;');
  if(!$q)
  {
    echo $db->get_error();
  }
  else
  {
    echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
          <tr>
          <th>' . $lang->get('acpug_heading_edit_existing') . '</th>
          </tr>';
    echo '<tr><td class="row2"><select name="group_edit_id">';
    while ( $row = $db->fetchrow() )
    {
      if ( $row['group_name'] != 'Everyone' )
      {
        echo '<option value="' . $row['group_id'] . '">' . htmlspecialchars( $row['group_name'] ) . '</option>';
      }
    }
    $db->free_result();
    echo '</select></td></tr>';
    echo '<tr><td class="row1" style="text-align: center;"><input type="submit" name="do_edit" value="' . $lang->get('acpug_btn_edit_stage1') . '" /></td></tr>
          </table>
          </div>
          </form><br />';
  }
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  echo '<div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
        <tr>
        <th colspan="2">' . $lang->get('acpug_heading_create_new') . '</th>
        </tr>';
  echo '<tr><td class="row2">' . $lang->get('acpug_field_group_name') . '</td><td class="row2"><input type="text" name="create_group_name" /></td></tr>';
  echo '<tr><td colspan="2" class="row1" style="text-align: center;"><input type="submit" name="do_create_stage1" value="' . $lang->get('acpug_btn_create_stage1') . ' &raquo;" /></td></tr>
        </table>
        </div>';
  echo '</form>';
}

?>
