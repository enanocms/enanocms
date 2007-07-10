<?php
/**
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * @Version 1.0 (Banshee)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 */
 
  // Set up gzip encoding before any output is sent
  
  $aggressive_optimize_html = false;
  
  global $do_gzip;
  $do_gzip = false;
  
  if(isset($_SERVER['PATH_INFO'])) $v = $_SERVER['PATH_INFO'];
  elseif(isset($_GET['title'])) $v = $_GET['title'];
  else $v = '';
  
  error_reporting(E_ALL);
  
  // if(!strstr($v, 'CSS') && !strstr($v, 'UploadFile') && !strstr($v, 'DownloadFile')) // These pages are blacklisted because we can't have debugConsole's HTML output disrupting the flow of header() calls and whatnot
  // {
  //   $do_gzip = ( function_exists('gzcompress') && ( isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') ) ) ? true : false;
  //   // Uncomment the following line to enable debugConsole (requires PHP 5 or later)
  //   // define('ENANO_DEBUG', '');
  // }
  
  if(defined('ENANO_DEBUG')) $do_gzip = false;
  
  if($aggressive_optimize_html || $do_gzip)
  {
    ob_start();
  }
  
  require('includes/common.php');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if(!isset($_GET['do'])) $_GET['do'] = 'view';
  switch($_GET['do'])
  {
    default:
      die_friendly('Invalid action', '<p>The action "'.$_GET['do'].'" is not defined. Return to <a href="'.makeUrl($paths->page).'">viewing this page\'s text</a>.</p>');
      break;
    case 'view':
      // echo PageUtils::getpage($paths->page, true, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
      $rev_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->cpage['urlname_nons'], $paths->namespace, $rev_id );
      $page->send_headers = true;
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '';
      $page->password = $pagepass;
      $page->send(true);
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
          echo PageUtils::comments_html($paths->cpage['urlname_nons'], $paths->namespace, $act, Array('id'=>$id));
          break;
        case 'postcomment':
          if(empty($_POST['name']) ||
             empty($_POST['subj']) ||
             empty($_POST['text'])
             ) { echo 'Invalid request'; break; }
          $cid = ( isset($_POST['captcha_id']) ) ? $_POST['captcha_id'] : false;
          $cin = ( isset($_POST['captcha_input']) ) ? $_POST['captcha_input'] : false;
          PageUtils::addcomment($paths->cpage['urlname_nons'], $paths->namespace, $_POST['name'], $_POST['subj'], $_POST['text'], $cin, $cid); // All filtering, etc. is handled inside this method
          echo PageUtils::comments_html($paths->cpage['urlname_nons'], $paths->namespace);
          break;
        case 'editcomment':
          if(!isset($_GET['id']) || ( isset($_GET['id']) && !preg_match('#^([0-9]+)$#', $_GET['id']) )) { echo '<p>Invalid comment ID</p>'; break; }
          $q = $db->sql_query('SELECT subject,comment_data,comment_id FROM '.table_prefix.'comments WHERE comment_id='.$_GET['id']);
          if(!$q) $db->_die('The comment data could not be selected.');
          $row = $db->fetchrow();
          $db->free_result();
          echo '<form action="'.makeUrl($paths->page, 'do=comments&amp;sub=savecomment').'" method="post">';
          echo "<br /><div class='tblholder'><table border='0' width='100%' cellspacing='1' cellpadding='4'>
                  <tr><td class='row1'>Subject:</td><td class='row1'><input type='text' name='subj' value='{$row['subject']}' /></td></tr>
                  <tr><td class='row2'>Comment:</td><td class='row2'><textarea rows='10' cols='40' style='width: 98%;' name='text'>{$row['comment_data']}</textarea></td></tr>
                  <tr><td class='row1' colspan='2' class='row1' style='text-align: center;'><input type='hidden' name='id' value='{$row['comment_id']}' /><input type='submit' value='Save Changes' /></td></tr>
                </table></div>";
          echo '</form>';
          break;
        case 'savecomment':
          if(empty($_POST['subj']) || empty($_POST['text'])) { echo '<p>Invalid request</p>'; break; }
          $r = PageUtils::savecomment_neater($paths->cpage['urlname_nons'], $paths->namespace, $_POST['subj'], $_POST['text'], (int)$_POST['id']);
          if($r != 'good') { echo "<pre>$r</pre>"; break; }
          echo PageUtils::comments_html($paths->cpage['urlname_nons'], $paths->namespace);
          break;
        case 'deletecomment':
          if(!empty($_GET['id']))
          {
            PageUtils::deletecomment_neater($paths->cpage['urlname_nons'], $paths->namespace, (int)$_GET['id']);
          }
          echo PageUtils::comments_html($paths->cpage['urlname_nons'], $paths->namespace);
          break;
      }
      $template->footer();
      break;
    case 'edit':
      if(isset($_POST['_cancel'])) { header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break; }
      if(isset($_POST['_save'])) {
        $e = PageUtils::savepage($paths->cpage['urlname_nons'], $paths->namespace, $_POST['page_text'], $_POST['edit_summary'], isset($_POST['minor']));
        header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break;
      }
      $template->header();
      if(isset($_POST['_preview']))
      {
        $text = $_POST['page_text'];
        echo PageUtils::genPreview($_POST['page_text']);
      }
      else $text = RenderMan::getPage($paths->cpage['urlname_nons'], $paths->namespace, 0, false, false, false, false);
      echo '
        <form action="'.makeUrl($paths->page, 'do=edit').'" method="post" enctype="multipart/form-data">
        <br />
        <textarea name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea><br />
        <br />
        ';
      if($paths->wiki_mode)
        echo 'Edit summary: <input name="edit_summary" type="text" size="40" /><br /><label><input type="checkbox" name="minor" /> This is a minor edit</label><br />';  
      echo '<br />
          <input type="submit" name="_save" value="Save changes" style="font-weight: bold;" />
          <input type="submit" name="_preview" value="Preview changes" />
          <input type="submit" name="_revert" value="Revert changes" />
          <input type="submit" name="_cancel" value="Cancel" />
        </form>
      ';
      $template->footer();
      break;
    case 'viewsource':
      $template->header();
      $text = RenderMan::getPage($paths->cpage['urlname_nons'], $paths->namespace, 0, false, false, false, false);
      echo '
        <form action="'.makeUrl($paths->page, 'do=edit').'" method="post">
        <br />
        <textarea readonly="readonly" name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea>';
      echo '<br />
          <input type="submit" name="_cancel" value="Close viewer" />
        </form>
      ';
      $template->footer();
      break;
    case 'history':
      $hist = PageUtils::histlist($paths->cpage['urlname_nons'], $paths->namespace);
      $template->header();
      echo $hist;
      $template->footer();
      break;
    case 'rollback':
      $id = (isset($_GET['id'])) ? $_GET['id'] : false;
      if(!$id || !preg_match('#^([0-9]+)$#', $id)) die_friendly('Invalid action ID', '<p>The URL parameter "id" is not an integer. Exiting to prevent nasties like SQL injection, etc.</p>');
      $rb = PageUtils::rollback( (int) $id );
      $template->header();
      echo '<p>'.$rb.' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>';
      $template->footer();
      break;
    case 'catedit':
      if(isset($_POST['__enanoSaveButton']))
      {
        unset($_POST['__enanoSaveButton']);
        $val = PageUtils::catsave($paths->cpage['urlname_nons'], $paths->namespace, $_POST);
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
      $c = PageUtils::catedit_raw($paths->cpage['urlname_nons'], $paths->namespace);
      echo $c[1];
      $template->footer();
      break;
    case 'moreoptions':
      $template->header();
      echo '<div class="menu_nojs" style="width: 150px; padding: 0;"><ul style="display: block;"><li><div class="label">More options for this page</div><div style="clear: both;"></div></li>'.$template->tpl_strings['TOOLBAR_EXTRAS'].'</ul></div>';
      $template->footer();
      break;
    case 'protect':
      if (!isset($_REQUEST['level'])) die_friendly('Invalid request', '<p>No protection level specified</p>');
      if(!empty($_POST['reason']))
      {
        if(!preg_match('#^([0-2]*){1}$#', $_POST['level'])) die_friendly('Error protecting page', '<p>Request validation failed</p>');
        PageUtils::protect($paths->cpage['urlname_nons'], $paths->namespace, intval($_POST['level']), $_POST['reason']);
        die_friendly('Page protected', '<p>The protection setting has been applied. <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>');
      }
      $template->header();
      ?>
      <form action="<?php echo makeUrl($paths->page, 'do=protect'); ?>" method="post">
        <input type="hidden" name="level" value="<?php echo $_REQUEST['level']; ?>" />
        <?php if(isset($_POST['reason'])) echo '<p style="color: red;">Error: you must enter a reason for protecting this page.</p>'; ?>
        <p>Reason for protecting the page:</p>
        <p><input type="text" name="reason" size="40" /><br />
           Protecion level to be applied: <b><?php
             switch($_REQUEST['level'])
             {
               case '0':
                 echo 'No protection';
                 break;
               case '1':
                 echo 'Full protection';
                 break;
               case '2':
                 echo 'Semi-protection';
                 break;
               default:
                 echo 'None;</b> Warning: request validation will fail after clicking submit<b>';
             }
           ?></b></p>
        <p><input type="submit" value="Protect page" style="font-weight: bold;" /></p> 
      </form>
      <?php
      $template->footer();
      break;
    case 'rename':
      if(!empty($_POST['newname']))
      {
        $r = PageUtils::rename($paths->cpage['urlname_nons'], $paths->namespace, $_POST['newname']);
        die_friendly('Page renamed', '<p>'.nl2br($r).' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>');
      }
      $template->header();
      ?>
      <form action="<?php echo makeUrl($paths->page, 'do=rename'); ?>" method="post">
        <?php if(isset($_POST['newname'])) echo '<p style="color: red;">Error: you must enter a new name for this page.</p>'; ?>
        <p>Please enter a new name for this page:</p>
        <p><input type="text" name="newname" size="40" /></p>
        <p><input type="submit" value="Rename page" style="font-weight: bold;" /></p> 
      </form>
      <?php
      $template->footer();    
      break;
    case 'flushlogs':
      if(!$session->get_permissions('clear_logs')) die_friendly('Access denied', '<p>Flushing the logs for a page <u>requires</u> administrative rights.</p>');
      if(isset($_POST['_downthejohn']))
      {
        $template->header();
          $result = PageUtils::flushlogs($paths->cpage['urlname_nons'], $paths->namespace);
          echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=flushlogs'); ?>" method="post">
          <h3>You are about to <span style="color: red;">destroy</span> all logged edits and actions on this page.</h3>
           <p>Unlike deleting or editing this page, this action is <u>not reversible</u>! You should only do this if you are desperate for
              database space.</p>
           <p>Do you really want to continue?</p>
           <p><input type="submit" name="_downthejohn" value="Flush logs" style="color: red; font-weight: bold;" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'delvote':
      if(isset($_POST['_ballotbox']))
      {
        $template->header();
        $result = PageUtils::delvote($paths->cpage['urlname_nons'], $paths->namespace);
        echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=delvote'); ?>" method="post">
          <h3>Your vote counts.</h3>
           <p>If you think that this page is not relavent to the content on this site, or if it looks like this page was only created in
              an attempt to spam the site, you can request that this page be deleted by an administrator.</p>
           <p>After you vote, you should leave a comment explaining the reason for your vote, especially if you are the first person to
              vote against this page.</p>
           <p>So far, <?php echo ( $paths->cpage['delvotes'] == 1 ) ? $paths->cpage['delvotes'] . ' person has' : $paths->cpage['delvotes'] . ' people have'; ?> voted to delete this page.</p>
           <p><input type="submit" name="_ballotbox" value="Vote to delete this page" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'resetvotes':
      if(!$session->get_permissions('vote_reset')) die_friendly('Access denied', '<p>Resetting the deletion votes against this page <u>requires</u> admin rights.</p>');
      if(isset($_POST['_youmaylivealittlelonger']))
      {
        $template->header();
          $result = PageUtils::resetdelvotes($paths->cpage['urlname_nons'], $paths->namespace);
          echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>';
        $template->footer();
        break;
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=resetvotes'); ?>" method="post">
          <p>This action will reset the number of votes against this page to zero. Are you sure you want to do this?</p>
          <p><input type="submit" name="_youmaylivealittlelonger" value="Reset votes" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'deletepage':
      if(!$session->get_permissions('delete_page')) die_friendly('Access denied', '<p>Deleting pages <u>requires</u> admin rights.</p>');
      if(isset($_POST['_adiossucker']))
      {
        $reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
        if ( empty($reason) )
          $error = 'Please enter a reason for deleting this page.';
        else
        {
          $template->header();
            $result = PageUtils::deletepage($paths->cpage['urlname_nons'], $paths->namespace, $reason);
            echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">Return to the page</a>.</p>';
          $template->footer();
          break;
        }
      }
      $template->header();
        ?>
        <form action="<?php echo makeUrl($paths->page, 'do=deletepage'); ?>" method="post">
          <h3>You are about to <span style="color: red;">destroy</span> this page.</h3>
           <p>While the deletion of the page itself is completely reversible, it is impossible to recover any comments or category information on this page. If this is a file page, the file along with all older revisions of it will be permanently deleted. Also, any custom information that this page is tagged with, such as a custom name, protection status, or additional settings such as whether to allow comments, will be permanently lost.</p>
           <p>Are you <u>absolutely sure</u> that you want to continue?<br />
              You will not be asked again.</p>
           <?php if ( isset($error) ) echo "<p>$error</p>"; ?>
           <p>Reason for deleting: <input type="text" name="reason" size="50" /></p>
           <p><input type="submit" name="_adiossucker" value="Delete this page" style="color: red; font-weight: bold;" /></p>
        </form>
        <?php
      $template->footer();
      break;
    case 'setwikimode':
      if(!$session->get_permissions('set_wiki_mode')) die_friendly('Access denied', '<p>Changing the wiki mode setting <u>requires</u> admin rights.</p>');
      if(!isset($_GET['level']) || ( isset($_GET['level']) && !preg_match('#^([0-9])$#', $_GET['level']))) die_friendly('Invalid request', '<p>Level not specified</p>');
      $template->header();
      $template->footer();
      break;
    case 'diff':
      $template->header();
      $id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
      $id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
      if(!$id1 || !$id2) { echo '<p>Invalid request.</p>'; $template->footer(); break; }
      if(!preg_match('#^([0-9]+)$#', (string)$_GET['diff1']) ||
         !preg_match('#^([0-9]+)$#', (string)$_GET['diff2']  )) { echo '<p>SQL injection attempt</p>'; $template->footer(); break; }
      echo PageUtils::pagediff($paths->cpage['urlname_nons'], $paths->namespace, $id1, $id2);
      $template->footer();
      break;
    case 'aclmanager':
      $data = ( isset($_POST['data']) ) ? $_POST['data'] : Array('mode' => 'listgroups');
      PageUtils::aclmanager($data);
      break;
  }
  
  //
  // Optimize HTML by replacing newlines with spaces (excludes <pre>, <script>, and <style> blocks)
  //
  if ($aggressive_optimize_html)
  {
    // Load up the HTML
    $html = ob_get_contents();
    ob_end_clean();
    
    // Which tags to strip - you can change this if needed
    $strip_tags = Array('pre', 'script', 'style', 'enano:no-opt');
    $strip_tags = implode('|', $strip_tags);
    
    // Strip out the tags and replace with placeholders
    preg_match_all("#<($strip_tags)(.*?)>(.*?)</($strip_tags)>#is", $html, $matches);
    $seed = md5(microtime() . mt_rand()); // Random value used for placeholders
    for ($i = 0;$i < sizeof($matches[1]); $i++)
    {
      $html = str_replace("<{$matches[1][$i]}{$matches[2][$i]}>{$matches[3][$i]}</{$matches[4][$i]}>", "{DONT_STRIP_ME_NAKED:$seed:$i}", $html);
    }
    
    // Finally, process the HTML
    $html = preg_replace("#\n([ ]*)#", " ", $html);
    
    // Remove annoying spaces between tags
    $html = preg_replace("#>([ ]*?){2,}<#", "> <", $html);
    
    // Re-insert untouchable tags
    for ($i = 0;$i < sizeof($matches[1]); $i++)
    {
      $html = str_replace("{DONT_STRIP_ME_NAKED:$seed:$i}", "<{$matches[1][$i]}{$matches[2][$i]}>{$matches[3][$i]}</{$matches[4][$i]}>", $html);
    }
    
    // Remove <enano:no-opt> blocks (can be used by themes that don't want their HTML optimized)
    $html = preg_replace('#<(\/|)enano:no-opt(.*?)>#', '', $html);
    
    // Tell snoopish users what's going on
    $html = str_replace('<html>', "\n<!-- NOTE: This HTML document has been Aggressively Optimized(TM) by Enano to make page loading faster. -->\n<html>", $html);
    
    // Re-enable output buffering to allow the Gzip function (below) to work
    ob_start();
    
    // Done, send it to the user
    echo( $html );
  }
  
  //
  // Compress buffered output if required and send to browser
  //
  if ( $do_gzip )
  {
    //
    // Copied from phpBB, which was in turn borrowed from php.net
    //
    $gzip_contents = ob_get_contents();
    ob_end_clean();
  
    $gzip_size = strlen($gzip_contents);
    $gzip_crc = crc32($gzip_contents);
  
    $gzip_contents = gzcompress($gzip_contents, 9);
    $gzip_contents = substr($gzip_contents, 0, strlen($gzip_contents) - 4);
  
    header('Content-encoding: gzip');
    echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
    echo $gzip_contents;
    echo pack('V', $gzip_crc);
    echo pack('V', $gzip_size);
  }
  
  $db->close();

?>
