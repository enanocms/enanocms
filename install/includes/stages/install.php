<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
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
require ( ENANO_ROOT . '/install/includes/sql_parse.php' );
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
    <h3>Database connection failed</h3>
    <p>The installer couldn't connect to the database because something went wrong while the connection attempt was being made. Please press your browser's back button and correct your database information.</p>
    <p>Error description:
      <?php
      echo $db->sql_error();
      ?>
    </p>
    <p>
      <input type="submit" name="_cont" value="Go back" />
    </p>
  </form>
  <?php
  return true;
}

// we're connected to the database now.

$ui->show_header();
flush();

?>
<h3>Installing Enano</h3>
<p>Please wait while Enano creates its database and initial content on your server.</p>

<h3>Installation progress</h3>

<?php

@set_time_limit(0);

function stg_load_files()
{
  global $dbdriver;
  if ( !@include( ENANO_ROOT . "/install/includes/payloads/common.php" ) )
    return false;
  
  if ( !@include( ENANO_ROOT . "/install/includes/payloads/$dbdriver.php" ) )
    return false;
  
  return true;
}

// FIXME: l10n
start_install_table();

run_installer_stage('load', 'Load installer files', 'stg_load_files', 'One of the files needed for installation couldn\'t be loaded. Please check your Enano directory.', false);
run_installer_stage('setpass', 'Retrieve administrator password', 'stg_password_decode', 'The administrator password couldn\'t be decrypted. This really shouldn\'t happen.');
run_installer_stage('genaes', 'Generate private key', 'stg_make_private_key', 'Couldn\'t generate a private key for the site. This really shouldn\'t happen.');
run_installer_stage('sqlparse', 'Prepare database schema', 'stg_load_schema', 'Couldn\'t load or parse the schema file. This really shouldn\'t happen.');
run_installer_stage('payload', 'Install database', 'stg_deliver_payload', 'There was a problem with an SQL query.');

close_install_table();

$db->close();

