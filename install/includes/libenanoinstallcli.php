<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * libenanoinstallcli.php - Installer frontend logic, CLI version
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function run_installer_stage($stage_id, $stage_name, $function, $failure_explanation, $allow_skip = true)
{
  global $silent, $lang;
  
  if ( !$silent )
    echo parse_shellcolor_string($lang->get("cli_msg_$stage_name"));
  
  $result = @call_user_func($function);
  
  if ( !$result )
  {
    if ( !$silent )
      echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
    installer_fail($lang->get("cli_err_$stage_name"));
  }
  
  if ( !$silent )
    echo parse_shellcolor_string($lang->get('cli_msg_ok')) . "\n";
}

