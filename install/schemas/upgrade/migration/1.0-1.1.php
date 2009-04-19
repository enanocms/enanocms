<?php

function MIGRATE()
{
  global $languages;
  global $db, $dbdriver;
  
  // Database upgrade
  try
  {
    $sql_parser = new SQL_Parser('install/schemas/upgrade/migration/1.0-1.1-' . $dbdriver . '.sql');
  }
  catch ( Exception $e )
  {
    die("<pre>$e</pre>");
  }
  
  $sql_parser->assign_vars(array(
      'TABLE_PREFIX' => table_prefix
    ));
  
  $sql_list = $sql_parser->parse();
  foreach ( $sql_list as $sql )
  {
    if ( !$db->sql_query($sql) )
      $db->_die();
  }
  
  // Install default language
  $lang_id = 'eng';
  $lang_data =& $languages[$lang_id];
  $lang_dir = ENANO_ROOT . "/language/{$lang_data['dir']}/";
  // function install_language($lang_code, $lang_name_neutral, $lang_name_local, $lang_file = false)
  install_language($lang_id, $lang_data['name_eng'], false);
  
  // Only import strings if the script isn't planning to do it again later
  global $do_langimport;
  if ( !$do_langimport )
  {
    $lang_local = new Language($lang_id);
    $lang_local->import($lang_dir . "core.json");
    $lang_local->import($lang_dir . "tools.json");
    $lang_local->import($lang_dir . "user.json");
    $lang_local->import($lang_dir . "admin.json");
  }
  
  // This doesn't set to installer_enano_version() because it only
  // migrates the database from 1.0.x to 1.1.x status and runs the
  // core logic required to transform a 1.0.x installation into
  // a 1.1.x installation. Thus, when upgrading, the upgrade script
  // still needs to run all later upgrade schema files in addition
  // to this migration code.
  setConfig('enano_version', '1.1.1');
  
  return true;
}

