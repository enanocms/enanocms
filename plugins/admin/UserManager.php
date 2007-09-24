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

function page_Admin_UserManager()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    echo '<h3>Error: Not authenticated</h3><p>It looks like your administration session is invalid or you are not authorized to access this administration page. Please <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true) . '">re-authenticate</a> to continue.</p>';
    return;
  }
  
  //die('<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>');
  
  if ( isset($_POST['action']['save']) )
  {
    #
    # BEGIN VALIDATION
    #
    
    $errors = array();
    
    if ( defined('ENANO_DEMO_MODE') )
    {
      $errors[] = 'Users cannot be modified or deleted in demo mode.';
    }
    
    $user_id = intval($_POST['user_id']);
    if ( empty($user_id) || $user_id == 1 )
      $errors[] = 'Invalid user ID.';
    
    if ( isset($_POST['delete_account']) && count($errors) < 1 )
    {
      $q = $db->sql_query('DELETE FROM '.table_prefix."users_extra WHERE user_id=$user_id;");
      if ( !$q )
        $db->_die();
      $q = $db->sql_query('DELETE FROM '.table_prefix."users WHERE user_id=$user_id;");
      if ( !$q )
        $db->_die();
      echo '<div class="info-box">The user account has been deleted.</div>';
    }
    else
    {
      if ( $session->user_id != $user_id )
      {
        $username = $_POST['username'];
        if ( !preg_match('#^'.$session->valid_username.'$#', $username) )
          $errors[] = 'The username you entered contains invalid characters.';
        
        $password = false;
        if ( $_POST['changing_pw'] == 'yes' )
        {
          $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
          $key_hex_md5 = $_POST['crypt_key'];
          $key_hex = $session->fetch_public_key($key_hex_md5);
          if ( $key_hex )
          {
            $key_bin = hexdecode($key_hex);
            $data_hex = $_POST['crypt_data'];
            $password = $aes->decrypt($data_hex, $key_bin, ENC_HEX);
          }
          else
          {
            $errors[] = 'Session manager denied public encryption key lookup request';
          }
        }
        
        $email = $_POST['email'];
        if ( !preg_match('/^(?:[\w\d]+\.?)+@((?:(?:[\w\d]\-?)+\.)+\w{2,4}|localhost)$/', $email) )
          $errors[] = 'You have entered an invalid e-mail address.';
        
        $real_name = $_POST['real_name'];
      }
      
      $signature = RenderMan::preprocess_text($_POST['signature'], true, true);
      
      $user_level = intval($_POST['user_level']);
      if ( $user_level < USER_LEVEL_MEMBER || $user_level > USER_LEVEL_ADMIN )
        $errors[] = 'Invalid user level';
      
      $imaddr_aim = htmlspecialchars($_POST['imaddr_aim']);
      $imaddr_msn = htmlspecialchars($_POST['imaddr_msn']);
      $imaddr_yahoo = htmlspecialchars($_POST['imaddr_yahoo']);
      $imaddr_xmpp = htmlspecialchars($_POST['imaddr_xmpp']);
      $homepage = htmlspecialchars($_POST['homepage']);
      $location = htmlspecialchars($_POST['location']);
      $occupation = htmlspecialchars($_POST['occupation']);
      $hobbies = htmlspecialchars($_POST['hobbies']);
      $email_public = ( isset($_POST['email_public']) ) ? '1' : '0';
      
      if ( !preg_match('/@([a-z0-9-]+)(\.([a-z0-9-\.]+))?/', $imaddr_msn) && !empty($imaddr_msn) )
      {
        $imaddr_msn = "$imaddr_msn@hotmail.com";
      }
      
      if ( substr($homepage, 0, 7) != 'http://' )
      {
        $homepage = "http://$homepage";
      }
      
      if ( !preg_match('/^http:\/\/([a-z0-9-.]+)([A-z0-9@#\$%\&:;<>,\.\?=\+\(\)\[\]_\/\\\\]*?)$/i', $homepage) )
      {
        $homepage = '';
      }
      
      if ( count($errors) < 1 )
      {
        $q = $db->sql_query('SELECT u.user_level FROM '.table_prefix.'users AS u WHERE u.user_id = ' . $user_id . ';');
        if ( !$q )
          $db->_die();
        
        if ( $db->numrows() < 1 )
        {
          echo 'Couldn\'t select user data: no rows returned';
        }
        
        $row = $db->fetchrow();
        $existing_level =& $row['user_level'];
        $db->free_result();
      
        $to_update_users = array();
        if ( $user_id != $session->user_id )
        {
          $to_update_users['username'] = $username;
          if ( $password )
          {
            $password = $aes->encrypt($password, $session->private_key, ENC_HEX);
            $to_update_users['password'] = $password;
          }
          $to_update_users['email'] = $email;
          $to_update_users['real_name'] = $real_name;
        }
        $to_update_users['signature'] = $signature;
        $to_update_users['user_level'] = $user_level;
        
        if ( isset($_POST['account_active']) )
        {
          $to_update_users['account_active'] = "1";
        }
        else
        {
          $to_update_users['account_active'] = "0";
          $to_update_users['activation_key'] = sha1($session->dss_rand());
        }
        
        $to_update_users_extra = array();
        $to_update_users_extra['user_aim'] = $imaddr_aim;
        $to_update_users_extra['user_msn'] = $imaddr_msn;
        $to_update_users_extra['user_yahoo'] = $imaddr_yahoo;
        $to_update_users_extra['user_xmpp'] = $imaddr_xmpp;
        $to_update_users_extra['user_homepage'] = $homepage;
        $to_update_users_extra['user_location'] = $location;
        $to_update_users_extra['user_job'] = $occupation;
        $to_update_users_extra['user_hobbies'] = $hobbies;
        $to_update_users_extra['email_public'] = ( $email_public ) ? '1' : '0';
        
        $update_sql = '';
        
        foreach ( $to_update_users as $key => $unused_crap )
        {
          $value =& $to_update_users[$key];
          $value = $db->escape($value);
          $update_sql .= ( empty($update_sql) ? '' : ',' ) . "$key='$value'";
        }
        
        $update_sql = 'UPDATE '.table_prefix."users SET $update_sql WHERE user_id=$user_id;";
        
        $update_sql_extra = '';
        
        foreach ( $to_update_users_extra as $key => $unused_crap )
        {
          $value =& $to_update_users_extra[$key];
          $value = $db->escape($value);
          $update_sql_extra .= ( empty($update_sql_extra) ? '' : ',' ) . "$key='$value'";
        }
        
        $update_sql_extra = 'UPDATE '.table_prefix."users_extra SET $update_sql_extra WHERE user_id=$user_id;";
        
        if ( !$db->sql_query($update_sql) )
          $db->_die();
        
        if ( !$db->sql_query($update_sql_extra) )
          $db->_die();
        
        if ( $existing_level != $user_level )
        {
          // We need to update group memberships
          if ( $existing_level == USER_LEVEL_ADMIN ) 
          {
            $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES("security","u_from_admin",UNIX_TIMESTAMP(),"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '","' . $db->escape($username) . '");');
            if ( !$q )
              $db->_die();
            $session->remove_user_from_group($user_id, GROUP_ID_ADMIN);
          }
          else if ( $existing_level == USER_LEVEL_MOD ) 
          {
            $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES("security","u_from_mod",UNIX_TIMESTAMP(),"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '","' . $db->escape($username) . '");');
            if ( !$q )
              $db->_die();
            $session->remove_user_from_group($user_id, GROUP_ID_MOD);
          }
          
          if ( $user_level == USER_LEVEL_ADMIN )
          {
            $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES("security","u_to_admin",UNIX_TIMESTAMP(),"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '","' . $db->escape($username) . '");');
            if ( !$q )
              $db->_die();
            $session->add_user_to_group($user_id, GROUP_ID_ADMIN, false);
          }
          else if ( $user_level == USER_LEVEL_MOD )
          {
            $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES("security","u_to_mod",UNIX_TIMESTAMP(),"' . $db->escape($_SERVER['REMOTE_ADDR']) . '","' . $db->escape($session->username) . '","' . $db->escape($username) . '");');
            if ( !$q )
              $db->_die();
            $session->add_user_to_group($user_id, GROUP_ID_MOD, false);
          }
        }
        
        echo '<div class="info-box">Your changes have been saved.</div>';
      }
    }
    
    if ( count($errors) > 0 )
    {
      echo '<div class="error-box">
              <b>Your request could not be processed due to the following validation errors:</b>
              <ul>
                <li>' . implode("</li>\n        <li>", $errors) . '</li>
              </ul>
            </div>';
      $form = new Admin_UserManager_SmartForm();
      $form->user_id = $user_id;
      $form->username = $username;
      $form->email = $email;
      $form->real_name = $real_name;
      $form->signature = $signature;
      $form->user_level = $user_level;
      $form->im = array(
          'aim' => $imaddr_aim,
          'yahoo' => $imaddr_yahoo,
          'msn' => $imaddr_msn,
          'xmpp' => $imaddr_xmpp
        );
      $form->contact = array(
          'homepage' => $homepage,
          'location' => $location,
          'job' => $occupation,
          'hobbies' => $hobbies
        );
      $form->email_public = ( isset($_POST['email_public']) );
      $form->account_active = ( isset($_POST['account_active']) );
      echo $form->render();
      return false;
    }
    
    #
    # END VALIDATION
    #
  }
  else if ( isset($_POST['action']['go']) || ( isset($_GET['src']) && $_GET['src'] == 'get' ) )
  {
    if ( isset($_GET['user']) )
    {
      $username =& $_GET['user'];
    }
    else if ( isset($_POST['username']) )
    {
      $username =& $_POST['username'];
    }
    else
    {
      echo 'No username provided';
      return false;
    }
    $q = $db->sql_query('SELECT u.user_id AS authoritative_uid, u.username, u.email, u.real_name, u.signature, u.account_active, u.user_level, x.* FROM '.table_prefix.'users AS u
                           LEFT JOIN '.table_prefix.'users_extra AS x
                             ON ( u.user_id = x.user_id OR x.user_id IS NULL )
                           WHERE ( lcase(u.username) = \'' . $db->escape(strtolower($username)) . '\' OR u.username = \'' . $db->escape($username) . '\' ) AND user_id != 1;');
    if ( !$q )
      $db->_die();
    
    if ( $db->numrows() < 1 )
    {
      echo '<div class="error-box">The username you entered could not be found.</div>';
    }
    else
    {
      $row = $db->fetchrow();
      $row['user_id'] = $row['authoritative_uid'];
      $form = new Admin_UserManager_SmartForm();
      $form->user_id   = $row['user_id'];
      $form->username  = $row['username'];
      $form->email     = $row['email'];
      $form->real_name = $row['real_name'];
      $form->signature = $row['signature'];
      $form->user_level= $row['user_level'];
      $form->account_active = ( $row['account_active'] == 1 );
      $form->email_public   = ( $row['email_public'] == 1 );
      $form->im = array(
          'aim' => $row['user_aim'],
          'yahoo' => $row['user_yahoo'],
          'msn' => $row['user_msn'],
          'xmpp' => $row['user_xmpp']
        );
      $form->contact = array(
          'homepage' => $row['user_homepage'],
          'location' => $row['user_location'],
          'job'      => $row['user_job'],
          'hobbies'  => $row['user_hobbies'],
        );
      $form->email_public = ( $row['email_public'] == 1 );
      $html = $form->render();
      if ( !$html )
      {
        echo 'Internal error: form processor returned false';
      }
      else
      {
        echo $html;
      }
      return true;
    }
  }
  else if ( isset($_POST['action']['clear_sessions']) )
  {
    if ( defined('ENANO_DEMO_MODE') )
    {
      echo '<div class="error-box">Sorry Charlie, no can do. You might mess up other people logged into the demo site.</div>';
    }
    else
    {
      // Get the current session information so the user doesn't get logged out
      $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
      $sk = md5(strrev($session->sid_super));
      $qb = $db->sql_query('SELECT session_key,salt,auth_level,source_ip,time FROM '.table_prefix.'session_keys WHERE session_key=\''.$sk.'\' AND user_id='.$session->user_id.' AND auth_level='.USER_LEVEL_ADMIN);
      if ( !$qb )
      {
        die('Error selecting session key info block B: '.$db->get_error());
      }
      if ( $db->numrows($qb) < 1 )
      {
        die('Error: cannot read admin session info block B, aborting table clear process');
      }
      $qa = $db->sql_query('SELECT session_key,salt,auth_level,source_ip,time FROM '.table_prefix.'session_keys WHERE session_key=\''.md5($session->sid).'\' AND user_id='.$session->user_id.' AND auth_level='.USER_LEVEL_MEMBER);
      if ( !$qa )
      {
        die('Error selecting session key info block A: '.$db->get_error());
      }
      if ( $db->numrows($qa) < 1 )
      {
        die('Error: cannot read user session info block A, aborting table clear process');
      }
      $ra = $db->fetchrow($qa);
      $rb = $db->fetchrow($qb);
      $db->free_result($qa);
      $db->free_result($qb);
      
      $db->sql_query('DELETE FROM '.table_prefix.'session_keys;');
      $db->sql_query('INSERT INTO '.table_prefix.'session_keys( session_key,salt,user_id,auth_level,source_ip,time ) VALUES( \''.$ra['session_key'].'\', \''.$ra['salt'].'\', \''.$session->user_id.'\', \''.$ra['auth_level'].'\', \''.$ra['source_ip'].'\', '.$ra['time'].' ),( \''.$rb['session_key'].'\', \''.$rb['salt'].'\', \''.$session->user_id.'\', \''.$rb['auth_level'].'\', \''.$rb['source_ip'].'\', '.$rb['time'].' )');
      
      echo '<div class="info-box">The session key table has been cleared. Your database should be a little bit smaller now.</div>';
    }
  }
  echo '<form action="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->cpage['module'], true) . '" method="post" enctype="multipart/form-data" onsubmit="if ( !submitAuthorized ) return false;">';
  echo '<h3>User administration panel</h3>';
  echo '<p>From this panel you can modify or delete user accounts.</p>';
  echo '<table border="0">
          <tr>
            <td><b>Search for user:</b><br />
                <small>If your browser supports AJAX, this will provide suggestions for you.</small>
                </td>
            <td style="width: 10px;"></td>
            <td>' . $template->username_field('username') . '</td>
            <td>
              <input type="submit" name="action[go]" value="Go &raquo;" />
            </td>
          </tr>
        </table>';
  echo '<h3>Clear session key table</h3>';
  echo '<p>It\'s a good idea to clean out your session keys table every once in a while, since this helps to reduce database size. During this process you will be logged off and (hopefully) logged back on automatically. If you do this, all users besides you will be logged off, so be sure to do this at a time when traffic is low.</p>';
  echo '<p><input type="submit" name="action[clear_sessions]" value="Clear session keys" /></p>';
  echo '</form>';
  
  if(isset($_GET['action']) && isset($_GET['user']))
  {
    switch($_GET['action'])
    {
      case "activate":
        $e = $db->sql_query('SELECT activation_key FROM '.table_prefix.'users WHERE username=\'' . $db->escape($_GET['user']) . '\'');
        if($e)
        {
          $row = $db->fetchrow();
          $db->free_result();
          if($session->activate_account($_GET['user'], $row['activation_key'])) { echo '<div class="info-box">The user account "'.$_GET['user'].'" has been activated.</div>'; $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE time_id=' . $db->escape($_GET['logid'])); }
          else echo '<div class="warning-box">The user account "'.$_GET['user'].'" has NOT been activated, possibly because the account is already active.</div>';
        } else echo '<div class="error-box">Error activating account: '.mysql_error().'</div>';
        break;
      case "sendemail":
        if($session->send_activation_mail($_GET['user'])) { echo '<div class="info-box">The user "'.$_GET['user'].'" has been sent an e-mail with an activation link.</div>'; $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE time_id=' . $db->escape($_GET['logid'])); }
        else echo '<div class="error-box">The user account "'.$_GET['user'].'" has not been activated, probably because of a bad SMTP configuration.</div>';
        break;
      case "deny":
        $e = $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE log_type=\'admin\' AND action=\'activ_req\' AND edit_summary=\'' . $db->escape($_GET['user']) . '\';');
        if(!$e) echo '<div class="error-box">Error during row deletion: '.mysql_error().'</div>';
        else echo '<div class="info-box">All activation requests for the user "'.$_GET['user'].'" have been deleted.</div>';
        break;
    }
  }
  $q = $db->sql_query('SELECT l.log_type, l.action, l.time_id, l.date_string, l.author, l.edit_summary, u.user_coppa FROM '.table_prefix.'logs AS l
                         LEFT JOIN '.table_prefix.'users AS u
                           ON ( u.username = l.edit_summary OR u.username IS NULL )
                         WHERE log_type=\'admin\' AND action=\'activ_req\' ORDER BY time_id DESC;');
  if($q)
  {
    if($db->numrows() > 0)
    {
      $n = $db->numrows();
      if($n == 1) $s = $n . ' user is';
      else $s = $n . ' users are';
      echo '<h3>'.$s . ' awaiting account activation</h3>';
      echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4" width="100%">
            <tr><th>Date of request</th><th>Requested by</th><th>Requested for</th><th>COPPA user</th><th colspan="3">Actions</th></tr>';
      $cls = 'row2';
      while($row = $db->fetchrow())
      {
        if($cls == 'row2') $cls = 'row1';
        else $cls = 'row2';
        $coppa = ( $row['user_coppa'] == '1' ) ? '<b>Yes</b>' : 'No';
        echo '<tr><td class="'.$cls.'">'.date('F d, Y h:i a', $row['time_id']).'</td><td class="'.$cls.'">'.$row['author'].'</td><td class="'.$cls.'">'.$row['edit_summary'].'</td><td style="text-align: center;" class="' . $cls . '">' . $coppa . '</td><td class="'.$cls.'" style="text-align: center;"><a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&amp;action=activate&amp;user='.$row['edit_summary'].'&amp;logid='.$row['time_id']).'">Activate now</a></td><td class="'.$cls.'" style="text-align: center;"><a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&amp;action=sendemail&amp;user='.$row['edit_summary'].'&amp;logid='.$row['time_id']).'">Send activation e-mail</a></td><td class="'.$cls.'" style="text-align: center;"><a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&amp;action=deny&amp;user='.$row['edit_summary'].'&amp;logid='.$row['time_id']).'">Deny request</a></td></tr>';
      }
      echo '</table>';
    }
    $db->free_result();
  }
  
}

/**
 * Smart form class for the user manager.
 * @package Enano
 * @subpackage Administration
 */

class Admin_UserManager_SmartForm
{
  
  /**
   * Universally Unique Identifier (UUID) for this editor instance. Used to unique-itize Javascript functions and whatnot.
   * @var string
   */
  
  var $uuid = '';
  
  /**
   * User ID that we're editing.
   * @var int
   */
  
  var $user_id = 0;
  
  /**
   * Username
   * @var string
   */
  
  var $username = '';
  
  /**
   * E-mail address
   * @var string
   */
  
  var $email = '';
  
  /**
   * Real name
   * @var string
   */
  
  var $real_name = '';
  
  /**
   * Signature
   * @var string
   */
  
  var $signature = '';
  
  /**
   * IM contact information
   * @var array
   */
   
  var $im = array();
  
  /**
   * Real-life contact info
   * @var array
   */
  
  var $contact = array();
  
  /**
   * User level
   * @var int
   */
  
  var $user_level = USER_LEVEL_MEMBER;
  
  /**
   * Account activated
   * @var bool
   */
  
  var $account_active = true;
  
  /**
   * Email public switch
   * @var bool
   */
  
  var $email_public = false;
  
  /**
   * Constructor.
   */
  
  function __construct()
  {
    $this->uuid = md5( mt_rand() . microtime() );
  }
  
  /**
   * PHP4 constructor.
   */
  
  function Admin_UserManager_SmartForm()
  {
    $this->__construct();
  }
  
  /**
   * Renders and returns the finished form.
   * @return string
   */
  
  function render()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( file_exists( ENANO_ROOT . "/themes/$template->theme/admin_usermanager_form.tpl" ) )
    {
      $parser = $template->makeParser('admin_usermanager_form.tpl');
    }
    else
    {
      $tpl_code = <<<EOF
      <!-- Start of user edit form -->
      
        <script type="text/javascript">
          function userform_{UUID}_chpasswd()
          {
            var link = document.getElementById('userform_{UUID}_pwlink');
            var form = document.getElementById('userform_{UUID}_pwform');
            domOpacity(link, 100, 0, 500);
            domObjChangeOpac(0, form);
            setTimeout("var link = document.getElementById('userform_{UUID}_pwlink'); var form = document.getElementById('userform_{UUID}_pwform'); link.style.display = 'none'; form.style.display = 'block'; domOpacity(form, 0, 100, 500);", 550);
            <!-- BEGINNOT same_user -->document.forms['useredit_{UUID}'].changing_pw.value = 'yes';<!-- END same_user -->
          }
          
          function userform_{UUID}_chpasswd_cancel()
          {
            var link = document.getElementById('userform_{UUID}_pwlink');
            var form = document.getElementById('userform_{UUID}_pwform');
            domOpacity(form, 100, 0, 500);
            domObjChangeOpac(0, link);
            setTimeout("var link = document.getElementById('userform_{UUID}_pwlink'); var form = document.getElementById('userform_{UUID}_pwform'); form.style.display = 'none'; link.style.display = 'block'; domOpacity(link, 0, 100, 500);", 550);
            <!-- BEGINNOT same_user -->document.forms['useredit_{UUID}'].changing_pw.value = 'no';<!-- END same_user -->
          }
          
          function userform_{UUID}_validate()
          {
            var form = document.forms['useredit_{UUID}'];
            <!-- BEGINNOT same_user -->
            if ( form.changing_pw.value == 'yes' )
            {
              if ( form.new_password.value != form.new_password_confirm.value )
              {
                alert('The passwords you entered did not match.');
                return false;
              }
              form.new_password_confirm.value = '';
              runEncryption();
            }
            <!-- END same_user -->
            return true;
          }
        </script>
      
        <form action="{FORM_ACTION}" method="post" name="useredit_{UUID}" enctype="multipart/form-data" onsubmit="return userform_{UUID}_validate();">
        
          <input name="user_id" value="{USER_ID}" type="hidden" />
        
          <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
            
              <!-- Heading -->
            
              <tr>
                <th colspan="2">
                  Editing user: {USERNAME}
                </th>
              </tr>
              
              <!-- Basic options (stored in enano_users) -->
              
                <tr>
                  <th colspan="2" class="subhead">
                    Basic options
                  </th>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    Username:<br />
                    <small>Must be at least 2 characters in length</small>
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="username" value="{USERNAME}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/><!-- BEGIN same_user --> <small>You cannot change your own username. To change your username you must log into a different administrative account.</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2">
                    Password:
                    <!-- BEGIN password_meter -->
                    <br />
                    <small>Password strength requirements are not enforced here.</small>
                    <!-- END password_meter -->
                  </td>
                  <td class="row1">
                    <div id="userform_{UUID}_pwlink">
                      <b>Password will be left unchanged.</b> <a href="#" onclick="userform_{UUID}_chpasswd(); return false;">Reset password...</a>
                    </div>
                    <div id="userform_{UUID}_pwform" style="display: none;">
                      <!-- BEGIN same_user -->
                      To change your password, please use the user preferences panel. <a href="#" onclick="userform_{UUID}_chpasswd_cancel(); return false;">Cancel</a>
                      <!-- BEGINELSE same_user -->
                      <input type="hidden" name="changing_pw" value="no" />
                      <input type="hidden" name="challenge_data" value="{MD5_CHALLENGE}" />
                      <input type="hidden" name="use_crypt" value="no" />
                      <input type="hidden" name="crypt_key" value="{PUBLIC_KEY}" />
                      <input type="hidden" name="crypt_data" value="" />
                      <table border="0" style="background-color: transparent;" cellspacing="0" cellpadding="0">
                        <tr>
                          <td colspan="2">
                            <b>Change password to:</b>
                          </td>
                        </tr>
                        <tr>
                          <td>New password:</td>
                          <td><input type="password" name="new_password" value="" <!-- BEGIN password_meter -->onkeyup="password_score_field(this);" <!-- END password_meter -->/><span class="password-checker" style="font-weight: bold; color: #AA0000"> Weak (score: -10)</span>
                            <!-- BEGIN password_meter -->
                              <div id="pwmeter" style="margin: 4px 0; height: 8px;"></div>
                            <!-- END password_meter -->
                          </td>
                        </tr>
                        <tr>
                          <td>Confirm:</td>
                          <td><input type="password" name="new_password_confirm" value="" /></td>
                        </tr>
                        <tr>
                          <td colspan="2">
                            <a href="#" onclick="userform_{UUID}_chpasswd_cancel(); return false;">Cancel</a>
                          </td>
                        </tr>
                      </table>
                      <!-- END same_user -->
                    </div>
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    E-mail address:
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="email" value="{EMAIL}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/><!-- BEGIN same_user --> <small>To change your e-mail address, please use the user preferences panel.</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    Real name:
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="real_name" value="{REAL_NAME}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/><!-- BEGIN same_user --> <small>To change your real name on file, please use the user preferences panel.</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    Signature:
                  </td>
                  <td class="row1" style="width: 75%;">
                    {SIGNATURE_FIELD}
                  </td>
                </tr>
                
              <!-- / Basic options -->
              
              <!-- Extended options (anything in enano_users_extra) -->
              
                <tr>
                  <th class="subhead" colspan="2">
                    Instant messenger contact information
                  </th>
                <tr>
                  <td class="row2">AIM handle:</td>
                  <td class="row1"><input type="text" name="imaddr_aim" value="{IM_AIM}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2"><acronym title="Windows&trade; Live Messenger">WLM</acronym> handle:<br /><small>If you don't specify the domain (@whatever.com), "@hotmail.com" will be assumed.</small></td>
                  <td class="row1"><input type="text" name="imaddr_msn" value="{IM_WLM}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">Yahoo! IM handle:</td>
                  <td class="row1"><input type="text" name="imaddr_yahoo" value="{IM_YAHOO}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">Jabber/XMPP handle:</td>
                  <td class="row1"><input type="text" name="imaddr_xmpp" value="{IM_XMPP}" size="30" /></td>
                </tr>
                <tr>
                  <th class="subhead" colspan="2">
                    Extra contact information
                  </th>
                </tr>
                <tr>
                  <td class="row2">Homepage:<br /><small>Please remember the http:// prefix.</small></td>
                  <td class="row1"><input type="text" name="homepage" value="{HOMEPAGE}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">Location:</td>
                  <td class="row1"><input type="text" name="location" value="{LOCATION}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">Job:</td>
                  <td class="row1"><input type="text" name="occupation" value="{JOB}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">Hobbies:</td>
                  <td class="row1"><input type="text" name="hobbies" value="{HOBBIES}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2"><label for="chk_email_public_{UUID}">E-mail address is public</label><br /><small>If this is checked, the user's e-mail address will be displayed on your the page. To protect the address from spambots, it will be encrypted.</small></td>
                  <td class="row1"><input type="checkbox" id="chk_email_public_{UUID}" name="email_public" <!-- BEGIN email_public -->checked="checked" <!-- END email_public -->size="30" /></td>
                </tr>
              
              <!-- / Extended options -->
              
              <!-- Administrator-only options -->
              
                <tr>
                  <th class="subhead" colspan="2">
                    Administrator-only options
                  </th>
                </tr>
                
                <tr>
                  <td class="row2">User account is active<br />
                                   <small>If this is unchecked, the existing activation key will be overwritten in the database, thus invalidating any activation e-mails sent to the user.</small>
                                   </td>
                  <td class="row1"><label><input type="checkbox" name="account_active" <!-- BEGIN account_active -->checked="checked" <!-- END account_active -->/> Account is active and enabled</label></td>
                </tr>
                
                <tr>
                  <td class="row2">
                    User's site access level<br />
                    <small>If this is changed, the relevant group memberships will be updated accordingly.</small>
                  </td>
                  <td class="row1">
                    <select name="user_level">
                      <option value="{USER_LEVEL_MEMBER}"<!-- BEGIN ul_member --> selected="selected"<!-- END ul_member -->>Normal member</option>
                      <option value="{USER_LEVEL_MOD}"<!-- BEGIN ul_mod --> selected="selected"<!-- END ul_mod -->>Moderator</option>
                      <option value="{USER_LEVEL_ADMIN}"<!-- BEGIN ul_admin --> selected="selected"<!-- END ul_admin -->>Site administrator</option>
                    </select>
                  </td>
                </tr>
                
                <tr>
                  <td class="row2">
                    Delete user account
                  </td>
                  <td class="row1">
                    <label><input type="checkbox" name="delete_account" onclick="var d = (this.checked) ? 'block' : 'none'; document.getElementById('delete_blurb_{UUID}').style.display = d;" /> Permanently delete this user account when I click Save</label>
                    <div id="delete_blurb_{UUID}" style="display: none;">
                      <!-- BEGIN same_user -->
                      <p><b><blink style="color: red;">WARNING!</blink> This will delete your own user account!</b></p>
                      <!-- END same_user -->
                      <p><small>Even if you delete this user account, the username will be shown in page edit history, comments, and other areas of the site.
                      Deleting a user account CANNOT BE UNDONE and should only be done in extreme circumstances.
                      If the user has violated the site policy, deleting the account will not prevent him from using the site or creating a new account, for that you need to add a new ban rule.</small></p>
                    </div>
                  </td>
                </tr>
                </tr>
              
              <!-- Save button -->
              <tr>
                <th colspan="2">
                  <input type="submit" name="action[save]" value="Save changes" style="font-weight: bold;" />
                  <input type="submit" name="action[noop]" value="Cancel" style="font-weight: normal;" />
                </th>
              </tr>
            
            </table>
          </div>
        
        </form>
        {AES_JAVASCRIPT}
      <!-- Conclusion of user edit form -->
EOF;
      $parser = $template->makeParserText($tpl_code);
    }
    
    $this->username = htmlspecialchars($this->username);
    $this->email = htmlspecialchars($this->email);
    $this->user_id = intval($this->user_id);
    $this->real_name = htmlspecialchars($this->real_name);
    $this->signature = htmlspecialchars($this->signature);
    $this->user_level = intval($this->user_level);
    
    $im_aim   = ( isset($this->im['aim']) )   ? $this->im['aim']   : false;
    $im_yahoo = ( isset($this->im['yahoo']) ) ? $this->im['yahoo'] : false;
    $im_msn   = ( isset($this->im['msn']) )   ? $this->im['msn']   : false;
    $im_xmpp  = ( isset($this->im['xmpp']) )  ? $this->im['xmpp']  : false;
    
    $homepage = ( isset($this->contact['homepage']) ) ? $this->contact['homepage'] : false;
    $location = ( isset($this->contact['location']) ) ? $this->contact['location'] : false;
    $job = ( isset($this->contact['job']) ) ? $this->contact['job'] : false;
    $hobbies = ( isset($this->contact['hobbies']) ) ? $this->contact['hobbies'] : false;
    
    if ( empty($this->username) )
    {
      // @error One or more required parameters not set
      return 'Admin_UserManager_SmartForm::render: Invalid parameter ($form->username)';
    }
    
    if ( empty($this->user_id) )
    {
      // @error One or more required parameters not set
      return 'Admin_UserManager_SmartForm::render: Invalid parameter ($form->user_id)';
    }
    
    if ( empty($this->email) )
    {
      // @error One or more required parameters not set
      return 'Admin_UserManager_SmartForm::render: Invalid parameter ($form->email)';
    }
    
    $form_action = makeUrlNS('Special', 'Administration', 'module=' . $paths->cpage['module'], true);
    $aes_javascript = $session->aes_javascript("useredit_$this->uuid", 'new_password', 'use_crypt', 'crypt_key', 'crypt_data', 'challenge_data');
    
    $parser->assign_vars(array(
        'UUID' => $this->uuid,
        'USERNAME' => $this->username,
        'EMAIL' => $this->email,
        'USER_ID' => $this->user_id,
        'MD5_CHALLENGE' => $session->dss_rand(),
        'PUBLIC_KEY' => $session->rijndael_genkey(),
        'REAL_NAME' => $this->real_name,
        'SIGNATURE_FIELD' => $template->tinymce_textarea('signature', $this->signature, 10, 50),
        'USER_LEVEL_MEMBER' => USER_LEVEL_CHPREF,
        'USER_LEVEL_MOD' => USER_LEVEL_MOD,
        'USER_LEVEL_ADMIN' => USER_LEVEL_ADMIN,
        'AES_JAVASCRIPT' => $aes_javascript,
        'IM_AIM' => $im_aim,
        'IM_YAHOO' => $im_yahoo,
        'IM_WLM' => $im_msn,
        'IM_XMPP' => $im_xmpp,
        'HOMEPAGE' => $homepage,
        'LOCATION' => $location,
        'JOB' => $job,
        'HOBBIES' => $hobbies,
        'FORM_ACTION' => $form_action
      ));
    
    $parser->assign_bool(array(
        'password_meter' => ( getConfig('pw_strength_enable') == '1' ),
        'ul_member' => ( $this->user_level == USER_LEVEL_CHPREF ),
        'ul_mod' => ( $this->user_level == USER_LEVEL_MOD ),
        'ul_admin' => ( $this->user_level == USER_LEVEL_ADMIN ),
        'account_active' => ( $this->account_active === true ),
        'email_public' => ( $this->email_public === true ),
        'same_user' => ( $this->user_id == $session->user_id )
      ));
    
    $parsed = $parser->run();
    return $parsed;
  }
  
}

?>
