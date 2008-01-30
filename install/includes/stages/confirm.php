<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * confirm.php - Installer installation summary/confirmation stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

require_once( ENANO_ROOT . '/includes/constants.php' );

$ui->show_header();
?>
           <h3><?php echo $lang->get('confirm_title'); ?></h3>
            <p><?php echo $lang->get('confirm_body'); ?></p>
            <p style="font-size: smaller;"><b><?php echo $lang->get('confirm_info_aes_title'); ?></b>
               <?php echo $lang->get('confirm_info_aes_body', array('aes_bits' => AES_BITS)); ?>
               </p>
            <form action="install.php?stage=install" method="post" name="install_login" onsubmit="return ( verify() && submit_encrypt() );"><?php
  foreach ( $_POST as $key => &$value )
  {
    if ( !preg_match('/^[a-z0-9_]+$/', $key) )
      die('You idiot hacker...');
    if ( $key == '_cont' )
      continue;
    $value_clean = str_replace(array('\\', '"', '<', '>'), array('\\\\', '\\"', '&lt;', '&gt;'), $value);
    echo "\n              <input type=\"hidden\" name=\"$key\" value=\"$value_clean\" />";
  }
?>

              <div style="text-align: center;">
                <input type="submit" name="_cont" value="<?= $lang->get('confirm_btn_install_enano'); ?>" />
              </div>
            </form>
