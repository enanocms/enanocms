<?php
/*
Plugin Name: Special user/login-related pages
Plugin URI: http://enanocms.org/
Description: Provides the pages Special:Login, Special:Logout, Special:Register, and Special:Preferences.
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
      \'name\'=>\'Log in\',
      \'urlname\'=>\'Login\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    $paths->add_page(Array(
      \'name\'=>\'Log out\',
      \'urlname\'=>\'Logout\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    $paths->add_page(Array(
      \'name\'=>\'Register\',
      \'urlname\'=>\'Register\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    $paths->add_page(Array(
      \'name\'=>\'Edit Profile\',
      \'urlname\'=>\'Preferences\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Contributions\',
      \'urlname\'=>\'Contributions\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Change style\',
      \'urlname\'=>\'ChangeStyle\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Activate user account\',
      \'urlname\'=>\'ActivateAccount\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Captcha\',
      \'urlname\'=>\'Captcha\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Forgot password\',
      \'urlname\'=>\'PasswordReset\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Member list\',
      \'urlname\'=>\'Memberlist\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

$__login_status = '';

function page_Special_Login()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $__login_status;
  global $lang;
  
  $pubkey = $session->rijndael_genkey();
  $challenge = $session->dss_rand();
  
  $locked_out = false;
  // are we locked out?
  $threshold = ( $_ = getConfig('lockout_threshold') ) ? intval($_) : 5;
  $duration  = ( $_ = getConfig('lockout_duration') ) ? intval($_) : 15;
  // convert to minutes
  $duration  = $duration * 60;
  $policy = ( $x = getConfig('lockout_policy') && in_array(getConfig('lockout_policy'), array('lockout', 'disable', 'captcha')) ) ? getConfig('lockout_policy') : 'lockout';
  if ( $policy != 'disable' )
  {
    $ipaddr = $db->escape($_SERVER['REMOTE_ADDR']);
    $timestamp_cutoff = time() - $duration;
    $q = $session->sql('SELECT timestamp FROM '.table_prefix.'lockout WHERE timestamp > ' . $timestamp_cutoff . ' AND ipaddr = \'' . $ipaddr . '\' ORDER BY timestamp DESC;');
    $fails = $db->numrows();
    if ( $fails >= $threshold )
    {
      $row = $db->fetchrow();
      $locked_out = true;
      $lockdata = array(
          'locked_out' => true,
          'lockout_threshold' => $threshold,
          'lockout_duration' => ( $duration / 60 ),
          'lockout_fails' => $fails,
          'lockout_policy' => $policy,
          'lockout_last_time' => $row['timestamp'],
          'time_rem' => ( $duration / 60 ) - round( ( time() - $row['timestamp'] ) / 60 ),
          'captcha' => ''
        );
      if ( $policy == 'captcha' )
      {
        $lockdata['captcha'] = $session->make_captcha();
      }
    }
    $db->free_result();
  }
  
  if ( isset($_GET['act']) && $_GET['act'] == 'getkey' )
  {
    $username = ( $session->user_logged_in ) ? $session->username : false;
    $response = Array(
      'username' => $username,
      'key' => $pubkey,
      'challenge' => $challenge,
      'locked_out' => false
      );
    
    if ( $locked_out )
    {
      foreach ( $lockdata as $x => $y )
      {
        $response[$x] = $y;
      }
      unset($x, $y);
    }
    
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $response = $json->encode($response);
    echo $response;
    return null;
  }
  
  $level = ( isset($_GET['level']) && in_array($_GET['level'], array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9') ) ) ? intval($_GET['level']) : USER_LEVEL_MEMBER;
  if ( isset($_POST['login']) )
  {
    if ( in_array($_POST['auth_level'], array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9') ) )
    {
      $level = intval($_POST['auth_level']);
    }
  }
  
  if ( $level > USER_LEVEL_MEMBER && !$session->user_logged_in )
  {
    $level = USER_LEVEL_MEMBER;
  }
  if ( $level <= USER_LEVEL_MEMBER && $session->user_logged_in )
    $paths->main_page();
  $template->header();
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Login').'" method="post" name="loginform" onsubmit="runEncryption();">';
  $header = ( $level > USER_LEVEL_MEMBER ) ? $lang->get('user_login_message_short_elev') : $lang->get('user_login_message_short');
  if ( isset($_POST['login']) )
  {
    $errstring = $__login_status['error'];
    switch($__login_status['error'])
    {
      case 'key_not_found':
        $errstring = $lang->get('user_err_key_not_found');
        break;
      case 'key_wrong_length':
        $errstring = $lang->get('user_err_key_wrong_length');
        break;
      case 'too_big_for_britches':
        $errstring = $lang->get('user_err_too_big_for_britches');
        break;
      case 'invalid_credentials':
        $errstring = $lang->get('user_err_invalid_credentials');
        if ( $__login_status['lockout_policy'] == 'lockout' )
        {
          $errstring .= $lang->get('err_invalid_credentials_lockout', array('lockout_fails' => $__login_status['lockout_fails']));
        }
        else if ( $__login_status['lockout_policy'] == 'captcha' )
        {
          $errstring .= $lang->get('user_err_invalid_credentials_lockout_captcha', array('lockout_fails' => $__login_status['lockout_fails']));
        }
        break;
      case 'backend_fail':
        $errstring = $lang->get('user_err_backend_fail');
        break;
      case 'locked_out':
        $attempts = intval($__login_status['lockout_fails']);
        if ( $attempts > $__login_status['lockout_threshold'])
          $attempts = $__login_status['lockout_threshold'];
        
        $server_time = time();
        $time_rem = ( $__login_status['lockout_last_time'] == time() ) ? $__login_status['lockout_duration'] : $__login_status['lockout_duration'] - round( ( $server_time - $__login_status['lockout_last_time'] ) / 60 );
        if ( $time_rem < 1 )
          $time_rem = $__login_status['lockout_duration'];
        
        $s = ( $time_rem == 1 ) ? '' : $lang->get('meta_plural');
        
        $captcha_string = ( $__login_status['lockout_policy'] == 'captcha' ) ? $lang->get('err_locked_out_captcha_blurb') : '';
        $errstring = $lang->get('user_err_locked_out', array('plural' => $s, 'captcha_blurb' => $captcha_string, 'time_rem' => $time_rem));
        
        break;
    }
    echo '<div class="error-box-mini">'.$errstring.'</div>';
  }
  if ( $p = $paths->getAllParams() )
  {
    echo '<input type="hidden" name="return_to" value="'.$p.'" />';
  }
  else if ( isset($_POST['login']) && isset($_POST['return_to']) )
  {
    echo '<input type="hidden" name="return_to" value="'.htmlspecialchars($_POST['return_to']).'" />';
  }
  ?>
    <div class="tblholder">
      <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
        <tr>
          <th colspan="3"><?php echo $header; ?></th>
        </tr>
        <tr>
          <td colspan="3" class="row1">
            <?php
            if ( $level <= USER_LEVEL_MEMBER )
            {
              echo '<p>' . $lang->get('user_login_body', array('reg_link' => makeUrlNS('Special', 'Register'))) . '</p>';
            }
            else
            {
              echo '<p>' . $lang->get('user_login_body_elev') . '</p>';
            }
            ?>
          </td>
        </tr>
        <tr>
          <td class="row2">
            <?php echo $lang->get('user_login_field_username'); ?>:
          </td>
          <td class="row1">
            <input name="username" size="25" type="text" <?php
              if ( $level <= USER_LEVEL_MEMBER )
              {
                echo 'tabindex="1" ';
              }
              else
              {
                echo 'tabindex="3" ';
              }
              if ( $session->user_logged_in )
              {
                echo 'value="' . $session->username . '"';
              }
              ?> />
          </td>
          <?php if ( $level <= USER_LEVEL_MEMBER ) { ?>
          <td rowspan="<?php echo ( ( $locked_out && $lockdata['lockout_policy'] == 'captcha' ) ) ? '4' : '2'; ?>" class="row3">
            <small><?php echo $lang->get('user_login_forgotpass_blurb', array('forgotpass_link' => makeUrlNS('Special', 'PasswordReset'))); ?><br />
            <?php echo $lang->get('user_login_createaccount_blurb', array('reg_link' => makeUrlNS('Special', 'Register'))); ?></small>
          </td>
          <?php } ?>
        </tr>
        <tr>
          <td class="row2">
            <?php echo $lang->get('user_login_field_password'); ?>:
          </td><td class="row1"><input name="pass" size="25" type="password" tabindex="<?php echo ( $level <= USER_LEVEL_MEMBER ) ? '2' : '1'; ?>" /></td>
         </tr>
         <?php
         if ( $locked_out && $lockdata['lockout_policy'] == 'captcha' )
         {
           ?>
           <tr>
             <td class="row2" rowspan="2"><?php echo $lang->get('user_login_field_captcha'); ?>:<br /></td><td class="row1"><input type="hidden" name="captcha_hash" value="<?php echo $lockdata['captcha']; ?>" /><input name="captcha_code" size="25" type="text" tabindex="<?php echo ( $level <= USER_LEVEL_MEMBER ) ? '3' : '4'; ?>" /></td>
           </tr>
           <tr>
             <td class="row3">
               <img src="<?php echo makeUrlNS('Special', 'Captcha/' . $lockdata['captcha']) ?>" onclick="this.src=this.src+'/a';" style="cursor: pointer;" />
             </td>
           </tr>
           <?php
         }
         ?>
         <?php if ( $level <= USER_LEVEL_MEMBER ) { ?>
         <tr>
           <td class="row3" colspan="3">
             <?php
             $returnpage_link = ( $return = $paths->getAllParams() ) ? '/' . $return : '';
             $nocrypt_link = makeUrlNS('Special', "Login$returnpage_link", "level=$level&use_crypt=0", true);
             echo '<p><b>' . $lang->get('user_login_nocrypt_title') . ':</b> ' . $lang->get('user_login_nocrypt_body', array('nocrypt_link' => $nocrypt_link)) . '</p>';
             echo '<p>' . $lang->get('user_login_nocrypt_countrylist') . '</p>';
             ?>
           </td>
         </tr>
         <?php } ?>
         <tr>
           <th colspan="3" style="text-align: center" class="subhead"><input type="submit" name="login" value="Log in" tabindex="<?php echo ( $level <= USER_LEVEL_MEMBER ) ? '3' : '2'; ?>" /></th>
         </tr>
      </table>
    </div>
      <input type="hidden" name="challenge_data" value="<?php echo $challenge; ?>" />
      <input type="hidden" name="use_crypt" value="no" />
      <input type="hidden" name="crypt_key" value="<?php echo $pubkey; ?>" />
      <input type="hidden" name="crypt_data" value="" />
      <input type="hidden" name="auth_level" value="<?php echo (string)$level; ?>" />
      <?php if ( $level <= USER_LEVEL_MEMBER ): ?>
      <script type="text/javascript">
        document.forms.loginform.username.focus();
      </script>
      <?php else: ?>
      <script type="text/javascript">
        document.forms.loginform.pass.focus();
      </script>
      <?php endif; ?>
    </form>
    <?php
      echo $session->aes_javascript('loginform', 'pass', 'use_crypt', 'crypt_key', 'crypt_data', 'challenge_data');
    ?>
  <?php
  $template->footer();
}

function page_Special_Login_preloader() // adding _preloader to the end of the function name calls the function before $session and $paths setup routines are called
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $__login_status;
  if ( isset($_GET['act']) && $_GET['act'] == 'ajaxlogin' )
  {
    $plugins->attachHook('login_password_reset', 'SpecialLogin_SendResponse_PasswordReset($row[\'user_id\'], $row[\'temp_password\']);');
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $data = $json->decode($_POST['params']);
    $captcha_hash = ( isset($data['captcha_hash']) ) ? $data['captcha_hash'] : false;
    $captcha_code = ( isset($data['captcha_code']) ) ? $data['captcha_code'] : false;
    $level = ( isset($data['level']) ) ? intval($data['level']) : USER_LEVEL_MEMBER;
    $result = $session->login_with_crypto($data['username'], $data['crypt_data'], $data['crypt_key'], $data['challenge'], $level, $captcha_hash, $captcha_code);
    $session->start();
    if ( $result['success'] )
    {
      $response = Array(
          'result' => 'success',
          'key' => $session->sid_super // ( ( $session->sid_super ) ? $session->sid_super : $session->sid )
        );
    }
    else
    {
      $captcha = '';
      if ( $result['error'] == 'locked_out' && $result['lockout_policy'] == 'captcha' )
      {
        $session->kill_captcha();
        $captcha = $session->make_captcha();
      }
      $response = Array(
          'result' => 'error',
          'data' => $result,
          'captcha' => $captcha
        );
    }
    $response = $json->encode($response);
    echo $response;
    $db->close();
    exit;
  }
  if(isset($_POST['login'])) {
    $captcha_hash = ( isset($_POST['captcha_hash']) ) ? $_POST['captcha_hash'] : false;
    $captcha_code = ( isset($_POST['captcha_code']) ) ? $_POST['captcha_code'] : false;
    if($_POST['use_crypt'] == 'yes')
    {
      $result = $session->login_with_crypto($_POST['username'], $_POST['crypt_data'], $_POST['crypt_key'], $_POST['challenge_data'], intval($_POST['auth_level']), $captcha_hash, $captcha_code);
    }
    else
    {
      $result = $session->login_without_crypto($_POST['username'], $_POST['pass'], false, intval($_POST['auth_level']), $captcha_hash, $captcha_code);
    }
    $session->start();
    $paths->init();
    if($result['success'])
    {
      $template->load_theme($session->theme, $session->style);
      if(isset($_POST['return_to']))
      {
        $name = ( isset($paths->pages[$_POST['return_to']]['name']) ) ? $paths->pages[$_POST['return_to']]['name'] : $_POST['return_to'];
        redirect( makeUrl($_POST['return_to'], false, true), 'Login successful', 'You have successfully logged into the '.getConfig('site_name').' site as "'.$session->username.'". Redirecting to ' . $name . '...' );
      }
      else
      {
        redirect( makeUrl(getConfig('main_page'), false, true), 'Login successful', 'You have successfully logged into the '.getConfig('site_name').' site as "'.$session->username.'". Redirecting to the main page...' );
      }
    }
    else
    {
      $GLOBALS['__login_status'] = $result;
    }
  }
}

function SpecialLogin_SendResponse_PasswordReset($user_id, $passkey)
{
  $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
  
  $response = Array(
      'result' => 'success_reset',
      'user_id' => $user_id,
      'temppass' => $passkey
    );
  
  $response = $json->encode($response);
  echo $response;
  
  $db->close();
  
  exit;
}

function page_Special_Logout() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( !$session->user_logged_in )
    $paths->main_page();
  
  $l = $session->logout();
  if ( $l == 'success' )
  {
    redirect(makeUrl(getConfig('main_page'), false, true), 'Logged out', 'You have been successfully logged out, and all cookies have been cleared. You will now be transferred to the main page.', 4);
  }
  $template->header();
  echo '<h3>An error occurred during the logout process.</h3><p>'.$l.'</p>';
  $template->footer();
}

function page_Special_Register()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  // form field trackers
  $username = '';
  $email = '';
  $realname = '';
  
  if(getConfig('account_activation') == 'disable' && ( ( $session->user_level >= USER_LEVEL_ADMIN && !isset($_GET['IWannaPlayToo']) ) || $session->user_level < USER_LEVEL_ADMIN || !$session->user_logged_in ))
  {
    $s = ($session->user_level >= USER_LEVEL_ADMIN) ? '<p>Oops...it seems that you <em>are</em> the administrator...hehe...you can also <a href="'.makeUrl($paths->page, 'IWannaPlayToo', true).'">force account registration to work</a>.</p>' : '';
    die_friendly('Registration disabled', '<p>The administrator has disabled new user registration on this site.</p>' . $s);
  }
  if ( $session->user_level < USER_LEVEL_ADMIN && $session->user_logged_in )
  {
    $paths->main_page();
  }
  if(isset($_POST['submit'])) 
  {
    $_GET['coppa'] = ( isset($_POST['coppa']) ) ? $_POST['coppa'] : 'x';
    
    $captcharesult = $session->get_captcha($_POST['captchahash']);
    if($captcharesult != $_POST['captchacode'])
    {
      $s = 'The confirmation code you entered was incorrect.';
    }
    else
    {
      if ( getConfig('enable_coppa') == '1' && ( !isset($_POST['coppa']) || ( isset($_POST['coppa']) && !in_array($_POST['coppa'], array('yes', 'no')) ) ) )
      {
        $s = 'Invalid COPPA input';
      }
      else
      {
        $coppa = ( isset($_POST['coppa']) && $_POST['coppa'] == 'yes' );
        $s = false;
        
        // decrypt password
        // as with the change pass form, we aren't going to bother checking the confirmation code because if the passwords didn't match
        // and yet the password got encrypted, that means the user screwed with the code, and if the user screwed with the code and thus
        // forgot his password, that's his problem.
        
        if ( $_POST['use_crypt'] == 'yes' )
        {
          $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
          $crypt_key = $session->fetch_public_key($_POST['crypt_key']);
          if ( !$crypt_key )
          {
            $s = 'Couldn\'t look up public encryption key';
          }
          else
          {
            $data = $_POST['crypt_data'];
            $bin_key = hexdecode($crypt_key);
            //die("Decrypting with params: key $crypt_key, data $data");
            $password = $aes->decrypt($data, $bin_key, ENC_HEX);
          }
        }
        else
        {
          $password = $_POST['password'];
        }
        
        // CAPTCHA code was correct, create the account
        // ... and check for errors returned from the crypto API
        if ( !$s )
          $s = $session->create_user($_POST['username'], $password, $_POST['email'], $_POST['real_name'], $coppa);
      }
    }
    if($s == 'success' && !$coppa)
    {
      switch(getConfig('account_activation'))
      {
        case "none":
        default:
          $str = 'You may now <a href="'.makeUrlNS('Special', 'Login').'">log in</a> with the username and password that you created.';
          break;
        case "user":
          $str = 'Because this site requires account activation, you have been sent an e-mail with further instructions. Please follow the instructions in that e-mail to continue your registration.';
          break;
        case "admin":
          $str = 'Because this site requires administrative account activation, you cannot use your account at the moment. A notice has been sent to the site administration team that will alert them that your account has been created.';
          break;
      }
      die_friendly('Registration successful', '<p>Thank you for registering, your user account has been created. '.$str.'</p>');
    }
    else if ( $s == 'success' && $coppa )
    {
      $str = 'However, in compliance with the Childrens\' Online Privacy Protection Act, you must have your parent or legal guardian activate your account. Please ask them to check their e-mail for further information.';
      die_friendly('Registration successful', '<p>Thank you for registering, your user account has been created. '.$str.'</p>');
    }
    $username = htmlspecialchars($_POST['username']);
    $email    = htmlspecialchars($_POST['email']);
    $realname = htmlspecialchars($_POST['real_name']);
  }
  $template->header();
  echo 'A user account enables you to have greater control over your browsing experience.';
  
  if ( getConfig('enable_coppa') != '1' || ( isset($_GET['coppa']) && in_array($_GET['coppa'], array('yes', 'no')) ) )
  {
    $coppa = ( isset($_GET['coppa']) && $_GET['coppa'] == 'yes' );
    $session->kill_captcha();
    $captchacode = $session->make_captcha();
    
    $pubkey = $session->rijndael_genkey();
    $challenge = $session->dss_rand();
    
    ?>
      <h3>Create a user account</h3>
      <form name="regform" action="<?php echo makeUrl($paths->page); ?>" method="post" onsubmit="runEncryption();">
        <div class="tblholder">
          <table border="0" width="100%" cellspacing="1" cellpadding="4">
            <tr><th class="subhead" colspan="3">Please tell us a little bit about yourself.</th></tr>
            
            <?php if(isset($_POST['submit'])) echo '<tr><td colspan="3" class="row2" style="color: red;">'.$s.'</td></tr>'; ?>
            
            <!-- FIELD: Username -->
            <tr>
              <td class="row1" style="width: 50%;">
                Preferred username:
                <span id="e_username"></span>
              </td>
              <td class="row1" style="width: 50%;">
                <input tabindex="1" type="text" name="username" size="30" value="<?php echo $username; ?>" onkeyup="namegood = false; validateForm();" onblur="checkUsername();" />
              </td>
              <td class="row1" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/bad.gif" id="s_username" />
              </td>
            </tr>
            
            <!-- FIELD: Password -->
            <tr>
              <td class="row3" style="width: 50%;" rowspan="<?php echo ( getConfig('pw_strength_enable') == '1' ) ? '3' : '2'; ?>">
                Password:
                <span id="e_password"></span>
                <?php if ( getConfig('pw_strength_enable') == '1' && getConfig('pw_strength_minimum') > -10 ): ?>
                <small>It needs to score at least <b><?php echo getConfig('pw_strength_minimum'); ?></b> for your registration to be accepted.</small>
                <?php endif; ?>
              </td>
              <td class="row3" style="width: 50%;">
                <input tabindex="2" type="password" name="password" size="15" onkeyup="<?php if ( getConfig('pw_strength_enable') == '1' ): ?>password_score_field(this); <?php endif; ?>validateForm();" /><?php if ( getConfig('pw_strength_enable') == '1' ): ?><span class="password-checker" style="font-weight: bold; color: #aaaaaa;"> Loading...</span><?php endif; ?>
              </td>
              <td rowspan="<?php echo ( getConfig('pw_strength_enable') == '1' ) ? '3' : '2'; ?>" class="row3" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/bad.gif" id="s_password" />
              </td>
            </tr>
            
            <!-- FIELD: Password confirmation -->
            <tr>
              <td class="row3" style="width: 50%;">
                <input tabindex="3" type="password" name="password_confirm" size="15" onkeyup="validateForm();" /> <small>Enter your password again to confirm.</small>
              </td>
            </tr>
            
            <!-- FIELD: Password strength meter -->
            
            <?php if ( getConfig('pw_strength_enable') == '1' ): ?>
            <tr>
              <td class="row3" style="width: 50%;">
                <div id="pwmeter"></div>
              </td>
            </tr>
            <?php endif; ?>
            
            <!-- FIELD: E-mail address -->
            <tr>
              <td class="row1" style="width: 50%;">
                <?php
                  if ( $coppa ) echo 'Your parent or guardian\'s e'; 
                  else echo 'E';
                ?>-mail address:
                <?php
                  if ( ( $x = getConfig('account_activation') ) == 'user' )
                  {
                    echo '<br /><small>An e-mail with an account activation key will be sent to this address, so please ensure that it is correct.</small>';
                  }
                ?>
              </td>
              <td class="row1" style="width: 50%;">
                <input tabindex="4" type="text" name="email" size="30" value="<?php echo $email; ?>" onkeyup="validateForm();" />
              </td>
              <td class="row1" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/bad.gif" id="s_email" />
              </td>
            </tr>
            
            <!-- FIELD: Real name -->
            <tr>
              <td class="row3" style="width: 50%;">
                Real name:<br />
                <small>Giving your real name is totally optional. If you choose to provide your real name, it will be used to provide attribution for any edits or contributions you may make to this site.</small>
              </td>
              <td class="row3" style="width: 50%;">
                <input tabindex="5" type="text" name="real_name" size="30" value="<?php echo $realname; ?>" /></td><td class="row3" style="max-width: 24px;">
              </td>
            </tr>
            
            <!-- FIELD: CAPTCHA image -->
            <tr>
              <td class="row1" style="width: 50%;" rowspan="2">
                Visual confirmation<br />
                <small>
                  Please enter the code shown in the image to the right into the text box. This process helps to ensure that this registration is not being performed by an automated bot. If the image to the right is illegible, you can <a href="#" onclick="regenCaptcha(); return false;">generate a new image</a>.<br />
                  <br />
                  If you are visually impaired or otherwise cannot read the text shown to the right, please contact the site management and they will create an account for you.
                </small>
              </td>
              <td colspan="2" class="row1">
                <img id="captchaimg" alt="CAPTCHA image" src="<?php echo makeUrlNS('Special', 'Captcha/'.$captchacode); ?>" />
                <span id="b_username"></span>
              </td>
            </tr>
            
            <!-- FIELD: CAPTCHA input field -->
            <tr>
              <td class="row1" colspan="2">
                Code:
                <input tabindex="6" name="captchacode" type="text" size="10" />
                <input type="hidden" name="captchahash" value="<?php echo $captchacode; ?>" />
              </td>
            </tr>
            
            <!-- FIELD: submit button -->
            <tr>
              <th class="subhead" colspan="3" style="text-align: center;">
                <input tabindex="7" type="submit" name="submit" value="Create my account" />
              </td>
            </tr>
            
          </table>
        </div>
        <?php
          $val = ( $coppa ) ? 'yes' : 'no';
          echo '<input type="hidden" name="coppa" value="' . $val . '" />';
        ?>
        <input type="hidden" name="challenge_data" value="<?php echo $challenge; ?>" />
        <input type="hidden" name="use_crypt" value="no" />
        <input type="hidden" name="crypt_key" value="<?php echo $pubkey; ?>" />
        <input type="hidden" name="crypt_data" value="" />
      <script type="text/javascript">
        // ENCRYPTION CODE
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
          var frm = document.forms.regform;
          if ( frm.password.value.length < 1 )
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
          pass1 = frm.password.value;
          pass2 = frm.password_confirm.value;
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
            pass = frm.password.value;
            pass = stringToByteArray(pass);
            cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
            if(!cryptstring)
            {
              return false;
            }
            cryptstring = byteArrayToHex(cryptstring);
            frm.crypt_data.value = cryptstring;
            frm.password.value = "";
            frm.password_confirm.value = "";
          }
          return true;
        }
        </script>
      </form>
      <!-- Don't optimize this script, it fails when compressed -->
      <enano:no-opt>
        <script type="text/javascript">
          // <![CDATA[
          var namegood = false;
          function validateForm()
          {
            var frm = document.forms.regform;
            failed = false;
            
            // Username
            if(!namegood)
            {
              //if(frm.username.value.match(/^([A-z0-9 \!@\-\(\)]+){2,}$/ig))
              var regex = new RegExp('^([^<>_&\?]+){2,}$', 'ig');
              if ( frm.username.value.match(regex) )
              {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/unknown.gif';
                document.getElementById('e_username').innerHTML = ''; // '<br /><small><b>Checking availability...</b></small>';
              } else {
                failed = true;
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/bad.gif';
                document.getElementById('e_username').innerHTML = '<br /><small>Your username must be at least two characters in length and may contain only alphanumeric characters (A-Z and 0-9), spaces, and the following characters: :, !, @, #, *.</small>';
              }
            }
            document.getElementById('b_username').innerHTML = '';
            if(hex_md5(frm.real_name.value) == '5a397df72678128cf0e8147a2befd5f1')
            {
              document.getElementById('b_username').innerHTML = '<br /><br />Hey...I know you!<br /><img alt="" src="http://upload.wikimedia.org/wikipedia/commons/thumb/7/7f/Bill_Gates_2004_cr.jpg/220px-Bill_Gates_2004_cr.jpg" />';
            }
            
            // Password
            if(frm.password.value.match(/^(.+){6,}$/ig) && frm.password_confirm.value.match(/^(.+){6,}$/ig) && frm.password.value == frm.password_confirm.value)
            {
              document.getElementById('s_password').src='<?php echo scriptPath; ?>/images/good.gif';
              document.getElementById('e_password').innerHTML = '<br /><small>The password you entered is valid.</small>';
            } else {
              failed = true;
              if(frm.password.value.length < 6)
              {
                document.getElementById('e_password').innerHTML = '<br /><small>Your password must be at least six characters in length.</small>';
              }
              else if(frm.password.value != frm.password_confirm.value)
              {
                document.getElementById('e_password').innerHTML = '<br /><small>The passwords you entered do not match.</small>';
              }
              else
              {
                document.getElementById('e_password').innerHTML = '';
              }
              document.getElementById('s_password').src='<?php echo scriptPath; ?>/images/bad.gif';
            }
            
            // E-mail address
            
            // workaround for idiot jEdit bug
            if ( validateEmail(frm.email.value) )
            {
              document.getElementById('s_email').src='<?php echo scriptPath; ?>/images/good.gif';
            } else {
              failed = true;
              document.getElementById('s_email').src='<?php echo scriptPath; ?>/images/bad.gif';
            }
            if(failed)
            {
              frm.submit.disabled = 'disabled';
            } else {
              frm.submit.disabled = false;
            }
          }
          function checkUsername()
          {
            var frm = document.forms.regform;
            
            if(!namegood)
            {
              if(frm.username.value.match(/^([A-z0-9 \.:\!@\#\*]+){2,}$/ig))
              {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/unknown.gif';
                document.getElementById('e_username').innerHTML = '';
              } else {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/bad.gif';
                document.getElementById('e_username').innerHTML = '<br /><small>Your username must be at least two characters in length and may contain only alphanumeric characters (A-Z and 0-9), spaces, and the following characters: :, !, @, #, *.</small>';
                return false;
              }
            }
            
            document.getElementById('e_username').innerHTML = '<br /><small><b>Checking availability...</b></small>';
            ajaxGet('<?php echo scriptPath; ?>/ajax.php?title=null&_mode=checkusername&name='+escape(frm.username.value), function() {
              if(ajax.readyState == 4)
                if(ajax.responseText == 'good')
                {
                  document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/good.gif';
                  document.getElementById('e_username').innerHTML = '<br /><small><b>This username is available.</b></small>';
                  namegood = true;
                } else if(ajax.responseText == 'bad') {
                  document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/bad.gif';
                  document.getElementById('e_username').innerHTML = '<br /><small><b>Error: that username is already taken.</b></small>';
                  namegood = false;
                } else {
                  document.getElementById('e_username').innerHTML = ajax.responseText;
                }
            });
          }
          function regenCaptcha()
          {
            document.getElementById('captchaimg').src = '<?php echo makeUrlNS("Special", "Captcha/"); ?>'+frm.captchahash.value+'/'+Math.floor(Math.random() * 100000);
            return false;
          }
          <?php if ( getConfig('pw_strength_enable') == '1' ): ?>
          var frm = document.forms.regform;
          password_score_field(frm.password);
          <?php endif; ?>
          validateForm();
          setTimeout('checkUsername();', 1000);
          // ]]>
        </script>
      </enano:no-opt>
    <?php
  }
  else
  {
    $year = intval( date('Y') );
    $year = $year - 13;
    $month = date('F');
    $day = date('d');
    
    $yo13_date = "$month $day, $year";
    $link_coppa_yes = makeUrlNS('Special', 'Register', 'coppa=yes', true);
    $link_coppa_no  = makeUrlNS('Special', 'Register', 'coppa=no',  true);
    
    // COPPA enabled, ask age
    echo '<div class="tblholder">';
    echo '<table border="0" cellspacing="1" cellpadding="4">';
    echo '<tr>
            <td class="row1">
              Before you can register, please tell us your age.
            </td>
          </tr>
          <tr>
            <td class="row3">
              <a href="' . $link_coppa_no  . '">I was born <b>on or before</b> ' . $yo13_date . ' and am <b>at least</b> 13 years of age</a><br />
              <a href="' . $link_coppa_yes . '">I was born <b>after</b> ' . $yo13_date . ' and am <b>less than</b> 13 years of age</a>
            </td>
          </tr>';
    echo '</table>';
    echo '</div>';
  }
  $template->footer();
}

/*
If you want the old preferences page back, be my guest.
function page_Special_Preferences() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  if(isset($_POST['submit'])) {
    $data = $session->update_user($session->user_id, $_POST['username'], $_POST['current_pass'], $_POST['new_pass'], $_POST['email'], $_POST['real_name'], $_POST['sig']);
    if($data == 'success') echo '<h3>Information</h3><p>Your profile has been updated. <a href="'.scriptPath.'/">Return to the index page</a>.</p>';
    else echo $data;
  } else {
    echo '
    <h3>Edit your profile</h3>
    <form action="'.makeUrl($paths->nslist['Special'].'Preferences').'" method="post">
      <table border="0" style="margin-left: 0.2in;">   
        <tr><td>Username:</td><td><input type="text" name="username" value="'.$session->username.'" /></td></tr>
        <tr><td>Current Password:</td><td><input type="password" name="current_pass" /></td></tr>
        <tr><td colspan="2"><small>You only need to enter your current password if you are changing your e-mail address or changing your password.</small></td></tr>
        <tr><td>New Password:</td><td><input type="password" name="new_pass" /></td></tr>
        <tr><td>E-mail:</td><td><input type="text" name="email" value="'.$session->email.'" /></td></tr>
        <tr><td>Real Name:</td><td><input type="text" name="real_name" value="'.$session->real_name.'" /></td></tr>
        <tr><td>Signature:<br /><small>Your signature appears<br />below your comment posts.</small></td><td><textarea rows="10" cols="40" name="sig">'.$session->signature.'</textarea></td></tr>
        <tr><td colspan="2">
        <input type="submit" name="submit" value="Save Changes" /></td></tr>
      </table>
    </form>
    ';
  }
  $template->footer();
}
*/

function page_Special_Contributions() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  $user = $paths->getParam();
  if(!$user && isset($_GET['user']))
  {
    $user = $_GET['user'];
  }
  elseif(!$user && !isset($_GET['user']))
  {
    echo 'No user selected!';
    $template->footer();
    return;
  }
  
  $user = $db->escape($user);
  
  $q = 'SELECT time_id,date_string,page_id,namespace,author,edit_summary,minor_edit,page_id,namespace FROM '.table_prefix.'logs WHERE author=\''.$user.'\' AND action=\'edit\' ORDER BY time_id DESC;';
  if(!$db->sql_query($q)) $db->_die('The history data for the page "'.$paths->cpage['name'].'" could not be selected.');
  echo 'History of edits and actions<h3>Edits:</h3>';
  if($db->numrows() < 1) echo 'No history entries in this category.';
  while($r = $db->fetchrow())
  {
    $title = get_page_title($r['page_id'], $r['namespace']);    
    echo '<a href="' . makeUrlNS($r['namespace'], $r['page_id'], "oldid={$r['time_id']}", true) . '" onclick="ajaxHistView(\''.$r['time_id'].'\', \''.$paths->nslist[$r['namespace']].$r['page_id'].'\'); return false;"><i>'.$r['date_string'].'</i></a> (<a href="#" onclick="ajaxRollback(\''.$r['time_id'].'\'); return false;">revert to</a>) <a href="'.makeUrl($paths->nslist[$r['namespace']].$r['page_id']).'">'.htmlspecialchars($title).'</a>: '.$r['edit_summary'];
    if($r['minor_edit']) echo '<b> - minor edit</b>';
    echo '<br />';
  }
  $db->free_result();
  echo '<h3>Other changes:</h3>';
  $q = 'SELECT log_type,time_id,action,date_string,page_id,namespace,author,edit_summary,minor_edit,page_id,namespace FROM '.table_prefix.'logs WHERE author=\''.$user.'\' AND action!=\'edit\' ORDER BY time_id DESC;';
  if(!$db->sql_query($q)) $db->_die('The history data for the page "'.$paths->cpage['name'].'" could not be selected.');
  if($db->numrows() < 1) echo 'No history entries in this category.';
  while($r = $db->fetchrow()) 
  {
    if ( $r['log_type'] == 'page' )
    {
      $title = get_page_title($r['page_id'], $r['namespace']);
      echo '(<a href="#" onclick="ajaxRollback(\''.$r['time_id'].'\'); return false;">rollback</a>) <i>'.$r['date_string'].'</i> <a href="'.makeUrl($paths->nslist[$r['namespace']].$r['page_id']).'">'.htmlspecialchars($title).'</a>: ';
      if      ( $r['action'] == 'prot'   ) echo 'Protected page; reason: '.$r['edit_summary'];
      else if ( $r['action'] == 'unprot' ) echo 'Unprotected page; reason: '.$r['edit_summary'];
      else if ( $r['action'] == 'rename' ) echo 'Renamed page; old title was: '.htmlspecialchars($r['edit_summary']);
      else if ( $r['action'] == 'create' ) echo 'Created page';
      else if ( $r['action'] == 'delete' ) echo 'Deleted page';
      if ( $r['minor_edit'] ) echo '<b> - minor edit</b>';
      echo '<br />';
    }
    else if($r['log_type']=='security') 
    {
      // Not implemented, and when it is, it won't be public
    }
  }
  $db->free_result();
  $template->footer();
}

function page_Special_ChangeStyle()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!$session->user_logged_in) die_friendly('Access denied', '<p>You must be logged in to change your style. Spoofer.</p>');
  if(isset($_POST['theme']) && isset($_POST['style']) && isset($_POST['return_to']))
  {
    if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['theme']) )
      die('Hacking attempt');
    if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['style']) )
      die('Hacking attempt');
    $d = ENANO_ROOT . '/themes/' . $_POST['theme'];
    $f = ENANO_ROOT . '/themes/' . $_POST['theme'] . '/css/' . $_POST['style'] . '.css';
    if(!file_exists($d) || !is_dir($d)) die('The directory "'.$d.'" does not exist.');
    if(!file_exists($f)) die('The file "'.$f.'" does not exist.');
    $d = $db->escape($_POST['theme']);
    $f = $db->escape($_POST['style']);
    $q = 'UPDATE '.table_prefix.'users SET theme=\''.$d.'\',style=\''.$f.'\' WHERE username=\''.$session->username.'\'';
    if(!$db->sql_query($q))
    {
      $db->_die('Your theme/style preferences were not updated.');
    }
    else
    {
      redirect(makeUrl($_POST['return_to']), '', '', 0);
    }
  }
  else
  {
    $template->header();
      $ret = ( isset($_POST['return_to']) ) ? $_POST['return_to'] : $paths->getParam(0);
      if(!$ret) $ret = getConfig('main_page');
      ?>
        <form action="<?php echo makeUrl($paths->page); ?>" method="post">
          <?php if(!isset($_POST['themeselected'])) { ?>
            <h3>Please select a new theme:</h3>
            <p>
              <select name="theme">
               <?php
                foreach($template->theme_list as $t) {
                  if($t['enabled'])
                  {
                    echo '<option value="'.$t['theme_id'].'"';
                    if($t['theme_id'] == $session->theme) echo ' selected="selected"';
                    echo '>'.$t['theme_name'].'</option>';
                  }
                }
               ?>
              </select>
            </p>
            <p><input type="hidden" name="return_to" value="<?php echo $ret; ?>" />
               <input type="submit" name="themeselected" value="Continue" /></p>
          <?php } else { 
            $theme = $_POST['theme'];
            if ( !preg_match('/^([0-9A-z_-]+)$/i', $theme ) )
              die('Hacking attempt');
            ?>
            <h3>Please select a stylesheet:</h3>
            <p>
              <select name="style">
                <?php
                  $dir = './themes/'.$theme.'/css/';
                  $list = Array();
                  // Open a known directory, and proceed to read its contents
                  if (is_dir($dir)) {
                    if ($dh = opendir($dir)) {
                      while (($file = readdir($dh)) !== false) {
                        if(preg_match('#^(.*?)\.css$#is', $file) && $file != '_printable.css') {
                          $list[] = substr($file, 0, strlen($file)-4);
                        }
                      }
                      closedir($dh);
                    }
                  } else die($dir.' is not a dir');
                  foreach ( $list as $l )
                  {
                    echo '<option value="'.$l.'">'.capitalize_first_letter($l).'</option>';
                  }
                ?>
              </select>
            </p>
            <p><input type="hidden" name="return_to" value="<?php echo $ret; ?>" />
               <input type="hidden" name="theme" value="<?php echo $theme; ?>" />
               <input type="submit" name="allclear" value="Change style" /></p>
          <?php } ?>
        </form>
      <?php
    $template->footer();
  }
}

function page_Special_ActivateAccount()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $user = $paths->getParam(0);
  if(!$user) die_friendly('Account activation error', '<p>This page can only be accessed using links sent to users via e-mail.</p>');
  $key = $paths->getParam(1);
  if(!$key) die_friendly('Account activation error', '<p>This page can only be accessed using links sent to users via e-mail.</p>');
  $s = $session->activate_account(str_replace('_', ' ', $user), $key);
  if($s > 0) die_friendly('Activation successful', '<p>Your account is now active. Thank you for registering.</p>');
  else die_friendly('Activation failed', '<p>The activation key was probably incorrect.</p>');
}

function page_Special_Captcha()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if($paths->getParam(0) == 'make')
  {
    $session->kill_captcha();
    echo $session->make_captcha();
    return;
  }
  $hash = $paths->getParam(0);
  if(!$hash || !preg_match('#^([0-9a-f]*){32,32}$#i', $hash)) $paths->main_page();
  $code = $session->get_captcha($hash);
  if(!$code) die('Invalid hash or IP address incorrect.');
  require(ENANO_ROOT.'/includes/captcha.php');
  $captcha = new captcha($code);
  //header('Content-disposition: attachment; filename=autocaptcha.png');
  $captcha->make_image();
  exit;
}

function page_Special_PasswordReset()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  if($paths->getParam(0) == 'stage2')
  {
    $user_id = intval($paths->getParam(1));
    $encpass = $paths->getParam(2);
    if ( $user_id < 2 )
    {
      echo '<p>Hacking attempt</p>';
      $template->footer();
      return false;
    }
    if(!preg_match('#^([a-f0-9]+)$#i', $encpass))
    {
      echo '<p>Hacking attempt</p>';
      $template->footer();
      return false;
    }
    
    $q = $db->sql_query('SELECT username,temp_password_time FROM '.table_prefix.'users WHERE user_id='.$user_id.' AND temp_password=\'' . $encpass . '\';');
    if($db->numrows() < 1)
    {
      echo '<p>Invalid credentials</p>';
      $template->footer();
      return false;
    }
    $row = $db->fetchrow();
    $db->free_result();
    
    if ( ( intval($row['temp_password_time']) + ( 3600 * 24 ) ) < time() )
    {
      echo '<p>Your temporary password has expired. Please <a href="' . makeUrlNS('Special', 'PasswordReset') . '">request another one</a>.</p>';
      $template->footer();
      return false;
    }
    
    if ( isset($_POST['do_stage2']) )
    {
      $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
      if($_POST['use_crypt'] == 'yes')
      {
        $crypt_key = $session->fetch_public_key($_POST['crypt_key']);
        if(!$crypt_key)
        {
          echo 'ERROR: Couldn\'t look up public key for decryption.';
          $template->footer();
          return false;
        }
        $crypt_key = hexdecode($crypt_key);
        $data = $aes->decrypt($_POST['crypt_data'], $crypt_key, ENC_HEX);
        if(strlen($data) < 6)
        {
          echo 'ERROR: Your password must be six characters or greater in length.';
          $template->footer();
          return false;
        }
      }
      else
      {
        $data = $_POST['pass'];
        $conf = $_POST['pass_confirm'];
        if($data != $conf)
        {
          echo 'ERROR: The passwords you entered do not match.';
          $template->footer();
          return false;
        }
        if(strlen($data) < 6)
        {
          echo 'ERROR: Your password must be six characters or greater in length.';
          $template->footer();
          return false;
        }
      }
      if(empty($data))
      {
        echo 'ERROR: Sanity check failed!';
        $template->footer();
        return false;
      }
      if ( getConfig('pw_strength_enable') == '1' )
      {
        $min_score = intval(getConfig('pw_strength_minimum'));
        $inp_score = password_score($data);
        if ( $inp_score < $min_score )
        {
          $url = makeUrl($paths->fullpage);
          echo "<p>ERROR: Your password did not pass the complexity score requirement. You need $min_score points to pass; your password received a score of $inp_score. <a href=\"$url\">Go back</a></p>";
          $template->footer();
          return false;
        }
      }
      $encpass = $aes->encrypt($data, $session->private_key, ENC_HEX);
      $q = $db->sql_query('UPDATE '.table_prefix.'users SET password=\'' . $encpass . '\',temp_password=\'\',temp_password_time=0 WHERE user_id='.$user_id.';');
      
      if($q)
      {
        $session->login_without_crypto($row['username'], $data);
        echo '<p>Your password has been reset. Return to the <a href="' . makeUrl(getConfig('main_page')) . '">main page</a>.</p>';
      }
      else
      {
        echo $db->get_error();
      }
      
      $template->footer();
      return false;
    }
    
    // Password reset form
    $pubkey = $session->rijndael_genkey();
    
    $evt_get_score = ( getConfig('pw_strength_enable') == '1' ) ? 'onkeyup="password_score_field(this);" ' : '';
    $pw_meter =      ( getConfig('pw_strength_enable') == '1' ) ? '<tr><td class="row1">Password strength rating:</td><td class="row1"><div id="pwmeter"></div><script type="text/javascript">password_score_field(document.forms.resetform.pass);</script></td></tr>' : '';
    $pw_blurb =      ( getConfig('pw_strength_enable') == '1' && intval(getConfig('pw_strength_minimum')) > -10 ) ? '<br /><small>Your password needs to have a score of at least <b>'.getConfig('pw_strength_minimum').'</b>.</small>' : '';
    
    ?>
    <form action="<?php echo makeUrl($paths->fullpage); ?>" method="post" name="resetform" onsubmit="return runEncryption();">
      <br />
      <div class="tblholder">
        <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Reset password</th></tr>
          <tr><td class="row1">Password:<?php echo $pw_blurb; ?></td><td class="row1"><input name="pass" type="password" <?php echo $evt_get_score; ?>/></td></tr>
          <tr><td class="row2">Confirm: </td><td class="row2"><input name="pass_confirm" type="password" /></td></tr>
          <?php echo $pw_meter; ?>
          <tr>
            <td colspan="2" class="row1" style="text-align: center;">
              <input type="hidden" name="use_crypt" value="no" />
              <input type="hidden" name="crypt_key" value="<?php echo $pubkey; ?>" />
              <input type="hidden" name="crypt_data" value="" />
              <input type="submit" name="do_stage2" value="Reset password" />
            </td>
          </tr>
        </table>
      </div>
    </form>
    <script type="text/javascript">
    if ( !KILL_SWITCH )
    {
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
      var testpassed = ( ct == v && md5_vm_test() );
      var frm = document.forms.resetform;
      if(testpassed)
      {
        frm.use_crypt.value = 'yes';
        var cryptkey = frm.crypt_key.value;
        frm.crypt_key.value = hex_md5(cryptkey);
        cryptkey = hexToByteArray(cryptkey);
        if(!cryptkey || ( ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ) && cryptkey.length != keySizeInBits / 8 )
        {
          frm._login.disabled = true;
          len = ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ? '\nLen: '+cryptkey.length : '';
          alert('The key is messed up\nType: '+typeof(cryptkey)+len);
        }
      }
      function runEncryption()
      {
        var frm = document.forms.resetform;
        pass1 = frm.pass.value;
        pass2 = frm.pass_confirm.value;
        if ( pass1 != pass2 )
        {
          alert('The passwords you entered do not match.');
          return false;
        }
        if ( pass1.length < 6 )
        {
          alert('The new password must be 6 characters or greater in length.');
          return false;
        }
        if(testpassed)
        {
          pass = frm.pass.value;
          pass = stringToByteArray(pass);
          cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
          if(!cryptstring)
          {
            return false;
          }
          cryptstring = byteArrayToHex(cryptstring);
          frm.crypt_data.value = cryptstring;
          frm.pass.value = "";
          frm.pass_confirm.value = "";
        }
        return true;
      }
    }
    </script>
    <?php
    $template->footer();
    return true;
  }
  if(isset($_POST['do_reset']))
  {
    if($session->mail_password_reset($_POST['username']))
    {
      echo '<p>An e-mail has been sent to the e-mail address on file for your username with a new password in it. Please check your e-mail for further instructions.</p>';
    }
    else
    {
      echo '<p>Error occured, your new password was not sent.</p>';
    }
    $template->footer();
    return true;
  }
  echo '<p>Don\'t worry, it happens to the best of us.</p>
        <p>To reset your password, just enter your username below, and a new password will be e-mailed to you.</p>
        <form action="'.makeUrl($paths->page).'" method="post" onsubmit="if(!submitAuthorized) return false;">
          <p>Username:  '.$template->username_field('username').'</p>
          <p><input type="submit" name="do_reset" value="Mail new password" /></p>
        </form>';
  $template->footer();
}

function page_Special_Memberlist()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  
  $startletters = 'abcdefghijklmnopqrstuvwxyz';
  $startletters = enano_str_split($startletters);
  $startletter = ( isset($_GET['letter']) ) ? strtolower($_GET['letter']) : '';
  if ( !in_array($startletter, $startletters) && $startletter != 'chr' )
  {
    $startletter = '';
  }
  
  $startletter_sql = $startletter;
  if ( $startletter == 'chr' )
  {
    $startletter_sql = '([^a-z])';
  }
  
  // offset
  $offset = ( isset($_GET['offset']) && strval(intval($_GET['offset'])) === $_GET['offset']) ? intval($_GET['offset']) : 0;
  
  // sort order
  $sortkeys = array(
      'uid' => 'u.user_id',
      'username' => 'u.username',
      'email' => 'u.email',
      'regist' => 'u.reg_time'
    );
  
  $sortby = ( isset($_GET['sort']) && isset($sortkeys[$_GET['sort']]) ) ? $_GET['sort'] : 'username';
  $sort_sqllet = $sortkeys[$sortby];
  
  $target_order = ( isset($_GET['orderby']) && in_array($_GET['orderby'], array('ASC', 'DESC')) )? $_GET['orderby'] : 'ASC';
  
  $sortorders = array();
  foreach ( $sortkeys as $k => $_unused )
  {
    $sortorders[$k] = ( $sortby == $k ) ? ( $target_order == 'ASC' ? 'DESC' : 'ASC' ) : 'ASC';
  }
  
  // Why 3.3714%? 100 percent / 28 cells, minus a little (0.2% / cell) to account for cell spacing
  
  echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4" style="text-align: center;">
            <tr>';
  echo '<td class="row1" style="width: 3.3714%;"><a href="' . makeUrlNS('Special', 'Memberlist', 'letter=&sort=' . $sortby . '&orderby=' . $target_order, true) . '">All</a></td>';
  echo '<td class="row1" style="width: 3.3714%;"><a href="' . makeUrlNS('Special', 'Memberlist', 'letter=chr&sort=' . $sortby . '&orderby=' . $target_order, true) . '">#</a></td>';
  foreach ( $startletters as $letter )
  {
    echo '<td class="row1" style="width: 3.3714%;"><a href="' . makeUrlNS('Special', 'Memberlist', 'letter=' . $letter . '&sort=' . $sortby . '&orderby=' . $target_order, true) . '">' . strtoupper($letter) . '</a></td>';
  }
  echo '    </tr>
          </table>
        </div>';
  
  // formatter parameters
  $formatter = new MemberlistFormatter();
  $formatters = array(
    'username' => array($formatter, 'username'),
    'user_level' => array($formatter, 'user_level'),
    'email' => array($formatter, 'email'),
    'reg_time' => array($formatter, 'reg_time')
    );
  
  // User search             
  if ( isset($_GET['finduser']) )
  {
    $finduser = str_replace(array(  '%',   '_'),
                            array('\\%', '\\_'),
                            $_GET['finduser']);
    $finduser = str_replace(array('*', '?'),
                            array('%', '_'),
                            $finduser);
    $finduser = $db->escape($finduser);
    $username_where = 'u.username LIKE "' . $finduser . '"';
    $finduser_url = 'finduser=' . rawurlencode($_GET['finduser']) . '&';
  }
  else
  {
    $username_where = 'u.username REGEXP "^' . $startletter_sql . '"';
    $finduser_url = '';
  }
  
  // Column markers
  $headings = '<tr>
                 <th style="max-width: 50px;">
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=uid&orderby=' . $sortorders['uid'], true) . '">#</a>
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=username&orderby=' . $sortorders['username'], true) . '">Username</a>
                 </th>
                 <th>
                   Title
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=email&orderby=' . $sortorders['email'], true) . '">E-mail</a>
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=regist&orderby=' . $sortorders['regist'], true) . '">Registered</a>
                 </th>
               </tr>';
               
  // determine number of rows
  $q = $db->sql_query('SELECT u.user_id FROM '.table_prefix.'users AS u WHERE ' . $username_where . ' AND u.username != "Anonymous";');
  if ( !$q )
    $db->_die();
  
  $num_rows = $db->numrows();
  $db->free_result();
  
  if ( !empty($finduser_url) )
  {
    $s = ( $num_rows == 1 ) ? '' : 'es';
    echo "<h3 style='float: left;'>Search returned $num_rows match$s</h3>";
  }
  
  // main selector
  $q = $db->sql_unbuffered_query('SELECT u.user_id, u.username, u.reg_time, u.email, u.user_level, u.reg_time, x.email_public FROM '.table_prefix.'users AS u
                                    LEFT JOIN '.table_prefix.'users_extra AS x
                                      ON ( u.user_id = x.user_id )
                                    WHERE ' . $username_where . ' AND u.username != "Anonymous"
                                    ORDER BY ' . $sort_sqllet . ' ' . $target_order . ';');
  if ( !$q )
    $db->_die();
  
  $html = paginate(
            $q,                                                                                                       // MySQL result resource
            '<tr>
               <td class="{_css_class}">{user_id}</td>
               <td class="{_css_class}" style="text-align: left;">{username}</td>
               <td class="{_css_class}">{user_level}</td>
               <td class="{_css_class}">{email}</small></td>
               <td class="{_css_class}">{reg_time}</td>
             </tr>
             ',                                                                                                       // TPL code for rows
             $num_rows,                                                                                               // Number of results
             makeUrlNS('Special', 'Memberlist', ( str_replace('%', '%%', $finduser_url) ) . 'letter=' . $startletter . '&offset=%s&sort=' . $sortby . '&orderby=' . $target_order ), // Result URL
             $offset,                                                                                                 // Start at this number
             25,                                                                                                      // Results per page
             $formatters,                                                                                             // Formatting hooks
             '<div class="tblholder">
                <table border="0" cellspacing="1" cellpadding="4" style="text-align: center;">
                  ' . $headings . '
                 ',                                                                                                   // Header (printed before rows)
             '  ' . $headings . '
                 </table>
              </div>
              ' .
              '<div style="float: left;">
                <form action="' . makeUrlNS('Special', 'Memberlist') . '" method="get" onsubmit="if ( !submitAuthorized ) return false;">'
               . ( urlSeparator == '&' ? '<input type="hidden" name="title" value="' . htmlspecialchars( $paths->nslist[$paths->namespace] . $paths->cpage['urlname_nons'] ) . '" />' : '' )
               . ( $session->sid_super ? '<input type="hidden" name="auth"  value="' . $session->sid_super . '" />' : '')
               . '<p>Find a member: ' . $template->username_field('finduser') . ' <input type="submit" value="Go" /><br /><small>You may use the following wildcards: * to match multiple characters, ? to match a single character.</small></p>'
               . '</form>
               </div>'                                                                                                // Footer (printed after rows)
          );
  
  if ( $num_rows < 1 )
  {
    echo ( isset($_GET['finduser']) ) ? '<p>Sorry - no users that matched your query could be found. Please try some different search terms.</p>' : '<p>Sorry - no users with usernames that start with that letter could be found.</p>';
  }
  else
  {
    echo $html;
  }
  
  $template->footer();
}

/**
 * Class for formatting results for the memberlist.
 * @access private
 */

class MemberlistFormatter
{
  function username($username, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $userpage = $paths->nslist['User'] . sanitize_page_id($username);
    $class = ( isPage($userpage) ) ? ' title="Click to view this user\'s userpage"' : ' class="wikilink-nonexistent" title="This user hasn\'t created a userpage yet, but you can still view profile details by clicking this link."';
    $anchor = '<a href="' . makeUrlNS('User', sanitize_page_id($username)) . '"' . $class . '>' . htmlspecialchars($username) . '</a>';
    if ( $session->user_level >= USER_LEVEL_ADMIN )
    {
      $anchor .= ' <small>- <a href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'UserManager&src=get&username=' . urlencode($username), true) . '"
                               onclick="ajaxAdminUser(\'' . addslashes(htmlspecialchars($username)) . '\'); return false;">Administer user</a></small>';
    }
    return $anchor;
  }
  function user_level($level, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    switch ( $level )
    {
      case USER_LEVEL_GUEST:
        $s_level = 'Guest'; break;
      case USER_LEVEL_MEMBER:
      case USER_LEVEL_CHPREF:
        $s_level = 'Member'; break;
      case USER_LEVEL_MOD:
        $s_level = 'Moderator'; break;
      case USER_LEVEL_ADMIN:
        $s_level = 'Site administrator'; break;
      default:
        $s_level = 'Unknown (level ' . $level . ')';
    }
    return $s_level;
  }
  function email($addy, $row)
  {
    if ( $row['email_public'] == '1' )
    {
      global $email;
      $addy = $email->encryptEmail($addy);
      return $addy;
    }
    else
    {
      return '<small>&lt;Non-public&gt;</small>';
    }
  }
  /**
   * Format a time as a reference to a day, with user-friendly "X days ago"/"Today"/"Yesterday" returned when relevant.
   * @param int UNIX timestamp
   * @return string
   */
  
  function format_date($time)
  {
    // Our formattting string to pass to date()
    // This should not include minute/second info, only today's date in whatever format suits your fancy
    $formatstring = 'F j, Y';
    // Today's date
    $today = date($formatstring);
    // Yesterday's date
    $yesterday = date($formatstring, (time() - (24*60*60)));
    // Date on the input
    $then = date($formatstring, $time);
    // "X days ago" logic
    for ( $i = 2; $i <= 6; $i++ )
    {
      // hours_in_day * minutes_in_hour * seconds_in_minute * num_days
      $offset = 24 * 60 * 60 * $i;
      $days_ago = date($formatstring, (time() - $offset));
      // so does the input timestamp match the date from $i days ago?
      if ( $then == $days_ago )
      {
        // yes, return $i
        return "$i days ago";
      }
    }
    // either yesterday, today, or before 6 days ago
    switch($then)
    {
      case $today:
        return 'Today';
      case $yesterday:
        return 'Yesterday';
      default:
        return $then;
    }
    //     .--.
    //    |o_o |
    //    |!_/ |
    //   //   \ \
    //  (|     | )
    // /'\_   _/`\
    // \___)=(___/
    return 'Linux rocks!';
  }
  function reg_time($time, $row)
  {
    return $this->format_date($time);
  }
}

?>