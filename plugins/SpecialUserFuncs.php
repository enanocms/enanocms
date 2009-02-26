<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialuserfuncs_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialuserfuncs_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.5",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

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
 
global $db, $session, $paths, $template, $plugins; // Common objects

// $plugins->attachHook('session_started', 'SpecialUserFuncs_paths_init();');

function SpecialUserFuncs_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_log_in',
    'urlname'=>'Login',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  $paths->add_page(Array(
    'name'=>'specialpage_log_out',
    'urlname'=>'Logout',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  $paths->add_page(Array(
    'name'=>'specialpage_register',
    'urlname'=>'Register',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  $paths->add_page(Array(
    'name'=>'specialpage_preferences',
    'urlname'=>'Preferences',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_contributions',
    'urlname'=>'Contributions',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_change_theme',
    'urlname'=>'ChangeStyle',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_activate_account',
    'urlname'=>'ActivateAccount',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_captcha',
    'urlname'=>'Captcha',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_password_reset',
    'urlname'=>'PasswordReset',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_member_list',
    'urlname'=>'Memberlist',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
    
  $paths->add_page(Array(
    'name'=>'specialpage_language_export',
    'urlname'=>'LangExportJSON',
    'namespace'=>'Special',
    'special'=>0,'visible'=>0,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
    
  $paths->add_page(Array(
    'name'=>'specialpage_avatar',
    'urlname'=>'Avatar',
    'namespace'=>'Special',
    'special'=>0,'visible'=>0,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

$__login_status = '';

function page_Special_Login()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $__login_status;
  global $lang;
  
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
    header('Content-type: text/javascript');
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
    
    // 1.1.3: generate diffie hellman key
    $response['dh_supported'] = $dh_supported;
    if ( $dh_supported )
    {
      $dh_key_priv = dh_gen_private();
      $dh_key_pub = dh_gen_public($dh_key_priv);
      $dh_key_priv = $_math->str($dh_key_priv);
      $dh_key_pub = $_math->str($dh_key_pub);
      $response['dh_public_key'] = $dh_key_pub;
      // store the keys in the DB
      $q = $db->sql_query('INSERT INTO ' . table_prefix . "diffiehellman( public_key, private_key ) VALUES ( '$dh_key_pub', '$dh_key_priv' );");
      if ( !$q )
        $db->die_json();
    }
    
    $response = enano_json_encode($response);
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
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Login').'" method="post" name="loginform" onsubmit="try{runEncryption();}catch(e){};">';
  $header = ( $level > USER_LEVEL_MEMBER ) ? $lang->get('user_login_message_short_elev') : $lang->get('user_login_message_short');
  if ( isset($_POST['login']) )
  {
    $errstring = $__login_status['error'];
    switch($__login_status['error'])
    {
      case 'key_not_found':
        $errstring = $lang->get('user_err_key_not_found');
        break;
      case 'ERR_DH_KEY_NOT_FOUND':
        $errstring = $lang->get('user_err_dh_key_not_found'); // . " -- {$__login_status['debug']}";
        break;
      case 'ERR_DH_KEY_NOT_INTEGER':
        $errstring = $lang->get('user_err_dh_key_not_numeric');
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
          $errstring .= $lang->get('err_invalid_credentials_lockout', array('fails' => $__login_status['lockout_fails']));
        }
        else if ( $__login_status['lockout_policy'] == 'captcha' )
        {
          $errstring .= $lang->get('user_err_invalid_credentials_lockout_captcha', array('fails' => $__login_status['lockout_fails']));
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
        $time_rem = ( intval(@$__login_status['lockout_last_time']) == time() ) ? $__login_status['lockout_duration'] : $__login_status['lockout_duration'] - round( ( $server_time - $__login_status['lockout_last_time'] ) / 60 );
        if ( $time_rem < 1 )
          $time_rem = $__login_status['lockout_duration'];
        
        $s = ( $time_rem == 1 ) ? '' : $lang->get('meta_plural');
        
        $captcha_string = ( $__login_status['lockout_policy'] == 'captcha' ) ? $lang->get('user_err_locked_out_captcha_blurb') : '';
        $errstring = $lang->get('user_err_locked_out', array('plural' => $s, 'captcha_blurb' => $captcha_string, 'time_rem' => $time_rem));
        
        break;
      default:
        $errstring = $lang->get($errstring);
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
         <?php
         $code = $plugins->setHook('login_form_html');
         foreach ( $code as $cmd )
         {
           eval($cmd);
         }
         if ( $level <= USER_LEVEL_MEMBER )
         {
           // "remember me" switch
           // first order of business is to determine what the checkbox should say
           $session_time = intval(getConfig('session_remember_time', '30'));
           if ( $session_time === 0 )
           {
             // sessions are infinite
             $text_remember = $lang->get('user_login_check_remember_infinite');
           }
           else
           {
             // is the number of days evenly divisible by 7? if so, use weeks
             if ( $session_time % 7 == 0 )
             {
               $session_time = $session_time / 7;
               $unit = 'week';
             }
             else
             {
               $unit = 'day';
             }
             // if it's not equal to 1, pluralize it
             if ( $session_time != 1 )
             {
               $unit .= 's';
             }
             $text_remember = $lang->get('user_login_check_remember', array(
                 'session_length' => $session_time,
                 'length_units' => $lang->get("etc_unit_$unit")
               ));
           }
           ?>
           <tr>
             <td class="row2">
               <?php echo $lang->get('user_login_field_remember'); ?>
             </td>
             <td class="row1" colspan="2">
               <label>
                 <input type="checkbox" name="remember" tabindex="3" />
                 <?php echo $text_remember; ?>
               </label>
             </td>
           </tr>
           <?php
         }
         if ( $level <= USER_LEVEL_MEMBER && ( !isset($_GET['use_crypt']) || ( isset($_GET['use_crypt']) && $_GET['use_crypt']!='0' ) ) )
         {
           echo '<tr>
             <td class="row3" colspan="3">';
             
           $returnpage_link = ( $return = $paths->getAllParams() ) ? '/' . $return : '';
           $nocrypt_link = makeUrlNS('Special', "Login$returnpage_link", "level=$level&use_crypt=0", true);
           echo '<p><b>' . $lang->get('user_login_nocrypt_title') . '</b> ' . $lang->get('user_login_nocrypt_body', array('nocrypt_link' => $nocrypt_link)) . '</p>';
           echo '<p>' . $lang->get('user_login_nocrypt_countrylist') . '</p>';
           
           echo '  </td>
           </tr>';
         }
         else if ( $level <= USER_LEVEL_MEMBER && ( isset($_GET['use_crypt']) && $_GET['use_crypt']=='0' ) )
         {
           echo '<tr>
             <td class="row3" colspan="3">';
             
           $returnpage_link = ( $return = $paths->getAllParams() ) ? '/' . $return : '';
           $usecrypt_link = makeUrlNS('Special', "Login$returnpage_link", "level=$level&use_crypt=1", true);
           echo '<p><b>' . $lang->get('user_login_usecrypt_title') . '</b> ' . $lang->get('user_login_usecrypt_body', array('usecrypt_link' => $usecrypt_link)) . '</p>';
           echo '<p>' . $lang->get('user_login_usecrypt_countrylist') . '</p>';
           
           echo '  </td>
           </tr>';
         }
         else if ( $level > USER_LEVEL_MEMBER && !strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') && $dh_supported )
         {
           echo '<tr>';
           echo '<td class="row3" colspan="3">';
           echo '<p>' . $lang->get('user_login_dh_notice') . '</p>';
           echo '</td>';
           echo '</tr>';
         }
         ?>
         
         <tr>
           <th colspan="3" style="text-align: center" class="subhead"><input type="submit" name="login" value="Log in" tabindex="<?php echo ( $level <= USER_LEVEL_MEMBER ) ? '4' : '2'; ?>" /></th>
         </tr>
      </table>
    </div>
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
      <?php
      echo $session->generate_aes_form();
      ?>
    </form>
    <?php
      echo $session->aes_javascript('loginform', 'pass');
    ?>
  <?php
  $template->footer();
}

function page_Special_Login_preloader() // adding _preloader to the end of the function name calls the function before $session and $paths setup routines are called
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $__login_status;
  global $lang;
  require_once( ENANO_ROOT . '/includes/math.php' );
  
  if ( $paths->getParam(0) === 'action.json' )
  {
    if ( !isset($_POST['r']) )
      die('No request.');
    
    $request = $_POST['r'];
    try
    {
      $request = enano_json_decode($request);
    }
    catch ( Exception $e )
    {
      die(enano_json_encode(array(
          'mode' => 'error',
          'error' => 'ERR_JSON_PARSE_FAILED'
        )));
    }
    
    echo enano_json_encode($session->process_login_request($request));
    
    $db->close();
    exit;
  }
  if ( isset($_GET['act']) && $_GET['act'] == 'ajaxlogin' )
  {
    echo 'This version of the Enano LoginAPI is deprecated. Please clear your browser\'s cache and try your login again. Developers, please use the action.json method instead.';
    return true;
  }
  if(isset($_POST['login']))
  {
    $captcha_hash = ( isset($_POST['captcha_hash']) ) ? $_POST['captcha_hash'] : false;
    $captcha_code = ( isset($_POST['captcha_code']) ) ? $_POST['captcha_code'] : false;
    
    try
    {
      $password = $session->get_aes_post('pass');
    }
    catch ( Exception $e )
    {
      $__login_status = array(
        'mode' => 'error',
        'error' => $e->getMessage()
      );
      return false;
    }
    
    // These are to allow auth plugins to work universally between JSON and HTML login forms
    $userinfo =& $_POST;
    $userinfo['password'] =& $password;
    $req = array(
      'level' => intval($_POST['auth_level']),
      'remember' => isset($_POST['remember'])
    );
    
    // At this point if any extra fields were injected into the login form, we need to let plugins process it
    
    /**
     * Called upon processing an incoming login request from the plain HTML login form.. If you added anything to the form,
     * that will be in the $userinfo array here and on $_POST. Expected return values are: true if your plugin has
     * not only succeeded but ALSO issued a session key (bypass the whole Enano builtin login process) and an associative array
     * with "mode" set to "error" and an error string in "error" to send an error back to the client. Any return value other
     * than these will be ignored.
     * @hook login_process_userdata_json
     */
     
    $skip_normal_login = false;
    
    $code = $plugins->setHook('login_process_userdata_json');
    foreach ( $code as $cmd )
    {
      $result = eval($cmd);
      if ( $result === true )
      {
        $skip_normal_login = true;
        $result = array('success' => true);
        break;
      }
      else if ( is_array($result) )
      {
        if ( isset($result['mode']) && $result['mode'] === 'error' && isset($result['error']) )
        {
          $__login_status = array(
            'mode' => 'error',
            'error' => $result['error']
          );
          return false;
        }
      }
    }
    
    if ( !$skip_normal_login )
    {
      $result = $session->login_without_crypto($_POST['username'], $password, false, intval($_POST['auth_level']), $captcha_hash, $captcha_code, isset($_POST['remember']));
    }
   
    if($result['success'])
    {
      $session->start();
      
      $template->load_theme($session->theme, $session->style);
      if(isset($_POST['return_to']))
      {
        $name = ( isPage($_POST['return_to']['name']) ) ? $paths->pages[$_POST['return_to']]['name'] : $_POST['return_to'];
        $subst = array(
            'username' => $session->username,
            'redir_target' => $name
          );
        redirect( makeUrl($_POST['return_to'], false, true), $lang->get('user_login_success_title'), $lang->get('user_login_success_body', $subst) );
      }
      else
      {
        $subst = array(
            'username' => $session->username,
            'redir_target' => $lang->get('user_login_success_body_mainpage')
          );
        redirect( makeUrl(get_main_page(), false, true), $lang->get('user_login_success_title'), $lang->get('user_login_success_body', $subst) );
      }
    }
    else
    {
      if ( $result['error'] === 'valid_reset' )
      {
        header('HTTP/1.1 302 Temporary Redirect');
        header('Location: ' . $result['redirect_url']);
        
        $db->close();
        exit();
      }
      $GLOBALS['__login_status'] = $result;
    }
  }
}

function SpecialLogin_SendResponse_PasswordReset($user_id, $passkey)
{
  $response = Array(
      'result' => 'success_reset',
      'user_id' => $user_id,
      'temppass' => $passkey
    );
  
  $response = enano_json_encode($response);
  echo $response;
  
  $db->close();
  exit;
}

function page_Special_Logout()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( !$session->user_logged_in )
    $paths->main_page();
  
  $token = $paths->getParam(0);
  if ( $token !== $session->csrf_token )
  {
    csrf_request_confirm();
  }
  
  $l = $session->logout();
  if ( $l == 'success' )
  {
    $url = makeUrl(get_main_page(), false, true);
    if ( $paths->getParam(1) )
    {
      $pi = explode('/', $paths->getAllParams());
      $pi = implode('/', array_values(array_slice($pi, 1)));
      list($pid, $ns) = RenderMan::strToPageID($pi);
      $perms = $session->fetch_page_acl($pid, $ns);
      if ( $perms->get_permissions('read') )
      {
        $url = makeUrl($pi, false, true);
      }
    }
    redirect($url, $lang->get('user_logout_success_title'), $lang->get('user_logout_success_body'), 3);
  }
  $template->header();
  echo '<h3>' . $lang->get('user_logout_err_title') . '</h3>';
  echo '<p>' . $l . '</p>';
  $template->footer();
}

function page_Special_Register()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( $session->user_level < USER_LEVEL_ADMIN && $session->user_logged_in )
  {
    $paths->main_page();
  }
  
  // form field trackers
  $username = '';
  $email = '';
  $realname = '';
  
  $terms = getConfig('register_tou');
  
  if(getConfig('account_activation') == 'disable' && ( ( $session->user_level >= USER_LEVEL_ADMIN && !isset($_GET['IWannaPlayToo']) ) || $session->user_level < USER_LEVEL_ADMIN || !$session->user_logged_in ))
  {
    $s = ($session->user_level >= USER_LEVEL_ADMIN) ? '<p>' . $lang->get('user_reg_err_disabled_body_adminblurb', array( 'reg_link' => makeUrl($paths->page, 'IWannaPlayToo&coppa=no', true) )) . '</p>' : '';
    die_friendly($lang->get('user_reg_err_disabled_title'), '<p>' . $lang->get('user_reg_err_disabled_body') . '</p>' . $s);
  }
  // are we locked out from logging in? if so, also lock out registration
  if ( getConfig('lockout_policy') === 'lockout' )
  {
    $ip = $db->escape($_SERVER['REMOTE_ADDR']);
    $threshold = time() - ( 60 * intval(getConfig('lockout_duration')) );
    $limit = intval(getConfig('lockout_threshold'));
    $q = $db->sql_query('SELECT * FROM ' . table_prefix . "lockout WHERE timestamp >= $threshold ORDER BY timestamp DESC;");
    if ( !$q )
      $db->_die();
    if ( $db->numrows() >= $limit )
    {
      $row = $db->fetchrow();
      $db->free_result();
      $time_rem = intval(getConfig('lockout_duration')) - round((time() - $row['timestamp']) / 60);
      die_friendly($lang->get('user_reg_err_disabled_title'), '<p>' . $lang->get('user_reg_err_locked_out', array('time' => $time_rem)) . '</p>');
    }
    $db->free_result();
  }
  if(isset($_POST['submit'])) 
  {
    $_GET['coppa'] = ( isset($_POST['coppa']) ) ? $_POST['coppa'] : 'x';
    
    $captcharesult = $session->get_captcha($_POST['captchahash']);
    $session->kill_captcha();
    // bypass captcha if logged in (at this point, if logged in, we're admin)
    if ( !$session->user_logged_in && strtolower($captcharesult) != strtolower($_POST['captchacode']) )
    {
      $s = $lang->get('user_reg_err_captcha');
    }
    else
    {
      if ( getConfig('enable_coppa') == '1' && ( !isset($_POST['coppa']) || ( isset($_POST['coppa']) && !in_array($_POST['coppa'], array('yes', 'no')) ) ) )
      {
        $s = 'Invalid COPPA input';
      }
      else if ( !$session->user_logged_in && !empty($terms) && !isset($_POST['tou_agreed']) )
      {
        $s = $lang->get('user_reg_err_accept_tou');
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
          $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
          $crypt_key = $session->fetch_public_key($_POST['crypt_key']);
          if ( !$crypt_key )
          {
            $s = $lang->get('user_reg_err_missing_key');
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
        
        $error =& $s;
        
        /**
         * Validation of POST data coming from registration. Put an error message in the variable $error to stop registration.
         * @hook ucp_register_validate
         */
        
        $code = $plugins->setHook('ucp_register_validate');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        
        // All things verified, create account
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
          $str = $lang->get('user_reg_msg_success_activ_none', array('login_link' => makeUrlNS('Special', 'Login', false, true)));
          break;
        case "user":
          $str = $lang->get('user_reg_msg_success_activ_user');
          break;
        case "admin":
          $str = $lang->get('user_reg_msg_success_activ_admin');
          break;
      }
      die_friendly($lang->get('user_reg_msg_success_title'), '<p>' . $lang->get('user_reg_msg_success_body') . ' ' . $str . '</p>');
    }
    else if ( $s == 'success' && $coppa )
    {
      $str = $lang->get('user_reg_msg_success_activ_coppa');
      die_friendly($lang->get('user_reg_msg_success_title'), '<p>' . $lang->get('user_reg_msg_success_body') . ' ' . $str . '</p>');
    }
    $username = htmlspecialchars($_POST['username']);
    $email    = htmlspecialchars($_POST['email']);
    $realname = htmlspecialchars($_POST['real_name']);
  }
  $template->header();
  echo $lang->get('user_reg_msg_greatercontrol');
  
  if ( getConfig('enable_coppa') != '1' || ( isset($_GET['coppa']) && in_array($_GET['coppa'], array('yes', 'no')) ) )
  {
    $coppa = ( isset($_GET['coppa']) && $_GET['coppa'] == 'yes' );
    $session->kill_captcha();
    $captchacode = $session->make_captcha();
    
    $pubkey = $session->rijndael_genkey();
    $challenge = $session->dss_rand();
    
    ?>
      <h3><?php echo $lang->get('user_reg_msg_table_title'); ?></h3>
      <form name="regform" action="<?php echo makeUrl($paths->page); ?>" method="post" onsubmit="return runEncryption();">
        <div class="tblholder">
          <table border="0" width="100%" cellspacing="1" cellpadding="4">
            <tr><th colspan="3"><?php echo $lang->get('user_reg_msg_table_subtitle'); ?></th></tr>
            
            <?php if(isset($_POST['submit'])) echo '<tr><td colspan="3" class="row2" style="color: red;">'.$s.'</td></tr>'; ?>
            
            <!-- FIELD: Username -->
            <tr>
              <td class="row1" style="width: 50%;">
                <?php echo $lang->get('user_reg_lbl_field_username'); ?>
                <span id="e_username"></span>
              </td>
              <td class="row1" style="width: 50%;">
                <input tabindex="1" type="text" name="username" size="30" value="<?php echo $username; ?>" onkeyup="namegood = false; validateForm(this);" onblur="checkUsername();" />
              </td>
              <td class="row1" style="width: 1px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/checkbad.png" id="s_username" />
              </td>
            </tr>
            
            <!-- FIELD: Password -->
            <tr>
              <td class="row3" style="width: 50%;" rowspan="<?php echo ( getConfig('pw_strength_enable') == '1' ) ? '3' : '2'; ?>">
                <?php echo $lang->get('user_reg_lbl_field_password'); ?>
                <span id="e_password"></span>
                <?php if ( getConfig('pw_strength_enable') == '1' && getConfig('pw_strength_minimum') > -10 ): ?>
                <small><?php echo $lang->get('user_reg_msg_password_score'); ?></small>
                <?php endif; ?>
              </td>
              <td class="row3" style="width: 50%;">
                <input tabindex="2" type="password" name="password" size="15" onkeyup="<?php if ( getConfig('pw_strength_enable') == '1' ): ?>password_score_field(this); <?php endif; ?>validateForm(this);" /><?php if ( getConfig('pw_strength_enable') == '1' ): ?><span class="password-checker" style="font-weight: bold; color: #aaaaaa;"> Loading...</span><?php endif; ?>
              </td>
              <td rowspan="<?php echo ( getConfig('pw_strength_enable') == '1' ) ? '3' : '2'; ?>" class="row3" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/checkbad.png" id="s_password" />
              </td>
            </tr>
            
            <!-- FIELD: Password confirmation -->
            <tr>
              <td class="row3" style="width: 50%;">
                <input tabindex="3" type="password" name="password_confirm" size="15" onkeyup="validateForm(this);" /> <small><?php echo $lang->get('user_reg_lbl_field_password_confirm'); ?></small>
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
                  if ( $coppa )
                  {
                    echo $lang->get('user_reg_lbl_field_email_coppa');
                  }
                  else
                  {
                    echo $lang->get('user_reg_lbl_field_email');
                  }
                ?>
                <?php
                  if ( ( $x = getConfig('account_activation') ) == 'user' )
                  {
                    echo '<br /><small>' . $lang->get('user_reg_msg_email_activuser') . '</small>';
                  }
                ?>
              </td>
              <td class="row1" style="width: 50%;">
                <input tabindex="4" type="text" name="email" size="30" value="<?php echo $email; ?>" onkeyup="validateForm(this);" />
              </td>
              <td class="row1" style="max-width: 24px;">
                <img alt="Good/bad icon" src="<?php echo scriptPath; ?>/images/checkbad.png" id="s_email" />
              </td>
            </tr>
            
            <!-- FIELD: Real name -->
            <tr>
              <td class="row3" style="width: 50%;">
                <?php echo $lang->get('user_reg_lbl_field_realname'); ?><br />
                <small><?php echo $lang->get('user_reg_msg_realname_optional'); ?></small>
              </td>
              <td class="row3" style="width: 50%;">
                <input tabindex="5" type="text" name="real_name" size="30" value="<?php echo $realname; ?>" />
              </td>
              <td class="row3" style="max-width: 24px;">
              </td>
            </tr>
            
            <?php
            /**
             * Allows adding fields to the user registration form. Form is built with Enano tables, 3 columns. (Rightmost can be left empty or if you're using Javascript validation an image you can update with your own Javascript code)
             * @hook ucp_register_form
             */
            
            $code = $plugins->setHook('ucp_register_form');
            foreach ( $code as $cmd )
            {
              eval($cmd);
            }
            ?>
            
            <!-- FIELD: CAPTCHA image -->
            <?php
            if ( !$session->user_logged_in ):
            ?>
            <tr>
              <td class="row1" style="width: 50%;" rowspan="2">
                <?php echo $lang->get('user_reg_lbl_field_captcha'); ?><br />
                <small>
                  <?php echo $lang->get('user_reg_msg_captcha_pleaseenter', array('regen_flags' => 'href="#" onclick="regenCaptcha(); return false;"')); ?><br />
                  <br />
                  <?php echo $lang->get('user_reg_msg_captcha_blind'); ?>
                </small>
              </td>
              <td class="row1">
                <img id="captchaimg" alt="CAPTCHA image" src="<?php echo makeUrlNS('Special', 'Captcha/'.$captchacode); ?>" /><br />
                <span id="b_username"></span>
              </td>
              <td class="row1">
              </td>
            </tr>
            
            <!-- FIELD: CAPTCHA input field -->
            <tr>
              <td class="row1" colspan="2">
                <?php echo $lang->get('user_reg_lbl_field_captcha_code'); ?>
                <input tabindex="6" name="captchacode" type="text" size="10" />
                <input type="hidden" name="captchahash" value="<?php echo $captchacode; ?>" />
              </td>
            </tr>
            
            <!-- FIELD: TOU -->
            
            <?php
            if ( !empty($terms) ):
            ?>
            
            <tr>
              <td class="row1" colspan="3">
                <?php
                echo $lang->get('user_reg_msg_please_read_tou');
                ?>
              </td>
            </tr>
            
            <tr>
              <td class="row3" colspan="3">
                <div style="border: 1px solid #000000; height: 75px; width: 60%; clip: rect(0px,auto,auto,0px); overflow: auto; background-color: #FFF; margin: 0 auto; padding: 4px;">
                  <?php
                  echo RenderMan::render($terms);
                  ?>
                </div>
                <p style="text-align: center;">
                  <label>
                    <input tabindex="7" type="checkbox" name="tou_agreed" />
                    <b><?php echo $lang->get('user_reg_lbl_field_tou'); ?></b>
                  </label>
                </p>
              </td>
            </tr>
            
            <?php
            endif; // !empty($terms)
            endif; // $session->user_logged_in
            ?>
            
            <!-- FIELD: submit button -->
            <tr>
              <th class="subhead" colspan="3" style="text-align: center;">
                <input tabindex="8" type="submit" name="submit" value="<?php echo $lang->get('user_reg_btn_create_account'); ?>" />
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
        function runEncryption()
        {
          var frm = document.forms.regform;
          if ( frm.password.value.length < 1 )
            return true;
          pass1 = frm.password.value;
          pass2 = frm.password_confirm.value;
          if ( pass1 != pass2 )
          {
            alert($lang.get('user_reg_err_alert_password_nomatch'));
            return false;
          }
          if ( pass1.length < 6 && pass1.length > 0 )
          {
            alert($lang.get('user_reg_err_alert_password_tooshort'));
            return false;
          }
          if(aes_self_test())
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
          function validateForm(field)
          {
            if ( typeof(field) != 'object' )
            {
              field = {
                name: '_nil',
                value: '_nil'
              }
            }
            // wait until $lang is initted
            if ( typeof($lang) != 'object' )
            {
              setTimeout('validateForm();', 200);
              return false;
            }
            var frm = document.forms.regform;
            failed = false;
            
            // Username
            if(!namegood && ( field.name == 'username' || field.name == '_nil' ) ) 
            {
              //if(frm.username.value.match(/^([A-z0-9 \!@\-\(\)]+){2,}$/ig))
              var regex = new RegExp('^([^<>&\?]+){2,}$', 'ig');
              if ( frm.username.value.match(regex) )
              {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/checkunk.png';
                document.getElementById('e_username').innerHTML = '&nbsp;';
              } else {
                failed = true;
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/checkbad.png';
                document.getElementById('e_username').innerHTML = '<br /><small>' + $lang.get('user_reg_err_username_invalid') + '</small>';
              }
            }
            if ( document.getElementById('b_username') )
            {
              document.getElementById('b_username').innerHTML = '';
              if(hex_md5(frm.real_name.value) == '5a397df72678128cf0e8147a2befd5f1')
              {
                document.getElementById('b_username').innerHTML = '<br /><br />Hey...I know you!<br /><img alt="" src="http://upload.wikimedia.org/wikipedia/commons/thumb/7/7f/Bill_Gates_2004_cr.jpg/220px-Bill_Gates_2004_cr.jpg" />';
              }
            }
            
            // Password
            if ( field.name == 'password' || field.name == 'password_confirm' || field.name == '_nil' )
            {
              if(frm.password.value.match(/^(.+){6,}$/ig) && frm.password_confirm.value.match(/^(.+){6,}$/ig) && frm.password.value == frm.password_confirm.value )
              {
                document.getElementById('s_password').src='<?php echo scriptPath; ?>/images/check.png';
                document.getElementById('e_password').innerHTML = '<br /><small>' + $lang.get('user_reg_err_password_good') + '</small>';
              } else {
                failed = true;
                if(frm.password.value.length < 6)
                {
                  document.getElementById('e_password').innerHTML = '<br /><small>' + $lang.get('user_reg_msg_password_length') + '</small>';
                }
                else if(frm.password.value != frm.password_confirm.value)
                {
                  document.getElementById('e_password').innerHTML = '<br /><small>' + $lang.get('user_reg_msg_password_needmatch') + '</small>';
                }
                else
                {
                  document.getElementById('e_password').innerHTML = '';
                }
                document.getElementById('s_password').src='<?php echo scriptPath; ?>/images/checkbad.png';
              }
            }
            
            // E-mail address
            
            // workaround for idiot jEdit bug
            if ( validateEmail(frm.email.value) && ( field.name == 'email' || field.name == '_nil' ) )
            {
              document.getElementById('s_email').src='<?php echo scriptPath; ?>/images/check.png';
            } else {
              failed = true;
              document.getElementById('s_email').src='<?php echo scriptPath; ?>/images/checkbad.png';
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
              var regex = new RegExp('^([^<>&\?]+){2,}$', 'ig');
              if ( frm.username.value.match(regex) )
              {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/checkunk.png';
                document.getElementById('e_username').innerHTML = '&nbsp;';
              } else {
                document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/checkbad.png';
                document.getElementById('e_username').innerHTML = '<br /><small>' + $lang.get('user_reg_err_username_invalid') + '</small>';
                return false;
              }
            }
            
            document.getElementById('e_username').innerHTML = '<br /><small><b>' + $lang.get('user_reg_msg_username_checking') + '</b></small>';
            ajaxGet('<?php echo scriptPath; ?>/ajax.php?title=null&_mode=checkusername&name='+escape(frm.username.value), function() {
              if ( ajax.readyState == 4 && ajax.status == 200 )
                if(ajax.responseText == 'good')
                {
                  document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/check.png';
                  document.getElementById('e_username').innerHTML = '<br /><small><b>' + $lang.get('user_reg_msg_username_available') + '</b></small>';
                  namegood = true;
                } else if(ajax.responseText == 'bad') {
                  document.getElementById('s_username').src='<?php echo scriptPath; ?>/images/checkbad.png';
                  document.getElementById('e_username').innerHTML = '<br /><small><b>' + $lang.get('user_reg_msg_username_unavailable') + '</b></small>';
                  namegood = false;
                } else {
                  document.getElementById('e_username').innerHTML = ajax.responseText;
                }
            });
          }
          function regenCaptcha()
          {
            var frm = document.forms.regform;
            document.getElementById('captchaimg').src = '<?php echo makeUrlNS("Special", "Captcha/$captchacode"); ?>/'+Math.floor(Math.random() * 100000);
            return false;
          }
          addOnloadHook(function()
            {
              <?php if ( getConfig('pw_strength_enable') == '1' ): ?>
              var frm = document.forms.regform;
              load_component('pwstrength');
              password_score_field(frm.password);
              <?php endif; ?>
              load_component('crypto');
              validateForm();
              setTimeout('checkUsername();', 1000);
            });
          // ]]>
        </script>
      </enano:no-opt>
    <?php
  }
  else
  {
    $year = intval( enano_date('Y') );
    $year = $year - 13;
    $month = enano_date('F');
    $day = enano_date('d');
    
    $yo13_date = "$month $day, $year";
    $link_coppa_yes = makeUrlNS('Special', 'Register', 'coppa=yes', true);
    $link_coppa_no  = makeUrlNS('Special', 'Register', 'coppa=no',  true);
    
    // COPPA enabled, ask age
    echo '<div class="tblholder">';
    echo '<table border="0" cellspacing="1" cellpadding="4">';
    echo '<tr>
            <td class="row1">
              ' . $lang->get('user_reg_coppa_title') . '
            </td>
          </tr>
          <tr>
            <td class="row3">
              <a href="' . $link_coppa_no  . '">' . $lang->get('user_reg_coppa_link_atleast13', array( 'yo13_date' => $yo13_date )) . '</a><br />
              <a href="' . $link_coppa_yes . '">' . $lang->get('user_reg_coppa_link_not13', array( 'yo13_date' => $yo13_date )) . '</a>
            </td>
          </tr>';
    echo '</table>';
    echo '</div>';
  }
  $template->footer();
}

function page_Special_Contributions() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  // This is a vast improvement over the old Special:Contributions in 1.0.x.
  
  $template->header();
  $user = $paths->getParam();
  if ( !$user && isset($_GET['user']) )
  {
    $user = $_GET['user'];
  }
  else if ( !$user && !isset($_GET['user']) )
  {
    echo '<p>' . $lang->get('userfuncs_contribs_err_no_user') . '</p>';
    $template->footer();
    return;
  }
  
  $user = $db->escape($user);
  $q = 'SELECT log_type, time_id, action, date_string, page_id, namespace, author, edit_summary, minor_edit, page_id, namespace, ( action = \'edit\' ) AS is_edit FROM '.table_prefix.'logs WHERE author=\''.$user.'\' AND log_type=\'page\' AND is_draft != 1 ORDER BY is_edit DESC, time_id DESC;';
  $q = $db->sql_query($q);
  if ( !$q )
    $db->_die('SpecialUserFuncs selecting contribution data');
  
  echo '<h3>' . $lang->get('userfuncs_contribs_heading_edits') . '</h3>';
  
  $cnt_edits = 0;
  $cnt_other = 0;
  $current = 'cnt_edits';
  $cls = 'row2';
  
  while ( $row = $db->fetchrow($q) )
  {
    if ( $current == 'cnt_edits' && $row['is_edit'] != 1 )
    {
      // No longer processing page edits - split the table
      if ( $cnt_edits == 0 )
      {
        echo '<p>' . $lang->get('userfuncs_contribs_msg_no_edits') . '</p>';
      }
      else
      {
        echo '</table></div>';
        echo '<h3>' . $lang->get('userfuncs_contribs_heading_other') . '</h3>';
      }
      $current = 'cnt_other';
      $cls = 'row2';
    }
    if ( $$current == 0 )
    {
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4">';
      echo '  <tr>
                <th>' . $lang->get('history_col_datetime') . '</th>';
      echo '    <th>' . $lang->get('history_col_page') . '</th>';
      if ( $current == 'cnt_edits' )
      {
        echo '  <th>' . $lang->get('history_col_summary') . '</th>';
      }
      echo '    <th>' . $lang->get('history_col_minor') . '</th>';
      if ( $current == 'cnt_other' )
      {
        echo '  <th>' . $lang->get('history_col_action_taken') . '</th>
                <th>' . $lang->get('history_col_extra') . '</th>
             ';
      }
      echo '    <th>' . $lang->get('history_col_actions') . '</th>
              </tr>';
    }
    $$current++;
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    
    echo '<tr>';
    
    // date & time
    echo '  <td class="' . $cls . '">' . enano_date('d M Y h:i a', $row['time_id']) . '</td>';
    
    // page & link to said page
    echo '  <td class="' . $cls . '"><a href="' . makeUrlNS($row['namespace'], $row['page_id']) . '">' . get_page_title_ns($row['page_id'], $row['namespace']) . '</a></td>';
    
    switch ( $row['action'] )
    {
      case 'edit':
        if ( $row['edit_summary'] == 'Automatic backup created when logs were purged' )
        {
          $row['edit_summary'] = $lang->get('history_summary_clearlogs');
        }
        else if ( empty($row['edit_summary']) )
        {
          $row['edit_summary'] = '<span style="color: #808080">' . $lang->get('history_summary_none_given') . '</span>';
        }
        echo '  <td class="' . $cls . '">' . $row['edit_summary'] . '</td>';
        if ( $row['minor_edit'] == 1 )
        {
          echo '<td class="' . $cls . '"><b>M</b></td>';
        }
        else
        {
          echo '<td class="' . $cls . '"></td>';
        }
        break;
      case 'prot':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_protect') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $row['edit_summary'] . '</td>';
        break;
      case 'unprot':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_unprotect') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $row['edit_summary'] . '</td>';
        break;
      case 'semiprot':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_semiprotect') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $row['edit_summary'] . '</td>';
        break;
      case 'rename':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_rename') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_oldtitle') . ' ' . htmlspecialchars($row['edit_summary']) . '</td>';
        break;
      case 'create':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_create') . '</td>';
        echo '  <td class="' . $cls . '"></td>';
        break;
      case 'delete':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_delete') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $row['edit_summary'] . '</td>';
        break;
      case 'reupload':
        echo '  <td class="' . $cls . '"></td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_log_uploadnew') . '</td>';
        echo '  <td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $row['edit_summary'] . '</td>';
        break;
    }
    
    // actions column
    echo '    <td class="' . $cls . '" style="text-align: center;">';
    if ( $row['is_edit'] == 1 )
    {
      echo '    <a href="' . makeUrlNS($row['namespace'], $row['page_id'], "oldid={$row['time_id']}", true) . '">' . $lang->get('history_action_view') . '</a> | ';
      echo '      <a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=rollback&id={$row['time_id']}", true) . '">' . $lang->get('history_action_restore') . '</a>';
    }
    else
    {
      echo '      <a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=rollback&id={$row['time_id']}", true) . '">' . $lang->get('history_action_revert') . '</a>';
    }
    echo '    </td>';
    
    if ( $current == 'cnt_other' && $cnt_edits + $cnt_other >= $db->numrows($q) )
    {
      echo '</table></div>';
    }
  }
  
  if ( $current == 'cnt_edits' )
  {
    // no "other" edits, close the table
    if ( $cnt_edits > 0 )
      echo '</table></div>';
    else
      echo '<p>' . $lang->get('userfuncs_contribs_msg_no_edits') . '</p>';
    echo '<h3>' . $lang->get('userfuncs_contribs_heading_other') . '</h3>';
    echo '<p>' . $lang->get('userfuncs_contribs_msg_no_other') . '</p>';
  }
  
  $db->free_result();
  $template->footer();
}

function page_Special_ChangeStyle()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( !$session->user_logged_in )
  {
    die_friendly('Access denied', '<p>You must be logged in to change your style. Spoofer.</p>');
  }
  if(isset($_POST['theme']) && isset($_POST['style']) && isset($_POST['return_to']))
  {
    if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['theme']) )
      die('Hacking attempt');
    if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['style']) )
      die('Hacking attempt');
    $d = ENANO_ROOT . '/themes/' . $_POST['theme'];
    $f = ENANO_ROOT . '/themes/' . $_POST['theme'] . '/css/' . $_POST['style'] . '.css';
    if ( !file_exists($d) || !is_dir($d) )
    {
      die('The directory "'.$d.'" does not exist.');
    }
    if ( !file_exists($f) )
    {
      die('The file "'.$f.'" does not exist.');
    }
    $d = $db->escape($_POST['theme']);
    $f = $db->escape($_POST['style']);
    $q = 'UPDATE '.table_prefix.'users SET theme=\''.$d.'\',style=\''.$f.'\' WHERE username=\''.$session->username.'\'';
    if ( !$db->sql_query($q) )
    {
      $db->_die('Your theme/style preferences were not updated.');
    }
    else
    {
      redirect(makeUrl($_POST['return_to']), $lang->get('userfuncs_changetheme_success_title'), $lang->get('userfuncs_changetheme_success_body'), 3);
    }
  }
  else
  {
    $template->header();
      $ret = ( isset($_POST['return_to']) ) ? $_POST['return_to'] : $paths->getParam(0);
      if ( !$ret )
      {
        $ret = get_main_page();
      }
      ?>
        <form action="<?php echo makeUrl($paths->page); ?>" method="post">
          <?php if ( !isset($_POST['themeselected']) ) { ?>
            <h3><?php echo $lang->get('userfuncs_changetheme_heading_theme'); ?></h3>
            <p>
              <select name="theme">
               <?php
                foreach ( $template->theme_list as $t )
                {
                  if ( $t['enabled'] )
                  {
                    echo '<option value="'.$t['theme_id'].'"';
                    if ( $t['theme_id'] == $session->theme )
                    {
                      echo ' selected="selected"';
                    }
                    echo '>' . $t['theme_name'] . '</option>';
                  }
                }
               ?>
              </select>
            </p>
            <p><input type="hidden" name="return_to" value="<?php echo $ret; ?>" />
               <input type="submit" name="themeselected" value="<?php echo $lang->get('userfuncs_changetheme_btn_continue'); ?>" /></p>
          <?php } else { 
            $theme = $_POST['theme'];
            if ( !preg_match('/^([0-9A-z_-]+)$/i', $theme ) )
              die('Hacking attempt');
            ?>
            <h3><?php echo $lang->get('userfuncs_changetheme_heading_style'); ?></h3>
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
               <input type="submit" name="allclear" value="<?php echo $lang->get('userfuncs_changetheme_btn_allclear'); ?>" /></p>
          <?php } ?>
        </form>
      <?php
    $template->footer();
  }
}

function page_Special_ActivateAccount()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $user = $paths->getParam(0);
  if ( !$user )
  {
    die_friendly($lang->get('userfuncs_activate_err_badlink_title'), '<p>' . $lang->get('userfuncs_activate_err_badlink_body') . '</p>');
  }
  $key = $paths->getParam(1);
  if ( !$key )
  {
    die_friendly($lang->get('userfuncs_activate_err_badlink_title'), '<p>' . $lang->get('userfuncs_activate_err_badlink_body') . '</p>');
  }
  $s = $session->activate_account(str_replace('_', ' ', $user), $key);
  if ( $s > 0 )
  {
    die_friendly($lang->get('userfuncs_activate_success_title'), '<p>' . $lang->get('userfuncs_activate_success_body') . '</p>');
  }
  else
  {
    die_friendly($lang->get('userfuncs_activate_err_badlink_title'), '<p>' . $lang->get('userfuncs_activate_err_bad_key') . '</p>');
  }
}

function page_Special_Captcha()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $paths->getParam(0) == 'make' )
  {
    $session->kill_captcha();
    echo $session->make_captcha();
    return;
  }
  
  $hash = $paths->getParam(0);
  if ( !$hash || !preg_match('#^([0-9a-f]*){32,40}$#i', $hash) )
  {
    $paths->main_page();
  }

  $session->make_captcha(7, $hash);  
  $code = $session->generate_captcha_code();
  $q = $db->sql_query('UPDATE ' . table_prefix . "captcha SET code = '$code' WHERE session_id = '$hash';");
  if ( !$q )
    $db->_die();
  
  require ( ENANO_ROOT.'/includes/captcha.php' );
  $captcha = captcha_object($hash, 'freecap');
  // $captcha->debug = true;
  $captcha->make_image();
  
  exit;
}

function page_Special_PasswordReset()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $template->header();
  if($paths->getParam(0) == 'stage2')
  {
    require_once(ENANO_ROOT . '/includes/math.php');
    require_once(ENANO_ROOT . '/includes/diffiehellman.php');
    
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
    
    $q = $db->sql_query('SELECT username,temp_password_time,temp_password,password_salt FROM '.table_prefix.'users WHERE user_id='.$user_id.';');
    if($db->numrows() < 1)
    {
      echo '<p>Invalid credentials</p>';
      $template->footer();
      return false;
    }
    $row = $db->fetchrow();
    $db->free_result();
    
    $temp_pass = $session->pk_decrypt($encpass);
    $temp_hmac = hmac_sha1($temp_pass, $row['password_salt']);
    
    if ( $temp_hmac !== $row['temp_password'] )
    {
      echo '<p>Invalid credentials</p>';
      $template->footer();
      return false;
    }
    
    if ( ( intval($row['temp_password_time']) + ( 3600 * 24 ) ) < time() )
    {
      echo '<p>' . $lang->get('userfuncs_passreset_err_pass_expired', array('reset_url' => makeUrlNS('Special', 'PasswordReset'))) . '</p>';
      $template->footer();
      return false;
    }
    
    if ( isset($_POST['do_stage2']) )
    {
      $data = $session->get_aes_post('pass');
      
      if(empty($data))
      {
        echo 'ERROR: Sanity check failed!';
        $template->footer();
        return false;
      }
      if ( strlen($data) < 6 )
      {
        echo '<p>' . $lang->get('userfuncs_passreset_err_too_short') . '</p>';
        $template->footer();
        return false;
      }
      if ( $_POST['use_crypt'] == 'no' )
      {
        if ( $_POST['pass'] !== $_POST['pass_confirm'] )
        {
          echo '<p>' . $lang->get('userfuncs_passreset_err_no_match') . '</p>';
          $template->footer();
          return false;
        }
      }
      if ( getConfig('pw_strength_enable') == '1' )
      {
        $min_score = intval(getConfig('pw_strength_minimum'));
        $inp_score = password_score($data);
        if ( $inp_score < $min_score )
        {
          $url = makeUrl($paths->fullpage);
          echo "<p>" . $lang->get('userfuncs_passreset_err_failed_score', array('inp_score' => $inp_score, 'url' => $url)) . "</p>";
          $template->footer();
          return false;
        }
      }
      
      $session->set_password($user_id, $data);
      
      $q = $db->sql_query('UPDATE '.table_prefix.'users SET temp_password=\'\',temp_password_time=0 WHERE user_id = '.$user_id.';');
      
      if($q)
      {
        $session->login_without_crypto($row['username'], $data);
        echo '<p>' . $lang->get('userfuncs_passreset_stage2_success', array('url_mainpage' => makeUrl(get_main_page()))) . '</p>';
      }
      else
      {
        echo $db->get_error();
      }
      
      $template->footer();
      return false;
    }
    
    // Password reset form
    $evt_get_score = ( getConfig('pw_strength_enable') == '1' ) ? 'onkeyup="password_score_field(this);" ' : '';
    $pw_meter =      ( getConfig('pw_strength_enable') == '1' ) ? '<tr><td class="row1">' . $lang->get('userfuncs_passreset_stage2_lbl_strength') . '</td><td class="row1"><div id="pwmeter"></div></td></tr>' : '';
    $pw_blurb =      ( getConfig('pw_strength_enable') == '1' && intval(getConfig('pw_strength_minimum')) > -10 ) ? '<br /><small>' . $lang->get('userfuncs_passreset_stage2_blurb_strength') . '</small>' : '';
    
    ?>
    <form action="<?php echo makeUrl($paths->fullpage); ?>" method="post" name="resetform" onsubmit="return runEncryption();">
      <br />
      <div class="tblholder">
        <table border="0" style="width: 100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2"><?php echo $lang->get('userfuncs_passreset_stage2_th'); ?></th></tr>
          <tr><td class="row1"><?php echo $lang->get('userfuncs_passreset_stage2_lbl_password'); ?> <?php echo $pw_blurb; ?></td><td class="row1"><input name="pass" type="password" <?php echo $evt_get_score; ?>/></td></tr>
          <tr><td class="row2"><?php echo $lang->get('userfuncs_passreset_stage2_lbl_confirm'); ?> </td><td class="row2"><input name="pass_confirm" type="password" /></td></tr>
          <?php echo $pw_meter; ?>
          <tr>
            <td colspan="2" class="row3" style="text-align: center;">
              
              <input type="submit" name="do_stage2" value="<?php echo $lang->get('userfuncs_passreset_stage2_btn_submit'); ?>" />
            </td>
          </tr>
        </table>
      </div>
      <?php echo $session->generate_aes_form(); ?>
    </form>
    <script type="text/javascript">
    addOnloadHook(function()
      {
        load_component('pwstrength');
        password_score_field(document.forms.resetform.pass);
      });
    </script>
    <?php
    echo $session->aes_javascript('resetform', 'pass', 'use_crypt', 'crypt_key', 'crypt_data', 'challenge_data', 'dh_supported', 'dh_public_key', 'dh_client_public_key');
    $template->footer();
    return true;
  }
  if ( $session->user_logged_in )
  {
    $paths->main_page();
  }
  
  if(isset($_POST['do_reset']))
  {
    if($session->mail_password_reset($_POST['username']))
    {
      echo '<p>' . $lang->get('userfuncs_passreset_stage1_success') . '</p>';
    }
    else
    {
      echo '<p>' . $lang->get('userfuncs_passreset_stage1_error') . '</p>';
    }
    $template->footer();
    return true;
  }
  echo '<p>' . $lang->get('userfuncs_passreset_blurb_line1') . '</p>
        <p>' . $lang->get('userfuncs_passreset_blurb_line2') . '</p>
        <form action="'.makeUrl($paths->page).'" method="post" onsubmit="if(!submitAuthorized) return false;">
          <p>' . $lang->get('userfuncs_passreset_lbl_username') . '  '.$template->username_field('username').'</p>
          <p><input type="submit" name="do_reset" value="' . $lang->get('userfuncs_passreset_btn_mailpasswd') . '" /></p>
        </form>';
  $template->footer();
}

function page_Special_Memberlist()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
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
    $username_where = ENANO_SQLFUNC_LOWERCASE . '(u.username) LIKE \'%' . strtolower($finduser) . '%\'';
    $finduser_url = 'finduser=' . rawurlencode($_GET['finduser']) . '&';
  }
  else
  {
    if ( ENANO_DBLAYER == 'MYSQL' )
      $username_where = 'lcase(u.username) REGEXP lcase("^' . $startletter_sql . '")';
    else if ( ENANO_DBLAYER == 'PGSQL' )
      $username_where = 'lower(u.username) ~ lower(\'^' . $startletter_sql . '\')';
    $finduser_url = '';
  }
  
  // Column markers
  $headings = '<tr>
                 <th style="max-width: 50px;">
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=uid&orderby=' . $sortorders['uid'], true) . '">#</a>
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=username&orderby=' . $sortorders['username'], true) . '">' . $lang->get('userfuncs_ml_column_username') . '</a>
                 </th>
                 <th>
                   ' . $lang->get('userfuncs_ml_column_userlevel') . '
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=email&orderby=' . $sortorders['email'], true) . '">' . $lang->get('userfuncs_ml_column_email') . '</a>
                 </th>
                 <th>
                   <a href="' . makeUrlNS('Special', 'Memberlist', $finduser_url . 'letter=' . $startletter . '&sort=regist&orderby=' . $sortorders['regist'], true) . '">' . $lang->get('userfuncs_ml_column_regtime') . '</a>
                 </th>
               </tr>';
               
  // determine number of rows
  $q = $db->sql_query('SELECT u.user_id FROM '.table_prefix.'users AS u WHERE ' . $username_where . ' AND u.username != \'Anonymous\';');
  if ( !$q )
    $db->_die();
  
  $num_rows = $db->numrows();
  $db->free_result();
  
  if ( !empty($finduser_url) )
  {
    switch ( $num_rows )
    {
      case 0:
        $str = $lang->get('userfuncs_ml_msg_matches_zero'); break;
      case 1:
        $str = $lang->get('userfuncs_ml_msg_matches_one'); break;
      default:
        $str = $lang->get('userfuncs_ml_msg_matches', array('matches' => $num_rows)); break;
    }
    echo "<h3>$str</h3>";
  }
  
  // main selector
  $pgsql_additional_group_by = ( ENANO_DBLAYER == 'PGSQL' ) ? ', u.username, u.reg_time, u.email, u.user_level, u.user_has_avatar, u.avatar_type, x.email_public' : '';
  $q = $db->sql_unbuffered_query('SELECT \'\' AS infobit, u.user_id, u.username, u.reg_time, u.email, u.user_level, u.user_has_avatar, u.avatar_type, x.email_public, COUNT(c.comment_id) AS num_comments FROM '.table_prefix.'users AS u
                                    LEFT JOIN '.table_prefix.'users_extra AS x
                                      ON ( u.user_id = x.user_id )
                                    LEFT JOIN ' . table_prefix . 'comments AS c
                                      ON ( u.user_id = c.user_id )
                                    WHERE ' . $username_where . ' AND u.username != \'Anonymous\'
                                    GROUP BY u.user_id' . $pgsql_additional_group_by . '
                                    ORDER BY ' . $sort_sqllet . ' ' . $target_order . ';');
  if ( !$q )
    $db->_die();
  
  // formatter parameters
  $formatter = new MemberlistFormatter();
  $formatters = array(
    'username' => array($formatter, 'username'),
    'user_level' => array($formatter, 'user_level'),
    'email' => array($formatter, 'email'),
    'reg_time' => array($formatter, 'reg_time'),
    'infobit' => array($formatter, 'infobit')
    );
  
  $html = paginate(
            $q,                                                                                                       // MySQL result resource
            '<tr>
               <td class="{_css_class}">{user_id}</td>
               <td class="{_css_class}" style="text-align: left;">{username}</td>
               <td class="{_css_class}">{user_level}</td>
               <td class="{_css_class}">{email}</small></td>
               <td class="{_css_class}">{reg_time}</td>
             </tr>
             <tr>
               <td colspan="5" class="row3" style="text-align: left;">
                 <div id="ml_moreinfo_{user_id}" style="display: none;">
                   {infobit}
                 </div>
               </td>
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
               . ( urlSeparator == '&' ? '<input type="hidden" name="title" value="' . htmlspecialchars( $paths->page ) . '" />' : '' )
               . ( $session->sid_super ? '<input type="hidden" name="auth"  value="' . $session->sid_super . '" />' : '')
               . '<p>' . $lang->get('userfuncs_ml_lbl_finduser') . ' ' . $template->username_field('finduser') . ' <input type="submit" value="' . $lang->get('userfuncs_ml_btn_go') . '" /><br />
                  <small>' . $lang->get('userfuncs_ml_tip_wildcard') . '</small></p>'
               . '</form>
               </div>'                                                                                                // Footer (printed after rows)
          );
  
  if ( $num_rows < 1 )
  {
    echo ( isset($_GET['finduser']) ) ? '<p>' . $lang->get('userfuncs_ml_err_nousers_find') . '</p>' :
                                        '<p>' . $lang->get('userfuncs_ml_err_nousers') . '</p>';
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
    global $lang;
    
    $userpage = $paths->nslist['User'] . sanitize_page_id($username);
    $class = ( isPage($userpage) ) ? '' : ' class="wikilink-nonexistent"';
    $anchor = '<a href="' . makeUrlNS('User', sanitize_page_id($username)) . '"' . $class . ' onclick="load_component(\'jquery\'); load_component(\'jquery-ui\'); var el = document.getElementById(\'ml_moreinfo_' . $row['user_id'] . '\'); $(el).toggle(\'blind\'); return false;">' . htmlspecialchars($username) . '</a>';
    if ( $session->user_level >= USER_LEVEL_ADMIN )
    {
      $anchor .= ' <small>- <a href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'UserManager&src=get&username=' . urlencode($username), true) . '"
                               onclick="ajaxAdminUser(\'' . addslashes(htmlspecialchars($username)) . '\'); return false;">' . $lang->get('userfuncs_ml_btn_adminuser') . '</a></small>';
    }
    return $anchor;
  }
  function user_level($level, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    switch ( $level )
    {
      case USER_LEVEL_GUEST:
        $s_level = $lang->get('userfuncs_ml_level_guest'); break;
      case USER_LEVEL_MEMBER:
      case USER_LEVEL_CHPREF:
        $s_level = $lang->get('userfuncs_ml_level_member'); break;
      case USER_LEVEL_MOD:
        $s_level = $lang->get('userfuncs_ml_level_mod'); break;
      case USER_LEVEL_ADMIN:
        $s_level = $lang->get('userfuncs_ml_level_admin'); break;
      default:
        $s_level = $lang->get('userfuncs_ml_level_unknown', array( 'level' => $level ));
    }
    return $s_level;
  }
  function email($addy, $row)
  {
    global $lang;
    if ( $row['email_public'] == '1' )
    {
      global $email;
      $addy = $email->encryptEmail($addy);
      return $addy;
    }
    else
    {
      return '<small>&lt;' . $lang->get('userfuncs_ml_email_nonpublic') . '&gt;</small>';
    }
  }
  /**
   * Format a time as a reference to a day, with user-friendly "X days ago"/"Today"/"Yesterday" returned when relevant.
   * @param int UNIX timestamp
   * @return string
   */
  
  function format_date($time)
  {
    global $lang;
    // Our formattting string to pass to enano_date()
    // This should not include minute/second info, only today's date in whatever format suits your fancy
    $formatstring = 'F j, Y';
    // Today's date
    $today = enano_date($formatstring);
    // Yesterday's date
    $yesterday = enano_date($formatstring, (time() - (24*60*60)));
    // Date on the input
    $then = enano_date($formatstring, $time);
    // "X days ago" logic
    for ( $i = 2; $i <= 6; $i++ )
    {
      // hours_in_day * minutes_in_hour * seconds_in_minute * num_days
      $offset = 24 * 60 * 60 * $i;
      $days_ago = enano_date($formatstring, (time() - $offset));
      // so does the input timestamp match the date from $i days ago?
      if ( $then == $days_ago )
      {
        // yes, return $i
        return $lang->get('userfuncs_ml_date_daysago', array('days_ago' => $i));
      }
    }
    // either yesterday, today, or before 6 days ago
    switch($then)
    {
      case $today:
        return $lang->get('userfuncs_ml_date_today');
      case $yesterday:
        return $lang->get('userfuncs_ml_date_yesterday');
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
  function infobit($_, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $bit = '';
    if ( $row['user_has_avatar'] == 1 )
    {
      $bit .= '<div style="float: left; margin-right: 10px;">
        <img alt=" " src="' . make_avatar_url(intval($row['user_id']), $row['avatar_type'], $row['email']) . '" />
      </div>';
    }
    $rank_data = $session->get_user_rank(intval($row['user_id']));
    $userpage = $paths->nslist['User'] . sanitize_page_id($row['username']);
    $title = ( isPage($userpage) ) ? ' title="' . $lang->get('userfuncs_ml_tip_userpage') . '"' : ' title="' . $lang->get('userfuncs_ml_tip_nouserpage') . '"';
    $bit .= '<a' . $title . ' href="' . makeUrlNS('User', $row['username'], false, true) . '" style="font-size: x-large; ' . $rank_data['rank_style'] . '">' . htmlspecialchars($row['username']) . '</a><br />';
    if ( $rank_data['user_title'] )
      $bit .= htmlspecialchars($rank_data['user_title']) . '<br />';
    if ( $rank_data['rank_title'] )
      $bit .= '<small>' . htmlspecialchars($lang->get($rank_data['rank_title'])) . '</small><br />';
    
    $bit .= '<div style="text-align: right;">
               <a href="' . makeUrlNS('Special', "PrivateMessages/Compose/To/{$row['username']}", false, true) . '" class="abutton abutton_blue"><img alt=" " src="' . cdnPath . '/images/icons/send_pm.png" /> ' . $lang->get('comment_btn_send_privmsg') . '</a>
               <a href="' . makeUrlNS('Special', "PrivateMessages/FriendList/Add/{$row['username']}", false, true) . '" class="abutton abutton_green"><img alt=" " src="' . cdnPath . '/images/icons/add_buddy.png" /> ' . $lang->get('comment_btn_add_buddy') . '</a>
             </div>';
    
    return $bit;
  }
}

function page_Special_LangExportJSON()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $lang_id = ( $x = $paths->getParam(0) ) ? intval($x) : $lang->lang_id;
  
  if ( $lang->lang_id == $lang_id )
    $lang_local =& $lang;
  else
    $lang_local = new Language($lang_id);
    
  $lang_local->get('meta_meta');
  
  $lang_strings = enano_json_encode($lang_local->strings);
  $etag = substr(sha1($lang_strings), 0, 20) . '-' . dechex($lang_local->lang_timestamp);
  
  if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
  {
    if ( "\"$etag\"" == $_SERVER['HTTP_IF_NONE_MATCH'] )
    {
      header('HTTP/1.1 304 Not Modified');
      exit();
    }
  }
  
  $timestamp = enano_date('D, j M Y H:i:s T', $lang_local->lang_timestamp);
  // generate expires header
  $expires = date('r', mktime(-1, -1, -1, -1, -1, intval(date('y'))+1));

  header("Last-Modified: $timestamp");
  header("Date: $timestamp");
  header("ETag: \"$etag\"");
  header('Content-type: text/javascript');
  header("Expires: $expires");
  
  $lang_local->fetch();
  echo "if ( typeof(enano_lang) != 'object' )
  var enano_lang = new Object();

enano_lang[{$lang_local->lang_id}] = " . $lang_strings . ";";

  gzip_output();
  
  exit(0);
}

/**
 * Fetches and displays an avatar from the filesystem. Avatar fetching is abstracted as of 1.1.4.
 */

function page_Special_Avatar()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $aggressive_optimize_html;
  $aggressive_optimize_html = false;
  
  $img_types = array(
      IMAGE_TYPE_PNG => 'png',
      IMAGE_TYPE_GIF => 'gif',
      IMAGE_TYPE_JPG => 'jpg',
      IMAGE_TYPE_GRV => 'grv'
    );
  
  $avi_id = $paths->getParam(0);
  if ( !$avi_id || !@preg_match('/^[a-f0-9]+$/', $avi_id) )
  {
    echo 'Doesn\'t match the regexp';
    return true;
  }
  
  $avi_id_dec = hexdecode($avi_id);
  $avi_id_dec = @unpack('Vdate/Vuid/vimg_type', $avi_id_dec);
  if ( !$avi_id_dec )
  {
    echo 'Bad unpack';
    return true;
  }
  
  // check parameters
  if ( !isset($img_types[$avi_id_dec['img_type']]) )
  {
    echo 'Invalid image type';
    return true;
  }
  
  // build file path
  $avi_type = $img_types[$avi_id_dec['img_type']];
  
  // is this a gravatar?
  if ( $avi_type == 'grv' )
  {
    // yes, we'll have to redirect
    // sanitize UID
    $uid = intval($avi_id_dec['uid']);
    
    // fetch email
    $q = $db->sql_query('SELECT email FROM ' . table_prefix . "users WHERE user_id = $uid;");
    if ( !$q )
      $db->_die();
    if ( $db->numrows() < 1 )
      return false;
    
    list($email) = $db->fetchrow_num();
    $db->free_result();
    
    $url = make_gravatar_url($url);
    
    // ship out the redirect
    header('HTTP/1.1 302 Permanent Redirect');
    header("Location: $url");
  }
  
  $avi_path = ENANO_ROOT . '/' . getConfig('avatar_directory') . '/' . $avi_id_dec['uid'] . '.' . $avi_type;
  if ( file_exists($avi_path) )
  {
    $avi_mod_time = @filemtime($avi_path);
    $avi_mod_time = date('r', $avi_mod_time);
    $avi_size = @filesize($avi_path);
    header("Last-Modified: $avi_mod_time");
    header("Content-Length: $avi_size");
    header("Content-Type: image/$avi_type");
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
    header("Cache-Control: public");
    // expire it 30 days from now
    $expiry_time = time() + ( 86400 * 30 );
    header("Expires: " . date('r', $expiry_time));
    
    $fh = @fopen($avi_path, 'r');
    if ( !$fh )
    {
      echo 'Could not open file';
      return true;
    }
    
    while ( $fd = @fread($fh, 1024) )
    {
      echo $fd;
    }
    fclose($fh);
    
  }
  return true;
}

?>