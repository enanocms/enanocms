<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.3 (Caoineag alpha 3)
 * Copyright (C) 2006-2007 Dan Fuhry
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
 /**!blocktype( param1 = "value1" [ param2 = "value2" ... ] )**
 
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
   eng: {
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
 /**!upgrade from = "0.1-alpha1" to = "0.1-alpha2" **
 
 **!* /
 </code>
 * Remember that upgrades will always be done incrementally, so if the user is upgrading 0.1-alpha2 to 0.1, Enano's plugin
 * engine will run the 0.1-alpha2 to 0.1-beta1 upgrader, then the 0.1-beta1 to 0.1 upgrader, going by the versions listed in
 * the example metadata block above.
 * 
 * All of this information is effective as of Enano 1.1.4.
 */

// Plugin manager "2.0"

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
  
  // Are we processing an AJAX request from the smartform?
  if ( $paths->getParam(0) == 'action.json' )
  {
    // Set to application/json to discourage advertisement scripts
    header('Content-Type: application/json');
    
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
  
  //
  // Not a JSON request, output normal HTML interface
  //
  
  // Scan all plugins
  $plugin_list = array();
  
  if ( $dirh = @opendir( ENANO_ROOT . '/plugins' ) )
  {
    while ( $dh = @readdir($dirh) )
    {
      if ( !preg_match('/\.php$/i', $dh) )
        continue;
      $fullpath = ENANO_ROOT . "/plugins/$dh";
      // it's a PHP file, attempt to read metadata
      // pass 1: try to read a !info block
      $blockdata = $plugins->parse_plugin_blocks($fullpath, 'info');
      if ( empty($blockdata) )
      {
        // no !info block, check for old header
        $fh = @fopen($fullpath, 'r');
        if ( !$fh )
          // can't read, bail out
          continue;
        $plugin_data = array();
        for ( $i = 0; $i < 8; $i++ )
        {
          $plugin_data[] = @fgets($fh, 8096);
        }
        // close our file handle
        fclose($fh);
        // is the header correct?
        if ( trim($plugin_data[0]) != '<?php' || trim($plugin_data[1]) != '/*' )
        {
          // nope. get out.
          continue;
        }
        // parse all the variables
        $plugin_meta = array();
        for ( $i = 2; $i <= 7; $i++ )
        {
          if ( !preg_match('/^([A-z0-9 ]+?): (.+?)$/', trim($plugin_data[$i]), $match) )
            continue 2;
          $plugin_meta[ strtolower($match[1]) ] = $match[2];
        }
      }
      else
      {
        // parse JSON block
        $plugin_data =& $blockdata[0]['value'];
        $plugin_data = enano_clean_json(enano_trim_json($plugin_data));
        try
        {
          $plugin_meta_uc = enano_json_decode($plugin_data);
        }
        catch ( Exception $e )
        {
          continue;
        }
        // convert all the keys to lowercase
        $plugin_meta = array();
        foreach ( $plugin_meta_uc as $key => $value )
        {
          $plugin_meta[ strtolower($key) ] = $value;
        }
      }
      if ( !isset($plugin_meta) || !is_array(@$plugin_meta) )
      {
        // parsing didn't work.
        continue;
      }
      // check for required keys
      $required_keys = array('plugin name', 'plugin uri', 'description', 'author', 'version', 'author uri');
      foreach ( $required_keys as $key )
      {
        if ( !isset($plugin_meta[$key]) )
          // not set, skip this plugin
          continue 2;
      }
      // decide if it's a system plugin
      $plugin_meta['system plugin'] = in_array($dh, $plugins->system_plugins);
      // reset installed variable
      $plugin_meta['installed'] = false;
      $plugin_meta['status'] = 0;
      // all checks passed
      $plugin_list[$dh] = $plugin_meta;
    }
  }
  // gather info about installed plugins
  $q = $db->sql_query('SELECT plugin_filename, plugin_version, plugin_flags FROM ' . table_prefix . 'plugins;');
  if ( !$q )
    $db->_die();
  while ( $row = $db->fetchrow() )
  {
    if ( !isset($plugin_list[ $row['plugin_filename'] ]) )
    {
      // missing plugin file, don't report (for now)
      continue;
    }
    $filename =& $row['plugin_filename'];
    $plugin_list[$filename]['installed'] = true;
    $plugin_list[$filename]['status'] = PLUGIN_INSTALLED;
    if ( $row['plugin_version'] != $plugin_list[$filename]['version'] )
    {
      $plugin_list[$filename]['status'] |= PLUGIN_OUTOFDATE;
      $plugin_list[$filename]['version installed'] = $row['plugin_version'];
    }
    if ( $row['plugin_flags'] & PLUGIN_DISABLED )
    {
      $plugin_list[$filename]['status'] |= PLUGIN_DISABLED;
    }
  }
  $db->free_result();
  
  // sort it all out by filename
  ksort($plugin_list);
  
  // start printing things out
  acp_start_form();
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
          $buttons = 'uninstall|disable';
        }
        else if ( $data['installed'] && $data['status'] & PLUGIN_OUTOFDATE )
        {
          $color = '_red';
          $status = $lang->get('acppl_lbl_status_need_upgrade');
          $buttons = 'uninstall|update';
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
              'uninstall' => 'red'
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
                  <div style=\"cursor: pointer;\" onclick=\"if ( !this.fx ) this.fx = new Spry.Effect.Blind('plugininfo_$uuid', { duration: 500, from: '0%', to: '100%', toggle: true }); this.fx.start();\"
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
  echo '</form>';
}
