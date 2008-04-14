<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * database.php - Installer database driver selection stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

echo '<h3>' . $lang->get('database_driver_heading') . '</h3>';
echo '<p>' . $lang->get('database_driver_intro') . '</p>';
if ( @file_exists('/etc/enano-is-virt-appliance') )
{
  echo '<p>' . $lang->get('database_driver_msg_virt_appliance') . '</p>';
}

$mysql_disable_reason = '';
$pgsql_disable_reason = '';
$mysql_disable = '';
$pgsql_disable = '';
if ( !function_exists('mysql_connect') )
{
  $mysql_disable = ' disabled="disabled"';
  $mysql_disable_reason = $lang->get('database_driver_err_no_mysql');
}
if ( !function_exists('pg_connect') )
{
  $pgsql_disable = ' disabled="disabled"';
  $pgsql_disable_reason = $lang->get('database_driver_err_no_pgsql');
}

echo '<form action="install.php?stage=database" method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="language" value="' . $lang_id . '" />';
?>
<table border="0" cellspacing="5">
  <tr>
    <td>
      <?php 
      if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ):
      ?>
      <input type="radio" checked="checked" name="driver" value="mysql" <?php echo $mysql_disable; ?>/>
      <?php
      else:
      ?>
      <button name="driver" value="mysql"<?php echo $mysql_disable; ?>>
        <img src="../images/about-powered-mysql.png" />
      </button>
      <?php
      endif;
      ?>
    </td>
    <td<?php if ( $mysql_disable ) echo ' style="opacity: 0.5; filter: alpha(opacity=50);"'; ?>>
      <b><?php echo $lang->get('database_driver_mysql'); ?></b><br />
      <?php echo $lang->get('database_driver_mysql_intro'); ?>
      <?php
      if ( $mysql_disable )
      {
        echo "<br /><br /><b>$mysql_disable_reason</b>";
      }
      ?>
    </td>
  </tr>
  <tr>
    <td>
      <?php
      if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ):
      ?>
      <input type="radio" name="driver" value="mysql" <?php echo $pgsql_disable; ?>/>
      <?php
      else:
      ?>
      <button name="driver" value="postgresql"<?php echo $pgsql_disable; ?>>
        <img src="../images/about-powered-pgsql.png" />
      </button>
      <?php
      endif;
      ?>
    </td>
    <td<?php if ( $pgsql_disable ) echo ' style="opacity: 0.5; filter: alpha(opacity=50);"'; ?>>
      <b><?php echo $lang->get('database_driver_pgsql'); ?></b><br />
      <?php echo $lang->get('database_driver_pgsql_intro'); ?>
      <?php
      if ( $pgsql_disable )
      {
        echo "<br /><br /><b>$pgsql_disable_reason</b>";
      }
      ?>
    </td>
  </tr>
</table>

<?php
if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') )
{
  echo '<div style="text-align: center;">
          <input type="submit" />
        </div>';
}

echo '</form>';
