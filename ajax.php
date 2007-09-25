<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.2 (Coblynau)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
  // fillusername should be done without the help of the rest of Enano - all we need is the DBAL
  if ( isset($_GET['_mode']) && $_GET['_mode'] == 'fillusername' )
  {
    // setup and load a very basic, specialized instance of the Enano API
    function dc_here($m)     { return false; }
    function dc_dump($a, $g) { return false; }
    function dc_watch($n)    { return false; }
    function dc_start_timer($u) { return false; }
    function dc_stop_timer($m) { return false; }
    // Determine directory (special case for development servers)
    if ( strpos(__FILE__, '/repo/') && file_exists('.enanodev') )
    {
      $filename = str_replace('/repo/', '/', __FILE__);
    }
    else
    {
      $filename = __FILE__;
    }
    define('ENANO_ROOT', dirname($filename));
    require(ENANO_ROOT.'/includes/functions.php');
    require(ENANO_ROOT.'/includes/dbal.php');
    $db = new mysql();
    $db->connect();
    
    // should be connected now
    $name = (isset($_GET['name'])) ? $db->escape($_GET['name']) : false;
    if ( !$name )
    {
      die('userlist = new Array(); errorstring=\'Invalid URI\'');
    }
    $q = $db->sql_query('SELECT username,user_id FROM '.table_prefix.'users WHERE lcase(username) LIKE lcase(\'%'.$name.'%\');');
    if ( !$q )
    {
      die('userlist = new Array(); errorstring=\'MySQL error selecting username data: '.addslashes(mysql_error()).'\'');
    }
    if($db->numrows() < 1)
    {
      die('userlist = new Array(); errorstring=\'No usernames found\';');
    }
    echo 'var errorstring = false; userlist = new Array();';
    $i = 0;
    while($r = $db->fetchrow())
    {
      echo "userlist[$i] = '".addslashes($r['username'])."'; ";
      $i++;
    }
    $db->free_result();
    
    // all done! :-)
    $db->close();
    exit;
  }
 
  require('includes/common.php');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!isset($_GET['_mode'])) die('This script cannot be accessed directly.');
  
  $_ob = '';
  
  switch($_GET['_mode']) {
    case "checkusername":
      echo PageUtils::checkusername($_GET['name']);
      break;
    case "getsource":
      $p = ( isset($_GET['pagepass']) ) ? $_GET['pagepass'] : false;
      echo PageUtils::getsource($paths->page, $p);
      break;
    case "getpage":
      // echo PageUtils::getpage($paths->page, false, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
      $revision_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->cpage['urlname_nons'], $paths->namespace, $revision_id );
      
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? $_REQUEST['pagepass'] : '';
      $page->password = $pagepass;
            
      $page->send();
      break;
    case "savepage":
      $summ = ( isset($_POST['summary']) ) ? $_POST['summary'] : '';
      $minor = isset($_POST['minor']);
      $e = PageUtils::savepage($paths->cpage['urlname_nons'], $paths->namespace, $_POST['text'], $summ, $minor);
      if($e=='good')
      {
        $page = new PageProcessor($paths->cpage['urlname_nons'], $paths->namespace);
        $page->send();
      }
      else
      {
        echo 'Error saving the page: '.$e;
      }
      break;
    case "protect":
      echo PageUtils::protect($paths->cpage['urlname_nons'], $paths->namespace, (int)$_POST['level'], $_POST['reason']);
      break;
    case "histlist":
      echo PageUtils::histlist($paths->cpage['urlname_nons'], $paths->namespace);
      break;
    case "rollback":
      echo PageUtils::rollback( (int)$_GET['id'] );
      break;
    case "comments":
      $comments = new Comments($paths->cpage['urlname_nons'], $paths->namespace);
      if ( isset($_POST['data']) )
      {
        $comments->process_json($_POST['data']);
      }
      else
      {
        die('{ "mode" : "error", "error" : "No input" }');
      }
      break;
    case "rename":
      echo PageUtils::rename($paths->cpage['urlname_nons'], $paths->namespace, $_POST['newtitle']);
      break;
    case "flushlogs":
      echo PageUtils::flushlogs($paths->cpage['urlname_nons'], $paths->namespace);
      break;
    case "deletepage":
      $reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
      if ( empty($reason) )
        die('Please enter a reason for deleting this page.');
      echo PageUtils::deletepage($paths->cpage['urlname_nons'], $paths->namespace, $reason);
      break;
    case "delvote":
      echo PageUtils::delvote($paths->cpage['urlname_nons'], $paths->namespace);
      break;
    case "resetdelvotes":
      echo PageUtils::resetdelvotes($paths->cpage['urlname_nons'], $paths->namespace);
      break;
    case "getstyles":
      echo PageUtils::getstyles($_GET['id']);
      break;
    case "catedit":
      echo PageUtils::catedit($paths->cpage['urlname_nons'], $paths->namespace);
      break;
    case "catsave":
      echo PageUtils::catsave($paths->cpage['urlname_nons'], $paths->namespace, $_POST);
      break;
    case "setwikimode":
      echo PageUtils::setwikimode($paths->cpage['urlname_nons'], $paths->namespace, (int)$_GET['mode']);
      break;
    case "setpass":
      echo PageUtils::setpass($paths->cpage['urlname_nons'], $paths->namespace, $_POST['password']);
      break;
    case "fillusername":
      break;
    case "fillpagename":
      $name = (isset($_GET['name'])) ? $_GET['name'] : false;
      if(!$name) die('userlist = new Array(); namelist = new Array(); errorstring=\'Invalid URI\'');
      $nd = RenderMan::strToPageID($name);
      $c = 0;
      $u = Array();
      $n = Array();
      
      $name = sanitize_page_id($name);
      $name = str_replace('_', ' ', $name);
      
      for($i=0;$i<sizeof($paths->pages)/2;$i++)
      {
        if( ( 
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['name']) ||
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['urlname']) ||
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['urlname_nons']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['name']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['urlname']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['urlname_nons'])
            ) &&
           ( ( $nd[1] != 'Article' && $paths->pages[$i]['namespace'] == $nd[1] ) || $nd[1] == 'Article' )
            && $paths->pages[$i]['visible']
           )
        {
          $c++;
          $u[] = $paths->pages[$i]['name'];
          $n[] = $paths->pages[$i]['urlname'];
        }
      }
      if($c > 0)
      {
        echo 'userlist = new Array(); namelist = new Array(); errorstring = false; '."\n";
        for($i=0;$i<sizeof($u);$i++) // Can't use foreach because we need the value of $i and we need to use both $u and $n
        {
          echo "userlist[$i] = '".addslashes($n[$i])."';\n";
          echo "namelist[$i] = '".addslashes(htmlspecialchars($u[$i]))."';\n";
        }
      } else {
        die('userlist = new Array(); namelist = new Array(); errorstring=\'No page matches found.\'');
      }
      break;
    case "preview":
      echo PageUtils::genPreview($_POST['text']);
      break;
    case "pagediff":
      $id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
      $id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
      if(!$id1 || !$id2) { echo '<p>Invalid request.</p>'; $template->footer(); break; }
      if(!preg_match('#^([0-9]+)$#', (string)$_GET['diff1']) ||
         !preg_match('#^([0-9]+)$#', (string)$_GET['diff2']  )) { echo '<p>SQL injection attempt</p>'; $template->footer(); break; }
      echo PageUtils::pagediff($paths->cpage['urlname_nons'], $paths->namespace, $id1, $id2);
      break;
    case "jsres":
      die('// ERROR: this section is deprecated and has moved to includes/clientside/static/enano-lib-basic.js.');
      break;
    case "rdns":
      if(!$session->get_permissions('mod_misc')) die('Go somewhere else for your reverse DNS info!');
      $ip = $_GET['ip'];
      $rdns = gethostbyaddr($ip);
      if($rdns == $ip) echo 'Unable to get reverse DNS information. Perhaps the DNS server is down or the PTR record no longer exists.';
      else echo $rdns;
      break;
    case 'acljson':
      $parms = ( isset($_POST['acl_params']) ) ? rawurldecode($_POST['acl_params']) : false;
      echo PageUtils::acl_json($parms);
      break;
    case "change_theme":
      if ( !isset($_POST['theme_id']) || !isset($_POST['style_id']) )
      {
        die('Invalid input');
      }
      if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['theme_id']) || !preg_match('/^([a-z0-9_-]+)$/i', $_POST['style_id']) )
      {
        die('Invalid input');
      }
      if ( !file_exists(ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css') )
      {
        die('Can\'t find theme file: ' . ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css');
      }
      if ( !$session->user_logged_in )
      {
        die('You must be logged in to change your theme');
      }
      // Just in case something slipped through...
      $theme_id = $db->escape($_POST['theme_id']);
      $style_id = $db->escape($_POST['style_id']);
      $e = $db->sql_query('UPDATE ' . table_prefix . "users SET theme='$theme_id', style='$style_id' WHERE user_id=$session->user_id;");
      if ( !$e )
        die( $db->get_error() );
      die('GOOD');
      break;
    case 'get_tags':
      $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
      
      $ret = array('tags' => array(), 'user_level' => $session->user_level, 'can_add' => $session->get_permissions('tag_create'));
      $q = $db->sql_query('SELECT t.tag_id, t.tag_name, pg.pg_target IS NOT NULL AS used_in_acl, t.user FROM '.table_prefix.'tags AS t
        LEFT JOIN '.table_prefix.'page_groups AS pg
          ON ( ( pg.pg_type = ' . PAGE_GRP_TAGGED . ' AND pg.pg_target=t.tag_name ) OR ( pg.pg_type IS NULL AND pg.pg_target IS NULL ) )
        WHERE t.page_id=\'' . $db->escape($paths->cpage['urlname_nons']) . '\' AND t.namespace=\'' . $db->escape($paths->namespace) . '\';');
      if ( !$q )
        $db->_die();
      
      while ( $row = $db->fetchrow() )
      {
        $can_del = true;
        
        $perm = ( $row['user'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
        
        if ( $row['user'] == 1 && !$session->user_logged_in )
          // anonymous user trying to delete tag (hardcode blacklisted)
          $can_del = false;
          
        if ( !$session->get_permissions($perm) )
          $can_del = false;
        
        if ( $row['used_in_acl'] == 1 && !$session->get_permissions('edit_acl') && $session->user_level < USER_LEVEL_ADMIN )
          $can_del = false;
        
        $ret['tags'][] = array(
          'id' => $row['tag_id'],
          'name' => $row['tag_name'],
          'can_del' => $can_del,
          'acl' => ( $row['used_in_acl'] == 1 )
        );
      }
      
      echo $json->encode($ret);
      
      break;
    case 'addtag':
      $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
      $resp = array(
          'success' => false,
          'error' => 'No error',
          'can_del' => ( $session->get_permissions('tag_delete_own') && $session->user_logged_in ),
          'in_acl' => false
        );
      
      // first of course, are we allowed to tag pages?
      if ( !$session->get_permissions('tag_create') )
      {
        $resp['error'] = 'You are not permitted to tag pages.';
        die($json->encode($resp));
      }
      
      // sanitize the tag name
      $tag = sanitize_tag($_POST['tag']);
      $tag = $db->escape($tag);
      
      if ( strlen($tag) < 2 )
      {
        $resp['error'] = 'Tags must consist of at least 2 alphanumeric characters.';
        die($json->encode($resp));
      }
      
      // check if tag is already on page
      $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'tags WHERE page_id=\'' . $db->escape($paths->cpage['urlname_nons']) . '\' AND namespace=\'' . $db->escape($paths->namespace) . '\' AND tag_name=\'' . $tag . '\';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() > 0 )
      {
        $resp['error'] = 'This page already has this tag.';
        die($json->encode($resp));
      }
      $db->free_result();
      
      // tricky: make sure this tag isn't being used in some page group, and thus adding it could affect page access
      $can_edit_acl = ( $session->get_permissions('edit_acl') || $session->user_level >= USER_LEVEL_ADMIN );
      $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'page_groups WHERE pg_type=' . PAGE_GRP_TAGGED . ' AND pg_target=\'' . $tag . '\';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() > 0 && !$can_edit_acl )
      {
        $resp['error'] = 'This tag is used in an ACL page group, and thus can\'t be added to a page by people without administrator privileges.';
        die($json->encode($resp));
      }
      $resp['in_acl'] = ( $db->numrows() > 0 );
      $db->free_result();
      
      // we're good
      $q = $db->sql_query('INSERT INTO '.table_prefix.'tags(tag_name,page_id,namespace,user) VALUES(\'' . $tag . '\', \'' . $db->escape($paths->cpage['urlname_nons']) . '\', \'' . $db->escape($paths->namespace) . '\', ' . $session->user_id . ');');
      if ( !$q )
        $db->_die();
      
      $resp['success'] = true;
      $resp['tag'] = $tag;
      $resp['tag_id'] = $db->insert_id();
      
      echo $json->encode($resp);
      break;
    case 'deltag':
      
      $tag_id = intval($_POST['tag_id']);
      if ( empty($tag_id) )
        die('Invalid tag ID');
      
      $q = $db->sql_query('SELECT t.tag_id, t.user, t.page_id, t.namespace, pg.pg_target IS NOT NULL AS used_in_acl FROM '.table_prefix.'tags AS t
  LEFT JOIN '.table_prefix.'page_groups AS pg
    ON ( pg.pg_id IS NULL OR ( pg.pg_target = t.tag_name AND pg.pg_type = ' . PAGE_GRP_TAGGED . ' ) )
  WHERE t.tag_id=' . $tag_id . ';');
      
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
        die('Could not find a tag with that ID');
      
      $row = $db->fetchrow();
      $db->free_result();
      
      if ( $row['page_id'] == $paths->cpage['urlname_nons'] && $row['namespace'] == $paths->namespace )
        $perms =& $session;
      else
        $perms = $session->fetch_page_acl($row['page_id'], $row['namespace']);
        
      $perm = ( $row['user'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
      
      if ( $row['user'] == 1 && !$session->user_logged_in )
        // anonymous user trying to delete tag (hardcode blacklisted)
        die('You are not authorized to delete this tag.');
        
      if ( !$perms->get_permissions($perm) )
        die('You are not authorized to delete this tag.');
      
      if ( $row['used_in_acl'] == 1 && !$perms->get_permissions('edit_acl') && $session->user_level < USER_LEVEL_ADMIN )
        die('You are not authorized to delete this tag.');
      
      // We're good
      $q = $db->sql_query('DELETE FROM '.table_prefix.'tags WHERE tag_id = ' . $tag_id . ';');
      if ( !$q )
        $db->_die();
      
      echo 'success';
      
      break;
    case 'ping':
      echo 'pong';
      break;
    default:
      die('Hacking attempt');
      break;
  }
  
?>