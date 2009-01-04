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

// Page editing portal

function page_Admin_PageEditor()
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
  
  echo '<h3>' . $lang->get('acped_heading_main') . '</h3>';
  $show_select = true;
  
  if ( isset($_REQUEST['action']) || isset($_REQUEST['source']) )
  {
    if ( isset($_REQUEST['action']) )
    {
      $act =& $_REQUEST['action'];
      $act = strtolower($act);
    }
    else if ( isset($_REQUEST['source']) && $_REQUEST['source'] == 'ajax' )
    {
      $act = 'select';
    }
    switch ( $act )
    {
      case 'save':
      case 'select':
        // First step is to determine the page ID and namespace
        
        if ( isset($_REQUEST['pid_search']) )
        {
          list($page_id, $namespace) = RenderMan::strToPageID($_REQUEST['page_id']);
          $name = $db->escape(dirtify_page_id($page_id));
          $page_id = $db->escape(sanitize_page_id($page_id));
          $namespace = $db->escape($namespace);
          $name = strtolower($name);
          $page_id = strtolower($page_id);
          $sql = "SELECT * FROM " . table_prefix . "pages WHERE ( " . ENANO_SQLFUNC_LOWERCASE . "(urlname) LIKE '%$page_id%' OR " . ENANO_SQLFUNC_LOWERCASE . "(name) LIKE '%$name%' ) ORDER BY name ASC;";
        }
        else
        {
          // pid_search was not set, assume absolute page ID
          list($page_id, $namespace) = RenderMan::strToPageID($_REQUEST['page_id']);
          $page_id = $db->escape(sanitize_page_id($page_id));
          $namespace = $db->escape($namespace);
          
          $sql = "SELECT * FROM " . table_prefix . "pages WHERE urlname = '$page_id' AND namespace = '$namespace';";
        }
        
        if ( !($q = $db->sql_query($sql)) )
        {
          $db->_die('PageManager selecting dataset for page');
        }
        
        if ( $db->numrows() < 1 )
        {
          echo '<div class="error-box">
                  ' . $lang->get('acped_err_page_not_found') . '
                </div>';
          break;
        }
        
        if ( $db->numrows() > 1 )
        {
          // Ambiguous results
          if ( isset($_REQUEST['pid_search']) )
          {
            echo '<h3>' . $lang->get('acped_msg_results_ambiguous_title') . '</h3>';
            echo '<p>' . $lang->get('acped_msg_results_ambiguous_body') . '</p>';
            echo '<ul>';
            while ( $row = $db->fetchrow($q) )
            {
              echo '<li>';
              $pathskey = $paths->nslist[$row['namespace']] . $row['urlname'];
              $edit_url = makeUrlNS($row['namespace'], $row['urlname']) . '#do:edit';
              $view_url = makeUrlNS($row['namespace'], $row['urlname']);
              $page_name = htmlspecialchars(get_page_title_ns( $row['urlname'], $row['namespace'] ));
              $view_link = $lang->get('acped_ambig_btn_viewpage');
              echo "<a href=\"$edit_url\">$page_name</a> (<a onclick=\"window.open(this.href); return false;\" href=\"$view_url\">$view_link</a>)";
              echo '</li>';
            }
            echo '</ul>';
            $show_select = false;
            break;
          }
          else
          {
            echo '<p>' . $lang->get('acped_err_ambig_absolute') . '</p>';
            break;
          }
        }
        
        // From this point on we can assume that exactly one matching page was found.
        $dataset = $db->fetchrow();
        $page_id = $dataset['urlname'];
        $namespace = $dataset['namespace'];
        $url = makeUrlNS($namespace, $page_id, false, true) . '#do:edit';
        $url = addslashes($url);
        echo '<script type="text/javascript">
                window.location = \'' . $url . '\';
              </script>';
        
        $show_select = false;
        break;
    }
  }
  
  if ( $show_select )
  {
    echo '<p>' . $lang->get('acped_hint') . '</p>';
    
    // Show the search form
    
    $form_action = makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageEditor", true);
    echo "<form action=\"$form_action\" method=\"post\">";
    echo $lang->get('acped_lbl_field_search') . ' ';
    echo $template->pagename_field('page_id') . ' ';
    echo '<input type="hidden" name="action" value="select" />';
    echo '<input type="submit" name="pid_search" value="' . $lang->get('search_btn_search') . '" />';
    echo "</form>";
    
    // Grab all pages from the database and show a list of pages on the site
    
    echo '<h3>' . $lang->get('acped_heading_select_page_from_list') . '</h3>';
    echo '<p>' . $lang->get('acped_hint_select_page_from_list') . '</p>';
    
    $q = $db->sql_query('SELECT COUNT(name) AS num_pages FROM ' . table_prefix . 'pages;');
    if ( !$q )
      $db->_die('PageManager doing initial page count');
    list($num_pages) = $db->fetchrow_num();
    $db->free_result();
    
    $pg_start = ( isset($_GET['offset']) ) ? intval($_GET['offset']) : 0;
    
    $q = $db->sql_unbuffered_query('SELECT urlname, name, namespace, ' . $num_pages . ' AS num_pages, ' . $pg_start . ' AS offset, \'edit\' AS mode FROM ' . table_prefix . 'pages ORDER BY name ASC;');
    if ( !$q )
      $db->_die('PageManager doing main select query for page list');
    
    // Paginate results
    $html = paginate(
        $q,
        '{urlname}',
        $num_pages,
        makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageEditor&offset=%s", false),
        $pg_start,
        99,
        array('urlname' => 'admin_pagemanager_format_listing'),
        '<div class="tblholder" style="height: 300px; clip: rect(0px, auto, auto, 0px); overflow: auto;">
        <table border="0" cellspacing="1" cellpadding="4">',
        '  </table>
         </div>'
      );
    echo $html;
  }
  
}

?>
