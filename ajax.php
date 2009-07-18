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
 
  define('ENANO_INTERFACE_AJAX', '');
 
  require('includes/common.php');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!isset($_GET['_mode'])) die('This script cannot be accessed directly.');
  
  $_ob = '';
  
  switch($_GET['_mode']) {
    case "checkusername":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::checkusername($_GET['name']);
      break;
    case "getsource":
      header('Content-type: text/plain');
      $password = ( isset($_GET['pagepass']) ) ? $_GET['pagepass'] : false;
      $revid = ( isset($_GET['revid']) ) ? intval($_GET['revid']) : 0;
      $page = new PageProcessor($paths->page_id, $paths->namespace, $revid);
      $page->password = $password;
      
      $have_draft = false;
      if ( $src = $page->fetch_source() )
      {
        $allowed = true;
        $q = $db->sql_query('SELECT author, time_id, page_text, edit_summary, page_format FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                               AND page_id = \'' . $db->escape($paths->page_id) . '\'
                               AND namespace = \'' . $db->escape($paths->namespace) . '\'
                               AND is_draft = 1;');
        if ( !$q )
          $db->die_json();
        
        if ( $db->numrows() > 0 )
        {
          $have_draft = true;
          $draft_row = $db->fetchrow($q);
        }
      }
      else if ( $src !== false )
      {
        $allowed = true;
        $src = '';
      }
      else
      {
        $allowed = false;
        $src = '';
      }
      
      $auth_edit = ( $session->get_permissions('edit_page') && ( $session->get_permissions('even_when_protected') || !$paths->page_protected ) );
      $auth_wysiwyg = ( $session->get_permissions('edit_wysiwyg') );
      
      $return = array(
          'mode' => 'editor',
          'src' => $src,
          'auth_view_source' => $allowed,
          'auth_edit' => $auth_edit,
          'time' => time(),
          'require_captcha' => false,
          'allow_wysiwyg' => $auth_wysiwyg,
          'revid' => $revid,
          'have_draft' => false
        );
      
      $return['page_format'] = $paths->cpage['page_format'];
      if ( $return['page_format'] == 'xhtml' )
      {
        // gently process headings to make tinymce format them correctly
        if ( preg_match_all('/^ *?(={1,6}) *(.+?) *\\1 *$/m', $return['src'], $matches) )
        {
          foreach ( $matches[0] as $i => $match )
          {
            $hi = strlen($matches[1][$i]);
            $heading = "<h{$hi}>{$matches[2][$i]}</h{$hi}>";
            $return['src'] = str_replace_once($match, $heading, $return['src']);
          }
        }
      }
      
      if ( $have_draft )
      {
        $row =& $draft_row;
        $return['have_draft'] = true;
        $return['draft_author'] = $row['author'];
        $return['draft_time'] = enano_date('d M Y h:i a', intval($row['time_id']));
        if ( isset($_GET['get_draft']) && @$_GET['get_draft'] === '1' )
        {
          $return['src'] = $row['page_text'];
          $return['edit_summary'] = $row['edit_summary'];
          $return['page_format'] = $row['page_format'];
        }
      }
      
      $return['undo_info'] = array();
      
      if ( $revid > 0 )
      {
        // Retrieve information about this revision and the current one
        $q = $db->sql_query('SELECT l1.author AS currentrev_author, l2.author AS oldrev_author FROM ' . table_prefix . 'logs AS l1
  LEFT JOIN ' . table_prefix . 'logs AS l2
    ON ( l2.log_id = ' . $revid . '
         AND l2.log_type  = \'page\'
         AND l2.action    = \'edit\'
         AND l2.page_id   = \'' . $db->escape($paths->page_id)   . '\'
         AND l2.namespace = \'' . $db->escape($paths->namespace) . '\'
         AND l2.is_draft != 1
        )
  WHERE l1.log_type  = \'page\'
    AND l1.action    = \'edit\'
    AND l1.page_id   = \'' . $db->escape($paths->page_id)   . '\'
    AND l1.namespace = \'' . $db->escape($paths->namespace) . '\'
    AND l1.time_id   > ' . $page->revision_time . '
    AND l1.is_draft != 1
  ORDER BY l1.time_id DESC;');
        if ( !$q )
          $db->die_json();
        
        if ( $db->numrows() > 0 )
        {
          $rev_count = $db->numrows() - 1;
          if ( $rev_count == -1 )
          {
            $return = array(
                'mode' => 'error',
                'error' => '[Internal] No rows returned by revision info query. SQL:<pre>' . $db->latest_query . '</pre>'
              );
          }
          else
          {
            $row = $db->fetchrow();
            $return['undo_info'] = array(
              'old_author'     => $row['oldrev_author'],
              'current_author' => $row['currentrev_author'],
              'undo_count'     => $rev_count
            );
          }
        }
        else
        {
          $return['revid'] = $revid = 0;
        }
      }
      
      if ( $auth_edit && !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
      {
        $return['require_captcha'] = true;
        $return['captcha_id'] = $session->make_captcha();
      }
      
      $template->load_theme();
      $return['toolbar_templates'] = $template->extract_vars('toolbar.tpl');
      $return['edit_notice'] = $template->get_wiki_edit_notice();
      
      echo enano_json_encode($return);
      break;
    case "getpage":
      // echo PageUtils::getpage($paths->page, false, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
      $output = new Output_Striptease();
      
      $revision_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->page_id, $paths->namespace, $revision_id );
      
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? $_REQUEST['pagepass'] : '';
      $page->password = $pagepass;
      $page->allow_redir = ( !isset($_GET['redirect']) || (isset($_GET['redirect']) && $_GET['redirect'] !== 'no') );
            
      $page->send();
      break;
    case "savepage":
      /* **** OBSOLETE **** */
      
      break;
    case "savepage_json":
      header('Content-type: application/json');
      if ( !isset($_POST['r']) )
        die('Invalid request');
      
      try
      {
        $request = enano_json_decode($_POST['r']);
        if ( !isset($request['src']) || !isset($request['summary']) || !isset($request['minor_edit']) || !isset($request['time']) || !isset($request['draft']) )
          die('Invalid request');
      }
      catch(Zend_Json_Exception $e)
      {
        die("JSON parsing failed. View as HTML to see full report.\n<br /><br />\n<pre>" . htmlspecialchars(strval($e)) . "</pre><br />Request: <pre>" . htmlspecialchars($_POST['r']) . "</pre>");
      }
      
      $time = intval($request['time']);
      
      if ( $request['draft'] )
      {
        //
        // The user wants to save a draft version of the page.
        //
        
        // Validate permissions
        if ( !$session->get_permissions('edit_page') )
        {
          $return = array(
            'mode' => 'error',
            'error' => 'access_denied'
          );
        }
        else
        {
          // Delete any draft copies if they exist
          $q = $db->sql_query('DELETE FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                                 AND page_id = \'' . $db->escape($paths->page_id) . '\'
                                 AND namespace = \'' . $db->escape($paths->namespace) . '\'
                                 AND is_draft = 1;');
          if ( !$q )
            $db->die_json();
          
          // are we just supposed to delete the draft?
          if ( $request['src'] === -1 )
          {
            $return = array(
              'mode' => 'success',
              'is_draft' => 'delete'
            );
          }
          else
          {
            $src = RenderMan::preprocess_text($request['src'], false, false);
            $draft_format = $request['format'];
            if ( !in_array($draft_format, array('xhtml', 'wikitext')) )
            {
              $return = array(
                'mode' => 'error',
                'error' => 'invalid_format'
              );
            }
            else
            {
              // Save the draft
              $q = $db->sql_query('INSERT INTO ' . table_prefix . 'logs ( log_type, action, page_id, namespace, author, edit_summary, page_text, is_draft, time_id, page_format )
                                     VALUES (
                                       \'page\',
                                       \'edit\',
                                       \'' . $db->escape($paths->page_id) . '\',
                                       \'' . $db->escape($paths->namespace) . '\',
                                       \'' . $db->escape($session->username) . '\',
                                       \'' . $db->escape($request['summary']) . '\',
                                       \'' . $db->escape($src) . '\',
                                       1,
                                       ' . time() . ',
                                       \'' . $draft_format . '\'
                                     );');
              
              // Done!
              $return = array(
                  'mode' => 'success',
                  'is_draft' => true
                );
            }
          }
        }
      }
      else
      {
        // Verify that no edits have been made since the editor was requested
        $q = $db->sql_query('SELECT time_id, author FROM ' . table_prefix . "logs WHERE log_type = 'page' AND action = 'edit' AND page_id = '{$paths->page_id}' AND namespace = '{$paths->namespace}' AND is_draft != 1 ORDER BY time_id DESC LIMIT 1;");
        if ( !$q )
          $db->die_json();
        
        $row = $db->fetchrow();
        $db->free_result();
        
        if ( $row['time_id'] > $time )
        {
          $return = array(
            'mode' => 'obsolete',
            'author' => $row['author'],
            'date_string' => enano_date('d M Y h:i a', $row['time_id']),
            'time' => $row['time_id'] // time() ???
            );
          echo enano_json_encode($return);
          break;
        }
        
        // Verify captcha, if needed
        if ( false && !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
        {
          if ( !isset($request['captcha_id']) || !isset($request['captcha_code']) )
          {
            die('Invalid request, need captcha metadata');
          }
          $code_correct = strtolower($session->get_captcha($request['captcha_id']));
          $code_input = strtolower($request['captcha_code']);
          if ( $code_correct !== $code_input )
          {
            $return = array(
              'mode' => 'errors',
              'errors' => array($lang->get('editor_err_captcha_wrong')),
              'new_captcha' => $session->make_captcha()
            );
            echo enano_json_encode($return);
            break;
          }
        }
        
        // Verification complete. Start the PageProcessor and let it do the dirty work for us.
        $page = new PageProcessor($paths->page_id, $paths->namespace);
        if ( $page->update_page($request['src'], $request['summary'], ( $request['minor_edit'] == 1 ), $request['format']) )
        {
          $return = array(
              'mode' => 'success',
              'is_draft' => false
            );
        }
        else
        {
          $errors = array();
          while ( $err = $page->pop_error() )
          {
            $errors[] = $err;
          }
          $return = array(
            'mode' => 'errors',
            'errors' => array_values($errors)
            );
          if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
          {
            $return['new_captcha'] = $session->make_captcha();
          }
        }
      }
      
      // If this is based on a draft version, delete the draft - we no longer need it.
      if ( @$request['used_draft'] && !$request['draft'] )
      {
        $q = $db->sql_query('DELETE FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                               AND page_id = \'' . $db->escape($paths->page_id) . '\'
                               AND namespace = \'' . $db->escape($paths->namespace) . '\'
                               AND is_draft = 1;');
      }
      
      echo enano_json_encode($return);
      
      break;
    case "diff_cur":
      
      // Lie about our content type to fool ad scripts
      header('Content-type: application/xhtml+xml');
      
      if ( !isset($_POST['text']) )
        die('Invalid request');
      
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      if ( !($src = $page->fetch_source()) )
      {
        die('Access denied');
      }
      
      $diff = RenderMan::diff($src, $_POST['text']);
      if ( $diff == '<table class="diff"></table>' )
      {
        $diff = '<p>' . $lang->get('editor_msg_diff_empty') . '</p>';
      }
      
      echo '<div class="info-box">' . $lang->get('editor_msg_diff') . '</div>';
      echo $diff;
      
      break;
    case "protect":
      // echo PageUtils::protect($paths->page_id, $paths->namespace, (int)$_POST['level'], $_POST['reason']);
      
      if ( @$_POST['reason'] === '__ROLLBACK__' )
      {
        // __ROLLBACK__ is a keyword for log entries.
        die('"__ROLLBACK__" ain\'t gonna do it, buddy. Try to _not_ use reserved keywords next time, ok?');
      }
      
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      header('Content-type: application/json');
      
      $result = $page->protect_page(intval($_POST['level']), $_POST['reason']);
      echo enano_json_encode($result);
      break;
    case "histlist":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::histlist($paths->page_id, $paths->namespace);
      break;
    case "rollback":
      $id = intval(@$_GET['id']);
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      header('Content-type: application/json');
      
      $result = $page->rollback_log_entry($id);
      echo enano_json_encode($result);
      break;
    case "comments":
      require_once(ENANO_ROOT.'/includes/comment.php');
      $comments = new Comments($paths->page_id, $paths->namespace);
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
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      header('Content-type: application/json');
      
      $result = $page->rename_page($_POST['newtitle']);
      echo enano_json_encode($result);
      break;
    case "flushlogs":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::flushlogs($paths->page_id, $paths->namespace);
      break;
    case "deletepage":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      $reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
      if ( empty($reason) )
        die($lang->get('page_err_need_reason'));
      echo PageUtils::deletepage($paths->page_id, $paths->namespace, $reason);
      break;
    case "delvote":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::delvote($paths->page_id, $paths->namespace);
      break;
    case "resetdelvotes":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::resetdelvotes($paths->page_id, $paths->namespace);
      break;
    case "getstyles":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::getstyles($_GET['id']);
      break;
    case "catedit":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::catedit($paths->page_id, $paths->namespace);
      break;
    case "catsave":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::catsave($paths->page_id, $paths->namespace, $_POST);
      break;
    case "setwikimode":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::setwikimode($paths->page_id, $paths->namespace, (int)$_GET['mode']);
      break;
    case "setpass":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      echo PageUtils::setpass($paths->page_id, $paths->namespace, $_POST['password']);
      break;
    case "fillusername":
      break;
    case "fillpagename":
      break;
    case "preview":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      $template->init_vars();
      echo PageUtils::genPreview($_POST['text']);
      break;
    case "transform":
      header('Content-type: text/javascript');
      if ( !isset($_GET['to']) )
      {
        echo enano_json_encode(array(
            'mode' => 'error',
            'error' => '"to" not specified'
          ));
        break;
      }
      if ( !isset($_POST['text']) )
      {
        echo enano_json_encode(array(
            'mode' => 'error',
            'error' => '"text" not specified (must be on POST)'
          ));
        break;
      }
      switch($_GET['to'])
      {
        case 'xhtml':
          $result = RenderMan::render($_POST['text'], RENDER_WIKI_DEFAULT | RENDER_BLOCKONLY);
          break;
        case 'wikitext':
          $result = RenderMan::reverse_render($_POST['text']);
          break;
        default:
          $text =& $_POST['text'];
          $result = false;
          $code = $plugins->setHook('ajax_transform');
          foreach ( $code as $cmd )
          {
            eval($cmd);
          }
          if ( !$result )
          {
            echo enano_json_encode(array(
                'mode' => 'error',
                'error' => 'Invalid target format'
              ));
            break;
          }
          break;
      }
      
      // mostly for debugging, but I suppose this could be useful elsewhere.
      if ( isset($_POST['plaintext']) )
        die($result);
      
      echo enano_json_encode(array(
          'mode' => 'transformed_text',
          'text' => $result
        ));
      break;
    case "pagediff":
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      $id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
      $id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
      if(!$id1 || !$id2) { echo '<p>Invalid request.</p>'; $template->footer(); break; }
      if(!preg_match('#^([0-9]+)$#', (string)$_GET['diff1']) ||
         !preg_match('#^([0-9]+)$#', (string)$_GET['diff2']  )) { echo '<p>SQL injection attempt</p>'; $template->footer(); break; }
      echo PageUtils::pagediff($paths->page_id, $paths->namespace, $id1, $id2);
      break;
    case "jsres":
      die('// ERROR: this section is deprecated and has moved to includes/clientside/static/enano-lib-basic.js.');
      break;
    case "rdns":
      if(!$session->get_permissions('mod_misc')) die('Go somewhere else for your reverse DNS info!');
      $ip = $_GET['ip'];
      if ( !is_valid_ip($ip) )
      {
        echo $lang->get('acpsl_err_invalid_ip');
      }
      $rdns = gethostbyaddr($ip);
      if ( $rdns == $ip )
        echo $lang->get('acpsl_err_ptr_no_resolve');
      else echo $rdns;
      break;
    case 'acljson':
      require_once(ENANO_ROOT.'/includes/pageutils.php');
      $parms = ( isset($_POST['acl_params']) ) ? rawurldecode($_POST['acl_params']) : false;
      echo PageUtils::acl_json($parms);
      break;
    case 'theme_list':
      header('Content-type: application/json');
      
      $return = array();
      foreach ( $template->theme_list as $theme )
      {
        if ( $theme['enabled'] != 1 )
          continue;
        
        $return[] = array(
            'theme_name' => $theme['theme_name'],
            'theme_id' => $theme['theme_id'],
            'have_thumb' => file_exists(ENANO_ROOT . "/themes/{$theme['theme_id']}/preview.png")
          );
      }
      
      echo enano_json_encode($return);
      
      break;
    case "get_styles":
      if ( !preg_match('/^[a-z0-9_-]+$/', $_GET['theme_id']) )
        die(enano_json_encode(array()));
      
      $theme_id = $_GET['theme_id'];
      $return = array();
      
      if ( $dr = @opendir(ENANO_ROOT . "/themes/$theme_id/css/") )
      {
        while ( $dh = @readdir($dr) )
        {
          if ( preg_match('/\.css$/', $dh) && $dh != '_printable.css' )
          {
            $return[] = preg_replace('/\.css$/', '', $dh);
          }
        }
      }
      else
      {
        $return = array(
            'mode' => 'error',
            'error' => 'Could not open directory.'
          );
      }
      echo enano_json_encode($return);
      break;
    case "change_theme":
      if ( !isset($_POST['theme_id']) || !isset($_POST['style_id']) )
      {
        die(enano_json_encode(array('mode' => 'error', 'error' => 'Invalid parameter')));
      }
      if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['theme_id']) || !preg_match('/^([a-z0-9_-]+)$/i', $_POST['style_id']) )
      {
        die(enano_json_encode(array('mode' => 'error', 'error' => 'Invalid parameter')));
      }
      if ( !file_exists(ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css') )
      {
        die(enano_json_encode(array('mode' => 'error', 'error' => 'Can\'t find theme file: ' . ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css')));;
      }
      if ( !$session->user_logged_in )
      {
        die(enano_json_encode(array('mode' => 'error', 'error' => 'You must be logged in to change your theme')));
      }
      // Just in case something slipped through...
      $theme_id = $db->escape($_POST['theme_id']);
      $style_id = $db->escape($_POST['style_id']);
      $e = $db->sql_query('UPDATE ' . table_prefix . "users SET theme = '$theme_id', style = '$style_id' WHERE user_id = $session->user_id;");
      if ( !$e )
        die( $db->get_error() );
      
      echo enano_json_encode(array(
          'success' => true
        ));
      break;
    case 'get_tags':
      
      $ret = array('tags' => array(), 'user_level' => $session->user_level, 'can_add' => $session->get_permissions('tag_create'));
      $q = $db->sql_query('SELECT t.tag_id, t.tag_name, pg.pg_target IS NOT NULL AS used_in_acl, t.user_id FROM '.table_prefix.'tags AS t
        LEFT JOIN '.table_prefix.'page_groups AS pg
          ON ( ( pg.pg_type = ' . PAGE_GRP_TAGGED . ' AND pg.pg_target=t.tag_name ) OR ( pg.pg_type IS NULL AND pg.pg_target IS NULL ) )
        WHERE t.page_id=\'' . $db->escape($paths->page_id) . '\' AND t.namespace=\'' . $db->escape($paths->namespace) . '\';');
      if ( !$q )
        $db->_die();
      
      while ( $row = $db->fetchrow() )
      {
        $can_del = true;
        
        $perm = ( $row['user_id'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
        
        if ( $row['user_id'] == 1 && !$session->user_logged_in )
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
      
      echo enano_json_encode($ret);
      
      break;
    case 'addtag':
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
        die(enano_json_encode($resp));
      }
      
      // sanitize the tag name
      $tag = sanitize_tag($_POST['tag']);
      $tag = $db->escape($tag);
      
      if ( strlen($tag) < 2 )
      {
        $resp['error'] = 'Tags must consist of at least 2 alphanumeric characters.';
        die(enano_json_encode($resp));
      }
      
      // check if tag is already on page
      $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'tags WHERE page_id=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $db->escape($paths->namespace) . '\' AND tag_name=\'' . $tag . '\';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() > 0 )
      {
        $resp['error'] = 'This page already has this tag.';
        die(enano_json_encode($resp));
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
        die(enano_json_encode($resp));
      }
      $resp['in_acl'] = ( $db->numrows() > 0 );
      $db->free_result();
      
      // we're good
      $q = $db->sql_query('INSERT INTO '.table_prefix.'tags(tag_name,page_id,namespace,user_id) VALUES(\'' . $tag . '\', \'' . $db->escape($paths->page_id) . '\', \'' . $db->escape($paths->namespace) . '\', ' . $session->user_id . ');');
      if ( !$q )
        $db->_die();
      
      $resp['success'] = true;
      $resp['tag'] = $tag;
      $resp['tag_id'] = $db->insert_id();
      
      echo enano_json_encode($resp);
      break;
    case 'deltag':
      
      $tag_id = intval($_POST['tag_id']);
      if ( empty($tag_id) )
        die('Invalid tag ID');
      
      $q = $db->sql_query('SELECT t.tag_id, t.user_id, t.page_id, t.namespace, pg.pg_target IS NOT NULL AS used_in_acl FROM '.table_prefix.'tags AS t
  LEFT JOIN '.table_prefix.'page_groups AS pg
    ON ( pg.pg_id IS NULL OR ( pg.pg_target = t.tag_name AND pg.pg_type = ' . PAGE_GRP_TAGGED . ' ) )
  WHERE t.tag_id=' . $tag_id . ';');
      
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
        die('Could not find a tag with that ID');
      
      $row = $db->fetchrow();
      $db->free_result();
      
      if ( $row['page_id'] == $paths->page_id && $row['namespace'] == $paths->namespace )
        $perms =& $session;
      else
        $perms = $session->fetch_page_acl($row['page_id'], $row['namespace']);
        
      $perm = ( $row['user_id'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
      
      if ( $row['user_id'] == 1 && !$session->user_logged_in )
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