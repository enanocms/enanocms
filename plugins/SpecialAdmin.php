<?php
/*
Plugin Name: plugin_specialadmin_title
Plugin URI: http://enanocms.org/
Description: plugin_specialadmin_desc
Author: Dan Fuhry
Version: 1.0.3
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('session_started', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'specialpage_administration\',
      \'urlname\'=>\'Administration\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_manage_sidebar\',
      \'urlname\'=>\'EditSidebar\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
  ');

// Admin pages that were too enormous to be in this file were split off into the plugins/admin/ directory in 1.0.1
require(ENANO_ROOT . '/plugins/admin/PageManager.php');
require(ENANO_ROOT . '/plugins/admin/PageGroups.php');
require(ENANO_ROOT . '/plugins/admin/SecurityLog.php');
require(ENANO_ROOT . '/plugins/admin/UserManager.php');

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

function page_Admin_Home() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  if ( $paths->getParam(0) == 'updates.xml' )
  {
    require_once(ENANO_ROOT . '/includes/http.php');
    $req = new Request_HTTP('germantown.enanocms.org', '/meta/updates.xml');
    $response = $req->get_response_body();
    header('Content-type: application/xml');
    if ( $req->response_code != HTTP_OK )
    {
      // Error in response
      echo '<enano><latest><error><![CDATA[
Did not properly receive response from server. Response code: ' . $req->response_code . ' ' . $req->response_string . '
]]></error></latest></enano>';
    }
    else
    {
      // Retrieve first update
      $first_update = preg_match('/<release tag="([^"]+)" version="([^"]+)" (codename="([^"]+)" )?relnotes="([^"]+)" ?\/>/', $response, $match);
      if ( !$first_update )
      {
        echo '<enano><latest><error><![CDATA[
Received invalid XML response.
]]></error></latest></enano>';
      }
      if ( version_compare(enano_version(true), $match[2], '<') )
      {
        $response = str_replace_once('</latest>', "  <haveupdates />\n  </latest>", $response);
      }
      echo $response;
    }
    return;
  }
  
  // Basic information
  echo '<h2>' . $lang->get('acphome_heading_main') . '</h2>';
  echo '<p>' . $lang->get('acphome_welcome_line1') . '</p>';
  echo '<p>' . $lang->get('acphome_welcome_line2') . '</p>';
  
  // Demo mode
  if ( defined('ENANO_DEMO_MODE') )
  {
    echo '<h3>' . $lang->get('acphome_msg_demo_title') . '</h3>
          <p>' . $lang->get('acphome_msg_demo_body', array('reset_url' => makeUrlNS('Special', 'DemoReset', false, true))) . '</p>';
  }
  
  // Check for the installer scripts
  if( ( file_exists(ENANO_ROOT.'/install.php') || file_exists(ENANO_ROOT.'/schema.sql') ) && !defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">
            ' . $lang->get('acphome_msg_install_files') . '
          </div>';
  }
  
  echo '<h3>' . $lang->get('acphome_heading_updates') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_updates_info', array('updates_url' => 'http://germantown.enanocms.org/meta/updates.xml')) . '</p>';
  echo '<div id="update_check_container"><input type="button" onclick="ajaxUpdateCheck(this.parentNode.id);" value="' . $lang->get('acphome_btn_check_updates') . '" /></div>';
  
  // Inactive users
  $q = $db->sql_query('SELECT time_id FROM '.table_prefix.'logs WHERE log_type=\'admin\' AND action=\'activ_req\';');
  if ( $q )
  {
    if ( $db->numrows() > 0 )
    {
      $n = $db->numrows();
      $um_flags = 'href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'UserManager\'); return false;"';
      if ( $n == 1 )
        $s = $lang->get('acphome_msg_inactive_users_one', array('um_flags' => $um_flags));
      else
        $s = $lang->get('acphome_msg_inactive_users_plural', array('um_flags' => $um_flags));
      echo '<div class="warning-box">
              ' . $s . '
            </div>';
    }
  }
  $db->free_result();
  // Stats
  if(getConfig('log_hits') == '1')
  {
    $stats = stats_top_pages(10);
    //die('<pre>'.print_r($stats,true).'</pre>');
    $c = 0;
    $cls = 'row2';
    echo '<h3>' . $lang->get('acphome_heading_top_pages') . '</h3>
          <div class="tblholder">
            <table style="width: 100%;" border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>' . $lang->get('acphome_th_toppages_page') . '</th>
                <th>' . $lang->get('acphome_th_toppages_hits') . '</th>
              </tr>';
    foreach($stats as $data)
    {
      echo   '<tr>';
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      echo     '<td class="'.$cls.'">
                  <a href="'.makeUrl($data['page_urlname']).'">'.$data['page_title'].'</a></td><td style="text-align: center;" class="'.$cls.'">'.$data['num_hits']
             . '</td>';
      echo   '</tr>';
    }
    echo '  </table>
          </div>';
  }
  
  // Security log
  echo '<h3>' . $lang->get('acphome_heading_seclog') . '</h3>';
  $seclog = get_security_log(5);
  echo $seclog;
  
  echo '<p><a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'SecurityLog\'); return false;">' . $lang->get('acphome_btn_seclog_full') . '</a></p>';
  
}

function page_Admin_GeneralConfig() {
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  if(isset($_POST['submit']) && !defined('ENANO_DEMO_MODE') )
  {
    
    // Global site options
    setConfig('site_name', $_POST['site_name']);
    setConfig('site_desc', $_POST['site_desc']);
    setConfig('main_page', str_replace(' ', '_', $_POST['main_page']));
    setConfig('copyright_notice', $_POST['copyright']);
    setConfig('contact_email', $_POST['contact_email']);
    
    // Wiki mode
    if(isset($_POST['wikimode']))                setConfig('wiki_mode', '1');
    else                                         setConfig('wiki_mode', '0');
    if(isset($_POST['wiki_mode_require_login'])) setConfig('wiki_mode_require_login', '1');
    else                                         setConfig('wiki_mode_require_login', '0');
    if(isset($_POST['editmsg']))                 setConfig('wiki_edit_notice', '1');
    else                                         setConfig('wiki_edit_notice', '0');
    setConfig('wiki_edit_notice_text', $_POST['editmsg_text']);
    if(isset($_POST['guest_edit_require_captcha'])) setConfig('guest_edit_require_captcha', '1');
    else                                         setConfig('guest_edit_require_captcha', '0');
    
    // Stats
    if(isset($_POST['log_hits']))                setConfig('log_hits', '1');
    else                                         setConfig('log_hits', '0');
    
    // Disablement
    if(isset($_POST['site_disabled'])) {         setConfig('site_disabled', '1'); setConfig('site_disabled_notice', $_POST['site_disabled_notice']); }
    else                                         setConfig('site_disabled', '0');
    
    // Account activation
    setConfig('account_activation', $_POST['account_activation']);
    
    // W3C compliance buttons
    if(isset($_POST['w3c-vh32']))     setConfig("w3c_vh32", "1");
    else                              setConfig("w3c_vh32", "0");
    if(isset($_POST['w3c-vh40']))     setConfig("w3c_vh40", "1");
    else                              setConfig("w3c_vh40", "0");
    if(isset($_POST['w3c-vh401']))    setConfig("w3c_vh401", "1");
    else                              setConfig("w3c_vh401", "0");
    if(isset($_POST['w3c-vxhtml10'])) setConfig("w3c_vxhtml10", "1");
    else                              setConfig("w3c_vxhtml10", "0");
    if(isset($_POST['w3c-vxhtml11'])) setConfig("w3c_vxhtml11", "1");
    else                              setConfig("w3c_vxhtml11", "0");
    if(isset($_POST['w3c-vcss']))     setConfig("w3c_vcss", "1");
    else                              setConfig("w3c_vcss", "0");
    
    // SourceForge.net logo
    if(isset($_POST['showsf'])) setConfig('sflogo_enabled', '1');
    else                        setConfig('sflogo_enabled', '0');
    setConfig('sflogo_groupid', $_POST['sfgroup']);
    setConfig('sflogo_type', $_POST['sflogo']);
    
    // Comment options
    if(isset($_POST['comment-approval'])) setConfig('approve_comments', '1');
    else                                  setConfig('approve_comments', '0');
    if(isset($_POST['enable-comments']))  setConfig('enable_comments', '1');
    else                                  setConfig('enable_comments', '0');
    setConfig('comments_need_login', $_POST['comments_need_login']);
    
    // Powered by link
    if ( isset($_POST['enano_powered_link']) ) setConfig('powered_btn', '1');
    else                                       setConfig('powered_btn', '0');    
    
    if(isset($_POST['dbdbutton']))        setConfig('dbd_button', '1');
    else                                  setConfig('dbd_button', '0');
    
    if($_POST['emailmethod'] == 'phpmail') setConfig('smtp_enabled', '0');
    else                                   setConfig('smtp_enabled', '1');
    
    setConfig('smtp_server', $_POST['smtp_host']);
    setConfig('smtp_user', $_POST['smtp_user']);
    if($_POST['smtp_pass'] != 'XXXXXXXXXXXX') setConfig('smtp_password', $_POST['smtp_pass']);
    
    // Password strength
    if ( isset($_POST['pw_strength_enable']) ) setConfig('pw_strength_enable', '1');
    else                                       setConfig('pw_strength_enable', '0');
    
    $strength = intval($_POST['pw_strength_minimum']);
    if ( $strength >= -10 && $strength <= 30 )
    {
      $strength = strval($strength);
      setConfig('pw_strength_minimum', $strength);
    }
    
    // Account lockout policy
    if ( preg_match('/^[0-9]+$/', $_POST['lockout_threshold']) )
      setConfig('lockout_threshold', $_POST['lockout_threshold']);
    
    if ( preg_match('/^[0-9]+$/', $_POST['lockout_duration']) )
      setConfig('lockout_duration', $_POST['lockout_duration']);
    
    if ( in_array($_POST['lockout_policy'], array('disable', 'captcha', 'lockout')) )
      setConfig('lockout_policy', $_POST['lockout_policy']);
    
    // Avatar settings
    setConfig('avatar_enable', ( isset($_POST['avatar_enable']) ? '1' : '0' ));
    // for these next three values, set the config value if it's a valid integer; this is
    // done by using strval(intval($foo)) === $foo, which flattens $foo to an integer and
    // then converts it back to a string. This effectively verifies that var $foo is both
    // set and that it's a valid string representing an integer.
    setConfig('avatar_max_size', ( strval(intval($_POST['avatar_max_size'])) === $_POST['avatar_max_size'] ? $_POST['avatar_max_size'] : '10240' ));
    setConfig('avatar_max_width', ( strval(intval($_POST['avatar_max_width'])) === $_POST['avatar_max_width'] ? $_POST['avatar_max_width'] : '96' ));
    setConfig('avatar_max_height', ( strval(intval($_POST['avatar_max_height'])) === $_POST['avatar_max_height'] ? $_POST['avatar_max_height'] : '96' ));
    setConfig('avatar_enable_anim', ( isset($_POST['avatar_enable_anim']) ? '1' : '0' ));
    setConfig('avatar_upload_file', ( isset($_POST['avatar_upload_file']) ? '1' : '0' ));
    setConfig('avatar_upload_http', ( isset($_POST['avatar_upload_http']) ? '1' : '0' ));
    
    if ( is_dir(ENANO_ROOT . '/' . $_POST['avatar_directory']) )
    {
      if ( preg_match('/^([A-z0-9_-]+)(\/([A-z0-9_-]+))*$/', $_POST['avatar_directory']) )
      {
        setConfig('avatar_directory', $_POST['avatar_directory']);
      }
      else
      {
        echo '<div class="error-box">' . $lang->get('acpgc_err_avatar_dir_invalid') . '</div>';
      }
    }
    else
    {
      echo '<div class="error-box">' . $lang->get('acpgc_err_avatar_dir_invalid') . '</div>';
    }
    
    echo '<div class="info-box">' . $lang->get('acpgc_msg_save_success') . '</div><br />';
    
  }
  else if ( isset($_POST['submit']) && defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">Saving the general site configuration is blocked in the administration demo.</div>';
  }
  echo('<form name="main" action="'.htmlspecialchars(makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module'])).'" method="post" onsubmit="if(!submitAuthorized) return false;">');
  ?>
  <div class="tblholder">
    <table border="0" width="100%" cellspacing="1" cellpadding="4">
      
    <!-- Global options -->
    
      <tr><th colspan="2"><?php echo $lang->get('acpgc_heading_main'); ?></th></tr>
      <tr><th colspan="2" class="subhead"><?php echo $lang->get('acpgc_heading_submain'); ?></th></tr>
      
      <tr><td class="row1" style="width: 50%;"><?php echo $lang->get('acpgc_field_site_name'); ?></td>  <td class="row1" style="width: 50%;"><input type="text" name="site_name" size="30" value="<?php echo htmlspecialchars(getConfig('site_name')); ?>" /></td></tr>
      <tr><td class="row2"><?php echo $lang->get('acpgc_field_site_desc'); ?></td>               <td class="row2"><input type="text" name="site_desc" size="30" value="<?php echo htmlspecialchars(getConfig('site_desc')); ?>" /></td></tr>
      <tr><td class="row1"><?php echo $lang->get('acpgc_field_main_page'); ?></td>                      <td class="row1"><?php echo $template->pagename_field('main_page', htmlspecialchars(str_replace('_', ' ', getConfig('main_page')))); ?></td></tr>
      <tr><td class="row2"><?php echo $lang->get('acpgc_field_copyright'); ?></td><td class="row2"><input type="text" name="copyright" size="30" value="<?php echo htmlspecialchars(getConfig('copyright_notice')); ?>" /></td></tr>
      <tr><td class="row1" colspan="2"><?php echo $lang->get('acpgc_field_copyright_hint'); ?></td></tr>
      <tr><td class="row2"><?php echo $lang->get('acpgc_field_contactemail'); ?><br /><small><?php echo $lang->get('acpgc_field_contactemail_hint'); ?></small></td><td class="row2"><input name="contact_email" type="text" size="40" value="<?php echo htmlspecialchars(getConfig('contact_email')); ?>" /></td></tr>
      
    <!-- Wiki mode -->
      
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_wikimode'); ?></th></tr>
      
      <tr>
        <td class="row3" rowspan="2">
          <?php echo $lang->get('acpgc_field_wikimode_intro'); ?><br /><br />
          <?php echo $lang->get('acpgc_field_wikimode_info_sanitize'); ?><br /><br />
          <?php echo $lang->get('acpgc_field_wikimode_info_history'); ?>
        </td>
        <td class="row1">
          <input type="checkbox" name="wikimode" id="wikimode" <?php if(getConfig('wiki_mode')=='1') echo('CHECKED '); ?> /><label for="wikimode"><?php echo $lang->get('acpgc_field_wikimode'); ?></label>
        </td>
      </tr>
      
      <tr><td class="row2"><label><input type="checkbox" name="wiki_mode_require_login"<?php if(getConfig('wiki_mode_require_login')=='1') echo('CHECKED '); ?>/> Only for logged in users</label></td></tr>
      
      <tr>
        <td class="row3" rowspan="2">
          <b><?php echo $lang->get('acpgc_field_editnotice_title'); ?></b><br />
          <?php echo $lang->get('acpgc_field_editnotice_info'); ?>
        </td>
        <td class="row1">
          <input onclick="if(this.checked) document.getElementById('editmsg_text').style.display='block'; else document.getElementById('editmsg_text').style.display='none';" type="checkbox" name="editmsg" id="editmsg" <?php if(getConfig('wiki_edit_notice')=='1') echo('CHECKED '); ?>/>
          <label for="editmsg"><?php echo $lang->get('acpgc_field_editnotice'); ?></label>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <textarea <?php if(getConfig('wiki_edit_notice')!='1') echo('style="display:none" '); ?>rows="5" cols="30" name="editmsg_text" id="editmsg_text"><?php echo getConfig('wiki_edit_notice_text'); ?></textarea>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <b><?php echo $lang->get('acpgc_field_edit_require_captcha_title'); ?></b><br />
          <?php echo $lang->get('acpgc_field_edit_require_captcha_hint'); ?>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="guest_edit_require_captcha" <?php if ( getConfig('guest_edit_require_captcha') == '1' ) echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_edit_require_captcha'); ?>
          </label>
        </td>
      </tr>
      
    <!-- Site statistics -->
    
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_stats'); ?></th></tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_stats_intro'); ?><br /><br />
          <?php echo $lang->get('acpgc_stats_hint_privacy'); ?>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="log_hits" <?php if(getConfig('log_hits') == '1') echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_stats_enable'); ?>
          </label><br />
          <small><?php echo $lang->get('acpgc_field_stats_hint'); ?></small>
        </td>
      </tr>
      
    <!-- Comment options -->
      
      <tr>
        <th class="subhead" colspan="2">
          <?php echo $lang->get('acpgc_heading_comments'); ?>
        </th>
      </tr>
      
      <tr>
        <td class="row1">
          <label for="enable-comments">
            <b><?php echo $lang->get('acpgc_field_enable_comments'); ?></b>
          </label>
        </td>
        <td class="row1">
          <input name="enable-comments"  id="enable-comments"  type="checkbox" <?php if(getConfig('enable_comments')=='1')  echo('CHECKED '); ?>/>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <label for="comment-approval">
            <?php echo $lang->get('acpgc_field_approve_comments'); ?>
          </label>
        </td>
        <td class="row2">
          <input name="comment-approval" id="comment-approval" type="checkbox" <?php if(getConfig('approve_comments')=='1') echo('CHECKED '); ?>/>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_comment_allow_guests'); ?>
        </td>
        <td class="row1">
          <label>
            <input name="comments_need_login" type="radio" value="0" <?php if(getConfig('comments_need_login')=='0') echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_comment_allow_guests_yes'); ?>
          </label>
          <label>
            <input name="comments_need_login" type="radio" value="1" <?php if(getConfig('comments_need_login')=='1') echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_comment_allow_guests_captcha'); ?>
          </label>
          <label>
            <input name="comments_need_login" type="radio" value="2" <?php if(getConfig('comments_need_login')=='2') echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_comment_allow_guests_no'); ?>
          </label>
        </td>
      </tr>
            
    <!-- Default permissions -->
    
    <!--
    
    READ: Do not try to enable this, backend support for it has been disabled. To edit default
          permissions, select The Entire Website in any permissions editor window.
    
      <tr><th colspan="2">Default permissions for pages</th></tr>
      
      <tr>
        <td class="row1">You can edit the default set of permissions used when no other permissions are available. Permissions set here are used when no other permissions are available. As with other ACL rules, you can assign these defaults to every user or one specific user or group.</td>
        <td class="row1"><a href="#" onclick="ajaxOpenACLManager('__DefaultPermissions', 'Special'); return false;">Manage default permissions</a></td>
      </tr>
      
      -->
      
    <!-- Site disablement -->
    
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_disablesite'); ?></th></tr>
      
      <tr>
        <td class="row3" rowspan="2">
          <?php echo $lang->get('acpgc_field_disablesite_hint'); ?>
        </td>
        <td class="row1">
          <label>
            <input onclick="if(this.checked) document.getElementById('site_disabled_notice').style.display='block'; else document.getElementById('site_disabled_notice').style.display='none';" type="checkbox" name="site_disabled" <?php if(getConfig('site_disabled') == '1') echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_disablesite'); ?>
          </label>
        </td>
      </tr>
      <tr>
        <td class="row2">
          <div id="site_disabled_notice"<?php if(getConfig('site_disabled')!='1') echo(' style="display:none"'); ?>>
            <?php echo $lang->get('acpgc_field_disablesite_message'); ?><br />
            <textarea name="site_disabled_notice" rows="7" cols="30"><?php echo getConfig('site_disabled_notice'); ?></textarea>
          </div>
        </td>
      </tr>
      
    </table>
    </div>
        
    <div class="tblholder">
    <table border="0" width="100%" cellspacing="1" cellpadding="4">
    
    <tr>
      <th colspan="2"><?php echo $lang->get('acpgc_heading_users'); ?></th>
    </tr>
    
    <!-- Account activation -->
      
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_activate'); ?></th></tr>
      
      <tr>
        <td class="row3" colspan="2">
          <?php echo $lang->get('acpgc_activate_intro_line1'); ?><br /><br />
          <?php echo $lang->get('acpgc_activate_intro_line2'); ?><br /><br />
          <b><?php echo $lang->get('acpgc_activate_intro_sfnet_warning'); ?></b>
        </td>
      </tr>
      
      <tr>
      <td class="row1" style="width: 50%;"><?php echo $lang->get('acpgc_field_activate'); ?></td><td class="row1">
          <?php
          echo '<label><input'; if(getConfig('account_activation') == 'disable') echo ' checked="checked"'; echo ' type="radio" name="account_activation" value="disable" /> ' . $lang->get('acpgc_field_activate_disable') . '</label><br />';
          echo '<label><input'; if(getConfig('account_activation') != 'user' && getConfig('account_activation') != 'admin' && getConfig('account_activation') != 'disable') echo ' checked="checked"'; echo ' type="radio" name="account_activation" value="none" /> ' . $lang->get('acpgc_field_activate_none') . '</label>';
          echo '<label><input'; if(getConfig('account_activation') == 'user') echo ' checked="checked"'; echo ' type="radio" name="account_activation" value="user" /> ' . $lang->get('acpgc_field_activate_user') . '</label>';
          echo '<label><input'; if(getConfig('account_activation') == 'admin') echo ' checked="checked"'; echo ' type="radio" name="account_activation" value="admin" /> ' . $lang->get('acpgc_field_activate_admin') . '</label>';
          ?>
        </td>
      </tr>
      
    <!-- Account lockout -->
    
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_lockout'); ?></th></tr>
      
      <tr><td class="row3" colspan="2"><?php echo $lang->get('acpgc_lockout_intro'); ?></td></tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('acpgc_field_lockout_threshold'); ?><br />
          <small><?php echo $lang->get('acpgc_field_lockout_threshold_hint'); ?></small>
        </td>
        <td class="row2">
          <input type="text" name="lockout_threshold" value="<?php echo ( $_ = getConfig('lockout_threshold') ) ? $_ : '5' ?>" />
        </td>
      </tr>
      
      <tr>
        <td class="row1"><?php echo $lang->get('acpgc_field_lockout_duration'); ?><br />
          <small><?php echo $lang->get('acpgc_field_lockout_duration_hint'); ?></small>
        </td>
        <td class="row1">
          <input type="text" name="lockout_duration" value="<?php echo ( $_ = getConfig('lockout_duration') ) ? $_ : '15' ?>" />
        </td>
      </tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('acpgc_field_lockout_policy'); ?><br />
          <small><?php echo $lang->get('acpgc_field_lockout_policy_hint'); ?></small>
        </td>
        <td class="row2">
          <label><input type="radio" name="lockout_policy" value="disable" <?php if ( getConfig('lockout_policy') == 'disable' ) echo 'checked="checked"'; ?> /> <?php echo $lang->get('acpgc_field_lockout_policy_nothing'); ?></label><br />
          <label><input type="radio" name="lockout_policy" value="captcha" <?php if ( getConfig('lockout_policy') == 'captcha' ) echo 'checked="checked"'; ?> /> <?php echo $lang->get('acpgc_field_lockout_policy_captcha'); ?></label><br />
          <label><input type="radio" name="lockout_policy" value="lockout" <?php if ( getConfig('lockout_policy') == 'lockout' || !getConfig('lockout_policy') ) echo 'checked="checked"'; ?> /> <?php echo $lang->get('acpgc_field_lockout_policy_lockout'); ?></label>
        </td>
      </tr>
      
    <!-- Password strength -->
      
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_passstrength'); ?></th></tr>
      
      <tr>
        <td class="row2">
          <b><?php echo $lang->get('acpgc_field_passstrength_title'); ?></b><br />
          <small><?php echo $lang->get('acpgc_field_passstrength_hint'); ?></small>
        </td>
        <td class="row2">
          <label><input type="checkbox" name="pw_strength_enable" <?php if ( getConfig('pw_strength_enable') == '1' ) echo 'checked="checked" '; ?>/> <?php echo $lang->get('acpgc_field_passstrength'); ?></label>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <b><?php echo $lang->get('acpgc_field_passminimum_title'); ?></b><br />
          <small><?php echo $lang->get('acpgc_field_passminimum_hint'); ?></small>
        </td>
        <td class="row1">
          <input type="text" name="pw_strength_minimum" value="<?php echo ( $x = getConfig('pw_strength_minimum') ) ? $x : '-10'; ?>" />
        </td>
      </tr>
      
    <!-- E-mail options -->
    
      <tr>
        <th class="subhead" colspan="2">
          <?php echo $lang->get('acpgc_heading_email'); ?>
        </th>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_email_method'); ?><br />
          <small><?php echo $lang->get('acpgc_field_email_method_hint'); ?></small>
        </td>
        <td class="row1">
          <label>
            <input <?php if(getConfig('smtp_enabled') != '1') echo 'checked="checked"'; ?> type="radio" name="emailmethod" value="phpmail" />
            <?php echo $lang->get('acpgc_field_email_method_builtin'); ?>
          </label>
          
          <br />
          
          <label>
            <input <?php if(getConfig('smtp_enabled') == '1') echo 'checked="checked"'; ?> type="radio" name="emailmethod" value="smtp" />
            <?php echo $lang->get('acpgc_field_email_method_smtp'); ?>
          </label>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_email_smtp_hostname'); ?><br />
          <small><?php echo $lang->get('acpgc_field_email_smtp_hostname_hint'); ?></small>
        </td>
        <td class="row2">
          <input value="<?php echo getConfig('smtp_server'); ?>" name="smtp_host" type="text" size="30" />
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_email_smtp_auth'); ?><br />
          <small><?php echo $lang->get('acpgc_field_email_smtp_hostname_hint'); ?></small>
        </td>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_email_smtp_username'); ?> <input value="<?php echo getConfig('smtp_user'); ?>" name="smtp_user" type="text" size="30" /><br />
          <?php echo $lang->get('acpgc_field_email_smtp_password'); ?> <input value="<?php if(getConfig('smtp_password') != false) echo 'XXXXXXXXXXXX'; ?>" name="smtp_pass" type="password" size="30" />
        </td>
      </tr>
        
    <!-- Avatar support -->
    
      <tr>
        <th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_avatars'); ?></th>
      </tr>
      
      <tr>
        <td class="row3" colspan="2">
          <?php echo $lang->get('acpgc_avatars_intro'); ?>
        </th>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_avatar_enable'); ?><br />
          <small><?php echo $lang->get('acpgc_field_avatar_enable_hint'); ?></small>
        </td>
        <td class="row1">
          <label><input type="checkbox" name="avatar_enable" <?php if ( getConfig('avatar_enable') == '1' ) echo 'checked="checked" '; ?>/> <?php echo $lang->get('acpgc_field_avatar_enable_label'); ?></label>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_avatar_max_filesize'); ?><br />
          <small><?php echo $lang->get('acpgc_field_avatar_max_filesize_hint'); ?></small>
        </td>
        <td class="row2">
          <input type="text" name="avatar_max_size" size="7" <?php if ( ($x = getConfig('avatar_max_size')) !== false ) echo "value=\"$x\" "; else echo "value=\"10240\" "; ?>/> <?php echo $lang->get('etc_unit_bytes'); ?>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_avatar_max_dimensions'); ?><br />
          <small><?php echo $lang->get('acpgc_field_avatar_max_dimensions_hint'); ?></small>
        </td>
        <td class="row1">
          <input type="text" name="avatar_max_width" size="7" <?php if ( $x = getConfig('avatar_max_width') ) echo "value=\"$x\" "; else echo "value=\"150\" "; ?>/> &#215;
          <input type="text" name="avatar_max_height" size="7" <?php if ( $x = getConfig('avatar_max_height') ) echo "value=\"$x\" "; else echo "value=\"150\" "; ?>/> <?php echo $lang->get('etc_unit_pixels'); ?>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_avatar_allow_anim_title'); ?><br />
          <small><?php echo $lang->get('acpgc_field_avatar_allow_anim_hint'); ?></small>
        </td>
        <td class="row2">
          <label><input type="checkbox" name="avatar_enable_anim" <?php if ( getConfig('avatar_enable_anim') == '1' ) echo 'checked="checked" '; ?>/> <?php echo $lang->get('acpgc_field_avatar_allow_anim'); ?></label>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_avatar_upload_methods'); ?><br />
          <small></small>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="avatar_upload_file" <?php if ( getConfig('avatar_upload_file') == '1' || getConfig('avatar_upload_file') === false ) echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_avatar_upload_file'); ?>
          </label>
          
          <br />
          
          <label>
            <input type="checkbox" name="avatar_upload_http" <?php if ( getConfig('avatar_upload_http') == '1' || getConfig('avatar_upload_http') === false ) echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_avatar_upload_http'); ?>
          </label>
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_avatar_directory'); ?><br />
          <small><?php echo $lang->get('acpgc_field_avatar_directory_hint'); ?></small>
        </td>
        <td class="row2">
          <input type="text" name="avatar_directory" size="30" <?php if ( $x = getConfig('avatar_directory') ) echo "value=\"$x\" "; else echo "value=\"files/avatars\" "; ?>/>
        </td>
      </tr>
        
    </table>
    </div>
        
    <div class="tblholder">
    <table border="0" width="100%" cellspacing="1" cellpadding="4">
    
    <tr>
      <th colspan="2"><?php echo $lang->get('acpgc_heading_sidebar'); ?></th>
    </tr>
    
    <!-- enanocms.org link -->
    
    <tr>
      <th colspan="2" class="subhead"><?php echo $lang->get('acpgc_heading_promoteenano'); ?></th>
    </tr>                      
    <tr>
      <td class="row3" style="width: 50%;">
        <?php echo $lang->get('acpgc_field_enano_link_title'); ?>
      </td>
      <td class="row1">
        <label>
          <input name="enano_powered_link" type="checkbox" <?php if(getConfig('powered_btn') == '1') echo 'checked="checked"'; ?> />&nbsp;&nbsp;<?php echo $lang->get('acpgc_field_enano_link'); ?>
        </label>
      </td>
    </tr>
      
    <!-- SourceForge.net logo -->
      
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_sfnet_logo'); ?></th></tr>
      
      <tr>
        <td colspan="2" class="row3">
          <?php echo $lang->get('acpgc_sfnet_intro'); ?>
        </td>
      </tr>
      
      <?php
      if ( getConfig("sflogo_enabled") == '1' )
        $c='checked="checked" ';
      else
        $c='';
        
      if ( getConfig("sflogo_groupid") )
        $g = getConfig("sflogo_groupid");
      else
        $g = '';
        
      if ( getConfig("sflogo_type") )
        $t = getConfig("sflogo_type");
      else
        $t = '1';
      ?>
      
      <tr>
        <td class="row1"><?php echo $lang->get('acpgc_field_sfnet_display'); ?></td>
        <td class="row1"><input type=checkbox name="showsf" id="showsf" <?php echo $c; ?> /></td>
      </tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('acpgc_field_sfnet_group_id'); ?></td>
        <td class="row2"><input value="<?php echo $g; ?>" type=text size=15 name=sfgroup /></td>
      </tr>
      
      <tr>
        <td class="row1"><?php echo $lang->get('acpgc_field_sfnet_logo_style'); ?></td>
        <td class="row1">
          <select name="sflogo">
            <option <?php if($t=='1') echo('selected="selected" '); ?>value=1><?php echo $lang->get('acpgc_field_sfnet_logo_style_1'); ?></option>
            <option <?php if($t=='2') echo('selected="selected" '); ?>value=2><?php echo $lang->get('acpgc_field_sfnet_logo_style_2'); ?></option>
            <option <?php if($t=='3') echo('selected="selected" '); ?>value=3><?php echo $lang->get('acpgc_field_sfnet_logo_style_3'); ?></option>
            <option <?php if($t=='4') echo('selected="selected" '); ?>value=4><?php echo $lang->get('acpgc_field_sfnet_logo_style_4'); ?></option>
            <option <?php if($t=='5') echo('selected="selected" '); ?>value=5><?php echo $lang->get('acpgc_field_sfnet_logo_style_5'); ?></option>
            <option <?php if($t=='6') echo('selected="selected" '); ?>value=6><?php echo $lang->get('acpgc_field_sfnet_logo_style_6'); ?></option>
            <option <?php if($t=='7') echo('selected="selected" '); ?>value=7><?php echo $lang->get('acpgc_field_sfnet_logo_style_7'); ?></option>
          </select>
        </td>
      </tr>
      
    <!-- W3C validator buttons -->
      
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_w3clogos'); ?></th></tr>
      <tr><td colspan="2" class="row3"><?php echo $lang->get('acpgc_w3clogos_intro'); ?></th></tr>
      
      <tr><td class="row1"><label for="w3c-vh32"><?php     echo $lang->get('acpgc_w3clogos_btn_html32');  ?></label></td><td class="row1"><input type="checkbox" <?php if(getConfig('w3c_vh32')=='1')     echo('checked="checked" '); ?> id="w3c-vh32"     name="w3c-vh32"     /></td></tr>
      <tr><td class="row2"><label for="w3c-vh40"><?php     echo $lang->get('acpgc_w3clogos_btn_html40');  ?></label></td><td class="row2"><input type="checkbox" <?php if(getConfig('w3c_vh40')=='1')     echo('checked="checked" '); ?> id="w3c-vh40"     name="w3c-vh40"     /></td></tr>
      <tr><td class="row1"><label for="w3c-vh401"><?php    echo $lang->get('acpgc_w3clogos_btn_html401'); ?></label></td><td class="row1"><input type="checkbox" <?php if(getConfig('w3c_vh401')=='1')    echo('checked="checked" '); ?> id="w3c-vh401"    name="w3c-vh401"    /></td></tr>
      <tr><td class="row2"><label for="w3c-vxhtml10"><?php echo $lang->get('acpgc_w3clogos_btn_xhtml10'); ?></label></td><td class="row2"><input type="checkbox" <?php if(getConfig('w3c_vxhtml10')=='1') echo('checked="checked" '); ?> id="w3c-vxhtml10" name="w3c-vxhtml10" /></td></tr>
      <tr><td class="row1"><label for="w3c-vxhtml11"><?php echo $lang->get('acpgc_w3clogos_btn_xhtml11'); ?></label></td><td class="row1"><input type="checkbox" <?php if(getConfig('w3c_vxhtml11')=='1') echo('checked="checked" '); ?> id="w3c-vxhtml11" name="w3c-vxhtml11" /></td></tr>
      <tr><td class="row2"><label for="w3c-vcss"><?php     echo $lang->get('acpgc_w3clogos_btn_css');     ?></label></td><td class="row2"><input type="checkbox" <?php if(getConfig('w3c_vcss')=='1')     echo('checked="checked" '); ?> id="w3c-vcss"     name="w3c-vcss"     /></td></tr>

    <!-- DefectiveByDesign.org ad -->      
      
      <tr>
        <th class="subhead" colspan="2">
          <?php echo $lang->get('acpgc_heading_dbd'); ?>
        </th>
      </tr>
      
      <tr>
        <td colspan="2" class="row3">
          <b><?php echo $lang->get('acpgc_dbd_intro'); ?></b>
          <?php echo $lang->get('acpgc_dbd_explain'); ?>
        </td>
      </tr>
      
      <tr>
        <td class="row1">
          <label for="dbdbutton">
            <?php echo $lang->get('acpgc_field_stopdrm'); ?>
          </label>
        </td>
        <td class="row1">
          <input type="checkbox" name="dbdbutton" id="dbdbutton" <?php if(getConfig('dbd_button')=='1')  echo('checked="checked" '); ?>/>
        </td>
      </tr>
      
    <!-- Save button -->
    
    </table>
    </div>
        
    <div class="tblholder">
    <table border="0" width="100%" cellspacing="1" cellpadding="4">
      
      <tr><th colspan="2"><input type="submit" name="submit" value="<?php echo $lang->get('acpgc_btn_save_changes'); ?>" /></th></tr>
      
    </table>
  </div>
</form>
  <?php
}

function page_Admin_UploadConfig()
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
  
  if(isset($_POST['save']))
  {
    if(isset($_POST['enable_uploads']) && getConfig('enable_uploads') != '1')
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'upload_enable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('enable_uploads', '1');
    }
    else if ( !isset($_POST['enable_uploads']) && getConfig('enable_uploads') == '1' )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'upload_disable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('enable_uploads', '0');
    }
    if(isset($_POST['enable_imagemagick']) && getConfig('enable_imagemagick') != '1')
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'magick_enable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('enable_imagemagick', '1');
    }
    else if ( !isset($_POST['enable_imagemagick']) && getConfig('enable_imagemagick') == '1' )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'magick_disable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('enable_imagemagick', '0');
    }
    if(isset($_POST['cache_thumbs']))
    {
      setConfig('cache_thumbs', '1');
    }
    else
    {
      setConfig('cache_thumbs', '0');
    }
    if(isset($_POST['file_history']) && getConfig('file_history') != '1' )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'filehist_enable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('file_history', '1');
    }
    else if ( !isset($_POST['file_history']) && getConfig('file_history') == '1' )
    {
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author) VALUES(\'security\',\'filehist_disable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('file_history', '0');
    }
    if(file_exists($_POST['imagemagick_path']) && $_POST['imagemagick_path'] != getConfig('imagemagick_path'))
    {
      $old = getConfig('imagemagick_path');
      $oldnew = "{$old}||{$_POST['imagemagick_path']}";
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES(\'security\',\'magick_path\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\',\'' . $db->escape($oldnew) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('imagemagick_path', $_POST['imagemagick_path']);
    }
    else if ( $_POST['imagemagick_path'] != getConfig('imagemagick_path') )
    {
      echo '<span style="color: red"><b>Warning:</b> the file "'.htmlspecialchars($_POST['imagemagick_path']).'" was not found, and the ImageMagick file path was not updated.</span>';
    }
    $max_upload = floor((float)$_POST['max_file_size'] * (int)$_POST['fs_units']);
    if ( $max_upload > 1048576 && defined('ENANO_DEMO_MODE') )
    {
      echo '<div class="error-box">Wouldn\'t want the server DoS\'ed now. Stick to under a megabyte for the demo, please.</div>';
    }
    else
    {
      setConfig('max_file_size', $max_upload.'');
    }
  }
  echo '<form name="main" action="'.htmlspecialchars(makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module'])).'" method="post">';
  ?>
  <h3><?php echo $lang->get('acpup_heading_main'); ?></h3>
  
  <p>
    <?php echo $lang->get('acpup_intro'); ?>
  </p>
  <p>
    <label>
      <input type="checkbox" name="enable_uploads" <?php if(getConfig('enable_uploads')=='1') echo 'checked="checked"'; ?> />
      <b><?php echo $lang->get('acpup_field_enable'); ?></b>
    </label>
  </p>
  <p>
    <?php echo $lang->get('acpup_field_max_size'); ?>
    <input name="max_file_size" onkeyup="if(!this.value.match(/^([0-9\.]+)$/ig)) this.value = this.value.substr(0,this.value.length-1);" value="<?php echo getConfig('max_file_size'); ?>" />
    <select name="fs_units">
      <option value="1" selected="selected"><?php echo $lang->get('etc_unit_bytes'); ?></option>
      <option value="1024"><?php echo $lang->get('etc_unit_kilobytes_short'); ?></option>
      <option value="1048576"><?php echo $lang->get('etc_unit_megabytes_short'); ?></option>
    </select>
  </p>
  
  <p><?php echo $lang->get('acpup_info_magick'); ?></p>
  <p>
    <label>
      <input type="checkbox" name="enable_imagemagick" <?php if(getConfig('enable_imagemagick')=='1') echo 'checked="checked"'; ?> />
      <?php echo $lang->get('acpup_field_magick_enable'); ?>
    </label>
    <br />
    <?php echo $lang->get('acpup_field_magick_path'); ?> <input type="text" name="imagemagick_path" value="<?php if(getConfig('imagemagick_path')) echo getConfig('imagemagick_path'); else echo '/usr/bin/convert'; ?>" /><br />
    <?php echo $lang->get('acpup_field_magick_path_hint'); ?>
  </p>
     
  <p><?php echo $lang->get('acpup_info_cache'); ?></p>
  <p>
    <?php echo $lang->get('acpup_info_cache_chmod'); ?>
  
    <?php
      if(!is_writable(ENANO_ROOT.'/cache/'))
        echo $lang->get('acpup_msg_cache_not_writable');
    ?>
  </p>
  
  <p>
    <label>
      <input type="checkbox" name="cache_thumbs" <?php if(getConfig('cache_thumbs')=='1' && is_writable(ENANO_ROOT.'/cache/')) echo 'checked="checked"'; else if ( ! is_writable(ENANO_ROOT . '/cache/') ) echo 'readonly="readonly"'; ?> />
      <?php echo $lang->get('acpup_field_cache'); ?>
    </label>
  </p>
  
  <p><?php echo $lang->get('acpup_info_history'); ?></p>
  <p>
    <label>
      <input type="checkbox" name="file_history" <?php if(getConfig('file_history')=='1') echo 'checked="checked"'; ?> />
      <?php echo $lang->get('acpup_field_history'); ?>
    </label>
  </p>
  
  <hr style="margin-left: 1em;" />
  <p><input type="submit" name="save" value="<?php echo $lang->get('acpup_btn_save'); ?>" style="font-weight: bold;" /></p>
  <?php
  echo '</form>';
}

function page_Admin_UploadAllowedMimeTypes()
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
  
  global $mime_types, $mimetype_exps, $mimetype_extlist;
  if(isset($_POST['save']) && !defined('ENANO_DEMO_MODE'))
  {
    $bits = '';
    $keys = array_keys($mime_types);
    foreach($keys as $i => $k)
    {
      if(isset($_POST['ext_'.$k])) $bits .= '1';
      else $bits .= '0';
    }
    $bits = compress_bitfield($bits);
    setConfig('allowed_mime_types', $bits);
    echo '<div class="info-box">' . $lang->get('acpft_msg_saved') . '</div>';
  }
  else if ( isset($_POST['save']) && defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">' . $lang->get('acpft_msg_demo_mode') . '</div>';
  }
  $allowed = fetch_allowed_extensions();
  ?>
  <h3><?php echo $lang->get('acpft_heading_main'); ?></h3>
   <p><?php echo $lang->get('acpft_hint'); ?></p>
  <?php
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', (( isset($_GET['sqldbg'])) ? 'sqldbg&amp;' : '') .'module='.$paths->cpage['module']).'" method="post">';
    $c = -1;
    $t = -1;
    $cl = 'row1';
    echo "\n".'    <div class="tblholder">'."\n".'      <table cellspacing="1" cellpadding="2" style="margin: 0; padding: 0;" border="0">'."\n".'        <tr>'."\n        ";
    ksort($mime_types);
    foreach($mime_types as $e => $m)
    {
      $c++;
      $t++;
      if($c == 3)
      {
        $c = 0;
        $cl = ( $cl == 'row1' ) ? 'row2' : 'row1';
        echo '</tr>'."\n".'        <tr>'."\n        ";
      }
      $seed = "extchkbx_{$e}_".md5(microtime() . mt_rand());
      $chk = (!empty($allowed[$e])) ? ' checked="checked"' : '';
      echo "  <td class='$cl'>\n            <label><input id='{$seed}' type='checkbox' name='ext_{$e}'{$chk} />.{$e}\n            ({$m})</label>\n          </td>\n        ";
    }
    while($c < 2)
    {
      $c++;
      echo "  <td class='{$cl}'></td>\n        ";
    }
    echo '<tr><th class="subhead" colspan="3"><input type="submit" name="save" value="' . $lang->get('etc_save_changes') . '" /></th></tr>';
    echo '</tr>'."\n".'      </table>'."\n".'    </div>';
    echo '</form>';
  ?>
  <?php
}

function page_Admin_PluginManager()
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
  
  if(isset($_GET['action']))
  {
    if ( !isset($_GET['plugin']) )
    {
      echo '<div class="error-box">No plugin specified.</div>';
    }
    else if ( !preg_match('/^[A-z0-9_-]+\.php$/', $_GET['plugin']) )
    {
      echo '<div class="error-box">Hacking attempt</div>';
    }
    else
    {
      $plugin =& $_GET['plugin'];
      switch($_GET['action'])
      {
        case "enable":
          $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES(\'security\',\'plugin_enable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',"' . $db->escape($session->username) . '","' . $db->escape($_GET['plugin']) . '");');
          if ( !$q )
            $db->_die();
          setConfig("plugin_$plugin", '1');
          break;
        case "disable":
          if ( defined('ENANO_DEMO_MODE') && strstr($_GET['plugin'], 'Demo') )
          {
            echo('<h3>' . $lang->get('acppl_err_heading') . '</h3>
                   <p>' . $lang->get('acppl_err_demo_plugin') . '</p>');
            break;
          }
          if ( !in_array($plugin, $plugins->system_plugins) )
          {
            $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES(\'security\',\'plugin_disable\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',"' . $db->escape($session->username) . '","' . $db->escape($_GET['plugin']) . '");');
            if ( !$q )
              $db->_die();
            setConfig("plugin_$plugin", '0');
          }
          else 
          {
            echo '<h3>' . $lang->get('acppl_err_heading') . '</h3>
                   <p>' . $lang->get('acppl_err_system_plugin') . '</p>';
          }
          break;
      }
    }
  }
  $dir = './plugins/';
  $plugin_list = Array();
  $system = Array();
  $show_system = ( isset($_GET['show_system']) && $_GET['show_system'] == 'yes' );
  
  if (is_dir($dir))
  {
    if ($dh = opendir($dir))
    {
      while (($file = readdir($dh)) !== false)
      {
        if(preg_match('#^(.*?)\.php$#is', $file) && $file != 'index.php')
        {
          unset($thelist);
          if ( in_array($file, $plugins->system_plugins) )
          {
            if ( !$show_system )
              continue;
            $thelist =& $system;
          }
          else
          {
            $thelist =& $plugin_list;
          }
          $f = file_get_contents($dir . $file);
          $f = explode("\n", $f);
          $f = array_slice($f, 2, 7);
          $f[0] = substr($f[0], 13, strlen($f[0]));
          $f[1] = substr($f[1], 12, strlen($f[1]));
          $f[2] = substr($f[2], 13, strlen($f[2]));
          $f[3] = substr($f[3], 8,  strlen($f[3]));
          $f[4] = substr($f[4], 9,  strlen($f[4]));
          $f[5] = substr($f[5], 12, strlen($f[5]));
          $thelist[$file] = Array();
          $thelist[$file]['name'] = $f[0];
          $thelist[$file]['uri']  = $f[1];
          $thelist[$file]['desc'] = $f[2];
          $thelist[$file]['auth'] = $f[3];
          $thelist[$file]['vers'] = $f[4];
          $thelist[$file]['aweb'] = $f[5];
          
          if ( preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $thelist[$file]['name']) )
            $thelist[$file]['name'] = $lang->get($thelist[$file]['name']);
          
          if ( preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $thelist[$file]['desc']) )
            $thelist[$file]['desc'] = $lang->get($thelist[$file]['desc']);
          
        }
      }
      closedir($dh);
    }
    else
    {
      echo '<div class="error-box">' . $lang->get('acppl_err_open_dir') . '</div>';
      return;
    }
  }
  else
  {
    echo '<div class="error-box">' . $lang->get('acppl_err_missing_dir') . '</div>';
    return;
  }
  echo('<div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">
      <tr>
        <th>' . $lang->get('acppl_col_filename') . '</th>
        <th>' . $lang->get('acppl_col_name') . '</th>
        <th>' . $lang->get('acppl_col_description') . '</th>
        <th>' . $lang->get('acppl_col_author') . '</th>
        <th>' . $lang->get('acppl_col_version') . '</th>
        <th></th>
      </tr>');
    $plugin_files_1 = array_keys($plugin_list);
    $plugin_files_2 = array_keys($system);
    $plugin_files = array_values(array_merge($plugin_files_1, $plugin_files_2));
    $cls = 'row2';
    for ( $i = 0; $i < sizeof($plugin_files); $i++ )
    {
      $cls = ( $cls == 'row2' ) ? 'row3' : 'row2';
      $this_plugin = ( isset($system[$plugin_files[$i]]) ) ? $system[$plugin_files[$i]] : $plugin_list[$plugin_files[$i]];
      $is_system = ( $system[$plugin_files[$i]] );
      $bgcolor = '';
      if ( $is_system && $cls == 'row2' )
        $bgcolor = ' style="background-color: #FFD8D8;"';
      else if ( $is_system && $cls == 'row3' )
        $bgcolor = ' style="background-color: #FFD0D0;"';
      echo '<tr>
              <td class="'.$cls.'"'.$bgcolor.'>'.$plugin_files[$i].'</td>
              <td class="'.$cls.'"'.$bgcolor.'><a href="'.$this_plugin['uri'].'">'.$this_plugin['name'].'</a></td>
              <td class="'.$cls.'"'.$bgcolor.'>'.$this_plugin['desc'].'</td>
              <td class="'.$cls.'"'.$bgcolor.'><a href="'.$this_plugin['aweb'].'">'.$this_plugin['auth'].'</a></td>
              <td class="'.$cls.'"'.$bgcolor.'>'.$this_plugin['vers'].'</td>
              <td class="'.$cls.'"'.$bgcolor.'>';
      if ( !in_array($plugin_files[$i], $plugins->system_plugins) )
      {
        if ( getConfig('plugin_'.$plugin_files[$i]) == '1' )
        {
          echo '<a href="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'&amp;show_system=' . ( $show_system ? 'yes' : 'no' ) . '&amp;action=disable&amp;plugin='.$plugin_files[$i].'">' . $lang->get('acppl_btn_disable') . '</a>';
        }
        else
        {
          echo '<a href="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'&amp;show_system=' . ( $show_system ? 'yes' : 'no' ) . '&amp;action=enable&amp;plugin='.$plugin_files[$i].'">' . $lang->get('acppl_btn_enable') . '</a>';
        }
      }
      else
      {
        echo $lang->get('acppl_lbl_system_plugin');
      }
      echo '</td></tr>';
    }
    $showhide_link = ( $show_system ) ?
    '<a style="color: white;" href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->cpage['module'] . '&show_system=no', true) . '">' . $lang->get('acppl_btn_hide_system') . '</a>' :
    '<a style="color: white;" href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->cpage['module'] . '&show_system=yes', true) . '">' . $lang->get('acppl_btn_show_system') . '</a>' ;
    echo '<tr><th colspan="6" class="subhead">'.$showhide_link.'</th></tr>';
    echo '</table></div>';
}

/*
function page_Admin_PageManager()
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
  
  echo '<h2>Page management</h2>';
  
  if ( isset($_POST['search']) || isset($_POST['select']) || ( isset($_GET['source']) && $_GET['source'] == 'ajax' ) )
  {
    // The object of the game: using only the text a user entered, guess the page ID and namespace. *sigh* I HATE writing search algorithms...
    $source = ( isset($_GET['source']) ) ? $_GET['source'] : false;
    if ( $source == 'ajax' )
    {
      $_POST['search'] = true;
      $_POST['page_url'] = $_GET['page_id'];
    }
    if ( isset($_POST['search']) )
    {
      $pid = $_POST['page_url'];
    }
    elseif ( isset($_POST['select']) )
    {
      $pid = $_POST['page_force_url'];
    }
    else
    {
      echo 'Internal error selecting page search terms';
      return false;
    }
    // Look for a namespace prefix in the urlname, and assign a different namespace, if necessary
    $k = array_keys($paths->nslist);
    for ( $i = 0; $i < sizeof($paths->nslist); $i++ )
    {
      $ln = strlen($paths->nslist[$k[$i]]);
      if(substr($pid, 0, $ln) == $paths->nslist[$k[$i]])
      {
        $ns = $k[$i];
        $page_id = substr($pid, $ln, strlen($pid));
      }
    }
    // The namespace is in $ns and the page name or ID (we don't know which yet) is in $page_id
    // Now, iterate through $paths->pages searching for a page with this name or ID
    for ( $i = 0; $i < sizeof($paths->pages) / 2; $i++ )
    {
      if ( !isset($final_pid) )
      {
        if ( $paths->pages[$i]['urlname_nons'] == str_replace(' ', '_', $page_id) )
        {
          $final_pid = str_replace(' ', '_', $page_id);
        }
        else if ( $paths->pages[$i]['name'] == $page_id )
        {
          $final_pid = $paths->pages[$i]['urlname_nons'];
        }
        else if ( strtolower($paths->pages[$i]['urlname_nons']) == strtolower(str_replace(' ', '_', $page_id)) )
        {
          $final_pid = $paths->pages[$i]['urlname_nons'];
        }
        else if ( strtolower($paths->pages[$i]['name']) == strtolower(str_replace('_', ' ', $page_id)) )
        {
          $final_pid = $paths->pages[$i]['urlname_nons'];
        }
        if ( isset($final_pid) )
        {
          $_POST['name'] = $paths->pages[$i]['name'];
          $_POST['urlname'] = $paths->pages[$i]['urlname_nons'];
        }
      }
    }
    if ( !isset($final_pid) )
    {
      echo 'The page you searched for cannot be found. <a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'PageManager\'); return false;">Back</a>';
      return false;
    }
    $_POST['namespace'] = $ns;
    $_POST['old_namespace'] = $ns;
    $_POST['page_id'] = $final_pid;
    $_POST['old_page_id'] = $final_pid;
    if ( !isset($paths->pages[$paths->nslist[$_POST['namespace']].$_POST['urlname']]) )
    {
      echo 'The page you searched for cannot be found. <a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'PageManager\'); return false;">Back</a>';
      return false;
    }
  }
  
  if ( isset($_POST['page_id']) && isset($_POST['namespace']) && !isset($_POST['cancel']) )
  {
    $cpage = $paths->pages[$paths->nslist[$_POST['old_namespace']].$_POST['old_page_id']];
    if(isset($_POST['submit']))
    {
      switch(true)
      {
        case true:
          // Create a list of things to update
          $page_info = Array(
              'name'=>$_POST['name'],
              'urlname'=>sanitize_page_id($_POST['page_id']),
              'namespace'=>$_POST['namespace'],
              'special'=>isset($_POST['special']) ? '1' : '0',
              'visible'=>isset($_POST['visible']) ? '1' : '0',
              'comments_on'=>isset($_POST['comments_on']) ? '1' : '0',
              'protected'=>isset($_POST['protected']) ? '1' : '0'
            );
          
          $updating_urlname_or_namespace = ( $page_info['namespace'] != $cpage['namespace'] || $page_info['urlname'] != $cpage['urlname_nons'] );
          
          if ( !isset($paths->nslist[ $page_info['namespace'] ]) )
          {
            echo '<div class="error-box">The namespace you selected is not properly registered.</div>';
            break;
          }
          if ( isset($paths->pages[ $paths->nslist[$page_info['namespace']] . $page_info[ 'urlname' ] ]) && $updating_urlname_or_namespace )
          {
            echo '<div class="error-box">There is already a page that exists with that URL string and namespace.</div>';
            break;
          }
          // Build the query
          $q = 'UPDATE '.table_prefix.'pages SET ';
          $k = array_keys($page_info);
          foreach($k as $c)
          {
            $q .= $c.'=\''.$db->escape($page_info[$c]).'\',';
          }
          $q = substr($q, 0, strlen($q)-1);
          // Build the WHERE statements
          $q .= ' WHERE ';
          $k = array_keys($cpage);
          if ( !isset($cpage) )
            die('[internal] no cpage');
          foreach($k as $c)
          {
            if($c != 'urlname_nons' && $c != 'urlname' && $c != 'really_protected')
            {
              $q .= $c.'=\''.$db->escape($cpage[$c]).'\' AND ';
            }
            else if($c == 'urlname')
            {
              $q .= $c.'=\''.$db->escape($cpage['urlname_nons']).'\' AND ';
            }
          }
          // Trim off the last " AND " and append a semicolon
          $q = substr($q, 0, strlen($q)-5) . ';';
          // Send the completed query to MySQL
          $e = $db->sql_query($q);
          if(!$e) $db->_die('The page data could not be updated.');
          // Update any additional tables
          $q = Array(
            'UPDATE '.table_prefix.'categories SET page_id=\''.$page_info['urlname'].'\',namespace=\''.$page_info['namespace'].'\' WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
            'UPDATE '.table_prefix.'comments   SET page_id=\''.$page_info['urlname'].'\',namespace=\''.$page_info['namespace'].'\' WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
            'UPDATE '.table_prefix.'logs       SET page_id=\''.$page_info['urlname'].'\',namespace=\''.$page_info['namespace'].'\' WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
            'UPDATE '.table_prefix.'page_text  SET page_id=\''.$page_info['urlname'].'\',namespace=\''.$page_info['namespace'].'\' WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
            'UPDATE '.table_prefix.'acl        SET page_id=\''.$page_info['urlname'].'\',namespace=\''.$page_info['namespace'].'\' WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';'
            );
          foreach($q as $cq)
          {
            $e = $db->sql_query($cq);
            if(!$e) $db->_die('Some of the additional tables containing page information could not be updated.');
          }
          // Update $cpage
          $cpage = $page_info;
          $cpage['urlname_nons'] = $cpage['urlname'];
          $cpage['urlname'] = $paths->nslist[$cpage['namespace']].$cpage['urlname'];
          $_POST['old_page_id'] = $page_info['urlname'];
          $_POST['old_namespace'] = $page_info['namespace'];
          echo '<div class="info-box">Your changes have been saved.</div>';
          break;
      }
    } elseif(isset($_POST['delete'])) {
      $q = Array(
        'DELETE FROM '.table_prefix.'categories WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
        'DELETE FROM '.table_prefix.'comments   WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
        'DELETE FROM '.table_prefix.'logs       WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
        'DELETE FROM '.table_prefix.'page_text  WHERE page_id=\'' . $db->escape($_POST['old_page_id']) . '\' AND namespace=\'' . $db->escape($_POST['old_namespace']) . '\';',
        );
      foreach($q as $cq)
      {
        $e = $db->sql_query($cq);
        if(!$e) $db->_die('Some of the additional tables containing page information could not be updated.');
      }
      
      if(!$db->sql_query(
        'DELETE FROM '.table_prefix.'pages WHERE urlname="'.$db->escape($_POST['old_page_id']).'" AND namespace="'.$db->escape($_POST['old_namespace']).'";'
      )) $db->_die('The page could not be deleted.');
      echo '<div class="info-box">This page has been deleted.</p><p><a href="javascript:ajaxPage(\''.$paths->nslist['Admin'].'PageManager\');">Return to Page manager</a><br /><a href="javascript:ajaxPage(\''.$paths->nslist['Admin'].'Home\');">Admin home</a></div>';
      return;
    }
    $url = makeUrlNS('Special', 'Administration', 'module='.$paths->cpage['module'], true);
    echo '<form action="'.$url.'" method="post">';
    ?>
    <h3>Modify page: <?php echo htmlspecialchars($_POST['name']); ?></h3>
     <table border="0">
       <tr>
         <td>Namespace:</td>
         <td>
           <select name="namespace">
             <?php
             $nm = array_keys($paths->nslist);
             foreach ( $nm as $ns )
             {
               if ( $ns != 'Special' && $ns != 'Admin' )
               {
                 echo '<option ';
                 if ( $_POST['namespace'] == $ns )
                 echo 'selected="selected" ';
                 echo 'value="'.$ns.'">';
                 if ( $paths->nslist[$ns] == '' )
                   echo '[No prefix]';
                 else
                   echo $paths->nslist[$ns];
                 echo '</option>';
               }
             } ?>
           </select>
         </td>
       </tr>
       <tr>
         <td>
           Page title:
         </td>
         <td>
           <input type="text" name="name" value="<?php echo htmlspecialchars($cpage['name']); ?>" />
         </td>
       </tr>
       <tr>
         <td>
           Page URL string:<br />
           <small>No spaces, and don't enter the namespace prefix (e.g. User:).<br />
                  Changing this value is usually not a good idea, especially for templates and project pages.</small>
          </td>
          <td>
            <input type="text" name="page_id" value="<?php echo htmlspecialchars(dirtify_page_id($cpage['urlname_nons'])); ?>" />
          </td>
       </tr>
       <tr>
         <td></td>
         <td>
           <input <?php if($cpage['comments_on']) echo 'checked="checked"'; ?> name="comments_on" type="checkbox" id="cmt" />
           <label for="cmt">Enable comments for this page</label>
         </td>
       </tr>
       <tr>
         <td></td>
         <td>
           <input <?php if($cpage['special']) echo 'checked="checked"'; ?> name="special" type="checkbox" id="spc" />
           <label for="spc">Bypass the template engine for this page</label><br />
           <small>This option enables you to use your own HTML headers and other code. It is recommended that only advanced users enable this feature. As with other Enano pages, you may use PHP code in your pages, meaning you can use Enano's API on the page.</small>
         </td>
       </tr>
       <tr>
         <td></td>
         <td>
           <input <?php if($cpage['visible']) echo 'checked="checked"'; ?> name="visible" type="checkbox" id="vis" />
           <label for="vis">Allow this page to be shown in page lists</label><br />
           <small>Unchecking this checkbox prevents the page for being indexed for searching. The index is rebuilt each time a page is saved, and you can force an index rebuild by going to the page <?php echo $paths->nslist['Special']; ?>SearchRebuild.</small>
         </td>
       </tr>
       <tr>
         <td></td>
         <td>
           <input <?php if($cpage['protected']) echo 'checked="checked"'; ?> name="protected" type="checkbox" id="prt" />
           <label for="prt">Prevent non-administrators from editing this page</label><br />
           <small>This option only has an effect when Wiki Mode is enabled.</small>
         </td>
       </tr>
       <tr>
         <td></td>
         <td>
           <input type="submit" name="delete" value="Delete page" style="color: red" onclick="return confirm('Do you REALLY want to delete this page?')" />
         </td>
       </tr>
       <tr>
         <td colspan="2" style="text-align: center;">
           <hr />
         </td>
       </tr>
       <tr>
         <td colspan="2" style="text-align: right;">
           <input type="hidden" name="old_page_id" value="<?php echo htmlspecialchars($_POST['old_page_id']); ?>" />
           <input type="hidden" name="old_namespace" value="<?php echo htmlspecialchars($_POST['old_namespace']); ?>" />
           <input type="Submit" name="submit" value="Save changes" style="font-weight: bold;" />
           <input type="submit" name="cancel" value="Cancel changes" />
         </td>
       </tr>
     </table>
    <?php
    echo '</form>';
  }
  else
  {
    echo '<h3>Please select a page</h3>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    ?>
      <p>Search for page title (remember prefixes like User: and File:) <?php echo $template->pagename_field('page_url'); ?>  <input type="submit" style="font-weight: bold;" name="search" value="Search" /></p>
      <p>Select page title from a list: <select name="page_force_url">
      <?php
        for($i=0;$i<sizeof($paths->pages)/2;$i++)
        {
          if($paths->pages[$i]['namespace'] != 'Admin' && $paths->pages[$i]['namespace'] != 'Special') echo '<option value="'.$paths->nslist[$paths->pages[$i]['namespace']].$paths->pages[$i]['urlname_nons'].'">'.htmlspecialchars($paths->nslist[$paths->pages[$i]['namespace']].$paths->pages[$i]['name']).'</option>'."\n";
        }
      ?>
      </select>  <input type="submit" name="select" value="Select" /></p>
    <?php
    echo '</form>';
    
  }
}
*/

function page_Admin_PageEditor()
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
  
  
  echo '<h2>Edit page content</h2>';
  
  if(isset($_POST['search']) || isset($_POST['select'])) {
    // The object of the game: using only the text a user entered, guess the page ID and namespace. *sigh* I HATE writing search algorithms...
    if(isset($_POST['search'])) $pid = $_POST['page_url'];
    elseif(isset($_POST['select'])) $pid = $_POST['page_force_url'];
    else { echo 'Internal error selecting page search terms'; return false; }
    // Look for a namespace prefix in the urlname, and assign a different namespace, if necessary
    $k = array_keys($paths->nslist);
    for($i=0;$i<sizeof($paths->nslist);$i++)
    {
      $ln = strlen($paths->nslist[$k[$i]]);
      if(substr($pid, 0, $ln) == $paths->nslist[$k[$i]])
      {
        $ns = $k[$i];
        $page_id = substr($pid, $ln, strlen($pid));
      }
    }
    // The namespace is in $ns and the page name or ID (we don't know which yet) is in $page_id
    // Now, iterate through $paths->pages searching for a page with this name or ID
    for($i=0;$i<sizeof($paths->pages)/2;$i++)
    {
      if(!isset($final_pid))
      {
        if    ($paths->pages[$i]['urlname_nons'] == str_replace(' ', '_', $page_id)) $final_pid = str_replace(' ', '_', $page_id);
        elseif($paths->pages[$i]['name'] == $page_id) $final_pid = $paths->pages[$i]['urlname_nons'];
        elseif(strtolower($paths->pages[$i]['urlname_nons']) == strtolower(str_replace(' ', '_', $page_id))) $final_pid = $paths->pages[$i]['urlname_nons'];
        elseif(strtolower($paths->pages[$i]['name']) == strtolower(str_replace('_', ' ', $page_id))) $final_pid = $paths->pages[$i]['urlname_nons'];
        if(isset($final_pid)) { $_POST['name'] = $paths->pages[$i]['name']; $_POST['urlname'] = $paths->pages[$i]['urlname_nons']; }
      }
    }
    if(!isset($final_pid)) { echo 'The page you searched for cannot be found. <a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'PageManager\'); return false;">Back</a>'; return false; }
    $_POST['namespace'] = $ns;
    $_POST['page_id'] = $final_pid;
    if(!isset($paths->pages[$paths->nslist[$_POST['namespace']].$_POST['urlname']])) { echo 'The page you searched for cannot be found. <a href="#" onclick="ajaxPage(\''.$paths->nslist['Admin'].'PageManager\'); return false;">Back</a>'; return false; }
  }
  
  if(isset($_POST['page_id']) && !isset($_POST['cancel']))
  {
    echo '<form name="main" action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">';
    if(!isset($_POST['content']) || isset($_POST['revert'])) $content = RenderMan::getPage($_POST['page_id'], $_POST['namespace'], 0, false, false, false, false);
    else $content = $_POST['content'];
    if(isset($_POST['save']))
    {
      $data = $content;
      $id = md5( microtime() . mt_rand() );
      
      $minor = isset($_POST['minor']) ? 'true' : 'false';
      $q='INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,page_id,namespace,page_text,char_tag,author,edit_summary,minor_edit) VALUES(\'page\', \'edit\', '.time().', \''.date('d M Y h:i a').'\', \'' . $db->escape($_POST['page_id']) . '\', \'' . $db->escape($_POST['namespace']) . '\', \''.$db->escape($data).'\', \''.$id.'\', \''.$session->username.'\', \''.$db->escape(htmlspecialchars($_POST['summary'])).'\', '.$minor.');';
      if(!$db->sql_query($q)) $db->_die('The history (log) entry could not be inserted into the logs table.');
      
      $query = 'UPDATE '.table_prefix.'page_text SET page_text=\''.$db->escape($data).'\',char_tag=\''.$id.'\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'' . $db->escape($_POST['namespace']) . '\';';
      $e = $db->sql_query($query);
      if(!$e) echo '<div class="warning-box">The page data could not be saved. MySQL said: '.mysql_error().'<br /><br />Query:<br /><pre>'.$query.'</pre></div>';
      else echo '<div class="info-box">Your page has been saved. <a href="'.makeUrlNS($_POST['namespace'], $_POST['page_id']).'">View page...</a></div>';
    } elseif(isset($_POST['preview'])) {
      echo '<h3>Preview</h3><p><b>Reminder:</b> This is only a preview; your changes to this page have not yet been saved.</p><div style="margin: 1em; padding: 10px; border: 1px dashed #606060; background-color: #F8F8F8; max-height: 200px; overflow: auto;">'.RenderMan::render($content).'</div>';
    }
    ?>
    <p>
    <textarea name="content" rows="20" cols="60" style="width: 100%;"><?php echo htmlspecialchars($content); ?></textarea><br />
    Edit summary: <input name="summary" value="<?php if(isset($_POST['summary'])) echo htmlspecialchars($_POST['summary']); ?>" size="40" /><br />
    <label><input type="checkbox" name="minor" <?php if(isset($_POST['minor'])) echo 'checked="checked" '; ?>/>  This is a minor edit</label>
    </p>
    <p>
    <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($_POST['page_id']); ?>" />
    <input type="hidden" name="namespace" value="<?php echo htmlspecialchars($_POST['namespace']); ?>" />
    <input type="submit" name="save" value="Save changes" style="font-weight: bold;" />&nbsp;&nbsp;<input type="submit" name="preview" value="Show preview" />&nbsp;&nbsp;<input type="submit" name="revert" value="Revert changes" onclick="return confirm('Do you really want to revert your changes?');" />&nbsp;&nbsp;<input type="submit" name="cancel" value="Cancel" onclick="return confirm('Do you really want to cancel your changes?');" />
    </p>
    <?php
    echo '</form>';
  } else {
    echo '<h3>Please select a page</h3>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    ?>
      <p>Search for page title (remember prefixes like User: and File:) <?php echo $template->pagename_field('page_url'); ?>  <input type="submit" style="font-weight: bold;" name="search" value="Search" /></p>
      <p>Select page title from a list: <select name="page_force_url">
      <?php
        for ( $i = 0; $i < sizeof($paths->pages) / 2; $i++ )
        {
          if($paths->pages[$i]['namespace'] != 'Admin' && $paths->pages[$i]['namespace'] != 'Special') echo '<option value="'.$paths->nslist[$paths->pages[$i]['namespace']].$paths->pages[$i]['urlname_nons'].'">'.$paths->nslist[$paths->pages[$i]['namespace']].$paths->pages[$i]['name'].'</option>'."\n";
        }
      ?>
      </select>  <input type="submit" name="select" value="Select" /></p>
    <?php
    echo '</form>';
  }
}

function page_Admin_ThemeManager() 
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
  
  
  // Get the list of styles in the themes/ dir
  $h = opendir('./themes');
  $l = Array();
  if(!$h) die('Error opening directory "./themes" for reading.');
  while(false !== ($n = readdir($h))) {
    if($n != '.' && $n != '..' && is_dir('./themes/'.$n))
      $l[] = $n;
  }
  closedir($h);
  echo('
  <h3>Theme Management</h3>
   <p>Install, uninstall, and manage Enano themes.</p>
  ');
  if(isset($_POST['disenable'])) {
    $q = 'SELECT enabled FROM '.table_prefix.'themes WHERE theme_id=\'' . $db->escape($_POST['theme_id']) . '\'';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting enabled/disabled state value: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($s);
    $db->free_result();
    if($r[0] == 1) $e = 0;
    else $e = 1;
    $s=true;
    if($e==0)
    {
      $c = $db->sql_query('SELECT * FROM '.table_prefix.'themes WHERE enabled=1');
      if(!$c) $db->_die('The backup check for having at least on theme enabled failed.');
      if($db->numrows() <= 1) { echo '<div class="warning-box">You cannot disable the last remaining theme.</div>'; $s=false; }
    }
    $db->free_result();
    if($s) {
    $q = 'UPDATE '.table_prefix.'themes SET enabled='.$e.' WHERE theme_id=\'' . $db->escape($_POST['theme_id']) . '\'';
    $a = $db->sql_query($q);
    if(!$a) die('Error updating enabled/disabled state value: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    else echo('<div class="info-box">The theme "'.$_POST['theme_id'].'" has been  '. ( ( $e == '1' ) ? 'enabled' : 'disabled' ).'.</div>');
    }
  }
  elseif(isset($_POST['edit'])) {
    
    $dir = './themes/'.$_POST['theme_id'].'/css/';
    $list = Array();
    // Open a known directory, and proceed to read its contents
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
          if(preg_match('#^(.*?)\.css$#is', $file) && $file != '_printable.css') {
            $list[$file] = capitalize_first_letter(substr($file, 0, strlen($file)-4));
          }
        }
        closedir($dh);
      }
    }
    $lk = array_keys($list);
    
    $q = 'SELECT theme_name,default_style FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\'';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting name value: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($s);
    $db->free_result();
    echo('<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">');
    echo('<div class="question-box">
          Theme name displayed to users: <input type="text" name="name" value="'.$r[0].'" /><br /><br />
          Default stylesheet: <select name="defaultcss">');
    foreach ($lk as $l)
    {
      if($r[1] == $l) $v = ' selected="selected"';
      else $v = '';
      echo "<option value='{$l}'$v>{$list[$l]}</option>";
    }
    echo('</select><br /><br />
          <input type="submit" name="editsave" value="OK" /><input type="hidden" name="theme_id" value="'.$_POST['theme_id'].'" />
          </div>');
    echo('</form>');
  }
  elseif(isset($_POST['editsave'])) {
    $q = 'UPDATE '.table_prefix.'themes SET theme_name=\'' . $db->escape($_POST['name']) . '\',default_style=\''.$db->escape($_POST['defaultcss']).'\' WHERE theme_id=\'' . $db->escape($_POST['theme_id']) . '\'';
    $s = $db->sql_query($q);
    if(!$s) die('Error updating name value: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    else echo('<div class="info-box">Theme data updated.</div>');
  }
  elseif(isset($_POST['up'])) {
    // If there is only one theme or if the selected theme is already at the top, do nothing
    $q = 'SELECT theme_order FROM '.table_prefix.'themes ORDER BY theme_order;';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\'';
    $sn = $db->sql_query($q);
    if(!$sn) die('Error selecting order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($sn);
    if( /* check for only one theme... */ $db->numrows($s) < 2 || $r[0] == 1 /* ...and check if this theme is already at the top */ ) { echo('<div class="warning-box">This theme is already at the top of the list, or there is only one theme installed.</div>'); } else {
      // Get the order IDs of the selected theme and the theme before it
      $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\'' . $db->escape($_POST['theme_id']) . '\'';
      $s = $db->sql_query($q);
      if(!$s) die('Error selecting order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $r = $db->fetchrow_num($s);
      $r = $r[0];
      $rb = $r - 1;
      // Thank God for jEdit's rectangular selection and the ablity to edit multiple lines at the same time ;)
      $q = 'UPDATE '.table_prefix.'themes SET theme_order=0 WHERE theme_order='.$rb.'';      /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$rb.' WHERE theme_order='.$r.''; /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$r.' WHERE theme_order=0';       /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      echo('<div class="info-box">Theme moved up.</div>');
    }
    $db->free_result($s);
    $db->free_result($sn);
  }
  elseif(isset($_POST['down'])) {
    // If there is only one theme or if the selected theme is already at the top, do nothing
    $q = 'SELECT theme_order FROM '.table_prefix.'themes ORDER BY theme_order;';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($s);
    if( /* check for only one theme... */ $db->numrows($s) < 2 || $r[0] == $db->numrows($s) /* ...and check if this theme is already at the bottom */ ) { echo('<div class="warning-box">This theme is already at the bottom of the list, or there is only one theme installed.</div>'); } else {
      // Get the order IDs of the selected theme and the theme before it
      $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\'';
      $s = $db->sql_query($q);
      if(!$s) die('Error selecting order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $r = $db->fetchrow_num($s);
      $r = $r[0];
      $rb = $r + 1;
      // Thank God for jEdit's rectangular selection and the ablity to edit multiple lines at the same time ;)
      $q = 'UPDATE '.table_prefix.'themes SET theme_order=0 WHERE theme_order='.$rb.'';      /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$rb.' WHERE theme_order='.$r.''; /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$r.' WHERE theme_order=0';       /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
      echo('<div class="info-box">Theme moved down.</div>');
    }
  }
  else if(isset($_POST['uninstall'])) 
  {
    $q = 'SELECT * FROM '.table_prefix.'themes;';
    $s = $db->sql_query($q);
    if ( !$s )
    {
      die('Error getting theme count: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    }
    $n = $db->numrows($s);
    $db->free_result();
    
    if ( $_POST['theme_id'] == 'oxygen' )
    {
      echo '<div class="error-box">The Oxygen theme is used by Enano for installation, upgrades, and error messages, and cannot be uninstalled.</div>';
    }
    else
    {
      if($n < 2)
      {
        echo '<div class="error-box">The theme could not be uninstalled because it is the only theme left.</div>';
      }
      else
      {
        $q = 'DELETE FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\' LIMIT 1;';
        $s = $db->sql_query($q);
        if ( !$s )
        {
          die('Error deleting theme data: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
        }
        else
        {
          echo('<div class="info-box">Theme uninstalled.</div>');
        }
      }
    }
  }
  elseif(isset($_POST['install'])) {
    $q = 'SELECT theme_id FROM '.table_prefix.'themes;';
    $s = $db->sql_query($q);
    if(!$s) die('Error getting theme count: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
    $n = $db->numrows($s);
    $n++;
    $theme_id = $_POST['theme_id'];
    $theme = Array();
    include('./themes/'.$theme_id.'/theme.cfg');
    if ( !isset($theme['theme_id']) )
    {
      echo '<div class="error-box">Could not load theme.cfg (theme metadata file)</div>';
    }
    else
    {
      $default_style = false;
      if ( $dh = opendir('./themes/' . $theme_id . '/css') )
      {
        while ( $file = readdir($dh) )
        {
          if ( $file != '_printable.css' && preg_match('/\.css$/i', $file) )
          {
            $default_style = $file;
            break;
          }
        }
        closedir($dh);
      }
      else
      {
        die('The /css subdirectory could not be located in the theme\'s directory');
      }
      
      if ( $default_style )
      {
        $q = 'INSERT INTO '.table_prefix.'themes(theme_id,theme_name,theme_order,enabled,default_style) VALUES(\''.$db->escape($theme['theme_id']).'\', \''.$db->escape($theme['theme_name']).'\', '.$n.', 1, \'' . $db->escape($default_style) . '\')';
        $s = $db->sql_query($q);
        if(!$s) die('Error inserting theme data: '.mysql_error().'<br /><u>SQL:</u><br />'.$q);
        else echo('<div class="info-box">Theme "'.$theme['theme_name'].'" installed.</div>');
      }
      else
      {
        echo '<div class="error-box">Could not determine the default style for the theme.</div>';
      }
    }
  }
  echo('
  <h3>Currently installed themes</h3>
    <form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">
    <p>
      <select name="theme_id">
        ');
        $q = 'SELECT theme_id,theme_name,enabled FROM '.table_prefix.'themes ORDER BY theme_order';
        $s = $db->sql_query($q);
        if(!$s) die('Error selecting theme data: '.mysql_error().'<br /><u>Attempted SQL:</u><br />'.$q);
        while ( $r = $db->fetchrow_num($s) ) {
          if($r[2] < 1) $r[1] .= ' (disabled)';
          echo('<option value="'.$r[0].'">'.$r[1].'</option>');
        }
        $db->free_result();
        echo('
        </select> <input type="submit" name="disenable" value="Enable/Disable" /> <input type="submit" name="edit" value="Change settings" /> <input type="submit" name="up" value="Move up" /> <input type="submit" name="down" value="Move down" /> <input type="submit" name="uninstall" value="Uninstall" style="color: #DD3300; font-weight: bold;" />
      </p>
    </form>
    <h3>Install a new theme</h3>
  ');
    $theme = Array();
    $obb = '';
    for($i=0;$i<sizeof($l);$i++) {
      if(is_file('./themes/'.$l[$i].'/theme.cfg') && file_exists('./themes/'.$l[$i].'/theme.cfg')) {
        include('./themes/'.$l[$i].'/theme.cfg');
        $q = 'SELECT * FROM '.table_prefix.'themes WHERE theme_id=\''.$theme['theme_id'].'\'';
        $s = $db->sql_query($q);
        if(!$s) die('Error selecting list of currently installed themes: '.mysql_error().'<br /><u>Attempted SQL:</u><br />'.$q);
        if($db->numrows($s) < 1) {
          $obb .= '<option value="'.$theme['theme_id'].'">'.$theme['theme_name'].'</option>';
        }
        $db->free_result();
      }
    }
    if($obb != '') {
      echo('<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post"><p>');
      echo('<select name="theme_id">');
      echo($obb);
      echo('</select>');
      echo('
      <input type="submit" name="install" value="Install this theme" />
      </p></form>');
    } else echo('<p>All themes are currently installed.</p>');
}

function page_Admin_GroupManager()
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
  
  if(isset($_POST['do_create_stage1']))
  {
    if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['create_group_name']))
    {
      echo '<p>The group name you chose is invalid.</p>';
      return;
    }
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="2">Creating group: '.$_POST['create_group_name'].'</th></tr>
          <tr>
            <td class="row1">Group moderator</td><td class="row1">' . $template->username_field('group_mod') . '</td>
          </tr>
          <tr><td class="row2">Group status</td><td class="row2">
            <label><input type="radio" name="group_status" value="'.GROUP_CLOSED.'" checked="checked" /> Closed to new members</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_REQUEST.'" /> Members can ask to be added</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_OPEN.'" /> Members can join freely</label><br />
            <label><input type="radio" name="group_status" value="'.GROUP_HIDDEN.'" /> Group is hidden</label>
          </td></tr>
          <tr>
            <th class="subhead" colspan="2">
              <input type="hidden" name="create_group_name" value="'.$_POST['create_group_name'].'" />
              <input type="submit" name="do_create_stage2" value="Create group" />
            </th>
          </tr>
          </table>
          </div>';
    echo '</form>';
    return;
  }
  elseif(isset($_POST['do_create_stage2']))
  {
    if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['create_group_name']))
    {
      echo '<p>The group name you chose is invalid.</p>';
      return;
    }
    if(!in_array(intval($_POST['group_status']), Array(GROUP_CLOSED, GROUP_OPEN, GROUP_HIDDEN, GROUP_REQUEST)))
    {
      echo '<p>Hacking attempt</p>';
      return;
    }
    $e = $db->sql_query('SELECT group_id FROM '.table_prefix.'groups WHERE group_name=\''.$db->escape($_POST['create_group_name']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() > 0)
    {
      echo '<p>The group name you entered already exists.</p>';
      return;
    }
    $db->free_result();
    $q = $db->sql_query('INSERT INTO '.table_prefix.'groups(group_name,group_type) VALUES( \''.$db->escape($_POST['create_group_name']).'\', ' . intval($_POST['group_status']) . ' )');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    $e = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($_POST['group_mod']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>The username you entered could not be found.</p>';
      return;
    }
    $row = $db->fetchrow();
    $id = $row['user_id'];
    $db->free_result();
    $e = $db->sql_query('SELECT group_id FROM '.table_prefix.'groups WHERE group_name=\''.$db->escape($_POST['create_group_name']).'\';');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>The group ID could not be looked up.</p>';
      return;
    }
    $row = $db->fetchrow();
    $gid = $row['group_id'];
    $db->free_result();
    $e = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,is_mod) VALUES('.$gid.', '.$id.', 1);');
    if(!$e)
    {
      echo $db->get_error();
      return;
    }
    echo "<div class='info-box'>
            <b>Information</b><br />
            The group {$_POST['create_group_name']} has been created successfully.
          </div>";
  }
  if(isset($_POST['do_edit']) || isset($_POST['edit_do']))
  {
    // Fetch the group name
    $q = $db->sql_query('SELECT group_name,system_group FROM '.table_prefix.'groups WHERE group_id='.intval($_POST['group_edit_id']).';');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<p>Error: couldn\'t look up group name</p>';
    }
    $row = $db->fetchrow();
    $name = $row['group_name'];
    $db->free_result();
    if(isset($_POST['edit_do']))
    {
      if(isset($_POST['edit_do']['del_group']))
      {
        if ( $row['system_group'] == 1 )
        {
          echo '<div class="error-box">The group "' . $name . '" could not be deleted because it is a system group required for site functionality.</div>';
        }
        else
        {
          $q = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE group_id='.intval($_POST['group_edit_id']).';');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          $q = $db->sql_query('DELETE FROM '.table_prefix.'groups WHERE group_id='.intval($_POST['group_edit_id']).';');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          echo '<div class="info-box">The group "'.$name.'" has been deleted. Return to the <a href="javascript:ajaxPage(\'Admin:GroupManager\');">group manager</a>.</div>';
          return;
        }
      }
      if(isset($_POST['edit_do']['save_name']))
      {
        if(!preg_match('/^([A-z0-9 -]+)$/', $_POST['group_name']))
        {
          echo '<p>The group name you chose is invalid.</p>';
          return;
        }
        $q = $db->sql_query('UPDATE '.table_prefix.'groups SET group_name=\''.$db->escape($_POST['group_name']).'\'
            WHERE group_id='.intval($_POST['group_edit_id']).';');
        if(!$q)
        {
          echo $db->get_error();
          return;
        }
        else
        {
          echo '<div class="info-box" style="margin: 0 0 10px 0;"">
                  The group name has been updated.
                </div>';
        }
        $name = $_POST['group_name'];
        
      }
      $q = $db->sql_query('SELECT member_id FROM '.table_prefix.'group_members
                             WHERE group_id='.intval($_POST['group_edit_id']).';');
      if(!$q)
      {
        echo $db->get_error();
        return;
      }
      if($db->numrows() > 0)
      {
        while($row = $db->fetchrow($q))
        {
          if(isset($_POST['edit_do']['del_' . $row['member_id']]))
          {
            $e = $db->sql_query('DELETE FROM '.table_prefix.'group_members WHERE member_id='.$row['member_id']);
            if(!$e)
            {
              echo $db->get_error();
              return;
            }
          }
        }
      }
      $db->free_result();
      if(isset($_POST['edit_do']['add_member']))
      {
        $q = $db->sql_query('SELECT user_id FROM '.table_prefix.'users WHERE username=\''.$db->escape($_POST['edit_add_username']).'\';');
        if(!$q)
        {
          echo $db->get_error();
          return;
        }
        if($db->numrows() > 0)
        {
          $row = $db->fetchrow();
          $user_id = $row['user_id'];
          $is_mod = ( isset( $_POST['add_mod'] ) ) ? '1' : '0';
          $q = $db->sql_query('INSERT INTO '.table_prefix.'group_members(group_id,user_id,is_mod) VALUES('.intval($_POST['group_edit_id']).','.$user_id.','.$is_mod.');');
          if(!$q)
          {
            echo $db->get_error();
            return;
          }
          else
          {
            echo '<div class="info-box" style="margin: 0 0 10px 0;"">
                    The user "'.$_POST['edit_add_username'].'" has been added to this usergroup.
                  </div>';
          }
        }
        else
          echo '<div class="warning-box"><b>The user "'.$_POST['edit_add_username'].'" could not be added.</b><br />This username does not exist.</div>';
      }
    }
    $sg_disabled = ( $row['system_group'] == 1 ) ? ' value="Can\'t delete system group" disabled="disabled" style="color: #FF9773" ' : ' value="Delete this group" style="color: #FF3713" ';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th>Edit group name</th></tr>
          <tr>
            <td class="row1">
              Group name: <input type="text" name="group_name" value="'.$name.'" />
            </td>
          </tr>
          <tr>
            <th class="subhead">
              <input type="submit" name="edit_do[save_name]" value="Save name" />
              <input type="submit" name="edit_do[del_group]" '.$sg_disabled.' />
            </th>
          </tr>
          </table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.$_POST['group_edit_id'].'" />';
    echo '</form>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
          <tr><th colspan="3">Edit group members</th></tr>';
    $q = $db->sql_query('SELECT m.member_id,m.is_mod,u.username FROM '.table_prefix.'group_members AS m
                           LEFT JOIN '.table_prefix.'users AS u
                             ON u.user_id=m.user_id
                             WHERE m.group_id='.intval($_POST['group_edit_id']).'
                           ORDER BY m.is_mod DESC, u.username ASC;');
    if(!$q)
    {
      echo $db->get_error();
      return;
    }
    if($db->numrows() < 1)
    {
      echo '<tr><td colspan="3" class="row1">This group has no members.</td></tr>';
    }
    else
    {
      $cls = 'row2';
      while($row = $db->fetchrow())
      {
        $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
        $mod = ( $row['is_mod'] == 1 ) ? 'Mod' : '';
        echo '<tr>
                <td class="'.$cls.'" style="width: 100%;">
                  ' . $row['username'] . '
                </td>
                <td class="'.$cls.'">
                  '.$mod.'
                </td>
                <td class="'.$cls.'">
                  <input type="submit" name="edit_do[del_'.$row['member_id'].']" value="Remove member" />
                </td>
              </tr>';
      }
    }
    $db->free_result();
    echo '</table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.$_POST['group_edit_id'].'" />';
    echo '</form>';
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
    echo '<div class="tblholder">
          <table border="0" style="width:100%;" cellspacing="1" cellpadding="4">
            <tr>
              <th>Add a new member</th>
            </tr>
            <tr>
              <td class="row1">
                Username: ' . $template->username_field('edit_add_username') . '
              </td>
            </tr>
            <tr>
              <td class="row2">
                <label><input type="checkbox" name="add_mod" /> Is a group moderator</label> (can add and delete other members)
              </td>
            </tr>
            <tr>
              <th class="subhead">
                <input type="submit" name="edit_do[add_member]" value="Add user to group" />
              </th>
            </tr>
          </table>
          </div>
          <input type="hidden" name="group_edit_id" value="'.$_POST['group_edit_id'].'" />';
    echo '</form>';
    return;
  }
  echo '<h3>Manage Usergroups</h3>';
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  $q = $db->sql_query('SELECT group_id,group_name FROM '.table_prefix.'groups ORDER BY group_name ASC;');
  if(!$q)
  {
    echo $db->get_error();
  }
  else
  {
    echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
          <tr>
          <th>Edit an existing group</th>
          </tr>';
    echo '<tr><td class="row2"><select name="group_edit_id">';
    while ( $row = $db->fetchrow() )
    {
      if ( $row['group_name'] != 'Everyone' )
      {
        echo '<option value="' . $row['group_id'] . '">' . htmlspecialchars( $row['group_name'] ) . '</option>';
      }
    }
    $db->free_result();
    echo '</select></td></tr>';
    echo '<tr><td class="row1" style="text-align: center;"><input type="submit" name="do_edit" value="Edit group" /></td></tr>
          </table>
          </div>
          </form><br />';
  }
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post" onsubmit="if(!submitAuthorized) return false;" enctype="multipart/form-data">';
  echo '<div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
        <tr>
        <th colspan="2">Create a new group</th>
        </tr>';
  echo '<tr><td class="row2">Group name:</td><td class="row2"><input type="text" name="create_group_name" /></td></tr>';
  echo '<tr><td colspan="2" class="row1" style="text-align: center;"><input type="submit" name="do_create_stage1" value="Continue >" /></td></tr>
        </table>
        </div>';
  echo '</form>';
}

function page_Admin_COPPA()
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
  
  echo '<h2>Background information</h2>';
  echo '<p>
          The United States Childrens\' Online Privacy Protection Act (COPPA) was a law passed in 2001 that requires sites oriented towards
          children under 13 years old or with a significant amount of under-13 children clearly state what information is being collected
          in a privacy policy and obtain authorization from a parent or legal guardian before allowing children to use the site. Enano 
          provides an easy way to allow you, as the website administrator, to obtain this authorization.
        </p>';
  
  // Start form
  
  if ( isset($_POST['coppa_address']) )
  {
    // Saving changes
    $enable_coppa = ( isset($_POST['enable_coppa']) ) ? '1' : '0';
    setConfig('enable_coppa', $enable_coppa);
    
    $address = $_POST['coppa_address']; // RenderMan::preprocess_text($_POST['coppa_address'], true, false);
    setConfig('coppa_address', $address);
    
    echo '<div class="info-box">Your changes have been saved.</div>';
  }
  
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', (( isset($_GET['sqldbg'])) ? 'sqldbg&amp;' : '') .'module='.$paths->cpage['module']).'" method="post">';
  
  echo '<div class="tblholder">';
  echo '<table border="0" cellspacing="1" cellpadding="4">';
  echo '<tr>
          <th colspan="2">
            COPPA support
          </th>
        </tr>';
        
  echo '<tr>
          <td class="row1">
            Enable COPPA support:
          </td>
          <td class="row2">
            <label><input type="checkbox" name="enable_coppa" ' . ( ( getConfig('enable_coppa') == '1' ) ? 'checked="checked"' : '' ) . ' /> COPPA enabled</label><br />
            <small>If this is checked, users will be asked if they are under 13 years of age before registering</small>
          </td>
        </tr>';
        
  echo '<tr>
          <td class="row1">
            Your mailing address:<br />
            <small>This is the address to which parents will send authorization forms.</small>
          </td>
          <td class="row2">
            <textarea name="coppa_address" rows="7" cols="40">' . getConfig('coppa_address') . '</textarea>
          </td>
        </tr>';
        
  echo '<tr>
          <th colspan="2" class="subhead">
            <input type="submit" value="Save changes" />
          </th>
        </tr>';
        
  echo '</table>';
  
  echo '</form>';
  
}

function page_Admin_BanControl()
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
  
  if(isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $_GET['id'] != '')
  {
    $e = $db->sql_query('DELETE FROM '.table_prefix.'banlist WHERE ban_id=' . intval($_GET['id']) . '');
    if(!$e) $db->_die('The ban list entry was not deleted.');
  }
  if(isset($_POST['create']) && !defined('ENANO_DEMO_MODE'))
  {
    $type = intval($_POST['type']);
    $value = trim($_POST['value']);
    if ( !in_array($type, array(BAN_IP, BAN_USER, BAN_EMAIL)) )
    {
      echo '<div class="error-box">Hacking attempt.</div>';
    }
    else if ( empty($value) )
    {
      echo '<div class="error-box">Please enter something to ban.</div>';
    }
    else
    {
      $entries = array();
      $input = explode(',', $_POST['value']);
      $error = false;
      foreach ( $input as $entry )
      {
        $entry = trim($entry);
        if ( empty($entry) )
        {
          echo '<div class="error-box">Malformed entry.</div>';
          $error = true;
          break;
        }
        if ( $type == BAN_IP )
        {
          if ( !isset($_POST['regex']) )
          {
            // as of 1.0.2 parsing is done at runtime
            $entries[] = $entry;
          }
          else
          {
            $entries[] = $entry;
          }
        }
        else
        {
          $entries[] = $entry;
        }
      }
      if ( !$error )
      {
        $regex = ( isset($_POST['regex']) ) ? '1' : '0';
        $to_insert = array();                                                         
        $reason = $db->escape($_POST['reason']);
        foreach ( $entries as $entry )
        {
          $entry = $db->escape($entry);
          $to_insert[] = "($type, '$entry', '$reason', $regex)";
        }
        $q = 'INSERT INTO '.table_prefix."banlist(ban_type, ban_value, reason, is_regex)\n  VALUES" . implode(",\n  ", $to_insert) . ';';
        @set_time_limit(0);
        $e = $db->sql_query($q);
        if(!$e) $db->_die('The banlist could not be updated.');
      }
    }
  }
  else if ( isset($_POST['create']) && defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">This function is disabled in the demo. Just because <i>you</i> don\'t like ' . htmlspecialchars($_POST['value']) . ' doesn\'t mean <i>we</i> don\'t like ' . htmlspecialchars($_POST['value']) . '.</div>';
  }
  $q = $db->sql_query('SELECT ban_id,ban_type,ban_value,is_regex FROM '.table_prefix.'banlist ORDER BY ban_type;');
  if(!$q) $db->_die('The banlist data could not be selected.');
  echo '<div class="tblholder" style="max-height: 800px; clip: rect(0px,auto,auto,0px); overflow: auto;">
          <table border="0" cellspacing="1" cellpadding="4">';
  echo '<tr><th>Type</th><th>Value</th><th>Regular Expression</th><th></th></tr>';
  if($db->numrows() < 1) echo '<td class="row1" colspan="4">No ban rules yet.</td>';
  $cls = 'row2';
  while($r = $db->fetchrow())
  {
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    if($r['ban_type']==BAN_IP) $t = 'IP address';
    elseif($r['ban_type']==BAN_USER) $t = 'Username';
    elseif($r['ban_type']==BAN_EMAIL) $t = 'E-mail address';
    if($r['is_regex']) $g = 'Yes'; else $g = 'No';
    echo '<tr><td class="'.$cls.'">'.$t.'</td><td class="'.$cls.'">'.$r['ban_value'].'</td><td class="'.$cls.'">'.$g.'</td><td class="'.$cls.'"><a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'BanControl&amp;action=delete&amp;id='.$r['ban_id']).'">Delete</a></td></tr>';
  }
  $db->free_result();
  echo '</table></div>';
  echo '<h3>Create new ban rule</h3>';
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">';
  ?>
  Type: <select name="type"><option value="<?php echo BAN_IP; ?>">IP address</option><option value="<?php echo BAN_USER; ?>">Username</option><option value="<?php echo BAN_EMAIL; ?>">E-mail address</option></select><br />
  Rule: <input type="text" name="value" size="30" /><br />
  <small>You can ban multiple IP addresses, users, or e-mail addresses by separating entries with a single comma (User1,User2). Do not put a space after the comma. For IP addresses, you may specify ranges like 172|192.168.4-30|90-167.1-90, which will turn into 172 and 192 . 168 . 4-30 and 90-167 . 1 - 90, which matches 18,899 IP addresses.</small><br />
  Reason to show to the banned user: <textarea name="reason" rows="7" cols="40"></textarea><br />
  <input type="checkbox" name="regex" id="regex" />  <label for="regex">This rule is a regular expression</label> (advanced users only)<br />
  <input type="submit" style="font-weight: bold;" name="create" value="Create new ban rule" />
  <?php
  echo '</form>';
}

function page_Admin_MassEmail()
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
  
  global $enano_config;
  if ( isset($_POST['do_send']) && !defined('ENANO_DEMO_MODE') )
  {
    $use_smtp = getConfig('smtp_enabled') == '1';
    
    //
    // Let's do some checking to make sure that mass mail functions
    // are working in win32 versions of php. (copied from phpBB)
    //
    if ( preg_match('/[c-z]:\\\.*/i', getenv('PATH')) && !$use_smtp)
    {
      $ini_val = ( @phpversion() >= '4.0.0' ) ? 'ini_get' : 'get_cfg_var';

      // We are running on windows, force delivery to use our smtp functions
      // since php's are broken by default
      $use_smtp = true;
      $enano_config['smtp_server'] = @$ini_val('SMTP');
    }
    
    $mail = new emailer( !empty($use_smtp) );
    
    // Validate subject/message body
    $subject = stripslashes(trim($_POST['subject']));
    $message = stripslashes(trim($_POST['message']));
    
    if ( empty($subject) )
      $errors[] = 'Please enter a subject.';
    if ( empty($message) )
      $errors[] = 'Please enter a message.';
    
    // Get list of members
    if ( !empty($_POST['userlist']) )
    {
      $userlist = str_replace(', ', ',', $_POST['userlist']);
      $userlist = explode(',', $userlist);
      foreach ( $userlist as $k => $u )
      {
        if ( $u == $session->username )
        {
          // Message is automatically sent to the sender
          unset($userlist[$k]);
        }
        else
        {
          $userlist[$k] = $db->escape($u);
        }
      }
      $userlist = 'WHERE username=\'' . implode('\' OR username=\'', $userlist) . '\'';
      
      $q = $db->sql_query('SELECT email FROM '.table_prefix.'users ' . $userlist . ';');
      if ( !$q )
        $db->_die();
      
      if ( $row = $db->fetchrow() )
      {
        do {
          $mail->cc($row['email']);
        } while ( $row = $db->fetchrow() );
      }
      
      $db->free_result();
      
    }
    else
    {
      // Sending to a usergroup
      
      $group_id = intval($_POST['group_id']);
      if ( $group_id < 1 )
      {
        $errors[] = 'Invalid group ID';
      }
      else
      {
        $q = $db->sql_query('SELECT u.email FROM '.table_prefix.'group_members AS g
                               LEFT JOIN '.table_prefix.'users AS u
                                 ON (u.user_id=g.user_id)
                               WHERE g.group_id=' . $group_id . ';');
        if ( !$q )
          $db->_die();
        
        if ( $row = $db->fetchrow() )
        {
          do {
            $mail->cc($row['email']);
          } while ( $row = $db->fetchrow() );
        }
        
        $db->free_result();
      }
    }
    
    if ( sizeof($errors) < 1 )
    {
    
      $mail->from(getConfig('contact_email'));
      $mail->replyto(getConfig('contact_email'));
      $mail->set_subject($subject);
      $mail->email_address(getConfig('contact_email'));
      
      // Copied/modified from phpBB
      $email_headers = 'X-AntiAbuse: Website server name - ' . $_SERVER['SERVER_NAME'] . "\n";
      $email_headers .= 'X-AntiAbuse: User_id - ' . $session->user_id . "\n";
      $email_headers .= 'X-AntiAbuse: Username - ' . $session->username . "\n";
      $email_headers .= 'X-AntiAbuse: User IP - ' . $_SERVER['REMOTE_ADDR'] . "\n";
      
      $mail->extra_headers($email_headers);
      
      $tpl = 'The following message was mass-mailed by {SENDER}, one of the administrators from {SITE_NAME}. If this message contains spam or any comments which you find abusive or offensive, please contact the administration team at:
  
{CONTACT_EMAIL}

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
{MESSAGE}
';
  
      $mail->use_template($tpl);
      
      $mail->assign_vars(array(
          'SENDER' => $session->username,
          'SITE_NAME' => getConfig('site_name'),
          'CONTACT_EMAIL' => getConfig('contact_email'),
          'MESSAGE' => $message
        ));
      
      //echo '<pre>'.print_r($mail,true).'</pre>';
      
      // All done
      $mail->send();
      $mail->reset();
      
      echo '<div class="info-box">Your message has been sent.</div>';
      
    }
    else
    {
      echo '<div class="warning-box">Could not send message for the following reason(s):<ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
    }
    
  }
  else if ( isset($_POST['do_send']) && defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">This function is disabled in the demo. You think demo@enanocms.org likes getting "test" mass e-mails?</div>';
  }
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">';
  ?>
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr>
        <th colspan="2">Send mass e-mail</th>
      </tr>
      <tr>
        <td class="row2" rowspan="2" style="width: 30%; min-width: 200px;">
          Send message to:<br />
          <small>
            By default, this message will be sent to the group selected here. You may instead send the message to a specific
            list of users by entering them in the second row, with usernames separated by a single comma (no space).
          </small>
        </td>
        <td class="row1">
          <select name="group_id">
            <?php
            $q = $db->sql_query('SELECT group_name,group_id FROM '.table_prefix.'groups ORDER BY group_name ASC;');
            if ( !$q )
              $db->_die();
            while ( $row = $db->fetchrow() )
            {
              echo '<option value="' . $row['group_id'] . '">' . $row['group_name'] . '</option>';
            }
            ?>
          </select>
        </td>
      </tr>
      <tr>
        <td class="row1">
          Usernames: <input type="text" name="userlist" size="50" />
        </td>
      </tr>
      <tr>
        <td class="row2" style="width: 30%; min-width: 200px;">
          Subject:
        </td>
        <td class="row1">
          <input name="subject" type="text" size="50" />
        </td>
      </tr>
      <tr>
        <td class="row2"  style="width: 30%; min-width: 200px;">
          Message:
        </td>
        <td class="row1">
          <textarea name="message" rows="30" cols="60" style="width: 100%;"></textarea>
        </td>
      </tr>
      <tr>
        <th class="subhead" colspan="2" style="text-align: left;" valign="middle">
          <div style="float: right;"><input type="submit" name="do_send" value="Send message" /></div>
          <small style="font-weight: normal;">Please be warned: it may take a LONG time to send this message. <b>Please do not stop the script until the process is finished.</b></small>
        </th>
      </tr>
      
    </table>
  </div>
  <?php
  echo '</form>';
}

function page_Admin_DBBackup()
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
  
  if ( ENANO_DBLAYER != 'MYSQL' )
    die('<h3>Not supported</h3>
          <p>This function is only supported under the MySQL database driver.</p>');
  
  if(isset($_GET['submitting']) && $_GET['submitting'] == 'yes' && defined('ENANO_DEMO_MODE') )
  {
    redirect(makeUrlComplete('Special', 'Administration'), 'Access denied', 'You\'ve got to be kidding me. Forget it, kid.', 4 );
  }
  
  global $system_table_list;
  if(isset($_GET['submitting']) && $_GET['submitting'] == 'yes')
  {
    
    if(defined('SQL_BACKUP_CRYPT'))
      // Try to increase our time limit
      @set_time_limit(0);
    // Do the actual export
    $aesext = ( defined('SQL_BACKUP_CRYPT') ) ? '.tea' : '';
    $filename = 'enano_backup_' . date('ymd') . '.sql' . $aesext;
    ob_start();
    // Spew some headers
    $headdate = date('F d, Y \a\t h:i a');
    echo <<<HEADER
-- Enano CMS SQL backup
-- Generated on {$headdate} by {$session->username}

HEADER;
    // build the table list
    $base = ( isset($_POST['do_system_tables']) ) ? $system_table_list : Array();
    $add  = ( isset($_POST['additional_tables'])) ? $_POST['additional_tables'] : Array();
    $tables = array_merge($base, $add);
    
    // Log it!
    $e = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'db_backup\', '.time().', \''.date('d M Y h:i a').'\', \''.$db->escape($session->username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', \'' . $db->escape(implode(', ', $tables)) . '\')');
    if ( !$e )
      $db->_die();
    
    foreach($tables as $i => $t)
    {
      if(!preg_match('#^([a-z0-9_]+)$#i', $t))
        die('Hacking attempt');
      // if($t == table_prefix.'files' && isset($_POST['do_data']))
      //   unset($tables[$i]);
    }
    foreach($tables as $t)
    {
      // THE FOLLOWING COMMENT DOES NOT APPLY AS OF 1.0.
      // Sorry folks - this script CAN'T backup enano_files and enano_search_index due to the sheer size of the tables.
      // If encryption is enabled the log data will be excluded too.
      $result = export_table(
        $t,
        isset($_POST['do_struct']),
        ( isset($_POST['do_data']) ),
        false
        ) . "\n";
      if ( !$result )
      {
        $db->_die();
      }
      echo $result;
    }
    $data = ob_get_contents();
    ob_end_clean();
    if(defined('SQL_BACKUP_CRYPT'))
    {
      // Free some memory, we don't need this stuff any more
      $db->close();
      unset($paths, $db, $template, $plugins);
      $tea = new TEACrypt();
      $data = $tea->encrypt($data, $session->private_key);
    }
    header('Content-disposition: attachment, filename="'.$filename.'";');
    header('Content-type: application/transact-sql');
    header('Content-length: '.strlen($data));
    echo $data;
    exit;
  }
  else
  {
    // Show the UI
    echo '<form action="'.makeUrlNS('Admin', 'DBBackup', 'submitting=yes', true).'" method="post" enctype="multipart/form-data">';
    ?>
    <p>This page allows you to back up your Enano database should something go miserably wrong.</p>
    <p><label><input type="checkbox" name="do_system_tables" checked="checked" />  Export tables that are part of the Enano core</label><p>
    <p>Additional tables to export:</p>
    <p><select name="additional_tables[]" multiple="multiple">
       <?php
         if ( ENANO_DBLAYER == 'MYSQL' )
         {
           $q = $db->sql_query('SHOW TABLES;') or $db->_die('Somehow we were denied the request to get the list of tables.');
         }
         else if ( ENANO_DBLAYER == 'PGSQL' )
         {
           $q = $db->sql_query('SELECT relname FROM pg_stat_user_tables ORDER BY relname;') or $db->_die('Somehow we were denied the request to get the list of tables.');
         }
         while($row = $db->fetchrow_num())
         {
           if(!in_array($row[0], $system_table_list)) echo '<option value="'.$row[0].'">'.$row[0].'</option>';
         }
       ?>
       </select>
       </p>
    <p><label><input type="checkbox" name="do_struct" checked="checked" /> Include table structure</label><br />
       <label><input type="checkbox" name="do_data"   checked="checked" /> Include table data</label>
       </p>
    <p><input type="submit" value="Create backup" /></p>
    <?php
    echo '</form>';
  }
}

function page_Admin_AdminLogout()
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
  
  $session->logout(USER_LEVEL_ADMIN);
  echo '<h3>You have now been logged out of the administration panel.</h3><p>You will continue to be logged into the website, but you will need to re-authenticate before you can access the administration panel again.</p><p>Return to the <a href="'.makeUrl(getConfig('main_page')).'">Main Page</a>.</p>';
}

function page_Special_Administration()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if($session->auth_level < USER_LEVEL_ADMIN) {
    redirect(makeUrlNS('Special', 'Login/'.$paths->page, 'level='.USER_LEVEL_ADMIN), 'Not authorized', 'You need an authorization level of '.USER_LEVEL_ADMIN.' to use this page, your auth level is: ' . $session->auth_level, 0);
    exit;
  }
  else
  {
    $template->load_theme('admin', 'default');
    $template->init_vars();
    if( !isset( $_GET['noheaders'] ) ) 
    {
      $template->header();
    }
    echo 'Administer your Enano website.';
    ?>
    <script type="text/javascript">
    function ajaxPage(t)
    {
      if ( KILL_SWITCH )
      {
        document.getElementById('ajaxPageContainer').innerHTML = '<div class="error-box">Because of the lack of AJAX support, support for Internet Explorer versions less than 6.0 has been disabled in Runt. You can download and use Mozilla Firefox (or Seamonkey under Windows 95); both have an up-to-date standards-compliant rendering engine that has been tested thoroughly with Enano.</div>';
        return false;
      }
      if ( t == namespace_list.Admin + 'AdminLogout' )
      {
        var mb = new messagebox(MB_YESNO|MB_ICONQUESTION, $lang.get('user_logout_confirm_title_elev'), $lang.get('user_logout_confirm_body_elev'));
        mb.onclick['Yes'] = function() {
          var tigraentry = document.getElementById('i_div0_0').parentNode;
          var tigraobj = $(tigraentry);
          var div = document.createElement('div');
          div.style.backgroundColor = '#FFFFFF';
          domObjChangeOpac(70, div);
          div.style.position = 'absolute';
          var top = tigraobj.Top();
          var left = tigraobj.Left();
          var width = tigraobj.Width();
          var height = tigraobj.Height();
          div.style.top = top + 'px';
          div.style.left = left + 'px';
          div.style.width = width + 'px';
          div.style.height = height + 'px';
          var body = document.getElementsByTagName('body')[0];
          enlighten(true);
          body.appendChild(div);
          ajaxPageBin(namespace_list.Admin + 'AdminLogout');
        }
        return;
      }
      ajaxPageBin(t);
    }
    function ajaxPageBin(t)
    {
      if ( KILL_SWITCH )
      {
        document.getElementById('ajaxPageContainer').innerHTML = '<div class="error-box">Because of the lack of AJAX support, support for Internet Explorer versions less than 6.0 has been disabled in Runt. You can download and use Mozilla Firefox (or Seamonkey under Windows 95); both have an up-to-date standards-compliant rendering engine that has been tested thoroughly with Enano.</div>';
        return false;
      }
      document.getElementById('ajaxPageContainer').innerHTML = '<div class="wait-box">Loading page...</div>';
      ajaxGet('<?php echo scriptPath; ?>/ajax.php?title='+t+'&_mode=getpage&noheaders&auth=<?php echo $session->sid_super; ?>', function() {
          if(ajax.readyState == 4) {
            document.getElementById('ajaxPageContainer').innerHTML = ajax.responseText;
            fadeInfoBoxes();
          }
        });
    }
    function _enanoAdminOnload() { ajaxPage('<?php echo $paths->nslist['Admin']; ?>Home'); }
    var TREE_TPL = {
      'target'  : '_self',  // name of the frame links will be opened in
                  // other possible values are: _blank, _parent, _search, _self and _top
    
      'icon_e'  : '<?php echo scriptPath; ?>/images/icons/empty.gif',      // empty image
      'icon_l'  : '<?php echo scriptPath; ?>/images/icons/line.gif',       // vertical line
      'icon_32' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root leaf icon normal
      'icon_36' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root leaf icon selected
      'icon_48' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root icon normal
      'icon_52' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root icon selected
      'icon_56' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root icon opened
      'icon_60' : '<?php echo scriptPath; ?>/images/icons/base.gif',       // root icon selected
      'icon_16' : '<?php echo scriptPath; ?>/images/icons/folder.gif',     // node icon normal
      'icon_20' : '<?php echo scriptPath; ?>/images/icons/folderopen.gif', // node icon selected
      'icon_24' : '<?php echo scriptPath; ?>/images/icons/folder.gif',     // node icon opened
      'icon_28' : '<?php echo scriptPath; ?>/images/icons/folderopen.gif', // node icon selected opened
      'icon_0'  : '<?php echo scriptPath; ?>/images/icons/page.gif',       // leaf icon normal
      'icon_4'  : '<?php echo scriptPath; ?>/images/icons/page.gif',       // leaf icon selected
      'icon_8'  : '<?php echo scriptPath; ?>/images/icons/page.gif',       // leaf icon opened
      'icon_12' : '<?php echo scriptPath; ?>/images/icons/page.gif',       // leaf icon selected
      'icon_2'  : '<?php echo scriptPath; ?>/images/icons/joinbottom.gif', // junction for leaf
      'icon_3'  : '<?php echo scriptPath; ?>/images/icons/join.gif',       // junction for last leaf
      'icon_18' : '<?php echo scriptPath; ?>/images/icons/plusbottom.gif', // junction for closed node
      'icon_19' : '<?php echo scriptPath; ?>/images/icons/plus.gif',       // junction for last closed node
      'icon_26' : '<?php echo scriptPath; ?>/images/icons/minusbottom.gif',// junction for opened node
      'icon_27' : '<?php echo scriptPath; ?>/images/icons/minus.gif'       // junction for last opended node
    };
    addOnloadHook(keepalive_onload);
    <?php
    echo $paths->parseAdminTree(); // Make a Javascript array that defines the tree
    if(!isset($_GET['module'])) { echo 'addOnloadHook(_enanoAdminOnload);'; } ?>
    </script>
    <table border="0" width="100%">
      <tr>
        <td class="holder" valign="top">
          <div class="pad" style="padding-right: 20px;">
            <script type="text/javascript">
            if ( !KILL_SWITCH )
            {
              new tree(TREE_ITEMS, TREE_TPL);
            }
            </script>
          </div>
        </td>
        <td width="100%" valign="top">
          <div class="pad" id="ajaxPageContainer">
          <?php
          if(isset($_GET['module'])) 
          {
            // Look for a namespace prefix in the urlname, and assign a different namespace, if necessary
            $k = array_keys($paths->nslist);
            for ( $i = 0; $i < sizeof($paths->nslist); $i++ )
            {
              $ln = strlen( $paths->nslist[ $k[ $i ] ] );
              if ( substr($_GET['module'], 0, $ln) == $paths->nslist[$k[$i]] )
              {
                $ns = $k[$i];
                $nm = substr($_GET['module'], $ln, strlen($_GET['module']));
              }
            }
            $fname = 'page_'.$ns.'_'.$nm;
            $s = strpos($fname, '?noheaders');
            if($s) $fname = substr($fname, 0, $s);
            $paths->cpage['module'] = $_GET['module'];
            if ( function_exists($fname) && $_GET['module'] != $paths->nslist['Special'] . 'Administration' )
            {
              eval($fname.'();');
            }
          } 
          else 
          {
            echo '<script type="text/javascript">document.write(\'<div class="wait-box">Please wait while the administration panel loads. You need to be using a recent browser with AJAX support in order to use Runt.</div>\');</script><noscript><div class="error-box">It looks like Javascript isn\'t enabled in your browser. Please enable Javascript or use a different browser to continue.</div></noscript>';
          }
          ?>
          </div>
          <script type="text/javascript">
            if ( KILL_SWITCH )
            {
              document.getElementById('ajaxPageContainer').innerHTML = '<div class="error-box">Because of the lack of AJAX support, support for Internet Explorer versions less than 6.0 has been disabled in Runt. You can download and use Mozilla Firefox (or Seamonkey under Windows 95); both have an up-to-date standards-compliant rendering engine that has been tested thoroughly with Enano.</div>';
            }
        </script>
        </td>
      </tr>
    </table>
  
    <?php
  }
  if(!isset($_GET['noheaders']))
  {
    $template->footer();
  }
}

function page_Special_EditSidebar()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if($session->auth_level < USER_LEVEL_ADMIN) 
  {
    redirect(makeUrlNS('Special', 'Login/'.$paths->page, 'level='.USER_LEVEL_ADMIN), '', '', false);
    exit;
  }
  else 
  {
    
    $template->add_header('<script type="text/javascript" src="'.scriptPath.'/includes/clientside/dbx.js"></script>');
    $template->add_header('<script type="text/javascript" src="'.scriptPath.'/includes/clientside/dbx-key.js"></script>');
    $template->add_header('<script type="text/javascript" src="'.scriptPath.'/includes/clientside/sbedit.js"></script>');
    $template->add_header('<link rel="stylesheet" type="text/css" href="'.scriptPath.'/includes/clientside/dbx.css" />');
    
    // Knock the sidebars dead to keep javascript in plugins from interfering
    $template->tpl_strings['SIDEBAR_LEFT']  = '';
    $template->tpl_strings['SIDEBAR_RIGHT'] = '';
    
    $template->load_theme('oxygen', 'bleu');
    $template->init_vars();
    
    $template->header();
    
    if(isset($_POST['save']))
    {
      // Write the new block order to the database
      // The only way to do this is with tons of queries (one per block + one select query at the start to count everything) but afaik its safe...
      // Anyone know a better way to do this?
      $q = $db->sql_query('SELECT item_order,item_id,sidebar_id FROM '.table_prefix.'sidebar ORDER BY sidebar_id ASC, item_order ASC;');
      if ( !$q )
      {
        $db->_die('The sidebar order data could not be selected.');
      }
      $orders = Array();
      while($row = $db->fetchrow())
      {
        $orders[] = Array(
            count($orders),
            $row['item_id'],
            $row['sidebar_id'],
          );
      }
      $db->free_result();
      
      // We now have an array with each sidebar ID in its respective order. Explode the order string in $_POST['order_(left|right)'] and use it to build a set of queries.
      $ol = explode(',', $_POST['order_left']);
      $odr = explode(',', $_POST['order_right']);
      $om = array_merge($ol, $odr);
      unset($ol, $odr);
      $queries = Array();
      foreach($orders as $k => $v)
      {
        $queries[] = 'UPDATE '.table_prefix.'sidebar SET item_order='.$om[$k].' WHERE item_id='.$v[1].';';
      }
      foreach($queries as $sql)
      {
        $q = $db->sql_query($sql);
        if(!$q)
        {
          $t = $db->get_error();
          echo $t;
          $template->footer();
          exit;
        }
      }
      echo '<div class="info-box" style="margin: 10px 0;">The sidebar order information was updated successfully.</div>';
    }
    elseif(isset($_POST['create']))
    {
      switch((int)$_POST['type'])
      {
        case BLOCK_WIKIFORMAT:
          $content = $_POST['wikiformat_content'];
          break;
        case BLOCK_TEMPLATEFORMAT:
          $content = $_POST['templateformat_content'];
          break;
        case BLOCK_HTML:
          $content = $_POST['html_content'];
          break;
        case BLOCK_PHP:
          $content = $_POST['php_content'];
          break;
        case BLOCK_PLUGIN:
          $content = $_POST['plugin_id'];
          break;
      }
      
      if ( defined('ENANO_DEMO_MODE') )
      {
        // Sanitize the HTML
        $content = sanitize_html($content, true);
      }
      
      if ( defined('ENANO_DEMO_MODE') && intval($_POST['type']) == BLOCK_PHP )
      {
        echo '<div class="error-box" style="margin: 10px 0 10px 0;">Adding PHP code blocks in the Enano administration demo has been disabled for security reasons.</div>';
        $_POST['php_content'] = '?>&lt;Nulled&gt;';
        $content = $_POST['php_content'];
      }
      
      // Get the value of item_order
      
      $q = $db->sql_query('SELECT * FROM '.table_prefix.'sidebar WHERE sidebar_id='.$db->escape($_POST['sidebar_id']).';');
      if(!$q) $db->_die('The order number could not be selected');
      $io = $db->numrows();
      
      $db->free_result();
      
      $q = 'INSERT INTO '.table_prefix.'sidebar(block_name, block_type, sidebar_id, block_content, item_order) VALUES ( \''.$db->escape($_POST['title']).'\', \''.$db->escape($_POST['type']).'\', \''.$db->escape($_POST['sidebar_id']).'\', \''.$db->escape($content).'\', '.$io.' );';
      $result = $db->sql_query($q);
      if(!$result)
      {
        echo $db->get_error();
        $template->footer();
        exit;
      }
      
      echo '<div class="info-box" style="margin: 10px 0;">The item was added.</div>';
      
    }
    
    if(isset($_GET['action']) && isset($_GET['id']))
    {
      if(!preg_match('#^([0-9]*)$#', $_GET['id']))
      {
        echo '<div class="warning-box">Error with action: $_GET["id"] was not an integer, aborting to prevent SQL injection</div>';
      }
      switch($_GET['action'])
      {
        case 'new':
          ?>
          <script type="text/javascript">
          function setType(input)
          {
            val = input.value;
            if(!val)
            {
              return false;
            }
            var divs = getElementsByClassName(document, 'div', 'sbadd_block');
            for(var i in divs)
            {
              if(divs[i].id == 'blocktype_'+val) divs[i].style.display = 'block';
              else divs[i].style.display = 'none';
            }
          }
          </script>
          
          <form action="<?php echo makeUrl($paths->page); ?>" method="post">
          
            <p>
              What type of block should this be?
            </p>
            <p>
              <select name="type" onchange="setType(this)"> <?php /* (NOT WORKING, at least in firefox 2) onload="var thingy = this; setTimeout('setType(thingy)', 500);" */ ?>
                <option value="<?php echo BLOCK_WIKIFORMAT; ?>">Wiki-formatted block</option>
                <option value="<?php echo BLOCK_TEMPLATEFORMAT; ?>">Template-formatted block (old pre-beta 3 behavior)</option>
                <option value="<?php echo BLOCK_HTML; ?>">Raw HTML block</option>
                <option value="<?php echo BLOCK_PHP; ?>">PHP code block (danger, Will Robinson!)</option>
                <option value="<?php echo BLOCK_PLUGIN; ?>">Use code from a plugin</option>
              </select>
            </p>
            
            <p>
            
              Block title: <input name="title" type="text" size="40" /><br />
              Which sidebar: <select name="sidebar_id"><option value="<?php echo SIDEBAR_LEFT; ?>">Left</option><option value="<?php echo SIDEBAR_RIGHT; ?>">Right</option></select>
            
            </p>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_WIKIFORMAT; ?>">
              <p>
                Wikitext:
              </p>
              <p>
                <textarea style="width: 98%;" name="wikiformat_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_TEMPLATEFORMAT; ?>">
              <p>
                Template code:
              </p>
              <p>
                <textarea style="width: 98%;" name="templateformat_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_HTML; ?>">
              <p>
                HTML to place inside the sidebar:
              </p>
              <p>
                <textarea style="width: 98%;" name="html_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_PHP; ?>">
              <?php if ( defined('ENANO_DEMO_MODE') ) { ?>
                <p>Creating PHP blocks in demo mode is disabled for security reasons.</p>
              <?php } else { ?>
              <p>
                <b>WARNING:</b> If you don't know what you're doing, or if you are not fluent in PHP, stop now and choose a different block type. You will brick your Enano installation if you are not careful here.
                ALWAYS remember to write secure code! The Enano team is not responsible if someone drops all your tables because of an SQL injection vulnerability in your sidebar code. You are probably better off using the template-formatted block type.
              </p>
              <p>
                <span style="color: red;">
                  It is especially important to note that this code is NOT checked for errors! If there is a syntax error in your code here, it will prevent any pages from loading AT ALL. So you need to use an external PHP editor (like <a href="http://www.jedit.org">jEdit</a>) to check your syntax before you hit save.
                </span> You have been warned.
              </p>
              <p>
                Also, you should avoid using output buffering functions (ob_[start|end|get_contents|clean]) here, because Enano uses those to track output from this script.
              </p>
              <p>
                The standard &lt;?php and ?&gt; tags work here. Don't use an initial "&lt;?php" or it will cause a parse error.
              </p>
              <p>
                PHP code:
              </p>
              <p>
                <textarea style="width: 98%;" name="php_content" rows="15" cols="50"></textarea>
              </p>
              <?php } ?>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_PLUGIN; ?>">
              <p>
                Plugin:
              </p>
              <p>
                <select name="plugin_id">
                <?php
                  foreach($template->plugin_blocks as $k => $c)
                  {
                    echo '<option value="'.$k.'">'.$k.'</option>';
                  }
                ?>
                </select>
              </p>
            </div>
            
            <p>
            
              <input type="submit" name="create" value="Create new block" style="font-weight: bold;" />&nbsp;
              <input type="submit" name="cancel" value="Cancel" />
            
            </p>
            
          </form>
          
          <script type="text/javascript">
            var divs = getElementsByClassName(document, 'div', 'sbadd_block');
            for(var i in divs)
            {
              if(divs[i].id != 'blocktype_<?php echo BLOCK_WIKIFORMAT; ?>') setTimeout("document.getElementById('"+divs[i].id+"').style.display = 'none';", 500);
            }
          </script>
          
          <?php
          $template->footer();
          return;
          break;
        case 'move':
          if( !isset($_GET['side']) || ( isset($_GET['side']) && !preg_match('#^([0-9]+)$#', $_GET['side']) ) )
          {
            echo '<div class="warning-box" style="margin: 10px 0;">$_GET[\'side\'] contained an SQL injection attempt</div>';
            break;
          }
          $query = $db->sql_query('UPDATE '.table_prefix.'sidebar SET sidebar_id=' . $db->escape($_GET['side']) . ' WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$query)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          echo '<div class="info-box" style="margin: 10px 0;">Item moved.</div>';
          break;
        case 'delete':
          $query = $db->sql_query('DELETE FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';'); // Already checked for injection attempts ;-)
          if(!$query)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          if(isset($_GET['ajax']))
          {
            ob_end_clean();
            die('GOOD');
          }
          echo '<div class="error-box" style="margin: 10px 0;">Item deleted.</div>';
          break;
        case 'disenable';
          $q = $db->sql_query('SELECT item_enabled FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          $r = $db->fetchrow();
          $db->free_result();
          $e = ( $r['item_enabled'] == 1 ) ? '0' : '1';
          $q = $db->sql_query('UPDATE '.table_prefix.'sidebar SET item_enabled='.$e.' WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          if(isset($_GET['ajax']))
          {
            ob_end_clean();
            die('GOOD');
          }
          break;
        case 'rename';
          $newname = $db->escape($_POST['newname']);
          $q = $db->sql_query('UPDATE '.table_prefix.'sidebar SET block_name=\''.$newname.'\' WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          if(isset($_GET['ajax']))
          {
            ob_end_clean();
            die('GOOD');
          }
          break;
        case 'getsource':
          $q = $db->sql_query('SELECT block_content,block_type FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          ob_end_clean();
          $r = $db->fetchrow();
          $db->free_result();
          if($r['block_type'] == BLOCK_PLUGIN) die('HOUSTON_WE_HAVE_A_PLUGIN');
          die($r['block_content']);
          break;
        case 'save':
          if ( defined('ENANO_DEMO_MODE') )
          {
            $q = $db->sql_query('SELECT block_type FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';');
            if(!$q)
            {
              echo 'var status=unescape(\''.hexencode($db->get_error()).'\');';
              exit;
            }
            $row = $db->fetchrow();
            if ( $row['block_type'] == BLOCK_PHP )
            {
              $_POST['content'] = '?>&lt;Nulled&gt;';
            }
            else
            {
              $_POST['content'] = sanitize_html($_POST['content'], true);
            }
          }
          $q = $db->sql_query('UPDATE '.table_prefix.'sidebar SET block_content=\''.$db->escape(rawurldecode($_POST['content'])).'\' WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo 'var status=unescape(\''.hexencode($db->get_error()).'\');';
            exit;
          }
          $q = $db->sql_query('SELECT block_type,block_content FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';');
          if(!$q)
          {
            echo 'var status=unescape(\''.hexencode($db->get_error()).'\');';
            exit;
          }
          $row = $db->fetchrow();
          $db->free_result();
          switch($row['block_type'])
          {
            case BLOCK_WIKIFORMAT:
            default:
              $c = RenderMan::render($row['block_content']);
              break;
            case BLOCK_TEMPLATEFORMAT:
              $c = $template->tplWikiFormat($row['block_content'], false, 'sidebar-editor.tpl');
              $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
              break;
            case BLOCK_HTML:
              $c = $row['block_content'];
              $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
              break;
            case BLOCK_PHP:
              ob_start();
              eval($row['block_content']);
              $c = ob_get_contents();
              ob_end_clean();
              $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
              break;
            case BLOCK_PLUGIN:
              $c = ($template->fetch_block($row['block_content'])) ? $template->fetch_block($row['block_content']) : 'Can\'t find plugin block';
              break;
          }
          die('var status = \'GOOD\'; var content = unescape(\''.hexencode($c).'\');');
          break;
      }
    }
    
    $q = $db->sql_query('SELECT item_id,sidebar_id,item_enabled,block_name,block_type,block_content FROM '.table_prefix.'sidebar ORDER BY sidebar_id ASC, item_order ASC;');
    if(!$q) $db->_die('The sidebar text data could not be selected.');
    
    $vars = $template->extract_vars('sidebar-editor.tpl');
    
    $parser = $template->makeParserText($vars['sidebar_button']);
    $parser->assign_vars(Array(
        'HREF'=>'#',
        'FLAGS'=>'onclick="return false;"',
        'TEXT'=>'Change theme'
      ));
    $template->tpl_strings['THEME_LINK'] = $parser->run();
    $parser->assign_vars(Array(
        'TEXT'=>'Log out',
      ));
    $template->tpl_strings['LOGOUT_LINK'] = $parser->run();
    
    $n1 = Array();
    $n2 = Array();
    $n  =& $n1;
    
    echo '<table border="0"><tr><td valign="top"><div class="dbx-group" id="sbedit_left">';
    //if(isset($vars['sidebar_top'])) echo $template->parse($vars['sidebar_top']);
    
    // Time for the loop
    // what this loop does is fetch the row data, then send it out to the appropriate parser for formatting,
    // then puts the result into $c, which is then sent to the template compiler for insertion into the TPL code.
    while($row = $db->fetchrow())
    {
      if(isset($current_side))
      {
        if($current_side != $row['sidebar_id'])
        {
          // Time to switch!
          //if(isset($vars['sidebar_top'])) echo $template->parse($vars['sidebar_bottom']);
          echo '</div></td><td valign="top"><div class="dbx-group" id="sbedit_right">';
          //echo '</td><td valign="top">';
          //if(isset($vars['sidebar_top'])) echo $template->parse($vars['sidebar_top']);
          $n =& $n2;
        }
      }
      $n[] = count($n);
      $current_side = $row['sidebar_id'];
      switch($row['block_type'])
      {
        case BLOCK_WIKIFORMAT:
        default:
          $parser = $template->makeParserText($vars['sidebar_section']);
          $c = RenderMan::render($row['block_content']);
          break;
        case BLOCK_TEMPLATEFORMAT:
          $parser = $template->makeParserText($vars['sidebar_section']);
          $c = $template->tplWikiFormat($row['block_content'], false, 'sidebar-editor.tpl');
          $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
          // fix for the "Administration" link that somehow didn't get rendered properly
          $c = preg_replace("/(^|\n)([ ]*)<a([ ]+.*)?>(.+)<\/a>(<br(.*)\/>)([\r\n]+|$)/isU", '\\1\\2<li><a\\3>\\4</a></li>\\7', $c);
          break;
        case BLOCK_HTML:
          $parser = $template->makeParserText($vars['sidebar_section_raw']);
          $c = $row['block_content'];
          $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
          break;
        case BLOCK_PHP:
          $parser = $template->makeParserText($vars['sidebar_section_raw']);
          ob_start();
          eval($row['block_content']);
          $c = ob_get_contents();
          ob_end_clean();
          $c = preg_replace('#<a (.*?)>(.*?)</a>#is', '<a href="#" onclick="return false;">\\2</a>', $c);
          break;
        case BLOCK_PLUGIN:
          $parser = $template->makeParserText($vars['sidebar_section_raw']);
          $c = ($template->fetch_block($row['block_content'])) ? $template->fetch_block($row['block_content']) : 'Can\'t find plugin block';
          break;
      }
      $block_name = $row['block_name']; // $template->tplWikiFormat($row['block_name']);
      if ( empty($block_name) )
        $block_name = '&lt;Unnamed&gt;';
      $t = '<span title="Double-click to rename this block" id="sbrename_' . $row['item_id'] . '" ondblclick="ajaxRenameSidebarStage1(this, \''.$row['item_id'].'\'); return false;">' . $block_name . '</span>';
      if($row['item_enabled'] == 0) $t .= ' <span id="disabled_'.$row['item_id'].'" style="color: red;">(disabled)</span>';
      else           $t .= ' <span id="disabled_'.$row['item_id'].'" style="color: red; display: none;">(disabled)</span>';
      $side = ( $row['sidebar_id'] == SIDEBAR_LEFT ) ? SIDEBAR_RIGHT : SIDEBAR_LEFT;
      $tb = '<a title="Enable or disable this block"    href="'.makeUrl($paths->page, 'action=disenable&id='.$row['item_id'].''       , true).'" onclick="ajaxDisenableBlock(\''.$row['item_id'].'\'); return false;"   ><img alt="Enable/disable this block" style="border-width: 0;" src="'.scriptPath.'/images/disenable.png" /></a>
             <a title="Edit the contents of this block" href="'.makeUrl($paths->page, 'action=edit&id='.$row['item_id'].''            , true).'" onclick="ajaxEditBlock(\''.$row['item_id'].'\', this); return false;"><img alt="Edit this block" style="border-width: 0;" src="'.scriptPath.'/images/edit.png" /></a>
             <a title="Permanently delete this block"   href="'.makeUrl($paths->page, 'action=delete&id='.$row['item_id'].''          , true).'" onclick="if(confirm(\'Do you really want to delete this block?\')) { ajaxDeleteBlock(\''.$row['item_id'].'\', this); } return false;"><img alt="Delete this block" style="border-width: 0;" src="'.scriptPath.'/images/delete.png" /></a>
             <a title="Move this block to the other sidebar" href="'.makeUrl($paths->page, 'action=move&id='.$row['item_id'].'&side='.$side, true).'"><img alt="Move this block" style="border-width: 0;" src="'.scriptPath.'/images/move.png" /></a>';
      $as = '';
      $ae = '&nbsp;&nbsp;'.$tb;
      $parser->assign_vars(Array('CONTENT'=>$c,'TITLE'=>$t,'ADMIN_START'=>$as,'ADMIN_END'=>$ae));
      echo $parser->run();
      unset($parser);
      
    }
    $db->free_result();
    //if(isset($vars['sidebar_top'])) echo $template->parse($vars['sidebar_bottom']);
    echo '</div></td></tr></table>';
    echo '<form action="'.makeUrl($paths->page).'" method="post">';
    $order = implode(',', $n1);
    echo "<input type='hidden' id='divOrder_Left' name='order_left' value='{$order}' />";
    $order = implode(',', $n2);
    echo "<input type='hidden' id='divOrder_Right' name='order_right' value='{$order}' />";
    echo '
          <div style="margin: 0 auto 0 auto; text-align: center;">
            <input type="submit" name="save" style="font-weight: bold;" value="Save changes" />
            <input type="submit" name="revert" style="font-weight: normal;" value="Revert" onclick="return confirm(\'Do you really want to revert your changes?\nNote: this does not revert edits or deletions, those are saved as soon as you confirm the action.\')" />
            <br />
            <a href="'.makeUrl($paths->page, 'action=new&id=0', true).'">Create new block</a>  |  <a href="'.makeUrl(getConfig('main_page'), false, true).'">Main Page</a>
          </div>
        </form>
         ';
  }
  
  $template->footer();
}

?>