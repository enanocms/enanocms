<?php
/*
Plugin Name: Private Message frontend
Plugin URI: http://enanocms.org/
Description: Provides the page Special:PrivateMessages, which is used to manage private message functions. Also handles buddy lists.
Author: Dan Fuhry
Version: 1.0.1
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 2
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('base_classes_initted', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'Private Messages\',
      \'urlname\'=>\'PrivateMessages\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

function page_Special_PrivateMessages()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!$session->user_logged_in) die_friendly('Access denied', '<p>You need to <a href="'.makeUrlNS('Special', 'Login/'.$paths->page).'">log in</a> to view your private messages.</p>');
  $argv = Array();
  $argv[] = $paths->getParam(0);
  $argv[] = $paths->getParam(1);
  $argv[] = $paths->getParam(2);
  if(!$argv[0]) $argv[0] = 'InVaLiD';
  switch($argv[0])
  {
    default:
      header('Location: '.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox'));
      break;
    case 'View':
      $id = $argv[1];
      if(!preg_match('#^([0-9]+)$#', $id)) die_friendly('Message error', '<p>Invalid message ID</p>');
      $q = $db->sql_query('SELECT p.message_from, p.message_to, p.subject, p.message_text, p.date, p.folder_name, u.signature FROM '.table_prefix.'privmsgs AS p LEFT JOIN '.table_prefix.'users AS u ON (p.message_from=u.username) WHERE message_id='.$id.'');
      if(!$q) $db->_die('The message data could not be selected.');
      $r = $db->fetchrow();
      $db->free_result();
      if( ($r['message_to'] != $session->username && $r['message_from'] != $session->username ) || $r['folder_name']=='drafts' ) die_friendly('Access denied', '<p>You are not authorized to view this message.</p>');
      if($r['message_to'] == $session->username)
      {
        $q = $db->sql_query('UPDATE '.table_prefix.'privmsgs SET message_read=1 WHERE message_id='.$id.'');
        $db->free_result();
        if(!$q) $db->_die('Could not mark message as read');
      }
      $template->header();
      userprefs_show_menu();
      ?>
        <br />
        <div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Private message from <?php echo $r['message_from']; ?></th></tr>
          <tr><td class="row1">Subject:</td><td class="row1"><?php echo $r['subject']; ?></td></tr>
          <tr><td class="row2">Date:</td><td class="row2"><?php echo date('M j, Y G:i', $r['date']); ?></td></tr>
          <tr><td class="row1">Message:</td><td class="row1"><?php echo RenderMan::render($r['message_text']);
          if($r['signature'] != '')
          {
            echo '<hr style="margin-left: 1em; width: 200px;" />';
            echo RenderMan::render($r['signature']);
          }
          ?></td></tr>
          <tr><td colspan="2" class="row3"><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/Compose/ReplyTo/'.$id); ?>">Send reply</a>  |  <a href="<?php echo makeUrlNS('Special', 'PrivateMessages/Delete/'.$id); ?>">Delete message</a>  |  <?php if($r['folder_name'] != 'archive') { ?><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/Move/'.$id.'/Archive'); ?>">Archive message</a>  |  <?php } ?><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/Folder/Inbox') ?>">Return to inbox</a></td></tr>
        </table></div>
      <?php
      $template->footer();              
      break;
    case 'Move':
      $id = $argv[1];
      if(!preg_match('#^([0-9]+)$#', $id)) die_friendly('Message error', '<p>Invalid message ID</p>');
      $q = $db->sql_query('SELECT message_to FROM '.table_prefix.'privmsgs WHERE message_id='.$id.'');
      if(!$q) $db->_die('The message data could not be selected.');
      $r = $db->fetchrow();
      $db->free_result();
      if($r['message_to'] != $session->username) die_friendly('Access denied', '<p>You are not authorized to alter this message.</p>');
      $fname = $argv[2];
      if(!$fname || ( $fname != 'Inbox' && $fname != 'Outbox' && $fname != 'Sent' && $fname != 'Drafts' && $fname != 'Archive' ) ) die_friendly('Invalid request', '<p>The folder name "'.$fname.'" is invalid.</p>');
      $q = $db->sql_query('UPDATE '.table_prefix.'privmsgs SET folder_name=\''.strtolower($fname).'\' WHERE message_id='.$id.';');
      $db->free_result();
      if(!$q) $db->_die('The message was not successfully moved.');
      die_friendly('Message status', '<p>Your message has been moved to the folder "'.$fname.'".</p><p><a href="'.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox').'">Return to inbox</a></p>');
      break;
    case 'Delete':
      $id = $argv[1];
      if(!preg_match('#^([0-9]+)$#', $id)) die_friendly('Message error', '<p>Invalid message ID</p>');
      $q = $db->sql_query('SELECT message_to FROM '.table_prefix.'privmsgs WHERE message_id='.$id.'');
      if(!$q) $db->_die('The message data could not be selected.');
      $r = $db->fetchrow();
      if($r['message_to'] != $session->username) die_friendly('Access denied', '<p>You are not authorized to delete this message.</p>');
      $q = $db->sql_query('DELETE FROM '.table_prefix.'privmsgs WHERE message_id='.$id.';');
      if(!$q) $db->_die('The message was not successfully deleted.');
      $db->free_result();
      die_friendly('Message status', '<p>The message has been deleted.</p><p><a href="'.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox').'">Return to inbox</a></p>');
      break;
    case 'Compose':
      if($argv[1]=='Send' && isset($_POST['_send']))
      {
        // Check each POST DATA parameter...
        if(!isset($_POST['to']) || ( isset($_POST['to']) && $_POST['to'] == '')) die_friendly('Sending of message failed', '<p>Please enter the username to which you want to send your message.</p>');
        if(!isset($_POST['subject']) || ( isset($_POST['subject']) && $_POST['subject'] == '')) die_friendly('Sending of message failed', '<p>Please enter a subject for your message.</p>');
        if(!isset($_POST['message']) || ( isset($_POST['message']) && $_POST['message'] == '')) die_friendly('Sending of message failed', '<p>Please enter a message to send.</p>');
        $namelist = $_POST['to'];
        $namelist = str_replace(', ', ',', $namelist);
        $namelist = explode(',', $namelist);
        foreach($namelist as $n) { $n = $db->escape($n); }
        $subject = RenderMan::preprocess_text($_POST['subject']);
        $message = RenderMan::preprocess_text($_POST['message']);
        $base_query = 'INSERT INTO '.table_prefix.'privmsgs(message_from,message_to,date,subject,message_text,folder_name,message_read) VALUES';
        foreach($namelist as $n)
        {
          $base_query .= '(\''.$session->username.'\', \''.$n.'\', '.time().', \''.$subject.'\', \''.$message.'\', \'inbox\', 0),';
        }
        $base_query = substr($base_query, 0, strlen($base_query)-1) . ';';
        $result = $db->sql_query($base_query);
        $db->free_result();
        if(!$result) $db->_die('The message could not be sent.');
        else die_friendly('Message status', '<p>Your message has been sent. You may edit the message if you wish; one copy for each recipient will be in your outbox until each recipient has read it. Return to your <a href="'.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox').'">inbox</a>.</p>');
        return;
      } elseif($argv[1]=='Send' && isset($_POST['_savedraft'])) {
        // Check each POST DATA parameter...
        if(!isset($_POST['to']) || ( isset($_POST['to']) && $_POST['to'] == '')) die_friendly('Sending of message failed', '<p>Please enter the username to which you want to send your message.</p>');
        if(!isset($_POST['subject']) || ( isset($_POST['subject']) && $_POST['subject'] == '')) die_friendly('Sending of message failed', '<p>Please enter a subject for your message.</p>');
        if(!isset($_POST['message']) || ( isset($_POST['message']) && $_POST['message'] == '')) die_friendly('Sending of message failed', '<p>Please enter a message to send.</p>');
        $namelist = $_POST['to'];
        $namelist = str_replace(', ', ',', $namelist);
        $namelist = explode(',', $namelist);
        foreach($namelist as $n) { $n = $db->escape($n); }
        if(count($namelist) > MAX_PMS_PER_BATCH && $session->get_permssions('mod_misc')) die_friendly('Limit exceeded', '<p>You can only send this message to a maximum of '.MAX_PMS_PER_BATCH.' users.</p>');
        $subject = $db->escape($_POST['subject']);
        $message = RenderMan::preprocess_text($_POST['message']);
        $base_query = 'INSERT INTO '.table_prefix.'privmsgs(message_from,message_to,date,subject,message_text,folder_name,message_read) VALUES';
        foreach($namelist as $n)
        {
          $base_query .= '(\''.$session->username.'\', \''.$n.'\', '.time().', \''.$subject.'\', \''.$message.'\', \'drafts\', 0),';
        }
        $base_query = substr($base_query, 0, strlen($base_query)-1) . ';';
        $result = $db->sql_query($base_query);
        $db->free_result();
        if(!$result) $db->_die('The message could not be saved.');
      } elseif(isset($_POST['_inbox'])) {
        header('Location: '.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox'));
      }
      if($argv[1] == 'ReplyTo' && preg_match('#^([0-9]+)$#', $argv[2]))
      {
        $to = '';
        $text = '';
        $subj = '';
        $id = $argv[2];
        $q = $db->sql_query('SELECT p.message_from, p.message_to, p.subject, p.message_text, p.date, p.folder_name, u.signature FROM '.table_prefix.'privmsgs AS p LEFT JOIN '.table_prefix.'users AS u ON (p.message_from=u.username) WHERE message_id='.$id.';');
        if(!$q) $db->_die('The message data could not be selected.');
        $r = $db->fetchrow();
        $db->free_result();
        if( ($r['message_to'] != $session->username && $r['message_from'] != $session->username ) || $r['folder_name']=='drafts' ) die_friendly('Access denied', '<p>You are not authorized to view the contents of this message.</p>');
        $subj = 'Re: ' . $r['subject'];
        $text = "\n\n\nOn ".date('M j, Y G:i', $r['date']).", ".$r['message_from']." wrote:\n> ".str_replace("\n", "\n> ", $r['message_text']); // Way less complicated than using a regex ;-)
        
        $tbuf = $text;
        while( preg_match("/\n([\> ]*?)\> \>/", $text) )
        {
          $text = preg_replace("/\n([\> ]*?)\> \>/", '\\1>>', $text);
          if ( $text == $tbuf )
            break;
          $tbuf = $text;
        }
        
        $to = $r['message_from'];
      } else {
        if(( $argv[1]=='to' || $argv[1]=='To' ) && $argv[2]) $to = $argv[2];
        else $to = '';
        $text = '';
        $subj = '';
      }
        $template->header();
        userprefs_show_menu();
        echo '<form action="'.makeUrlNS('Special', 'PrivateMessages/Compose/Send').'" method="post" onsubmit="if(!submitAuthorized) return false;">';
        ?>
        <br />
        <div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Compose new private message</th></tr>
          <tr><td class="row1">To:<br /><small>Separate multiple names with a single comma; you<br />can send this message to up to <b><?php echo (string)MAX_PMS_PER_BATCH; ?></b> users.</small></td><td class="row1"><?php echo $template->username_field('to', (isset($_POST['_savedraft'])) ? $_POST['to'] : $to ); ?></td></tr>
          <tr><td class="row2">Subject:</td><td class="row2"><input name="subject" type="text" size="30" value="<?php if(isset($_POST['_savedraft'])) echo $_POST['subject']; else echo $subj; ?>" /></td></tr>
          <tr><td class="row1">Message:</td><td class="row1" style="min-width: 80%;"><textarea rows="20" cols="40" name="message" style="width: 100%;"><?php if(isset($_POST['_savedraft'])) echo $_POST['message']; else echo $text; ?></textarea></td></tr>
          <tr><th colspan="2"><input type="submit" name="_send" value="Send message" />  <input type="submit" name="_savedraft" value="Save as draft" /> <input type="submit" name="_inbox" value="Back to Inbox" /></th></tr>
        </table></div>
        <?php
        echo '</form>';
        $template->footer();
      break;
    case 'Edit':
      $id = $argv[1];
      if(!preg_match('#^([0-9]+)$#', $id)) die_friendly('Message error', '<p>Invalid message ID</p>');
      $q = $db->sql_query('SELECT message_from, message_to, subject, message_text, date, folder_name, message_read FROM '.table_prefix.'privmsgs WHERE message_id='.$id.'');
      if(!$q) $db->_die('The message data could not be selected.');
      $r = $db->fetchrow();
      $db->free_result();
      if($r['message_from'] != $session->username || $r['message_read'] == 1 ) die_friendly('Access denied', '<p>You are not authorized to edit this message.</p>');
      $fname = $argv[2];
      
      if(isset($_POST['_send']))
      {
        // Check each POST DATA parameter...
        if(!isset($_POST['to']) || ( isset($_POST['to']) && $_POST['to'] == '')) die_friendly('Sending of message failed', '<p>Please enter the username to which you want to send your message.</p>');
        if(!isset($_POST['subject']) || ( isset($_POST['subject']) && $_POST['subject'] == '')) die_friendly('Sending of message failed', '<p>Please enter a subject for your message.</p>');
        if(!isset($_POST['message']) || ( isset($_POST['message']) && $_POST['message'] == '')) die_friendly('Sending of message failed', '<p>Please enter a message to send.</p>');
        $namelist = $_POST['to'];
        $namelist = str_replace(', ', ',', $namelist);
        $namelist = explode(',', $namelist);
        foreach($namelist as $n) { $n = $db->escape($n); }
        $subject = RenderMan::preprocess_text($_POST['subject']);
        $message = RenderMan::preprocess_text($_POST['message']);
        $base_query = 'UPDATE '.table_prefix.'privmsgs SET subject=\''.$subject.'\',message_to=\''.$namelist[0].'\',message_text=\''.$message.'\',folder_name=\'inbox\' WHERE message_id='.$id.';';
        $result = $db->sql_query($base_query);
        $db->free_result();
        if(!$result) $db->_die('The message could not be sent.');
        else die_friendly('Message status', '<p>Your message has been sent. You may edit the message if you wish; one copy for each recipient will be in your outbox until each recipient has read it. Return to your <a href="'.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox').'">inbox</a>.</p>');
        return;
      } elseif(isset($_POST['_savedraft'])) {
        // Check each POST DATA parameter...
        if(!isset($_POST['to']) || ( isset($_POST['to']) && $_POST['to'] == '')) die_friendly('Sending of message failed', '<p>Please enter the username to which you want to send your message.</p>');
        if(!isset($_POST['subject']) || ( isset($_POST['subject']) && $_POST['subject'] == '')) die_friendly('Sending of message failed', '<p>Please enter a subject for your message.</p>');
        if(!isset($_POST['message']) || ( isset($_POST['message']) && $_POST['message'] == '')) die_friendly('Sending of message failed', '<p>Please enter a message to send.</p>');
        $namelist = $_POST['to'];
        $namelist = str_replace(', ', ',', $namelist);
        $namelist = explode(',', $namelist);
        foreach($namelist as $n) { $n = $db->escape($n); }
        $subject = $db->escape($_POST['subject']);
        $message = RenderMan::preprocess_text($_POST['message']);
        $base_query = 'UPDATE '.table_prefix.'privmsgs SET subject=\''.$subject.'\',message_to=\''.$namelist[0].'\',message_text=\''.$message.'\' WHERE message_id='.$id.';';
        $result = $db->sql_query($base_query);
        $db->free_result();
        if(!$result) $db->_die('The message could not be saved.');
      }
        if($argv[1]=='to' && $argv[2]) $to = $argv[2];
        else $to = '';
        $template->header();
        userprefs_show_menu();
        echo '<form action="'.makeUrlNS('Special', 'PrivateMessages/Edit/'.$id).'" method="post">';
        ?>
        <br />
        <div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Edit draft</th></tr>
          <tr><td class="row1">To:<br /><small>Separate multiple names with a single comma</small></td><td class="row1"><input name="to" type="text" size="30" value="<?php if(isset($_POST['_savedraft'])) echo $_POST['to']; else echo $r['message_to']; ?>" /></td></tr>
          <tr><td class="row2">Subject:</td><td class="row2"><input name="subject" type="text" size="30" value="<?php if(isset($_POST['_savedraft'])) echo $_POST['subject']; else echo $r['subject']; ?>" /></td></tr>
          <tr><td class="row1">Message:</td><td class="row1"><textarea rows="20" cols="40" name="message" style="width: 100%;"><?php if(isset($_POST['_savedraft'])) echo $_POST['message']; else echo $r['message_text']; ?></textarea></td></tr>
          <tr><th colspan="2"><input type="submit" name="_send" value="Send message" />  <input type="submit" name="_savedraft" value="Save as draft" /></th></tr>
        </table></div>
        <?php
        echo '</form>';
        $template->footer();
      break;
    case 'Folder':
      $template->header();
      userprefs_show_menu();
      switch($argv[1])
      {
        default:
          echo '<p>The folder "'.$argv[1].'" does not exist. Return to your <a href="'.makeUrlNS('Special', 'PrivateMessages/Folder/Inbox').'">inbox</a>.</p>';
          break;
        case 'Inbox':
        case 'Outbox':
        case 'Sent':
        case 'Drafts':
        case 'Archive':
          ?>
          <table border="0" width="100%" cellspacing="10" cellpadding="0">
          <tr>
          <td style="padding: 0px; width: 120px;" valign="top"  >
          <div class="tblholder" style="width: 120px;"><table border="0" width="120" cellspacing="1" cellpadding="4">
          <tr><th><small>Private messages</small></th></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Inbox'); ?>">Inbox</a>    </small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Outbox'); ?>">Outbox</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Sent'); ?>">Sent Items</a></small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Drafts'); ?>">Drafts</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Archive'); ?>">Archive</a></small></td></tr>
          <tr><th><small>Buddies</small></th></tr>
          <tr><td class="row2"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FriendList'); ?>">Friend list</a></small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FoeList'); ?>">Foe list</a></small></td></tr>
          </table></div>
          </td>
          <td valign="top">
          <?php
          $fname = strtolower($argv[1]);
          switch($argv[1])
          {
            case 'Inbox':
            case 'Archive':
            default:
              $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject, p.message_read FROM '.table_prefix.'privmsgs AS p WHERE p.folder_name=\''.$fname.'\' AND p.message_to=\''.$session->username.'\' ORDER BY date DESC;');
              break;
            case 'Outbox':
              $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject, p.message_read FROM '.table_prefix.'privmsgs AS p WHERE p.message_from=\''.$session->username.'\' AND message_read=0 ORDER BY date DESC;');
              break;
            case 'Sent':
              $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject, p.message_read FROM '.table_prefix.'privmsgs AS p WHERE p.message_from=\''.$session->username.'\' AND message_read=1 ORDER BY date DESC;');
              break;
            case 'Drafts':
              $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject, p.message_read FROM '.table_prefix.'privmsgs AS p WHERE p.folder_name=\''.$fname.'\' AND p.message_from=\''.$session->username.'\' ORDER BY date DESC;');
              break;
          }
          if($argv[1] == 'Drafts' || $argv[1] == 'Outbox') $act = 'Edit';
          else $act = 'View';
          if(!$q) $db->_die('The private message data could not be selected.');
          echo '<form action="'.makeUrlNS('Special', 'PrivateMessages/PostHandler').'" method="post"><div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4"><tr><th colspan="4" style="text-align: left;">Folder: '.$argv[1].'</th></tr><tr><th class="subhead">';
          if($fname == 'drafts' || $fname == 'Outbox') echo 'To'; else echo 'From';
          echo '</th><th class="subhead">Subject</th><th class="subhead">Date</th><th class="subhead">Mark</th></tr>';
          if($db->numrows() < 1)
            echo '<tr><td style="text-align: center;" class="row1" colspan="4">No messages in this folder.</td></tr>';
          else {
            $cls = 'row2';
            while($r = $db->fetchrow())
            {
              if($cls == 'row2') $cls='row1';
              else $cls = 'row2';
              $mto = str_replace(' ', '_', $r['message_to']);
              $mfr = str_replace(' ', '_', $r['message_from']);
              echo '<tr><td class="'.$cls.'"><a href="'.makeUrlNS('User', ( $fname == 'drafts') ? $mto : $mfr).'">';
              if($fname == 'drafts' || $fname == 'outbox') echo $r['message_to']; else echo $r['message_from'];
              echo '</a></td><td class="'.$cls.'"><a href="'.makeUrlNS('Special', 'PrivateMessages/'.$act.'/'.$r['message_id']).'">';
              if($r['message_read'] == 0) echo '<b>';
              echo $r['subject'];
              if($r['message_read'] == 0) echo '</b>';
              echo '</a></td><td class="'.$cls.'">'.date('M j, Y G:i', $r['date']).'</td><td class="'.$cls.'" style="text-align: center;"><input name="marked_'.$r['message_id'].'" type="checkbox" /></td></tr>';
            }
            $db->free_result();
          }
          echo '<tr><th style="text-align: right;" colspan="4"><input type="hidden" name="folder" value="'.$fname.'" /><input type="submit" name="archive" value="Archive selected" /> <input type="submit" name="delete" value="Delete selected" /> <input type="submit" name="deleteall" value="Delete all" /></th></tr>';
          echo '</table></div></form>
          <br />
          <a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/').'">New message</a>
          </td></tr></table>';
          break;
      }
      $template->footer();
      break;
    case 'PostHandler':
      $fname = $db->escape(strtolower($_POST['folder']));
      if($fname=='drafts' || $fname=='outbox')
      {
        $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject FROM '.table_prefix.'privmsgs AS p WHERE p.folder_name=\''.$fname.'\' AND p.message_from=\''.$session->username.'\' ORDER BY date DESC;');  
      } else {
        $q = $db->sql_query('SELECT p.message_id, p.message_from, p.message_to, p.date, p.subject FROM '.table_prefix.'privmsgs AS p WHERE p.folder_name=\''.$fname.'\' AND p.message_to=\''.$session->username.'\' ORDER BY date DESC;');
      }
      if(!$q) $db->_die('The private message data could not be selected.');
          
      if(isset($_POST['archive'])) {
        while($row = $db->fetchrow($q))
        {
          if(isset($_POST['marked_'.$row['message_id']]))
          {
            $e = $db->sql_query('UPDATE '.table_prefix.'privmsgs SET folder_name=\'archive\' WHERE message_id='.$row['message_id'].';');
            if(!$e) $db->_die('Message '.$row['message_id'].' was not successfully moved.');
            $db->free_result();
          }
        }
      } elseif(isset($_POST['delete'])) {
        while($row = $db->fetchrow($q))
        {
          if(isset($_POST['marked_'.$row['message_id']]))
          {
            $e = $db->sql_query('DELETE FROM '.table_prefix.'privmsgs WHERE message_id='.$row['message_id'].';');
            if(!$e) $db->_die('Message '.$row['message_id'].' was not successfully moved.');
            $db->free_result();
          }
        }
      } elseif(isset($_POST['deleteall'])) {
        while($row = $db->fetchrow($q))
        {
          $e = $db->sql_query('DELETE FROM '.table_prefix.'privmsgs WHERE message_id='.$row['message_id'].';');
          if(!$e) $db->_die('Message '.$row['message_id'].' was not successfully moved.');
          $db->free_result();
        }
      } else {
        die_friendly('Invalid request', 'This section can only be accessed from within another Private Message section.');
      }
      $db->free_result($q);
      header('Location: '.makeUrlNS('Special', 'PrivateMessages/Folder/'. substr(strtoupper($_POST['folder']), 0, 1) . substr(strtolower($_POST['folder']), 1, strlen($_POST['folder'])) ));
      break;
    case 'FriendList':
      if($argv[1] == 'Add')
      {
        if(isset($_POST['_go']))
          $buddyname = $_POST['buddyname'];
        elseif($argv[2])
          $buddyname = $argv[2];
        else
          die_friendly('Error adding buddy', '<p>No name specified</p>');
        $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($buddyname).'\'');
        if(!$q) $db->_die('The buddy\'s user ID could not be selected.');
        if($db->numrows() < 1) echo '<h3>Error adding buddy</h3><p>The username you entered is not in use by any registered user.</p>';
        {
          $r = $db->fetchrow();
          $db->free_result();
          $q = $db->sql_query('INSERT INTO '.table_prefix.'buddies(user_id,buddy_user_id,is_friend) VALUES('.$session->user_id.', '.$r['user_id'].', 1);');
          if(!$q) echo '<h3>Warning:</h3><p>Buddy could not be added: '.mysql_error().'</p>';
          $db->free_result();
        }
      } elseif($argv[1] == 'Remove' && preg_match('#^([0-9]+)$#', $argv[2])) {
        // Using WHERE user_id prevents users from deleting others' buddies
        $q = $db->sql_query('DELETE FROM '.table_prefix.'buddies WHERE user_id='.$session->user_id.' AND buddy_id='.$argv[2].';');
        $db->free_result();
        if(!$q) echo '<h3>Warning:</h3><p>Buddy could not be deleted: '.mysql_error().'</p>';
        if(mysql_affected_rows() < 1) echo '<h3>Warning:</h3><p>No rows were affected. Either the selected buddy ID does not exist or you tried to delete someone else\'s buddy.</p>';
      }
      $template->header();
      userprefs_show_menu();
      ?>
      <table border="0" width="100%" cellspacing="10" cellpadding="0">
          <tr>
          <td style="padding: 0px; width: 120px;" valign="top"  >
          <div class="tblholder" style="width: 120px;"><table border="0" width="120" cellspacing="1" cellpadding="4">
          <tr><th><small>Private messages</small></th></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Inbox'); ?>">Inbox</a>    </small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Outbox'); ?>">Outbox</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Sent'); ?>">Sent Items</a></small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Drafts'); ?>">Drafts</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Archive'); ?>">Archive</a></small></td></tr>
          <tr><th><small>Buddies</small></th></tr>
          <tr><td class="row2"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FriendList'); ?>">Friend list</a></small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FoeList'); ?>">Foe list</a></small></td></tr>
          </table></div>
          </td>
          <td valign="top">
        <?php
        $q = $db->sql_query('SELECT u.username,b.buddy_id FROM '.table_prefix.'buddies AS b LEFT JOIN '.table_prefix.'users AS u ON ( u.user_id=b.buddy_user_id ) WHERE b.user_id='.$session->user_id.' AND is_friend=1;');
        if(!$q) $db->_die('The buddy list could not be selected.');
        else 
        {
          $allbuds = '';
          echo '<br /><div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4"><tr><th colspan="3">Buddy list for '.$session->username.'</th></tr>';
          if($db->numrows() < 1) echo '<tr><td class="row3">No buddies in your list.</td></tr>';
          $cls = 'row2';
          while ( $row = $db->fetchrow() )
          {
            if($cls=='row2') $cls = 'row1';
            else $cls = 'row2';
            echo '<tr><td class="'.$cls.'"><a href="'.makeUrlNS('User', str_replace(' ', '_', $row['username'])).'" '. ( isPage($paths->nslist['User'].str_replace(' ', '_', $row['username'])) ? '' : 'class="wikilink-nonexistent" ' ) .'>'.$row['username'].'</a></td><td class="'.$cls.'"><a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/to/'.str_replace(' ', '_', $row['username'])).'">Send private message</a></td><td class="'.$cls.'"><a onclick="return confirm(\'Are you sure you want to delete this user from your buddy list?\')" href="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Remove/'.$row['buddy_id']).'">Remove</a></td></tr>';
            $allbuds .= str_replace(' ', '_', $row['username']).',';
          }
          $db->free_result();
          $allbuds = substr($allbuds, 0, strlen($allbuds)-1);
          if($cls=='row2') $cls = 'row1';
          else $cls = 'row2';
          echo '<tr><td colspan="3" class="'.$cls.'" style="text-align: center;"><a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/to/'.$allbuds).'">Send a PM to all buddies</a></td></tr>';
          echo '</table></div>';
        }
        echo '<form action="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Add').'" method="post" onsubmit="if(!submitAuthorized) return false;">
              <h3>Add a new friend</h3>';
        echo '<p>Username: '.$template->username_field('buddyname').'  <input type="submit" name="_go" value="Add" /></p>';
        echo '</form>';
        ?>
        </td>
        </tr>
        </table>
        <?php
      $template->footer();
      break;
    case 'FoeList':
      if($argv[1] == 'Add' && isset($_POST['_go']))
      {
        $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($_POST['buddyname']).'\'');
        if(!$q) $db->_die('The buddy\'s user ID could not be selected.');
        if($db->numrows() < 1) echo '<h3>Error adding buddy</h3><p>The username you entered is not in use by any registered user.</p>';
        {
          $r = $db->fetchrow();
          $q = $db->sql_query('INSERT INTO '.table_prefix.'buddies(user_id,buddy_user_id,is_friend) VALUES('.$session->user_id.', '.$r['user_id'].', 0);');
          if(!$q) echo '<h3>Warning:</h3><p>Buddy could not be added: '.mysql_error().'</p>';
        }
        $db->free_result();
      } elseif($argv[1] == 'Remove' && preg_match('#^([0-9]+)$#', $argv[2])) {
        // Using WHERE user_id prevents users from deleting others' buddies
        $q = $db->sql_query('DELETE FROM '.table_prefix.'buddies WHERE user_id='.$session->user_id.' AND buddy_id='.$argv[2].';');
        $db->free_result();
        if(!$q) echo '<h3>Warning:</h3><p>Buddy could not be deleted: '.mysql_error().'</p>';
        if(mysql_affected_rows() < 1) echo '<h3>Warning:</h3><p>No rows were affected. Either the selected buddy ID does not exist or you tried to delete someone else\'s buddy.</p>';
      }
      $template->header();
      userprefs_show_menu();
      ?>
      <table border="0" width="100%" cellspacing="10" cellpadding="0">
          <tr>
          <td style="padding: 0px; width: 120px;" valign="top"  >
          <div class="tblholder" style="width: 120px;"><table border="0" width="120" cellspacing="1" cellpadding="4">
          <tr><th><small>Private messages</small></th></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Inbox'); ?>">Inbox</a>    </small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Outbox'); ?>">Outbox</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Sent'); ?>">Sent Items</a></small></td></tr>
          <tr><td class="row2"><small><a href="<?php echo $session->append_sid('Drafts'); ?>">Drafts</a>  </small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo $session->append_sid('Archive'); ?>">Archive</a></small></td></tr>
          <tr><th><small>Buddies</small></th></tr>
          <tr><td class="row2"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FriendList'); ?>">Friend list</a></small></td></tr>
          <tr><td class="row1"><small><a href="<?php echo makeUrlNS('Special', 'PrivateMessages/FoeList'); ?>">Foe list</a></small></td></tr>
          </table></div>
          </td>
          <td valign="top">
        <?php
        $q = $db->sql_query('SELECT u.username,b.buddy_id FROM '.table_prefix.'buddies AS b LEFT JOIN '.table_prefix.'users AS u ON ( u.user_id=b.buddy_user_id ) WHERE b.user_id='.$session->user_id.' AND is_friend=0;');
        if(!$q) $db->_die('The buddy list could not be selected.');
        else 
        {
          $allbuds = '';
          echo '<br /><div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4"><tr><th colspan="3">Foe list for '.$session->username.'</th></tr>';
          if($db->numrows() < 1) echo '<tr><td class="row2">No foes in your list.</td></tr>';
          $cls = 'row2';
          while ( $row = $db->fetchrow() )
          {
            if($cls=='row2') $cls = 'row1';
            else $cls = 'row2';
            echo '<tr><td class="'.$cls.'"><a href="'.makeUrlNS('User', str_replace(' ', '_', $row['username'])).'" '. ( isPage($paths->nslist['User'].str_replace(' ', '_', $row['username'])) ? '' : 'class="wikilink-nonexistent" ' ) .'>'.$row['username'].'</a></td><td class="'.$cls.'"><a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/to/'.str_replace(' ', '_', $row['username'])).'">Send private message</a></td><td class="'.$cls.'"><a onclick="return confirm(\'Are you sure you want to delete this user from your buddy list?\')" href="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Remove/'.$row['buddy_id']).'">Remove</a></td></tr>';
            $allbuds .= str_replace(' ', '_', $row['username']).',';
          }
          $allbuds = substr($allbuds, 0, strlen($allbuds)-1);
          if($cls=='row2') $cls = 'row1';
          else $cls = 'row2';
          //echo '<tr><td colspan="3" class="'.$cls.'" style="text-align: center;"><a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/to/'.$allbuds).'">Send a PM to all buddies</a></td></tr>';
          echo '</table></div>';
        }
        $db->free_result();
        echo '<form action="'.makeUrlNS('Special', 'PrivateMessages/FoeList/Add').'" method="post" onsubmit="if(!submitAuthorized) return false;">
              <h3>Add a new foe</h3>';
        echo '<p>Username: '.$template->username_field('buddyname').'  <input type="submit" name="_go" value="Add" /></p>';
        echo '</form>';
        ?>
        </td>
        </tr>
        </table>
        <?php
      $template->footer();
      break;
  }
}

?>