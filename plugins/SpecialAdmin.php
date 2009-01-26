<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialadmin_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialadmin_desc",
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

// $plugins->attachHook('session_started', 'SpecialAdmin_paths_init();');

function SpecialAdmin_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_administration',
    'urlname'=>'Administration',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
  
  $paths->add_page(Array(
    'name'=>'specialpage_manage_sidebar',
    'urlname'=>'EditSidebar',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

$plugins->attachHook('session_started', 'SpecialAdmin_theme_init();');
$plugins->attachHook('common_post', 'SpecialAdmin_include();');

function SpecialAdmin_theme_init()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  // Admin pages that were too enormous to be in this file were split off into the plugins/admin/ directory in 1.0.1.
  // Only load these files if we're looking to load the admin panel
  list($pid, $ns) = RenderMan::strToPageID($paths->get_pageid_from_url());
  if ( $ns == 'Admin' || ( $pid == 'Administration' && $ns == 'Special' ) )
  {
    // Set the theme
    $session->theme = 'admin';
    $session->style = 'default';
    
    $template->add_header('<script type="text/javascript" src="' . cdnPath . '/includes/clientside/static/admin-menu.js"></script>');
  }
}

function SpecialAdmin_include()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  // Admin pages that were too enormous to be in this file were split off into the plugins/admin/ directory in 1.0.1.
  // Only load these files if we're looking to load the admin panel
  list($pid, $ns) = RenderMan::strToPageID($paths->get_pageid_from_url());
  if ( $ns == 'Admin' || ( $pid == 'Administration' && $ns == 'Special' ) )
  {
    require(ENANO_ROOT . '/plugins/admin/PageManager.php');
    require(ENANO_ROOT . '/plugins/admin/PageEditor.php');
    require(ENANO_ROOT . '/plugins/admin/PageGroups.php');
    require(ENANO_ROOT . '/plugins/admin/GroupManager.php');
    require(ENANO_ROOT . '/plugins/admin/SecurityLog.php');
    require(ENANO_ROOT . '/plugins/admin/UserManager.php');
    require(ENANO_ROOT . '/plugins/admin/UserRanks.php');
    require(ENANO_ROOT . '/plugins/admin/LangManager.php');
    require(ENANO_ROOT . '/plugins/admin/ThemeManager.php');
    require(ENANO_ROOT . '/plugins/admin/PluginManager.php');
    require(ENANO_ROOT . '/plugins/admin/CacheManager.php');
  }
}

// For convenience and nothing more.
function acp_start_form()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', ( isset($_GET['sqldbg']) ? 'sqldbg&' : '' ) . ( isset($_GET['nocompress']) ? 'nocompress&' : '' ) . 'module='.$paths->cpage['module']).'" method="post" enctype="multipart/form-data">';
}

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
    $req = new Request_HTTP('ktulu.enanocms.org', '/meta/updates.xml');
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
      else
      {
        if ( version_compare(enano_version(true), $match[2], '<') )
        {
          $response = str_replace_once('</latest>', "  <haveupdates />\n  </latest>", $response);
        }
        echo $response;
      }
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
  if( file_exists(ENANO_ROOT.'/install/install.php') && !defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">
            ' . $lang->get('acphome_msg_install_files') . '
          </div>';
  }
  
  echo '<h3>' . $lang->get('acphome_heading_updates') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_updates_info', array('updates_url' => 'http://ktulu.enanocms.org/meta/updates.xml')) . '</p>';
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
    require_once(ENANO_ROOT . '/includes/stats.php');
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
  
  // Any hooks?
  $code = $plugins->setHook('acp_home');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
  
  // Security log
  echo '<h3>' . $lang->get('acphome_heading_seclog') . '</h3>';
  echo '<p>' . $lang->get('acphome_msg_seclog_info') . '</p>';
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
    setConfig('main_page', sanitize_page_id($_POST['main_page']));
    setConfig('copyright_notice', $_POST['copyright']);
    setConfig('contact_email', $_POST['contact_email']);
    
    setConfig('main_page_alt_enable', ( isset($_POST['main_page_alt_enable']) && $_POST['main_page_alt_enable'] === '1' ? '1' : '0' ));
    if ( !empty($_POST['main_page_alt']) )
    {
      setConfig('main_page_alt', sanitize_page_id($_POST['main_page_alt']));
    }
    
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
    if ( in_array($_POST['comment_spam_policy'], array('moderate', 'reject', 'accept')) )
    {
      setConfig('comment_spam_policy', $_POST['comment_spam_policy']);
    }
    
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
    
    // Default theme
    $default_theme = ( isset($template->named_theme_list[@$_POST['default_theme']]) ) ? $_POST['default_theme'] : $template->theme_list[0]['theme_id'];
    setConfig('theme_default', $default_theme);
    
    // Breadcrumb mode
    if ( in_array($_POST['breadcrumb_mode'], array('subpages', 'always', 'never')) )
    {
      setConfig('breadcrumb_mode', $_POST['breadcrumb_mode']);
    }
    
    // CDN path
    if ( preg_match('/^http:\/\//', $_POST['cdn_path']) || $_POST['cdn_path'] === '' )
    {
      // trim off a trailing slash
      setConfig('cdn_path', preg_replace('#/$#', '', $_POST['cdn_path']));
    }
    
    setConfig('register_tou', RenderMan::preprocess_text($_POST['register_tou'], true, false));
    
    // Account lockout policy
    if ( ctype_digit($_POST['lockout_threshold']) )
      setConfig('lockout_threshold', $_POST['lockout_threshold']);
    
    if ( ctype_digit($_POST['lockout_duration']) )
      setConfig('lockout_duration', $_POST['lockout_duration']);
    
    if ( in_array($_POST['lockout_policy'], array('disable', 'captcha', 'lockout')) )
      setConfig('lockout_policy', $_POST['lockout_policy']);
    
    // Session time
    foreach ( array('session_short_time', 'session_remember_time') as $k )
    {
      if ( strval(intval($_POST[$k])) === $_POST[$k] && intval($_POST[$k]) >= 0 )
      {
        setConfig($k, $_POST[$k]);
      }
    }
    
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
    setConfig('avatar_upload_gravatar', ( isset($_POST['avatar_upload_gravatar']) ? '1' : '0' ));
    if ( in_array($_POST['gravatar_rating'], array('g', 'pg', 'r', 'x')) )
    {
      setConfig('gravatar_rating', $_POST['gravatar_rating']);
    }
    
    if ( is_dir(ENANO_ROOT . '/' . $_POST['avatar_directory']) )
    {
      if ( preg_match('/^[A-z0-9_-]+(?:\/(?:[A-z0-9_-]+))*\/?$/', $_POST['avatar_directory']) )
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
      echo '<div class="error-box">' . $lang->get('acpgc_err_avatar_dir_not_exist') . '</div>';
    }
    
    setConfig('userpage_grant_acl', ( isset($_POST['userpage_grant_acl']) ? '1' : '0' ));
    
    // Allow plugins to save their changes
    $code = $plugins->setHook('acp_general_save');
    foreach ( $code as $cmd )
    {
      eval($cmd);
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
      
      <tr>
        <th colspan="2" class="subhead"><?php echo $lang->get('acpgc_heading_submain'); ?></th>
      </tr>
      
      <!-- site name -->
      
      <tr>
        <td class="row1" style="width: 50%;">
          <?php echo $lang->get('acpgc_field_site_name'); ?>
        </td>
        <td class="row1" style="width: 50%;">
          <input type="text" name="site_name" size="30" value="<?php echo htmlspecialchars(getConfig('site_name')); ?>" />
        </td>
      </tr>
      
      <!-- site tagline -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_site_desc'); ?>
        </td>
        <td class="row2">
          <input type="text" name="site_desc" size="30" value="<?php echo htmlspecialchars(getConfig('site_desc')); ?>" />
        </td>
      </tr>
      
      <!-- main page -->
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_main_page'); ?></td>
        <td class="row1">
          <?php echo $template->pagename_field('main_page', sanitize_page_id(getConfig('main_page'))); ?><br />
            <label><input type="radio" name="main_page_alt_enable" value="0" onclick="$('#main_page_alt_tr').hide();" <?php if ( getConfig('main_page_alt_enable', '0') == '0' ) echo 'checked="checked" '; ?>/> <?php echo $lang->get('acpgc_field_main_page_option_same'); ?></label><br />
            <label><input type="radio" name="main_page_alt_enable" value="1" onclick="$('#main_page_alt_tr').show();" <?php if ( getConfig('main_page_alt_enable', '0') == '1' ) echo 'checked="checked" '; ?>/> <?php echo $lang->get('acpgc_field_main_page_option_members'); ?></label>
        </td>
      </tr>
      <tr id="main_page_alt_tr"<?php if ( getConfig('main_page_alt_enable', '0') == '0' ) echo ' style="display: none;"'; ?>>
        <td class="row3">
          <?php echo $lang->get('acpgc_field_main_page_members'); ?>
        </td>
        <td class="row3">
          <?php echo $template->pagename_field('main_page_alt', sanitize_page_id(getConfig('main_page_alt', /* default alt to current main page */ getConfig('main_page', 'Main_Page')))); ?>
        </td>
      </tr>
      
      <!-- copyright notice -->
      <tr>
        <td class="row2">
            <?php echo $lang->get('acpgc_field_copyright'); ?>
        </td>
        <td class="row2">
          <input type="text" name="copyright" size="30" value="<?php echo htmlspecialchars(getConfig('copyright_notice')); ?>" />
        </td>
      </tr>
      <tr>
        <td class="row1" colspan="2">
          <?php echo $lang->get('acpgc_field_copyright_hint'); ?>
        </td>
      </tr>
      
      <!-- contact e-mail -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_contactemail'); ?><br />
          <small><?php echo $lang->get('acpgc_field_contactemail_hint'); ?></small>
        </td>
        <td class="row2">
          <input name="contact_email" type="text" size="40" value="<?php echo htmlspecialchars(getConfig('contact_email')); ?>" />
        </td>
      </tr>
      
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
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_comment_spam_policy'); ?><br />
          <small><?php echo $lang->get('acpgc_field_comment_spam_policy_hint'); ?></small>
        </td>
        <td class="row2">
          <label>
            <input name="comment_spam_policy" type="radio" value="moderate" <?php if ( getConfig('comment_spam_policy', 'moderate') == 'moderate' ) echo 'checked="checked"'; ?>/>
            <?php echo $lang->get('acpgc_field_comment_spam_policy_moderate'); ?>
          </label><br /> 
          <label>
            <input name="comment_spam_policy" type="radio" value="reject" <?php if ( getConfig('comment_spam_policy', 'moderate') == 'reject' ) echo 'checked="checked"'; ?>/>
            <?php echo $lang->get('acpgc_field_comment_spam_policy_reject'); ?>
          </label><br />
          <label>
            <input name="comment_spam_policy" type="radio" value="accept" <?php if ( getConfig('comment_spam_policy', 'moderate') == 'accept' ) echo 'checked="checked"'; ?>/>
            <?php echo $lang->get('acpgc_field_comment_spam_policy_accept'); ?>
          </label>
        </td>
      </tr>
            
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
      
    <!-- Default theme -->
    
      <tr><th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_default_theme'); ?></th></tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_defualt_theme'); ?>
        </td>
        <td class="row2">
          <select name="default_theme">
          <?php
              foreach ( $template->named_theme_list as $theme_id => $theme_data )
              {
                $theme_name = htmlspecialchars($theme_data['theme_name']);
                $selected = ( $theme_id === getConfig('theme_default') ) ? ' selected="selected"' : '';
                echo "  <option value=\"$theme_id\"$selected>$theme_name</option>\n          ";
              }
            ?>
          </select>
        </td>
      </tr>
      
    <!-- Breadcrumbs -->
    
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_breadcrumb_mode'); ?>
        </td>
        <td class="row1">
          <select name="breadcrumb_mode">
          <?php
            foreach ( array('subpages', 'always', 'never') as $mode )
            {
              $str = $lang->get("acpgc_field_breadcrumb_mode_$mode");
              $sel = ( getConfig('breadcrumb_mode') == $mode ) ? ' selected="selected"' : '';
              echo "  <option value=\"$mode\"$sel>$str</option>\n          ";
            }
          ?>
          </select>
        </td>
      </tr>
    
    <!-- CDN settings -->
    
      <tr>
        <td class="row2">
          <p>
            <?php echo $lang->get('acpgc_field_cdn_path'); ?><br />
            <small><?php echo $lang->get('acpgc_field_cdn_path_hint'); ?></small>
          </p>
          <p>
            <small><?php echo $lang->get('acpgc_field_cdn_path_example'); ?></small>
          </p>
        </td>
        <td class="row2">
          <input type="text" name="cdn_path" value="<?php echo htmlspecialchars(getConfig('cdn_path', '')); ?>" style="width: 98%;" />
        </td>
      </tr>
      
    <!-- Allow plugins to add code -->
      <?php
      $code = $plugins->setHook('acp_general_basic');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      ?>
      
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
      
    <!-- Terms of Use -->
    
      <tr>
        <th class="subhead" colspan="2">
          <?php echo $lang->get('acpgc_heading_tou'); ?>
        </th>
      </tr>
      
      <tr>
        <td class="row2">
          <b><?php echo $lang->get('acpgc_field_tou'); ?></b><br />
          <small><?php echo $lang->get('acpgc_field_tou_hint'); ?></small>
        </td>
        <td class="row2">
          <?php
            $terms = getConfig('register_tou');
            echo $template->tinymce_textarea('register_tou', $terms, 10, 40);
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
      
    <!-- Session length -->
    
      <tr>
        <th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_sessions'); ?></th>
      </tr>
      
      <tr>
        <td class="row3" colspan="2"><?php echo $lang->get('acpgc_hint_sessions_noelev'); ?></td>
      </tr>
      
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpgc_field_short_time'); ?><br />
          <small><?php echo $lang->get('acpgc_field_short_time_hint'); ?></small>
        </td>
        <td class="row1">
          <input type="text" name="session_short_time" value="<?php echo getConfig('session_short_time', '720'); ?>" size="4" />
        </td>
      </tr>
      
      <tr>
        <td class="row2">
          <?php echo $lang->get('acpgc_field_long_time'); ?><br />
          <small><?php echo $lang->get('acpgc_field_long_time_hint'); ?></small>
        </td>
        <td class="row2">
          <input type="text" name="session_remember_time" value="<?php echo getConfig('session_remember_time', '30'); ?>" size="4" />
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
          
          <br />
          
          <label>
          <input type="checkbox" name="avatar_upload_gravatar" <?php if ( getConfig('avatar_upload_gravatar') == '1' || getConfig('avatar_upload_gravatar') === false ) echo 'checked="checked" '; ?>onclick="document.getElementById('acp_gravatar_rating').style.display = ( this.checked ) ? 'block' : 'none';" />
            <?php echo $lang->get('acpgc_field_avatar_upload_gravatar'); ?>
          </label>
          
          <br />
          
          <fieldset id="acp_gravatar_rating" style="margin-top: 10px; <?php if ( getConfig('avatar_upload_gravatar') === '0' ) echo ' display: none;'; ?>">
          
            <?php /* The four ratings are g, pg, r, and x - loop through each and output a localized string and a radiobutton */ ?>
            <legend><?php echo $lang->get('acpgc_field_avatar_gravatar_rating'); ?></legend>
            
            <?php foreach ( array('g', 'pg', 'r', 'x') as $rating ): ?>
            
            <label>
            
              <input type="radio" name="gravatar_rating" value="<?php echo $rating; ?>"<?php
                // Check the button if this is the current selection *or* if we're on "G" and the current configuration value is unset
                if ( getConfig('gravatar_rating', 'g') == $rating )
                  echo ' checked="checked"';
                ?> />
                
              <?php /* The localized string */ ?>
              <?php echo $lang->get("acpgc_field_avatar_gravatar_rating_$rating"); ?>
              
            </label>
            
            <br />
            
            <?php endforeach; ?>
          </fieldset>
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
      
    <!-- Misc. options -->
    
      <tr>
        <th class="subhead" colspan="2"><?php echo $lang->get('acpgc_heading_usermisc'); ?></th>
      </tr>
      
      <tr>
        <td class="row1">
          <b><?php echo $lang->get('acpgc_field_userpage_acl_title'); ?></b><br />
          <small>
            <?php echo $lang->get('acpgc_field_userpage_acl_hint'); ?>
          </small>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="userpage_grant_acl" <?php if ( getConfig('userpage_grant_acl', '1') == '1' ) echo 'checked="checked" '; ?>/>
            <?php echo $lang->get('acpgc_field_userpage_acl'); ?>
          </label>
        </td>
      </tr>
      
    <!-- Allow plugins to add code -->
      <?php
      $code = $plugins->setHook('acp_general_users');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      ?>
        
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
      
    <!-- Allow plugins to add code -->
      <?php
      $code = $plugins->setHook('acp_general_sidebar');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      ?>
      
    <!-- Save button -->
    
    </table>
    </div>
    
    <!-- Allow plugins to add code -->
      <?php
      $code = $plugins->setHook('acp_general_tail');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      ?>
        
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
      if ( defined('ENANO_DEMO_MODE') )
        // Hackish but safe.
        $_POST['imagemagick_path'] = '/usr/bin/convert';
      $old = getConfig('imagemagick_path');
      $oldnew = "{$old}||{$_POST['imagemagick_path']}";
      $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,edit_summary,author,page_text) VALUES(\'security\',\'magick_path\',' . time() . ',\'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\',\'' . $db->escape($session->username) . '\',\'' . $db->escape($oldnew) . '\');');
      if ( !$q )
        $db->_die();
      setConfig('imagemagick_path', $_POST['imagemagick_path']);
    }
    else if ( $_POST['imagemagick_path'] != getConfig('imagemagick_path') )
    {
      echo '<span style="color: red">' . $lang->get('acpup_err_magick_not_found', array('magick_path' => htmlspecialchars($_POST['imagemagick_path']))) . '</span>';
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
  acp_start_form();
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
  acp_start_form();
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

/*
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
    else if ( !preg_match('/^[A-z0-9_\.-]+\.php$/', $_GET['plugin']) )
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
        case "reimport":
          $plugin_id = substr($plugin, 0, -4);
          if ( isset($plugins->loaded_plugins[$plugin_id]) )
          {
            // plugin file is safe, call import
            $lang->import_plugin( ENANO_ROOT . "/plugins/$plugin" );
            echo '<div class="info-box">' . $lang->get('acppl_msg_reimport_success') . '</div>';
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
      $is_system = ( @$system[$plugin_files[$i]] );
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
              <td class="'.$cls.'"'.( $bgcolor != '' ? str_replace_once(';', '; text-align: center;', $bgcolor) : ' style="text-align: center;"' ).' nowrap="nowrap">';
      if ( !in_array($plugin_files[$i], $plugins->system_plugins) )
      {
        if ( getConfig('plugin_'.$plugin_files[$i]) == '1' )
        {
          echo '<a href="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'&amp;show_system=' . ( $show_system ? 'yes' : 'no' ) . '&amp;action=disable&amp;plugin='.$plugin_files[$i].'">' . $lang->get('acppl_btn_disable') . '</a>';
          echo ' | ';
          echo '<a title="' . $lang->get('acppl_btn_reimport_tip') . '" href="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'&amp;show_system=' . ( $show_system ? 'yes' : 'no' ) . '&amp;action=reimport&amp;plugin='.$plugin_files[$i].'">' . $lang->get('acppl_btn_reimport') . '</a>';
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
*/

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
    die('<h3>' . $lang->get('acpdb_err_not_supported_title') . '</h3>
          <p>' . $lang->get('acpdb_err_not_supported_desc') . '</p>');
  
  if(isset($_GET['submitting']) && $_GET['submitting'] == 'yes' && defined('ENANO_DEMO_MODE') )
  {
    redirect(makeUrlComplete('Special', 'Administration'), $lang->get('acpdb_err_demo_mode_title'), $lang->get('acpdb_err_demo_mode_desc'), 5);
  }
  
  global $system_table_list;
  if(isset($_GET['submitting']) && $_GET['submitting'] == 'yes')
  {
    
    if(defined('SQL_BACKUP_CRYPT'))
      // Try to increase our time limit
      @set_time_limit(0);
    // Do the actual export
    $aesext = ( defined('SQL_BACKUP_CRYPT') ) ? '.tea' : '';
    $filename = 'enano_backup_' . enano_date('ymd') . '.sql' . $aesext;
    ob_start();
    // Spew some headers
    $headdate = enano_date('F d, Y \a\t h:i a');
    echo <<<HEADER
-- Enano CMS SQL backup
-- Generated on {$headdate} by {$session->username}

HEADER;
    // build the table list
    $base = ( isset($_POST['do_system_tables']) ) ? $system_table_list : Array();
    $add  = ( isset($_POST['additional_tables'])) ? $_POST['additional_tables'] : Array();
    $tables = array_merge($base, $add);
    
    // Log it!
    $e = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'db_backup\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($session->username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', \'' . $db->escape(implode(', ', $tables)) . '\')');
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
    header('Content-disposition: attachment; filename='.$filename.'');
    header('Content-type: application/octet-stream');
    header('Content-length: '.strlen($data));
    echo $data;
    exit;
  }
  else
  {
    // Show the UI
    echo '<form action="'.makeUrlNS('Admin', 'DBBackup', 'submitting=yes', true).'" method="post" enctype="multipart/form-data">';
    ?>
    <p><?php echo $lang->get('acpdb_intro'); ?></p>
    <p><label><input type="checkbox" name="do_system_tables" checked="checked" /> <?php echo $lang->get('acpdb_lbl_system_tables'); ?></label><p>
    <p><?php echo $lang->get('acpdb_lbl_additional_tables'); ?></p>
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
    <p><label><input type="checkbox" name="do_struct" checked="checked" /> <?php echo $lang->get('acpdb_lbl_include_structure'); ?></label><br />
       <label><input type="checkbox" name="do_data"   checked="checked" /> <?php echo $lang->get('acpdb_lbl_include_data'); ?></label>
       </p>
    <p><input type="submit" value="<?php echo $lang->get('acpdb_btn_create_backup'); ?>" /></p>
    <?php
    echo '</form>';
  }
}

/*
 * Admin:PageManager sources are in /plugins/admin/PageManager.php.
 */

/*
 * Admin:PageEditor sources are in /plugins/admin/PageEditor.php.
 */

/*
 * Admin:ThemeManager sources are in /plugins/admin/ThemeManager.php.
 */

/*
 * Admin:GroupManager sources are in /plugins/admin/GroupManager.php.
 */

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
  
  echo '<h2>' . $lang->get('acpcp_heading_main') . '</h2>';
  echo '<p>
          ' . $lang->get('acpcp_intro') . '
        </p>';
  
  // Start form
  
  if ( isset($_POST['coppa_address']) )
  {
    // Saving changes
    $enable_coppa = ( isset($_POST['enable_coppa']) ) ? '1' : '0';
    setConfig('enable_coppa', $enable_coppa);
    
    $address = $_POST['coppa_address']; // RenderMan::preprocess_text($_POST['coppa_address'], true, false);
    setConfig('coppa_address', $address);
    
    echo '<div class="info-box">' . $lang->get('acpcp_msg_save_success') . '</div>';
  }
  
  acp_start_form();
  
  echo '<div class="tblholder">';
  echo '<table border="0" cellspacing="1" cellpadding="4">';
  echo '<tr>
          <th colspan="2">
            ' . $lang->get('acpcp_th_form') . '
          </th>
        </tr>';
        
  echo '<tr>
          <td class="row1">
            ' . $lang->get('acpcp_field_enable_title') . '
          </td>
          <td class="row2">
            <label><input type="checkbox" name="enable_coppa" ' . ( ( getConfig('enable_coppa') == '1' ) ? 'checked="checked"' : '' ) . ' /> ' . $lang->get('acpcp_field_enable') . '</label><br />
            <small>' . $lang->get('acpcp_field_enable_hint') . '</small>
          </td>
        </tr>';
        
  echo '<tr>
          <td class="row1">
            ' . $lang->get('acpcp_field_address') . '<br />
            <small>' . $lang->get('acpcp_field_address_hint') . '</small>
          </td>
          <td class="row2">
            <textarea name="coppa_address" rows="7" cols="40">' . getConfig('coppa_address') . '</textarea>
          </td>
        </tr>';
        
  echo '<tr>
          <th colspan="2" class="subhead">
            <input type="submit" value="' . $lang->get('etc_save_changes') . '" />
          </th>
        </tr>';
        
  echo '</table>';
  
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
      $errors[] = $lang->get('acpmm_err_need_subject');
    if ( empty($message) )
      $errors[] = $lang->get('acpmm_err_need_message');
    
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
      
      // FIXME: how to handle l10n with this?
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
      
      echo '<div class="info-box">' . $lang->get('acpmm_msg_send_success') . '</div>';
      
    }
    else
    {
      echo '<div class="warning-box">' . $lang->get('acpmm_err_send_fail') . '<ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
    }
    
  }
  else if ( isset($_POST['do_send']) && defined('ENANO_DEMO_MODE') )
  {
    echo '<div class="error-box">' . $lang->get('acpmm_err_demo') . '</div>';
  }
  acp_start_form();
  ?>
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr>
        <th colspan="2"><?php echo $lang->get('acpmm_heading_main'); ?></th>
      </tr>
      <tr>
        <td class="row2" rowspan="2" style="width: 30%; min-width: 200px;">
          <?php echo $lang->get('acpmm_field_group_to'); ?><br />
          <small>
            <?php echo $lang->get('acpmm_field_group_to_hint'); ?>
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
              list($g_name) = array_values($row);
              $g_name_langstr = 'groupcp_grp_' . strtolower($g_name);
              if ( ($g_langstr = $lang->get($g_name_langstr)) != $g_name_langstr )
              {
                $g_name = $g_langstr;
              }
              echo '<option value="' . $row['group_id'] . '">' . htmlspecialchars($g_name) . '</option>';
            }
            ?>
          </select>
        </td>
      </tr>
      <tr>
        <td class="row1">
          <?php echo $lang->get('acpmm_field_username'); ?> <input type="text" name="userlist" size="50" />
        </td>
      </tr>
      <tr>
        <td class="row2" style="width: 30%; min-width: 200px;">
          <?php echo $lang->get('acpmm_field_subject'); ?>
        </td>
        <td class="row1">
          <input name="subject" type="text" size="50" />
        </td>
      </tr>
      <tr>
        <td class="row2"  style="width: 30%; min-width: 200px;">
          <?php echo $lang->get('acpmm_field_message'); ?>
        </td>
        <td class="row1">
          <textarea name="message" rows="30" cols="60" style="width: 100%;"></textarea>
        </td>
      </tr>
      <tr>
        <th class="subhead" colspan="2" style="text-align: left;" valign="middle">
          <div style="float: right;"><input type="submit" name="do_send" value="<?php echo $lang->get('acpmm_btn_send'); ?>" /></div>
          <small style="font-weight: normal;"><?php echo $lang->get('acpmm_msg_send_takeawhile'); ?></small>
        </th>
      </tr>
      
    </table>
  </div>
  <?php
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
    if ( !$e )
      $db->_die('The ban list entry was not deleted.');
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
      echo '<div class="error-box">' . $lang->get('acpbc_err_empty') . '</div>';
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
          echo '<div class="error-box">' . $lang->get('acpbc_err_invalid_ip_range') . '</div>';
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
    echo '<div class="error-box">' . $lang->get('acpbc_err_demo', array('ban_target' => htmlspecialchars($_POST['value']))) . '</div>';
  }
  $q = $db->sql_query('SELECT ban_id,ban_type,ban_value,is_regex FROM '.table_prefix.'banlist ORDER BY ban_type;');
  if ( !$q )
    $db->_die('The banlist data could not be selected.');
  echo '<div class="tblholder" style="max-height: 800px; clip: rect(0px,auto,auto,0px); overflow: auto;">
          <table border="0" cellspacing="1" cellpadding="4">';
  echo '<tr>
          <th>' . $lang->get('acpbc_col_type') . '</th>
          <th>' . $lang->get('acpbc_col_value') . '</th>
          <th>' . $lang->get('acpbc_col_regex') . '</th>
          <th></th>
        </tr>';
  if ( $db->numrows() < 1 )
  {
    echo '<td class="row1" colspan="4">' . $lang->get('acpbc_msg_no_rules') . '</td>';
  }
  $cls = 'row2';
  while ( $r = $db->fetchrow() )
  {
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    if ( $r['ban_type'] == BAN_IP )
      $t = $lang->get('acpbc_ban_type_ip');
    else if ( $r['ban_type'] == BAN_USER )
      $t = $lang->get('acpbc_ban_type_username');
    else if ( $r['ban_type'] == BAN_EMAIL )
      $t = $lang->get('acpbc_ban_type_email');
    $g = ( $r['is_regex'] ) ? '<b>' . $lang->get('acpbc_ban_regex_yes') . '</b>' : $lang->get('acpbc_ban_regex_no');
    echo '<tr>
            <td class="'.$cls.'">'.$t.'</td>
            <td class="'.$cls.'">'.htmlspecialchars($r['ban_value']).'</td>
            <td class="'.$cls.'">'.$g.'</td>
            <td class="'.$cls.'"><a href="'.makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'BanControl&amp;action=delete&amp;id='.$r['ban_id']).'">' . $lang->get('acpbc_btn_delete') . '</a></td>
          </tr>';
  }
  $db->free_result();
  echo '</table></div>';
  echo '<h3>' . $lang->get('acpbc_heading_create_new') . '</h3>';
  acp_start_form();
  ?>
  
  <?php echo $lang->get('acpbc_field_type'); ?>
    <select name="type">
      <option value="<?php echo BAN_IP; ?>"><?php echo $lang->get('acpbc_ban_type_ip'); ?></option>
      <option value="<?php echo BAN_USER; ?>"><?php echo $lang->get('acpbc_ban_type_username'); ?></option>
      <option value="<?php echo BAN_EMAIL; ?>"><?php echo $lang->get('acpbc_ban_type_email'); ?></option>
    </select>
    <br />
    
  <?php echo $lang->get('acpbc_field_rule'); ?>
    <input type="text" name="value" size="30" /><br />
    <small><?php echo $lang->get('acpbc_field_rule_hint'); ?></small><br />
    
  <?php echo $lang->get('acpbc_field_reason'); ?>
    <textarea name="reason" rows="7" cols="40"></textarea><br />
    
  <label><input type="checkbox" name="regex" id="regex" /> <?php echo $lang->get('acpbc_field_regex'); ?></label>
    <?php echo $lang->get('acpbc_field_regex_hint'); ?><br />
    
  <input type="submit" style="font-weight: bold;" name="create" value="<?php echo $lang->get('acpbc_btn_create'); ?>" />
  <?php
  echo '</form>';
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
  echo '<h3>' . $lang->get('acplo_heading_main') . '</h3>
         <p>' . $lang->get('acplo_msg_logout_complete', array('mainpage_link' => makeUrl(get_main_page()))) . '</p>';
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
    if( !isset( $_GET['noheaders'] ) ) 
    {
      $template->header();
    }
    echo $lang->get('adm_page_tagline');
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
        load_component('messagebox');
        miniPromptMessage({
            title: $lang.get('user_logout_confirm_title_elev'),
            message: $lang.get('user_logout_confirm_body_elev'),
            buttons: [
              {
                text: $lang.get('user_logout_confirm_btn_logout'),
                color: 'red',
                style: {
                  fontWeight: 'bold'
                },
                onclick: function()
                {
                  var tigraentry = document.getElementById('i_div0_0').parentNode;
                  var tigraobj = $dynano(tigraentry);
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
                  miniPromptDestroy(this);
                  body.appendChild(div);
                  ajaxPageBin(namespace_list.Admin + 'AdminLogout');
                }
              },
              {
                text: $lang.get('etc_cancel'),
                onclick: function()
                {
                  miniPromptDestroy(this);
                }
              }
            ]
          });
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
      ajaxGet('<?php echo scriptPath; ?>/ajax.php?title='+t+'&_mode=getpage&noheaders&auth=' + ENANO_SID, function() {
          if ( ajax.readyState == 4 && ajax.status == 200 ) {
            var response = String(ajax.responseText + '');
            if ( check_json_response(response) )
            {
              response = parseJSON(response);
              if ( response.mode == 'error' )
              {
                if ( response.error == 'need_auth_to_admin' )
                {
                  load_component('login');
                  ajaxDynamicReauth(t);
                }
                else
                {
                  alert(response.error);
                }
              }
            }
            else
            {
              document.getElementById('ajaxPageContainer').innerHTML = ajax.responseText;
              fadeInfoBoxes();
              autofill_onload();
            }
          }
        });
    }
    <?php
    if ( !isset($_GET['module']) )
    {
      echo <<<EOF
    var _enanoAdminOnload = function() { ajaxPage('{$paths->nslist['Admin']}Home'); };
    addOnloadHook(_enanoAdminOnload);
    
EOF;
    }
    ?>
    var TREE_TPL = {
      'target'  : '_self',  // name of the frame links will be opened in
                  // other possible values are: _blank, _parent, _search, _self and _top
    
      'icon_e'  : '<?php echo cdnPath; ?>/images/icons/empty.gif',      // empty image
      'icon_l'  : '<?php echo cdnPath; ?>/images/spacer.gif',           // vertical line
      'icon_32' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root leaf icon normal
      'icon_36' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root leaf icon selected
      'icon_48' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root icon normal
      'icon_52' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root icon selected
      'icon_56' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root icon opened
      'icon_60' : '<?php echo cdnPath; ?>/images/spacer.gif',           // root icon selected
      'icon_16' : '<?php echo cdnPath; ?>/images/spacer.gif',           // node icon normal
      'icon_20' : '<?php echo cdnPath; ?>/images/spacer.gif',           // node icon selected
      'icon_24' : '<?php echo cdnPath; ?>/images/spacer.gif',           // node icon opened
      'icon_28' : '<?php echo cdnPath; ?>/images/spacer.gif',           // node icon selected opened
      'icon_0'  : '<?php echo cdnPath; ?>/images/icons/page.gif',       // leaf icon normal
      'icon_4'  : '<?php echo cdnPath; ?>/images/icons/page.gif',       // leaf icon selected
      'icon_8'  : '<?php echo cdnPath; ?>/images/icons/page.gif',       // leaf icon opened
      'icon_12' : '<?php echo cdnPath; ?>/images/icons/page.gif',       // leaf icon selected
      'icon_2'  : '<?php echo cdnPath; ?>/images/icons/joinbottom.gif', // junction for leaf
      'icon_3'  : '<?php echo cdnPath; ?>/images/icons/join.gif',       // junction for last leaf
      'icon_18' : '<?php echo cdnPath; ?>/images/icons/plusbottom.gif', // junction for closed node
      'icon_19' : '<?php echo cdnPath; ?>/images/icons/plus.gif',       // junction for last closed node
      'icon_26' : '<?php echo cdnPath; ?>/images/icons/minusbottom.gif',// junction for opened node
      'icon_27' : '<?php echo cdnPath; ?>/images/icons/minus.gif'       // junction for last opended node
    };
    
    addOnloadHook(function()
      {
        load_component(['fat', 'ajax', 'l10n', 'autofill', 'jquery', 'jquery-ui']);
        keepalive_onload();
      });
    
    <?php
    echo $paths->parseAdminTree(); // Make a Javascript array that defines the tree
    ?>
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
              call_user_func($fname);
            }
          } 
          else 
          {
            echo '<script type="text/javascript">document.write(\'<div class="wait-box">Please wait while the administration panel loads. You need to be using a recent browser with AJAX support in order to use Runt.</div>\');</script><noscript><div class="error-box">It looks like Javascript isn\'t enabled in your browser. Please enable Javascript or use a different browser to continue.</div></noscript>';
          }
          ?>
          </div>
          <script type="text/javascript">
            addOnloadHook(function()
              {
                if ( KILL_SWITCH )
                {
                  document.getElementById('ajaxPageContainer').innerHTML = '<div class="error-box">Because of the lack of AJAX support, support for Internet Explorer versions less than 6.0 has been disabled in Runt. You can download and use Mozilla Firefox (or Seamonkey under Windows 95); both have an up-to-date standards-compliant rendering engine that has been tested thoroughly with Enano.</div>';
                }
              }
            );
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
  global $cache;
  
  if($session->auth_level < USER_LEVEL_ADMIN) 
  {
    redirect(makeUrlNS('Special', 'Login/'.$paths->page, 'level='.USER_LEVEL_ADMIN), '', '', false);
    exit;
  }
  else 
  {
    
    $template->add_header('<script type="text/javascript" src="'.cdnPath.'/includes/clientside/dbx.js"></script>');
    $template->add_header('<script type="text/javascript" src="'.cdnPath.'/includes/clientside/dbx-key.js"></script>');
    $template->add_header('<script type="text/javascript" src="'.cdnPath.'/includes/clientside/sbedit.js"></script>');
    $template->add_header('<link rel="stylesheet" type="text/css" href="'.cdnPath.'/includes/clientside/dbx.css" />');
    
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
        $queries[] = 'UPDATE '.table_prefix.'sidebar SET item_order='.intval($om[$k]).' WHERE item_id='.intval($v[1]).';';
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
      $cache->purge('anon_sidebar');
      echo '<div class="info-box" style="margin: 10px 0;">' . $lang->get('sbedit_msg_order_update_success') . '</div>';
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
        echo '<div class="error-box" style="margin: 10px 0 10px 0;">' . $lang->get('sbedit_err_demo_php_disable') . '</div>';
        $_POST['php_content'] = '?>&lt;Nulled&gt;';
        $content = $_POST['php_content'];
      }
      
      // Get the value of item_order
      
      $q = $db->sql_query('SELECT * FROM '.table_prefix.'sidebar WHERE sidebar_id='.intval($_POST['sidebar_id']).';');
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
    
      $cache->purge('anon_sidebar');
      echo '<div class="info-box" style="margin: 10px 0;">' . $lang->get('sbedit_msg_item_added') . '</div>';
      
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
              <?php echo $lang->get('sbedit_create_intro'); ?>
            </p>
            <p>
              <select name="type" onchange="setType(this)"> <?php /* (NOT WORKING, at least in firefox 2) onload="var thingy = this; setTimeout('setType(thingy)', 500);" */ ?>
                <option value="<?php echo BLOCK_WIKIFORMAT; ?>"><?php echo $lang->get('sbedit_block_type_wiki'); ?></option>
                <option value="<?php echo BLOCK_TEMPLATEFORMAT; ?>"><?php echo $lang->get('sbedit_block_type_tpl'); ?></option>
                <option value="<?php echo BLOCK_HTML; ?>"><?php echo $lang->get('sbedit_block_type_html'); ?></option>
                <option value="<?php echo BLOCK_PHP; ?>"><?php echo $lang->get('sbedit_block_type_php'); ?></option>
                <option value="<?php echo BLOCK_PLUGIN; ?>"><?php echo $lang->get('sbedit_block_type_plugin'); ?></option>
              </select>
            </p>
            
            <p>
            
              <?php echo $lang->get('sbedit_field_block_title'); ?> <input name="title" type="text" size="40" /><br />
              <?php echo $lang->get('sbedit_field_block_sidebar'); ?>
                <select name="sidebar_id">
                  <option value="<?php echo SIDEBAR_LEFT; ?>"><?php echo $lang->get('sbedit_field_block_sidebar_left'); ?></option>
                  <option value="<?php echo SIDEBAR_RIGHT; ?>"><?php echo $lang->get('sbedit_field_block_sidebar_right'); ?></option>
                </select>
            
            </p>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_WIKIFORMAT; ?>">
              <p>
                <?php echo $lang->get('sbedit_field_wikitext'); ?>
              </p>
              <p>
                <textarea style="width: 98%;" name="wikiformat_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_TEMPLATEFORMAT; ?>">
              <p>
                <?php echo $lang->get('sbedit_field_tplcode'); ?>
              </p>
              <p>
                <textarea style="width: 98%;" name="templateformat_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_HTML; ?>">
              <p>
                <?php echo $lang->get('sbedit_field_html'); ?>
              </p>
              <p>
                <textarea style="width: 98%;" name="html_content" rows="15" cols="50"></textarea>
              </p>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_PHP; ?>">
              <?php if ( defined('ENANO_DEMO_MODE') ) { ?>
                <p><?php echo $lang->get('sbedit_field_php_disabled'); ?></p>
              <?php } else { ?>
              <?php echo $lang->get('sbedit_field_php'); ?>
              
              <p>
                <textarea style="width: 98%;" name="php_content" rows="15" cols="50"></textarea>
              </p>
              <?php } ?>
            </div>
            
            <div class="sbadd_block" id="blocktype_<?php echo BLOCK_PLUGIN; ?>">
              <p>
                <?php echo $lang->get('sbedit_field_plugin'); ?>
              </p>
              <p>
                <select name="plugin_id">
                <?php
                  foreach($template->plugin_blocks as $k => $c)
                  {
                    echo '<option value="'.$k.'">'.$lang->get($k).'</option>';
                  }
                ?>
                </select>
              </p>
            </div>
            
            <p>
            
              <input type="submit" name="create" value="<?php echo $lang->get('sbedit_btn_create_block'); ?>" style="font-weight: bold;" />&nbsp;
              <input type="submit" name="cancel" value="<?php echo $lang->get('etc_cancel'); ?>" />
            
            </p>
            
          </form>
          
          <script type="text/javascript">
            addOnloadHook(function()
              {
                var divs = getElementsByClassName(document, 'div', 'sbadd_block');
                for(var i in divs)
                {
                  if(divs[i].id != 'blocktype_<?php echo BLOCK_WIKIFORMAT; ?>') setTimeout("document.getElementById('"+divs[i].id+"').style.display = 'none';", 500);
                }
              });
          </script>
          
          <?php
          $template->footer();
          return;
          break;
        case 'move':
          $cache->purge('anon_sidebar');
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
          echo '<div class="info-box" style="margin: 10px 0;">' . $lang->get('sbedit_msg_block_moved') . '</div>';
          break;
        case 'delete':
          $query = $db->sql_query('DELETE FROM '.table_prefix.'sidebar WHERE item_id=' . intval($_GET['id']) . ';'); // Already checked for injection attempts ;-)
          if(!$query)
          {
            echo $db->get_error();
            $template->footer();
            exit;
          }
          $cache->purge('anon_sidebar');
          if(isset($_GET['ajax']))
          {
            ob_end_clean();
            die('GOOD');
          }
          echo '<div class="error-box" style="margin: 10px 0;">' . $lang->get('sbedit_msg_block_deleted') . '</div>';
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
          $cache->purge('anon_sidebar');
          
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
              $c = ($template->fetch_block($row['block_content'])) ? $template->fetch_block($row['block_content']) : $lang->get('sbedit_msg_plugin_not_loaded');
              break;
          }
          $c = preg_replace('/\{(restrict|hideif) ([a-z0-9_\(\)\|&! ]+)\}/', '', $c);
          $cache->purge('anon_sidebar');
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
        'TEXT' => $lang->get('sidebar_btn_changestyle')
      ));
    $template->tpl_strings['THEME_LINK'] = $parser->run();
    $parser->assign_vars(Array(
        'TEXT' => $lang->get('sidebar_btn_logout'),
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
          $c = ($template->fetch_block($row['block_content'], true)) ? $template->fetch_block($row['block_content'], true) : $lang->get('sbedit_msg_plugin_not_loaded');
          
          break;
      }
      $c = preg_replace('/\{(restrict|hideif) ([a-z0-9_\(\)\|&! ]+)\}/', '', $c);
      $block_name = $row['block_name']; // $template->tplWikiFormat($row['block_name']);
      if ( empty($block_name) )
        $block_name = '&lt;' . $lang->get('sbedit_note_block_unnamed') . '&gt;';
      $t = '<span title="' . $lang->get('sbedit_hint_rename') . '" id="sbrename_' . $row['item_id'] . '" ondblclick="ajaxRenameSidebarStage1(this, \''.$row['item_id'].'\'); return false;">' . $block_name . '</span>';
      if($row['item_enabled'] == 0) $t .= ' <span id="disabled_'.$row['item_id'].'" style="color: red;">' . $lang->get('sbedit_note_block_disabled') . '</span>';
      else           $t .= ' <span id="disabled_'.$row['item_id'].'" style="color: red; display: none;">' . $lang->get('sbedit_note_block_disabled') . '</span>';
      $side = ( $row['sidebar_id'] == SIDEBAR_LEFT ) ? SIDEBAR_RIGHT : SIDEBAR_LEFT;
      $tb = '<a title="' . $lang->get('sbedit_tip_disenable') . '" href="'.makeUrl($paths->page, 'action=disenable&id='.$row['item_id'].''       , true).'" onclick="ajaxDisenableBlock(\''.$row['item_id'].'\'); return false;"   ><img alt="' . $lang->get('sbedit_tip_disenable') . '" style="border-width: 0;" src="'.scriptPath.'/images/disenable.png" /></a>
             <a title="' . $lang->get('sbedit_tip_edit') . '"      href="'.makeUrl($paths->page, 'action=edit&id='.$row['item_id'].''            , true).'" onclick="ajaxEditBlock(\''.$row['item_id'].'\', this); return false;"><img alt="' . $lang->get('sbedit_tip_edit') . '" style="border-width: 0;" src="'.scriptPath.'/images/edit.png" /></a>
             <a title="' . $lang->get('sbedit_tip_delete') . '"    href="'.makeUrl($paths->page, 'action=delete&id='.$row['item_id'].''          , true).'" onclick="if(confirm(\'' . $lang->get('sbedit_msg_delete_confirm') . '\')) { ajaxDeleteBlock(\''.$row['item_id'].'\', this); } return false;"><img alt="' . $lang->get('sbedit_tip_delete') . '" style="border-width: 0;" src="'.scriptPath.'/images/delete.png" /></a>
             <a title="' . $lang->get('sbedit_tip_move') . '"      href="'.makeUrl($paths->page, 'action=move&id='.$row['item_id'].'&side='.$side, true).'"><img alt="' . $lang->get('sbedit_tip_move') . '" style="border-width: 0;" src="'.scriptPath.'/images/move.png" /></a>';
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
            <input type="submit" name="save" style="font-weight: bold;" value="' . $lang->get('etc_save_changes') . '" />
            <input type="submit" name="revert" style="font-weight: normal;" value="' . $lang->get('sbedit_btn_revert') . '" onclick="return confirm($lang.get(\'sbedit_msg_discard_order_confirm\'))" />
            <br />
            <a href="'.makeUrl($paths->page, 'action=new&id=0', true).'">' . $lang->get('sbedit_btn_create_new_stage1') . '</a>  |  <a href="'.makeUrl(get_main_page(), false, true).'">' . $lang->get('sbedit_btn_main_page') . '</a>
          </div>
        </form>
         ';
  }
  
  $template->footer();
}

?>