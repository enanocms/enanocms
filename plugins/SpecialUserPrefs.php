<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialuserprefs_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialuserprefs_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.4",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

$userprefs_menu = Array();
$userprefs_menu_links = Array();
function userprefs_menu_add($section, $text, $link)
{
  global $userprefs_menu;
  if ( isset($userprefs_menu[$section]) && is_array($userprefs_menu[$section]) )
  {
    $userprefs_menu[$section][] = Array(
      'text' => $text,
      'link' => $link
      );
  }
  else
  {
    $userprefs_menu[$section] = Array(Array(
      'text' => $text,
      'link' => $link
      ));
  }
}

$plugins->attachHook('compile_template', 'userprefs_jbox_setup($button, $tb, $menubtn);');

function userprefs_jbox_setup(&$button, &$tb, &$menubtn)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( $paths->namespace != 'Special' || $paths->page_id != 'Preferences' )
    return false;
  
  $tb .= "<ul>$template->toolbar_menu</ul>";
  $template->toolbar_menu = '';
  
  $button->assign_vars(array(
      'TEXT' => $lang->get('usercp_btn_memberlist'),
      'FLAGS' => '',
      'PARENTFLAGS' => '',
      'HREF' => makeUrlNS('Special', 'Memberlist')
    ));
  
  $tb .= $button->run();
}

function userprefs_menu_html()
{
  global $userprefs_menu;
  global $userprefs_menu_links;
  global $lang;
  
  $html = '';
  $quot = '"';
  
  foreach ( $userprefs_menu as $section => $buttons )
  {
    $section_name = $section;
    if ( preg_match('/^[a-z]+_[a-z_]+$/', $section) )
    {
      $section_name = $lang->get($section_name);
    }
    $html .= ( isset($userprefs_menu_links[$section]) ) ? "<a href={$quot}{$userprefs_menu_links[$section]}{$quot}>{$section_name}</a>\n        " : "<a>{$section_name}</a>\n        ";
    $html .= "<ul>\n          ";
    foreach ( $buttons as $button )
    {
      $buttontext = $button['text'];
      if ( preg_match('/^[a-z]+_[a-z_]+$/', $buttontext) )
      {
        $buttontext = $lang->get($buttontext);
      }
      $html .= "  <li><a href={$quot}{$button['link']}{$quot}>{$buttontext}</a></li>\n          ";
    }
    $html .= "</ul>\n        ";
  }
  
  return $html;
}

function userprefs_show_menu()
{
  echo '<div class="menu_nojs">
          ' . userprefs_menu_html() . '
          <span class="menuclear"></span>
        </div>
        <br />
        ';
}

function userprefs_menu_init()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $userprefs_menu_links;
  
  userprefs_menu_add('usercp_sec_profile', 'usercp_sec_profile_emailpassword', makeUrlNS('Special', 'Preferences/EmailPassword') . '" onclick="ajaxLoginNavTo(\'Special\', \'Preferences/EmailPassword\', '.USER_LEVEL_CHPREF.'); return false;');
  userprefs_menu_add('usercp_sec_profile', 'usercp_sec_profile_signature', makeUrlNS('Special', 'Preferences/Signature'));
  userprefs_menu_add('usercp_sec_profile', 'usercp_sec_profile_publicinfo', makeUrlNS('Special', 'Preferences/Profile'));
  userprefs_menu_add('usercp_sec_profile', 'usercp_sec_profile_usergroups', makeUrlNS('Special', 'Usergroups'));
  if ( getConfig('avatar_enable') == '1' )
  {
    userprefs_menu_add('usercp_sec_profile', 'usercp_sec_profile_avatar', makeUrlNS('Special', 'Preferences/Avatar'));
  }
  userprefs_menu_add('usercp_sec_pm', 'usercp_sec_pm_inbox', makeUrlNS('Special', 'PrivateMessages/Folder/Inbox'));
  userprefs_menu_add('usercp_sec_pm', 'usercp_sec_pm_outbox', makeUrlNS('Special', 'PrivateMessages/Folder/Outbox'));
  userprefs_menu_add('usercp_sec_pm', 'usercp_sec_pm_sent', makeUrlNS('Special', 'PrivateMessages/Folder/Sent'));
  userprefs_menu_add('usercp_sec_pm', 'usercp_sec_pm_drafts', makeUrlNS('Special', 'PrivateMessages/Folder/Drafts'));
  userprefs_menu_add('usercp_sec_pm', 'usercp_sec_pm_archive', makeUrlNS('Special', 'PrivateMessages/Folder/Archive'));
  
  /*
  // Reserved for Enano's Next Big Innovation.(TM)
  userprefs_menu_add('Private messages', 'Inbox', makeUrlNS('Special',      'Private_Messages#folder:inbox'));
  userprefs_menu_add('Private messages', 'Starred', makeUrlNS('Special',     'Private_Messages#folder:starred'));
  userprefs_menu_add('Private messages', 'Sent items', makeUrlNS('Special', 'Private_Messages#folder:sent'));
  userprefs_menu_add('Private messages', 'Drafts', makeUrlNS('Special',     'Private_Messages#folder:drafts'));
  userprefs_menu_add('Private messages', 'Archive', makeUrlNS('Special',    'Private_Messages#folder:archive'));
  userprefs_menu_add('Private messages', 'Trash', makeUrlNS('Special',    'Private_Messages#folder:trash'));
  */
  
  $userprefs_menu_links['Profile/membership'] = makeUrlNS('Special', 'Preferences');
  $userprefs_menu_links['Private messages']  = makeUrlNS('Special', 'PrivateMessages');
  
  $code = $plugins->setHook('userprefs_jbox');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
}

$plugins->attachHook('common_post', 'userprefs_menu_init();');

function page_Special_Preferences()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $timezone;
  
  // We need a login to continue
  if ( !$session->user_logged_in )
    redirect(makeUrlNS('Special', 'Login/' . $paths->page), 'Login required', 'You need to be logged in to access this page. Please wait while you are redirected to the login page.');
  
  // User ID - later this will be specified on the URL, but hardcoded for now
  $uid = intval($session->user_id);
  
  // Instanciate the AES encryptor
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  
  // Basic user info
  $q = $db->sql_query('SELECT username, password, email, real_name, signature, theme, style FROM '.table_prefix.'users WHERE user_id='.$uid.';');
  if ( !$q )
    $db->_die();
  
  $row = $db->fetchrow();
  $db->free_result();
  
  $section = $paths->getParam(0);
  if ( !$section )
  {
    $section = 'Home';
  }
  
  $errors = '';
  
  switch ( $section )
  {
    case 'EmailPassword':
      // Require elevated privileges (well sortof)
      if ( $session->auth_level < USER_LEVEL_CHPREF )
      {
        redirect(makeUrlNS('Special', 'Login/' . $paths->fullpage, 'level=' . USER_LEVEL_CHPREF, true), 'Authentication required', 'You need to re-authenticate to access this page.', 0);
      }
      
      if ( isset($_POST['submit']) )
      {
        $email_changed = false;
        // First do the e-mail address
        if ( strlen($_POST['newemail']) > 0 )
        {
          switch('foo') // Same reason as in the password code...
          {
            case 'foo':
              if ( $_POST['newemail'] != $_POST['newemail_conf'] )
              {
                $errors .= '<div class="error-box">' . $lang->get('usercp_emailpassword_err_email_no_match') . '</div>';
                break;
              }
          }
          $q = $db->sql_query('SELECT password FROM '.table_prefix.'users WHERE user_id='.$session->user_id.';');
          if ( !$q )
            $db->_die();
          $row = $db->fetchrow();
          $db->free_result();
          $old_pass = $aes->decrypt($row['password'], $session->private_key, ENC_HEX);
          
          $new_email = $_POST['newemail'];
          
          $result = $session->update_user($session->user_id, false, $old_pass, false, $new_email);
          if ( $result != 'success' )
          {
            $message = '<p>' . $lang->get('usercp_emailpassword_err_list') . '</p>';
            $message .= '<ul><li>' . implode("</li>\n<li>", $result) . '</li></ul>';
            die_friendly($lang->get('usercp_emailpassword_err_title'), $message);
          }
          $email_changed = true;
        }
        // Obtain password
        if ( $_POST['use_crypt'] == 'yes' && !empty($_POST['crypt_data']) )
        {
          $key = $session->fetch_public_key($_POST['crypt_key']);
          if ( !$key )
            die('Can\'t lookup key');
          $key = hexdecode($key);
          $newpass = $aes->decrypt($_POST['crypt_data'], $key, ENC_HEX);
          // At this point we know if we _want_ to change the password...
          
          // We can't check the password to see if it matches the confirmation
          // because the confirmation was destroyed during the encryption. I figured
          // this wasn't a big deal because if the encryption worked, then either
          // the Javascript validated it or the user hacked the form. In the latter
          // case, if he's smart enough to hack the encryption code, he's probably
          // smart enough to remember his password.
          
          if ( strlen($newpass) > 0 )
          {
            if ( defined('ENANO_DEMO_MODE') )
              $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">' . $lang->get('usercp_emailpassword_err_demo') . '</div>';
            // Perform checks
            if ( strlen($newpass) < 6 )
              $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">' . $lang->get('usercp_emailpassword_err_password_too_short') . '</div>';
            if ( getConfig('pw_strength_enable') == '1' )
            {
              $score_inp = password_score($newpass);
              if ( $score_inp < $score_min )
                $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">' . $lang->get('usercp_emailpassword_err_password_too_weak', array('score' => $score_inp)) . '</div>';
            }
            // Encrypt new password
            if ( empty($errors) )
            {
              $newpass_enc = $aes->encrypt($newpass, $session->private_key, ENC_HEX);
              // Perform the swap
              $q = $db->sql_query('UPDATE '.table_prefix.'users SET password=\'' . $newpass_enc . '\' WHERE user_id=' . $session->user_id . ';');
              if ( !$q )
                $db->_die();
              // Log out and back in
              $username = $session->username;
              $session->logout();
              if ( $email_changed )
              {
                if ( getConfig('account_activation') == 'user' )
                {
                  redirect(makeUrl(getConfig('main_page')), $lang->get('usercp_emailpassword_msg_profile_success'), $lang->get('usercp_emailpassword_msg_need_activ_user'), 20);
                }
                else if ( getConfig('account_activation') == 'admin' )
                {
                  redirect(makeUrl(getConfig('main_page')), $lang->get('usercp_emailpassword_msg_profile_success'), $lang->get('usercp_emailpassword_msg_need_activ_admin'), 20);
                }
              }
              $session->login_without_crypto($session->username, $newpass);
              redirect(makeUrlNS('Special', 'Preferences'), $lang->get('usercp_emailpassword_msg_pass_success'), $lang->get('usercp_emailpassword_msg_password_changed'), 5);
            }
          }
        }
        else
        {
          switch('foo') // allow breaking out of our section...i can't wait until PHP6 (goto support!)
          {
            case 'foo':
              $pass = $_POST['newpass'];
              if ( $pass != $_POST['newpass_conf'] )
              {
                $errors .= '<div class="error-box">' . $lang->get('usercp_emailpassword_err_password_no_match') . '</div>';
                break;
              }
              
              $session->logout();
              if ( $email_changed )
              {
                if ( getConfig('account_activation') == 'user' )
                {
                  redirect(makeUrl(getConfig('main_page')), $lang->get('usercp_emailpassword_msg_profile_success'), $lang->get('usercp_emailpassword_msg_need_activ_user'), 20);
                }
                else if ( getConfig('account_activation') == 'admin' )
                {
                  redirect(makeUrl(getConfig('main_page')), $lang->get('usercp_emailpassword_msg_profile_success'), $lang->get('usercp_emailpassword_msg_need_activ_admin'), 20);
                }
              }
              else
              {
                $session->login_without_crypto($session->username, $newpass);
                redirect(makeUrlNS('Special', 'Preferences'), $lang->get('usercp_emailpassword_msg_pass_success'), $lang->get('usercp_emailpassword_msg_password_changed'), 5);
              }
              
              return;
          }
        }
      }
      $template->tpl_strings['PAGE_NAME'] = $lang->get('usercp_emailpassword_title');
      break;
    case 'Signature':
      $template->tpl_strings['PAGE_NAME'] = $lang->get('usercp_signature_title');
      break;
    case 'Profile':
      $template->tpl_strings['PAGE_NAME'] = $lang->get('usercp_publicinfo_title');
      break;
  }
  
  $template->header();
  
  // Output the menu
  // This is not templatized because it conforms to the jBox menu standard.
  
  userprefs_show_menu();
        
  switch ( $section )
  {
    case 'Home':
      global $email;
      $userpage_id = $paths->nslist['User'] . sanitize_page_id($session->username);
      $userpage_exists = ( isPage($userpage_id) ) ? '' : ' class="wikilink-nonexistent"';
      $user_page = makeUrlNS('User', sanitize_page_id($session->username));
      $site_admin = $email->encryptEmail(getConfig('contact_email'), '', '', $lang->get('usercp_intro_para3_admin_link'));
      
      echo '<h3 style="margin-top: 0;">' . $lang->get('usercp_intro_heading_main', array('username' => $session->username)) . '</h3>';
      
      echo '<p>' . $lang->get('usercp_intro_para1') . '</p>
            <p>' . $lang->get('usercp_intro_para2', array('userpage_link' => $user_page)) . '</p>
            <p>' . $lang->get('usercp_intro_para3', array('admin_contact_link' => $site_admin)) . '</p>';
      break;
    case 'EmailPassword':
      
      $errors = trim($errors);
      if ( !empty($errors) )
      {
        echo $errors;
      }
      
      echo '<form action="' . makeUrlNS('Special', 'Preferences/EmailPassword') . '" method="post" onsubmit="return runEncryption();" name="empwform" >';
      
      // Password change form
      $pubkey = $session->rijndael_genkey();
      
      echo '<fieldset>
        <legend>' . $lang->get('usercp_emailpassword_grp_chpasswd') . '</legend>
        ' . $lang->get('usercp_emailpassword_field_newpass') . '<br />
          <input type="password" name="newpass" size="30" tabindex="1" ' . ( getConfig('pw_strength_enable') == '1' ? 'onkeyup="password_score_field(this);" ' : '' ) . '/>' . ( getConfig('pw_strength_enable') == '1' ? '<span class="password-checker" style="font-weight: bold; color: #aaaaaa;"> Loading...</span>' : '' ) . '
        <br />
        <br />
        ' . $lang->get('usercp_emailpassword_field_newpass_confirm') . '<br />
        <input type="password" name="newpass_conf" size="30" tabindex="2" />
        ' . ( getConfig('pw_strength_enable') == '1' ? '<br /><br /><div id="pwmeter"></div>
        <small>' . $lang->get('usercp_emailpassword_msg_password_min_score') . '</small>' : '' ) . '
      </fieldset><br />
      <fieldset>
        <legend>' . $lang->get('usercp_emailpassword_grp_chemail') . '</legend>
        ' . $lang->get('usercp_emailpassword_field_newemail') . '<br />
          <input type="text" value="' . ( isset($_POST['newemail']) ? htmlspecialchars($_POST['newemail']) : '' ) . '" name="newemail" size="30" tabindex="3" />
        <br />
        <br />
        ' . $lang->get('usercp_emailpassword_field_newemail_confirm') . '<br />
          <input type="text" value="' . ( isset($_POST['newemail']) ? htmlspecialchars($_POST['newemail']) : '' ) . '" name="newemail_conf" size="30" tabindex="4" />
      </fieldset>
      <input type="hidden" name="use_crypt" value="no" />
      <input type="hidden" name="crypt_key" value="' . $pubkey . '" />
      <input type="hidden" name="crypt_data" value="" />
      <br />
      <div style="text-align: right;"><input type="submit" name="submit" value="' . $lang->get('etc_save_changes') . '" tabindex="5" /></div>';
      
      echo '</form>';
      
      // ENCRYPTION CODE
      ?>
      <script type="text/javascript">
      <?php if ( getConfig('pw_strength_enable') == '1' ): ?>
      addOnloadHook(function()
        {
          password_score_field(document.forms.empwform.newpass);
        });
      <?php endif; ?>
        
        function runEncryption()
        {
          load_component('crypto');
          var aes_testpassed = aes_self_test();
          
          var frm = document.forms.empwform;
          if ( frm.newpass.value.length < 1 )
            return true;
          
          pass1 = frm.newpass.value;
          pass2 = frm.newpass_conf.value;
          if ( pass1 != pass2 )
          {
            alert($lang.get('usercp_emailpassword_err_password_no_match'));
            return false;
          }
          if ( pass1.length < 6 && pass1.length > 0 )
          {
            alert($lang.get('usercp_emailpassword_err_password_too_short'));
            return false;
          }
          
          if(aes_testpassed)
          {
            frm.use_crypt.value = 'yes';
            var cryptkey = frm.crypt_key.value;
            frm.crypt_key.value = hex_md5(cryptkey);
            cryptkey = hexToByteArray(cryptkey);
            if(!cryptkey || ( ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ) && cryptkey.length != keySizeInBits / 8 )
            {
              frm.submit.disabled = true;
              len = ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ? '\nLen: '+cryptkey.length : '';
              alert('The key is messed up\nType: '+typeof(cryptkey)+len);
            }
            pass = frm.newpass.value;
            pass = stringToByteArray(pass);
            cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
            if(!cryptstring)
            {
              return false;
            }
            cryptstring = byteArrayToHex(cryptstring);
            frm.crypt_data.value = cryptstring;
            frm.newpass.value = "";
            frm.newpass_conf.value = "";
          }
          return true;
        }
      </script>
      <?php
      
      break;
    case 'Signature':
      if ( isset($_POST['new_sig']) )
      {
        $sig = $_POST['new_sig'];
        $sig = RenderMan::preprocess_text($sig, true, false);
        $sql_sig = $db->escape($sig);
        $q = $db->sql_query('UPDATE '.table_prefix.'users SET signature=\'' . $sql_sig . '\' WHERE user_id=' . $session->user_id . ';');
        if ( !$q )
          $db->_die();
        $session->signature = $sig;
        echo '<div class="info-box" style="margin: 0 0 10px 0;">' . $lang->get('usercp_signature_msg_saved') . '</div>';
      }
      echo '<form action="'.makeUrl($paths->fullpage).'" method="post">';
      echo $template->tinymce_textarea('new_sig', htmlspecialchars($session->signature));
      echo '<input type="submit" value="' . $lang->get('usercp_signature_btn_save') . '" />';
      echo '</form>';
      break;
    case "Profile":
      if ( isset($_POST['submit']) )
      {
        $real_name = htmlspecialchars($_POST['real_name']);
        $real_name = $db->escape($real_name);
        
        $timezone = intval($_POST['timezone']);
        $tz_local = $timezone + 1440;
        
        $imaddr_aim = htmlspecialchars($_POST['imaddr_aim']);
        $imaddr_aim = $db->escape($imaddr_aim);
        
        $imaddr_msn = htmlspecialchars($_POST['imaddr_msn']);
        $imaddr_msn = $db->escape($imaddr_msn);
        
        $imaddr_yahoo = htmlspecialchars($_POST['imaddr_yahoo']);
        $imaddr_yahoo = $db->escape($imaddr_yahoo);
        
        $imaddr_xmpp = htmlspecialchars($_POST['imaddr_xmpp']);
        $imaddr_xmpp = $db->escape($imaddr_xmpp);
        
        $homepage = htmlspecialchars($_POST['homepage']);
        $homepage = $db->escape($homepage);
        
        $location = htmlspecialchars($_POST['location']);
        $location = $db->escape($location);
        
        $occupation = htmlspecialchars($_POST['occupation']);
        $occupation = $db->escape($occupation);
        
        $hobbies = htmlspecialchars($_POST['hobbies']);
        $hobbies = $db->escape($hobbies);
        
        $email_public = ( isset($_POST['email_public']) ) ? '1' : '0';
        $disable_js_fx = ( isset($_POST['disable_js_fx']) ) ? '1' : '0';
        
        $session->real_name = $real_name;
        
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
        
        $session->user_extra['user_aim'] = $imaddr_aim;
        $session->user_extra['user_msn'] = $imaddr_msn;
        $session->user_extra['user_xmpp'] = $imaddr_xmpp;
        $session->user_extra['user_yahoo'] = $imaddr_yahoo;
        $session->user_extra['user_homepage'] = $homepage;
        $session->user_extra['user_location'] = $location;
        $session->user_extra['user_job'] = $occupation;
        $session->user_extra['user_hobbies'] = $hobbies;
        $session->user_extra['email_public'] = intval($email_public);
        
        // user title
        $user_title_col = '';
        if ( $session->get_permissions('custom_user_title') && isset($_POST['user_title']) )
        {
          $user_title = trim($_POST['user_title']);
          if ( empty($user_title) )
          {
            $colval = 'NULL';
            $session->user_title = null;
          }
          else
          {
            $colval = "'" . $db->escape($user_title) . "'";
            $session->user_title = $user_title;
          }
          $user_title_col = ", user_title = $colval";
        }
        
        $q = $db->sql_query('UPDATE '.table_prefix."users SET real_name='$real_name', user_timezone = $tz_local{$user_title_col} WHERE user_id=$session->user_id;");
        if ( !$q )
          $db->_die();
        
        $q = $db->sql_query('UPDATE '.table_prefix."users_extra SET user_aim='$imaddr_aim',user_yahoo='$imaddr_yahoo',user_msn='$imaddr_msn',
                               user_xmpp='$imaddr_xmpp',user_homepage='$homepage',user_location='$location',user_job='$occupation',
                               user_hobbies='$hobbies',email_public=$email_public,disable_js_fx=$disable_js_fx
                               WHERE user_id=$session->user_id;");
        
        if ( !$q )
          $db->_die();
        
        // verify language id
        $lang_id = strval(intval($_POST['lang_id']));
        $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . 'language WHERE lang_id = ' . $lang_id . ';');
        if ( !$q )
          $db->_die();
        
        if ( $db->numrows() > 0 )
        {
          $db->free_result();
          
          // unload / reload $lang, this verifies that the selected language works
          unset($GLOBALS['lang']);
          unset($lang);
          $lang_id = intval($lang_id);
          $GLOBALS['lang'] = new Language($lang_id);
          global $lang;
          
          $q = $db->sql_query('UPDATE ' . table_prefix . 'users SET user_lang = ' . $lang_id . " WHERE user_id = {$session->user_id};");
          if ( !$q )
            $db->_die();
        }
        else
        {
          $db->free_result();
        }
        
        generate_cache_userranks();
        
        echo '<div class="info-box" style="margin: 0 0 10px 0;">' . $lang->get('usercp_publicinfo_msg_save_success') . '</div>';
      }
      
      $lang_box = '<select name="lang_id">';
      $q = $db->sql_query('SELECT lang_id, lang_name_native FROM ' . table_prefix . "language;");
      if ( !$q )
        $db->_die();
      
      while ( $row = $db->fetchrow_num() )
      {
        list($lang_id, $lang_name) = $row;
        $lang_name = htmlspecialchars($lang_name);
        $selected = ( $lang->lang_id == $lang_id ) ? ' selected="selected"' : '';
        $lang_box .= "<option value=\"$lang_id\"$selected>$lang_name</option>";
      }
      
      $lang_box .= '</select>';
      
      $tz_select = '<select name="timezone">';
      $tz_list = $lang->get('tz_list');
      try
      {
        $tz_list = enano_json_decode($tz_list);
      }
      catch(Exception $e)
      {
        die("Caught exception decoding timezone data: <pre>$e</pre>");
      }
      foreach ( $tz_list as $key => $i )
      {
        $i = ($i * 60);
        $title = $lang->get("tz_title_{$key}");
        $hrs = $lang->get("tz_hrs_{$key}");
        $selected = ( $i == $timezone ) ? ' selected="selected"' : '';
        $tz_select .= "<option value=\"$i\"$selected>$title</option>";
      }
      $tz_select .= '</select>';
      
      echo '<form action="'.makeUrl($paths->fullpage).'" method="post">';
      ?>
      <div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4">
          <tr>
            <th colspan="2"><?php echo $lang->get('usercp_publicinfo_heading_main'); ?></th>
          </tr>
          <tr>
            <td colspan="2" class="row3"><?php echo $lang->get('usercp_publicinfo_note_optional'); ?></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_realname'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="real_name" value="<?php echo $session->real_name; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2"><?php echo $lang->get('usercp_publicinfo_field_language') . '<br /><small>' . $lang->get('usercp_publicinfo_field_language_hint') . '</small>'; ?></td>
            <td class="row1"><?php echo $lang_box; ?></td>
          </tr>
          <tr>
            <td class="row2"><?php echo $lang->get('usercp_publicinfo_field_changetheme_title'); ?></td>
            <td class="row1"><?php echo $lang->get('usercp_publicinfo_field_changetheme_hint'); ?> <a href="<?php echo makeUrlNS('Special', 'ChangeStyle/' . $paths->page); ?>" onclick="ajaxChangeStyle(); return false;"><?php echo $lang->get('usercp_publicinfo_field_changetheme'); ?></a></td>
          </tr>
          <tr>
            <td class="row2"><?php echo $lang->get('usercp_publicinfo_field_timezone'); ?><br /><small><?php echo $lang->get('usercp_publicinfo_field_timezone_hint'); ?></small></td>
            <td class="row1"><?php echo $tz_select; ?></td>
          </tr>
          <?php
          if ( $session->get_permissions('custom_user_title') ):
          ?>
            <tr>
              <td class="row2">
                <?php echo $lang->get('usercp_publicinfo_field_usertitle_title'); ?><br />
                <small><?php echo $lang->get('usercp_publicinfo_field_usertitle_hint'); ?></small>
              </td>
              <td class="row1">
                <input type="text" name="user_title" value="<?php echo htmlspecialchars($session->user_title); ?>" />
              </td>
            </tr>
          <?php
          endif;
          ?>
          <tr>
            <th class="subhead" colspan="2">
              <?php echo $lang->get('usercp_publicinfo_th_im'); ?>
            </th>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_aim'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_aim" value="<?php echo $session->user_extra['user_aim']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_wlm'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_msn" value="<?php echo $session->user_extra['user_msn']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_yim'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_yahoo" value="<?php echo $session->user_extra['user_yahoo']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_xmpp'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_xmpp" value="<?php echo $session->user_extra['user_xmpp']; ?>" size="30" /></td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              <?php echo $lang->get('usercp_publicinfo_th_contact'); ?>
            </th>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_homepage'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="homepage" value="<?php echo $session->user_extra['user_homepage']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_location'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="location" value="<?php echo $session->user_extra['user_location']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_job'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="occupation" value="<?php echo $session->user_extra['user_job']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><?php echo $lang->get('usercp_publicinfo_field_hobbies'); ?></td>
            <td class="row1" style="width: 50%;"><input type="text" name="hobbies" value="<?php echo $session->user_extra['user_hobbies']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><label for="chk_email_public"><?php echo $lang->get('usercp_publicinfo_field_email_public'); ?></label><br /><small><?php echo $lang->get('usercp_publicinfo_field_email_public_hint'); ?></small></td>
            <td class="row1" style="width: 50%;"><input type="checkbox" id="chk_email_public" name="email_public" <?php if ($session->user_extra['email_public'] == 1) echo 'checked="checked"'; ?> size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><label for="chk_jsfx"><?php echo $lang->get('usercp_publicinfo_field_jsfx'); ?></label><br /><small><?php echo $lang->get('usercp_publicinfo_field_jsfx_hint'); ?></small></td>
            <td class="row1" style="width: 50%;"><input type="checkbox" id="chk_jsfx" name="disable_js_fx" <?php if ($session->user_extra['disable_js_fx'] == 1) echo 'checked="checked"'; ?> size="30" /></td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              <input type="submit" name="submit" value="<?php echo $lang->get('usercp_publicinfo_btn_save'); ?>" />
            </th>
          </tr>
        </table>
      </div>
      <?php
      echo '</form>';
      break;
    case 'Avatar':
      if ( getConfig('avatar_enable') != '1' )
      {
        echo '<div class="error-box"><b>' . $lang->get('usercp_avatar_err_disabled_title') . '</b><br />' . $lang->get('usercp_avatar_err_disabled_body') . '</div>';
      }
      
      // Determine current avatar
      $q = $db->sql_query('SELECT user_has_avatar, avatar_type FROM ' . table_prefix . 'users WHERE user_id = ' . $session->user_id . ';');
      if ( !$q )
        $db->_die('Avatar CP selecting user\'s avatar data');
      
      list($has_avi, $avi_type) = $db->fetchrow_num();
      
      if ( isset($_POST['submit']) )
      {
        $action = ( isset($_POST['avatar_action']) ) ? $_POST['avatar_action'] : 'keep';
        $avi_path = ENANO_ROOT . '/' . getConfig('avatar_directory') . '/' . $session->user_id . '.' . $avi_type;
        switch($action)
        {
          case 'keep':
          default:
            break;
          case 'remove':
            if ( $has_avi )
            {
              // First switch the avatar off
              $q = $db->sql_query('UPDATE ' . table_prefix . 'users SET user_has_avatar = 0 WHERE user_id = ' . $session->user_id . ';');
              if ( !$q )
                $db->_die('Avatar CP switching user avatar off');
              
              if ( @unlink($avi_path) )
              {
                echo '<div class="info-box">' . $lang->get('usercp_avatar_delete_success') . '</div>';
              }
              $has_avi = 0;
            }
            break;
          case 'set_http':
          case 'set_file':
            // Hackish way to preserve the UNIX philosophy of reusing as much code as possible
            if ( $action == 'set_http' )
            {
              // Check if this action is enabled
              if ( getConfig('avatar_upload_http') !== '1' )
              {
                // non-localized, only appears on hack attempt
                echo '<div class="error-box">Uploads over HTTP are disabled.</div>';
                break;
              }
              // Download the file
              require_once( ENANO_ROOT . '/includes/http.php' );
              
              if ( !preg_match('/^http:\/\/([a-z0-9-\.]+)(:([0-9]+))?\/(.+)$/', $_POST['avatar_http_url'], $match) )
              {
                echo '<div class="error-box">' . $lang->get('usercp_avatar_invalid_url') . '</div>';
                break;
              }
              
              $hostname = $match[1];
              $uri = '/' . $match[4];
              $port = ( $match[3] ) ? intval($match[3]) : 80;
              $max_size = intval(getConfig('avatar_max_size'));
              
              // Get temporary file
              $tempfile = tempnam(false, "enanoavatar_{$session->user_id}");
              if ( !$tempfile )
                echo '<div class="error-box">Error getting temp file.</div>';
              
              @unlink($tempfile);
              $request = new Request_HTTP($hostname, $uri, 'GET', $port);
              $result = $request->write_response_to_file($tempfile, 50, $max_size);
              if ( !$result || $request->response_code != HTTP_OK )
              {
                @unlink($tempfile);
                echo '<div class="error-box">' . $lang->get('usercp_avatar_bad_write') . '</div>';
                break;
              }
              
              // Response written. Proceed to validation...
            }
            else
            {
              // Check if this action is enabled
              if ( getConfig('avatar_upload_file') !== '1' )
              {
                // non-localized, only appears on hack attempt
                echo '<div class="error-box">Uploads from the browser are disabled.</div>';
                break;
              }
              
              $max_size = intval(getConfig('avatar_max_size'));
              
              $file =& $_FILES['avatar_file'];
              $tempfile =& $file['tmp_name'];
              if ( filesize($tempfile) > $max_size )
              {
                @unlink($tempfile);
                echo '<div class="error-box">' . $lang->get('usercp_avatar_file_too_large') . '</div>';
                break;
              }
            }
            $file_type = get_image_filetype($tempfile);
            if ( !$file_type )
            {
              unlink($tempfile);
              echo '<div class="error-box">' . $lang->get('usercp_avatar_bad_filetype') . '</div>';
              break;
            }
            
            $avi_path_new = ENANO_ROOT . '/' . getConfig('avatar_directory') . '/' . $session->user_id . '.' . $file_type;
            
            // The file type is good - validate dimensions and animation
            switch($file_type)
            {
              case 'png':
                $is_animated = is_png_animated($tempfile);
                $dimensions = png_get_dimensions($tempfile);
                break;
              case 'gif':
                $is_animated = is_gif_animated($tempfile);
                $dimensions = gif_get_dimensions($tempfile);
                break;
              case 'jpg':
                $is_animated = false;
                $dimensions = jpg_get_dimensions($tempfile);
                break;
              default:
                echo '<div class="error-box">API mismatch</div>';
                break 2;
            }
            // Did we get invalid size data? If so the image is probably corrupt.
            if ( !$dimensions )
            {
              @unlink($tempfile);
              echo '<div class="error-box">' . $lang->get('usercp_avatar_corrupt_image') . '</div>';
              break;
            }
            // Is the image animated?
            if ( $is_animated && getConfig('avatar_enable_anim') !== '1' )
            {
              @unlink($tempfile);
              echo '<div class="error-box">' . $lang->get('usercp_avatar_disallowed_animation') . '</div>';
              break;
            }
            // Check image dimensions
            list($image_x, $image_y) = $dimensions;
            $max_x = intval(getConfig('avatar_max_width'));
            $max_y = intval(getConfig('avatar_max_height'));
            if ( $image_x > $max_x || $image_y > $max_y )
            {
              @unlink($tempfile);
              echo '<div class="error-box">' . $lang->get('usercp_avatar_too_large') . '</div>';
              break;
            }
            // All good!
            @unlink($avi_path);
            if ( rename($tempfile, $avi_path_new) )
            {
              $q = $db->sql_query('UPDATE ' . table_prefix . "users SET user_has_avatar = 1, avatar_type = '$file_type' WHERE user_id = {$session->user_id};");
              if ( !$q )
                $db->_die('Avatar CP updating users table after successful avatar upload');
              $has_avi = 1;
              $avi_type = $file_type;
              echo '<div class="info-box">' . $lang->get('usercp_avatar_upload_success') . '</div>';
            }
            else
            {
              echo '<div class="error-box">' . $lang->get('usercp_avatar_move_failed') . '</div>';
            }
            break;
        }
      }
      
      ?>
      <script type="text/javascript">
      
        function avatar_select_field(elParent)
        {
          switch(elParent.value)
          {
            case 'keep':
            case 'remove':
              $('avatar_upload_http').object.style.display = 'none';
              $('avatar_upload_file').object.style.display = 'none';
              break;
            case 'set_http':
              $('avatar_upload_http').object.style.display = 'block';
              $('avatar_upload_file').object.style.display = 'none';
              break;
            case 'set_file':
              $('avatar_upload_http').object.style.display = 'none';
              $('avatar_upload_file').object.style.display = 'block';
              break;
          }
        }
      
      </script>
      <?php
      
      echo '<form action="' . makeUrl($paths->fullpage) . '" method="post" enctype="multipart/form-data">';
      echo '<div class="tblholder">';
      echo '<table border="0" cellspacing="1" cellpadding="4">';
      echo '<tr>
              <th colspan="2">
                ' . $lang->get('usercp_avatar_table_title') . '
              </th>
            </tr>';
            
      echo '<tr>
              <td class="row2" style="width: 50%;">
                ' . $lang->get('usercp_avatar_label_current') . '
              </td>
              <td class="row1" style="text-align: center;">';
              
      if ( $has_avi == 1 )
      {
        echo '<img alt="' . $lang->get('usercp_avatar_image_alt', array('username' => $session->username)) . '" src="' . make_avatar_url($session->user_id, $avi_type) . '" />';
      }
      else
      {
        echo $lang->get('usercp_avatar_image_none');
      }
      
      echo '    </td>
              </tr>';
              
      echo '  <tr>
                <td class="row2">
                  ' . $lang->get('usercp_avatar_lbl_change') . '
                </td>
                <td class="row1">
                  <label><input type="radio" name="avatar_action" value="keep" onclick="avatar_select_field(this);" checked="checked" /> ' . $lang->get('usercp_avatar_lbl_keep') . '</label><br />
                  <label><input type="radio" name="avatar_action" value="remove" onclick="avatar_select_field(this);" /> ' . $lang->get('usercp_avatar_lbl_remove') . '</label><br />';
      if ( getConfig('avatar_upload_http') == '1' )
      {
        echo '    <label><input type="radio" name="avatar_action" value="set_http" onclick="avatar_select_field(this);" /> ' . $lang->get('usercp_avatar_lbl_set_http') . '</label><br />
                  <div id="avatar_upload_http" style="display: none; margin: 10px 0 0 2.2em;">
                    ' . $lang->get('usercp_avatar_lbl_url') . ' <input type="text" name="avatar_http_url" size="40" value="http://" /><br />
                    <small>' . $lang->get('usercp_avatar_lbl_url_desc') . ' ' . $lang->get('usercp_avatar_limits') . '</small>
                  </div>';
      }
      else
      {
        echo '    <div id="avatar_upload_http" style="display: none;"></div>';
      }
      if ( getConfig('avatar_upload_file') == '1' )
      {
        echo '    <label><input type="radio" name="avatar_action" value="set_file" onclick="avatar_select_field(this);" /> ' . $lang->get('usercp_avatar_lbl_set_file') . '</label>
                  <div id="avatar_upload_file" style="display: none; margin: 10px 0 0 2.2em;">
                    ' . $lang->get('usercp_avatar_lbl_file') . ' <input type="file" name="avatar_file" size="40" /><br />
                    <small>' . $lang->get('usercp_avatar_lbl_file_desc') . ' ' . $lang->get('usercp_avatar_limits') . '</small>
                  </div>';
      }
      else
      {
        echo '    <div id="avatar_upload_file" style="display: none;"></div>';
      }
      echo '    </td>
              </tr>';
              
      echo '  <tr>
                <th class="subhead" colspan="2">
                  <input type="submit" name="submit" value="' . $lang->get('etc_save_changes') . '" />
                </th>
              </tr>';
              
      echo '</table>
            </div>';
      
      break;
    default:
      $good = false;
      $code = $plugins->setHook('userprefs_body');
      foreach ( $code as $cmd )
      {
        if ( eval($cmd) )
          $good = true;
      }
      if ( !$good )
      {
        echo '<h3>Invalid module</h3>
              <p>Userprefs module "'.$section.'" not found.</p>';
      }
      break;
  }
  
  $template->footer();
}

?>
