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

// The list of versions in THIS BRANCH, in chronological order.
$enano_versions = array('1.1.1', '1.1.2', '1.1.3');

// Turn on every imaginable API hack to make common load on older databases
define('IN_ENANO_UPGRADE', 1);
define('IN_ENANO_MIGRATION', 1);
define('ENANO_ALLOW_LOAD_NOLANG', 1);
@ini_set('display_errors', 'on');

require('includes/sql_parse.php');

require_once('includes/common.php');
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
    echo '<b>Session manager returned error: ' . $result['error'] . '</b>';
  }
  
  ?>
  <p>You need an active admin session to continue.</p>
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
  // Do we need to run the migration first?
  if ( substr(enano_version(), 0, 4) != '1.1.' )
  {
    require(ENANO_ROOT . '/install/upgrade/migration/1.0-1.1.php');
    $result = MIGRATE();
    if ( !$result )
    {
      echo 'Migration failed, there should be an error message above.';
      $ui->show_footer();
      exit;
    }
  }
  // Main upgrade stage
  
  // Init vars
  $version_flipped = array_flip($enano_versions);
  $version_curr = enano_version();
  $version_target = installer_enano_version();
  
  // Calculate which scripts to run
  if ( !isset($version_flipped[$version_curr]) )
  {
    echo '<p>ERROR: Unsupported version</p>';
    $ui->show_footer();
    exit;
  }
  if ( !isset($version_flipped[$version_target]) )
  {
    echo '<p>ERROR: Upgrader doesn\'t support its own version</p>';
    $ui->show_footer();
    exit;
  }
  $upg_queue = array();
  for ( $i = $version_flipped[$version_curr]; $i < $version_flipped[$version_target]; $i++ )
  {
    if ( !isset($enano_versions[$i + 1]) )
    {
      echo '<p>ERROR: Unsupported intermediate version</p>';
      $ui->show_footer();
      exit;
    }
    $ver_this = $enano_versions[$i];
    $ver_next = $enano_versions[$i + 1];
    $upg_queue[] = array($ver_this, $ver_next);
  }
  
  // Verify that all upgrade scripts are usable
  foreach ( $upg_queue as $verset )
  {
    $file = ENANO_ROOT . "/install/schemas/upgrade/{$verset[0]}-{$verset[1]}-$dbdriver.sql";
    if ( !file_exists($file) )
    {
      echo "<p>ERROR: Couldn't find required schema file: $file</p>";
      $ui->show_footer();
      exit;
    }
  }
  // Perform upgrade
  foreach ( $upg_queue as $verset )
  {
    $file = ENANO_ROOT . "/install/schemas/upgrade/{$verset[0]}-{$verset[1]}-$dbdriver.sql";
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
  
    foreach ( $sql_list as $sql )
    {
      if ( !$db->sql_query($sql) )
        $db->_die();
    }
    
    // Is there an additional script (logic) to be run after the schema?
    $postscript = ENANO_ROOT . "/install/schemas/upgrade/{$verset[0]}-{$verset[1]}.php";
    if ( file_exists($postscript) )
      @include($postscript);
    
    // The advantage of calling setConfig on the system version here?
    // Simple. If the upgrade fails, it will pick up from the last
    // version, not try to start again from the beginning. This will
    // still cause errors in most cases though. Eventually we probably
    // need some sort of query-numbering system that tracks in-progress
    // upgrades.
    
    setConfig('enano_version', $verset[1]);
  }
  echo '<p>All done!</p>';
}
else
{
  ?>
  <p>Nothing's really implemented for now except the actual migration code, which is not very smart. Just <a href="<?php echo $session->append_sid('upgrade.php?stage=pimpmyenano'); ?>">do the upgrade and get it over with</a>.</p>
  <?php
}

$ui->show_footer();

