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
  echo '<tr><td style="width: 500px; background-color: #' . "{$neutral_color}{$neutral_color}FF{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Done" src="../images/good.gif" /></td></tr>' . "\n";
  flush();
}

function echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack)
{
  global $neutral_color;
  global $lang;
  
  $neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
  echo '<tr><td style="width: 500px; background-color: #' . "FF{$neutral_color}{$neutral_color}{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Failed" src="../images/bad.gif" /></td></tr>' . "\n";
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

?>
