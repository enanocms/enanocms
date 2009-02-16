#!/usr/bin/env php
<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * install-cli.php - CLI installation frontend stub
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 * 
 * Thanks to Stephan B. for helping out with l10n in the installer (his work is in includes/stages/*.php).
 */

$result = require(dirname(__FILE__) . '/includes/cli-core.php');
exit( $result ? 0 : 1 );

