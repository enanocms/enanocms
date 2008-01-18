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

require_once('includes/common.php');
@ini_set('display_errors', 'on');

$ui = new Enano_Installer_UI('Enano upgrader', false);
if ( version_compare(PHP_VERSION, '5.0.0', '<') )
{
  $ui->__construct('Enano upgrader', false);
}
$ui->add_stage('Welcome', true);
$ui->add_stage('Select version', true);
$ui->add_stage('Perform upgrade', true);
$ui->add_stage('Finish', true);
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

$ui->show_header();
$ui->show_footer();

