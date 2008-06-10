<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
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
$enano_versions['1.0'] = array('1.0', '1.0.1', '1.0.2b1', '1.0.2', '1.0.3', '1.0.4');
$enano_versions['1.1'] = array('1.1.1', '1.1.2', '1.1.3', '1.1.4');

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

$ui = new Enano_Installer_UI('Enano upgrader', false);

$stg_welcome = $ui->add_stage('Welcome', true);
$stg_confirm = $ui->add_stage('Confirmation', true);
$stg_upgrade = $ui->add_stage('Perform upgrade', true);
$stg_finish  = $ui->add_stage('Finish', true);

// init languages
$lang_id_list = array_keys($languages);
$lang_id = $lang_id_list[0];
$language_dir = $languages[$lang_id]['dir'];

// load the language file
$lang = new Language($lang_id);
$lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/install.json');
$lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/user.json');

// Version check
if ( enano_version() == installer_enano_version() )
{
  $ui->show_header();
  echo '<h3>Already upgraded</h3>' . '<p>You don\'t need to migrate, you\'re already on <del>crack</del> the 1.1 platform.</p>';
  $ui->show_footer();
  exit();
}

// Start session manager
$session->start();
if ( !$session->user_logged_in || ( $session->user_logged_in && $session->auth_level < USER_LEVEL_ADMIN ) )
{
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
        header('Location: ' . scriptPath . '/install/' . $session->append_sid('upgrade.php'));
        exit();
      }
    }
  }
  
  $ui->show_header();
  
  ?>
  <h3><?php echo $lang->get('upgrade_login_msg_auth_needed_title'); ?></h3>
  <?php
  
  echo '<form action="upgrade.php" method="post">';
  
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
  list($major_version, $minor_version) = explode('.', enano_version());
  $current_branch = "$major_version.$minor_version";
  
  list($major_version, $minor_version) = explode('.', installer_enano_version());
  $target_branch = "$major_version.$minor_version";
  
  if ( $target_branch != $current_branch )
  {
    // First upgrade to the latest revision of the current branch
    enano_perform_upgrade($current_branch);
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
  }
  
  // Do the actual upgrade
  enano_perform_upgrade($target_branch);
  
  $site_url = makeUrl(getConfig('main_page'), false, true);
  echo '<p>All done! I\'ll actually be nice enough to give you a <a href="' . $site_url . '">link back to your site</a> this release <tt>:)</tt></p>';
  echo '<p><b>It is important that you run a language string re-import and then clear your browser cache.</b> Otherwise you may see bits of the interface that appear to not be localized. This process will be automatic and non-destructive in later versions.</p>';
}
else
{
  ?>
  <h3><?php echo $lang->get('upgrade_confirm_title'); ?></h3>
  <p><?php echo $lang->get('upgrade_confirm_body', array('enano_version' => installer_enano_version())); ?></p>
  <ul>
    <li><?php echo $lang->get('upgrade_confirm_objective_backup_fs', array('dir' => ENANO_ROOT)); ?></li>
    <li><?php echo $lang->get('upgrade_confirm_objective_backup_db', array('dbname' => $dbname)); ?></li>
  </ul>
  <form method="get" action="upgrade.php" style="text-align: center;">
    <input type="hidden" name="auth" value="<?php echo $session->sid_super; ?>" />
    <button name="stage" value="pimpmyenano" class="submit">
      <img src="images/icons/pimp.png" />
      <?php echo $lang->get('upgrade_confirm_btn_upgrade'); ?>
    </button>
  </form>
  <?php
}

$ui->show_footer();

