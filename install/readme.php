<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * install.php - Main installation interface
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

require_once('includes/common.php');

$ui = new Enano_Installer_UI('Enano installation', false);

$stg_readme = $ui->add_stage('Readme and important information', true);

$ui->set_visible_stage($stg_readme);

$ui->show_header();

?>
<h2>Readme</h2>
<p>This document contains important information you'll want to know before you install Enano. For installation instructions, please
   see the <a href="http://docs.enanocms.org/Help:2.1">Enano installation guide</a>. <a href="index.php">Return to welcome menu &raquo;</a></p>
<pre class="scroller"><?php

$readme = @file_get_contents('./README');
echo htmlspecialchars($readme);

?></pre>
<?php

$ui->show_footer();

?>
