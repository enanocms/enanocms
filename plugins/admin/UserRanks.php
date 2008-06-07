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
    // ajax call
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
    echo '<a href="#rank_edit:' . $row['rank_id'] . '" onclick="ajaxInitRankEdit(' . $row['rank_id'] . '); return false;" class="rankadmin-editlink" style="' . htmlspecialchars($row['rank_style']) . '">' . htmlspecialchars($rank_title) . '</a> ';
  }
  echo '</div>';
  
  echo '<div class="rankadmin-right" id="admin_ranks_container_right">';
  echo $lang->get('acpur_msg_select_rank');
  echo '</div>';
  echo '<span class="menuclear"></span>';
}

?>
