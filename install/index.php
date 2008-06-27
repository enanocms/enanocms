<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * welcome.php - Portal to upgrade, readme, and install pages
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require_once('includes/common.php');

$ui = new Enano_Installer_UI('Enano installation', true);
if ( version_compare(PHP_VERSION, '5.0.0', '<') )
{
  $ui->__construct('Enano installation', true);
}
$ui->add_stage('Welcome', true);
$ui->add_stage('Installation', true);
$ui->add_stage('Upgrade', true);
$ui->add_stage('Readme', true);

$ui->show_header();

if ( defined('ENANO_INSTALLED') )
{
  // Is Enano installed? If so, load the config and check version info
  define('IN_ENANO_UPGRADE', 'true');
  // common.php above calls chdir() to the ENANO_ROOT, so this loads the full Enano API.
  require('includes/common.php');
}

// are we in PHP5?
define('HAVE_PHP5', version_compare(PHP_VERSION, '5.0.0', '>='));

?>

          <div id="installnotice">
          <?php
            if ( !defined('ENANO_INSTALLED') ):
            ?>
            <div class="info-box-mini">
              <b>Enano hasn't been installed yet!</b><br />
              You'll need to install the Enano database before you can use your site. To get started, click the Install button below.
            </div>
          <?php
            if ( file_exists('./config.php') )
            {
            ?>
            <div class="warning-box-mini">
              <b>A configuration file (config.php) exists but doesn't set the ENANO_INSTALLED constant.</b><br />
              <p>Didn't expect to see this message?
              It's possible that your configuration file has become corrupted and no longer sets information that Enano needs to connect
              to the database. You should have a look at your config.php by downloading it with FTP or viewing it over SSH.
              If the file appears to have been tampered with, please <a href="http://forum.enanocms.org/">contact the Enano team</a>
              for support immediately.</p>
              <p><b>Most importantly, if you suspect a security breach, you should contact the Enano team
                 <a href="http://enanocms.org/Contact_us">via e-mail</a>. If you have the capability to use PGP encryption, you should do
                 so; our public key is available <a href="http://enanocms.org/bin/enanocms-signkey.asc">here</a>.</b></p>
            </div>
            <?php
            }
            endif;
            ?></div>
          <table border="0" cellspacing="10" cellpadding="0" width="100%" id="installmenu">
            <tr>
              <td style="text-align: right; width: 50%;">
                <!-- Enano logo -->
                <img alt="Enano CMS" src="images/enano-artwork/installer-greeting.png" />
              </td>
              <td class="balancer">
              </td>
              <td>
                <ul class="icons">
                  <li><a href="readme.php" class="readme icon">Readme</a></li>
<?php
                  if ( !defined('ENANO_INSTALLED') ):
                  ?>
                  <li><a href="install.php" class="install icon">Install</a></li>
                  <li>
                    <a class="upgrade-disabled icon icon-disabled" title="You need to install Enano before you can do this.">
                      Upgrade
                      <small>
                        Enano isn't installed yet. You can use this option when you want to upgrade to a newer release of Enano.
                      </small>
                    </a>
                  </li>
<?php
                  else:
                  ?>
                  <li>
                    <a class="install-disabled icon icon-disabled" title="Enano is already installed.">
                      Install
                      <small>Enano is already installed.</small> <!-- CSS takes care of making this position properly -->
                    </a>
                  </li>
                  <?php
                  if ( installer_enano_version() == enano_version(true) )
                  {
                    echo '<li>
                    <a class="upgrade-disabled icon icon-disabled">
                      Upgrade
                      <small>
                        You\'re already running the version of Enano included in this installer. Use the administration panel to check
                        for updates.
                      </small> <!-- CSS takes care of making this position properly -->
                    </a>
                  </li>';
                  }
                  else
                  {
                    if ( HAVE_PHP5 && !isset($_GET['debug_warn_php4']) )
                      echo '<li><a href="upgrade.php" class="upgrade icon">Upgrade</a></li>';
                    else
                      echo '<li>
                    <span class="upgrade-disabled icon icon-disabled">
                      Upgrade
                      <small>
                        Your server doesn\'t have PHP 5 or later installed. Enano 1.2 does not have support for PHP 4.
                        <a href="install.php?debug_warn_php4">Learn more &raquo;</a>
                      </small> <!-- CSS takes care of making this position properly -->
                    </span>
                  </li>';
                  }
                  endif;
                  ?>
                
                </ul>
              </td>
            </tr>
          </table>

<?php

$ui->show_footer();

?>
