<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
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
  global $lang;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  require_once(ENANO_ROOT . '/includes/math.php');
  require_once(ENANO_ROOT . '/includes/diffiehellman.php');
  
  $GLOBALS['dh_supported'] = $dh_supported;
  
  //die('<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>');
  
  if ( isset($_POST['action']['save']) )
  {
    #
    # BEGIN VALIDATION
    #
    
    $errors = array();
    
    if ( defined('ENANO_DEMO_MODE') )
    {
      $errors[] = $lang->get('acpum_err_nosave_demo');
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
      $q = $db->sql_query('DELETE FROM '.table_prefix."session_keys WHERE user_id=$user_id;");
      if ( !$q )
        $db->_die();
      echo '<div class="info-box">' . $lang->get('acpum_msg_delete_success') . '</div>';
      
      // deleting own account?
      if ( $user_id === $session->user_id )
      {
        // cute little hack to boot them out of the admin panel
        echo '<script type="text/javascript">
          addOnloadHook(function()
          {
            setTimeout(function()
            {
              eraseCookie("sid");
              ENANO_SID = false;
              auth_level = USER_LEVEL_MEMBER;
              window.location = makeUrlNS("Special", "Login");
            }, 3000);
          });
        </script>';
      }
    }
    else
    {
      if ( $session->user_id == $user_id )
      {
        $username = $session->username;
        $password = false;
        $email = $session->email;
        $real_name = $session->real_name;
      }
      else
      {
        $username = $_POST['username'];
        if ( !preg_match('#^'.$session->valid_username.'$#', $username) )
          $errors[] = $lang->get('acpum_err_illegal_username');
        
        $password = false;
        if ( $_POST['changing_pw'] == 'yes' )
        {
          $password = $session->get_aes_post('new_password');
        }
        
        $email = $_POST['email'];
        if ( !preg_match('/^(?:[\w\d]+\.?)+@((?:(?:[\w\d]\-?)+\.)+\w{2,4}|localhost)$/', $email) )
          $errors[] = $lang->get('acpum_err_illegal_email');
        
        $real_name = $_POST['real_name'];
      }
      
      $signature = RenderMan::preprocess_text($_POST['signature'], true, false);
      
      $user_level = intval($_POST['user_level']);
      if ( $user_level < USER_LEVEL_MEMBER || $user_level > USER_LEVEL_ADMIN )
        $errors[] = 'Invalid user level';
      
      $user_rank = $_POST['user_rank'];
      if ( $user_rank !== 'NULL' )
      {
        $user_rank = intval($user_rank);
        if ( !$user_rank )
          $errors[] = 'Invalid user rank';
      }
      
      $imaddr_aim = htmlspecialchars($_POST['imaddr_aim']);
      $imaddr_msn = htmlspecialchars($_POST['imaddr_msn']);
      $imaddr_yahoo = htmlspecialchars($_POST['imaddr_yahoo']);
      $imaddr_xmpp = htmlspecialchars($_POST['imaddr_xmpp']);
      $homepage = htmlspecialchars($_POST['homepage']);
      $location = htmlspecialchars($_POST['location']);
      $occupation = htmlspecialchars($_POST['occupation']);
      $hobbies = htmlspecialchars($_POST['hobbies']);
      $email_public = ( isset($_POST['email_public']) ) ? '1' : '0';
      $user_title = htmlspecialchars($_POST['user_title']);
      
      if ( !preg_match('/@([a-z0-9-]+)(\.([a-z0-9-\.]+))?/', $imaddr_msn) && !empty($imaddr_msn) )
      {
        $imaddr_msn = "$imaddr_msn@hotmail.com";
      }
      
      if ( !preg_match('#^https?://#', $homepage) )
      {
        $homepage = "http://$homepage";
      }
      
      if ( !preg_match('/^http:\/\/([a-z0-9-.]+)([A-z0-9@#\$%\&:;<>,\.\?=\+\(\)\[\]_\/\\\\]*?)$/i', $homepage) )
      {
        $homepage = '';
      }
      
      // true for quiet operation
      list(, , $avatar_post_fail) = avatar_post($user_id, true);
      
      if ( count($errors) < 1 && !$avatar_post_fail )
      {
        $q = $db->sql_query('SELECT u.user_level, u.user_has_avatar, u.avatar_type, u.username FROM '.table_prefix.'users AS u WHERE u.user_id = ' . $user_id . ';');
        if ( !$q )
          $db->_die();
        
        if ( $db->numrows() < 1 )
        {
          echo 'Couldn\'t select user data: no rows returned';
        }
        
        $row = $db->fetchrow();
        $existing_level =& $row['user_level'];
        $avi_type =& $row['avatar_type'];
        $has_avi = ( $row['user_has_avatar'] == 1 );
        $old_username = $row['username'];
        $db->free_result();
        
        $to_update_users = array();
        if ( $user_id != $session->user_id )
        {
          $to_update_users['username'] = $username;
          if ( $password )
          {
            $session->set_password($user_id, $password);
          }
          $to_update_users['email'] = $email;
          $to_update_users['real_name'] = $real_name;
        }
        $to_update_users['signature'] = $signature;
        $to_update_users['user_level'] = $user_level;
        $to_update_users['user_rank'] = $user_rank;
        $to_update_users['user_title'] = $user_title;
        
        if ( $user_rank > 0 )
        {
          $to_update_users['user_rank_userset'] = '0';
        }
        
        if ( isset($_POST['account_active']) )
        {
          $to_update_users['account_active'] = "1";
        }
        else
        {
          $to_update_users['account_active'] = "0";
          $to_update_users['activation_key'] = sha1($session->dss_rand());
        }
        
        if ( count($errors) < 1 )
        {
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
            if ( $value !== 'NULL' )
              $value = "'" . $db->escape($value) . "'";
 
            $update_sql .= ( empty($update_sql) ? '' : ',' ) . "$key=$value";
          }
          
          $update_sql = 'UPDATE ' . table_prefix . "users SET $update_sql WHERE user_id=$user_id;";
          
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
          
          // If the username was changed, we need to update their user page as well
          if ( $old_username != $username )
          {
            $page = new PageProcessor($old_username, 'User');
            if ( $page->exists() )
            {
              // they have a user page, rename it
              $old_urlname = $db->escape(sanitize_page_id($old_username));
              $new_urlname = $db->escape(sanitize_page_id($username));
              $sql = array(
                      'UPDATE ' . table_prefix . "pages      SET urlname = '$new_urlname' WHERE urlname = '$old_urlname' AND namespace = 'User';",
                      // Change the page's title ONLY if it exactly matches the old username
                      'UPDATE ' . table_prefix . "pages      SET name = '" . $db->escape($username) . "' WHERE urlname = '$new_urlname' AND name = '" . $db->escape($old_username) . "' AND namespace = 'User';",
                      'UPDATE ' . table_prefix . "logs       SET page_id = '$new_urlname' WHERE page_id = '$old_urlname' AND namespace = 'User';",
                      'UPDATE ' . table_prefix . "tags       SET page_id = '$new_urlname' WHERE page_id = '$old_urlname' AND namespace = 'User';",
                      'UPDATE ' . table_prefix . "comments   SET page_id = '$new_urlname' WHERE page_id = '$old_urlname' AND namespace = 'User';",
                      'UPDATE ' . table_prefix . "page_text  SET page_id = '$new_urlname' WHERE page_id = '$old_urlname' AND namespace = 'User';",
                      'UPDATE ' . table_prefix . "categories SET page_id = '$new_urlname' WHERE page_id = '$old_urlname' AND namespace = 'User';"
                    );
              foreach ( $sql as $q )
              {
                if ( !$db->sql_query($q) )
                  $db->_die('UserManager renaming user page post-username change');
              }
            }
          }
          
          if ( $existing_level != $user_level )
          {
            // We need to update group memberships
            if ( $existing_level == USER_LEVEL_ADMIN ) 
            {
              $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,author_uid,page_text) VALUES(\'security\',\'u_from_admin\',' . time() . ', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\', ' . $session->user_id . ', \'' . $db->escape($session->username) . '\', \'' . $db->escape($username) . '\');');
              if ( !$q )
                $db->_die();
              $session->remove_user_from_group($user_id, GROUP_ID_ADMIN);
            }
            else if ( $existing_level == USER_LEVEL_MOD ) 
            {
              $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,author_uid,page_text) VALUES(\'security\',\'u_from_mod\',' . time() . ', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\', ' . $session->user_id . ', \'' . $db->escape($session->username) . '\', \'' . $db->escape($username) . '\');');
              if ( !$q )
                $db->_die();
              $session->remove_user_from_group($user_id, GROUP_ID_MOD);
            }
            
            if ( $user_level == USER_LEVEL_ADMIN )
            {
              $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,author_uid,page_text) VALUES(\'security\',\'u_to_admin\',' . time() . ', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\', ' . $session->user_id . ', \'' . $db->escape($session->username) . '\', \'' . $db->escape($username) . '\');');
              if ( !$q )
                $db->_die();
              $session->add_user_to_group($user_id, GROUP_ID_ADMIN, false);
            }
            else if ( $user_level == USER_LEVEL_MOD )
            {
              $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,author_uid,page_text) VALUES(\'security\',\'u_to_mod\',' . time() . ', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\', ' . $session->user_id . ', \'' . $db->escape($session->username) . '\', \'' . $db->escape($username) . '\');');
              if ( !$q )
                $db->_die();
              $session->add_user_to_group($user_id, GROUP_ID_MOD, false);
            }
          }
          
          // user level updated, regenerate the ranks cache
          generate_cache_userranks();
          
          echo '<div class="info-box">' . $lang->get('acpum_msg_save_success') . '</div>';
        }
      }
    }
    
    if ( count($errors) > 0 || @$avatar_post_fail )
    {
      if ( count($errors) > 0 )
      {
        echo '<div class="error-box">
                <b>' . $lang->get('acpum_err_validation_fail') . '</b>
                <ul>
                  <li>' . implode("</li>\n        <li>", $errors) . '</li>
                </ul>
              </div>';
      }
      $form = new Admin_UserManager_SmartForm();
      $form->user_id = $user_id;
      $form->username = $username;
      $form->email = $email;
      $form->real_name = $real_name;
      $form->signature = $signature;
      $form->user_level = $user_level;
      $form->user_rank = $user_rank;
      $form->user_title = $user_title;
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
      // This is SAFE. The smartform calls is_valid_ip() on this value, thus preventing XSS
      // attempts from making it into the form HTML. Badly coded templates may still be
      // affected, but if have_reg_ip is checked for, then you're fine.
      $form->reg_ip_addr = $_POST['user_registration_ip'];
      echo $form->render();
      return false;
    }
    
    #
    # END VALIDATION
    #
  }
  else if ( isset($_POST['action']['go']) || ( isset($_GET['src']) && $_GET['src'] == 'get' ) || ($pathsuser = $paths->getParam(0)) )
  {
    if ( isset($_GET['user']) )
    {
      $username =& $_GET['user'];
    }
    else if ( isset($_GET['username']) )
    {
      $username =& $_GET['username'];
    }
    else if ( isset($_POST['username']) )
    {
      $username =& $_POST['username'];
    }
    else if ( $pathsuser )
    {
      $username = str_replace('_', ' ', dirtify_page_id($pathsuser));
    }
    else
    {
      echo 'No username provided';
      return false;
    }
    $q = $db->sql_query('SELECT u.user_id AS authoritative_uid, u.username, u.email, u.real_name, u.signature, u.account_active, u.user_level, u.user_rank, u.user_title, u.user_has_avatar, u.avatar_type, u.user_registration_ip, x.* FROM '.table_prefix.'users AS u
                           LEFT JOIN '.table_prefix.'users_extra AS x
                             ON ( u.user_id = x.user_id OR x.user_id IS NULL )
                           WHERE ( ' . ENANO_SQLFUNC_LOWERCASE . '(u.username) = \'' . $db->escape(strtolower($username)) . '\' OR u.username = \'' . $db->escape($username) . '\' ) AND u.user_id != 1;');
    if ( !$q )
      $db->_die();
    
    if ( $db->numrows() < 1 )
    {
      echo '<div class="error-box">' . $lang->get('acpum_err_bad_username') . '</div>';
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
      $form->user_rank = $row['user_rank'];
      $form->user_title= $row['user_title'];
      $form->account_active = ( $row['account_active'] == 1 );
      $form->email_public   = ( $row['email_public'] == 1 );
      $form->has_avatar     = ( $row['user_has_avatar'] == 1 );
      $form->avi_type       = $row['avatar_type'];
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
      $form->reg_ip_addr = ( $row['user_registration_ip'] ) ? $row['user_registration_ip'] : '';
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
      echo '<div class="error-box">' . $lang->get('acpum_err_sessionclear_demo') . '</div>';
    }
    else
    {
      // Get the current session information so the user doesn't get logged out
      $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
      $sk = md5($session->sid_super);
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
      $db->sql_query('INSERT INTO '.table_prefix.'session_keys( session_key,salt,user_id,auth_level,source_ip,time ) VALUES( \''.$ra['session_key'].'\', \'' . $db->escape($ra['salt']) . '\', \''.$session->user_id.'\', \''.$ra['auth_level'].'\', \''.$ra['source_ip'].'\', '.$ra['time'].' ),( \''.$rb['session_key'].'\', \'' . $db->escape($rb['salt']) . '\', \''.$session->user_id.'\', \''.$rb['auth_level'].'\', \''.$rb['source_ip'].'\', '.$rb['time'].' )');
      
      echo '<div class="info-box">' . $lang->get('acpum_msg_sessionclear_success') . '</div>';
    }
  }
  echo '<form action="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->cpage['module'], true) . '" method="post" enctype="multipart/form-data" onsubmit="if ( !submitAuthorized ) return false;">';
  echo '<h3>' . $lang->get('acpum_heading_main') . '</h3>';
  echo '<p>' . $lang->get('acpum_hint_intro') . '</p>';
  echo '<table border="0">
          <tr>
            <td><b>' . $lang->get('acpum_field_search_user') . '</b><br />
                <small>' . $lang->get('acpum_field_search_user_hint') . '</small>
                </td>
            <td style="width: 10px;"></td>
            <td>' . $template->username_field('username') . '</td>
            <td>
              <input type="submit" name="action[go]" value="' . $lang->get('acpum_btn_search_user_go') . ' &raquo;" />
            </td>
          </tr>
        </table>';
  echo '<h3>' . $lang->get('acpum_heading_clear_sessions') . '</h3>';
  echo '<p>' . $lang->get('acpum_hint_clear_sessions') . '</p>';
  echo '<p><input type="submit" name="action[clear_sessions]" value="' . $lang->get('acpum_btn_clear_sessions') . '" /></p>';
  echo '</form>';
  
  if(isset($_GET['action']) && isset($_GET['user']))
  {
    switch($_GET['action'])
    {
      case "activate":
        $e = $db->sql_query('SELECT activation_key FROM '.table_prefix.'users WHERE username=\'' . $db->escape($_GET['user']) . '\'');
        if ( $e )
        {
          // attempt to activate the account
          $row = $db->fetchrow();
          $db->free_result();
          if ( $session->activate_account($_GET['user'], $row['activation_key']) )
          {
            echo '<div class="info-box">' . $lang->get('acpum_msg_activate_success', array('username' => htmlspecialchars($_GET['user']))) . '</div>';
            $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE time_id=' . $db->escape($_GET['logid']));
          }
          else
          {
            echo '<div class="warning-box">' . $lang->get('acpum_err_activate_fail', array('username' => htmlspecialchars($_GET['user']))) . '</div>';
          }
        }
        else
        {
          echo '<div class="error-box">Error activating account: '.$db->get_error().'</div>';
        }
        break;
      case "sendemail":
        if ( $session->send_activation_mail($_GET['user'] ) )
        {
          echo '<div class="info-box">' . $lang->get('acpum_msg_activate_email_success', array('username' => htmlspecialchars($_GET['user']))) . '</div>';
          $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE time_id=' . $db->escape($_GET['logid']));
        }
        else
        {
          echo '<div class="error-box">' . $lang->get('acpum_err_activate_email_fail', array('username' => htmlspecialchars($_GET['user']))) . '</div>';
        }
        break;
      case "deny":
        $e = $db->sql_query('DELETE FROM '.table_prefix.'logs WHERE log_type=\'admin\' AND action=\'activ_req\' AND time_id=\'' . $db->escape($_GET['logid']) . '\';');
        if ( !$e )
        {
          echo '<div class="error-box">Error during row deletion: '.$db->get_error().'</div>';
        }
        else
        {
          echo '<div class="info-box">' . $lang->get('acpum_msg_activate_deny_success', array('username' => htmlspecialchars($_GET['user']))) . '</div>';
        }
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
      $str = ( $n == 1 ) ?
        $lang->get('acpum_heading_activation_one') :
        $lang->get('acpum_heading_activation_plural', array('count' => strval($n)));
        
      echo '<h3>' . $str . '</h3>';
        
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4" width="100%">
                <tr>
                  <th>' . $lang->get('acpum_col_activate_timestamp') . '</th>
                  <th>' . $lang->get('acpum_col_activate_requestedby') . '</th>
                  <th>' . $lang->get('acpum_col_activate_requestedfor') . '</th>
                  <th>' . $lang->get('acpum_col_activate_coppauser') . '</th>
                  <th colspan="3">' . $lang->get('acpum_col_activate_actions') . '</th>
                </tr>';
      $cls = 'row2';
      while($row = $db->fetchrow())
      {
        if($cls == 'row2') $cls = 'row1';
        else $cls = 'row2';
        $coppa = ( $row['user_coppa'] == '1' ) ? '<b>' . $lang->get('acpum_coppauser_yes') . '</b>' : $lang->get('acpum_coppauser_no');
        echo '<tr>
                <td class="'.$cls.'">'.enano_date(ED_DATE | ED_TIME, $row['time_id']).'</td>
                <td class="'.$cls.'">'.$row['author'].'</td>
                <td class="'.$cls.'">'.$row['edit_summary'].'</td>
                <td style="text-align: center;" class="' . $cls . '">' . $coppa . '</td>
                <td class="'.$cls.'" style="text-align: center;">
                  <a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&action=activate&user='.rawurlencode($row['edit_summary']).'&logid='.$row['time_id'], true).'">' . $lang->get('acpum_btn_activate_now') . '</a>
                </td>
                <td class="'.$cls.'" style="text-align: center;">
                  <a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&action=sendemail&user='.rawurlencode($row['edit_summary']).'&logid='.$row['time_id'], true).'">' . $lang->get('acpum_btn_send_email') . '</a>
                </td>
                <td class="'.$cls.'" style="text-align: center;">
                  <a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'UserManager&action=deny&user='.rawurlencode($row['edit_summary']).'&logid='.$row['time_id'], true).'">' . $lang->get('acpum_btn_activate_deny') . '</a>
                </td>
              </tr>';
      }
      echo '</table>';
      echo '</div>';
    }
    $db->free_result();
  }
  
  acp_usermanager_lockouts();
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
   * User-specific user rank
   * @var int
   */
  
  var $user_rank = NULL;
  
  /**
   * User's custom title
   * @var int
   */
  
  var $user_title = '';
  
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
   * Whether the user has an avatar or not.
   * @var bool
   */
  
  var $has_avatar = false;
  
  /**
   * The type of avatar the user has. One of "jpg", "png", or "gif".
   * @var string
   */
  
  var $avi_type = 'png';
  
  /**
   * The IP address of the user during registration
   * @var string
   */
  
  var $reg_ip_addr = '';
  
  /**
   * Constructor.
   */
  
  function Admin_UserManager_SmartForm()
  {
    $this->uuid = md5( mt_rand() . microtime() );
  }
  
  /**
   * Renders and returns the finished form.
   * @return string
   */
  
  function render()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $dh_supported;
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
              return runEncryption(true);
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
                  {lang:acpum_heading_editing_user} {USERNAME}
                </th>
              </tr>
              
              <!-- Basic options (stored in enano_users) -->
              
                <tr>
                  <th colspan="2" class="subhead">
                    {lang:acpum_heading_basic_options}
                  </th>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    {lang:acpum_field_username}<br />
                    <small>{lang:acpum_field_username_hint}</small>
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="username" value="{USERNAME}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/>
                    <!-- BEGIN same_user --><small>{lang:acpum_msg_same_user_username}</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2">
                    {lang:acpum_field_password}
                    <!-- BEGIN password_meter -->
                    <br />
                    <small>{lang:acpum_field_password_hint}</small>
                    <!-- END password_meter -->
                  </td>
                  <td class="row1">
                    <div id="userform_{UUID}_pwlink">
                      <b>{lang:acpum_msg_password_unchanged}</b> <a href="#" onclick="userform_{UUID}_chpasswd(); return false;">{lang:acpum_btn_reset_password}</a>
                    </div>
                    <div id="userform_{UUID}_pwform" style="display: none;">
                      <!-- BEGIN same_user -->
                        {lang:acpum_msg_same_user_password} <a href="#" onclick="userform_{UUID}_chpasswd_cancel(); return false;">{lang:etc_cancel}</a>
                      <!-- BEGINELSE same_user -->
                      <input type="hidden" name="changing_pw" value="no" />
                      {AES_FORM}
                      <table border="0" style="background-color: transparent;" cellspacing="0" cellpadding="0">
                        <tr>
                          <td colspan="2">
                            <b>{lang:acpum_field_password_title}</b>
                          </td>
                        </tr>
                        <tr>
                          <td>{lang:acpum_field_newpassword}</td>
                          <td>
                          <!-- BEGIN password_meter -->
                            <input type="password" name="new_password" value="" onkeyup="password_score_field(this);" /><span class="password-checker" style="font-weight: bold; color: #A0A0A0"> Waiting for l10n init</span>
                          <!-- BEGINELSE password_meter -->
                            <input type="password" name="new_password" value="" />
                          <!-- END password_meter -->
                          <!-- BEGIN password_meter -->
                            <div id="pwmeter" style="margin: 4px 0; height: 8px;"></div>
                          <!-- END password_meter -->
                          </td>
                        </tr>
                        <tr>
                          <td>{lang:acpum_field_newpassword_confirm}</td>
                          <td><input type="password" name="new_password_confirm" value="" /></td>
                        </tr>
                        <tr>
                          <td colspan="2">
                            <a href="#" onclick="userform_{UUID}_chpasswd_cancel(); return false;">{lang:etc_cancel}</a>
                          </td>
                        </tr>
                      </table>
                      <!-- END same_user -->
                    </div>
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    {lang:acpum_field_email}
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="email" value="{EMAIL}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/>
                    <!-- BEGIN same_user --><small>{lang:acpum_msg_same_user_email}</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    {lang:acpum_field_realname}
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="real_name" value="{REAL_NAME}" size="40" <!-- BEGIN same_user -->disabled="disabled" <!-- END same_user -->/>
                    <!-- BEGIN same_user --><small>{lang:acpum_msg_same_user_realname}</small><!-- END same_user -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    {lang:acpum_field_signature}
                  </td>
                  <td class="row1" style="width: 75%;">
                    {SIGNATURE_FIELD}
                  </td>
                </tr>
                
                <tr>
                  <td class="row2" style="width: 25%;">
                    {lang:acpum_field_usertitle}<br />
                    <small>
                      {lang:acpum_field_usertitle_hint}
                    </small>
                  </td>
                  <td class="row1" style="width: 75%;">
                    <input type="text" name="user_title" value="{USER_TITLE}" />
                  </td>
                </tr>
                
                
                
              <!-- / Basic options -->
              
              <!-- Extended options (anything in enano_users_extra) -->
              
                <tr>
                  <th class="subhead" colspan="2">
                    {lang:acpum_heading_imcontact}
                  </th>
                <tr>
                  <td class="row2">{lang:acpum_field_aim}</td>
                  <td class="row1"><input type="text" name="imaddr_aim" value="{IM_AIM}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_wlm}<br /><small>{lang:acpum_field_wlm_hint}</small></td>
                  <td class="row1"><input type="text" name="imaddr_msn" value="{IM_WLM}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_yim}</td>
                  <td class="row1"><input type="text" name="imaddr_yahoo" value="{IM_YAHOO}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_xmpp}</td>
                  <td class="row1"><input type="text" name="imaddr_xmpp" value="{IM_XMPP}" size="30" /></td>
                </tr>
                <tr>
                  <th class="subhead" colspan="2">
                    {lang:acpum_heading_contact_extra}
                  </th>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_homepage}<br /><small>{lang:acpum_field_homepage_hint}</small></td>
                  <td class="row1"><input type="text" name="homepage" value="{HOMEPAGE}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_location}</td>
                  <td class="row1"><input type="text" name="location" value="{LOCATION}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_job}</td>
                  <td class="row1"><input type="text" name="occupation" value="{JOB}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2">{lang:acpum_field_hobbies}</td>
                  <td class="row1"><input type="text" name="hobbies" value="{HOBBIES}" size="30" /></td>
                </tr>
                <tr>
                  <td class="row2"><label for="chk_email_public_{UUID}">{lang:acpum_field_email_public}</label><br /><small>{lang:acpum_field_email_public_hint}</small></td>
                  <td class="row1"><input type="checkbox" id="chk_email_public_{UUID}" name="email_public" <!-- BEGIN email_public -->checked="checked" <!-- END email_public -->size="30" /></td>
                </tr>
              
              <!-- / Extended options -->
              
              <!-- Avatar settings -->
              
                <tr>
                  <th class="subhead" colspan="2">
                    {lang:acpum_avatar_heading}
                  </th>
                </tr>
                
                <tr>
                  <td class="row2">
                    {lang:usercp_avatar_label_current}
                  </td>
                  <td class="row1">
                    <!-- BEGIN user_has_avatar -->
                      <img alt="{AVATAR_ALT}" src="{AVATAR_SRC}" />
                    <!-- BEGINELSE user_has_avatar -->
                      {lang:acpum_avatar_image_none}
                    <!-- END user_has_avatar -->
                  </td>
                </tr>
                
                <tr>
                  <td class="row2">
                    {lang:acpum_avatar_lbl_change}
                  </td>
                  <td class="row1" id="avatar_upload_btns_{UUID}">
                    <script type="text/javascript">
                      function admincp_users_avatar_set_{UUID}(elParent)
                      {
                        $('td#avatar_upload_btns_{UUID} > div:visible').hide('blind');
                        switch(elParent.value)
                        {
                          case 'set_http':
                            $('#avatar_upload_http_{UUID}').show('blind');
                            break;
                          case 'set_file':
                            $('#avatar_upload_file_{UUID}').show('blind');
                            break;
                          case 'set_gravatar':
                            $('#avatar_upload_gravatar_{UUID}').show('blind');
                            break;
                        }
                      }
                    </script>
                    <label><input onclick="admincp_users_avatar_set_{UUID}(this);" type="radio" name="avatar_action" value="keep" checked="checked" /> {lang:acpum_avatar_lbl_keep}</label><br />
                    <label><input onclick="admincp_users_avatar_set_{UUID}(this);" type="radio" name="avatar_action" value="remove" /> {lang:acpum_avatar_lbl_remove}</label><br />
                    <label><input onclick="admincp_users_avatar_set_{UUID}(this);" type="radio" name="avatar_action" value="set_http" /> {lang:acpum_avatar_lbl_set_http}</label><br />
                      <div id="avatar_upload_http_{UUID}" style="display: none; margin: 10px 0 0 2.2em;">
                        {lang:usercp_avatar_lbl_url} <input type="text" name="avatar_http_url" size="40" value="http://" /><br />
                        <small>{lang:usercp_avatar_lbl_url_desc} {lang:usercp_avatar_limits}</small>
                      </div>
                    <label><input onclick="admincp_users_avatar_set_{UUID}(this);" type="radio" name="avatar_action" value="set_file" /> {lang:acpum_avatar_lbl_set_file}</label><br />
                      <div id="avatar_upload_file_{UUID}" style="display: none; margin: 10px 0 0 2.2em;">
                        {lang:usercp_avatar_lbl_file} <input type="file" name="avatar_file" size="40" value="http://" /><br />
                        <small>{lang:usercp_avatar_lbl_file_desc} {lang:usercp_avatar_limits}</small>
                      </div>
                    <label><input onclick="admincp_users_avatar_set_{UUID}(this);" type="radio" name="avatar_action" value="set_gravatar" /> {lang:acpum_avatar_lbl_set_gravatar} <img alt=" " src="{GRAVATAR_URL}" /></label><br />
                      <div id="avatar_upload_gravatar_{UUID}"></div>
                  </td>
                </tr>
                
              <!-- / Avatar settings -->
              
              <!-- Administrator-only options -->
              
                <tr>
                  <th class="subhead" colspan="2">
                    {lang:acpum_heading_adminonly}
                  </th>
                </tr>
                
                <tr>
                  <td class="row2">{lang:acpum_field_active_title}<br />
                                   <small>{lang:acpum_field_active_hint}</small>
                                   </td>
                  <td class="row1"><label><input type="checkbox" name="account_active" <!-- BEGIN account_active -->checked="checked" <!-- END account_active -->/> {lang:acpum_field_active}</label></td>
                </tr>
                
                <tr>
                  <td class="row2">
                    {lang:acpum_field_userlevel}<br />
                    <small>{lang:acpum_field_userlevel_hint}</small>
                  </td>
                  <td class="row1">
                    <select name="user_level">
                      <option value="{USER_LEVEL_MEMBER}"<!-- BEGIN ul_member --> selected="selected"<!-- END ul_member -->>{lang:userfuncs_ml_level_member}</option>
                      <option value="{USER_LEVEL_MOD}"<!-- BEGIN ul_mod --> selected="selected"<!-- END ul_mod -->>{lang:userfuncs_ml_level_mod}</option>
                      <option value="{USER_LEVEL_ADMIN}"<!-- BEGIN ul_admin --> selected="selected"<!-- END ul_admin -->>{lang:userfuncs_ml_level_admin}</option>
                    </select>
                  </td>
                </tr>
                
                <tr>
                  <td class="row2">
                    {lang:acpum_field_userrank}<br />
                    <small>{lang:acpum_field_userrank_hint}</small>
                  </td>
                  <td class="row1">
                    <select name="user_rank">
                      {RANK_LIST}
                    </select>
                  </td>
                </tr>
                
                <!-- BEGIN have_reg_ip -->
                <tr>
                  <td class="row2">
                    {lang:acpum_field_reg_ip}
                  </td>
                  <td class="row1">
                    {REG_IP_ADDR}
                    <input type="hidden" name="user_registration_ip" value="{REG_IP_ADDR}" />
                  </td>
                </tr>
                <!-- BEGINELSE have_reg_ip -->
                <input type="hidden" name="user_registration_ip" value="" />
                <!-- END have_reg_ip -->
                
                <tr>
                  <td class="row2">
                    {lang:acpum_field_deleteaccount_title}
                  </td>
                  <td class="row1">
                  <label><input type="checkbox" name="delete_account" onclick="var d = (this.checked) ? 'block' : 'none'; document.getElementById('delete_blurb_{UUID}').style.display = d;" /> {lang:acpum_field_deleteaccount}</label>
                    <div id="delete_blurb_{UUID}" style="display: none;">
                      <!-- BEGIN same_user -->
                      <!-- Obnoxious I know, but it's needed. -->
                      <p><b>{lang:acpum_msg_delete_own_account}</b></p>
                      <!-- END same_user -->
                      <p><small>{lang:acpum_field_deleteaccount_hint}</small></p>
                    </div>
                  </td>
                </tr>
                </tr>
              
              <!-- Save button -->
              <tr>
                <th colspan="2">
                  <input type="submit" name="action[save]" value="{lang:acpum_btn_save}" style="font-weight: bold;" />
                  <input type="submit" name="action[noop]" value="{lang:etc_cancel}" style="font-weight: normal;" />
                </th>
              </tr>
            
            </table>
          </div>
        
        </form>
        
        <!-- BEGINNOT same_user -->
        <script type="text/javascript">
        password_score_field(document.forms['useredit_{UUID}'].new_password);
        </script>
        <!-- END same_user -->
        
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
    $aes_javascript = $session->aes_javascript("useredit_$this->uuid", 'new_password');
    
    // build rank list
    $q = $db->sql_query('SELECT rank_id, rank_title FROM ' . table_prefix . 'ranks');
    if ( !$q )
      $db->_die();
    $rank_list = '<option value="NULL"' . ( $this->user_rank === NULL ? ' selected="selected"' : '' ) . '>--</option>' . "\n";
    while ( $row = $db->fetchrow() )
    {
      $rank_list .= '<option value="' . $row['rank_id'] . '"' . ( $row['rank_id'] == $this->user_rank ? ' selected="selected"' : '' ) . '>' . htmlspecialchars($lang->get($row['rank_title'])) . '</option>' . "\n";
    }
    
    $parser->assign_vars(array(
        'UUID' => $this->uuid,
        'USERNAME' => $this->username,
        'EMAIL' => $this->email,
        'USER_ID' => $this->user_id,
        'AES_FORM' => $session->generate_aes_form(),
        'REAL_NAME' => $this->real_name,
        'SIGNATURE_FIELD' => $template->tinymce_textarea('signature', $this->signature, 10, 50),
        'USER_TITLE' => $this->user_title,
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
        'FORM_ACTION' => $form_action,
        'REG_IP_ADDR' => $this->reg_ip_addr,
        'RANK_LIST' => $rank_list,
        'GRAVATAR_URL' => make_gravatar_url($this->email, 16)
      ));
    
    if ( $this->has_avatar )
    {
      $parser->assign_vars(array(
          'AVATAR_SRC' => make_avatar_url($this->user_id, $this->avi_type),
          'AVATAR_ALT' => $lang->get('usercp_avatar_image_alt', array('username' => $this->username), $this->email)
        ));
    }
    
    $parser->assign_bool(array(
        'password_meter' => ( getConfig('pw_strength_enable') == '1' ),
        'ul_member' => ( $this->user_level == USER_LEVEL_CHPREF ),
        'ul_mod' => ( $this->user_level == USER_LEVEL_MOD ),
        'ul_admin' => ( $this->user_level == USER_LEVEL_ADMIN ),
        'account_active' => ( $this->account_active === true ),
        'email_public' => ( $this->email_public === true ),
        'same_user' => ( $this->user_id == $session->user_id ),
        'user_has_avatar' => ( $this->has_avatar ),
        'have_reg_ip' => ( intval(@strlen($this->reg_ip_addr)) > 0 && is_valid_ip($this->reg_ip_addr) )
      ));
    
    $parsed = $parser->run();
    return $parsed;
  }
  
}

function acp_usermanager_lockouts($homewrap = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  // Locked out users
  
  if ( !empty($_GET['clear_lockout']) && is_valid_ip($_GET['clear_lockout']) )
  {
    $ip = $db->escape($_GET['clear_lockout']);
    $q = $db->sql_query('DELETE FROM ' . table_prefix . "lockout WHERE ipaddr = '$ip' AND timestamp > ( " . time() . " - (" . getConfig('lockout_duration', 15) . "*60) );");
    if ( !$q )
      $db->_die();
    
    echo '<div class="info-box">' . $lang->get('acphome_msg_lockout_clear_success', array('ip' => htmlspecialchars($ip))) . '</div>';
  }
  
  $q = $db->sql_query('SELECT COUNT(id) AS fail_count, ipaddr, username, timestamp FROM ' . table_prefix . "lockout\n"
                    . "  WHERE timestamp > ( " . time() . " - " . intval(getConfig('lockout_duration', 15)) . "*60 ) GROUP BY ipaddr ORDER BY COUNT(id) DESC, timestamp DESC;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() > 0 )
  {
    if ( $homewrap )
      echo '<div class="acphome-box notice">';
    echo '<h3>' . $lang->get('acphome_msg_users_locked_out') . '</h3>';
    echo '<p>' . $lang->get('acphome_msg_users_locked_out_hint') . '</p>';
    
    ?>
    <div class="tblholder" style="margin-bottom: 10px;">
    <table width="100%" cellspacing="1" cellpadding="4">
      <tr>
        <th><?php echo $lang->get('acphome_th_locked_out_ip'); ?></th>
        <th><?php echo $lang->get('acphome_th_locked_out_username'); ?></th>
        <th><?php echo $lang->get('acphome_th_locked_out_status'); ?></th>
        <th><?php echo $lang->get('acphome_th_locked_out_time'); ?></th>
        <th></th>
      </tr>
    <?php
    
    while ( $row = $db->fetchrow() )
    {
      echo '<tr>';
      echo '<td class="row1">' . htmlspecialchars($row['ipaddr']) . '</td>';
      echo '<td class="row2">' . htmlspecialchars($row['username']) . '</td>';
      // status
      echo '<td class="row1" style="text-align: center;">' .
            ( $row['fail_count'] >= getConfig('lockout_threshold', 5)
                ? '<b>' . $lang->get('acphome_lbl_locked_out_banned') . '</b>'
                : $lang->get('acphome_lbl_locked_out_warned', array('fail_count' => $row['fail_count']))
            )
            . '</td>';
      // time left
      if ( $row['fail_count'] >= getConfig('lockout_threshold', 5) )
      {
        $expire_time = $row['timestamp'] + ( getConfig('lockout_duration', 15) * 60 );
        $time_left = round(($expire_time - time()) / 60);
        $minutes = $time_left == 1 ? $lang->get('etc_unit_minute') : $lang->get('etc_unit_minutes');
        echo '<td class="row2" style="text-align: center;">' . "$time_left $minutes" . '</td>';
      }
      else
      {
        echo '<td class="row2" style="text-align: center;">&ndash;</td>';
      }
      // action
      $btn_text = $row['fail_count'] >= getConfig('lockout_threshold', 5) ? $lang->get('acphome_btn_lockout_unblock') : $lang->get('acphome_btn_lockout_clear');
      echo '<td class="row1" style="text-align: center;"><a href="#" onclick="ajaxPage(\'' . $paths->nslist['Admin'] . 'UserManager\', \'clear_lockout=' . htmlspecialchars($row['ipaddr']) . '\'); return false;">' . $btn_text . '</a></td>';
      echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    if ( $homewrap )
      echo '</div>';
  }
  
  $db->free_result();
}
