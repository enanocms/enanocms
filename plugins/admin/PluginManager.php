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

/**
 * SYNOPSIS OF PLUGIN FRAMEWORK
 *
 * The new plugin manager is making an alternative approach to managing plugin files by allowing metadata to be embedded in them
 * or optionally included from external files. This method is API- and format-compatible with old plugins. The change is being
 * made because we believe this will provide greater flexibility within plugin files.
 * 
 * Plugin files can contain one or more specially formatted comment blocks with metadata, language strings, and installation or
 * upgrade SQL schemas. For this to work, plugins need to define their version numbers in an Enano-readable and standardized
 * format, and we think the best way to do this is with JSON. It is important that plugins define both the current version and
 * a list of all past versions, and then have upgrade sections telling which version they go from and which one they go to.
 * 
 * The format for the special comment blocks is:
 <code>
 /**!blocktype( param1 = "value1"; [ param2 = "value2"; ... ] )**
 
 ... block content ...
 
 **!* / (remove that last space)
 </code>
 * The format inside blocks varies. Metadata and language strings will be in JSON; installation and upgrade schemas will be in
 * SQL. You can include an external file into a block using the following syntax inside of a block:
 <code>
 !include "path/to/file"
 </code>
 * The file will always be relative to the Enano root. So if your plugin has a language file in ENANO_ROOT/plugins/fooplugin/,
 * you would use "plugins/fooplugin/language.json".
 *
 * The format for plugin metadata is as follows:
 <code>
 /**!info**
 {
   "Plugin Name" : "Foo plugin",
   "Plugin URI" : "http://fooplugin.enanocms.org/",
   "Description" : "Some short descriptive text",
   "Author" : "John Doe",
   "Version" : "0.1",
   "Author URI" : "http://yourdomain.com/",
   "Version list" : [ "0.1-alpha1", "0.1-alpha2", "0.1-beta1", "0.1" ]
 }
 **!* /
 </code>
 * This is the format for language data:
 <code>
 /**!language**
 {
   // each entry at this level should be an ISO-639-1 language code.
   eng: {
     // from here on in is the standard langauge file format
     categories: [ 'meta', 'foo', 'bar' ],
     strings: {
       meta: {
         foo: "Foo strings",
         bar: "Bar strings"
       },
       foo: {
         string_name: "string value",
         string_name_2: "string value 2"
       }
     }
   }
 }
 **!* / (once more, remove the space in there)
 </code>
 * Here is the format for installation schemas:
 <code>
 /**!install**
 
 CREATE TABLE {{TABLE_PREFIX}}foo_table(
   ...
 )
 
 **!* /
 </code>
 * And finally, the format for upgrade schemas:
 <code>
 /**!upgrade from = "0.1-alpha1"; to = "0.1-alpha2"; **
 
 **!* /
 </code>
 * As a courtesy to your users, we ask that you also include an "uninstall" block that reverses any changes your plugin makes
 * to the database upon installation. The syntax is identical to that of the install block.
 * 
 * Remember that upgrades will always be done incrementally, so if the user is upgrading 0.1-alpha2 to 0.1, Enano's plugin
 * engine will run the 0.1-alpha2 to 0.1-beta1 upgrader, then the 0.1-beta1 to 0.1 upgrader, going by the versions listed in
 * the example metadata block above. As with the standard Enano installer, prefixing a query with '@' will cause it to be
 * performed "blindly", e.g. not checked for errors.
 * 
 * All of this information is effective as of Enano 1.1.4.
 */

// Plugin manager "2.0"

function page_Admin_PluginManager()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $cache;
  if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
  {
    $login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
    echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
    echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
    return;
  }
  
  $plugin_list = $plugins->get_plugin_list(null, false);
  
  // Are we processing an AJAX request from the smartform?
  if ( $paths->getParam(0) == 'action.json' )
  {
    // Set to application/json to discourage advertisement scripts
    header('Content-Type: text/javascript');
    
    // Init return data
    $return = array('mode' => 'error', 'error' => 'undefined');
    
    // Start parsing process
    try
    {
      // Is the request properly sent on POST?
      if ( isset($_POST['r']) )
      {
        // Try to decode the request
        $request = enano_json_decode($_POST['r']);
        // Is the action to perform specified?
        if ( isset($request['mode']) )
        {
          switch ( $request['mode'] )
          {
            case 'install':
              // did they specify a plugin to operate on?
              if ( !isset($request['plugin']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'No plugin specified.',
                );
                break;
              }
              if ( !isset($request['install_confirmed']) )
              {
                if ( $plugins->is_file_auth_plugin($request['plugin']) )
                {
                  $return = array(
                    'confirm_title' => 'acppl_msg_confirm_authext_title',
                    'confirm_body' => 'acppl_msg_confirm_authext_body',
                    'need_confirm' => true,
                    'success' => false
                  );
                  break;
                }
              }
              
              $return = $plugins->install_plugin($request['plugin'], $plugin_list);
              break;
            case 'upgrade':
              // did they specify a plugin to operate on?
              if ( !isset($request['plugin']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'No plugin specified.',
                );
                break;
              }
              
              $return = $plugins->upgrade_plugin($request['plugin'], $plugin_list);
              break;
            case 'reimport':
              // did they specify a plugin to operate on?
              if ( !isset($request['plugin']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'No plugin specified.',
                );
                break;
              }
              
              $return = $plugins->reimport_plugin_strings($request['plugin'], $plugin_list);
              break;
            case 'uninstall':
              // did they specify a plugin to operate on?
              if ( !isset($request['plugin']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'No plugin specified.',
                );
                break;
              }
              
              $return = $plugins->uninstall_plugin($request['plugin'], $plugin_list);
              break;
            case 'disable':
            case 'enable':
              // We're not in demo mode. Right?
              if ( defined('ENANO_DEMO_MODE') )
              {
                $return = array(
                    'mode' => 'error',
                    'error' => $lang->get('acppl_err_demo_mode')
                  );
                break;
              }
              $flags_col = ( $request['mode'] == 'disable' ) ?
                            "plugin_flags | "  . PLUGIN_DISABLED :
                            "plugin_flags & ~" . PLUGIN_DISABLED;
              // did they specify a plugin to operate on?
              if ( !isset($request['plugin']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'No plugin specified.',
                );
                break;
              }
              // is the plugin in the directory and already installed?
              if ( !isset($plugin_list[$request['plugin']]) || (
                  isset($plugin_list[$request['plugin']]) && !$plugin_list[$request['plugin']]['installed']
                ))
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'Invalid plugin specified.',
                );
                break;
              }
              // get plugin id
              $dataset =& $plugin_list[$request['plugin']];
              if ( empty($dataset['plugin id']) )
              {
                $return = array(
                  'mode' => 'error',
                  'error' => 'Couldn\'t retrieve plugin ID.',
                );
                break;
              }
              
              // log action
              $time        = time();
              $ip_db       = $db->escape($_SERVER['REMOTE_ADDR']);
              $username_db = $db->escape($session->username);
              $file_db     = $db->escape($request['plugin']);
              // request['mode'] is TRUSTED - the case statement will only process if it is one of {enable,disable}.
              $q = $db->sql_query('INSERT INTO '.table_prefix."logs(log_type, action, time_id, edit_summary, author, page_text) VALUES\n"
                                . "  ('security', 'plugin_{$request['mode']}', $time, '$ip_db', '$username_db', '$file_db');");
              if ( !$q )
                $db->_die();
              
              // perform update
              $q = $db->sql_query('UPDATE ' . table_prefix . "plugins SET plugin_flags = $flags_col WHERE plugin_id = {$dataset['plugin id']};");
              if ( !$q )
                $db->die_json();
              
              $cache->purge('plugins');
              
              $return = array(
                'success' => true
              );
              break;
            case 'import':
              // import all of the plugin_* config entries
              $q = $db->sql_query('SELECT config_name, config_value FROM ' . table_prefix . "config WHERE config_name LIKE 'plugin_%';");
              if ( !$q )
                $db->die_json();
              
              while ( $row = $db->fetchrow($q) )
              {
                $plugin_filename = preg_replace('/^plugin_/', '', $row['config_name']);
                if ( isset($plugin_list[$plugin_filename]) && !@$plugin_list[$plugin_filename]['installed'] )
                {
                  $return = $plugins->install_plugin($plugin_filename, $plugin_list);
                  if ( !$return['success'] )
                    break 2;
                  if ( $row['config_value'] == '0' )
                  {
                    $fn_db = $db->escape($plugin_filename);
                    $e = $db->sql_query('UPDATE ' . table_prefix . "plugins SET plugin_flags = plugin_flags | " . PLUGIN_DISABLED . " WHERE plugin_filename = '$fn_db';");
                    if ( !$e )
                      $db->die_json();
                  }
                }
              }
              $db->free_result($q);
              
              $q = $db->sql_query('DELETE FROM ' . table_prefix . "config WHERE config_name LIKE 'plugin_%';");
              if ( !$q )
                $db->die_json();
              
              $return = array('success' => true);
              break;
            default:
              // The requested action isn't something this script knows how to do
              $return = array(
                'mode' => 'error',
                'error' => 'Unknown mode "' . $request['mode'] . '" sent in request'
              );
              break;
          }
        }
        else
        {
          // Didn't specify action
          $return = array(
            'mode' => 'error',
            'error' => 'Missing key "mode" in request'
          );
        }
      }
      else
      {
        // Didn't send a request
        $return = array(
          'mode' => 'error',
          'error' => 'No request specified'
        );
      }
    }
    catch ( Exception $e )
    {
      // Sent a request but it's not valid JSON
      $return = array(
          'mode' => 'error',
          'error' => 'Invalid request - JSON parsing failed'
        );
    }
    
    echo enano_json_encode($return);
    
    return true;
  }
  
  // Sort so that system plugins come last
  ksort($plugin_list);
  $plugin_list_sorted = array();
  foreach ( $plugin_list as $filename => $data )
  {
    if ( !$data['system plugin'] )
    {
      $plugin_list_sorted[$filename] = $data;
    }
  }
  ksort($plugin_list_sorted);
  foreach ( $plugin_list as $filename => $data )
  {
    if ( $data['system plugin'] )
    {
      $plugin_list_sorted[$filename] = $data;
    }
  }
  
  $plugin_list =& $plugin_list_sorted;
  
  //
  // Not a JSON request, output normal HTML interface
  //
  
  // start printing things out
  echo '<h3>' . $lang->get('acppl_heading_main') . '</h3>';
  echo '<p>' . $lang->get('acppl_intro') . '</p>';
  ?>
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="5">
      <?php
      $rowid = '2';
      foreach ( $plugin_list as $filename => $data )
      {
        // print out all plugins
        $rowid = ( $rowid == '1' ) ? '2' : '1';
        $plugin_name = ( preg_match('/^[a-z0-9_]+$/', $data['plugin name']) ) ? $lang->get($data['plugin name']) : $data['plugin name'];
        $plugin_basics = $lang->get('acppl_lbl_plugin_name', array(
            'plugin' => $plugin_name,
            'author' => $data['author']
          ));
        $color = '';
        $buttons = '';
        if ( $data['system plugin'] )
        {
          $status = $lang->get('acppl_lbl_status_system');
        }
        else if ( $data['installed'] && !( $data['status'] & PLUGIN_DISABLED ) && !( $data['status'] & PLUGIN_OUTOFDATE ) )
        {
          // this plugin is all good
          $color = '_green';
          $status = $lang->get('acppl_lbl_status_installed');
          $buttons = 'reimport|uninstall|disable';
        }
        else if ( $data['installed'] && $data['status'] & PLUGIN_OUTOFDATE )
        {
          $color = '_red';
          $status = $lang->get('acppl_lbl_status_need_upgrade');
          $buttons = 'uninstall|upgrade';
        }
        else if ( $data['installed'] && $data['status'] & PLUGIN_DISABLED )
        {
          $color = '_red';
          $status = $lang->get('acppl_lbl_status_disabled');
          $buttons = 'uninstall|enable';
        }
        else
        {
          $color = '_red';
          $status = $lang->get('acppl_lbl_status_uninstalled');
          $buttons = 'install';
        }
        $uuid = md5($data['plugin name'] . $data['version'] . $filename);
        $desc = ( preg_match('/^[a-z0-9_]+$/', $data['description']) ) ? $lang->get($data['description']) : $data['description'];
        $desc = sanitize_html($desc);
        
        $additional = '';
        
        // filename
        $additional .= '<b>' . $lang->get('acppl_lbl_filename') . '</b> ' . "{$filename}<br />";
        
        // plugin's site
        $data['plugin uri'] = htmlspecialchars($data['plugin uri']);
        $additional .= '<b>' . $lang->get('acppl_lbl_plugin_site') . '</b> ' . "<a href=\"{$data['plugin uri']}\">{$data['plugin uri']}</a><br />";
        
        // author's site
        $data['author uri'] = htmlspecialchars($data['author uri']);
        $additional .= '<b>' . $lang->get('acppl_lbl_author_site') . '</b> ' . "<a href=\"{$data['author uri']}\">{$data['author uri']}</a><br />";
        
        // version
        $additional .= '<b>' . $lang->get('acppl_lbl_version') . '</b> ' . "{$data['version']}<br />";
        
        // installed version
        if ( $data['status'] & PLUGIN_OUTOFDATE )
        {
          $additional .= '<b>' . $lang->get('acppl_lbl_installed_version') . '</b> ' . "{$data['version installed']}<br />";
        }
        
        // build list of buttons
        $buttons_html = '';
        if ( !empty($buttons) )
        {
          $filename_js = addslashes($filename);
          $buttons = explode('|', $buttons);
          $colors = array(
              'install' => 'green',
              'disable' => 'blue',
              'enable' => 'blue',
              'upgrade' => 'green',
              'uninstall' => 'red',
              'reimport' => 'green'
            );
          foreach ( $buttons as $button )
          {
            $btnface = $lang->get("acppl_btn_$button");
            $buttons_html .= "<a href=\"#\" onclick=\"ajaxPluginAction('$button', '$filename_js', this); return false;\" class=\"abutton_{$colors[$button]} abutton\">$btnface</a>\n";
          }
        }
        
        echo "<tr>
                <td class=\"row{$rowid}$color\">
                  <div style=\"float: right;\">
                    <b>$status</b>
                  </div>
                  <div style=\"cursor: pointer;\" onclick=\"if ( !this.fx ) { load_component('jquery'); load_component('jquery-ui'); load_component('messagebox'); load_component('ajax'); this.fx = true; } $('#plugininfo_$uuid').toggle('blind', {}, 500);\">
                    $plugin_basics
                  </div>
                  <span class=\"menuclear\"></span>
                  <div id=\"plugininfo_$uuid\" style=\"display: none;\">
                    $desc
                    <div style=\"padding: 5px;\">
                      $additional
                      <div style=\"float: right; position: relative; top: -10px;\">
                        $buttons_html
                      </div>
                      <span class=\"menuclear\"></span>
                    </div>
                  </div>
                </td>
              </tr>";
      }
      ?>
    </table>
  </div>
  <?php
  // are there still old style plugin entries?
  $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "config WHERE config_name LIKE 'plugin_%';");
  if ( !$q )
    $db->_die();
  
  $count = $db->numrows();
  $db->free_result($q);
  
  if ( $count > 0 )
  {
    echo '<h3>' . $lang->get('acppl_msg_old_entries_title') . '</h3>';
    echo '<p>' . $lang->get('acppl_msg_old_entries_body') . '</p>';
    echo '<p><a class="abutton abutton_green" href="#" onclick="ajaxPluginAction(\'import\', \'\', false); return false;">' . $lang->get('acppl_btn_import_old') . '</a></p>';
  }
}
