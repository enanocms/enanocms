<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * finish.php - Installer finalization stage
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
require ( ENANO_ROOT . '/includes/common.php' );

if ( !in_array($dbdriver, $supported_drivers) )
{
  $ui->show_header();
  echo '<h3>Installation error</h3>
         <p>ERROR: That database driver is not supported.</p>';
  return true;
}

$ui->show_header();
flush();

?>
<h3><?php echo $lang->get('finish_heading_progress'); ?></h3>
<p><?php echo $lang->get('finish_msg_progress'); ?></p>

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
run_installer_stage('cleanup', $lang->get('install_stg_cleanup_title'), 'stg_aes_cleanup', $lang->get('install_stg_cleanup_body'), false);
run_installer_stage('buildindex', $lang->get('install_stg_buildindex_title'), 'stg_build_index', $lang->get('install_stg_buildindex_body'));
run_installer_stage('renameconfig', $lang->get('install_stg_rename_title'), 'stg_rename_config', $lang->get('install_stg_rename_body', array('mainpage_link' => scriptPath . '/index.php')));

close_install_table();

?>
<h3><?php echo $lang->get('finish_msg_success_title'); ?></h3>
<p><?php echo $lang->get('finish_msg_success_body', array('mainpage_link' => makeUrlNS('Article', 'Main_Page'))); ?></p>
<?php 
  echo $lang->get('finish_body');
  echo '<p>' . $lang->get('finish_link_mainpage', array('mainpage_link' => scriptPath . '/index.php')) . '</p>';
?>
<?php

$db->close();

