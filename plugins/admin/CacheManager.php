<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
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
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  echo '<h3><img alt=" " src="' . scriptPath . '/images/icons/applets/cachemanager.png" />&nbsp;&nbsp;&nbsp;' . $lang->get('acpcm_heading_main') . '</h3>';
  echo '<p>' . $lang->get('acpcm_intro') . '</p>';
  
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
        <td class="row2">
          <input type="submit" name="clear_all" value="<?php echo $lang->get('acpcm_btn_clear_all'); ?>" />
        </td>
        <td class="row2">
          <?php echo $lang->get('acpcm_hint_clear_all'); ?>
        </td>
      </tr>
      
      <!-- REFRESH ALL -->
      <tr>
        <td class="row1">
          <input type="submit" name="refresh_all" value="<?php echo $lang->get('acpcm_btn_refresh_all'); ?>" />
        </td>
        <td class="row1">
          <?php echo $lang->get('acpcm_hint_refresh_all'); ?>
        </td>
      </tr>
      
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
