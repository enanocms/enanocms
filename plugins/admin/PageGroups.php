<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
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
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    echo '<h3>Error: Not authenticated</h3><p>It looks like your administration session is invalid or you are not authorized to access this administration page. Please <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true) . '">re-authenticate</a> to continue.</p>';
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
            echo '<div class="error-box">Please enter a name for the page group.</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_TAGGED && empty($_POST['member_tag']) )
          {
            echo '<div class="error-box">Please enter a page tag.</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_CATLINK && empty($_POST['member_cat']) )
          {
            echo '<div class="error-box">Please create a category page before linking a page group to a category.</div>';
            return;
          }
          if ( $_POST['group_type'] == PAGE_GRP_NORMAL && empty($_POST['member_page_0']) )
          {
            echo '<div class="error-box">Please specify at least one page to place in this group.</div>';
            return;
          }
          if ( $_POST['group_type'] != PAGE_GRP_TAGGED && $_POST['group_type'] != PAGE_GRP_CATLINK && $_POST['group_type'] != PAGE_GRP_NORMAL )
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
          }
          echo '<div class="info-box">The page group "' . htmlspecialchars($_POST['pg_name']) . '" has been created.</div>';
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
          if ( selection != pg_normal && selection != pg_tagged && selection != pg_catlink )
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
        $catlist = 'There aren\'t any categories on this site.';
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
      
      echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized || !__pg_edit_submitAuthorized) return false;" enctype="multipart/form-data">';
      
      echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
              <th colspan="2">Create page group</th>
              </tr>';
      
      // Name
      echo '<tr>
              <td class="row2">
              Group name:<br />
              <small>This should be short, descriptive, and human-readable.</small>
              </td>
              <td class="row1">
              <input type="text" name="pg_name" size="30" />
              </td>
            </tr>';
            
      // Group type
      echo '<tr>
              <td class="row2">
              Group type:
              </td>
              <td class="row1">
              <select name="group_type" onchange="pg_create_typeset(this);">
                <option value="' . PAGE_GRP_NORMAL  . '" selected="selected">Static group of pages</option>
                <option value="' . PAGE_GRP_TAGGED  . '">Group of pages with one tag</option>
                <option value="' . PAGE_GRP_CATLINK . '">Link to category</option>
              </select>
              </td>
            </tr>';
            
      // Titles
      echo '<tr>
              <th colspan="2">
                <span id="pg_create_title_normal">
                  Static group of pages
                </span>
                <span id="pg_create_title_tagged">
                  Group of commonly tagged pages
                </span>
                <span id="pg_create_title_catlink">
                  Mirror a category
                </span>
              </th>
            </tr>';
      
      echo '<tr>
              <td class="row2">
                <div id="pg_create_normal_1">
                  Member pages:<br />
                  <small>Click the "plus" button to add more fields.</small>
                </div>
                <div id="pg_create_catlink_1">
                  Include pages in this category:<br />
                  <small>Pages in subcategories are <u>not</u> included, however subcategory pages themselves are.</small>
                </div>
                <div id="pg_create_tagged_1">
                  Include pages with this tag:
                </div>
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
              </td>
            </tr>';
            
      // Submit button
      echo '<tr>
              <th class="subhead" colspan="2"><input type="submit" name="action[create_stage2]" value="Create page group" style="font-weight: bold;" /> <input type="submit" name="action[noop]" value="Cancel" style="font-weight: normal;" /></th>
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
        echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
        echo '<input type="hidden" name="delete_id" value="' . $delete_id . '" />';
        echo '<div class="tblholder">';
        echo '  <table border="0" cellspacing="1" cellpadding="4">';
        echo '    <tr><th>Confirm deletion</th></tr>';
        echo '    <tr><td class="row2" style="text-align: center; padding: 20px 0;">Are you sure you want to delete this page group?</td></tr>';
        echo '    <tr><td class="row1" style="text-align: center;">';
        echo '        <input type="submit" name="action[del_confirm]" value="Yes, delete group" style="font-weight: bold;" />';
        echo '        <input type="submit" name="action[noop]" value="Cancel" style="font-weight: normal;" />';
        echo '        </td></tr>';
        echo '  </table>';
        echo '</form>';
        
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
      echo "<div class='info-box'>The group ".'"'."$pg_name".'"'." has been deleted.</div>";
    }
    else if ( isset($_POST['action']['edit']) && !isset($_POST['action']['noop']) )
    {
      if ( isset($_POST['action']['edit_save']) )
      {
      }
     
      if ( isset($_POST['action']['edit']['add_page']) && isset($_GET['src']) && $_GET['src'] == 'ajax' )
      {
        $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        $return = array('successful' => false);
        
        //
        // Add the specified page to the group
        //
        
        // Get ID of the group
        $edit_id = intval($_POST['pg_id']);
        if ( !$edit_id )
        {
          $return = array('mode' => 'error', 'text' => 'Hack attempt');
          echo $json->encode($return);
          return;
        }
        
        // Run some validation - check that page exists and that it's not already in the group
        $page = $_POST['new_page'];
        if ( empty($page) )
        {
          $return = array('mode' => 'error', 'text' => 'Please enter a page title.');
          echo $json->encode($return);
          return;
        }
        
        if ( !isPage($page) )
        {
          $return = array('mode' => 'error', 'text' => 'The page you are trying to add (' . htmlspecialchars($page) . ') does not exist.');
          echo $json->encode($return);
          return;
        }
        
        list($page_id, $namespace) = RenderMan::strToPageID($page);
        $page_id = sanitize_page_id($page_id);
        
        $q = $db->sql_query('SELECT "x" FROM '.table_prefix.'page_group_members WHERE pg_id=' . $edit_id . ' AND page_id=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $namespace . '\';');
        if ( !$q )
        {
          $return = array('mode' => 'error', 'text' => $db->get_error());
          echo $json->encode($return);
          return;
        }
        if ( $db->numrows() > 0 )
        {
          $return = array('mode' => 'error', 'text' => 'The page you are trying to add is already in this group.');
          echo $json->encode($return);
          return;
        }
        
        $q = $db->sql_query('INSERT INTO '.table_prefix.'page_group_members(pg_id, page_id, namespace) VALUES(' . $edit_id . ', \'' . $db->escape($page_id) . '\', \'' . $namespace . '\');');
        if ( !$q )
        {
          $return = array('mode' => 'error', 'text' => $db->get_error());
          echo $json->encode($return);
          return;
        }
        
        $title = "($namespace) " . get_page_title($paths->nslist[$namespace] . $page_id);
        
        $return = array('mode' => 'info', 'text' => 'The page has been added to the specified group.', 'successful' => true, 'title' => $title, 'member_id' => $db->insert_id());
        
        echo $json->encode($return);
        return;
      }
      
      if ( isset($_POST['action']['edit_save']) )
      {
        $edit_id = $_POST['action']['edit'];
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
      
      if ( isset($_POST['action']['edit_save']['do_rm']) )
      {
        $vals = array_keys($_POST['action']['edit_save']['rm']);
        $good = array();
        foreach ( $vals as $id )
        {
          if ( strval(intval($id)) == $id )
            $good[] = $id;
        }
        $subquery = ( count($good) > 0 ) ? 'pg_member_id=' . implode(' OR pg_member_id=', $good) : "'foo'='foo'";
        $sql = 'DELETE FROM '.table_prefix."page_group_members WHERE ( $subquery ) AND pg_id=$edit_id;";
        if ( !$db->sql_query($sql) )
        {
          $db->_die();
        }
        echo '<div class="info-box">The requested page group members have been deleted.</div>';
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
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4">
                <tr>
                  <th colspan="3">Editing page group: ' . htmlspecialchars($row['pg_name']) . '</th>
                </tr>';
      // Group name
      
      echo '    <tr>
                  <td class="row2">Group name:</td>
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
          
          echo '<tr><th colspan="3" class="subhead"><input type="submit" name="action[edit_save]" value="Save group name" /></th></tr>';
          
          $q = $db->sql_query('SELECT m.pg_member_id,m.page_id,m.namespace FROM '.table_prefix.'page_group_members AS m
                                 LEFT JOIN '.table_prefix.'pages AS p
                                   ON ( p.urlname = m.page_id AND p.namespace = m.namespace )
                                 WHERE m.pg_id=' . $edit_id . ';');
          
          if ( !$q )
            $db->_die();
          
          $delim = ceil( $db->numrows() / 2 );
          if ( $delim < 5 )
          {
            $delim = 0xFFFFFFFE;
            // stupid hack
            $colspan = '2" id="pg_edit_tackon2me';
          }
          else
          {
            $colspan = "1";
          }
          
          echo '<tr><td class="row2" rowspan="2"><b>Remove</b> pages:</td><td class="row1" colspan="' . $colspan . '">';
          $i = 0;
          
          while ( $row = $db->fetchrow() )
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
          echo '<tr><th colspan="2" class="subhead" style="width: 70%;"><input type="submit" name="action[edit_save][do_rm]" value="Remove selected" /></th></tr>';
          
          // More javascript magic!
          ?>
          <script type="text/javascript">
            var __pg_edit_submitAuthorized = true;
            var __ol_pg_edit_setup = function()
            {
              var input = document.getElementById('inptext_pg_add_member');
              input.onkeyup = function(e) { ajaxPageNameComplete(this); };
              input.onkeypress = function(e) { if ( e.keyCode == 13 ) { setTimeout('__pg_edit_ajaxadd(document.getElementById(\'' + this.id + '\'));', 500); } };
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
          break;
      }
      
      if ( $ajax_page_add )
      {
        echo '<tr><th colspan="3"><input type="submit" name="action[noop]" value="Cancel all changes" /></th></tr>';
      }
      else
      {
        echo '<tr><th colspan="3" class="subhead">
                <input type="submit" name="action[edit_save]" value="Save and update" />
                <input type="submit" name="action[noop]" value="Cancel all changes" />
              </th></tr>';
      }
      
      echo '  </table>
            </div>';
      echo '</form>';
      
      if ( $ajax_page_add )
      {
        // This needs to be outside of the form.
        echo '<div class="tblholder"><table border="0" cellspacing="1" cellpadding="4"><tr>';
        echo '<th colspan="2">On-the-fly tools</th></tr>';
        echo '<tr>';
        // Add pages AJAX form
        echo '<td class="row2">Add page:<br /><small>You can add multiple pages by entering part of a page title, and it will be auto-completed. Press Enter to quickly add the page. This only works if you a really up-to-date browser.</small></td>';
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
  
  echo '<h2>Manage page groups</h2>';
  echo '<p>Enano\'s page grouping system allows you to build sets of pages that can be controlled by a single ACL rule. This makes managing features such as a members-only section of your site a lot easier. If you don\'t use the ACL system, you probably don\'t need to use page groups.</p>';
  
  $q = $db->sql_query('SELECT pg_id, pg_type, pg_name, pg_target FROM '.table_prefix.'page_groups;');
  if ( !$q )
    $db->_die();

  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  
  echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th>Group name</th>
              <th>Type</th>
              <th>Target</th>
              <th colspan="2">Actions</th>
            </tr>';
  
  if ( $row = $db->fetchrow() )
  {
    do
    {
      $name = htmlspecialchars($row['pg_name']);
      $type = 'Invalid';
      switch ( $row['pg_type'] )
      {
        case PAGE_GRP_CATLINK:
          $type = 'Link to category';
          break;
        case PAGE_GRP_TAGGED:
          $type = 'Set of tagged pages';
          break;
        case PAGE_GRP_NORMAL:
          $type = 'Static set of pages';
          break;
      }
      $target = '';
      if ( $row['pg_type'] == PAGE_GRP_TAGGED )
      {
        $target = 'Tag: ' . htmlspecialchars($row['pg_target']);
      }
      else if ( $row['pg_type'] == PAGE_GRP_CATLINK )
      {
        $target = 'Category: ' . htmlspecialchars(get_page_title($paths->nslist['Category'] . sanitize_page_id($row['pg_target'])));
      }
      $btn_edit = '<input type="submit" name="action[edit][' . $row['pg_id'] . ']" value="Edit" />';
      $btn_del = '<input type="submit" name="action[del][' . $row['pg_id'] . ']" value="Delete" />';
      // stupid jEdit bug/hack
      $quot = '"';
      echo "<tr>
              <td class={$quot}row1{$quot}>$name</td>
              <td class={$quot}row2{$quot}>$type</td>
              <td class={$quot}row1{$quot}>$target</td>
              <td class={$quot}row3{$quot} style={$quot}text-align: center;{$quot}>$btn_edit</td>
              <td class={$quot}row3{$quot} style={$quot}text-align: center;{$quot}>$btn_del</td>
            </tr>";
    }
    while ( $row = $db->fetchrow() );
  }
  else
  {
    echo '  <tr><td class="row3" colspan="5" style="text-align: center;">No page groups defined.</td></tr>';
  }
  
  echo '    <tr>
              <th class="subhead" colspan="5">
                <input type="submit" name="action[create]" value="Create new group" />
              </th>
            </tr>';
  
  echo '  </table>
        </div>';
        
  echo '</form>';          
    
}

?>
