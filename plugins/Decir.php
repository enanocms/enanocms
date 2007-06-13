<?php
/*
Plugin Name: Decir
Plugin URI: javascript: // No URL yet, stay tuned!
Description: Decir is an advanced bulletin board system (forum) for Enano. 
Author: Dan Fuhry
Version: 0.1
Author URI: http://www.enanocms.org/
*/

/*
 * Decir
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

define('ENANO_DECIR_VERSION', '0.1');
define('DECIR_ROOT', ENANO_ROOT . '/decir');
 
$plugins->attachHook('acl_rule_init', 'decir_early_init($this, $session);');
$plugins->attachHook('base_classes_initted', '
    $paths->add_page(Array(
      \'name\'=>\'Forum\',
      \'urlname\'=>\'Forum\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
  ');

function decir_early_init(&$paths, &$session)
{
  $paths->addAdminNode('Decir forum configuration', 'General settings', 'DecirGeneral');
  $paths->nslist['DecirForum']  = $paths->nslist['Special'] . 'Forum/ViewForum/';
  $paths->nslist['DecirPost']   = $paths->nslist['Special'] . 'Forum/Post/';
  $paths->nslist['DecirTopic']  = $paths->nslist['Special'] . 'Forum/Topic/';
  
  $session->register_acl_type('decir_see_forum',  AUTH_ALLOW, 'See forum in index', Array('read'),             'DecirForum');
  $session->register_acl_type('decir_view_forum', AUTH_ALLOW, 'View forum',         Array('decir_see_forum'),  'DecirForum');
  $session->register_acl_type('decir_post',       AUTH_ALLOW, 'Post new topics',    Array('decir_view_forum'), 'DecirForum');
  $session->register_acl_type('decir_reply',      AUTH_ALLOW, 'Reply to topics',    Array('decir_post'),       'DecirTopic');
}

function page_Special_Forum()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( getConfig('decir_version') != ENANO_DECIR_VERSION || isset($_POST['do_install_finish']) )
  {
    require(DECIR_ROOT . '/install.php');
  }
  
  $act = strtolower( ( $n = $paths->getParam(0) ) ? $n : 'Index' );
  
  $curdir = getcwd();
  chdir(DECIR_ROOT);
  
  switch($act)
  {
    case 'index':
    default:
      require('forum_index.php');
      break;
    case 'viewforum':
      require('viewforum.php');
      break;
    case 'topic':
    case 'post':
    case 'viewtopic':
      require('viewtopic.php');
      break;
    case 'new':
      require('posting.php');
      break;
  }
  
  chdir($curdir);
  
}

function page_Admin_DecirGeneral()
{
  global $db, $session, $paths, $template, $plugins; if($session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN) { header('Location: '.makeUrl($paths->nslist['Special'].'Administration'.urlSeparator.'noheaders')); die('Hacking attempt'); }
  echo 'Hello world!';
}

?>
