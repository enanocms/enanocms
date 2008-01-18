<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * payload.php - Installer payload (the installation logic)
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

return true;

function stg_sim_good()
{
  return true;
}

function stg_sim_bad()
{
  return true;
}

function stg_password_decode()
{
  global $db;
  static $pass = false;
  
  if ( $pass )
    return $pass;
  
  if ( !isset($_POST['crypt_data']) && !empty($_POST['password']) && $_POST['password'] === $_POST['password_confirm'] )
    $pass = $_POST['password'];
  
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  // retrieve encryption key
  $q = $db->sql_query('SELECT config_value FROM ' . table_prefix . 'config WHERE config_name=\'install_aes_key\';');
  if ( !$q )
    $db->_die();
  if ( $db->numrows() < 1 )
    return false;
  list($aes_key) = $db->fetchrow_num();
  $aes_key = $aes->hextostring($aes_key);
  
  $pass = $aes->decrypt($_POST['crypt_data'], $aes_key, ENC_HEX);
  if ( !$pass )
    return false;
  
  return $pass; // Will be true if the password isn't crapped
}

function stg_make_private_key()
{
  global $db;
  static $site_key = false;
  
  if ( $site_key )
    return $site_key;
  
  // Is there already a key cached in the database?
  $q = $db->sql_query('SELECT config_value FROM ' . table_prefix . 'config WHERE config_name=\'site_aes_key\';');
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() > 0 )
  {
    list($site_key) = $db->fetchrow_num();
    $db->free_result();
    return $site_key;
  }
  
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  // This will use /dev/urandom if possible
  $site_key = $aes->gen_readymade_key();
  
  // Stash it in the database, don't check for errors though because we can always regenerate it
  $db->sql_query('INSERT INTO ' . table_prefix . 'config ( config_name, config_value ) VALUES ( \'site_aes_key\', \'' . $site_key . '\' );');
  
  return $site_key;
}

function stg_load_schema()
{
  global $db, $dbdriver, $installer_version;
  static $sql_parser = false;
  
  if ( is_object($sql_parser) )
    return $sql_parser->parse();
  
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  
  $site_key = stg_make_private_key();
  $site_key = $aes->hextostring($site_key);
  $admin_pass_clean = stg_password_decode();
  $admin_pass = $aes->encrypt($admin_pass_clean, $site_key, ENC_HEX);
  
  unset($admin_pass_clean); // Security
  
  try
  {
    $sql_parser = new SQL_Parser( ENANO_ROOT . "/install/schemas/{$dbdriver}_stage2.sql" );
  }
  catch ( Exception $e )
  {
    echo "<pre>$e</pre>";
    return false;
  }
  
  $vars = array(
      'TABLE_PREFIX'         => $_POST['table_prefix'],
      'SITE_NAME'            => $db->escape($_POST['site_name']),
      'SITE_DESC'            => $db->escape($_POST['site_desc']),
      'COPYRIGHT'            => $db->escape($_POST['copyright']),
      // FIXME: update form
      'WIKI_MODE'            => ( isset($_POST['wiki_mode']) ? '1' : '0' ),
      'ENABLE_CACHE'         => ( is_writable( ENANO_ROOT . '/cache/' ) ? '1' : '0' ),
      'VERSION'              => $installer_version['version'],
      'ADMIN_USER'           => $db->escape($_POST['username']),
      'ADMIN_PASS'           => $admin_pass,
      'ADMIN_EMAIL'          => $db->escape($_POST['email']),
      'REAL_NAME'            => '', // This has always been stubbed.
      'ADMIN_EMBED_PHP'      => strval(AUTH_DISALLOW),
      'UNIX_TIME'            => strval(time())
    );
  
  $sql_parser->assign_vars($vars);
  return $sql_parser->parse();
}

function stg_deliver_payload()
{
  global $db;
  $schema = stg_load_schema();
  foreach ( $schema as $sql )
  {
    if ( !$db->sql_query($sql) )
    {
      echo $db->get_error();
      return false;
    }
  }
  return true;
}

function stg_write_config()
{
  global $dbhost, $dbuser, $dbpasswd, $dbname, $dbdriver;
  $db_data = array(
      'host' => str_replace("'", "\\'", $dbhost),
      'user' => str_replace("'", "\\'", $dbuser),
      'pass' => str_replace("'", "\\'", $dbpasswd),
      'name' => str_replace("'", "\\'", $dbname),
      'tp' => table_prefix,
      'drv' => $dbdriver
    );
  
  // Retrieves the existing key
  $site_key = stg_make_private_key();
  
  // Determine contentPath
  switch ( @$_POST['url_scheme'] )
  {
    case 'standard':
    default:
      $sp_append = 'index.php?title=';
      break;
    case 'shortened':
      $sp_append = 'index.php/';
      break;
    case 'rewrite':
      $sp_append = '/';
      break;
  }
  
  $scriptpath = scriptPath;
  $contentpath = $scriptpath . $sp_append;
  
  $config_file = <<<EOF
<?php

/**
 * Enano site configuration
 * NOTE ON EDITING: You should almost never need to change anything in this
 * file. The only exceptions are when your DB password/other info is changed
 * or if you are moving your Enano installation to another directory.
 */

//
// DATABASE INFO
//

// Database type to use, currently mysql and postgresql are supported
\$dbdriver = '{$db_data['drv']}';

// Hostname of your database server, probably localhost
\$dbhost = '{$db_data['host']}';

// Username used to connect to the database
\$dbuser = '{$db_data['user']}';
// Database password
\$dbpasswd = '{$db_data['pass']}';

// Name of the database
\$dbname = '{$db_data['name']}';

//
// CONSTANTS
//

// if they're already defined, no use re-defining them
if ( !defined('ENANO_CONSTANTS') )
{
  // The prefix for the tables in the database. Useful for holding more than
  // one Enano installation in the same database.
  define('table_prefix', '{$db_data['tp']}');
  
  // The path to Enano's files on your server, from the document root. If
  // Enano is installed in your document root this will be blank; installing
  // Enano in /enano/ will result in "/enano" here, etc.
  define('scriptPath', '$scriptpath');
  
  // The authoritative prefix for pages. This should be very literal: to
  // generate a URL on the site, the format is basically
  // contentPath . \$page_name. This is based off of scriptPath and the URL
  // scheme selected during installation. Pattern:
  //
  //    * Standard URLs:  scriptPath . '/index.php?title='
  //    * Shortened URLs: scriptPath . '/index.php/'
  //    * mod_rewrite:    scriptPath . '/'
  
  define('contentPath', '$contentpath');
  
  // Tell the Enano API that we're installed and that this file is complete
  define('ENANO_INSTALLED', 'You bet!');
  
  define('ENANO_CONSTANTS', '');
}

// The AES encryption key used to store passwords. We have a very specific
// reason for doing this; see the rationale at:
//   http://docs.enanocms.org/Help:Appendix_B
\$crypto_key = '$site_key';

EOF;
  
  // Write config file
  
  $ch = @fopen ( ENANO_ROOT . '/config.new.php', 'w' );
  if ( !$ch )
    return false;
  
  fwrite($ch, $config_file);
  fclose($ch);
  
  // If we are using mod_rewrite, also append any existing .htaccess
  if ( @$_POST['url_scheme'] === 'rewrite' )
  {
    $hh = @fopen ( ENANO_ROOT . '/.htaccess.new', 'w' );
    if ( !$hh )
      return false;
    $hhc = <<<EOF
#
# START ENANO RULES
#

# Enable mod_rewrite
RewriteEngine on

# Don't rewrite if the user requested a real directory or file
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Main rule - short and sweet
RewriteRule (.*) index.php?title=\$1 [L,QSA]

EOF;
    fwrite($hh, $hhc);
    fclose($hh);
  }
  
  return true;
}

function stg_language_setup()
{
  global $languages, $db;
  global $lang_id;
  $lang_info =& $languages[$lang_id];
  if ( !is_array($lang_info) )
    return false;
  
  // Install the language
  // ($lang_code, $lang_name_neutral, $lang_name_local, $lang_file = false)
  $result = install_language($lang_id, $lang_info['name_eng'], $lang_info['name'], ENANO_ROOT . "/language/{$lang_info['dir']}/core.json");
  if ( !$result )
    return false;
  
  $lang_local = new Language($lang_id);
  $lang_local->import( ENANO_ROOT . "/language/{$lang_info['dir']}/user.json" );
  $lang_local->import( ENANO_ROOT . "/language/{$lang_info['dir']}/tools.json" );
  $lang_local->import( ENANO_ROOT . "/language/{$lang_info['dir']}/admin.json" );
  
  return true;
}
