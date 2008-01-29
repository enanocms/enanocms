<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1 (Caoineag alpha 1)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function page_Admin_LangManager()
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
  if ( isset($_POST['action']) )
  {
    $action =& $_POST['action'];
    // Parse parameters
    if ( strpos($action, ';') )
    {
      // Parameter section
      $parms = substr($action, strpos($action, ';') + 1);
      
      // Action name section
      $action = substr($action, 0, strpos($action, ';'));
      
      // Match all parameters
      preg_match_all('/([a-z0-9_]+)=(.+?)(;|$)/', $parms, $matches);
      $parms = array();
      
      // For each full parameter, assign $parms an associative value
      foreach ( $matches[0] as $i => $_ )
      {
        $parm = $matches[2][$i];
        
        // Is this parameter in the form of an integer?
        // (designed to ease validation later)
        if ( preg_match('/^[0-9]+$/', $parm) )
          // Yes, run intval(), this enabling is_int()-ish checks
          $parm = intval($parm);
        
        $parms[$matches[1][$i]] = $parm;
      }
    }
    switch ( $action )
    {
      case 'install_language':
        $lang_list = list_available_languages();
        // Verify that we have this language's metadata
        if ( isset($lang_list[@$parms['iso639']]) )
        {
          // From here it's all downhill :-)
          $lang_code =& $parms['iso639'];
          $lang_data =& $lang_list[$lang_code];
          
          $result = install_language($lang_code, $lang_data['name_eng'], $lang_data['name']);
          if ( $result )
          {
            // Language installed. Import the language files.
            $lang_local = new Language($lang_code);
            if ( file_exists(ENANO_ROOT . "/language/{$lang_data['dir']}/backup.json") )
            {
              $lang_local->import(ENANO_ROOT . "/language/{$lang_data['dir']}/backup.json");
            }
            else
            {
              foreach ( array('core', 'admin', 'tools', 'user') as $file )
              {
                $lang_local->import(ENANO_ROOT . "/language/{$lang_data['dir']}/$file.json");
              }
            }
            unset($lang_local);
            
            echo '<div class="info-box">' . $lang->get('acplm_msg_lang_install_success', array('lang_name' => htmlspecialchars($lang_data['name_eng']))) . '</div>';
          }
        }
        break;
      case 'modify_language':
        $lang_id =& $parms['lang_id'];
        if ( !is_int($lang_id) )
        {
          echo 'Hacking attempt';
          break;
        }
        
        if ( isset($parms['finish']) && !empty($_POST['lang_name_native']) && !empty($_POST['lang_name_english']) )
        {
          // We just did validation above, it's safe to save.
          $name_native = $db->escape($_POST['lang_name_native']);
          $name_english = $db->escape($_POST['lang_name_english']);
          
          $q = $db->sql_query('UPDATE ' . table_prefix . "language SET lang_name_native = '$name_native', lang_name_default = '$name_english' WHERE lang_id = $lang_id;");
          if ( !$q )
            $db->_die();
          
          echo '<div class="info-box">' . $lang->get('acplm_msg_basic_save_success') . '</div>';
        }
        
        // Select language data
        $q = $db->sql_query('SELECT lang_name_native, lang_name_default, lang_code FROM ' . table_prefix . "language WHERE lang_id = $lang_id;");
        if ( !$q )
          $db->_die();
        
        list($name_native, $name_english, $lang_code) = $db->fetchrow_num();
        
        // Output properties table
        echo '<h3>' . $lang->get('acplm_heading_modify') . '</h3>';
        
        acp_start_form();
        
        ?>
          <div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th colspan="2">
                  <?php
                    echo $lang->get('acplm_th_lang_basic');
                  ?>
                </th>
              </tr>
              <tr>
              <td class="row2" style="width: 50%;">
                  <?php
                    echo str_replace('"', '', $lang->get('acplm_field_lang_name_native'));
                  ?>
                </td>
                <td class="row1">
                  <input type="text" name="lang_name_native" value="<?php echo htmlspecialchars($name_native); ?>" />
                </td>
              </tr>
              <tr>
                <td class="row2">
                  <?php
                    echo $lang->get('acplm_field_lang_name_english');
                  ?>
                </td>
                <td class="row1">
                  <input type="text" name="lang_name_english" value="<?php echo htmlspecialchars($name_english); ?>" />
                </td>
              </tr>
              <tr>
                <td class="row2">
                  <?php
                    echo $lang->get('acplm_field_lang_code') . '<br />'
                       . '<small>' . $lang->get('acplm_field_lang_code_hint') . '</small>';
                  ?>
                </td>
                <td class="row1">
                  <?php
                    echo $lang_code;
                  ?>
                </td>
              </tr>
              <tr>
                <th class="subhead" colspan="2">
                  <button name="action" value="modify_language;finish=1;lang_id=<?php echo $lang_id; ?>"><?php echo $lang->get('etc_save_changes'); ?></button>
                </th>
              </tr>
            </table>
          </div>
        </form>
        
        <?php
        acp_start_form();
        ?>
        
        <h3><?php echo $lang->get('acplm_heading_edit_strings_portal'); ?></h3>
        <p><?php echo $lang->get('acplm_msg_edit_strings_portal_intro'); ?></p>
        
        <p>
        
        <?php
        
        // Grab a Language object
        if ( $lang->lang_id == $lang_id )
        {
          $lang_local =& $lang;
        }
        else
        {
          $lang_local = new Language($lang_id);
          $lang_local->fetch();
        }
        
        $categories_loc = array();
        
        // Using the & here ensures that a reference is created, thus avoiding wasting memory
        foreach ( $lang_local->strings as $cat => &$_ )
        {
          unset($_);
          $categories_loc[$cat] = htmlspecialchars($lang->get("meta_$cat"));
        }
        
        asort($categories_loc);
        
        echo '<select name="cat_id">';
        foreach ( $categories_loc as $cat_id => $cat_name)
        {
          echo "<option value=\"$cat_id\">$cat_name</option>";
        }
        echo '</select>';
        
        ?>
        <button name="action" value="edit_strings;lang_id=<?php echo $lang_id; ?>">
          <?php echo $lang->get('acplm_btn_edit_strings_portal'); ?>
        </button>
        </p>
        
        <h3><?php echo $lang->get('acplm_heading_reimport_portal'); ?></h3>
        <p><?php echo $lang->get('acplm_msg_reimport_portal_intro'); ?></p>
        
        <p>
          <button name="action" value="reimport;iso639=<?php echo $lang_code; ?>;lang_id=<?php echo $lang_id; ?>">
            <?php echo $lang->get('acplm_btn_reimport'); ?>
          </button>
        </p>
        
        </form>
        
        <?php
        
        echo '<h3>' . $lang->get('acplm_heading_backup') . '</h3>';
        echo '<p>' . $lang->get('acplm_backup_intro') . '</p>';
        
        echo '<form action="' . makeUrlNS('Admin', 'LangManager') . '" method="post">';
        echo '<button name="action" value="backup_language;lang_id=' . $lang_id . '">' . $lang->get('acplm_btn_create_backup') . '</button>';
        echo '</form>';
        
        return true;
      case 'edit_strings':
        
        $cat_id = @$_POST['cat_id'];
        if ( !preg_match('/^[a-z0-9]+$/', $cat_id) || !is_int(@$parms['lang_id']) )
          break;
        
        $lang_id =& $parms['lang_id'];
        
        if ( isset($parms['save']) )
        {
          // Grab a Language object
          if ( $lang->lang_id == $lang_id )
          {
            $lang_local =& $lang;
          }
          else
          {
            $lang_local = new Language($lang_id);
          }
          // Main save loop
          // Trying to minimize queries as much as possible here, but you know how that goes.
          $count_upd = 0;
          foreach ( $_POST['string'] as $string_id => $user_content )
          {
            $curr_content = $lang_local->get_uncensored("{$cat_id}_{$string_id}");
            if ( $curr_content != $user_content )
            {
              $count_upd++;
              $user_content = $db->escape($user_content);
              $string_id = $db->escape($string_id);
              $q = $db->sql_query('UPDATE ' . table_prefix . "language_strings SET string_content = '$user_content' WHERE lang_id = $lang_id AND string_category = '$cat_id' AND string_name = '$string_id';");
              if ( !$q )
                $db->_die();
            }
          }
          if ( $count_upd > 0 )
          {
            // Update the cache
            $lang_local->regen_caches();
            
            // Update modification time
            $q = $db->sql_query('UPDATE ' . table_prefix . "language SET last_changed = " . time() . " WHERE lang_id = $lang_id;");
            if ( !$q )
              $db->_die();
          }
          
          echo '<div class="info-box">' . $lang->get('acplm_msg_string_save_success') . '</div>';
        }
        
        acp_start_form();
        
        $cat_name = $lang->get("meta_$cat_id");
        echo '<h3>' . $lang->get('acplm_editor_heading', array('cat_name' => $cat_name)) . '</h3>';
        
        // Fetch all strings
        // This is more efficient than iterating through $lang->strings, I think.
        $q = $db->sql_query('SELECT string_id, string_name, string_content FROM ' . table_prefix . "language_strings WHERE string_category = '$cat_id' AND lang_id = $lang_id;");
        if ( !$q )
          $db->_die();
        
        ?>
        <div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th style="width: 3%;"><?php echo $lang->get('acplm_editor_col_string_name'); ?></th>
              <th><?php echo $lang->get('acplm_editor_col_string_content'); ?></th>
            </tr>
        <?php
        
        while ( $row = $db->fetchrow_num() )
        {
          list($string_id, $string_name, $string_content) = $row;
          unset($row);
          
          echo '<tr>';
          
          if ( strpos($string_content, "\n") )
          {
            $editor = '<textarea rows="' . get_line_count($string_content) . '" cols="50" style="width: 99%;" ';
            $editor .= 'name="string[' . htmlspecialchars($string_name) . ']" ';
            $editor .= '>' . htmlspecialchars($string_content);
            $editor .= '</textarea>';
          }
          else
          {
            $editor = '<input type="text" size="50" style="width: 99%;" ';
            $editor .= 'name="string[' . htmlspecialchars($string_name) . ']" ';
            $editor .= 'value="' . htmlspecialchars($string_content) . '" ';
            $editor .= '/>';
          }
          
          echo '<td class="row2">' . htmlspecialchars($string_name) . '</td>';
          echo '<td class="row1">' . $editor . '</td>';
          
          
          echo '</tr>';
          echo "\n";
        }
        
        echo '<tr>
                <th class="subhead" colspan="2">';
                
        echo '<input type="hidden" name="cat_id" value="' . $cat_id . '" />';
                
        // Button: save
        echo '<button name="action" value="edit_strings;lang_id=' . $lang_id . ';save=1" style="font-weight: bold;">' . $lang->get('etc_save_changes') . '</button> ';
        // Button: revert
        echo '<button name="action" value="edit_strings;lang_id=' . $lang_id . '" style="font-weight: normal;">' . $lang->get('acplm_editor_btn_revert') . '</button> ';
        // Button: cancel
        echo '<button name="action" value="modify_language;lang_id=' . $lang_id . '" style="font-weight: normal;">' . $lang->get('acplm_editor_btn_cancel') . '</button>';
                
        echo '  </th>
              </tr>';
        
        ?>
          </table>
        </div>
        <?php
        echo '</form>';
        
        return true;
      case 'reimport':
        if ( !isset($parms['iso639']) || !is_int(@$parms['lang_id']) )
          break;
        
        $lang_code =& $parms['iso639'];
        $lang_id =& $parms['lang_id'];
        
        $lang_list = list_available_languages();
        
        if ( !isset($lang_list[$lang_code]) )
          break;
        
        // Grab a Language object
        if ( $lang->lang_id == $lang_id )
        {
          $lang_local =& $lang;
        }
        else
        {
          $lang_local = new Language($lang_id);
        }
        
        $lang_data =& $lang_list[$lang_code];
        
        // This is the big re-import loop
        if ( file_exists(ENANO_ROOT . "/language/{$lang_data['dir']}/backup.json") )
        {
          $lang_local->import(ENANO_ROOT . "/language/{$lang_data['dir']}/backup.json");
        }
        else
        {
          foreach ( array('core', 'admin', 'tools', 'user') as $file )
          {
            $lang_local->import(ENANO_ROOT . "/language/{$lang_data['dir']}/$file.json");
          }
        }
        
        echo '<div class="info-box">' . $lang->get('acplm_msg_reimport_success') . '</div>';
        
        break;
      case 'backup_language':
        if ( !is_int(@$parms['lang_id']) )
          break;
        
        $lang_id =& $parms['lang_id'];
        
        // Grab a Language object
        if ( $lang->lang_id == $lang_id )
        {
          $lang_local =& $lang;
        }
        else
        {
          $lang_local = new Language($lang_id);
        }
        
        $filename = 'enano_lang_' . $lang_local->lang_code . '_' . enano_date('ymd') . '.json';
        
        // Free as much memory as possible
        $db->close();
        unset($GLOBALS['session'], $GLOBALS['paths'], $GLOBALS['template'], $GLOBALS['plugins']);
        
        // HTTP headers
        header('Content-type: application/json');
        header('Content-disposition: attachment; filename=' . $filename);
        
        // Export to JSON
        $lang_struct = array(
            'categories' => array_keys($lang_local->strings),
            'strings' => $lang_local->strings
          );
        
        $lang_struct = enano_json_encode($lang_struct);
        
        header('Content-length: ' . strlen($lang_struct));
        echo $lang_struct;
        
        exit;
        
      case 'uninstall_language':
        if ( !is_int(@$parms['lang_id']) )
          break;
        
        $lang_id =& $parms['lang_id'];
        
        if ( isset($parms['confirm']) )
        {
          $lang_default = intval(getConfig('default_language'));
          if ( $lang_default == $lang_id )
          {
            echo '<div class="error-box">' . $lang->get('acplm_err_cant_uninstall_default') . '</div>';
            break;
          }
          if ( $lang_id == $lang->lang_id )
          {
            // Unload the current language since it's about to be uninstalled
            unset($lang, $GLOBALS['lang']);
            $GLOBALS['lang'] = new Language($lang_default);
            global $lang;
          }
          // We're clear
          
          // Remove cache files
          $cache_file = ENANO_ROOT . "/cache/lang_{$lang_id}.php";
          if ( file_exists($cache_file) )
            @unlink($cache_file);
          
          // Remove strings
          $q = $db->sql_query('DELETE FROM ' . table_prefix . "language_strings WHERE lang_id = $lang_id;");
          if ( !$q )
            $db->_die();
          
          // Delete the language
          $q = $db->sql_query('DELETE FROM ' . table_prefix . "language WHERE lang_id = $lang_id;");
          if ( !$q )
            $db->_die();
          
          echo '<div class="info-box">' . $lang->get('acplm_msg_uninstall_success') . '</div>';
          break;
        }
        
        acp_start_form();
        
        echo '<h3>' . $lang->get('acplm_uninstall_confirm_title') . '</h3>';
        echo '<p>' . $lang->get('acplm_uninstall_confirm_body') . '</p>';
        
        echo '<p><button name="action" style="font-weight: bold;" value="uninstall_language;lang_id=' . $lang_id . ';confirm=1">' . $lang->get('acplm_btn_uninstall_confirm') . '</button> ';
        echo '<button name="action" value="home">' . $lang->get('acplm_btn_uninstall_cancel') . '</button></p>';
        
        echo '</form>';
        return true;
    }
  }
  
  acp_start_form();
  
  // Select current languages
  $q = $db->sql_query('SELECT lang_code, lang_name_native, lang_name_default, lang_id FROM ' . table_prefix . "language ORDER BY lang_id ASC;");
  if ( !$q )
    $db->_die();
  
  // Language properties/edit/delete portal table
  echo '<h3>' . $lang->get('acplm_heading_editor_portal') . '</h3>';
  
  echo '<div class="tblholder">';
  echo '<table border="0" cellspacing="1" cellpadding="4">';
  echo '<tr>
          <th>' . $lang->get('acplm_col_lang_id') . '</th>
          <th>' . $lang->get('acplm_col_lang_code') . '</th>
          <th>' . $lang->get('acplm_col_lang_name') . '</th>
          <th>' . $lang->get('acplm_col_lang_name_eng') . '</th>
          <th></th>
        </tr>';
  
  $cls = 'row2';
  
  $btn_edit = $lang->get('acplm_portal_btn_edit');
  $btn_unin = $lang->get('acplm_portal_btn_unin');
  
  while ( $row = $db->fetchrow($q) )
  {
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    
    echo '<tr>';
    
    $lang_code = htmlspecialchars($row['lang_code']);
    $lang_name_native  = htmlspecialchars($row['lang_name_native']);
    $lang_name_english = htmlspecialchars($row['lang_name_default']);
    
    echo "<td class=\"$cls\" style=\"text-align: center;\">{$row['lang_id']}</td>";
    echo "<td class=\"$cls\" style=\"text-align: center;\">{$lang_code}</td>";
    echo "<td class=\"$cls\" style=\"text-align: center;\">{$lang_name_native}</td>";
    echo "<td class=\"$cls\" style=\"text-align: center;\">{$lang_name_english}</td>";
    echo "<td class=\"$cls\" style=\"text-align: center;\"><button name=\"action\" value=\"modify_language;lang_id={$row['lang_id']}\">$btn_edit</button> <button name=\"action\" value=\"uninstall_language;lang_id={$row['lang_id']}\">$btn_unin</button></td>";
    
    echo '</tr>';
  }
  
  echo '</table></div>';
  
  // Reset the result pointer to zero so we can fetch that list of languages again
  if ( !$db->sql_data_seek(0, $q) )
  {
    $db->_die('LangManager doing seek back to zero for installation blacklist');
  }
  
  // $lang_list is fetched by the posthandler sometimes
  if ( !isset($lang_list) )
  {
    // Build a list of languages in the languages/ directory, then
    // eliminate the ones that are already installed.
    $lang_list = list_available_languages();
  }
  
  while ( $row = $db->fetchrow($q) )
  {
    $lang_code =& $row['lang_code'];
    if ( isset($lang_list[$lang_code]) )
    {
      unset($lang_list[$lang_code]);
      unset($lang_list[$lang_code]); // PHP <5.1.4 Zend bug
    }
  }
  
  if ( count($lang_list) > 0 )
  {
    echo '<h3>' . $lang->get('acplm_heading_install') . '</h3>';
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>' . $lang->get('acplm_col_lang_code') . '</th>
                <th>' . $lang->get('acplm_col_lang_name') . '</th>
                <th>' . $lang->get('acplm_col_lang_name_eng') . '</th>
                <th></th>
              </tr>';
              
    $cls = 'row2';
    foreach ( $lang_list as $lang_code => $lang_data )
    {
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      
      echo '<tr>';
      
      $lang_code = htmlspecialchars($lang_code);
      $lang_data['name'] = htmlspecialchars($lang_data['name']);
      $lang_data['name_eng'] = htmlspecialchars($lang_data['name_eng']);
      
      echo "<td class=\"$cls\" style=\"text-align: center;\">$lang_code</td>";
      echo "<td class=\"$cls\" style=\"text-align: center;\">{$lang_data['name']}</td>";
      echo "<td class=\"$cls\" style=\"text-align: center;\">{$lang_data['name_eng']}</td>";
      echo "<td class=\"$cls\" style=\"text-align: center;\"><button name=\"action\" value=\"install_language;iso639=$lang_code\">" . $lang->get('acplm_btn_install_language') . "</button></td>";
      
      echo '</tr>';
    }
    echo '    </tr>
            </table>
          </div>';
  }
  echo '</form>';
}

