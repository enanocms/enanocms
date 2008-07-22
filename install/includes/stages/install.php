<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * install.php - Installer payload stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

require ( ENANO_ROOT . '/install/includes/libenanoinstall.php' );
require ( ENANO_ROOT . '/includes/sql_parse.php' );
require ( ENANO_ROOT . '/includes/dbal.php' );
require ( ENANO_ROOT . '/config.new.php' );

if ( !in_array($dbdriver, $supported_drivers) )
{
  $ui->show_header();
  echo '<h3>Installation error</h3>
         <p>ERROR: That database driver is not supported.</p>';
  return true;
}

$db = new $dbdriver();
$result = $db->connect(true, $dbhost, $dbuser, $dbpasswd, $dbname);
if ( !$result )
{
  $ui->show_header();
  // FIXME: l10n
  ?>
  <form action="install.php?stage=database" method="post" name="database_info">
    <input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
    <input type="hidden" name="driver" value="<?php echo $dbdriver; ?>" />
    <h3><?php echo $lang->get('database_msg_post_fail_title'); ?></h3>
    <p><?php echo $lang->get('database_msg_post_fail_body'); ?></p>
    <p><?php echo $lang->get('database_msg_post_fail_desc'); ?>
      <?php
      echo $db->sql_error();
      ?>
    </p>
    <p>
      <!-- FIXME: l10n -->
      <input type="submit" name="_cont" value="<?php echo $lang->get('database_btn_go_back'); ?>" />
    </p>
  </form>
  <?php
  return true;
}

// we're connected to the database now.

$ui->show_header();
flush();

?>
<h3><?php echo $lang->get('install_title'); ?></h3>
<p><?php echo $lang->get('install_body'); ?></p>

<h3><?php echo $lang->get('install_heading_progress'); ?></h3>

<?php

@set_time_limit(0);

function stg_load_files()
{
  global $dbdriver;
  if ( !@include( ENANO_ROOT . "/install/includes/payload.php" ) )
    return false;
  
  return true;
}

start_install_table();

run_installer_stage('load', $lang->get('install_stg_load_title'), 'stg_load_files', $lang->get('install_stg_load_body'), false);
run_installer_stage('setpass', $lang->get('install_stg_setpass_title'), 'stg_password_decode', $lang->get('install_stg_setpass_body'));
run_installer_stage('genaes', $lang->get('install_stg_genaes_title'), 'stg_make_private_key', $lang->get('install_stg_genaes_body'));
run_installer_stage('sqlparse', $lang->get('install_stg_sqlparse_title'), 'stg_load_schema', $lang->get('install_stg_sqlparse_body'));
run_installer_stage('payload', $lang->get('install_stg_payload_title'), 'stg_deliver_payload', $lang->get('install_stg_payload_body'));
run_installer_stage('writeconfig', $lang->get('install_stg_writeconfig_title'), 'stg_write_config', $lang->get('install_stg_writeconfig_body'));

// Now that the config is written, shutdown our primitive API and startup the full Enano API
$db->close();

@define('ENANO_ALLOW_LOAD_NOLANG', 1);
require(ENANO_ROOT . '/includes/common.php');
        
if ( is_object($db) && is_object($session) )
{
  run_installer_stage('startapi', $lang->get('install_stg_startapi_title'), 'stg_sim_good', '...', false);
}
else
{
  run_installer_stage('startapi', $lang->get('install_stg_startapi_title'), 'stg_sim_bad', $lang->get('install_stg_startapi_body'), false);
}

// Import languages
error_reporting(E_ALL | E_STRICT);
run_installer_stage('importlang', $lang->get('install_stg_importlang_title'), 'stg_language_setup', $lang->get('install_stg_importlang_body'));

// Init logs
run_installer_stage('initlogs', $lang->get('install_stg_initlogs_title'), 'stg_init_logs', $lang->get('install_stg_initlogs_body'));

close_install_table();

?>
<form action="install.php?stage=finish" method="post">
  <input type="hidden" name="language" value="<?php echo $lang_id; ?>" />
  <div style="text-align: center;">
    <input type="submit" name="_cont" value="<?php echo $lang->get('meta_btn_continue'); ?>" tabindex="1" />
  </div>
</form>
<?php

$db->close();

