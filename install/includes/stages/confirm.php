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
<h3>Enano is ready to install.</h3>
            <p>Almost there! You've entered all the information we need for now. Click Continue to install the Enano database.</p>
            <p style="font-size: smaller;"><b>A note on AES encryption:</b>
               Enano is currently configured to use <?php echo AES_BITS; ?>-bit AES encryption. While the default value of 192 bits is perfectly acceptable for most sites, those in need of extreme security will want to change this value to 256 bits (the maximum available strength). If you need to change the cipher strength, please edit the file includes/constants.php and then <u>restart</u> this installation. Do not click Continue below until you redo the installation process up until this point, or you will experience severe problems with logging into your site.
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
                <input type="submit" name="_cont" value="<?= $lang->get('meta_btn_continue'); ?>" />
              </div>
            </form>
