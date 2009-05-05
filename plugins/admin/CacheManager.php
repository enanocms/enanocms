<?php

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

// Cache manager - regenerate and clear various cached values

function page_Admin_CacheManager()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $cache;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  // validation/actions
  if ( isset($_POST['refresh']) || isset($_POST['clear']) )
  {
    $success = false;
    
    $target = ( isset($_POST['refresh']) ) ? $_POST['refresh'] : $_POST['clear'];
    $do_refresh = isset($_POST['refresh']);
    switch ( $target )
    {
      case 'page':
        $success = $cache->purge('page_meta');
        if ( $do_refresh && $success )
          $success = $paths->update_metadata_cache();
        break;
      case 'ranks':
        $success = $cache->purge('ranks');
        if ( $do_refresh && $success )
          $success = generate_cache_userranks();
        break;
      case 'sidebar':
        $success = $cache->purge('anon_sidebar');
        break;
      case 'plugins':
        $success = $cache->purge('plugins');
        if ( $do_refresh && $success )
          $success = $plugins->generate_plugins_cache();
        break;
      case 'template':
        if ( $dh = opendir(ENANO_ROOT . '/cache') )
        {
          while ( $file = @readdir($dh) )
          {
            $fullpath = ENANO_ROOT . "/cache/$file";
            // we don't want to mess with directories
            if ( !is_file($fullpath) )
              continue;
            
            if ( preg_match('/\.(?:tpl|css)\.php$/', $file) )
            {
              unlink($fullpath);
            }
          }
          $success = true;
        }
        break;
      case 'aes':
        $success = @unlink(ENANO_ROOT . '/cache/aes_decrypt.php');
        break;
      case 'lang':
        if ( $dh = opendir(ENANO_ROOT . '/cache') )
        {
          while ( $file = @readdir($dh) )
          {
            $fullpath = ENANO_ROOT . "/cache/$file";
            // we don't want to mess with directories
            if ( !is_file($fullpath) )
              continue;
            
            if ( preg_match('/^lang_json_(?:[a-f0-9]+?)\.php$/', $file) || preg_match('/^(?:cache_)?lang_(?:[0-9]+?)\.php$/', $file) )
              unlink($fullpath);
          }
          $success = true;
        }
        if ( $do_refresh && $success )
        {
          // for each language in the database, call regen_caches()
          $q = $db->sql_query('SELECT lang_id FROM ' . table_prefix . 'language;');
          if ( !$q )
            $db->_die();
          while ( $row = $db->fetchrow($q) )
          {
            $lang_local = ( $row['lang_id'] == $lang->lang_id ) ? $lang : new Language($row['lang_id']);
            $success = $lang_local->regen_caches();
            if ( !$success )
              break 2;
          }
        }
        break;
      case 'js':
        if ( $dh = opendir(ENANO_ROOT . '/cache') )
        {
          while ( $file = @readdir($dh) )
          {
            $fullpath = ENANO_ROOT . "/cache/$file";
            // we don't want to mess with directories
            if ( !is_file($fullpath) )
              continue;
            
            // compressed javascript
            if ( preg_match('/^jsres_(?:[A-z0-9_-]+)\.js\.json$/', $file) )
              unlink($fullpath);
            // tinymce stuff
            else if ( preg_match('/^tiny_mce_(?:[a-f0-9]+)\.gz$/', $file) )
              unlink($fullpath);
          }
          $success = true;
        }
        break;
      case 'thumbs':
        if ( $dh = opendir(ENANO_ROOT . '/cache') )
        {
          while ( $file = @readdir($dh) )
          {
            $fullpath = ENANO_ROOT . "/cache/$file";
            // we don't want to mess with directories
            if ( !is_file($fullpath) )
              continue;
            
            if ( preg_match('/^(?:[a-z0-9\._,-]+)-(?:[0-9]{10})-[0-9]+x[0-9]+\.([a-z0-9_-]+)$/i', $file) )
              unlink($fullpath);
          }
          $success = true;
        }
        break;
      case 'wikieditnotice':
        $cache->purge('wiki_edit_notice');
        if ( $do_refresh )
          $template->get_wiki_edit_notice();
        
        $success = true;
        break;
      case 'all':
        $success = purge_all_caches();
        if ( $do_refresh )
        {
          //
          // refresh all static (non-incremental) caches
          //
          
          // pages
          $success = $paths->update_metadata_cache();
          if ( !$success )
            break;
          
          // user ranks
          $success = generate_cache_userranks();
          if ( !$success )
            break;
          
          // plugins
          $success = $plugins->generate_plugins_cache();
          if ( !$success )
            break;
          
          // wiki edit notice
          $template->get_wiki_edit_notice();
          
          // languages
          $q = $db->sql_query('SELECT lang_id FROM ' . table_prefix . 'language;');
          if ( !$q )
            $db->_die();
          while ( $row = $db->fetchrow($q) )
          {
            $lang_local = ( $row['lang_id'] == $lang->lang_id ) ? $lang : new Language($row['lang_id']);
            $success = $lang_local->regen_caches();
            if ( !$success )
              break 2;
          }
        }
        break;
      default:
        $code = $plugins->setHook('acp_cache_manager_action');
        foreach ( $code as $cmd )
        {
          eval($cmd);
        }
        break;
    }
    if ( $success )
    {
      echo '<div class="info-box">' . $lang->get('acpcm_msg_action_success') . '</div>';
    }
    else
    {
      echo '<div class="error-box">' . $lang->get('acpcm_err_action_failed') . '</div>';
    }
  }
  else if ( isset($_POST['save']) )
  {
    $config_value = ( isset($_POST['cache_thumbs']) ) ? '1' : '0';
    setConfig('cache_thumbs', $config_value);
    echo '<div class="info-box">' . $lang->get('acpcm_msg_action_success') . '</div>';
  }
  
  echo '<h3><img alt=" " src="' . scriptPath . '/images/icons/applets/cachemanager.png" />&nbsp;&nbsp;&nbsp;' . $lang->get('acpcm_heading_main') . '</h3>';
  echo '<p>' . $lang->get('acpcm_intro') . '</p>';
  
  echo '<div class="warning-box">' . $lang->get('acpcm_msg_refresh_warning') . '</div>';
  
  acp_start_form();
  ?>
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <!-- HEADER -->
      <tr>
        <th colspan="2">
          <?php echo $lang->get('acpcm_table_header'); ?>
        </th>
      </tr>
      
      <!-- ENABLE CACHE -->
      <tr>
        <td class="row1" colspan="2">
          <label>
            <input type="checkbox" name="cache_thumbs"<?php if ( getConfig('cache_thumbs') == '1' ) echo ' checked="checked"'; ?> />
            <?php echo $lang->get('acpcm_lbl_enable_cache'); ?>
          </label>
          <br />
          <small>
            <?php echo $lang->get('acpcm_hint_enable_cache'); ?>
          </small>
        </td>
      </tr>
      
      <!-- CLEAR ALL -->
      <tr>
      <td class="row2" style="width: 120px; text-align: center;">
          <button name="clear" value="all"><?php echo $lang->get('acpcm_btn_clear_all'); ?></button>
        </td>
        <td class="row2">
          <?php echo $lang->get('acpcm_hint_clear_all'); ?>
        </td>
      </tr>
      
      <?php
      // if caching is disabled, might as well break off here
      if ( getConfig('cache_thumbs') == '1' ):
      ?>
      
      <!-- REFRESH ALL -->
      <tr>
        <td class="row1" style="text-align: center;">
          <button name="refresh" value="all"><?php echo $lang->get('acpcm_btn_refresh_all'); ?></button>
        </td>
        <td class="row1">
          <?php echo $lang->get('acpcm_hint_refresh_all'); ?>
        </td>
      </tr>
      
      <!-- INDIVIDUAL CACHES -->
      <tr>
        <th class="subhead" colspan="2">
          <?php echo $lang->get('acpcm_th_individual_caches'); ?>
        </th>
      </tr>
      
      <?php
      $class = 'row2';
      $cache_list = array('page', 'ranks', 'sidebar', 'plugins', 'template', 'aes', 'lang', 'js', 'thumbs', 'wikieditnotice');
      $code = $plugins->setHook('acp_cache_manager_list_caches');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      foreach ( $cache_list as $target )
      {
        $class = ( $class == 'row1' ) ? 'row2' : 'row1';
        ?><tr>
        <td class="<?php echo $class; ?>" style="text-align: center;">
          <button name="refresh" value="<?php echo $target; ?>"<?php if ( in_array($target, array('template', 'sidebar', 'aes', 'js', 'thumbs')) ) echo ' disabled="disabled"'; ?>>
            <?php echo $lang->get('acpcm_btn_refresh'); ?>
          </button>
          <button name="clear" value="<?php echo $target; ?>">
            <?php echo $lang->get('acpcm_btn_clear'); ?>
          </button>
        </td>
        <td class="<?php echo $class; ?>">
          <b><?php echo $lang->get("acpcm_cache_{$target}_desc_title"); ?></b> &ndash;
          <?php echo $lang->get("acpcm_cache_{$target}_desc_body"); ?>
        </td>
        </tr>
      <?php
      }
      
      // getConfig('cache_thumbs') == '1'
      endif;
      ?>
      
      <!-- SAVE CHANGES -->
      <tr>
        <th colspan="2" class="subhead">
          <input type="submit" name="save" value="<?php echo $lang->get('etc_save_changes'); ?>" style="font-weight: bold;" />
          <input type="submit" name="cancel" value="<?php echo $lang->get('etc_cancel'); ?>" />
        </th>
      </tr>
    </table>
  </div>
  <?php
  echo '</form>';
}

?>
