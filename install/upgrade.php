<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * upgrade.php - Upgrade interface
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

define('IN_ENANO', 1);

// The list of versions in THIS AND PREVIOUS branches, in chronological order.
$enano_versions = array();
$enano_versions['1.0'] = array('1.0', '1.0.1', '1.0.2b1', '1.0.2', '1.0.3', '1.0.4', '1.0.5', '1.0.6', '1.0.6pl1');
$enano_versions['1.1'] = array('1.1.1', '1.1.2', '1.1.3', '1.1.4', '1.1.5', '1.1.6', '1.1.7', '1.1.8');

define('BANSHEE_LATEST_DBREV', 1061);

// If true, this will do a full langimport instead of only adding new strings.
// Will probably be left on, but some change probably needs to be made to mark
// strings as customized in the DB.
$do_langimport = false;

// Turn on every imaginable API hack to make common load on older databases
define('IN_ENANO_UPGRADE', 1);
define('IN_ENANO_MIGRATION', 1);
define('ENANO_ALLOW_LOAD_NOLANG', 1);
@ini_set('display_errors', 'on');

// Load installer files
require_once('../includes/sql_parse.php');
require_once('includes/common.php');
require_once('includes/libenanoinstall.php');

// when the installer's common is loaded, it runs chdir() to the ENANO_ROOT, thus making this Enano's common.php
// PHP5 notice removed in 1.1.4 since the existing common is loaded and that loads lang and json2, which will
// give syntax errors on PHP4. So much for that. The installer will warn about this anyway.
require_once('includes/common.php');
@ini_set('display_errors', 'on');

// do langimport if below 1.1.7
$versions_flipped = array_flip($enano_versions['1.1']);
if ( !isset($versions_flipped[ enano_version() ]) || $versions_flipped[ enano_version() ] < $versions_flipped['1.1.7'] )
{
	$do_langimport = true;
}

if ( in_array(enano_version(), array('1.1.1', '1.1.2', '1.1.3', '1.1.4', '1.1.5')) || substr(enano_version(), 0, 3) == '1.0' )
	define('ENANO_UPGRADE_USE_AES_PASSWORDS', 1);

// init languages
$lang_id_list = array_keys($languages);
$lang_id = $lang_id_list[0];
$language_dir = $languages[$lang_id]['dir'];

// load the language file
$lang = new Language($lang_id);
$lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/install.json');
$lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/user.json');

$ui = new Enano_Installer_UI($lang->get('upgrade_system_title'), false);

$stg_welcome = $ui->add_stage($lang->get('upgrade_stg_welcome'), true);
$stg_login   = $ui->add_stage($lang->get('upgrade_stg_login'),  true);
$stg_confirm = $ui->add_stage($lang->get('upgrade_stg_confirm'), true);
$stg_upgrade = $ui->add_stage($lang->get('upgrade_stg_upgrade'), true);
$stg_finish  = $ui->add_stage($lang->get('upgrade_stg_finish'), true);

// Version check
if ( getConfig('db_version') === $db_version && !preg_match('/^upg-/', getConfig('enano_version')) )
{
	$ui->show_header();
	$link_home = makeUrl(get_main_page(), false, true);
	echo '<h3>' . $lang->get('upgrade_err_current_title') . '</h3>' .
 			'<p>' . $lang->get('upgrade_err_current_body', array('mainpage_link' => $link_home)) . '</p>' .
 			'<p>' . $lang->get('upgrade_err_current_body_para2', array('mainpage_link' => $link_home)) . '</p>';
	$ui->show_footer();
	exit();
}

// Start session manager
$session->start();

// Welcome page
if ( !isset($_GET['stage']) )
{
	$ui->show_header();
	
	if ( preg_match('/1\.0/', enano_version()) )
	{
		// Migrating from 1.0.x
		echo '<h3>' . $lang->get('upgrade_welcome_banshee_heading', array('enano_version' => installer_enano_version())) . '</h3>';
		echo '<p>' . $lang->get('upgrade_welcome_banshee_para1') . '</p>';
		echo '<p>' . $lang->get('upgrade_welcome_banshee_para2') . '</p>';
	}
	else
	{
		// Upgrading from 1.1.x/1.2.x
		echo '<h3>' . $lang->get('upgrade_welcome_caoineag_heading', array('enano_version' => installer_enano_version())) . '</h3>';
		echo '<p>' . $lang->get('upgrade_welcome_caoineag_para1') . '</p>';
	}
	
	echo '<div style="font-size: x-large; text-align: center; margin: 20px 0;">';
	echo '<a class="abutton" href="' . $session->append_sid('upgrade.php?stage=confirm') . '" style="text-decoration: none;">' . $lang->get('upgrade_welcome_btn_continue') . ' &raquo;</a>';
	echo '</div>';
	
	$ui->show_footer();
	exit;
}

if ( !$session->user_logged_in || ( $session->user_logged_in && $session->auth_level < USER_LEVEL_ADMIN ) )
{
	// if we're not logged in, destroy any existing session keys in the browser
	@setcookie('sid', '', time() - 86400);
	
	$ui->set_visible_stage($stg_login);
	if ( isset($_POST['do_login']) )
	{
		if ( !$session->user_logged_in )
		{
			$result = $session->login_without_crypto($_POST['username'], $_POST['password'], false, USER_LEVEL_MEMBER);
		}
		if ( !isset($result) || ( isset($result) && $result['success']) )
		{
			$result = $session->login_without_crypto($_POST['username'], $_POST['password'], false, USER_LEVEL_ADMIN);
			if ( $result['success'] )
			{
				header('HTTP/1.1 302 Some kind of redirect with implied no content');
				header('Location: ' . scriptPath . '/install/' . $session->append_sid('upgrade.php?stage=confirm'));
				exit();
			}
		}
	}
	
	$ui->show_header();
	
	?>
	<h3><?php echo $lang->get('upgrade_login_msg_auth_needed_title'); ?></h3>
	<?php
	
	echo '<form action="upgrade.php?stage=login" method="post">';
	
	if ( isset($result) )
	{
		echo '<b>' . $lang->get('upgrade_login_err_failed', array('error_code' => $result['error'])) . '</b>';
	}
	
	?>
	<p><?php
	if ( $session->user_logged_in )
	{
		echo $lang->get('upgrade_login_msg_auth_needed_body_level2');
	}
	else
	{
		echo $lang->get('upgrade_login_msg_auth_needed_body_level1');
	}
	?></p>
	<p>
	<?php echo $lang->get('upgrade_login_msg_local_auth'); ?>
	</p>
	<table border="0" cellspacing="0" cellpadding="5" style="margin: 0 auto;">
	<tr>
		<td><?php echo $lang->get('user_login_field_username'); ?>:</td>
		<td><input type="text" name="username" tabindex="1" /></td>
	</tr>
	<tr>
		<td><?php echo $lang->get('user_login_field_password'); ?>:</td>
		<td><input type="password" name="password" tabindex="2" /></td>
	</tr>
	<tr>
		<td colspan="2" style="text-align: center;">
			<input type="submit" name="do_login" value="<?php echo $lang->get('upgrade_login_btn_login'); ?>" tabindex="3" />
		</td>
	</tr>
	</table>
	<?php
	
	echo '</form>';
	
	$ui->show_footer();
	exit();
}

if ( isset($_GET['stage']) && @$_GET['stage'] == 'pimpmyenano' )
{
	$ui->set_visible_stage($stg_upgrade);
}
else if ( isset($_GET['stage']) && @$_GET['stage'] == 'postpimp' )
{
	$ui->set_visible_stage($stg_finish);
}
else
{
	$ui->set_visible_stage($stg_confirm);
}

// The real migration code
$ui->show_header();

if ( isset($_GET['stage']) && @$_GET['stage'] == 'pimpmyenano' )
{
	/*
 	HOW DOES ENANO'S UPGRADER WORK?
 	
 	Versions of Enano are organized into branches and then specific versions by
 	version number. The upgrader works by using a list of known version numbers
 	and then systematically executing upgrade schemas for each version.
 	
 	When the user requests an upgrade, the first thing performed is a migration
 	check, which verifies that they are within the right branch. If they are not
 	within the right branch the upgrade framework will load a migration script
 	which will define a function named MIGRATE(). Performing more than one
 	migration in one pass will probably never be supported. How that works for
 	UX in 1.3.x/1.4.x I know not yet.
 	
 	After performing any necessary branch migrations, the framework will perform
 	any upgrades within the target branch, which is the first two parts
 	(delimited by periods) of the installer's version number defined in the
 	installer's common.php.
 	
 	enano_perform_upgrade() will only do upgrades. Not migrations. The two as
 	illustrated within this installer are very different.
 	*/
	
	// Do we need to run the migration first?
	list($major_version, $minor_version) = explode('.', preg_replace('/^upg-/', '', enano_version()));
	$current_branch = "$major_version.$minor_version";
	
	list($major_version, $minor_version) = explode('.', installer_enano_version());
	$target_branch = "$major_version.$minor_version";
	
	if ( $target_branch != $current_branch )
	{
		// First upgrade to the latest revision of the current branch
		enano_perform_upgrade(BANSHEE_LATEST_DBREV);
		// Branch migration could be tricky and is often highly specific between
		// major branches, so just include a custom migration script.
		require(ENANO_ROOT . "/install/schemas/upgrade/migration/{$current_branch}-{$target_branch}.php");
		$result = MIGRATE();
		if ( !$result )
		{
			echo 'Migration failed, there should be an error message above.';
			$ui->show_footer();
			exit;
		}
		setConfig('db_version', BANSHEE_LATEST_DBREV + 1);
	}
	
	// Do the actual upgrade
	enano_perform_upgrade($db_version);
	
	// Mark as upgrade-in-progress
	setConfig('enano_version', 'upg-' . installer_enano_version());
	
	?>
	<h3>
		<?php echo $lang->get('upgrade_msg_schema_complete_title'); ?>
	</h3>
	<p>
		<?php echo $lang->get('upgrade_msg_schema_complete_body'); ?>
	</p>
	<form action="upgrade.php" method="get" style="text-align: center;">
		<input type="hidden" name="auth" value="<?php echo $session->sid_super; ?>" />
		<p style="text-align: center;">
			<button name="stage" value="postpimp" class="submit">
				<?php echo $lang->get('upgrade_btn_continue'); ?>
			</button>
		</p>
	</form>
	<?php
}
else if ( isset($_GET['stage']) && @$_GET['stage'] == 'postpimp' )
{
	// verify version
	if ( enano_version() != 'upg-' . installer_enano_version() )
	{
		echo '<p>' . $lang->get('upgrade_err_post_not_available') . '</p>';
		$ui->show_footer();
		$db->close();
		exit();
	}
	
	function stg_load_files()
	{
		if ( !@include( ENANO_ROOT . "/install/includes/payload.php" ) )
			return false;
		
		return true;
	}
	
	echo '<h3>' . $lang->get('upgrade_post_status_title') . '</h3>';
	echo '<p>' . $lang->get('upgrade_post_status_body') . '</p>';
	
	start_install_table();
	run_installer_stage('load', $lang->get('install_stg_load_title'), 'stg_load_files', $lang->get('install_stg_load_body'), false);
	run_installer_stage('importlang', $lang->get('install_stg_importlang_title'), 'stg_lang_import', $lang->get('install_stg_importlang_body'));
	run_installer_stage('flushcache', $lang->get('upgrade_stg_flushcache_title'), 'stg_flush_cache', $lang->get('upgrade_stg_flushcache_body'));
	run_installer_stage('setversion', $lang->get('upgrade_stg_setversion_title'), 'stg_set_version', $lang->get('upgrade_stg_setversion_body'));
	close_install_table();
	
	// demote privileges
	$session->logout(USER_LEVEL_ADMIN);
	
	$link_home = makeUrl(get_main_page(), false, true);
	echo '<h3>' . $lang->get('upgrade_post_status_finish_title') . '</h3>';
	echo '<p>' . $lang->get('upgrade_post_status_finish_body', array('mainpage_link' => $link_home)) . '</p>';
}
else
{
	?>
	<h3><?php echo $lang->get('upgrade_confirm_title'); ?></h3>
	<p><?php echo $lang->get('upgrade_confirm_body', array('enano_version' => installer_enano_version(), 'db_version' => $db_version)); ?></p>
	<ul>
		<li><?php echo $lang->get('upgrade_confirm_objective_backup_fs', array('dir' => ENANO_ROOT)); ?></li>
		<li><?php echo $lang->get('upgrade_confirm_objective_backup_db', array('dbname' => $dbname)); ?></li>
	</ul>
	<?php
	if ( $do_langimport && !preg_match('/1\.0/', enano_version()) ):
	?>
	<div class="warning-box" style="margin: 10px 0;">
		<?php echo $lang->get('upgrade_confirm_warning_langimport'); ?>
	</div>
	<?php
	endif;
	?>
	<form method="get" action="upgrade.php" style="text-align: center;">
		<input type="hidden" name="auth" value="<?php echo $session->sid_super; ?>" />
		<button name="stage" value="pimpmyenano" class="submit" style="padding-bottom: 8px;">
		<img src="images/icons/pimp.png" style="position: relative; top: 6px;" />
			<?php echo $lang->get('upgrade_confirm_btn_upgrade'); ?>
		</button>
	</form>
	<?php
}

$ui->show_footer();

