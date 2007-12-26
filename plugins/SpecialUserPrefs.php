<?php
/*
Plugin Name: User control panel
Plugin URI: http://enanocms.org/
Description: Provides the page Special:Preferences.
Author: Dan Fuhry
Version: 1.0.3
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3
 * Copyright (C) 2006-2007 Dan Fuhry
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
  
  if ( $paths->namespace != 'Special' || $paths->page_id != 'Preferences' )
    return false;
  
  $tb .= "<ul>$template->toolbar_menu</ul>";
  $template->toolbar_menu = '';
  
  $button->assign_vars(array(
      'TEXT' => 'list of registered members',
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
  
  $html = '';
  $quot = '"';
  
  foreach ( $userprefs_menu as $section => $buttons )
  {
    $html .= ( isset($userprefs_menu_links[$section]) ) ? "<a href={$quot}{$userprefs_menu_links[$section]}{$quot}>{$section}</a>\n        " : "<a>{$section}</a>\n        ";
    $html .= "<ul>\n          ";
    foreach ( $buttons as $button )
    {
      $html .= "  <li><a href={$quot}{$button['link']}{$quot}>{$button['text']}</a></li>\n          ";
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
  
  userprefs_menu_add('Profile/membership', 'Edit e-mail address and password', makeUrlNS('Special', 'Preferences/EmailPassword') . '" onclick="ajaxLoginNavTo(\'Special\', \'Preferences/EmailPassword\', '.USER_LEVEL_CHPREF.'); return false;');
  userprefs_menu_add('Profile/membership', 'Edit signature', makeUrlNS('Special', 'Preferences/Signature'));
  userprefs_menu_add('Profile/membership', 'Edit public profile', makeUrlNS('Special', 'Preferences/Profile'));
  userprefs_menu_add('Profile/membership', 'Group memberships', makeUrlNS('Special', 'Usergroups'));
  if ( getConfig('avatar_enable') == '1' )
  {
    userprefs_menu_add('Profile/membership', 'Avatar settings', makeUrlNS('Special', 'Preferences/Avatar'));
  }
  userprefs_menu_add('Private messages', 'Inbox', makeUrlNS('Special', 'PrivateMessages/Folder/Inbox'));
  userprefs_menu_add('Private messages', 'Outbox', makeUrlNS('Special', 'PrivateMessages/Folder/Outbox'));
  userprefs_menu_add('Private messages', 'Sent items', makeUrlNS('Special', 'PrivateMessages/Folder/Sent'));
  userprefs_menu_add('Private messages', 'Drafts', makeUrlNS('Special', 'PrivateMessages/Folder/Drafts'));
  userprefs_menu_add('Private messages', 'Archive', makeUrlNS('Special', 'PrivateMessages/Folder/Archive'));
  /*
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
                $errors .= '<div class="error-box">The e-mail addresses you entered did not match.</div>';
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
            $message = '<p>The following errors were encountered while saving your e-mail address:</p>';
            $message .= '<ul><li>' . implode("</li>\n<li>", $result) . '</li></ul>';
            die_friendly('Error updating e-mail address', $message);
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
              $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">You can\'t change your password in demo mode.</div>';
            // Perform checks
            if ( strlen($newpass) < 6 )
              $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">Password must be at least 6 characters. You hacked my script, darn you!</div>';
            if ( getConfig('pw_strength_enable') == '1' )
            {
              $score_inp = password_score($newpass);
              $score_min = intval( getConfig('pw_strength_minimum') );
              if ( $score_inp < $score_min )
                $errors .= '<div class="error-box" style="margin: 0 0 10px 0;">Your password did not meet the complexity score requirement for this site. Your password scored '. $score_inp .', while a score of at least '. $score_min .' is needed.</div>';
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
                  redirect(makeUrl(getConfig('main_page')), 'Profile changed', 'Your password and e-mail address have been changed. Since e-mail activation is required on this site, you will need to re-activate your account to continue. An e-mail has been sent to the new e-mail address with an activation link. You must click that link in order to log in again.', 19);
                }
                else if ( getConfig('account_activation') == 'admin' )
                {
                  redirect(makeUrl(getConfig('main_page')), 'Profile changed', 'Your password and e-mail address have been changed. Since administrative activation is requires on this site, a request has been sent to the administrators to activate your account for you. You will not be able to use your account until it is activated by an administrator.', 19);
                }
              }
              $session->login_without_crypto($session->username, $newpass);
              redirect(makeUrlNS('Special', 'Preferences'), 'Password changed', 'Your password has been changed, and you will now be redirected back to the user control panel.', 4);
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
                $errors .= '<div class="error-box">The passwords you entered did not match</div>';
                break;
              }
              
              if ( $email_changed )
              {
                if ( getConfig('account_activation') == 'user' )
                {
                  redirect(makeUrl(getConfig('main_page')), 'Profile changed', 'Your e-mail address has been changed. Since e-mail activation is required on this site, you will need to re-activate your account to continue. An e-mail has been sent to the new e-mail address with an activation link. You must click that link in order to log in again.', 19);
                }
                else if ( getConfig('account_activation') == 'admin' )
                {
                  redirect(makeUrl(getConfig('main_page')), 'Profile changed', 'Your e-mail address has been changed. Since administrative activation is requires on this site, a request has been sent to the administrators to activate your account for you. You will not be able to use your account until it is activated by an administrator.', 19);
                }
                else
                {
                  redirect(makeUrlNS('Special', 'Preferences'), 'Password changed', 'Your e-mail address has been changed, and you will now be redirected back to the user control panel.', 4);
                }
              }
              
              return;
          }
        }
      }
      $template->tpl_strings['PAGE_NAME'] = 'Change E-mail Address or Password';
      break;
    case 'Signature':
      $template->tpl_strings['PAGE_NAME'] = 'Editing signature';
      break;
    case 'Profile':
      $template->tpl_strings['PAGE_NAME'] = 'Editing public profile';
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
      $user_page = '<a href="' . makeUrlNS('User', sanitize_page_id($session->username)) . '"' . $userpage_exists . '>user page</a> <sup>(<a href="' . makeUrlNS('User', str_replace(' ', '_', $session->username)) . '#do:comments">comments</a>)</sup>';
      $site_admin = $email->encryptEmail(getConfig('contact_email'), '', '', 'administrator');
      $make_one_now = '<a href="' . makeUrlNS('User', sanitize_page_id($session->username)) . '">make one now</a>';
      echo "<h3 style='margin-top: 0;'>$session->username, welcome to your control panel</h3>";
      echo "<p>Here you can make changes to your profile, view statistics on yourself on this site, and set your preferences.</p>
            <p>Your $user_page is your free writing space. You can use it to tell the other members of this site a little bit about yourself. If you haven't already made a user page, why not $make_one_now?</p>
            <p>Use the menu at the top to navigate around. If you have any questions, you may contact the $site_admin.";
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
        <legend>Change password</legend>
        Type a new password:<br />
          <input type="password" name="newpass" size="30" tabindex="1" ' . ( getConfig('pw_strength_enable') == '1' ? 'onkeyup="password_score_field(this);" ' : '' ) . '/>' . ( getConfig('pw_strength_enable') == '1' ? '<span class="password-checker" style="font-weight: bold; color: #aaaaaa;"> Loading...</span>' : '' ) . '
        <br />
        <br />
        Type the password again to confirm:<br />
        <input type="password" name="newpass_conf" size="30" tabindex="2" />
        ' . ( getConfig('pw_strength_enable') == '1' ? '<br /><br /><div id="pwmeter"></div>
        <small>Your password needs to score at least <b>'.getConfig('pw_strength_minimum').'</b> in order to be accepted.</small>' : '' ) . '
      </fieldset><br />
      <fieldset>
        <legend>Change e-mail address</legend>
        New e-mail address:<br />
          <input type="text" value="' . ( isset($_POST['newemail']) ? htmlspecialchars($_POST['newemail']) : '' ) . '" name="newemail" size="30" tabindex="3" />
        <br />
        <br />
        Confirm e-mail address:<br />
          <input type="text" value="' . ( isset($_POST['newemail']) ? htmlspecialchars($_POST['newemail']) : '' ) . '" name="newemail_conf" size="30" tabindex="4" />
      </fieldset>
      <input type="hidden" name="use_crypt" value="no" />
      <input type="hidden" name="crypt_key" value="' . $pubkey . '" />
      <input type="hidden" name="crypt_data" value="" />
      <br />
      <div style="text-align: right;"><input type="submit" name="submit" value="Save Changes" tabindex="5" /></div>';
      
      echo '</form>';
      
      // ENCRYPTION CODE
      ?>
      <script type="text/javascript">
      <?php if ( getConfig('pw_strength_enable') == '1' ): ?>
      password_score_field(document.forms.empwform.newpass);
      <?php endif; ?>
        disableJSONExts();
        str = '';
        for(i=0;i<keySizeInBits/4;i++) str+='0';
        var key = hexToByteArray(str);
        var pt = hexToByteArray(str);
        var ct = rijndaelEncrypt(pt, key, "ECB");
        var ct = byteArrayToHex(ct);
        switch(keySizeInBits)
        {
          case 128:
            v = '66e94bd4ef8a2c3b884cfa59ca342b2e';
            break;
          case 192:
            v = 'aae06992acbf52a3e8f4a96ec9300bd7aae06992acbf52a3e8f4a96ec9300bd7';
            break;
          case 256:
            v = 'dc95c078a2408989ad48a21492842087dc95c078a2408989ad48a21492842087';
            break;
        }
        var aes_testpassed = ( ct == v && md5_vm_test() );
        function runEncryption()
        {
          var frm = document.forms.empwform;
          if ( frm.newpass.value.length < 1 )
            return true;
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
          }
          pass1 = frm.newpass.value;
          pass2 = frm.newpass_conf.value;
          if ( pass1 != pass2 )
          {
            alert('The passwords you entered do not match.');
            return false;
          }
          if ( pass1.length < 6 && pass1.length > 0 )
          {
            alert('The new password must be 6 characters or greater in length.');
            return false;
          }
          if(aes_testpassed)
          {
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
        echo '<div class="info-box" style="margin: 0 0 10px 0;">Your signature has been saved.</div>';
      }
      echo '<form action="'.makeUrl($paths->fullpage).'" method="post">';
      echo $template->tinymce_textarea('new_sig', htmlspecialchars($session->signature));
      echo '<input type="submit" value="Save signature" />';
      echo '</form>';
      break;
    case "Profile":
      if ( isset($_POST['submit']) )
      {
        $real_name = htmlspecialchars($_POST['real_name']);
        $real_name = $db->escape($real_name);
        
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
        
        $q = $db->sql_query('UPDATE '.table_prefix."users SET real_name='$real_name' WHERE user_id=$session->user_id;");
        if ( !$q )
          $db->_die();
        
        $q = $db->sql_query('UPDATE '.table_prefix."users_extra SET user_aim='$imaddr_aim',user_yahoo='$imaddr_yahoo',user_msn='$imaddr_msn',
                               user_xmpp='$imaddr_xmpp',user_homepage='$homepage',user_location='$location',user_job='$occupation',
                               user_hobbies='$hobbies',email_public=$email_public
                               WHERE user_id=$session->user_id;");
        
        if ( !$q )
          $db->_die();
        
        echo '<div class="info-box" style="margin: 0 0 10px 0;">Your profile has been updated.</div>';
      }
      echo '<form action="'.makeUrl($paths->fullpage).'" method="post">';
      ?>
      <div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4">
          <tr>
            <th colspan="2">Your public profile</th>
          </tr>
          <tr>
            <td colspan="2" class="row3">Please note that all of the information you enter here will be <b>publicly viewable.</b> All of the fields on this page are optional and may be left blank if you so desire.</td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Real name:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="real_name" value="<?php echo $session->real_name; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2">Change theme:</td>
            <td class="row1">If you don't like the look of the site, need a visual break, or are just curious, we might have some different themes for you to try out! <a href="<?php echo makeUrlNS('Special', 'ChangeStyle/' . $paths->page); ?>" onclick="ajaxChangeStyle(); return false;">Change my theme...</a></td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              Instant messenger contact information
            </th>
          <tr>
            <td class="row2" style="width: 50%;">AIM handle:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_aim" value="<?php echo $session->user_extra['user_aim']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><acronym title="Windows&trade; Live Messenger">WLM</acronym> handle:<br /><small>If you don't specify the domain (@whatever.com), "@hotmail.com" will be assumed.</small></td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_msn" value="<?php echo $session->user_extra['user_msn']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Yahoo! IM handle:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_yahoo" value="<?php echo $session->user_extra['user_yahoo']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Jabber/XMPP handle:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="imaddr_xmpp" value="<?php echo $session->user_extra['user_xmpp']; ?>" size="30" /></td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              Extra contact information
            </th>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Your homepage:<br /><small>Please remember the http:// prefix.</small></td>
            <td class="row1" style="width: 50%;"><input type="text" name="homepage" value="<?php echo $session->user_extra['user_homepage']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Your location:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="location" value="<?php echo $session->user_extra['user_location']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Your job:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="occupation" value="<?php echo $session->user_extra['user_job']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;">Your hobbies:</td>
            <td class="row1" style="width: 50%;"><input type="text" name="hobbies" value="<?php echo $session->user_extra['user_hobbies']; ?>" size="30" /></td>
          </tr>
          <tr>
            <td class="row2" style="width: 50%;"><label for="chk_email_public">E-mail address is public</label><br /><small>If this is checked, your e-mail address will be displayed on your user page. To protect your address from spambots, your e-mail address will be encrypted.</small></td>
            <td class="row1" style="width: 50%;"><input type="checkbox" id="chk_email_public" name="email_public" <?php if ($session->user_extra['email_public'] == 1) echo 'checked="checked"'; ?> size="30" /></td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              <input type="submit" name="submit" value="Save profile" />
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
