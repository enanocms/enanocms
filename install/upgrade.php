<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
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
$enano_versions['1.1'] = array('1.1.1', '1.1.2', '1.1.3');

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
require_once('includes/common.php');
@ini_set('display_errors', 'on');

$ui = new Enano_Installer_UI('Enano upgrader', false);
if ( version_compare(PHP_VERSION, '5.0.0', '<') )
{
  $ui->__construct('Enano upgrader', false);
}
$stg_welcome = $ui->add_stage('Welcome', true);
$stg_confirm = $ui->add_stage('Confirmation', true);
$stg_upgrade = $ui->add_stage('Perform upgrade', true);
$stg_finish  = $ui->add_stage('Finish', true);
$stg_php4 = $ui->add_stage('PHP4 compatibility notice', false);

if ( version_compare(PHP_VERSION, '5.0.0', '<') || isset($_GET['debug_warn_php4']) )
{
  $ui->set_visible_stage($stg_php4);
  $ui->step = '';
  
  $ui->show_header();
  
  // This isn't localized because all localization code is dependent on
  // PHP 5 (loading lang.php will throw a parser error under PHP4). This
  // one message probably doesn't need to be localized anyway.
  
  ?>
  <h2 class="heading-error">
    Your server doesn't have support for PHP 5.
  </h2>
  <p>
    PHP 5 is the latest version of the language on which Enano was built. Its many new features have been available since early 2004, yet
    many web hosts have not migrated to it because of the work involved. In 2007, Zend Corporation announced that support for the aging
    PHP 4.x would be discontinued at the end of the year. An initiative called <a href="http://gophp5.org/">GoPHP5</a> was started to
    encourage web hosts to migrate to PHP 5.
  </p>
  <p>
    Because of the industry's decision to not support PHP 4 any longer, the Enano team decided that it was time to begin using the powerful
    features of PHP 5 at the expense of PHP 4 compatibility. Therefore, this version of Enano cannot be installed on your server until it
    is upgraded to at least PHP 5.0.0, and preferably the latest available version.
    <!-- No, not even removing the check in this installer script will help. As soon as the PHP4 check is passed, the installer shows the
         language selection page, after which the language code is loaded. The language code and libjson2 will trigger parse errors under
         PHP <5.0.0. -->
  </p>
  <p>
    If you need to use Enano but can't upgrade your PHP because you're on a shared or reseller hosting service, you can use the
    <a href="http://enanocms.org/download?series=1.0">1.0.x series of Enano</a> on your site. While the Enano team attempts to make this
    older series work on PHP 4, official support is not provided for installations of Enano on PHP 4.
  </p>
  <?php
  
  $ui->show_footer();
  exit(0);
}

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
    $result = $session->login_without_crypto($_POST['username'], $_POST['password'], false, USER_LEVEL_ADMIN);
    if ( $result['success'] )
    {
      header('HTTP/1.1 302 Some kind of redirect with implied no content');
      header('Location: ' . scriptPath . '/install/' . $session->append_sid('upgrade.php'));
      exit();
    }
  }
  
  $ui->show_header();
  
  ?>
  <h3>Authentication needed</h3>
  <?php
  
  echo '<form action="upgrade.php" method="post">';
  
  if ( isset($result) )
  {
    echo '<b>Session manager returned error:</b>' . '<pre>' . print_r($result, true) . '</pre>';
  }
  
  ?>
  <p>You need <?php if ( !$session->user_logged_in ) echo 'to be logged in and have '; ?>an active admin session to continue.</p>
  <p>
    Username:&nbsp;&nbsp;&nbsp;<input type="text" name="username" /><br />
    Password:&nbsp;&nbsp;&nbsp;<input type="password" name="password" /><br />
    <input type="submit" name="do_login" value="Log in" />
  </p>
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
  <p>Nothing's really implemented for now except the actual migration code, which is not very smart. Just <a href="<?php echo $session->append_sid('upgrade.php?stage=pimpmyenano'); ?>">do the upgrade and get it over with</a>.</p>
  <?php
}

$ui->show_footer();

