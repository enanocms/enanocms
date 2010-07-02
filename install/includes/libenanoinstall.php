<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * libenanoinstall.php - Installation payload backend
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

$neutral_color = 'C';

function run_installer_stage($stage_id, $stage_name, $function, $failure_explanation, $allow_skip = true)
{
	static $resumed = false;
	static $resume_stack = array();
	
	if ( empty($resume_stack) && isset($_POST['resume_stack']) && preg_match('/[a-z_]+((\|[a-z_]+)+)/', $_POST['resume_stack']) )
	{
		$resume_stack = explode('|', $_POST['resume_stack']);
	}
	
	$already_run = false;
	if ( in_array($stage_id, $resume_stack) )
	{
		$already_run = true;
	}
	
	if ( !$resumed )
	{
		if ( !isset($_GET['sub']) )
			$resumed = true;
		if ( isset($_GET['sub']) && $_GET['sub'] == $stage_id )
		{
			$resumed = true;
		}
	}
	if ( !$resumed && $allow_skip )
	{
		echo_stage_success($stage_id, $stage_name);
		return false;
	}
	if ( !function_exists($function) )
		die('libenanoinstall: CRITICAL: function "' . $function . '" for ' . $stage_id . ' doesn\'t exist');
	$result = call_user_func($function, false, $already_run);
	if ( $result )
	{
		echo_stage_success($stage_id, $stage_name);
		$resume_stack[] = $stage_id;
		return true;
	}
	else
	{
		echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack);
		return false;
	}
}

function start_install_table()
{
	echo '<table border="0" cellspacing="0" cellpadding="0" style="margin-top: 10px;">' . "\n";
}

function close_install_table()
{
	echo '</table>' . "\n\n";
	flush();
}

function echo_stage_success($stage_id, $stage_name)
{
	global $neutral_color;
	$neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
	echo '<tr><td style="width: 500px; background-color: #' . "{$neutral_color}{$neutral_color}FF{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Done" src="../images/check.png" /></td></tr>' . "\n";
	flush();
}

function echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack)
{
	global $neutral_color;
	global $lang;
	
	$neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
	echo '<tr><td style="width: 500px; background-color: #' . "FF{$neutral_color}{$neutral_color}{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Failed" src="../images/checkbad.png" /></td></tr>' . "\n";
	flush();
	close_install_table();
	$post_data = '';
	$mysql_error = mysql_error();
	$file = ( defined('IN_ENANO_UPGRADE') ) ? 'upgrade.php' : 'install.php';
	foreach ( $_POST as $key => $value )
	{
		// FIXME: These should really also be sanitized for double quotes
		$value = htmlspecialchars($value);
		$key = htmlspecialchars($key);
		$post_data .= "          <input type=\"hidden\" name=\"$key\" value=\"$value\" />\n";
	}
	if ( $stage_id == 'renameconfig' )
		echo '<p>' . $failure_explanation . '</p>';
	else
		echo '<form action="' . $file . '?stage=install&amp;sub=' . $stage_id . '" method="post">
						' . $post_data . '
						<input type="hidden" name="resume_stack" value="' . htmlspecialchars(implode('|', $resume_stack)) . '" />
						<h3>' . $lang->get('meta_msg_err_stagefailed_title') . '</h3>
 						<p>' . $failure_explanation . '</p>
 						' . ( !empty($mysql_error) ? "<p>" . $lang->get('meta_msg_err_stagefailed_mysqlerror') . " $mysql_error</p>" : '' ) . '
 						<p>' . $lang->get('meta_msg_err_stagefailed_body') . '</p>
 						<p style="text-align: center;"><input type="submit" value="' . $lang->get('meta_btn_retry_installation') . '" /></p>
					</form>';
	global $ui;
	$ui->show_footer();
	exit;
}

function enano_perform_upgrade($target_rev)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	// Import version info
	global $enano_versions;
	// Import UI functions
	global $ui;
	// This is needed for upgrade abstraction
	global $dbdriver;
	
	// see if we're actually supposed to be in post-upgrade
	if ( getConfig('enano_version') == 'upg-' . installer_enano_version() )
	{
		// yep, fall out here to avoid errors
		return true;
	}
	
	//
	// Main upgrade stage
	//
	
	// Calculate which scripts to run
	$versions_avail = array();
	
	$dir = ENANO_ROOT . "/install/schemas/upgrade/$dbdriver/";
	if ( $dh = @opendir($dir) )
	{
		while ( $di = @readdir($dh) )
		{
			if ( preg_match('/^[0-9]+\.sql$/', $di) )
				$versions_avail[] = intval($di);
		}
	}
	else
	{
		return false;
	}
	
	// sort version list numerically
	asort($versions_avail);
	$versions_avail = array_values($versions_avail);
	
	$last_rev = $versions_avail[ count($versions_avail) - 1 ];
	
	$current_rev = getConfig('db_version', 0);
	
	if ( $last_rev <= $current_rev )
		return true;
	
	foreach ( $versions_avail as $i => $ver )
	{
		if ( $ver > $current_rev )
		{
			$upg_queue = array_slice($versions_avail, $i);
			break;
		}
	}
	
	// cap to target rev
	foreach ( $upg_queue as $i => $ver )
	{
		if ( $ver > $target_rev )
			unset($upg_queue[$i]);
	}
	
	// Perform upgrade
	foreach ( $upg_queue as $version )
	{
		$file = "{$dir}$version.sql";
		
		try
		{
			$parser = new SQL_Parser($file);
		}
		catch(Exception $e)
		{
			die("<pre>$e</pre>");
		}
		
		$parser->assign_vars(array(
			'TABLE_PREFIX' => table_prefix
		));
	
		$sql_list = $parser->parse();
		// Check for empty schema file
		if ( $sql_list[0] === ';' && count($sql_list) == 1 )
		{
			// It's empty, report success for this version
			// See below for explanation of why setConfig() is called here
			setConfig('db_version', $version);
			continue;
		}
		
		foreach ( $sql_list as $sql )
		{
			// check for '@' operator on query
			if ( substr($sql, 0, 1) == '@' )
			{
				// Yes - perform query but don't check for errors
				$db->sql_query($sql);
			}
			else
			{
				// Perform as normal
				if ( !$db->sql_query($sql) )
					$db->_die();
			}
		}
		
		// Is there an additional script (logic) to be run after the schema?
		$postscript = ENANO_ROOT . "/install/schemas/upgrade/$version.php";
		if ( file_exists($postscript) )
			@include($postscript);
		
		// The advantage of calling setConfig on the system version here?
		// Simple. If the upgrade fails, it will pick up from the last
		// version, not try to start again from the beginning. This will
		// still cause errors in most cases though. Eventually we probably
		// need some sort of query-numbering system that tracks in-progress
		// upgrades.
		
		setConfig('db_version', $version);
	}
}

