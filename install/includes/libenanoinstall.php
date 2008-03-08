<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
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
  $result = @call_user_func($function, false, $already_run);
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
    echo '<form action="install.php?stage=install&amp;sub=' . $stage_id . '" method="post">
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

function enano_perform_upgrade($target_branch)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  // Import version info
  global $enano_versions;
  // Import UI functions
  global $ui;
  // This is needed for upgrade abstraction
  global $dbdriver;
  // Main upgrade stage
  
  // Init vars
  list($major_version, $minor_version) = explode('.', installer_enano_version());
  $installer_branch = "$major_version.$minor_version";
  
  $version_flipped = array_flip($enano_versions[$target_branch]);
  $version_curr = enano_version();
  // Change this to be the last version in the current branch.
  // If we're just upgrading within this branch, use the version the installer library
  // reports to us. Else, use the latest in the old (current target) branch.
  // $version_target = installer_enano_version();
  $version_target = ( $target_branch === $installer_branch ) ? installer_enano_version() : $enano_versions[$target_branch][ count($enano_versions[$target_branch]) - 1 ];
  
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
    if ( !isset($enano_versions[$target_branch][$i + 1]) )
    {
      echo '<p>ERROR: Unsupported intermediate version</p>';
      $ui->show_footer();
      exit;
    }
    $ver_this = $enano_versions[$target_branch][$i];
    $ver_next = $enano_versions[$target_branch][$i + 1];
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
    // Check for empty schema file
    if ( $sql_list[0] === ';' && count($sql_list) == 1 )
    {
      // It's empty, report success for this version
      // See below for explanation of why setConfig() is called here
      setConfig('enano_version', $verset[1]);
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
}

?>
