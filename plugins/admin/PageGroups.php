<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3 (Dyrad)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function page_Admin_PageGroups()
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
  
  if ( isset($_POST['action']) )
  {
    if ( isset($_POST['action']['create']) || isset($_POST['action']['create_stage2']) )
    {
      switch ( isset($_POST['action']['create_stage2']) )
      {
        case true:
          if ( empty($_POST['pg_name']) || empty($_POST['group_type']) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_need_name') . '</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_TAGGED && empty($_POST['member_tag']) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_need_tag') . '</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_CATLINK && empty($_POST['member_cat']) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_need_cat') . '</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_NORMAL && empty($_POST['member_page_0']) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_need_page') . '</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_REGEX && empty($_POST['regex']) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_need_regex') . '</div>';
            return;
          }
          if ( $_POST['group_type'] != PAGE_GRP_TAGGED && $_POST['group_type'] != PAGE_GRP_CATLINK && $_POST['group_type'] != PAGE_GRP_NORMAL && $_POST['group_type'] != PAGE_GRP_REGEX )
          {
            echo '<div class="error-box">Umm, you sent an invalid group type. I\'d put a real error message here but this will only be shown if you try to hack the system.</div>';
            return;
          }
          // All checks passed, create the group
          switch($_POST['group_type'])
          {
            case PAGE_GRP_TAGGED:
              $name = $db->escape($_POST['pg_name']);
              $tag  = $db->escape($_POST['member_tag']);
              $sql = 'INSERT INTO '.table_prefix.'page_groups(pg_type,pg_name,pg_target) VALUES(' . PAGE_GRP_TAGGED . ', \'' . $name . '\', \'' . $tag . '\');';
              $q = $db->sql_query($sql);
              if ( !$q )
                $db->_die();
              break;
            case PAGE_GRP_CATLINK:
              $name = $db->escape($_POST['pg_name']);
              $cat  = $db->escape($_POST['member_cat']);
              $sql = 'INSERT INTO '.table_prefix.'page_groups(pg_type,pg_name,pg_target) VALUES(' . PAGE_GRP_CATLINK . ', \'' . $name . '\', \'' . $cat . '\');';
              $q = $db->sql_query($sql);
              if ( !$q )
                $db->_die();
              break;
            case PAGE_GRP_NORMAL:
              $name = $db->escape($_POST['pg_name']);
              $sql = 'INSERT INTO '.table_prefix.'page_groups(pg_type,pg_name) VALUES(' . PAGE_GRP_NORMAL . ', \'' . $name . '\');';
              $q = $db->sql_query($sql);
              if ( !$q )
                $db->_die();
              
              $ins_id = $db->insert_id();
              
              // Page list
              $keys = array_keys($_POST);
              $arr_pages = array();
              foreach ( $keys as $val )
              {
                if ( preg_match('/^member_page_([0-9]+?)$/', $val) && !empty($_POST[$val]) && isPage($_POST[$val]) )
                {
                  $arr_pages[] = $_POST[$val];
                }
              }
              $arr_sql = array();
              foreach ( $arr_pages as $page )
              {
                list($id, $ns) = RenderMan::strToPageID($page);
                $id = sanitize_page_id($id);
                $arr_sql[] = '(' . $ins_id . ',\'' . $db->escape($id) . '\', \'' . $ns . '\')';
              }
              $sql = 'INSERT INTO '.table_prefix.'page_group_members(pg_id,page_id,namespace) VALUES' . implode(',', $arr_sql) . ';';
              $q = $db->sql_query($sql);
              if ( !$q )
                $db->_die();
              break;
            case PAGE_GRP_REGEX:
              $name  = $db->escape($_POST['pg_name']);
              $regex = $db->escape($_POST['regex']);
              $sql = 'INSERT INTO '.table_prefix.'page_groups(pg_type,pg_name,pg_target) VALUES(' . PAGE_GRP_REGEX . ', \'' . $name . '\', \'' . $regex . '\');';
              $q = $db->sql_query($sql);
              if ( !$q )
                $db->_die();
              break;
          }
          echo '<div class="info-box">' . $lang->get('acppg_msg_create_success', array('group_name' => htmlspecialchars($_POST['pg_name']))) . '</div>';
          break;
      }
      // A little Javascript magic
      ?>
      <script language="javascript" type="text/javascript">
        function pg_create_typeset(selector)
        {
          var pg_normal  = <?php echo PAGE_GRP_NORMAL; ?>;
          var pg_tagged  = <?php echo PAGE_GRP_TAGGED; ?>;
          var pg_catlink = <?php echo PAGE_GRP_CATLINK; ?>;
          var pg_regex   = <?php echo PAGE_GRP_REGEX; ?>;
          var selection = false;
          // Get selection
          for ( var i = 0; i < selector.childNodes.length; i++ )
          {
            var child = selector.childNodes[i];
            if ( !child || child.tagName != 'OPTION' )
            {
              continue;
            }
            if ( child.selected )
            {
              selection = child.value;
            }
          }
          if ( !selection )
          {
            alert('Cannot get field value');
            return true;
          }
          selection = parseInt(selection);
          if ( selection != pg_normal && selection != pg_tagged && selection != pg_catlink && selection != pg_regex )
          {
            alert('Invalid field value');
            return true;
          }
          
          // We have the selection and it's validated; show the appropriate field group
          
          if ( selection == pg_normal )
          {
            document.getElementById('pg_create_title_catlink').style.display = 'none';
            document.getElementById('pg_create_catlink_1').style.display = 'none';
            document.getElementById('pg_create_catlink_2').style.display = 'none';
            
            document.getElementById('pg_create_title_tagged').style.display = 'none';
            document.getElementById('pg_create_tagged_1').style.display = 'none';
            document.getElementById('pg_create_tagged_2').style.display = 'none';
            
            document.getElementById('pg_create_title_normal').style.display = 'inline';
            document.getElementById('pg_create_normal_1').style.display = 'block';
            document.getElementById('pg_create_normal_2').style.display = 'block';
            
            document.getElementById('pg_create_title_regex').style.display = 'none';
            document.getElementById('pg_create_regex_1').style.display = 'none';
            document.getElementById('pg_create_regex_2').style.display = 'none';
          }
          else if ( selection == pg_catlink )
          {
            document.getElementById('pg_create_title_catlink').style.display = 'inline';
            document.getElementById('pg_create_catlink_1').style.display = 'block';
            document.getElementById('pg_create_catlink_2').style.display = 'block';
            
            document.getElementById('pg_create_title_tagged').style.display = 'none';
            document.getElementById('pg_create_tagged_1').style.display = 'none';
            document.getElementById('pg_create_tagged_2').style.display = 'none';
            
            document.getElementById('pg_create_title_normal').style.display = 'none';
            document.getElementById('pg_create_normal_1').style.display = 'none';
            document.getElementById('pg_create_normal_2').style.display = 'none';
            
            document.getElementById('pg_create_title_regex').style.display = 'none';
            document.getElementById('pg_create_regex_1').style.display = 'none';
            document.getElementById('pg_create_regex_2').style.display = 'none';
          }
          else if ( selection == pg_tagged )
          {
            document.getElementById('pg_create_title_catlink').style.display = 'none';
            document.getElementById('pg_create_catlink_1').style.display = 'none';
            document.getElementById('pg_create_catlink_2').style.display = 'none';
            
            document.getElementById('pg_create_title_tagged').style.display = 'inline';
            document.getElementById('pg_create_tagged_1').style.display = 'block';
            document.getElementById('pg_create_tagged_2').style.display = 'block';
            
            document.getElementById('pg_create_title_normal').style.display = 'none';
            document.getElementById('pg_create_normal_1').style.display = 'none';
            document.getElementById('pg_create_normal_2').style.display = 'none';
            
            document.getElementById('pg_create_title_regex').style.display = 'none';
            document.getElementById('pg_create_regex_1').style.display = 'none';
            document.getElementById('pg_create_regex_2').style.display = 'none';
          }
          else if ( selection == pg_regex )
          {
            document.getElementById('pg_create_title_catlink').style.display = 'none';
            document.getElementById('pg_create_catlink_1').style.display = 'none';
            document.getElementById('pg_create_catlink_2').style.display = 'none';
            
            document.getElementById('pg_create_title_tagged').style.display = 'none';
            document.getElementById('pg_create_tagged_1').style.display = 'none';
            document.getElementById('pg_create_tagged_2').style.display = 'none';
            
            document.getElementById('pg_create_title_normal').style.display = 'none';
            document.getElementById('pg_create_normal_1').style.display = 'none';
            document.getElementById('pg_create_normal_2').style.display = 'none';
            
            document.getElementById('pg_create_title_regex').style.display = 'inline';
            document.getElementById('pg_create_regex_1').style.display = 'block';
            document.getElementById('pg_create_regex_2').style.display = 'block';
          }
        
        }
        
        // Set to pg_normal on page load
        var pg_createform_init = function()
        {
          document.getElementById('pg_create_title_catlink').style.display = 'none';
          document.getElementById('pg_create_catlink_1').style.display = 'none';
          document.getElementById('pg_create_catlink_2').style.display = 'none';
          
          document.getElementById('pg_create_title_tagged').style.display = 'none';
          document.getElementById('pg_create_tagged_1').style.display = 'none';
          document.getElementById('pg_create_tagged_2').style.display = 'none';
          
          document.getElementById('pg_create_title_regex').style.display = 'none';
          document.getElementById('pg_create_regex_1').style.display = 'none';
          document.getElementById('pg_create_regex_2').style.display = 'none';
          
          document.getElementById('pg_create_title_normal').style.display = 'inline';
          document.getElementById('pg_create_normal_1').style.display = 'block';
          document.getElementById('pg_create_normal_2').style.display = 'block';
        }
        
        addOnloadHook(pg_createform_init);
        
        function pg_create_more_fields()
        {
          var targettd = document.getElementById('pg_create_normal_2');
          var id = 0;
          for ( var i = 0; i < targettd.childNodes.length; i++ )
          {
            var child = targettd.childNodes[i];
            if ( child.tagName == 'INPUT' )
            {
              if ( child.type == 'button' )
              {
                var newInp = document.createElement('input');
                // <input type="text" name="member_page_1" id="pg_create_member_1" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                newInp.type    = 'text';
                newInp.name    = 'member_page_' + id;
                newInp.id      = 'pg_create_member_' + id;
                newInp.onkeyup = function(e) { return ajaxPageNameComplete(this); };
                newInp.size    = '30';
                newInp.style.marginTop = '3px';
                targettd.insertBefore(newInp, child);
                targettd.insertBefore(document.createElement('br'), child);
                break;
              }
              else // if ( child.type == 'text' )
              {
                id++;
              }
            }
          }
        }
        
      </script>
      <?php
      
      // Build category list
      $q = $db->sql_query('SELECT name,urlname FROM '.table_prefix.'pages WHERE namespace=\'Category\';');
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
      {
        $catlist = $lang->get('acppg_err_no_cats');
      }
      else
      {
        $catlist = '<select name="member_cat">';
        while ( $row = $db->fetchrow() )
        {
          $catlist .= '<option value="' . htmlspecialchars($row['urlname']) . '">' . htmlspecialchars($row['name']) . '</option>';
        }
        $catlist .= '</select>';
      }
      
      echo '<script type="text/javascript">
              var __pg_edit_submitAuthorized = true;
            </script>';
      
      echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized || !__pg_edit_submitAuthorized) return false;" enctype="multipart/form-data">';
      
      echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
              <th colspan="2">' . $lang->get('acppg_th_create') . '</th>
              </tr>';
      
      // Name
      echo '<tr>
              <td class="row2">
              ' . $lang->get('acppg_field_group_name') . '<br />
              <small>' . $lang->get('acppg_field_group_name_hint') . '</small>
              </td>
              <td class="row1">
              <input type="text" name="pg_name" size="30" />
              </td>
            </tr>';
            
      // Group type
      echo '<tr>
              <td class="row2">
              ' . $lang->get('acppg_field_group_type') . '
              </td>
              <td class="row1">
              <select name="group_type" onchange="pg_create_typeset(this);">
                <option value="' . PAGE_GRP_NORMAL  . '" selected="selected">' . $lang->get('acppg_gtype_static') . '</option>
                <option value="' . PAGE_GRP_TAGGED  . '">' . $lang->get('acppg_gtype_tagged') . '</option>
                <option value="' . PAGE_GRP_CATLINK . '">' . $lang->get('acppg_gtype_catlink') . '</option>
                <option value="' . PAGE_GRP_REGEX   . '">' . $lang->get('acppg_gtype_regex_long') . '</option>
              </select>
              </td>
            </tr>';
            
      // Titles
      echo '<tr>
              <th colspan="2">
                <span id="pg_create_title_normal">
                  ' . $lang->get('acppg_gtype_static') . '
                </span>
                <span id="pg_create_title_tagged">
                  ' . $lang->get('acppg_gtype_tagged') . '
                </span>
                <span id="pg_create_title_catlink">
                  ' . $lang->get('acppg_gtype_catlink') . '
                </span>
                <span id="pg_create_title_regex">
                  ' . $lang->get('acppg_gtype_regex') . '
                </span>
              </th>
            </tr>';
      
      echo '<tr>
              <td class="row2">
                <div id="pg_create_normal_1">
                  ' . $lang->get('acppg_field_member_pages') . '<br />
                  <small>' . $lang->get('acppg_field_member_pages_hint') . '</small>
                </div>
                <div id="pg_create_catlink_1">
                  ' . $lang->get('acppg_field_target_category') . '<br />
                  <small>' . $lang->get('acppg_field_target_category_hint') . '</small>
                </div>
                <div id="pg_create_tagged_1">
                  ' . $lang->get('acppg_field_target_tag') . '
                </div>
                <div id="pg_create_regex_1">
                  ' . $lang->get('acppg_field_target_regex') . '<br />
                  <small>' . $lang->get('acppg_field_target_regex_hint') . '</small>
              </td>';
            
      echo '  <td class="row1">
                <div id="pg_create_normal_2" />
                  <input type="text" style="margin-top: 3px;" name="member_page_0" id="pg_create_member_0" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                  <input type="text" style="margin-top: 3px;" name="member_page_1" id="pg_create_member_1" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                  <input type="text" style="margin-top: 3px;" name="member_page_2" id="pg_create_member_2" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                  <input type="text" style="margin-top: 3px;" name="member_page_3" id="pg_create_member_3" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                  <input type="text" style="margin-top: 3px;" name="member_page_4" id="pg_create_member_4" onkeyup="return ajaxPageNameComplete(this);" size="30" /><br />
                  <input type="button" onclick="pg_create_more_fields(); return false;" style="margin-top: 5px;" value="&nbsp;&nbsp;+&nbsp;&nbsp;" />
                </div>
                <div id="pg_create_tagged_2">
                  <input type="text" name="member_tag" size="30" />
                </div>
                <div id="pg_create_catlink_2">
                  ' . $catlist . '
                </div>
                <div id="pg_create_regex_2">
                  <input type="text" name="regex" size="60" /> 
                </div>
              </td>
            </tr>';
            
      // Submit button
      echo '<tr>
              <th class="subhead" colspan="2"><input type="submit" name="action[create_stage2]" value="' . $lang->get('acppg_btn_create_finish') . '" style="font-weight: bold;" /> <input type="submit" name="action[noop]" value="' . $lang->get('etc_cancel') . '" style="font-weight: normal;" /></th>
            </tr>';
            
      echo '</table>
            </div>';
      
      echo '</form>';
      return;
    }
    else if ( isset($_POST['action']['del']) )
    {
      // Confirmation to delete a group (this is really only a stub)
      
      $delete_id = array_keys($_POST['action']['del']);
      $delete_id = intval($delete_id[0]);
      
      if ( !empty($delete_id) )
      {
        echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">' . "\n";
        echo '<input type="hidden" name="delete_id" value="' . $delete_id . '" />' . "\n";
        echo '<div class="tblholder">' . "\n";
        echo '  <table border="0" cellspacing="1" cellpadding="4">' . "\n";
        echo '    <tr><th>' . $lang->get('acppg_th_delete_confirm') . '</th></tr>' . "\n";
        echo '    <tr><td class="row2" style="text-align: center; padding: 20px 0;">' . $lang->get('acppg_msg_delete_confirm') . '</td></tr>' . "\n";
        echo '    <tr><td class="row1" style="text-align: center;">' . "\n";
        echo '        <input type="submit" name="action[del_confirm]" value="' . $lang->get('acppg_btn_delete_confirm') . '" style="font-weight: bold;" />' . "\n";
        echo '        <input type="submit" name="action[noop]" value="' . $lang->get('etc_cancel') . '" style="font-weight: normal;" />' . "\n";
        echo '        </td></tr>' . "\n";
        echo '  </table>' . "\n";
        echo '</form>' . "\n";
        
        return;
      }
    }
    else if ( isset($_POST['action']['del_confirm']) )
    {
      $delete_id = intval($_POST['delete_id']);
      if ( empty($delete_id) )
      {
        echo 'Hack attempt';
        return;
      }
      // Obtain group name
      $q = $db->sql_query('SELECT pg_name FROM '.table_prefix.'page_groups WHERE pg_id=' . $delete_id . ';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() < 1 )
      {
        echo 'Page group dun exist.';
        return;
      }
      $row = $db->fetchrow();
      $db->free_result();
      $pg_name = $row['pg_name'];
      unset($row);
      // Delete the group
      $q = $db->sql_query('DELETE FROM '.table_prefix.'page_groups WHERE pg_id=' . $delete_id . ';');
      if ( !$q )
        $db->_die();
      $q = $db->sql_query('DELETE FROM '.table_prefix.'page_group_members WHERE pg_id=' . $delete_id . ';');
      if ( !$q )
        $db->_die();
      
      $del_msg = $lang->get('acppg_msg_delete_success', array('pg_name' => htmlspecialchars($pg_name)));
      echo "<div class=\"info-box\">$del_msg</div>";
    }
    else if ( isset($_POST['action']['edit']) && !isset($_POST['action']['noop']) )
    {
      if ( isset($_POST['action']['edit_save']) )
      {
      }
     
      if ( isset($_POST['action']['edit']['add_page']) && isset($_GET['src']) && $_GET['src'] == 'ajax' )
      {
        $return = array('successful' => false);
        
        //
        // Add the specified page to the group
        //
        
        // Get ID of the group
        $edit_id = intval($_POST['pg_id']);
        if ( !$edit_id )
        {
          $return = array('mode' => 'error', 'text' => 'Hack attempt');
          echo enano_json_encode($return);
          return;
        }
        
        // Run some validation - check that page exists and that it's not already in the group
        $page = $_POST['new_page'];
        if ( empty($page) )
        {
          $return = array('mode' => 'error', 'text' => $lang->get('acppg_err_ajaxadd_need_title'));
          echo enano_json_encode($return);
          return;
        }
        
        /*
        // We're gonna allow adding nonexistent pages for now
        if ( !isPage($page) )
        {
          $return = array('mode' => 'error', 'text' => 'The page you are trying to add (' . htmlspecialchars($page) . ') does not exist.');
          echo enano_json_encode($return);
          return;
        }
        */
        
        list($page_id, $namespace) = RenderMan::strToPageID($page);
        $page_id = sanitize_page_id($page_id);
        
        if ( !isset($paths->namespace[$namespace]) )
        {
          $return = array('mode' => 'error', 'text' => 'Invalid namespace return from RenderMan::strToPageID()');
          echo enano_json_encode($return);
          return;
        }
        
        $q = $db->sql_query('SELECT "x" FROM '.table_prefix.'page_group_members WHERE pg_id=' . $edit_id . ' AND page_id=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $namespace . '\';');
        if ( !$q )
        {
          $return = array('mode' => 'error', 'text' => $db->get_error());
          echo enano_json_encode($return);
          return;
        }
        if ( $db->numrows() > 0 )
        {
          $return = array('mode' => 'error', 'text' => $lang->get('acppg_err_ajaxadd_already_in'));
          echo enano_json_encode($return);
          return;
        }
        
        $q = $db->sql_query('INSERT INTO '.table_prefix.'page_group_members(pg_id, page_id, namespace) VALUES(' . $edit_id . ', \'' . $db->escape($page_id) . '\', \'' . $namespace . '\');');
        if ( !$q )
        {
          $return = array('mode' => 'error', 'text' => $db->get_error());
          echo enano_json_encode($return);
          return;
        }
        
        $title = "($namespace) " . get_page_title($paths->nslist[$namespace] . $page_id);
        
        $return = array('mode' => 'info', 'text' => $lang->get('acppg_ajaxadd_success'), 'successful' => true, 'title' => $title, 'member_id' => $db->insert_id());
        
        echo enano_json_encode($return);
        return;
      }
      
      if ( isset($_POST['action']['edit_save']) && isset($_POST['pg_name']) )
      {
        $edit_id = $_POST['action']['edit'];
        $edit_id = intval($edit_id);
        if ( !empty($edit_id) )
        {
          // Update group name
          $new_name = $_POST['pg_name'];
          if ( empty($new_name) )
          {
            echo '<div class="error-box">' . $lang->get('acppg_err_save_need_name') . '</div>';
          }
          else
          {
            $q = $db->sql_query('SELECT pg_name FROM '.table_prefix.'page_groups WHERE pg_id=' . $edit_id . ';');
            if ( !$q )
              $db->_die();
            $row = $db->fetchrow();
            $db->free_result();
            if ( $new_name != $row['pg_name'] )
            {
              $new_name = $db->escape(trim($new_name));
              $q = $db->sql_query('UPDATE '.table_prefix.'page_groups SET pg_name=\'' . $new_name . '\' WHERE pg_id=' . $edit_id . ';');
              if ( !$q )
                $db->_die();
              else
                echo '<div class="info-box">' . $lang->get('acppg_msg_save_name_updated') . '</div>';
            }
            if ( $_POST['pg_type'] == PAGE_GRP_TAGGED )
            {
              $target = $_POST['pg_target'];
              $target = sanitize_tag($target);
              if ( empty($target) )
              {
                echo '<div class="error-box">' . $lang->get('acppg_err_save_need_tag') . '</div>';
              }
              else
              {
                $target = $db->escape($target);
                $q = $db->sql_query('UPDATE '.table_prefix.'page_groups SET pg_target=\'' . $target . '\' WHERE pg_id=' . $edit_id . ';');
                if ( !$q )
                  $db->_die();
                else
                  echo '<div class="info-box">' . $lang->get('acppg_msg_save_tag_updated') . '</div>';
              }
            }
            else if ( $_POST['pg_type'] == PAGE_GRP_REGEX )
            {
              $target = $_POST['pg_target'];
              if ( empty($target) )
              {
                echo '<div class="error-box">' . $lang->get('acppg_err_save_need_regex') . '</div>';
              }
              else
              {
                $target = $db->escape($target);
                $q = $db->sql_query('UPDATE '.table_prefix.'page_groups SET pg_target=\'' . $target . '\' WHERE pg_id=' . $edit_id . ';');
                if ( !$q )
                  $db->_die();
                else
                  echo '<div class="info-box">' . $lang->get('acppg_msg_save_regex_updated') . '</div>';
              }
            }
            else if ( $_POST['pg_type'] == PAGE_GRP_CATLINK )
            {
              $target = $_POST['pg_target'];
              if ( empty($target) )
              {
                echo '<div class="error-box">' . $lang->get('acppg_err_save_bad_category') . '</div>';
              }
              else
              {
                $target = $db->escape($target);
                $q = $db->sql_query('UPDATE '.table_prefix.'page_groups SET pg_target=\'' . $target . '\' WHERE pg_id=' . $edit_id . ';');
                if ( !$q )
                  $db->_die();
                else
                  echo '<div class="info-box">' . $lang->get('acppg_msg_save_cat_updated') . '</div>';
              }
            }
          }
        }
      }
      else if ( isset($_POST['action']['edit_save']) )
      {
        $edit_id = $_POST['action']['edit'];
        $edit_id = intval($edit_id);
      }
      else
      {
        $edit_id = array_keys($_POST['action']['edit']);
        $edit_id = intval($edit_id[0]);
      }
      
      if ( empty($edit_id) )
      {
        echo 'Hack attempt';
        return;
      }
      
      if ( isset($_POST['action']['edit_save']['do_rm']) && !isset($_POST['pg_name']) )
      {
        $vals = array_keys($_POST['action']['edit_save']['rm']);
        $good = array();
        foreach ( $vals as $id )
        {
          if ( strval(intval($id)) == $id )
            $good[] = $id;
        }
        $subquery = ( count($good) > 0 ) ? 'pg_member_id=' . implode(' OR pg_member_id=', $good) : "'foo'='bar'";
        if ( $subquery == "'foo'='bar'" )
        {
          echo '<div class="warning-box">' . $lang->get('acppg_err_save_no_pages') . '</div>';
        }
        else
        {
          $sql = 'DELETE FROM '.table_prefix."page_group_members WHERE ( $subquery ) AND pg_id=$edit_id;";
          if ( !$db->sql_query($sql) )
          {
            $db->_die();
          }
          echo '<div class="info-box">' . $lang->get('acppg_msg_save_pages_deleted') . '</div>';
        }
      }
      
      // Fetch information about page group
      $q = $db->sql_query('SELECT pg_name, pg_type, pg_target FROM '.table_prefix.'page_groups WHERE pg_id=' . $edit_id . ';');
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
      {
        echo 'Bad request - can\'t load page group from database.';
        return;
      }
      
      $row = $db->fetchrow();
      $db->free_result();
      
      echo '<form name="pg_edit_frm" action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
      echo '<input type="hidden" name="action[edit]" value="' . $edit_id . '" />';
      echo '<input type="hidden" name="pg_type" value="' . $row['pg_type'] . '" />';
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4">
                <tr>
                  <th colspan="3">' . $lang->get('acppg_th_editing_group') . ' ' . htmlspecialchars($row['pg_name']) . '</th>
                </tr>';
      // Group name
      
      echo '    <tr>
                  <td class="row2">' . $lang->get('acppg_field_group_name') . '</td>
                  <td class="row1" colspan="2"><input type="text" name="pg_name" value="' . htmlspecialchars($row['pg_name']) . '" size="30" /></td>
                </tr>';
      
      $ajax_page_add = false;
                
      // This is where the going gets tricky.
      // For static groups, we need to have each page listed out with a removal button, and a form to add new pages.
      // For category links, we need a select box with each category in it, and
      // For tag sets, just a text box to enter a new tag.
      
      // You can guess which one I dreaded.
      
      switch ( $row['pg_type'] )
      {
        case PAGE_GRP_NORMAL:
          
          // You have guessed correct.
          // *Sits in chair for 10 minutes listening to the radio in an effort to put off writing the code you see below*
          
          echo '<tr><th colspan="3" class="subhead"><input type="submit" name="action[edit_save]" value="' . $lang->get('acppg_btn_save_name') . '" /></th></tr>';
          echo '</table></div>';
          echo '</form>';
          echo '<form name="pg_static_rm_frm" action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" enctype="multipart/form-data">';
          echo '<input type="hidden" name="action[edit]" value="' . $edit_id . '" />';
          echo '<div class="tblholder">
                  <table border="0" cellspacing="1" cellpadding="4">
                    <tr>
                      <th colspan="3">' . $lang->get('acppg_th_remove_selected') . '</th>
                    </tr>';
          
          $q = $db->sql_query('SELECT m.pg_member_id,m.page_id,m.namespace FROM '.table_prefix.'page_group_members AS m
                                 LEFT JOIN '.table_prefix.'pages AS p
                                   ON ( p.urlname = m.page_id AND p.namespace = m.namespace )
                                 WHERE m.pg_id=' . $edit_id . ';');
          
          if ( !$q )
            $db->_die();
          
          $delim = ceil( $db->numrows($q) / 2 );
          if ( $delim < 5 )
          {
            $delim = 0xFFFFFFFE;
            // stupid hack. I'm XSSing my own code.
            $colspan = '2" id="pg_edit_tackon2me';
          }
          else
          {
            $colspan = "1";
          }
          
          echo '<tr><td class="row2" rowspan="2">' . $lang->get('acppg_field_remove') . '</td><td class="row1" colspan="' . $colspan . '">';
          $i = 0;
          
          while ( $row = $db->fetchrow($q) )
          {
            $i++;
            if ( $i == $delim )
            {
              echo '</td><td class="row1" id="pg_edit_tackon2me">';
            }
            $page_name = '(' . $row['namespace'] . ') ' . get_page_title($paths->nslist[$row['namespace']] . $row['page_id']);
            echo '<label><input type="checkbox" name="action[edit_save][rm][' . $row['pg_member_id'] . ']" /> ' . htmlspecialchars($page_name) . '</label><br />';
          }
          
          echo '</td></tr>';
          echo '<tr><th colspan="2" class="subhead" style="width: 70%;"><input type="submit" name="action[edit_save][do_rm]" value="' . $lang->get('acppg_btn_do_remove') . '" /></th></tr>';
          
          // More javascript magic!
          ?>
          <script type="text/javascript">
            var __pg_edit_submitAuthorized = true;
            var __ol_pg_edit_setup = function()
            {
              var input = document.getElementById('inptext_pg_add_member');
              input.onkeyup = function(e) { ajaxPageNameComplete(this); };
              <?php
              // stupid jEdit hack
              echo "input.onkeypress = function(e) { if ( e.keyCode == 13 ) { setTimeout('__pg_edit_ajaxadd(document.getElementById(\'' + this.id + '\'));', 500); } };";
              ?>
            }
            addOnloadHook(__ol_pg_edit_setup);
            var __pg_edit_objcache = false;
            function __pg_edit_ajaxadd(obj)
            {
              if ( __pg_edit_objcache )
                return false;
              __pg_edit_objcache = obj;
              
              if ( obj.nextSibling )
              {
                if ( obj.nextSibling.tagName == 'DIV' )
                {
                  obj.parentNode.removeChild(obj.nextSibling);
                }
              }
              
              // set width on parent, to prevent wrapping of ajax loading image
              var w = $(obj).Width();
              w = w + 24;
              obj.parentNode.style.width = w + 'px';
              
              // append the ajaxy loading image
              var img = document.createElement('img');
              img.src = scriptPath + '/images/loading.gif';
              img.style.marginLeft = '4px';
              insertAfter(obj.parentNode, img, obj);
              
              var url = makeUrlNS('Admin', 'PageGroups', 'src=ajax');
              var page_add = escape(obj.value);
              var pg_id = document.forms.pg_edit_frm['action[edit]'].value;
              ajaxPost(url, 'action[edit][add_page]=&pg_id=' + pg_id + '&new_page=' + page_add, function()
                {
                  if ( ajax.readyState == 4 )
                  {
                    var obj = __pg_edit_objcache;
                    __pg_edit_objcache = false;
                    
                    // kill the loading graphic
                    obj.parentNode.removeChild(obj.nextSibling);
                    
                    var resptext = String(ajax.responseText + '');
                    if ( resptext.substr(0, 1) != '{' )
                    {
                      // This ain't JSON baby.
                      alert('Invalid JSON response:\n' + resptext);
                      return false;
                    }
                    var json = parseJSON(resptext);
                    
                    var div = document.createElement('div');
                    if ( json.mode == 'info' )
                    {
                      div.className = 'info-box-mini';
                    }
                    else if ( json.mode == 'error' )
                    {
                      div.className = 'error-box-mini';
                    }
                    div.appendChild(document.createTextNode(json.text));
                    insertAfter(obj.parentNode, div, obj);
                    
                    if ( json.successful )
                    {
                      var td = document.getElementById('pg_edit_tackon2me');
                      var lbl = document.createElement('label');
                      var check = document.createElement('input');
                      check.type = 'checkbox';
                      check.name = 'action[edit_save][rm][' + json.member_id + ']';
                      lbl.appendChild(check);
                      lbl.appendChild(document.createTextNode(' ' + json.title));
                      td.appendChild(lbl);
                      td.appendChild(document.createElement('br'));
                    }
                    
                  }
                });
            }
          </script>
          <?php
          
          $ajax_page_add = true;
          
          break;
        case PAGE_GRP_TAGGED:
          echo '<tr>
                  <td class="row2">
                    ' . $lang->get('acppg_field_target_tag') . '
                  </td>
                  <td class="row1">
                    <input type="text" name="pg_target" value="' . htmlspecialchars($row['pg_target']) . '" size="30" />
                  </td>
                </tr>';
          break;
        case PAGE_GRP_REGEX:
          echo '<tr>
                  <td class="row2">
                    ' . $lang->get('acppg_field_target_regex') . '<br />
                    <small>' . $lang->get('acppg_field_target_regex_hint') . '</small>
                  </td>
                  <td class="row1">
                    <input type="text" name="pg_target" value="' . htmlspecialchars($row['pg_target']) . '" size="30" />
                  </td>
                </tr>';
          break;
        case PAGE_GRP_CATLINK:
          
          // Build category list
          $q = $db->sql_query('SELECT name,urlname FROM '.table_prefix.'pages WHERE namespace=\'Category\';');
          if ( !$q )
            $db->_die();
          
          if ( $db->numrows() < 1 )
          {
            $catlist = 'There aren\'t any categories on this site.';
          }
          else
          {
            $catlist = '<select name="pg_target">';
            while ( $catrow = $db->fetchrow() )
            {
              $selected = ( $catrow['urlname'] == $row['pg_target'] ) ? ' selected="selected"' : '';
              $catlist .= '<option value="' . htmlspecialchars($catrow['urlname']) . '"' . $selected . '>' . htmlspecialchars($catrow['name']) . '</option>';
            }
            $catlist .= '</select>';
          }
          
          echo '<tr>
                  <td class="row2">
                    ' . $lang->get('acppg_field_target_category') . '<br />
                    <small>' . $lang->get('acppg_field_target_category_hint2') . '</small>
                  </td>
                  <td class="row1">
                    ' . $catlist . '
                  </td>
                </tr>';
          
          break;
      }
      
      if ( $ajax_page_add )
      {
        echo '<tr><th colspan="3"><input type="submit" name="action[noop]" value="' . $lang->get('acppg_btn_cancel_all') . '" /></th></tr>';
      }
      else
      {
        echo '<tr><th colspan="3" class="subhead">
                <input type="submit" name="action[edit_save]" value="' . $lang->get('acppg_btn_save_update') . '" />
                <input type="submit" name="action[noop]" value="' . $lang->get('acppg_btn_cancel_all') . '" />
              </th></tr>';
      }
      
      echo '  </table>
            </div>';
      echo '</form>';
      
      if ( $ajax_page_add )
      {
        // This needs to be outside of the form.
        echo '<div class="tblholder"><table border="0" cellspacing="1" cellpadding="4"><tr>';
        echo '<th colspan="2">' . $lang->get('acppg_th_onthefly') . '</th></tr>';
        echo '<tr>';
        // Add pages AJAX form
        echo '<td class="row2">' . $lang->get('acppg_field_add_page') . '<br /><small>' . $lang->get('acppg_field_add_page_hint') . '</small></td>';
        echo '<td class="row1"><input type="text" size="30" name="pg_add_member" id="inptext_pg_add_member" /></td>';
        echo '</tr></table></div>';
      }
      
      return;
    }
    else if ( isset($_POST['action']['noop']) )
    {
      // Do nothing - skip to main form (noop is usually invoked by a cancel button in a form above)
    }
    else
    {
      echo '<div class="error-box">Invalid format of $_POST[action].</div>';
    }
  }
  // No action defined - show default menu
  
  echo '<h2>' . $lang->get('acppg_heading_main') . '</h2>';
  echo '<p>' . $lang->get('acppg_hint_intro') . '</p>';
  
  $q = $db->sql_query('SELECT pg_id, pg_type, pg_name, pg_target FROM '.table_prefix.'page_groups;');
  if ( !$q )
    $db->_die();

  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  
  echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th>' . $lang->get('acppg_col_group_name') . '</th>
              <th>' . $lang->get('acppg_col_type') . '</th>
              <th>' . $lang->get('acppg_col_target') . '</th>
              <th colspan="2">' . $lang->get('acppg_col_actions') . '</th>
            </tr>';
  
  if ( $row = $db->fetchrow($q) )
  {
    do
    {
      $name = htmlspecialchars($row['pg_name']);
      $type = 'Invalid';
      switch ( $row['pg_type'] )
      {
        case PAGE_GRP_CATLINK:
          $type = $lang->get('acppg_gtype_catlink');
          break;
        case PAGE_GRP_TAGGED:
          $type = $lang->get('acppg_gtype_tagged');
          break;
        case PAGE_GRP_NORMAL:
          $type = $lang->get('acppg_gtype_static');
          break;
        case PAGE_GRP_REGEX:
          $type = $lang->get('acppg_gtype_regex');
          break;
      }
      $target = '';
      if ( $row['pg_type'] == PAGE_GRP_TAGGED )
      {
        $target = $lang->get('acppg_lbl_tag') . ' ' . htmlspecialchars($row['pg_target']);
      }
      else if ( $row['pg_type'] == PAGE_GRP_CATLINK )
      {
        $target = $lang->get('acppg_lbl_category') . ' ' . htmlspecialchars(get_page_title($paths->nslist['Category'] . sanitize_page_id($row['pg_target'])));
      }
      else if ( $row['pg_type'] == PAGE_GRP_REGEX )
      {
        $target = $lang->get('acppg_lbl_regex') . ' <tt>' . htmlspecialchars($row['pg_target']) . '</tt>';
      }
      $btn_edit = '<input type="submit" name="action[edit][' . $row['pg_id'] . ']" value="' . $lang->get('acppg_btn_edit') . '" />';
      $btn_del = '<input type="submit" name="action[del][' . $row['pg_id'] . ']" value="' . $lang->get('acppg_btn_delete') . '" />';
      echo "<tr>
              <td class=\"row1\">$name</td>
              <td class=\"row2\">$type</td>
              <td class=\"row1\">$target</td>
              <td class=\"row3\" style=\"text-align: center;\">$btn_edit</td>
              <td class=\"row3\" style=\"text-align: center;\">$btn_del</td>
            </tr>";
    }
    while ( $row = $db->fetchrow($q) );
  }
  else
  {
    echo '  <tr><td class="row3" colspan="5" style="text-align: center;">' . $lang->get('acppg_msg_no_groups') . '</td></tr>';
  }
  
  echo '    <tr>
              <th class="subhead" colspan="5">
                <input type="submit" name="action[create]" value="' . $lang->get('acppg_btn_create_new') . '" />
              </th>
            </tr>';
  
  echo '  </table>
        </div>';
        
  echo '</form>';          
    
}

?>
