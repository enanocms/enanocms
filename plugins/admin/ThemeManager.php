<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function page_Admin_ThemeManager($force_no_json = false)
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
	
	$system_themes =& $template->system_themes;
	
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
	if ( $paths->getParam(0) === 'action.json' && !$force_no_json )
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
	global $cache;
	
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
			$q = $db->sql_query('SELECT group_id, group_name FROM ' . table_prefix . 'groups;');
			if ( !$q )
				$db->die_json();
			while ( $gr = $db->fetchrow() )
			{
				$row['group_names'][ intval($gr['group_id']) ] = $gr['group_name'];
			}
			$db->free_result();
			
			// Build a list of usernames
			$row['usernames'] = array();
			foreach ( $row['group_list'] as $el )
			{
				if ( !preg_match('/^u:([0-9]+)$/', $el, $match) )
					continue;
				$uid =& $match[1];
				$q = $db->sql_query('SELECT username FROM ' . table_prefix . "users WHERE user_id = $uid;");
				if ( !$q )
					$db->die_json();
				if ( $db->numrows() < 1 )
				{
					$db->free_result();
					continue;
				}
				list($username) = $db->fetchrow_num();
				$row['usernames'][$uid] = $username;
				$db->free_result();
			}
			
			echo enano_json_encode($row);
			break;
		case 'uid_lookup':
			$username = @$request['username'];
			if ( empty($username) )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => $lang->get('acptm_err_invalid_username')
					)));
			}
			$username = $db->escape(strtolower($username));
			$q = $db->sql_query('SELECT user_id, username FROM ' . table_prefix . "users WHERE " . ENANO_SQLFUNC_LOWERCASE . "(username) = '$username';");
			if ( !$q )
				$db->die_json();
			
			if ( $db->numrows() < 1 )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => $lang->get('acptm_err_username_not_found')
					)));
			}
			
			list($uid, $username_real) = $db->fetchrow_num();
			$db->free_result();
			
			echo enano_json_encode(array(
					'uid' => $uid,
					'username' => $username_real
				));
			break;
		case 'save_theme':
			if ( !isset($request['theme_data']) )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => 'No theme data in request'
					)));
			}
			$theme_data =& $request['theme_data'];
			// Perform integrity check on theme data
			$chk_theme_exists = isset($themes[@$theme_data['theme_id']]);
			$theme_data['theme_name'] = trim(@$theme_data['theme_name']);
			$chk_name_good = !empty($theme_data['theme_name']);
			$chk_policy_good = in_array(@$theme_data['group_policy'], array('allow_all', 'whitelist', 'blacklist'));
			$chk_grouplist_good = true;
			foreach ( $theme_data['group_list'] as $acl_entry )
			{
				if ( !preg_match('/^(u|g):[0-9]+$/', $acl_entry) )
				{
					$chk_grouplist_good = false;
					break;
				}
			}
			$chk_style_good = @in_array(@$theme_data['default_style'], @$themes[@$theme_data['theme_id']]['css']);
			if ( !$chk_theme_exists || !$chk_name_good || !$chk_policy_good || !$chk_grouplist_good || !$chk_style_good )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => $lang->get('acptm_err_save_validation_failed')
					)));
			}
			
			$enable = ( $theme_data['enabled'] ) ? '1' : '0';
			$theme_default = getConfig('theme_default');
			$warn_default = ( $theme_default === $theme_data['theme_id'] || $theme_data['make_default'] ) ?
												' ' . $lang->get('acptm_warn_access_with_default') . ' ' :
												' ';
			if ( $enable == 0 && ( $theme_default === $theme_data['theme_id'] || $theme_data['make_default'] ) )
			{
				$enable = '1';
				$warn_default .= '<b>' . $lang->get('acptm_warn_cant_disable_default') . '</b>';
			}
			
			// We're good. Update the theme...
			$q = $db->sql_query('UPDATE ' . table_prefix . 'themes SET
 															theme_name = \'' . $db->escape($theme_data['theme_name']) . '\',
 															default_style = \'' . $db->escape($theme_data['default_style']) . '\',
 															group_list = \'' . $db->escape(enano_json_encode($theme_data['group_list'])) . '\',
 															group_policy = \'' . $db->escape($theme_data['group_policy']) . '\',
 															enabled = ' . $enable . '
 														WHERE theme_id = \'' . $db->escape($theme_data['theme_id']) . '\';');
			if ( !$q )
				$db->die_json();
			
			if ( $theme_data['make_default'] )
			{
				setConfig('theme_default', $theme_data['theme_id']);
			}
			
			$cache->purge('themes');
			
			echo '<div class="info-box"><b>' . $lang->get('acptm_msg_save_success') . '</b>' . $warn_default . '</div>';
			
			page_Admin_ThemeManager(true);
			break;
		case 'install':
			$theme_id =& $request['theme_id'];
			if ( !isset($themes[$theme_id]) )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => 'Theme was deleted from themes/ directory or couldn\'t read theme metadata from filesystem'
					)));
			}
			if ( !isset($themes[$theme_id]['css'][0]) )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => 'Theme doesn\'t have any files in css/, thus it can\'t be installed. (translators: l10n?)'
					)));
			}
			// build dataset
			$theme_name = $db->escape($themes[$theme_id]['theme_name']);
			$default_style = $db->escape($themes[$theme_id]['css'][0]);
			$theme_id = $db->escape($theme_id);
			
			// insert it
			$q = $db->sql_query('INSERT INTO ' . table_prefix . "themes(theme_id, theme_name, default_style, enabled, group_list, group_policy)\n"
												. "  VALUES( '$theme_id', '$theme_name', '$default_style', 1, '[]', 'allow_all' );");
			if ( !$q )
				$db->die_json();
			
			$cache->purge('themes');
			
			// The response isn't processed unless it's in JSON.
			echo 'Roger that, over and out.';
			
			break;
		case 'uninstall':
			$theme_id =& $request['theme_id'];
			$theme_default = getConfig('theme_default');
			
			// Validation
			if ( !isset($themes[$theme_id]) )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => 'Theme was deleted from themes/ directory or couldn\'t read theme metadata from filesystem'
					)));
			}
			
			if ( $theme_id == $theme_default )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => $lang->get('acptm_err_uninstalling_default')
					)));
			}
			
			if ( $theme_id == 'oxygen' )
			{
				die(enano_json_encode(array(
						'mode' => 'error',
						'error' => $lang->get('acptm_err_uninstalling_oxygen')
					)));
			}
			
			$theme_id = $db->escape($theme_id);
			
			$q = $db->sql_query('DELETE FROM ' . table_prefix . "themes WHERE theme_id = '$theme_id';");
			if ( !$q )
				$db->die_json();
			
			$cache->purge('themes');
			
			// Change all the users that were on that theme to the default
			$default_style = $template->named_theme_list[$theme_default]['default_style'];
			$default_style = preg_replace('/\.css$/', '', $default_style);
			
			$theme_default = $db->escape($theme_default);
			$default_style = $db->escape($default_style);
			
			$q = $db->sql_query('UPDATE ' . table_prefix . "users SET theme = '$theme_default', style = '$default_style' WHERE theme = '$theme_id';");
			if ( !$q )
				$db->die_json();
			
			echo '<div class="info-box">' . $lang->get('acptm_msg_uninstall_success') . '</div>';
			
			page_Admin_ThemeManager(true);
			break;
	}
}

