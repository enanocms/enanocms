<?php
/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 (Banshee)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
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
      $page = new PageProcessor( $paths->cpage['urlname_nons'], $paths->namespace );
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
      
      /*
       * This is old code and should not be used. It's badly broken and a perfect example of bad database organization.
       
    case "addcomment":
      $cc = ( isset($_POST['captcha_code']) ) ? $_POST['captcha_code'] : false;
      $ci = ( isset($_POST['captcha_id']  ) ) ? $_POST['captcha_id']   : false;
      if(!isset($_POST['text']) ||
         !isset($_POST['subj']) ||
         !isset($_POST['name'])) die('alert(\'Error in POST DATA string, aborting\');');
      if($_POST['text']=='' ||
         $_POST['name']=='' ||
         $_POST['subj']=='') die('alert(\'One or more POST DATA fields was empty, aborting post submission\')');
     echo PageUtils::addcomment($paths->cpage['urlname_nons'], $paths->namespace, $_POST['name'], $_POST['subj'], $_POST['text'], $cc, $ci);
     break;
    case "comments":
      echo PageUtils::comments($paths->cpage['urlname_nons'], $paths->namespace, ( isset($_GET['action']) ? $_GET['action'] : false ), Array(
          'name' => ( isset($_POST['name']) ) ? $_POST['name'] : '',
          'subj' => ( isset($_POST['subj']) ) ? $_POST['subj'] : '',
          'text' => ( isset($_POST['text']) ) ? $_POST['text'] : ''
        ));
      break;
    case "savecomment":
      echo PageUtils::savecomment($paths->cpage['urlname_nons'], $paths->namespace, $_POST['s'], $_POST['t'], $_POST['os'], $_POST['ot'], $_POST['id']);
      break;
    case "deletecomment":
      echo PageUtils::deletecomment($paths->cpage['urlname_nons'], $paths->namespace, $_POST['name'], $_POST['subj'], $_POST['text'], $_GET['id']);
      break;
      */
      
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
      echo PageUtils::deletepage($paths->cpage['urlname_nons'], $paths->namespace);
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
    case "wikihelp":
      $html = file_get_contents('http://www.enanocms.org/ajax.php?title=Help:Wiki_formatting&_mode=getpage&nofooters');
      $html = str_replace('src="/Special', 'src="http://www.enanocms.org/Special', $html);
      echo '<div class="contentDiv"><h2>Wiki formatting guide</h2>'.$html.'</div>';
      break;
    case "fillusername":
      $name = (isset($_GET['name'])) ? $db->escape($_GET['name']) : false;
      if(!$name) die('userlist = new Array(); errorstring=\'Invalid URI\'');
      $q = $db->sql_query('SELECT username,user_id FROM '.table_prefix.'users WHERE username LIKE \'%'.$name.'%\';');
      if(!$q) die('userlist = new Array(); errorstring=\'MySQL error selecting username data: '.addslashes(mysql_error()).'\'');
      if($db->numrows() < 1) die('userlist = new Array(); errorstring=\'No usernames found.\'');
      echo 'var errorstring = false; userlist = new Array();';
      $i=0;
      while($r = $db->fetchrow())
      {
        echo "userlist[$i] = '".addslashes($r['username'])."'; ";
        $i++;
      }
      $db->free_result();
      break;
    case "fillpagename":
      $name = (isset($_GET['name'])) ? $_GET['name'] : false;
      if(!$name) die('userlist = new Array(); namelist = new Array(); errorstring=\'Invalid URI\'');
      $nd = RenderMan::strToPageID($name);
      $c = 0;
      $u = Array();
      $n = Array();
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
          echo "namelist[$i] = '".addslashes($u[$i])."';\n";
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
      if($rdns == $ip) echo 'Unable to get reverse DNS information. Perhaps the IP address does not exist anymore.';
      else echo $rdns;
      break;
    case 'acljson':
      $parms = ( isset($_POST['acl_params']) ) ? rawurldecode($_POST['acl_params']) : false;
      echo PageUtils::acl_json($parms);
      break;
    default:
      die('Hacking attempt');
      break;
  }
  
?>