<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * sysreqs.php - Installer system-requirements page
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

global $failed, $warned;

$failed = false;
$warned = false;

function not($var)
{
  if($var)
  {
    return false;
  } 
  else
  {
    return true;
  }
}

function run_test($code, $desc, $extended_desc, $warn = false)
{
  global $failed, $warned;
  static $cv = true;
  $cv = not($cv);
  $val = eval($code);
  if($val)
  {
    if($cv) $color='CCFFCC'; else $color='AAFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc</td><td style='padding-left: 10px;'><img alt='Test passed' src='../images/check.png' /></td></tr>";
  } elseif(!$val && $warn) {
    if($cv) $color='FFFFCC'; else $color='FFFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test passed with warning' src='../images/checkunk.png' /></td></tr>";
    $warned = true;
  } else {
    if($cv) $color='FFCCCC'; else $color='FFAAAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test failed' src='../images/checkbad.png' /></td></tr>";
    $failed = true;
  }
}
function is_apache()
{
  $r = strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') ? true : false;
  return $r;
}

function config_write_test()
{
  if ( !is_writable(ENANO_ROOT.'/config.new.php') )
    return false;
  // We need to actually _open_ the file to make sure it can be written, because sometimes this fails even when is_writable() returns
  // true on Windows/IIS servers. Don't ask me why.
  $h = @fopen( ENANO_ROOT . '/config.new.php', 'a+' );
  if ( !$h )
    return false;
  fclose($h);
  return true;
}

?>
<h3><?php echo $lang->get('sysreqs_heading'); ?></h3>
 <p><?php echo $lang->get('sysreqs_blurb'); ?></p>
 
<table border="0" cellspacing="0" cellpadding="0">

<?php
run_test('return version_compare(\'5.2.0\', PHP_VERSION, \'<=\');', $lang->get('sysreqs_req_php5'), $lang->get('sysreqs_req_desc_php5'), true);
run_test('return function_exists(\'mysql_connect\');', $lang->get('sysreqs_req_mysql'), $lang->get('sysreqs_req_desc_mysql'), true);
run_test('return function_exists(\'pg_connect\');', $lang->get('sysreqs_req_postgres'), $lang->get('sysreqs_req_desc_postgres'), true);
run_test('return @ini_get(\'file_uploads\');', $lang->get('sysreqs_req_uploads'), $lang->get('sysreqs_req_desc_uploads') );
run_test('return is_apache();', $lang->get('sysreqs_req_apache'), $lang->get('sysreqs_req_desc_apache'), true);
run_test('return config_write_test();', $lang->get('sysreqs_req_config'), $lang->get('sysreqs_req_desc_config') );
run_test('return file_exists(\'/usr/bin/convert\');', $lang->get('sysreqs_req_magick'), $lang->get('sysreqs_req_desc_magick'), true);
run_test('return is_writable(ENANO_ROOT.\'/cache/\');', $lang->get('sysreqs_req_cachewriteable'), $lang->get('sysreqs_req_desc_cachewriteable'), true);
run_test('return is_writable(ENANO_ROOT.\'/files/\');', $lang->get('sysreqs_req_fileswriteable'), $lang->get('sysreqs_req_desc_fileswriteable'), true);
if ( !function_exists('mysql_connect') && !function_exists('pg_connect') )
{
  run_test('return false;', $lang->get('sysreqs_req_nodbdrivers'), $lang->get('sysreqs_req_desc_nodbdrivers'), false);
}
echo '</table>';
echo '<br />';
if(!$failed)
{
  ?>
  
  <div class="pagenav">
  <?php
  if($warned) {
    echo '<table border="0" cellspacing="0" cellpadding="0">';
    run_test('return false;', $lang->get('sysreqs_summary_warn_title'), $lang->get('sysreqs_summary_warn_body'), true);
    echo '</table>';
  } else {
    echo '<table border="0" cellspacing="0" cellpadding="0">';
    run_test('return true;', '<b>' . $lang->get('sysreqs_summary_success_title') . '</b><br />' . $lang->get('sysreqs_summary_success_body'), 'You should never see this text. Congratulations for being an Enano hacker!');
    echo '</table>';
  }
  ?>
  <form action="install.php?stage=database" method="post">
    <?php
      echo '<input type="hidden" name="language" value="' . $lang_id . '" />';
    ?>
    <table border="0">
    <tr>
      <td>
        <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
      </td>
      <td>
        <p>
          <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
          &bull; <?php echo $lang->get('sysreqs_objective_scalebacks'); ?><br />
          &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
        </p>
      </td>
    </tr>
    </table>
  </form>
  </div>
<?php
}
else
{
  if ( $failed )
  {
    echo '<div class="pagenav"><table border="0" cellspacing="0" cellpadding="0">';
    run_test('return false;', $lang->get('sysreqs_summary_fail_title'), $lang->get('sysreqs_summary_fail_body'));
    echo '</table></div>';
  }
}
    
?>
