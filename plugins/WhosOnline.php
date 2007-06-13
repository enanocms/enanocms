<?php
/*
Plugin Name: Who's Online
Plugin URI: javascript: // No URL yet, stay tuned!
Description: This plugin tracks who is currently online. 3 queries per page request. This plugin works ONLY with MySQL and will likely be difficult to port because it uses unique indices and the REPLACE command.
Author: Dan Fuhry
Version: 0.1
Author URI: http://www.enanocms.org/
*/

/*
 * Who's Online plugin for Enano
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

global $whos_online;
$whos_online = Array('not_yet_initialized');

// First things first - create the table if needed
$ver = getConfig('whos_online_version');
if($ver != '0.1')
{
  if(!
    $db->sql_query('DROP TABLE IF EXISTS '.table_prefix.'online;')
    ) $db->_die('Could not clean out old who\'s-online table');
  // The key on username allows the REPLACE command later, to save queries
  if(!$db->sql_query('CREATE TABLE '.table_prefix.'online(
        entry_id int(12) UNSIGNED NOT NULL auto_increment,
        user_id int(12) NOT NULL,
        username varchar(63) NOT NULL,
        last_load int(12) NOT NULL,
        PRIMARY KEY ( entry_id ),
        KEY ( username )
      );')
    ) $db->_die('Could not create new who\'s-online table');
  if(!$db->sql_query('CREATE UNIQUE INDEX '.table_prefix.'onluser ON '.table_prefix.'online(username);'))
    $db->_die('Could not create index on username column.');
  setConfig('whos_online_version', '0.1');
}

$plugins->attachHook('session_started', '__WhosOnline_UserCount();');
$plugins->attachHook('login_success', '__WhosOnline_logonhandler();');
$plugins->attachHook('logout_success', '__WhosOnline_logoffhandler($ou, $oid, $level);');

function __WhosOnline_UserCount()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $whos_online;
  $whos_online = Array();
  $whos_online['users'] = Array();
  $whos_online['guests'] = Array();
  $q = $db->sql_query('REPLACE INTO '.table_prefix.'online SET user_id='.$session->user_id.',username=\''.$db->escape($session->username).'\',last_load='.time().';'); if(!$q) $db->_die('');
  $q = $db->sql_query('DELETE FROM '.table_prefix.'online WHERE last_load<'.( time() - 60*60*24 ).';'); if(!$q) $db->_die('');
  $q = $db->sql_query('SELECT o.username,o.user_id,u.user_level FROM '.table_prefix.'online AS o
    LEFT JOIN '.table_prefix.'users AS u
      ON u.user_id=o.user_id
    WHERE last_load>'.( time() - 60*5 - 1 ).' ORDER BY username ASC'); if(!$q) $db->_die('');
  $num_guests = 0;
  $num_users = 0;
  $users = Array();
  while ( $row = $db->fetchrow() )
  {
    ( $row['user_id'] == 1 ) ? $num_guests++ : $num_users++;
    if($row['user_id'] > 1)
    {
      switch($row['user_level'])
      {
        case USER_LEVEL_MEMBER:
        default:
          $color = '303030';
          $weight = 'normal';
          break;
        case USER_LEVEL_MOD:
          $color = '00AA00';
          $weight = 'bold';
          break;
        case USER_LEVEL_ADMIN:
          $color = 'AA0000';
          $weight = 'bold';
          break;
      }
      $users[] = "<a href='".makeUrlNS('User', str_replace(' ', '_', $row['username']))."' style='color: #$color; font-weight: $weight'>{$row['username']}</a>";
      $whos_online['users'][] = $row['username'];
    }
    else
    {
      $whos_online['guests'][] = $row['username'];
    }
  }
  $total = $num_guests + $num_users;
  $ms = ( $num_users == 1 ) ? '' : 's';
  $gs = ( $num_guests == 1 ) ? '' : 's';
  $ts = ( $total == 1 ) ? '' : 's';
  $is_are = ( $total == 1 ) ? 'is' : 'are';
  $users = implode(', ', $users);
  $online_main = ( $num_users > 0 ) ? "<br />
               Users online right now:
               <div style='max-height: 100px; clip: rect(0px,auto,auto,0px); overflow: auto;'>
               $users
               </div>
               Legend:<br /><span style='color: #00AA00; font-weight: bold;'>Moderators</span> :: <span style='color: #AA0000; font-weight: bold;'>Administrators</span>"
               : '';
  $html = "<div style='padding: 5px;'>
             <small>
               There $is_are <b>$total</b> user$ts online :: <b>$num_guests</b> guest$gs and <b>$num_users</b> member$ms
               $online_main
             </small>
           </div>";
  $template->sidebar_widget('Who\'s Online', $html);
}

function __WhosOnline_logonhandler()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $q = $db->sql_query('DELETE FROM '.table_prefix.'online WHERE user_id=1 AND username=\''.$db->escape($_SERVER['REMOTE_ADDR']).'\';');
  if(!$q)
    echo $db->get_error();
  if(!$session->theme)
    $session->register_guest_session();
  $template->load_theme($session->theme, $session->style);
  __WhosOnline_UserCount();
}

function __WhosOnline_logoffhandler($username, $user_id, $level)
{
  if($level <= USER_LEVEL_MEMBER)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $q = $db->sql_query('DELETE FROM '.table_prefix.'online WHERE user_id=\''.intval($user_id).'\' AND username=\''.$db->escape($username).'\';');
    if(!$q)
      echo $db->get_error();
    $q = $db->sql_query('REPLACE INTO '.table_prefix.'online SET user_id=1,username=\''.$db->escape($_SERVER['REMOTE_ADDR']).'\',last_load='.time().';'); if(!$q) $db->_die('');
    if(!$q)
      echo $db->get_error();
  }
}
 
?>
