<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3 (Dyrad)
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
    if ( strpos($action, ';') )
    {
      $parms = substr($action, strpos($action, ';') + 1);
      $action = substr($action, 0, strpos($action, ';'));
      preg_match_all('/([a-z0-9_]+)=(.+?)(;|$)/', $parms, $matches);
      $parms = array();
      foreach ( $matches[0] as $i => $_ )
      {
        $parms[$matches[1][$i]] = $matches[2][$i];
      }
    }
    switch ( $action )
    {
      case 'edit_language':
        break;
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
            foreach ( array('core', 'admin', 'tools', 'user') as $file )
            {
              $lang_local->import(ENANO_ROOT . "/language/{$lang_data['dir']}/$file.json");
            }
            unset($lang_local);
            
            echo '<div class="info-box">' . $lang->get('acplm_msg_lang_install_success', array('lang_name' => htmlspecialchars($lang_data['name_eng']))) . '</div>';
          }
        }
        break;
    }
  }
  
  // $lang_list is fetched by the posthandler sometimes
  if ( !isset($lang_list) )
  {
    // Build a list of languages in the languages/ directory, then
    // eliminate the ones that are already installed.
    $lang_list = list_available_languages();
  }
  
  // Select current languages
  $q = $db->sql_query('SELECT lang_code FROM ' . table_prefix . "language;");
  if ( !$q )
    $db->_die();
  
  while ( $row = $db->fetchrow() )
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
    echo '<form action="'.makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module']).'" method="post">';
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
    echo '</form>';        
  }
}

