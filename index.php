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
 *
 * @package Enano
 * @subpackage Frontend
 *
 */
 
  define('ENANO_INTERFACE_INDEX', '');
  
  // For the mighty and brave.
  // define('ENANO_DEBUG', '');
 
  // Set up gzip encoding before any output is sent
  
  $aggressive_optimize_html = true;
  
  global $do_gzip;
  $do_gzip = true;
  
  if(isset($_SERVER['PATH_INFO'])) $v = $_SERVER['PATH_INFO'];
  elseif(isset($_GET['title'])) $v = $_GET['title'];
  else $v = '';
  
  if ( isset($_GET['nocompress']) )
    $aggressive_optimize_html = false;
  
  error_reporting(E_ALL);
  
  if($aggressive_optimize_html || $do_gzip)
  {
    ob_start();
  }
  
  require('includes/common.php');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  $page_timestamp = time();
  
  if ( !isset($_GET['do']) )
  {
    $_GET['do'] = 'view';
  }
  switch($_GET['do'])
  {
    default:
      die_friendly('Invalid action', '<p>The action "'.htmlspecialchars($_GET['do']).'" is not defined. Return to <a href="'.makeUrl($paths->page).'">viewing this page\'s text</a>.</p>');
      break;
    case 'view':
      // echo PageUtils::getpage($paths->page, true, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
      $rev_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->page_id, $paths->namespace, $rev_id );
      $page->send_headers = true;
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '';
      $page->password = $pagepass;
      $page->send(true);
      $page_timestamp = $page->revision_time;
      break;
    case 'comments':
      $template->header();
      $sub = ( isset ($_GET['sub']) ) ? $_GET['sub'] : false;
      switch($sub)
      {
        case 'admin':
        default:
          $act = ( isset ($_GET['action']) ) ? $_GET['action'] : false;
          $id = ( isset ($_GET['id']) ) ? intval($_GET['id']) : -1;
          echo PageUtils::comments_html($paths->page_id, $paths->namespace, $act, Array('id'=>$id));
          break;
        case 'postcomment':
          if(empty($_POST['name']) ||
             empty($_POST['subj']) ||
             empty($_POST['text'])
             ) { echo 'Invalid request'; break; }
          $cid = ( isset($_POST['captcha_id']) ) ? $_POST['captcha_id'] : false;
          $cin = ( isset($_POST['captcha_input']) ) ? $_POST['captcha_input'] : false;
          PageUtils::addcomment($paths->page_id, $paths->namespace, $_POST['name'], $_POST['subj'], $_POST['text'], $cin, $cid); // All filtering, etc. is handled inside this method
          echo PageUtils::comments_html($paths->page_id, $paths->namespace);
          break;
        case 'editcomment':
          if(!isset($_GET['id']) || ( isset($_GET['id']) && !preg_match('#^([0-9]+)$#', $_GET['id']) )) { echo '<p>Invalid comment ID</p>'; break; }
          $q = $db->sql_query('SELECT subject,comment_data,comment_id FROM '.table_prefix.'comments WHERE comment_id='.$_GET['id']);
          if(!$q) $db->_die('The comment data could not be selected.');
          $row = $db->fetchrow();
          $db->free_result();
          $row['subject'] = str_replace('\'', '&#039;', $row['subject']);
          echo '<form action="'.makeUrl($paths->page, 'do=comments&amp;sub=savecomment').'" method="post">';
          echo "<br /><div class='tblholder'><table border='0' width='100%' cellspacing='1' cellpadding='4'>
                  <tr><td class='row1'>" . $lang->get('comment_postform_field_subject') . "</td><td class='row1'><input type='text' name='subj' value='{$row['subject']}' /></td></tr>
                  <tr><td class='row2'>" . $lang->get('comment_postform_field_comment') . "</td><td class='row2'><textarea rows='10' cols='40' style='width: 98%;' name='text'>{$row['comment_data']}</textarea></td></tr>
                  <tr><td class='row1' colspan='2' class='row1' style='text-align: center;'><input type='hidden' name='id' value='{$row['comment_id']}' /><input type='submit' value='" . $lang->get('etc_save_changes') . "' /></td></tr>
                </table></div>";
          echo '</form>';
          break;
        case 'savecomment':
          if(empty($_POST['subj']) || empty($_POST['text'])) { echo '<p>Invalid request</p>'; break; }
          $r = PageUtils::savecomment_neater($paths->page_id, $paths->namespace, $_POST['subj'], $_POST['text'], (int)$_POST['id']);
          if($r != 'good') { echo "<pre>$r</pre>"; break; }
          echo PageUtils::comments_html($paths->page_id, $paths->namespace);
          break;
        case 'deletecomment':
          if(!empty($_GET['id']))
          {
            PageUtils::deletecomment_neater($paths->page_id, $paths->namespace, (int)$_GET['id']);
          }
          echo PageUtils::comments_html($paths->page_id, $paths->namespace);
          break;
      }
      $template->footer();
      break;
    case 'edit':
      if(isset($_POST['_cancel']))
      {
        redirect(makeUrl($paths->page), '', '', 0);
        break;
      }
      if(isset($_POST['_save']))
      {
        $captcha_valid = true;
        if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
        {
          $captcha_valid = false;
          if ( isset($_POST['captcha_id']) && isset($_POST['captcha_code']) )
          {
            $hash_correct = strtolower($session->get_captcha($_POST['captcha_id']));
            $hash_input   = strtolower($_POST['captcha_code']);
            if ( $hash_input === $hash_correct )
              $captcha_valid = true;
          }
        }
        if ( $captcha_valid )
        {
          $e = PageUtils::savepage($paths->page_id, $paths->namespace, $_POST['page_text'], $_POST['edit_summary'], isset($_POST['minor']));
          if ( $e == 'good' )
          {
            redirect(makeUrl($paths->page), $lang->get('editor_msg_save_success_title'), $lang->get('editor_msg_save_success_body'), 3);
          }
        }
      }
      $template->header();
      if ( isset($captcha_valid) )
      {
        echo '<div class="usermessage">' . $lang->get('editor_err_captcha_wrong') . '</div>';
      }
      if(isset($_POST['_preview']))
      {
        $text = $_POST['page_text'];
        $edsumm = $_POST['edit_summary'];
        echo PageUtils::genPreview($_POST['page_text']);
        $text = htmlspecialchars($text);
        $revid = 0;
      }
      else
      {
        $revid = ( isset($_GET['revid']) ) ? intval($_GET['revid']) : 0;
        $page = new PageProcessor($paths->page_id, $paths->namespace, $revid);
        $text = $page->fetch_source();
        $edsumm = '';
        // $text = RenderMan::getPage($paths->cpage['urlname_nons'], $paths->namespace, 0, false, false, false, false);
      }
      if ( $revid > 0 )
      {
        $time = $page->revision_time;
        // Retrieve information about this revision and the current one
        $q = $db->sql_query('SELECT l1.author AS currentrev_author, l2.author AS oldrev_author FROM ' . table_prefix . 'logs AS l1
  LEFT JOIN ' . table_prefix . 'logs AS l2
    ON ( l2.log_id = ' . $revid . '
         AND l2.log_type  = \'page\'
         AND l2.action    = \'edit\'
         AND l2.page_id   = \'' . $db->escape($paths->page_id) . '\'
         AND l2.namespace = \'' . $db->escape($paths->namespace) . '\'
         AND l1.is_draft != 1
        )
  WHERE l1.log_type  = \'page\'
    AND l1.action    = \'edit\'
    AND l1.page_id   = \'' . $db->escape($paths->page_id) . '\'
    AND l1.namespace = \'' . $db->escape($paths->namespace) . '\'
    AND l1.time_id > ' . $time . '
    AND l1.is_draft != 1
  ORDER BY l1.time_id DESC;');
        if ( !$q )
          $db->die_json();
        
        if ( $db->numrows() > 0 )
        {
          echo '<div class="usermessage">' . $lang->get('editor_msg_editing_old_revision') . '</div>';
          
          $rev_count = $db->numrows() - 2;
          $row = $db->fetchrow();
          $undo_info = array(
            'old_author'     => $row['oldrev_author'],
            'current_author' => $row['currentrev_author'],
            'undo_count'     => max($rev_count, 1),
            'last_rev_id'    => $revid
          );
        }
        else
        {
          $revid = 0;
        }
        $db->free_result();
      }
      echo '
        <form action="'.makeUrl($paths->page, 'do=edit').'" method="post" enctype="multipart/form-data">
        <br />
        <textarea name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea><br />
        <br />
        ';
      $edsumm = ( $revid > 0 ) ? $lang->get('editor_reversion_edit_summary', $undo_info) : $edsumm;
      echo $lang->get('editor_lbl_edit_summary') . ' <input name="edit_summary" type="text" size="40" value="' . htmlspecialchars($edsumm) . '" /><br /><label><input type="checkbox" name="minor" /> ' . $lang->get('editor_lbl_minor_edit_field') . '</label><br />';
      if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
      {
        echo '<br /><table border="0"><tr><td>';
        echo '<b>' . $lang->get('editor_lbl_field_captcha') . '</b><br />'
             . '<br />'
             . $lang->get('editor_msg_captcha_pleaseenter') . '<br /><br />'
             . $lang->get('editor_msg_captcha_blind');
        echo '</td><td>';
        $hash = $session->make_captcha();
        echo '<img src="' . makeUrlNS('Special', "Captcha/$hash") . '" onclick="this.src+=\'/a\'" style="cursor: pointer;" /><br />';
        echo '<input type="hidden" name="captcha_id" value="' . $hash . '" />';
        echo $lang->get('editor_lbl_field_captcha_code') . ' <input type="text" name="captcha_code" value="" size="9" />';
        echo '</td></tr></table>';
      }
      echo '<br />
          <input type="submit" name="_save"    value="' . $lang->get('editor_btn_save') . '" style="font-weight: bold;" />
          <input type="submit" name="_preview" value="' . $lang->get('editor_btn_preview') . '" />
          <input type="submit" name="_revert"  value="' . $lang->get('editor_btn_revert') . '" />
          <input type="submit" name="_cancel"  value="' . $lang->get('editor_btn_cancel') . '" />
        </form>
      ';
      if ( getConfig('wiki_edit_notice') == '1' )
      {
        $notice = getConfig('wiki_edit_notice_text');
        echo RenderMan::render($notice);
      }
      $template->footer();
      break;
    case 'viewsource':
      $template->header();
      $text = RenderMan::getPage($paths->page_id, $paths->namespace, 0, false, false, false, false);
      $text = htmlspecialchars($text);
      echo '
        <form action="'.makeUrl($paths->page, 'do=edit').'" method="post">
        <br />
        <textarea readonly="readonly" name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea>';
      echo '<br />
          <input type="submit" name="_cancel" value="' . $lang->get('editor_btn_closeviewer') . '" />
        </form>
      ';
      $template->footer();
      break;
    case 'history':
      $hist = PageUtils::histlist($paths->page_id, $paths->namespace);
      $template->header();
      echo $hist;
      $template->footer();
      break;
    case 'rollback':
      $id = (isset($_GET['id'])) ? $_GET['id'] : false;
      if(!$id || !preg_match('#^([0-9]+)$#', $id)) die_friendly('Invalid action ID', '<p>The URL parameter "id" is not an integer. Exiting to prevent nasties like SQL injection, etc.</p>');
      
      $id = intval($id);
      
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      $result = $page->rollback_log_entry($id);
      
      if ( $result['success'] )
      {
        $result = $lang->get("page_msg_rb_success_{$result['action']}", array('dateline' => $result['dateline']));
      }
      else
      {
        $result = $lang->get("page_err_{$result['error']}", array('action' => @$result['action']));
      }
      
      $template->header();
      echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a></p>';
      $template->footer();
      break;
    case 'catedit':
      if(isset($_POST['__enanoSaveButton']))
      {
        unset($_POST['__enanoSaveButton']);
        $val = PageUtils::catsave($paths->page_id, $paths->namespace, $_POST);
        if($val == 'GOOD')
        {
          header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break;
        } else {
          die_friendly('Error saving category information', '<p>'.$val.'</p>');
        }
      }
      elseif(isset($_POST['__enanoCatCancel']))
      {
        header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break;
      }
      $template->header();
      $c = PageUtils::catedit_raw($paths->page_id, $paths->namespace);
      echo $c[1];
      $template->footer();
      break;
    case 'moreoptions':
      $template->header();
      echo '<div class="menu_nojs" style="width: 150px; padding: 0;"><ul style="display: block;"><li><div class="label">' . $lang->get('ajax_lbl_moreoptions_nojs') . '</div><div style="clear: both;"></div></li>'.$template->toolbar_menu.'</ul></div>';
      $template->footer();
      break;
    case 'protect':
      if (!isset($_REQUEST['level'])) die_friendly('Invalid request', '<p>No protection level specified</p>');
      if(!empty($_POST['reason']))
      {
        if(!preg_match('#^([0-2]*){1}$#', $_POST['level'])) die_friendly('Error protecting page', '<p>Request validation failed</p>');
        PageUtils::protect($paths->page_id, $paths->namespace, intval($_POST['level']), $_POST['reason']);
        
        die_friendly($lang->get('page_protect_lbl_success_title'), '<p>' . $lang->get('page_protect_lbl_success_body', array( 'page_link' => makeUrl($paths->page) )) . '</p>');
      }
      $template->header();
      ?>
      <form action="<?php echo makeUrl($paths->page, 'do=protect'); ?>" method="post">
        <input type="hidden" name="level" value="<?php echo $_REQUEST['level']; ?>" />
        <?php if(isset($_POST['reason'])) echo '<p style="color: red;">' . $lang->get('page_protect_err_need_reason') . '</p>'; ?>
        <p><?php echo $lang->get('page_protect_lbl_reason'); ?></p>
        <p><input type="text" name="reason" size="40" /><br />
           <?php echo $lang->get('page_protect_lbl_level'); ?> <b><?php
             switch($_REQUEST['level'])
             {
               case '0':
                 echo $lang->get('page_protect_lbl_level_none');
                 break;
               case '1':
                 echo $lang->get('page_protect_lbl_level_full');
                 break;
               case '2':
                 echo $lang->get('page_protect_lbl_level_semi');
                 break;
               default:
                 echo 'None;</b> Warning: request validation will fail after clicking submit<b>';
             }
           ?></b></p>
        <p><input type="submit" value="<?php echo htmlspecialchars($lang->get('page_protect_btn_submit')) ?>" style="font-weight: bold;" /></p> 
      </form>
      <?php
      $template->footer();
      break;
    case 'rename':
      if(!empty($_POST['newname']))
      {
        $r = PageUtils::rename($paths->page_id, $paths->namespace, $_POST['newname']);
        die_friendly($lang->get('page_rename_success_title'), '<p>'.nl2br($r).' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>');
      }
      $template->header();
      ?>
      <form action="<?php echo makeUrl($paths->page, 'do=rename'); ?>" method="post">
        <?php if(isset($_POST['newname'])) echo '<p style="color: red;">' . $lang->get('page_rename_err_need_name') . '</p>'; ?>
        <p><?php echo $lang->get('page_rename_lbl'); ?></p>
        <p><input type="text" name="newname" size="40" /></p>
        <p><input type="submit" value="<?php echo htmlspecialchars($lang->get('page_rename_btn_submit')); ?>" style="font-weight: bold;" /></p> 
      </form>
      <?php
      $template->footer();    
      break;
    case 'flushlogs':
      if(!$session->get_permissions('clear_logs'))
      {
        die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
      }
      if(isset($_POST['_downthejohn']))
      {
        $template->header();
          $result = PageUtils::flushlogs($paths->page_id, $paths->namespace);
          echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=flushlogs'); ?>" method="post">
           <?php echo $lang->get('page_flushlogs_warning_stern'); ?>
           <p><input type="submit" name="_downthejohn" value="<?php echo htmlspecialchars($lang->get('page_flushlogs_btn_submit')); ?>" style="color: red; font-weight: bold;" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'delvote':
      if(isset($_POST['_ballotbox']))
      {
        $template->header();
        $result = PageUtils::delvote($paths->page_id, $paths->namespace);
        echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=delvote'); ?>" method="post">
           <?php
             echo $lang->get('page_delvote_warning_stern');
             echo '<p>';
             switch($paths->cpage['delvotes'])
             {
               case 0:  echo $lang->get('page_delvote_count_zero'); break;
               case 1:  echo $lang->get('page_delvote_count_one'); break;
               default: echo $lang->get('page_delvote_count_plural', array('delvotes' => $paths->cpage['delvotes'])); break;
             }
             echo '</p>';
           ?>
           <p><input type="submit" name="_ballotbox" value="<?php echo htmlspecialchars($lang->get('page_delvote_btn_submit')); ?>" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'resetvotes':
      if(!$session->get_permissions('vote_reset'))
      {
        die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
      }
      if(isset($_POST['_youmaylivealittlelonger']))
      {
        $template->header();
          $result = PageUtils::resetdelvotes($paths->page_id, $paths->namespace);
          echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=resetvotes'); ?>" method="post">
          <p><?php echo $lang->get('ajax_delvote_reset_confirm'); ?></p>
          <p><input type="submit" name="_youmaylivealittlelonger" value="<?php echo htmlspecialchars($lang->get('page_delvote_reset_btn_submit')); ?>" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'deletepage':
      if(!$session->get_permissions('delete_page'))
      {
        die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
      }
      if(isset($_POST['_adiossucker']))
      {
        $reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
        if ( empty($reason) )
          $error = $lang->get('ajax_delete_prompt_reason');
        else
        {
          $template->header();
            $result = PageUtils::deletepage($paths->page_id, $paths->namespace, $reason);
            echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
          $template->footer();
          break;
        }
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=deletepage'); ?>" method="post">
           <?php echo $lang->get('page_delete_warning_stern'); ?>
           <?php if ( isset($error) ) echo "<p>$error</p>"; ?>
           <p><?php echo $lang->get('page_delete_lbl_reason'); ?> <input type="text" name="reason" size="50" /></p>
           <p><input type="submit" name="_adiossucker" value="<?php echo htmlspecialchars($lang->get('page_delete_btn_submit')); ?>" style="color: red; font-weight: bold;" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'setwikimode':
      if(!$session->get_permissions('set_wiki_mode'))
      {
        die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
      }
      if ( isset($_POST['finish']) )
      {
        $level = intval($_POST['level']);
        if ( !in_array($level, array(0, 1, 2) ) )
        {
          die_friendly('Invalid request', '<p>Level not specified</p>');
        }
        $q = $db->sql_query('UPDATE '.table_prefix.'pages SET wiki_mode=' . $level . ' WHERE urlname=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $paths->namespace . '\';');
        if ( !$q )
          $db->_die();
        redirect(makeUrl($paths->page), htmlspecialchars($paths->cpage['name']), $lang->get('page_wikimode_success_redirect'), 2);
      }
      else
      {
        $template->header();
        if(!isset($_GET['level']) || ( isset($_GET['level']) && !preg_match('#^([0-9])$#', $_GET['level']))) die_friendly('Invalid request', '<p>Level not specified</p>');
          $level = intval($_GET['level']);
          if ( !in_array($level, array(0, 1, 2) ) )
          {
            die_friendly('Invalid request', '<p>Level not specified</p>');
          }
        echo '<form action="' . makeUrl($paths->page, 'do=setwikimode', true) . '" method="post">';
        echo '<input type="hidden" name="finish" value="foo" />';
        echo '<input type="hidden" name="level" value="' . $level . '" />';
        $level_txt = ( $level == 0 ) ? 'page_wikimode_level_off' : ( ( $level == 1 ) ? 'page_wikimode_level_on' : 'page_wikimode_level_global' );
        $blurb = ( $level == 0 || ( $level == 2 && getConfig('wiki_mode') != '1' ) ) ? 'page_wikimode_blurb_disable' : 'page_wikimode_blurb_enable';
        ?>
        <h3><?php echo $lang->get('page_wikimode_heading'); ?></h3>
        <p><?php echo $lang->get($level_txt) . ' ' . $lang->get($blurb); ?></p>
        <p><?php echo $lang->get('page_wikimode_warning'); ?></p>
        <p><input type="submit" value="<?php echo htmlspecialchars($lang->get('page_wikimode_btn_submit')); ?>" /></p>
        <?php
        echo '</form>';
        $template->footer();
      }
      break;
    case 'diff':
      $template->header();
      $id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
      $id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
      if(!$id1 || !$id2) { echo '<p>Invalid request.</p>'; $template->footer(); break; }
      if(!preg_match('#^([0-9]+)$#', (string)$_GET['diff1']) ||
         !preg_match('#^([0-9]+)$#', (string)$_GET['diff2']  )) { echo '<p>SQL injection attempt</p>'; $template->footer(); break; }
      echo PageUtils::pagediff($paths->page_id, $paths->namespace, $id1, $id2);
      $template->footer();
      break;
    case 'detag':
      if ( $session->user_level < USER_LEVEL_ADMIN )
      {
        die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
      }
      if ( $paths->page_exists )
      {
        die_friendly($lang->get('etc_invalid_request_short'), '<p>' . $lang->get('page_detag_err_page_exists') . '</p>');
      }
      $q = $db->sql_query('DELETE FROM '.table_prefix.'tags WHERE page_id=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $paths->namespace . '\';');
      if ( !$q )
        $db->_die('Detag query, index.php:'.__LINE__);
      die_friendly($lang->get('page_detag_success_title'), '<p>' . $lang->get('page_detag_success_body') . '</p>');
      break;
    case 'aclmanager':
      $data = ( isset($_POST['data']) ) ? $_POST['data'] : Array('mode' => 'listgroups');
      PageUtils::aclmanager($data);
      break;
    case 'sql_report':
      $rev_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->page_id, $paths->namespace, $rev_id );
      $page->send_headers = true;
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '';
      $page->password = $pagepass;
      $page->send(true);
      ob_end_clean();
      ob_start();
      $db->sql_report();
      break;
  }
  
  //
  // Optimize HTML by replacing newlines with spaces (excludes <pre>, <script>, and <style> blocks)
  //
  if ($aggressive_optimize_html)
  {
    // Load up the HTML
    $html = ob_get_contents();
    @ob_end_clean();
    
    $html = aggressive_optimize_html($html);
    
    // Re-enable output buffering to allow the Gzip function (below) to work
    ob_start();
    
    // Generate an ETag
    // format: first 10 digits of SHA1 of page name, user id in hex, page timestamp in hex
    $etag = substr(sha1($paths->namespace . ':' . $paths->page_id), 0, 10) . '-' .
            dechex($session->user_id) . '-' .
            dechex($page_timestamp);
            
    if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
    {
      if ( "\"$etag\"" == $_SERVER['HTTP_IF_NONE_MATCH'] )
      {
        header('HTTP/1.1 304 Not Modified');
        exit();
      }
    }
            
    header("ETag: \"$etag\"");
    
    // Done, send it to the user
    echo( $html );
  }

  $db->close();  
  gzip_output();
  
  @ob_end_flush();
  
?>
