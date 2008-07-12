<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function page_Admin_UserRanks()
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
  
  // This should be a constant somewhere
  $protected_ranks = array(
      RANK_ID_MEMBER,
      RANK_ID_MOD,
      RANK_ID_ADMIN,
      RANK_ID_GUEST
    );
  
  if ( $paths->getParam(0) == 'action.json' )
  {
    // ajax call, try to decode json request
    header('Content-type: application/json');
    
    if ( !isset($_POST['r']) )
    {
      echo enano_json_encode(array(
          'mode' => 'error',
          'error' => 'Missing JSON request payload'
        ));
      return true;
    }
    try
    {
      $request = enano_json_decode($_POST['r']);
    }
    catch ( Exception $e )
    {
      echo enano_json_encode(array(
          'mode' => 'error',
          'error' => 'Invalid JSON request payload'
        ));
      return true;
    }
    
    if ( !isset($request['mode']) )
    {
      echo enano_json_encode(array(
          'mode' => 'error',
          'error' => 'JSON request payload does not contain required parameter "mode"'
        ));
      return true;
    }
    
    // we've got it
    switch ( $request['mode'] )
    {
      case 'get_rank':
        // easy enough, get a rank from the DB
        $rank_id = intval(@$request['rank_id']);
        if ( empty($rank_id) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => 'Missing rank ID'
            ));
          return true;
        }
        // query and fetch
        $q = $db->sql_query('SELECT rank_id, rank_title, rank_style FROM ' . table_prefix . "ranks WHERE rank_id = $rank_id;");
        if ( !$q || $db->numrows() < 1 )
          $db->die_json();
        
        $row = $db->fetchrow();
        $db->free_result();
        
        // why does mysql do this?
        $row['rank_id'] = intval($row['rank_id']);
        echo enano_json_encode($row);
        break;
      case 'save_rank':
        // easy enough, get a rank from the DB
        $rank_id = intval(@$request['rank_id']);
        // note - an empty rank_style field is permitted
        if ( empty($rank_id) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => 'Missing rank ID'
            ));
          return true;
        }
        
        if ( empty($request['rank_title']) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => $lang->get('acpur_err_missing_rank_title')
            ));
          return true;
        }
        
        // perform update
        $rank_title = $db->escape($request['rank_title']);
        $rank_style = $db->escape(@$request['rank_style']);
        $q = $db->sql_query('UPDATE ' . table_prefix . "ranks SET rank_title = '$rank_title', rank_style = '$rank_style' WHERE rank_id = $rank_id;");
        
        echo enano_json_encode(array(
            'mode' => 'success'
          ));
        break;
      case 'create_rank':
        if ( empty($request['rank_title']) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => $lang->get('acpur_err_missing_rank_title')
            ));
          return true;
        }
        
        $rank_title = $db->escape($request['rank_title']);
        $rank_style = $db->escape(@$request['rank_style']);
        
        // perform insert
        $q = $db->sql_query('INSERT INTO ' . table_prefix . "ranks ( rank_title, rank_style ) VALUES\n"
                          . "  ( '$rank_title', '$rank_style' );");
        if ( !$q )
          $db->die_json();
        
        $rank_id = $db->insert_id();
        if ( !$rank_id )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => 'Refetch of rank ID failed'
            ));
          return true;
        }
        
        echo enano_json_encode(array(
            'mode' => 'success',
            'rank_id' => $rank_id
          ));
        break;
      case 'delete_rank':
        // nuke a rank
        $rank_id = intval(@$request['rank_id']);
        if ( empty($rank_id) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => 'Missing rank ID'
            ));
          return true;
        }
        
        // is this rank protected (e.g. a system rank)?
        if ( in_array($rank_id, $protected_ranks) )
        {
          echo enano_json_encode(array(
              'mode' => 'error',
              'error' => $lang->get('acpur_err_cant_delete_system_rank')
            ));
          return true;
        }
        
        // unset any user and groups that might be using it
        $q = $db->sql_query('UPDATE ' . table_prefix . "users SET user_rank = NULL WHERE user_rank = $rank_id;");
        if ( !$q )
          $db->die_json();
        $q = $db->sql_query('UPDATE ' . table_prefix . "groups SET group_rank = NULL WHERE group_rank = $rank_id;");
        if ( !$q )
          $db->die_json();
        
        // now remove the rank itself
        $q = $db->sql_query('DELETE FROM ' . table_prefix . "ranks WHERE rank_id = $rank_id;");
        if ( !$q )
          $db->_die();
        
        echo enano_json_encode(array(
            'mode' => 'success'
          ));
        break;
      default:
        echo enano_json_encode(array(
          'mode' => 'error',
          'error' => 'Unknown requested operation'
        ));
      return true;
    }
    return true;
  }
  
  // draw initial interface
  // yes, four paragraphs of introduction. Suck it up.
  echo '<h3>' . $lang->get('acpur_heading_main') . '</h3>';
  echo '<p>' . $lang->get('acpur_intro_para1') . '</p>';
  echo '<p>' . $lang->get('acpur_intro_para2') . '</p>';
  echo '<p>' . $lang->get('acpur_intro_para3') . '</p>';
  echo '<p>' . $lang->get('acpur_intro_para4') . '</p>';
  
  // fetch ranks
  $q = $db->sql_query('SELECT rank_id, rank_title, rank_style FROM ' . table_prefix . "ranks ORDER BY rank_title ASC;");
  if ( !$q )
    $db->_die();
  
  echo '<div class="rankadmin-left" id="admin_ranks_container_left">';
  while ( $row = $db->fetchrow() )
  {
    // format rank according to what its users look like
    // rank titles can be stored as language strings, so have the language manager fetch this
    // normally it refetches (which takes time) if a string isn't found, but it won't try to fetch
    // a string that isn't in the category_stringid format
    $rank_title = $lang->get($row['rank_title']);
    // FIXME: make sure htmlspecialchars() is escaping quotes and backslashes
    echo '<a href="#rank_edit:' . $row['rank_id'] . '" onclick="ajaxInitRankEdit(' . $row['rank_id'] . '); return false;" class="rankadmin-editlink" style="' . htmlspecialchars($row['rank_style']) . '" id="rankadmin_editlink_' . $row['rank_id'] . '">' . htmlspecialchars($rank_title) . '</a> ';
  }
  echo '<a href="#rank_create" onclick="ajaxInitRankCreate(); return false;" class="rankadmin-editlink rankadmin-createlink" id="rankadmin_createlink">' . $lang->get('acpur_btn_create_init') . '</a> ';
  echo '</div>';
  
  echo '<div class="rankadmin-right" id="admin_ranks_container_right">';
  echo $lang->get('acpur_msg_select_rank');
  echo '</div>';
  echo '<span class="menuclear"></span>';
}

?>
