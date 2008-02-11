<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * website.php - Installer website-settings stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

// Note: this is called from database_*.php, not directly from install.php

$ui->add_header('<script type="text/javascript" src="includes/js/formutils.js"></script>');
$ui->show_header();

?>

<script type="text/javascript">
  // <![CDATA[
  function ajaxMrwTest()
  {
    install_set_ajax_loading();
    // Send a series of tests to the server, and if we get an "expected" response
    setTimeout("ajaxGet(scriptPath + '/install/rewrite', __ajaxMrwTest_chain_rewrite);", 750);
  }
  var __ajaxMrwTest_chain_rewrite = function()
  {
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      if ( ajax.responseText == 'good_rewrite' )
      {
        ajaxMrwSet('rewrite');
      }
      else
      {
        ajaxGet(scriptPath + '/install/install.php/shortened?do=modrewrite_test', __ajaxMrwTest_chain_shortened);
      }
    }
  }
  var __ajaxMrwTest_chain_shortened = function()
  {
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      if ( ajax.responseText == 'good_shortened' )
      {
        ajaxMrwSet('standard');
      }
      else
      {
        ajaxGet(scriptPath + '/install/install.php?do=modrewrite_test&str=standard', __ajaxMrwTest_chain_standard);
      }
    }
  }
  var __ajaxMrwTest_chain_standard = function()
  {
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      if ( ajax.responseText == 'good_standard' )
      {
        ajaxMrwSet('standard');
      }
      else
      {
        install_unset_ajax_loading();
        new messagebox(MB_OK | MB_ICONSTOP, $lang.get('website_msg_ajax_test_fail_title'), $lang.get('website_msg_ajax_test_fail_body'));
      }
    }
  }
  function ajaxMrwSet(level)
  {
    install_unset_ajax_loading();
    if ( !in_array(level, ['rewrite', 'shortened', 'standard']) )
      return false;
    
    document.getElementById('url_radio_rewrite').checked = false;
    document.getElementById('url_radio_shortened').checked = false;
    document.getElementById('url_radio_standard').checked = false;
    document.getElementById('url_radio_' + level).checked = true;
    document.getElementById('url_radio_' + level).focus();
    
    switch ( level )
    {
      case 'rewrite':
        var str = $lang.get('website_msg_bestmethod_rewrite');
        break;
      case 'shortened':
        var str = $lang.get('website_msg_bestmethod_shortened');
        break;
      case 'standard':
        var str = $lang.get('website_msg_bestmethod_standard');
        break;
    }
    document.getElementById('mrw_report').className = 'info-box-mini';
    document.getElementById('mrw_report').innerHTML = str;
  }
  
  function verify()
  {
    var frm = document.forms['install_website'];
    var fail = false;
    if ( frm.site_name.value == '' )
    {
      fail = true;
      new Spry.Effect.Shake($(frm.site_name).object, {duration: 750}).start();
      frm.site_name.focus();
    }
    if ( frm.site_desc.value == '' )
    {
      new Spry.Effect.Shake($(frm.site_desc).object, {duration: 750}).start();
      if ( !fail )
        frm.site_desc.focus();
      fail = true;
    }
    if ( frm.copyright.value == '' )
    {
      new Spry.Effect.Shake($(frm.copyright).object, {duration: 750}).start();
      if ( !fail )
        frm.copyright.focus();
      fail = true;
    }
    return ( !fail );
  }
  // ]]>
</script>

<form action="install.php?stage=login" method="post" name="install_website" onsubmit="return verify();"><?php
  foreach ( $_POST as $key => &$value )
  {
    if ( !preg_match('/^[a-z0-9_]+$/', $key) )
      die('You idiot hacker...');
    if ( $key == '_cont' )
      continue;
    $value_clean = str_replace(array('\\', '"', '<', '>'), array('\\\\', '\\"', '&lt;', '&gt;'), $value);
    echo "\n  <input type=\"hidden\" name=\"$key\" value=\"$value_clean\" />";
  }
  
  $https = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' );
  $scriptpath_full = 'http' . ( $https ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . scriptPath . '/';
  ?>
  
  <table border="0" cellspacing="0" cellpadding="10">
  
    <tr>
      <td>
        <b><?php echo $lang->get('website_field_name'); ?></b><br />
        <span id="hint_site_name" class="fieldtip"><?php echo $lang->get('website_field_name_hint'); ?></span>
      </td>
      <td style="width: 50%;">
        <input type="text" name="site_name" size="50" tabindex="1" />
      </td>
    </tr>
    
    <tr>
      <td>
        <b><?php echo $lang->get('website_field_desc'); ?></b><br />
        <span id="hint_site_desc" class="fieldtip"><?php echo $lang->get('website_field_desc_hint'); ?></span>
      </td>
      <td>
        <input type="text" name="site_desc" size="50" tabindex="2" />
      </td>
    </tr>
    
    <tr>
      <td>
        <b><?php echo $lang->get('website_field_copyright'); ?></b><br />
        <span id="hint_copyright" class="fieldtip"><?php echo $lang->get('website_field_copyright_hint'); ?></span>
      </td>
      <td>
        <input type="text" name="copyright" size="50" tabindex="3" />
      </td>
    </tr>
    
    <tr>
      <td valign="top">
        <b><?php echo $lang->get('website_field_urlscheme'); ?></b><br />
        <?php echo $lang->get('website_field_urlscheme_hint'); ?>
      </td>
      <td>
      
        <table border="0" cellpadding="10" cellspacing="0">
          <tr>
            <td valign="top">
              <input type="radio" name="url_scheme" value="standard" id="url_radio_standard" tabindex="5" />
            </td>
            <td>
              <label for="url_radio_standard">
                <b><?php echo $lang->get('website_field_urlscheme_opt_standard'); ?></b>
              </label>
              <span class="fieldtip" id="hint_url_scheme_standard">
                <p><?php echo $lang->get('website_field_urlscheme_opt_standard_hint'); ?></p>
                <p><small><b><?php echo $lang->get('website_field_urlscheme_lbl_example'); ?></b> <tt><?php echo $scriptpath_full . 'index.php?title=Page'; ?></tt></small></p>
              </span>
            </td>
          </tr>
        </table>
        
        <table border="0" cellpadding="10" cellspacing="0">
          <tr>
            <td valign="top">
              <input type="radio" checked="checked" name="url_scheme" value="shortened" id="url_radio_shortened" tabindex="5" />
            </td>
            <td>
              <label for="url_radio_shortened">
                <b><?php echo $lang->get('website_field_urlscheme_opt_shortened'); ?></b>
              </label>
              <span class="fieldtip" id="hint_url_scheme_shortened">
                <p><?php echo $lang->get('website_field_urlscheme_opt_shortened_hint'); ?></p>
                <p><small><b><?php echo $lang->get('website_field_urlscheme_lbl_example'); ?></b> <tt><?php echo $scriptpath_full . 'index.php/Page'; ?></tt></small></p>
              </span>
            </td>
          </tr>
        </table>
        
        <table border="0" cellpadding="10" cellspacing="0">
          <tr>
            <td valign="top">
              <input type="radio" name="url_scheme" value="rewrite" id="url_radio_rewrite" tabindex="5" />
            </td>
            <td>
              <label for="url_radio_rewrite">
                <b><?php echo $lang->get('website_field_urlscheme_opt_rewrite'); ?></b>
              </label>
              <span id="hint_url_scheme_rewrite" class="fieldtip">
                <p><?php echo $lang->get('website_field_urlscheme_opt_rewrite_hint'); ?></p>
                <p><small><b><?php echo $lang->get('website_field_urlscheme_lbl_example'); ?></b> <tt><?php echo $scriptpath_full . 'Page'; ?></tt></small></p>
              </span>
            </td>
          </tr>
        </table>
        
        <p>
          <a href="#mrw_scan" onclick="ajaxMrwTest(); return false;" tabindex="4"><?php echo $lang->get('website_btn_urlscheme_detect'); ?></a>
        </p>
        
        <div id="mrw_report"></div>
        
      </td>
    </tr>
    
  </table>
  
  <div style="text-align: center;">
    <input type="submit" name="_cont" value="<?php echo $lang->get('meta_btn_continue'); ?>" tabindex="6" />
  </div>
  
</form>

