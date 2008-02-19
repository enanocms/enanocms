<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.2 (Caoineag alpha 2)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

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
  
  $system_themes = array('admin', 'printable');
  
  // Obtain the list of themes (both available and already installed) and the styles available for each
  $dh = @opendir(ENANO_ROOT . '/themes');
  if ( !$dh )
    die('Couldn\'t open themes directory');
  $themes = array();
  while ( $dr = @readdir($dh) )
  {
    if ( $dr == '.' || $dr == '..' )
      continue;
    if ( !is_dir(ENANO_ROOT . "/themes/$dr") )
      continue;
    if ( !file_exists(ENANO_ROOT . "/themes/$dr/theme.cfg") || !is_dir(ENANO_ROOT . "/themes/$dr/css") )
      continue;
    $cdh = @opendir(ENANO_ROOT . "/themes/$dr/css");
    if ( !$cdh )
      continue;
    
    require(ENANO_ROOT . "/themes/$dr/theme.cfg");
    global $theme;
    
    $themes[$dr] = array(
        'css' => array(),
        'theme_name' => $theme['theme_name']
      );
    while ( $cdr = @readdir($cdh) )
    {
      if ( $cdr == '.' || $cdr == '..' )
        continue;
      if ( preg_match('/\.css$/i', $cdr) )
        $themes[$dr]['css'][] = substr($cdr, 0, -4);
    }
  }
  
  // Decide which themes are not installed
  $installable = array_flip(array_keys($themes));
  // FIXME: sanitize directory names or check with preg_match()
  $where_clause = 'theme_id = \'' . implode('\' OR theme_id = \'', array_flip($installable)) . '\'';
  $q = $db->sql_query('SELECT theme_id, theme_name, enabled FROM ' . table_prefix . "themes WHERE $where_clause;");
  if ( !$q )
    $db->_die();
  
  while ( $row = $db->fetchrow() )
  {
    $tid =& $row['theme_id'];
    unset($installable[$tid]);
    $themes[$tid]['theme_name'] = $row['theme_name'];
    $themes[$tid]['enabled'] = ( $row['enabled'] == 1 );
  }
  
  foreach ( $system_themes as $st )
  {
    unset($installable[$st]);
  }
  
  $installable = array_flip($installable);
  
  // AJAX code
  if ( $paths->getParam(0) === 'action.json' )
  {
    return ajaxServlet_Admin_ThemeManager($themes);
  }
  
  // List installed themes
  ?>
  <div style="float: right;">
    <a href="#" id="systheme_toggler" onclick="ajaxToggleSystemThemes(); return false;"><?php echo $lang->get('acptm_btn_system_themes_show'); ?></a>
  </div>
  <?php
  echo '<h3>' . $lang->get('acptm_heading_edit_themes') . '</h3>';
  echo '<div id="theme_list_edit">';
  foreach ( $themes as $theme_id => $theme_data )
  {
    if ( in_array($theme_id, $installable) )
      continue;
    if ( file_exists(ENANO_ROOT . "/themes/$theme_id/preview.png") )
    {
      $preview_path = scriptPath . "/themes/$theme_id/preview.png";
    }
    else
    {
      $preview_path = scriptPath . "/images/themepreview.png";
    }
    $d = ( @$theme_data['enabled'] ) ? '' : ' themebutton_theme_disabled';
    $st = ( in_array($theme_id, $system_themes) ) ? ' themebutton_theme_system' : '';
    echo '<div class="themebutton' . $st . '' . $d . '" id="themebtn_edit_' . $theme_id . '" style="background-image: url(' . $preview_path . ');">';
    if ( in_array($theme_id, $system_themes) )
    {
      echo   '<a class="tb-inner" href="#" onclick="return false;">
                ' . $lang->get('acptm_btn_theme_system') . '
                <span class="themename">' . htmlspecialchars($theme_data['theme_name']) . '</span>
              </a>';
    }
    else
    {
      echo   '<a class="tb-inner" href="#" onclick="ajaxEditTheme(\'' . $theme_id . '\'); return false;">
                ' . $lang->get('acptm_btn_theme_edit') . '
                <span class="themename">' . htmlspecialchars($theme_data['theme_name']) . '</span>
              </a>';
    }
    echo '</div>';
  }
  echo '</div>';
  echo '<span class="menuclear"></span>';
  
  if ( count($installable) > 0 )
  {
    echo '<h3>' . $lang->get('acptm_heading_install_themes') . '</h3>';
  
    echo '<div id="theme_list_install">';
    foreach ( $installable as $i => $theme_id )
    {
      if ( file_exists(ENANO_ROOT . "/themes/$theme_id/preview.png") )
      {
        $preview_path = scriptPath . "/themes/$theme_id/preview.png";
      }
      else
      {
        $preview_path = scriptPath . "/images/themepreview.png";
      }
      echo '<div class="themebutton" id="themebtn_install_' . $theme_id . '" enano:themename="' . htmlspecialchars($themes[$theme_id]['theme_name']) . '" style="background-image: url(' . $preview_path . ');">';
      echo   '<a class="tb-inner" href="#" onclick="ajaxInstallTheme(\'' . $theme_id . '\'); return false;">
                ' . $lang->get('acptm_btn_theme_install') . '
                <span class="themename">' . htmlspecialchars($themes[$theme_id]['theme_name']) . '</span>
              </a>';
      echo '</div>';
    }
    echo '</div>';
    echo '<span class="menuclear"></span>';
  }
}

function ajaxServlet_Admin_ThemeManager(&$themes)
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
  
  if ( !isset($_POST['r']) )
    return false;
  
  try
  {
    $request = enano_json_decode($_POST['r']);
  }
  catch ( Exception $e )
  {
    die('Exception in JSON parser, probably invalid input.');
  }
  
  if ( !isset($request['mode']) )
  {
    die('No mode specified in JSON request.');
  }
  
  switch ( $request['mode'] )
  {
    case 'fetch_theme':
      $theme_id = $db->escape($request['theme_id']);
      if ( empty($theme_id) )
        die('Invalid theme_id');
      
      $q = $db->sql_query("SELECT theme_id, theme_name, default_style, enabled, group_policy, group_list FROM " . table_prefix . "themes WHERE theme_id = '$theme_id';");
      if ( !$q )
        $db->die_json();
      
      if ( $db->numrows() < 1 )
        die('BUG: no theme with that theme_id installed.');
      
      $row = $db->fetchrow();
      $row['enabled'] = ( $row['enabled'] == 1 );
      $row['css'] = @$themes[$theme_id]['css'];
      $row['default_style'] = preg_replace('/\.css$/', '', $row['default_style']);
      $row['is_default'] = ( getConfig('theme_default') === $theme_id );
      $row['group_list'] = ( empty($row['group_list']) ) ? array() : enano_json_decode($row['group_list']);
      
      // Build a list of group names
      $row['group_names'] = array();
      foreach ( $row['group_list'] as $group_id )
      {
        $row['group_names'][$group_id] = '';
      }
      if ( count($row['group_names']) > 0 )
      {
        $idlist = 'group_id = ' . implode(' OR group_id = ', array_keys($row['group_names']));
        $q = $db->sql_query('SELECT group_id, group_name FROM ' . table_prefix . "groups WHERE $idlist;");
        if ( !$q )
          $db->die_json();
        while ( $gr = $db->fetchrow_num() )
        {
          list($group_id, $group_name) = $gr;
          $row['group_names'][$group_id] = $group_name;
        }
      }
      
      echo enano_json_encode($row);
      break;
  }
}

function page_Admin_ThemeManagerOld() 
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
    if(!$s) die('Error selecting enabled/disabled state value: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
    if(!$a) die('Error updating enabled/disabled state value: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
    if(!$s) die('Error selecting name value: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($s);
    $db->free_result();
    acp_start_form();
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
    if(!$s) die('Error updating name value: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
    else echo('<div class="info-box">Theme data updated.</div>');
  }
  elseif(isset($_POST['up'])) {
    // If there is only one theme or if the selected theme is already at the top, do nothing
    $q = 'SELECT theme_order FROM '.table_prefix.'themes ORDER BY theme_order;';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
    $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\'';
    $sn = $db->sql_query($q);
    if(!$sn) die('Error selecting order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($sn);
    if( /* check for only one theme... */ $db->numrows($s) < 2 || $r[0] == 1 /* ...and check if this theme is already at the top */ ) { echo('<div class="warning-box">This theme is already at the top of the list, or there is only one theme installed.</div>'); } else {
      // Get the order IDs of the selected theme and the theme before it
      $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\'' . $db->escape($_POST['theme_id']) . '\'';
      $s = $db->sql_query($q);
      if(!$s) die('Error selecting order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $r = $db->fetchrow_num($s);
      $r = $r[0];
      $rb = $r - 1;
      // Thank God for jEdit's rectangular selection and the ablity to edit multiple lines at the same time ;)
      $q = 'UPDATE '.table_prefix.'themes SET theme_order=0 WHERE theme_order='.$rb.'';      /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$rb.' WHERE theme_order='.$r.''; /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$r.' WHERE theme_order=0';       /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      echo('<div class="info-box">Theme moved up.</div>');
    }
    $db->free_result($s);
    $db->free_result($sn);
  }
  elseif(isset($_POST['down'])) {
    // If there is only one theme or if the selected theme is already at the top, do nothing
    $q = 'SELECT theme_order FROM '.table_prefix.'themes ORDER BY theme_order;';
    $s = $db->sql_query($q);
    if(!$s) die('Error selecting order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
    $r = $db->fetchrow_num($s);
    if( /* check for only one theme... */ $db->numrows($s) < 2 || $r[0] == $db->numrows($s) /* ...and check if this theme is already at the bottom */ ) { echo('<div class="warning-box">This theme is already at the bottom of the list, or there is only one theme installed.</div>'); } else {
      // Get the order IDs of the selected theme and the theme before it
      $q = 'SELECT theme_order FROM '.table_prefix.'themes WHERE theme_id=\''.$db->escape($_POST['theme_id']).'\'';
      $s = $db->sql_query($q);
      if(!$s) die('Error selecting order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $r = $db->fetchrow_num($s);
      $r = $r[0];
      $rb = $r + 1;
      // Thank God for jEdit's rectangular selection and the ablity to edit multiple lines at the same time ;)
      $q = 'UPDATE '.table_prefix.'themes SET theme_order=0 WHERE theme_order='.$rb.'';      /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$rb.' WHERE theme_order='.$r.''; /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      $q = 'UPDATE '.table_prefix.'themes SET theme_order='.$r.' WHERE theme_order=0';       /* Check for errors... <sigh> */ $s = $db->sql_query($q); if(!$s) die('Error updating order information: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
      echo('<div class="info-box">Theme moved down.</div>');
    }
  }
  else if(isset($_POST['uninstall'])) 
  {
    $q = 'SELECT * FROM '.table_prefix.'themes;';
    $s = $db->sql_query($q);
    if ( !$s )
    {
      die('Error getting theme count: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
          die('Error deleting theme data: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
    if(!$s) die('Error getting theme count: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
        if(!$s) die('Error inserting theme data: '.$db->get_error().'<br /><u>SQL:</u><br />'.$q);
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
        if(!$s) die('Error selecting theme data: '.$db->get_error().'<br /><u>Attempted SQL:</u><br />'.$q);
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
        if(!$s) die('Error selecting list of currently installed themes: '.$db->get_error().'<br /><u>Attempted SQL:</u><br />'.$q);
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
