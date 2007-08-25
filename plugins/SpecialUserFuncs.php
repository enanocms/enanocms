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
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Captcha\',
      \'urlname\'=>\'Captcha\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Forgot password\',
      \'urlname\'=>\'PasswordReset\',
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
  
  $pubkey = $session->rijndael_genkey();
  $challenge = $session->dss_rand();
  
  if ( isset($_GET['act']) && $_GET['act'] == 'getkey' )
  {
    $username = ( $session->user_logged_in ) ? $session->username : false;
    $response = Array(
      'username' => $username,
      'key' => $pubkey,
      'challenge' => $challenge
      );
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
  $header = ( $level > USER_LEVEL_MEMBER ) ? 'Please re-enter your login details' : 'Please enter your username and password to log in.';
  if ( isset($_POST['login']) )
  {
    echo '<p>'.$__login_status.'</p>';
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
              echo '<p>Logging in enables you to use your preferences and access member information. If you don\'t have a username and password here, you can <a href="'.makeUrl($paths->nslist['Special'].'Register').'">create an account</a>.</p>';
            }
            else
            {
              echo '<p>You are requesting that a sensitive operation be performed. To continue, please re-enter your password to confirm your identity.</p>';
            }
            ?>
          </td>
        </tr>
        <tr>
          <td class="row2">
            Username:
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
          <td rowspan="2" class="row3">
            <small>Forgot your password? <a href="<?php echo makeUrlNS('Special', 'PasswordReset'); ?>">No problem.</a><br />
            Maybe you need to <a href="<?php echo makeUrlNS('Special', 'Register'); ?>">create an account</a>.</small>
          </td>
          <?php } ?>
        </tr>
        <tr>
          <td class="row2">Password:<br /></td><td class="row1"><input name="pass" size="25" type="password" tabindex="<?php echo ( $level <= USER_LEVEL_MEMBER ) ? '2' : '1'; ?>" /></td>
         </tr>
         <?php if ( $level <= USER_LEVEL_MEMBER ) { ?>
         <tr>
           <td class="row3" colspan="3">
             <p><b>Important note regarding cryptography:</b> Some countries do not allow the import or use of cryptographic technology. If you live in one of the countries listed below, you should <a href="<?php if($p=$paths->getParam(0))$u='/'.$p;else $u='';echo makeUrl($paths->page.$u, 'level='.$level.'&use_crypt=0', true); ?>">log in without using encryption</a>.</p>
             <p>This restriction applies to the following countries: Belarus, China, India, Israel, Kazakhstan, Mongolia, Pakistan, Russia, Saudi Arabia, Singapore, Tunisia, Venezuela, and Vietnam.</p>
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
    $level = ( isset($data['level']) ) ? intval($data['level']) : USER_LEVEL_MEMBER;
    $result = $session->login_with_crypto($data['username'], $data['crypt_data'], $data['crypt_key'], $data['challenge'], $level);
    $session->start();
    //echo "$result\n$session->sid_super";
    //exit;
    if ( $result == 'success' )
    {
      $response = Array(
          'result' => 'success',
          'key' => $session->sid_super // ( ( $session->sid_super ) ? $session->sid_super : $session->sid )
        );
    }
    else
    {
      $response = Array(
          'result' => 'error',
          'error' => $result
        );
    }
    $response = $json->encode($response);
    echo $response;
    $db->close();
    exit;
  }
  if(isset($_POST['login'])) {
    if($_POST['use_crypt'] == 'yes')
    {
      $result = $session->login_with_crypto($_POST['username'], $_POST['crypt_data'], $_POST['crypt_key'], $_POST['challenge_data'], intval($_POST['auth_level']));
    }
    else
    {
      $result = $session->login_without_crypto($_POST['username'], $_POST['pass'], false, intval($_POST['auth_level']));
    }
    $session->start();
    $paths->init();
    if($result == 'success')
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
        
        // CAPTCHA code was correct, create the account
        $s = $session->create_user($_POST['username'], $_POST['password'], $_POST['email'], $_POST['real_name'], $coppa);
      }
    }
    if($s == 'success' && !isset($coppa))
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
  }
  $template->header();
  echo 'A user account enables you to have greater control over your browsing experience.';
  
  if ( getConfig('enable_coppa') != '1' || ( isset($_GET['coppa']) && in_array($_GET['coppa'], array('yes', 'no')) ) )
  {
    $coppa = ( isset($_GET['coppa']) && $_GET['coppa'] == 'yes' );
    $session->kill_captcha();
    $captchacode = $session->make_captcha();
    ?>
      <h3>Create a user account</h3>
      <form name="regform" action="<?php echo makeUrl($paths->page); ?>" method="post">
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
                <input type="text" name="username" size="30" onkeyup="namegood = false; validateForm();" onblur="checkUsername();" />
              </td>
              <td class="row1" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/bad.gif" id="s_username" />
              </td>
            </tr>
            
            <!-- FIELD: Password -->
            <tr>
              <td class="row3" style="width: 50%;" rowspan="2">
                Password:
                <span id="e_password"></span>
              </td>
              <td class="row3" style="width: 50%;">
                <input type="password" name="password" size="30" onkeyup="validateForm();" />
              </td>
              <td rowspan="2" class="row3" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/bad.gif" id="s_password" />
              </td>
            </tr>
            
            <!-- FIELD: Password confirmation -->
            <tr>
              <td class="row3" style="width: 50%;">
                <input type="password" name="password_confirm" size="30" onkeyup="validateForm();" /> <small>Enter your password again to confirm.</small>
              </td>
            </tr>
            
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
                <input type="text" name="email" size="30" onkeyup="validateForm();" />
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
                <input type="text" name="real_name" size="30" /></td><td class="row3" style="max-width: 24px;">
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
                <input name="captchacode" type="text" size="10" />
                <input type="hidden" name="captchahash" value="<?php echo $captchacode; ?>" />
              </td>
            </tr>
            
            <!-- FIELD: submit button -->
            <tr>
              <th class="subhead" colspan="3" style="text-align: center;">
                <input type="submit" name="submit" value="Create my account" />
              </td>
            </tr>
            
          </table>
        </div>
        <?php
          $val = ( $coppa ) ? 'yes' : 'no';
          echo '<input type="hidden" name="coppa" value="' . $val . '" />';
        ?>
      </form>
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
            if(frm.username.value.match(/^([A-z0-9 \!@\-\(\)]+){2,}$/ig))
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
              document.getElementById('e_password').innerHTML = '<br /><small>Your password must be at least six characters in length.</small>';
            else if(frm.password.value != frm.password_confirm.value)
              document.getElementById('e_password').innerHTML = '<br /><small>The passwords you entered do not match.</small>';
            else
              document.getElementById('e_password').innerHTML = '';
            document.getElementById('s_password').src='<?php echo scriptPath; ?>/images/bad.gif';
          }
          
          // E-mail address
          if(frm.email.value.match(/^(?:[\w\d]+\.?)+@(?:(?:[\w\d]\-?)+\.)+\w{2,4}$/))
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
          var frm = document.forms.regform;
          document.getElementById('captchaimg').src = '<?php echo makeUrlNS("Special", "Captcha/"); ?>'+frm.captchahash.value+'/'+Math.floor(Math.random() * 100000);
          return false;
        }
        validateForm();
        setTimeout('checkUsername();', 1000);
        // ]]>
      </script>
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
  if(!$user) die_friendly('Account activation error', '<p>The URL was incorrect.</p>');
  $key = $paths->getParam(1);
  if(!$key) die_friendly('Account activation error', '<p>The URL was incorrect.</p>');
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
    
    if ( ( intval($row['temp_password_time']) + 3600 * 24 ) < time() )
    {
      echo '<p>Password has expired</p>';
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
    
    ?>
    <form action="<?php echo makeUrl($paths->fullpage); ?>" method="post" name="resetform" onsubmit="return runEncryption();">
      <br />
      <div class="tblholder">
        <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Reset password</th></tr>
          <tr><td class="row1">Password:</td><td class="row1"><input name="pass" type="password" /></td></tr>
          <tr><td class="row2">Confirm: </td><td class="row2"><input name="pass_confirm" type="password" /></td></tr>
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

?>