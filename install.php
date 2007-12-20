<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * install.php - handles everything related to installation and initial configuration
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
@include('config.php');
if( ( defined('ENANO_INSTALLED') || defined('MIDGET_INSTALLED') ) && ((isset($_GET['mode']) && ($_GET['mode']!='finish' && $_GET['mode']!='css') && $_GET['mode']!='showlicense') || !isset($_GET['mode'])))
{
  $_GET['title'] = 'Enano:Installation_locked';
  require('includes/common.php');
  die_friendly('Installation locked', '<p>The Enano installer has found a Enano installation in this directory. You MUST delete config.php if you want to re-install Enano.</p><p>If you wish to upgrade an older Enano installation to this version, please use the <a href="upgrade.php">upgrade script</a>.</p>');
  exit;
}

define('IN_ENANO_INSTALL', 'true');

define('ENANO_VERSION', '1.1.1');
define('ENANO_CODE_NAME', 'Germination');
// In beta versions, define ENANO_BETA_VERSION here

// This is required to make installation work right
define("ENANO_ALLOW_LOAD_NOLANG", 1);

if(!defined('scriptPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('scriptPath', $sp);
}

if(!defined('contentPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('contentPath', $sp);
}
global $_starttime, $this_page, $sideinfo;
$_starttime = microtime(true);

global $db;

// Determine directory (special case for development servers)
if ( strpos(__FILE__, '/repo/') && file_exists('.enanodev') )
{
  $filename = str_replace('/repo/', '/', __FILE__);
}
else
{
  $filename = __FILE__;
}

define('ENANO_ROOT', dirname($filename));

function is_page($p)
{
  return true;
}

require('includes/wikiformat.php');
require('includes/constants.php');
require('includes/rijndael.php');
require('includes/functions.php');
require('includes/dbal.php');
require('includes/lang.php');
require('includes/json.php');

strip_magic_quotes_gpc();

//
// INSTALLER LIBRARY
//

$neutral_color = 'C';

function run_installer_stage($stage_id, $stage_name, $function, $failure_explanation, $allow_skip = true)
{
  static $resumed = false;
  static $resume_stack = array();
  
  if ( empty($resume_stack) && isset($_POST['resume_stack']) && preg_match('/[a-z_]+((\|[a-z_]+)+)/', $_POST['resume_stack']) )
  {
    $resume_stack = explode('|', $_POST['resume_stack']);
  }
  
  $already_run = false;
  if ( in_array($stage_id, $resume_stack) )
  {
    $already_run = true;
  }
  
  if ( !$resumed )
  {
    if ( !isset($_GET['stage']) )
      $resumed = true;
    if ( isset($_GET['stage']) && $_GET['stage'] == $stage_id )
    {
      $resumed = true;
    }
  }
  if ( !$resumed && $allow_skip )
  {
    echo_stage_success($stage_id, $stage_name);
    return false;
  }
  if ( !function_exists($function) )
    die('libenanoinstall: CRITICAL: function "' . $function . '" for ' . $stage_id . ' doesn\'t exist');
  $result = @call_user_func($function, false, $already_run);
  if ( $result )
  {
    echo_stage_success($stage_id, $stage_name);
    $resume_stack[] = $stage_id;
    return true;
  }
  else
  {
    echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack);
    return false;
  }
}

function start_install_table()
{
  echo '<table border="0" cellspacing="0" cellpadding="0" style="margin-top: 10px;">' . "\n";
  ob_start();
}

function close_install_table()
{
  echo '</table>' . "\n\n";
  ob_end_flush();
}

function echo_stage_success($stage_id, $stage_name)
{
  global $neutral_color;
  $neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
  echo '<tr><td style="width: 500px; background-color: #' . "{$neutral_color}{$neutral_color}FF{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Done" src="images/good.gif" /></td></tr>' . "\n";
  ob_flush();
}

function echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack)
{
  global $neutral_color;
  global $lang;
  
  $neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
  echo '<tr><td style="width: 500px; background-color: #' . "FF{$neutral_color}{$neutral_color}{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Failed" src="images/bad.gif" /></td></tr>' . "\n";
  ob_flush();
  close_install_table();
  $post_data = '';
  $mysql_error = mysql_error();
  foreach ( $_POST as $key => $value )
  {
    // FIXME: These should really also be sanitized for double quotes
    $value = htmlspecialchars($value);
    $key = htmlspecialchars($key);
    $post_data .= "          <input type=\"hidden\" name=\"$key\" value=\"$value\" />\n";
  }
  echo '<form action="install.php?mode=install&amp;stage=' . $stage_id . '" method="post">
          ' . $post_data . '
          <input type="hidden" name="resume_stack" value="' . htmlspecialchars(implode('|', $resume_stack)) . '" />
          <h3>' . $lang->get('meta_msg_err_stagefailed_title') . '</h3>
           <p>' . $failure_explanation . '</p>
           ' . ( !empty($mysql_error) ? "<p>" . $lang->get('meta_msg_err_stagefailed_mysqlerror') . " $mysql_error</p>" : '' ) . '
           <p>' . $lang->get('meta_msg_err_stagefailed_body') . '</p>
           <p style="text-align: center;"><input type="submit" value="' . $lang->get('meta_btn_retry_installation') . '" /></p>
        </form>';
  global $template, $template_bak;
  if ( is_object($template_bak) )
    $template_bak->footer();
  else
    $template->footer();
  exit;
}

//
// INSTALLER STAGES
//

function stg_mysql_connect($act_get = false)
{
  global $db;
  $db = new mysql();
  
  static $conn = false;
  if ( $act_get )
    return $conn;
  
  $db_user =& $_POST['db_user'];
  $db_pass =& $_POST['db_pass'];
  $db_name =& $_POST['db_name'];
  
  if ( !preg_match('/^[a-z0-9_-]+$/', $db_name) )
  {
    $db_name = htmlspecialchars($db_name);
    die("<p>SECURITY: malformed database name \"$db_name\"</p>");
  }
  
  // First, try to connect using the normal credentials
  $conn = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
  if ( !$conn )
  {
    // Connection failed. Do we have the root username and password?
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @mysql_connect($_POST['db_host'], $_POST['db_root_user'], $_POST['db_root_pass']);
      if ( !$conn_root )
      {
        // Couldn't connect using either set of credentials. Bail out.
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = mysql_real_escape_string($_POST['db_user']);
      $db_pass = mysql_real_escape_string($_POST['db_pass']);
      // Create the user account
      $q = @mysql_query("GRANT ALL PRIVILEGES ON test.* TO '{$db_user}'@'localhost' IDENTIFIED BY '$db_pass' WITH GRANT OPTION;", $conn_root);
      if ( !$q )
      {
        return false;
      }
      // Revoke privileges from test, we don't need them
      $q = @mysql_query("REVOKE ALL PRIVILEGES ON test.* FROM '{$db_user}'@'localhost';", $conn_root);
      if ( !$q )
      {
        return false;
      }
      if ( $_POST['db_host'] != 'localhost' && $_POST['db_host'] != '127.0.0.1' && $_POST['db_host'] != '::1' )
      {
        // If not connecting to a server running on localhost, allow from any host
        // this is safer than trying to detect the hostname of the webserver, but less secure
        $q = @mysql_query("GRANT ALL PRIVILEGES ON test.* TO '{$db_user}'@'%' IDENTIFIED BY '$db_pass' WITH GRANT OPTION;", $conn_root);
        if ( !$q )
        {
          return false;
        }
        // Revoke privileges from test, we don't need them
        $q = @mysql_query("REVOKE ALL PRIVILEGES ON test.* FROM '{$db_user}'@'%';", $conn_root);
        if ( !$q )
        {
          return false;
        }
      }
      mysql_close($conn_root);
      $conn = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
      if ( !$conn )
      {
        // This should honestly never happen.
        return false;
      }
    }
  }
  $q = @mysql_query("USE `$db_name`;", $conn);
  if ( !$q )
  {
    // access denied to the database; try the whole root schenanegan again
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @mysql_connect($_POST['db_host'], $_POST['db_root_user'], $_POST['db_root_pass']);
      if ( !$conn_root )
      {
        // Couldn't connect as root; bail out
        return false;
      }
      // create the database, if it doesn't exist
      $q = @mysql_query("CREATE DATABASE IF NOT EXISTS `$db_name`;", $conn_root);
      if ( !$q )
      {
        // this really should never fail, so don't give any tolerance to it
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = mysql_real_escape_string($_POST['db_user']);
      $db_pass = mysql_real_escape_string($_POST['db_pass']);
      // we're in with root rights; grant access to the database
      $q = @mysql_query("GRANT ALL PRIVILEGES ON `$db_name`.* TO '{$db_user}'@'localhost';", $conn_root);
      if ( !$q )
      {
        return false;
      }
      if ( $_POST['db_host'] != 'localhost' && $_POST['db_host'] != '127.0.0.1' && $_POST['db_host'] != '::1' )
      {
        $q = @mysql_query("GRANT ALL PRIVILEGES ON `$db_name`.* TO '{$db_user}'@'%';", $conn_root);
        if ( !$q )
        {
          return false;
        }
      }
      mysql_close($conn_root);
      // grant tables have hopefully been flushed, kill and reconnect our regular user connection
      mysql_close($conn);
      $conn = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
      if ( !$conn )
      {
        return false;
      }
    }
    else
    {
      return false;
    }
    // try again
    $q = @mysql_query("USE `$db_name`;", $conn);
    if ( !$q )
    {
      // really failed this time; bail out
      return false;
    }
  }
  // initialize DBAL
  $db->connect(true, $_POST['db_host'], $db_user, $db_pass, $db_name);
  // connected and database exists
  return true;
}

function stg_pgsql_connect($act_get = false)
{
  global $db;
  $db = new postgresql();
  
  static $conn = false;
  if ( $act_get )
    return $conn;
  
  $db_user =& $_POST['db_user'];
  $db_pass =& $_POST['db_pass'];
  $db_name =& $_POST['db_name'];
  
  if ( !preg_match('/^[a-z0-9_-]+$/', $db_name) )
  {
    $db_name = htmlspecialchars($db_name);
    die("<p>SECURITY: malformed database name \"$db_name\"</p>");
  }
  
  // First, try to connect using the normal credentials
  $conn = @pg_connect("host={$_POST['db_host']} port=5432 user={$_POST['db_user']} password={$_POST['db_pass']}");
  if ( !$conn )
  {
    // Connection failed. Do we have the root username and password?
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @pg_connect("host={$_POST['db_host']} port=5432 user={$_POST['db_root_user']} password={$_POST['db_root_pass']}");
      if ( !$conn_root )
      {
        // Couldn't connect using either set of credentials. Bail out.
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = pg_escape_string($_POST['db_user']);
      $db_pass = pg_escape_string($_POST['db_pass']);
      // Create the user account
      $q = @pg_query("CREATE ROLE '$db_user' WITH NOSUPERUSER UNENCRYPTED PASSWORD '$db_pass';", $conn_root);
      if ( !$q )
      {
        return false;
      }
      pg_close($conn_root);
      $conn = @pg_connect("host={$_POST['db_host']} port=5432 user={$_POST['db_user']} password={$_POST['db_pass']}");
      if ( !$conn )
      {
        // This should honestly never happen.
        return false;
      }
    }
  }
  if ( !$q )
  {
    // access denied to the database; try the whole root schenanegan again
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @pg_connect("host={$_POST['db_host']} port=5432 user={$_POST['db_root_user']} password={$_POST['db_root_pass']}");
      if ( !$conn_root )
      {
        // Couldn't connect as root; bail out
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = pg_escape_string($_POST['db_user']);
      $db_pass = pg_escape_string($_POST['db_pass']);
      // create the database, if it doesn't exist
      $q = @mysql_query("CREATE DATABASE $db_name WITH OWNER $db_user;", $conn_root);
      if ( !$q )
      {
        // this really should never fail, so don't give any tolerance to it
        return false;
      }
      // Setting the owner to $db_user should grant all the rights we need
      pg_close($conn_root);
      // grant tables have hopefully been flushed, kill and reconnect our regular user connection
      pg_close($conn);
      $conn = @pg_connect("host={$_POST['db_host']} port=5432 user={$_POST['db_user']} password={$_POST['db_pass']}");
      if ( !$conn )
      {
        return false;
      }
    }
    else
    {
      return false;
    }
    // try again
    $q = @mysql_query("USE `$db_name`;", $conn);
    if ( !$q )
    {
      // really failed this time; bail out
      return false;
    }
  }
  // initialize DBAL
  $db->connect(true, $_POST['db_host'], $db_user, $db_pass, $db_name);
  // connected and database exists
  return true;
}

function stg_drop_tables()
{
  global $db;
  // Our list of tables included in Enano
  $tables = Array( 'categories', 'comments', 'config', 'logs', 'page_text', 'session_keys', 'pages', 'users', 'users_extra', 'themes', 'buddies', 'banlist', 'files', 'privmsgs', 'sidebar', 'hits', 'search_index', 'groups', 'group_members', 'acl', 'tags', 'page_groups', 'page_group_members' );
  
  // Drop each table individually; if it fails, it probably means we're trying to drop a
  // table that didn't exist in the Enano version we're deleting the database for.
  foreach ( $tables as $table )
  {
    // Remember that table_prefix is sanitized.
    $table = "{$_POST['table_prefix']}$table";
    $db->sql_query("DROP TABLE $table;", $conn);
  }
  return true;
}

function stg_decrypt_admin_pass($act_get = false)
{
  static $decrypted_pass = false;
  if ( $act_get )
    return $decrypted_pass;
  
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  
  if ( !empty($_POST['crypt_data']) )
  {
    require('config.new.php');
    if ( !isset($cryptkey) )
    {
      return false;
    }
    define('_INSTRESUME_AES_KEYBACKUP', $key);
    $key = hexdecode($cryptkey);
    
    $decrypted_pass = $aes->decrypt($_POST['crypt_data'], $key, ENC_HEX);
    
  }
  else
  {
    $decrypted_pass = $_POST['admin_pass'];
  }
  if ( empty($decrypted_pass) )
    return false;
  return true;
}

function stg_generate_aes_key($act_get = false)
{
  static $key = false;
  if ( $act_get )
    return $key;
  
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  $key = $aes->gen_readymade_key();
  return true;
}

function stg_parse_schema($act_get = false)
{
  static $schema;
  if ( $act_get )
    return $schema;
  
  global $db;
  
  $admin_pass = stg_decrypt_admin_pass(true);
  $key = stg_generate_aes_key(true);
  $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
  $key = $aes->hextostring($key);
  $admin_pass = $aes->encrypt($admin_pass, $key, ENC_HEX);
  
  $cacheonoff = is_writable(ENANO_ROOT.'/cache/') ? '1' : '0';
  
  $admin_user = $_POST['admin_user'];
  $admin_user = str_replace('_', ' ', $admin_user);
  $admin_user = $db->escape($admin_user);
  
  switch ( $_POST['db_driver'] )
  {
    case 'mysql':
      $schema_file = 'schema.sql';
      break;
    case 'postgresql':
      $schema_file = 'schema-pg.sql';
      break;
  }
  
  if ( !isset($schema_file) )
    die('insanity');
  
  $schema = file_get_contents($schema_file);
  $schema = str_replace('{{SITE_NAME}}',    $db->escape($_POST['sitename']   ), $schema);
  $schema = str_replace('{{SITE_DESC}}',    $db->escape($_POST['sitedesc']   ), $schema);
  $schema = str_replace('{{COPYRIGHT}}',    $db->escape($_POST['copyright']  ), $schema);
  $schema = str_replace('{{ADMIN_USER}}',   $admin_user                                    , $schema);
  $schema = str_replace('{{ADMIN_PASS}}',   $db->escape($admin_pass          ), $schema);
  $schema = str_replace('{{ADMIN_EMAIL}}',  $db->escape($_POST['admin_email']), $schema);
  $schema = str_replace('{{ENABLE_CACHE}}', $db->escape($cacheonoff          ), $schema);
  $schema = str_replace('{{REAL_NAME}}',    '',                                              $schema);
  $schema = str_replace('{{TABLE_PREFIX}}', $_POST['table_prefix'],                          $schema);
  $schema = str_replace('{{VERSION}}',      ENANO_VERSION,                                   $schema);
  $schema = str_replace('{{ADMIN_EMBED_PHP}}', $_POST['admin_embed_php'],                    $schema);
  // Not anymore!! :-D
  // $schema = str_replace('{{BETA_VERSION}}', ENANO_BETA_VERSION,                              $schema);
  
  if(isset($_POST['wiki_mode']))
  {
    $schema = str_replace('{{WIKI_MODE}}', '1', $schema);
  }
  else
  {
    $schema = str_replace('{{WIKI_MODE}}', '0', $schema);
  }
  
  // Build an array of queries      
  $schema = explode("\n", $schema);
  
  foreach ( $schema as $i => $sql )
  {
    $query =& $schema[$i];
    $t = trim($query);
    if ( empty($t) || preg_match('/^(\#|--)/i', $t) )
    {
      unset($schema[$i]);
      unset($query);
    }
  }
  
  $schema = array_values($schema);
  $schema = implode("\n", $schema);
  $schema = explode(";\n", $schema);
  
  foreach ( $schema as $i => $sql )
  {
    $query =& $schema[$i];
    if ( substr($query, ( strlen($query) - 1 ), 1 ) != ';' )
    {
      $query .= ';';
    }
  }
  
  return true;
}

function stg_install($_unused, $already_run)
{
  // This one's pretty easy.
  $conn = stg_mysql_connect(true);
  if ( !is_resource($conn) )
    return false;
  $schema = stg_parse_schema(true);
  if ( !is_array($schema) )
    return false;
  
  // If we're resuming installation, the encryption key was regenerated.
  // This means we'll have to update the encrypted password in the database.
  if ( $already_run )
  {
    $admin_pass = stg_decrypt_admin_pass(true);
    $key = stg_generate_aes_key(true);
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    $key = $aes->hextostring($key);
    $admin_pass = $aes->encrypt($admin_pass, $key, ENC_HEX);
    $admin_user = mysql_real_escape_string($_POST['admin_user']);
    $admin_user = str_replace('_', ' ', $admin_user);
    
    $q = @mysql_query("UPDATE {$_POST['table_prefix']}users SET password='$admin_pass' WHERE username='$admin_user';");
    if ( !$q )
    {
      echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
      return false;
    }
    
    return true;
  }
  
  // OK, do the loop, baby!!!
  foreach($schema as $q)
  {
    $r = mysql_query($q, $conn);
    if ( !$r )
    {
      echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
      return false;
    }
  }
  
  return true;
}

function stg_write_config()
{
  $privkey = stg_generate_aes_key(true);
  
  switch($_POST['urlscheme'])
  {
    case "ugly":
    default:
      $cp = scriptPath.'/index.php?title=';
      break;
    case "short":
      $cp = scriptPath.'/index.php/';
      break;
    case "tiny":
      $cp = scriptPath.'/';
      break;
  }
  
  if ( $_POST['urlscheme'] == 'tiny' )
  {
    $contents = '# Begin Enano rules
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+) '.scriptPath.'/index.php?title=$1 [L,QSA]
RewriteRule \.(php|html|gif|jpg|png|css|js)$ - [L]
# End Enano rules
';
    if ( file_exists('./.htaccess') )
      $ht = fopen(ENANO_ROOT.'/.htaccess', 'a+');
    else
      $ht = fopen(ENANO_ROOT.'/.htaccess.new', 'w');
    if ( !$ht )
      return false;
    fwrite($ht, $contents);
    fclose($ht);
  }

  $config_file = '<?php
/* Enano auto-generated configuration file - editing not recommended! */
$dbhost   = \''.addslashes($_POST['db_host']).'\';
$dbname   = \''.addslashes($_POST['db_name']).'\';
$dbuser   = \''.addslashes($_POST['db_user']).'\';
$dbpasswd = \''.addslashes($_POST['db_pass']).'\';
if ( !defined(\'ENANO_CONSTANTS\') )
{
define(\'ENANO_CONSTANTS\', \'\');
define(\'table_prefix\', \''.addslashes($_POST['table_prefix']).'\');
define(\'scriptPath\', \''.scriptPath.'\');
define(\'contentPath\', \''.$cp.'\');
define(\'ENANO_INSTALLED\', \'true\');
}
$crypto_key = \''.$privkey.'\';
?>';

  $cf_handle = fopen(ENANO_ROOT.'/config.new.php', 'w');
  if ( !$cf_handle )
    return false;
  fwrite($cf_handle, $config_file);
  
  fclose($cf_handle);
  
  return true;
}

function _stg_rename_config_revert()
{
  if ( file_exists('./config.php') )
  {
    @rename('./config.php', './config.new.php');
  }
  
  $handle = @fopen('./config.php.new', 'w');
  if ( !$handle )
    return false;
  $contents = '<?php $cryptkey = \'' . _INSTRESUME_AES_KEYBACKUP . '\'; ?>';
  fwrite($handle, $contents);
  fclose($handle);
  return true;
}

function stg_build_index()
{
  global $db, $session, $paths, $template, $plugins; // Common objects;
  if ( $paths->rebuild_search_index() )
    return true;
  return false;
}

function stg_rename_config()
{
  if ( !@rename('./config.new.php', './config.php') )
  {
    echo '<p>Can\'t rename config.php</p>';
    _stg_rename_config_revert();
    return false;
  }
  
  if ( $_POST['urlscheme'] == 'tiny' && !file_exists('./.htaccess') )
  {
    if ( !@rename('./.htaccess.new', './.htaccess') )
    {
      echo '<p>Can\'t rename .htaccess</p>';
      _stg_rename_config_revert();
      return false;
    }
  }
  return true;
}

function stg_start_api_success()
{
  return true;
}

function stg_start_api_failure()
{
  return false;
}

function stg_import_language()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $lang_file = ENANO_ROOT . "/language/english/enano.json";
  install_language("eng", "English", "English", $lang_file);
  
  return true;
}

function stg_init_logs()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $q = $db->sql_query('INSERT INTO ' . table_prefix . 'logs(log_type,action,time_id,date_string,author,page_text,edit_summary) VALUES(\'security\', \'install_enano\', ' . time() . ', \'' . date('d M Y h:i a') . '\', \'' . mysql_real_escape_string($_POST['admin_user']) . '\', \'' . mysql_real_escape_string(ENANO_VERSION) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\');');
  if ( !$q )
  {
    echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
    return false;
  }
  
  if ( !$session->get_permissions('clear_logs') )
  {
    echo '<p><tt>$session: denied clear_logs</tt></p>';
    return false;
  }
  
  PageUtils::flushlogs('Main_Page', 'Article');
  
  return true;
}

//die('Key size: ' . AES_BITS . '<br />Block size: ' . AES_BLOCKSIZE);

if(!function_exists('wikiFormat'))
{
  function wikiFormat($message, $filter_links = true)
  {
    $wiki = & Text_Wiki::singleton('Mediawiki');
    $wiki->setRenderConf('Xhtml', 'code', 'css_filename', 'codefilename');
    $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
    $result = $wiki->transform($message, 'Xhtml');
    
    // HTML fixes
    $result = preg_replace('#<tr>([\s]*?)<\/tr>#is', '', $result);
    $result = preg_replace('#<p>([\s]*?)<\/p>#is', '', $result);
    $result = preg_replace('#<br />([\s]*?)<table#is', '<table', $result);
    
    return $result;
  }
}

global $failed, $warned;

$failed = false;
$warned = false;

function not($var)
{
  if($var)
  {
    return false;
  } 
  else
  {
    return true;
  }
}

function run_test($code, $desc, $extended_desc, $warn = false)
{
  global $failed, $warned;
  static $cv = true;
  $cv = not($cv);
  $val = eval($code);
  if($val)
  {
    if($cv) $color='CCFFCC'; else $color='AAFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc</td><td style='padding-left: 10px;'><img alt='Test passed' src='images/good.gif' /></td></tr>";
  } elseif(!$val && $warn) {
    if($cv) $color='FFFFCC'; else $color='FFFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test passed with warning' src='images/unknown.gif' /></td></tr>";
    $warned = true;
  } else {
    if($cv) $color='FFCCCC'; else $color='FFAAAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test failed' src='images/bad.gif' /></td></tr>";
    $failed = true;
  }
}
function is_apache() { $r = strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') ? true : false; return $r; }

function show_license($fb = false)
{
  ?>
  <div style="height: 500px; clip: rect(0px,auto,500px,auto); overflow: auto; padding: 10px; border: 1px dashed #456798; margin: 1em;">
  <?php
    if ( !file_exists('./GPL') || !file_exists('./language/english/install/license-deed.html') )
    {
      echo 'Cannot find the license files.';
    }
    echo file_get_contents('./language/english/install/license-deed.html');
    if ( defined('ENANO_BETA_VERSION') || $branch == 'unstable' )
    {
      ?>
      <h3><?php echo $lang->get('license_info_unstable_title'); ?></h3>
      <p><?php echo $lang->get('license_info_unstable_body'); ?></p>
      <?php
    }
    ?>
    <h3><?php echo $lang->get('license_section_gpl_heading'); ?></h3>
    <?php if ( $lang->lang_code != 'eng' ): ?>
    <p><i><?php echo $lang->get('license_gpl_blurb_inenglish'); ?></i></p>
    <?php endif; ?>
    <?php echo wikiFormat(file_get_contents(ENANO_ROOT . '/GPL')); ?>
   <?php
   global $template;
   if ( $fb )
   {
     echo '<p style="text-align: center;">Because I could never find the Create a Page button in PHP-Nuke.</p>';
     echo '<p>' . str_replace('http://enanocms.org/', 'http://www.2robots.com/2003/10/15/web-portals-suck/', $template->fading_button) . '</p>';
     echo '<p style="text-align: center;">It\'s not a portal, my friends.</p>';
   }
   ?>
 </div>
 <?php
}

require_once('includes/template.php');

if(!isset($_GET['mode']))
{
  $_GET['mode'] = 'welcome';
}
switch($_GET['mode'])
{
  case 'mysql_test':
    error_reporting(0);
    $dbhost     = rawurldecode($_POST['host']);
    $dbname     = rawurldecode($_POST['name']);
    $dbuser     = rawurldecode($_POST['user']);
    $dbpass     = rawurldecode($_POST['pass']);
    $dbrootuser = rawurldecode($_POST['root_user']);
    $dbrootpass = rawurldecode($_POST['root_pass']);
    if($dbrootuser != '')
    {
      $conn = mysql_connect($dbhost, $dbrootuser, $dbrootpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('root'.$e);
      }
      $rsp = 'good';
      $q = mysql_query('USE `' . mysql_real_escape_string($dbname) . '`;', $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          $rsp .= '_creating_db';
        }
      }
      mysql_close($conn);
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          $rsp .= '_creating_user';
      }
      mysql_close($conn);
      die($rsp);
    }
    else
    {
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('auth'.$e);
      }
      $q = mysql_query('USE `' . mysql_real_escape_string($dbname) . '`;', $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          die('name'.$e);
        }
        else
        {
          die('perm'.$e);
        }
      }
    }
    $v = mysql_get_server_info();
    if(version_compare($v, '4.1.17', '<')) die('vers'.$v);
    mysql_close($conn);
    die('good');
    break;
  case 'pgsql_test':
    error_reporting(0);
    $dbhost     = rawurldecode($_POST['host']);
    $dbname     = rawurldecode($_POST['name']);
    $dbuser     = rawurldecode($_POST['user']);
    $dbpass     = rawurldecode($_POST['pass']);
    $dbrootuser = rawurldecode($_POST['root_user']);
    $dbrootpass = rawurldecode($_POST['root_pass']);
    if($dbrootuser != '')
    {
      $conn = @pg_connect("host=$dbhost port=5432 user=$dbuser password=$dbpass dbname=$dbname");
      if(!$conn)
      {
        $e = pg_last_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('root'.$e);
      }
      $rsp = 'good';
      $q = mysql_query('USE `' . mysql_real_escape_string($dbname) . '`;', $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          $rsp .= '_creating_db';
        }
      }
      mysql_close($conn);
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          $rsp .= '_creating_user';
      }
      mysql_close($conn);
      die($rsp);
    }
    else
    {
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('auth'.$e);
      }
      $q = mysql_query('USE `' . mysql_real_escape_string($dbname) . '`;', $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          die('name'.$e);
        }
        else
        {
          die('perm'.$e);
        }
      }
    }
    $v = mysql_get_server_info();
    if(version_compare($v, '4.1.17', '<')) die('vers'.$v);
    mysql_close($conn);
    die('good');
    break;  
  case 'pophelp':
    $topic = ( isset($_GET['topic']) ) ? $_GET['topic'] : 'invalid';
    switch($topic)
    {
      case 'admin_embed_php':
        $title = $lang->get('pophelp_admin_embed_php_title');
        $content = $lang->get('pophelp_admin_embed_php_body');
        break;
      case 'url_schemes':
        $title = $lang->get('pophelp_url_schemes_title');
        $content = $lang->get('pophelp_url_schemes_body');
        break;
      default:
        $title = 'Invalid topic';
        $content = 'Invalid help topic.';
        break;
    }
    $close_window = $lang->get('pophelp_btn_close_window');
    echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>Enano installation quick help &bull; {$title}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <style type="text/css">
      body {
        font-family: trebuchet ms, verdana, arial, helvetica, sans-serif;
        font-size: 9pt;
      }
      h2          { border-bottom: 1px solid #90B0D0; margin-bottom: 0; }
      h3          { font-size: 11pt; font-weight: bold; }
      li          { list-style: url(../images/bullet.gif); }
      p           { margin: 1.0em; }
      blockquote  { background-color: #F4F4F4; border: 1px dotted #406080; margin: 1em; padding: 10px; max-height: 250px; overflow: auto; }
      a           { color: #7090B0; }
      a:hover     { color: #90B0D0; }
    </style>
  </head>
  <body>
    <h2>{$title}</h2>
    {$content}
    <p style="text-align: right;">
      <a href="#" onclick="window.close(); return false;">{$close_window}</a>
    </p>
  </body>
</html>
EOF;
    exit;
    break;
  case 'langjs':
    header('Content-type: text/javascript');
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $lang_js = $json->encode($lang->strings);
    // use EEOF here because jEdit misinterprets "typ'eof'"
    echo <<<EEOF
if ( typeof(enano_lang) != 'object' )
  var enano_lang = new Object();

enano_lang[1] = $lang_js;

EEOF;
    exit;
    break;
  default:
    break;
}

$template = new template_nodb();
$template->load_theme('stpatty', 'shamrock', false);

$modestrings = Array(
              'welcome' => $lang->get('welcome_modetitle'),
              'license' => $lang->get('license_modetitle'),
              'sysreqs' => $lang->get('sysreqs_modetitle'),
              'database' => $lang->get('database_modetitle'),
              'database_mysql'=> $lang->get('database_mysql_modetitle'),
              'database_pgsql'=> $lang->get('database_pgsql_modetitle'),
              'website' => $lang->get('website_modetitle'), 
              'login'   => $lang->get('login_modetitle'),
              'confirm' => $lang->get('confirm_modetitle'),
              'install' => $lang->get('install_modetitle'),
              'finish'  => $lang->get('finish_modetitle'),
              '_hiddenstages' => '...', // all stages below this line are hidden
              'showlicense' => $lang->get('license_modetitle')
            );

$sideinfo = '';
$vars = $template->extract_vars('elements.tpl');
$p = $template->makeParserText($vars['sidebar_button']);
$hidden = false;
foreach ( $modestrings as $id => $str )
{
  if ( $_GET['mode'] == $id )
  {
    $flags = 'style="font-weight: bold; text-decoration: underline;"';
    $this_page = $str;
  }
  else
  {
    $flags = '';
  }
  if ( $id == '_hiddenstages' )
    $hidden = true;
  if ( !$hidden )
  {
    $p->assign_vars(Array(
        'HREF' => '#',
        'FLAGS' => $flags . ' onclick="return false;"',
        'TEXT' => $str
      ));
    $sideinfo .= $p->run();
  }
}

$template->init_vars();

if(isset($_GET['mode']) && $_GET['mode'] == 'css')
{
  header('Content-type: text/css');
  echo $template->get_css();
  exit;
}

if ( defined('ENANO_IS_STABLE') )
  $branch = 'stable';
else if ( defined('ENANO_IS_UNSTABLE') )
  $branch = 'unstable';
else
{
  $version = explode('.', ENANO_VERSION);
  if ( !isset($version[1]) )
    // unknown branch, really
    $branch = 'unstable';
  else
  {
    $version[1] = intval($version[1]);
    if ( $version[1] % 2 == 1 )
      $branch = 'unstable';
    else
      $branch = 'stable';
  }
}

switch($_GET['mode'])
{ 
  default:
  case 'welcome':
    ?>
    <div style="text-align: center; margin-top: 10px;">
      <img alt="[ Enano CMS Project logo ]" src="images/enano-artwork/installer-greeting-green.png" style="display: block; margin: 0 auto; padding-left: 100px;" />
      <h2><?php echo $lang->get('welcome_heading'); ?></h2>
      <h3>
        <?php
        $branch_l = $lang->get("welcome_branch_$branch");
        
        $v_string = sprintf('%s %s &ndash; %s', $lang->get('welcome_version'), ENANO_VERSION, $branch_l);
        echo $v_string;
        ?>
      </h3>
      <?php
        if ( defined('ENANO_CODE_NAME') )
        {
          echo '<p>';
          echo $lang->get('welcome_aka', array(
              'codename' => strtolower(ENANO_CODE_NAME)
            ));
          echo '</p>';
        }
      ?>
      <form action="install.php?mode=license" method="post">
        <input type="submit" value="<?php echo $lang->get('welcome_btn_start'); ?>" />
      </form>
    </div>
    <?php
    break;
  case "license":
    ?>
    <h3><?php echo $lang->get('license_heading'); ?></h3>
     <p><?php echo $lang->get('license_blurb_thankyou'); ?></p>
     <p><?php echo $lang->get('license_blurb_pleaseread'); ?></p>
     <?php show_license(); ?>
     <div class="pagenav">
       <form action="install.php?mode=sysreqs" method="post">
         <table border="0">
         <tr>
           <td>
             <input type="submit" value="<?php echo $lang->get('license_btn_i_agree'); ?>" />
           </td>
           <td>
             <p>
               <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
               &bull; <?php echo $lang->get('license_objective_ensure_agree'); ?><br />
               &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
             </p>
           </td>
         </tr>
         </table>
       </form>
     </div>
    <?php
    break;
  case "sysreqs":
    error_reporting(E_ALL);
    ?>
    <h3><?php echo $lang->get('sysreqs_heading'); ?></h3>
     <p><?php echo $lang->get('sysreqs_blurb'); ?></p>
    <table border="0" cellspacing="0" cellpadding="0">
    <?php
    run_test('return version_compare(\'4.3.0\', PHP_VERSION, \'<\');', $lang->get('sysreqs_req_php'), $lang->get('sysreqs_req_desc_php') );
    run_test('return version_compare(\'5.2.0\', PHP_VERSION, \'<\');', $lang->get('sysreqs_req_php5'), $lang->get('sysreqs_req_desc_php5'), true);
    run_test('return function_exists(\'mysql_connect\');', $lang->get('sysreqs_req_mysql'), $lang->get('sysreqs_req_desc_mysql') );
    run_test('return function_exists(\'pg_connect\');', 'PostgreSQL extension for PHP', 'It seems that your PHP installation does not have the PostgreSQL extension enabled. Because of this, you won\'t be able to use the PostgreSQL database driver. This is OK in the majority of cases. If you want to use PostgreSQL support, you\'ll need to either compile the PHP extension for Postgres or install the extension with your distribution\'s package manager. Windows administrators will need enable php_pgsql.dll in their php.ini.', true);
    run_test('return @ini_get(\'file_uploads\');', $lang->get('sysreqs_req_uploads'), $lang->get('sysreqs_req_desc_uploads') );
    run_test('return is_apache();', $lang->get('sysreqs_req_apache'), $lang->get('sysreqs_req_desc_apache'), true);
    run_test('return is_writable(ENANO_ROOT.\'/config.new.php\');', $lang->get('sysreqs_req_config'), $lang->get('sysreqs_req_desc_config') );
    run_test('return file_exists(\'/usr/bin/convert\');', $lang->get('sysreqs_req_magick'), $lang->get('sysreqs_req_desc_magick'), true);
    run_test('return is_writable(ENANO_ROOT.\'/cache/\');', $lang->get('sysreqs_req_cachewriteable'), $lang->get('sysreqs_req_desc_cachewriteable'), true);
    run_test('return is_writable(ENANO_ROOT.\'/files/\');', $lang->get('sysreqs_req_fileswriteable'), $lang->get('sysreqs_req_desc_fileswriteable'), true);
    if ( !function_exists('mysql_connect') && !function_exists('pg_connect') )
    {
      run_test('return false;', 'No database drivers are available.', 'You need to have at least one database driver working to install Enano. See the warnings on MySQL and PostgreSQL above for more information on installing these database drivers.', false);
    }
    echo '</table>';
    if(!$failed)
    {
      ?>
      
      <div class="pagenav">
      <?php
      if($warned) {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', $lang->get('sysreqs_summary_warn_title'), $lang->get('sysreqs_summary_warn_body'), true);
        echo '</table>';
      } else {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return true;', '<b>' . $lang->get('sysreqs_summary_success_title') . '</b><br />' . $lang->get('sysreqs_summary_success_body'), 'You should never see this text. Congratulations for being an Enano hacker!');
        echo '</table>';
      }
      ?>
      <form action="install.php?mode=database" method="post">
        <table border="0">
        <tr>
          <td>
            <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
          </td>
          <td>
            <p>
              <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
              &bull; <?php echo $lang->get('sysreqs_objective_scalebacks'); ?><br />
              &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
            </p>
          </td>
        </tr>
        </table>
      </form>
      </div>
    <?php
    }
    else
    {
      if ( $failed )
      {
        echo '<div class="pagenav"><table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', $lang->get('sysreqs_summary_fail_title'), $lang->get('sysreqs_summary_fail_body'));
        echo '</table></div>';
      }
    }
    ?>
    <?php
    break;
  case "database":
    echo '<h3>Choose a database driver</h3>';
    echo '<p>The next step is to choose the database driver that Enano will use. In most cases this is MySQL, but there are certain
             advantages to PostgreSQL, which is made available only experimentally.</p>';
    if ( @file_exists('/etc/enano-is-virt-appliance') )
    {
      echo '<p><b>You\'re using the Enano virtual appliance.</b><br />Unless you configured the appliance manually, PostgreSQL support is not available. In 99% of cases you\'ll want to click MySQL below.</p>';
    }
    
    $mysql_disable_reason = '';
    $pgsql_disable_reason = '';
    $mysql_disable = '';
    $pgsql_disable = '';
    if ( !function_exists('mysql_connect') )
    {
      $mysql_disable = ' disabled="disabled"';
      $mysql_disable_reason = 'You don\'t have the MySQL PHP extension installed.';
    }
    if ( !function_exists('pg_connect') )
    {
      $pgsql_disable = ' disabled="disabled"';
      $pgsql_disable_reason = 'You don\'t have the PostgreSQL PHP extensnion installed.';
    }
    if ( function_exists('pg_connect') && version_compare(PHP_VERSION, '5.0.0', '<') )
    {
      $pgsql_disable = ' disabled="disabled"';
      $pgsql_disable_reason = 'You need to have at least PHP 5 to use the PostgreSQL database driver.';
    }
    
    echo '<form action="install.php" method="get">';
    ?>
    <table border="0" cellspacing="5">
      <tr>
        <td>
          <input type="image" name="mode" value="database_mysql" src="images/about-powered-mysql.png"<?php echo $mysql_disable; ?>/>
        </td>
        <td<?php if ( $mysql_disable ) echo ' style="opacity: 0.5; filter: alpha(opacity=50);"'; ?>>
          <b>MySQL</b><br />
          Click this button to use MySQL as the database backend for your site. Most web hosts support MySQL, and if you have
          administrative access to your MySQL server, you can create a new database and user during this installation process if you
          haven't done so already.
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
          <input type="image" name="mode" value="database_pgsql" src="images/about-powered-pgsql.png"<?php echo $pgsql_disable; ?> />
        </td>
        <td<?php if ( $pgsql_disable ) echo ' style="opacity: 0.5; filter: alpha(opacity=50);"'; ?>>
          <b>PostgreSQL</b><br />
          Click this button to use PostgreSQL as the database backend for your site. While not as widely supported, PostgreSQL has more
          liberal licensing conditions and when properly configured is faster than MySQL. Some plugins may not work with the PostgreSQL
          driver.
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
    echo '</form>';
    break;
  case "database_mysql":
    ?>
    <script type="text/javascript">
      function ajaxGet(uri, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('GET', uri, true);
        ajax.send(null);
      }
      
      function ajaxPost(uri, parms, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('POST', uri, true);
        ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        ajax.setRequestHeader("Content-length", parms.length);
        ajax.setRequestHeader("Connection", "close");
        ajax.send(parms);
      }
      function ajaxTestConnection()
      {
        v = verify();
        if(!v)
        {
          alert($lang.get('meta_msg_err_verification'));
          return false;
        }
        var frm = document.forms.dbinfo;
        db_host      = escape(frm.db_host.value.replace('+', '%2B'));
        db_name      = escape(frm.db_name.value.replace('+', '%2B'));
        db_user      = escape(frm.db_user.value.replace('+', '%2B'));
        db_pass      = escape(frm.db_pass.value.replace('+', '%2B'));
        db_root_user = escape(frm.db_root_user.value.replace('+', '%2B'));
        db_root_pass = escape(frm.db_root_pass.value.replace('+', '%2B'));
        
        parms = 'host='+db_host+'&name='+db_name+'&user='+db_user+'&pass='+db_pass+'&root_user='+db_root_user+'&root_pass='+db_root_pass;
        ajaxPost('<?php echo scriptPath; ?>/install.php?mode=mysql_test', parms, function() {
            if(ajax.readyState==4)
            {
              s = ajax.responseText.substr(0, 4);
              t = ajax.responseText.substr(4, ajax.responseText.length);
              if(s.substr(0, 4)=='good')
              {
                document.getElementById('s_db_host').src='images/good.gif';
                document.getElementById('s_db_name').src='images/good.gif';
                document.getElementById('s_db_auth').src='images/good.gif';
                document.getElementById('s_db_root').src='images/good.gif';
                if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_warn_creating_db');
                if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_warn_creating_user');
                document.getElementById('s_mysql_version').src='images/good.gif';
                document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_info_mysql_good');
              }
              else
              {
                switch(s)
                {
                case 'host':
                  document.getElementById('s_db_host').src='images/bad.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_host').innerHTML = $lang.get('database_msg_err_mysql_connect', { db_host: document.forms.dbinfo.db_host.value, mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'auth':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/bad.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_err_mysql_auth', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'perm':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_err_mysql_dbperm', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'name':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_err_mysql_dbexist', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'root':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/bad.gif';
                  document.getElementById('e_db_root').innerHTML = $lang.get('database_msg_err_mysql_auth', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'vers':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/good.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/good.gif';
                  if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_warn_creating_db');
                  if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_warn_creating_user');
                  
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_err_mysql_version', { mysql_version: t });
                  document.getElementById('s_mysql_version').src='images/bad.gif';
                default:
                  alert(t);
                  break;
                }
              }
            }
          });
      }
      function verify()
      {
        document.getElementById('e_db_host').innerHTML = '';
        document.getElementById('e_db_auth').innerHTML = '';
        document.getElementById('e_db_name').innerHTML = '';
        document.getElementById('e_db_root').innerHTML = '';
        var frm = document.forms.dbinfo;
        ret = true;
        if(frm.db_host.value != '')
        {
          document.getElementById('s_db_host').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_host').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_name.value.match(/^([a-z0-9_-]+)$/g))
        {
          document.getElementById('s_db_name').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_user.value != '')
        {
          document.getElementById('s_db_auth').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_auth').src='images/bad.gif';
          ret = false;
        }
        if(frm.table_prefix.value.match(/^([a-z0-9_]*)$/g))
        {
          document.getElementById('s_table_prefix').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_table_prefix').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_root_user.value == '')
        {
          document.getElementById('s_db_root').src='images/good.gif';
        }
        else if(frm.db_root_user.value != '' && frm.db_root_pass.value == '')
        {
          document.getElementById('s_db_root').src='images/bad.gif';
          ret = false;
        }
        else
        {
          document.getElementById('s_db_root').src='images/unknown.gif';
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <p><?php echo $lang->get('database_blurb_needdb'); ?></p>
    <p><?php echo $lang->get('database_blurb_howtomysql'); ?></p>
    <?php
    if ( file_exists('/etc/enano-is-virt-appliance') )
    {
      echo '<p>
              ' . $lang->get('database_vm_login_info', array( 'host' => 'localhost', 'user' => 'enano', 'pass' => 'clurichaun', 'name' => 'enano_www1' )) . '
            </p>';
    }
    ?>
    <form name="dbinfo" action="install.php?mode=website" method="post">
      <input type="hidden" name="db_driver" value="mysql" />
      <table border="0">
        <tr>
          <td colspan="3" style="text-align: center">
            <h3><?php echo $lang->get('database_table_title'); ?></h3>
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_hostname_title'); ?></b>
            <br /><?php echo $lang->get('database_field_hostname_body'); ?>
            <br /><span style="color: #993300" id="e_db_host"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_host" size="30" type="text" />
          </td>
          <td>
            <img id="s_db_host" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_dbname_title'); ?></b><br />
            <?php echo $lang->get('database_field_dbname_body'); ?><br />
            <span style="color: #993300" id="e_db_name"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_name" size="30" type="text" />
          </td>
          <td>
            <img id="s_db_name" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td rowspan="2">
            <b><?php echo $lang->get('database_field_dbauth_title'); ?></b><br />
            <?php echo $lang->get('database_field_dbauth_body'); ?><br />
            <span style="color: #993300" id="e_db_auth"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_user" size="30" type="text" />
          </td>
          <td rowspan="2">
            <img id="s_db_auth" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <input name="db_pass" size="30" type="password" />
          </td>
        </tr>
        <tr>
          <td colspan="3" style="text-align: center">
            <h3><?php echo $lang->get('database_heading_optionalinfo'); ?></h3>
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_tableprefix_title'); ?></b><br />
            <?php echo $lang->get('database_field_tableprefix_body'); ?>
          </td>
          <td>
            <input onkeyup="verify();" name="table_prefix" size="30" type="text" />
          </td>
          <td>
            <img id="s_table_prefix" alt="Good/bad icon" src="images/good.gif" />
          </td>
        </tr>
        <tr>
          <td rowspan="2">
            <b><?php echo $lang->get('database_field_rootauth_title'); ?></b><br />
            <?php echo $lang->get('database_field_rootauth_body'); ?><br />
            <span style="color: #993300" id="e_db_root"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_root_user" size="30" type="text" />
          </td>
          <td rowspan="2">
            <img id="s_db_root" alt="Good/bad icon" src="images/good.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <input onkeyup="verify();" name="db_root_pass" size="30" type="password" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_mysqlversion_title'); ?></b>
          </td>
          <td id="e_mysql_version">
            <?php echo $lang->get('database_field_mysqlversion_blurb_willbechecked'); ?>
          </td>
          <td>
            <img id="s_mysql_version" alt="Good/bad icon" src="images/unknown.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_droptables_title'); ?></b><br />
            <?php echo $lang->get('database_field_droptables_body'); ?>
          </td>
          <td>
            <input type="checkbox" name="drop_tables" id="dtcheck" />  <label for="dtcheck"><?php echo $lang->get('database_field_droptables_lbl'); ?></label>
          </td>
        </tr>
        <tr>
          <td colspan="3" style="text-align: center">
            <input type="button" value="<?php echo $lang->get('database_btn_testconnection'); ?>" onclick="ajaxTestConnection();" />
          </td>
        </tr>
      </table>
      <div class="pagenav">
        <table border="0">
          <tr>
            <td>
              <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" onclick="return verify();" name="_cont" />
            </td>
            <td>
              <p>
                <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
                &bull; <?php echo $lang->get('database_objective_test'); ?><br />
                &bull; <?php echo $lang->get('database_objective_uncrypt'); ?>
              </p>
            </td>
          </tr>
        </table>
      </div>
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('GET', uri, true);
        ajax.send(null);
      }
      
      function ajaxPost(uri, parms, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('POST', uri, true);
        ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        ajax.setRequestHeader("Content-length", parms.length);
        ajax.setRequestHeader("Connection", "close");
        ajax.send(parms);
      }
      function ajaxTestConnection()
      {
        v = verify();
        if(!v)
        {
          alert('One or more of the form fields is incorrect. Please correct any information in the form that has an "X" next to it.');
          return false;
        }
        var frm = document.forms.dbinfo;
        db_host      = escape(frm.db_host.value.replace('+', '%2B'));
        db_name      = escape(frm.db_name.value.replace('+', '%2B'));
        db_user      = escape(frm.db_user.value.replace('+', '%2B'));
        db_pass      = escape(frm.db_pass.value.replace('+', '%2B'));
        db_root_user = escape(frm.db_root_user.value.replace('+', '%2B'));
        db_root_pass = escape(frm.db_root_pass.value.replace('+', '%2B'));
        
        parms = 'host='+db_host+'&name='+db_name+'&user='+db_user+'&pass='+db_pass+'&root_user='+db_root_user+'&root_pass='+db_root_pass;
        ajaxPost('<?php echo scriptPath; ?>/install.php?mode=pgsql_test', parms, function() {
            if(ajax.readyState==4)
            {
              s = ajax.responseText.substr(0, 4);
              t = ajax.responseText.substr(4, ajax.responseText.length);
              if(s.substr(0, 4)=='good')
              {
                document.getElementById('s_db_host').src='images/good.gif';
                document.getElementById('s_db_name').src='images/good.gif';
                document.getElementById('s_db_auth').src='images/good.gif';
                document.getElementById('s_db_root').src='images/good.gif';
                if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = '<b>Warning:<\/b> The database you specified does not exist. It will be created during installation.';
                if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = '<b>Warning:<\/b> The specified regular user does not exist or the password is incorrect. The user will be created during installation. If the user already exists, the password will be reset.';
                document.getElementById('s_mysql_version').src='images/good.gif';
                document.getElementById('e_mysql_version').innerHTML = 'Your version of PostgreSQL meets Enano requirements.';
              }
              else
              {
                switch(s)
                {
                case 'host':
                  document.getElementById('s_db_host').src='images/bad.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_host').innerHTML = '<b>Error:<\/b> The database server "'+document.forms.dbinfo.db_host.value+'" couldn\'t be contacted.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'auth':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/bad.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_auth').innerHTML = '<b>Error:<\/b> Access to MySQL under the specified credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'perm':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = '<b>Error:<\/b> Access to the specified database using those login credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'name':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = '<b>Error:<\/b> The specified database does not exist<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'root':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/bad.gif';
                  document.getElementById('e_db_root').innerHTML = '<b>Error:<\/b> Access to MySQL under the specified credentials was denied.<br \/>'+t;
                  document.getElementById('e_mysql_version').innerHTML = 'The MySQL version that your server is running could not be determined.';
                  break;
                case 'vers':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/good.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/good.gif';
                  if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = '<b>Warning:<\/b> The database you specified does not exist. It will be created during installation.';
                  if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = '<b>Warning:<\/b> The specified regular user does not exist or the password is incorrect. The user will be created during installation. If the user already exists, the password will be reset.';
                  
                  document.getElementById('e_mysql_version').innerHTML = '<b>Error:<\/b> Your version of MySQL ('+t+') is older than 4.1.17. Enano will still work, but there is a known bug with the comment system and MySQL 4.1.11 that involves some comments not being displayed, due to an issue with the PHP function mysql_fetch_row().';
                  document.getElementById('s_mysql_version').src='images/bad.gif';
                default:
                  alert(t);
                  break;
                }
              }
            }
          });
      }
      function verify()
      {
        document.getElementById('e_db_host').innerHTML = '';
        document.getElementById('e_db_auth').innerHTML = '';
        document.getElementById('e_db_name').innerHTML = '';
        document.getElementById('e_db_root').innerHTML = '';
        var frm = document.forms.dbinfo;
        ret = true;
        if(frm.db_host.value != '')
        {
          document.getElementById('s_db_host').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_host').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_name.value.match(/^([a-z0-9_-]+)$/g))
        {
          document.getElementById('s_db_name').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_user.value != '')
        {
          document.getElementById('s_db_auth').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_auth').src='images/bad.gif';
          ret = false;
        }
        if(frm.table_prefix.value.match(/^([a-z0-9_]*)$/g))
        {
          document.getElementById('s_table_prefix').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_table_prefix').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_root_user.value == '')
        {
          document.getElementById('s_db_root').src='images/good.gif';
        }
        else if(frm.db_root_user.value != '' && frm.db_root_pass.value == '')
        {
          document.getElementById('s_db_root').src='images/bad.gif';
          ret = false;
        }
        else
        {
          document.getElementById('s_db_root').src='images/unknown.gif';
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <p>Now we need some information that will allow Enano to contact your database server. Enano uses PostgreSQL as a data storage backend,
       and we need to have access to a PostgreSQL server in order to continue.</p>
    <p>If you do not have access to a PostgreSQL server, and you are using your own server, you can download PostgreSQL for free from
       <a href="http://www.postgresql.org/">PostgreSQL.org</a>.</p>
    <form name="dbinfo" action="install.php?mode=website" method="post">
      <input type="hidden" name="db_driver" value="postgresql" />
      <table border="0">
        <tr><td colspan="3" style="text-align: center"><h3>Database information</h3></td></tr>
        <tr><td><b>Database hostname</b><br />This is the hostname (or sometimes the IP address) of your Postgres server. In many cases, this is "localhost".<br /><span style="color: #993300" id="e_db_host"></span></td><td><input onkeyup="verify();" name="db_host" size="30" type="text" /></td><td><img id="s_db_host" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Database name</b><br />The name of the actual database. If you don't already have a database, you can create one here, if you have the username and password of a PostgreSQL superuser.<br /><span style="color: #993300" id="e_db_name"></span></td><td><input onkeyup="verify();" name="db_name" size="30" type="text" /></td><td><img id="s_db_name" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td rowspan="2"><b>Database login</b><br />These fields should be the username and password for a role that has permission to create and alter tables, select data, insert data, update data, and delete data. You may or may not choose to allow dropping tables.<br /><span style="color: #993300" id="e_db_auth"></span></td><td><input onkeyup="verify();" name="db_user" size="30" type="text" /></td><td rowspan="2"><img id="s_db_auth" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><input name="db_pass" size="30" type="password" /></td></tr>
        <tr><td colspan="3" style="text-align: center"><h3>Optional information</h3></td></tr>
        <tr><td><b>Table prefix</b><br />The value that you enter here will be added to the beginning of the name of each Enano table. You may use lowercase letters (a-z), numbers (0-9), and underscores (_).</td><td><input onkeyup="verify();" name="table_prefix" size="30" type="text" /></td><td><img id="s_table_prefix" alt="Good/bad icon" src="images/good.gif" /></td></tr>
        <tr><td rowspan="2"><b>Database administrative login</b><br />If the Postgres database or role that you entered above does not exist yet, you can create them here, assuming that you have the login information for a PostgreSQL superuser. Leave these fields blank unless you need to use them.<br /><span style="color: #993300" id="e_db_root"></span></td><td><input onkeyup="verify();" name="db_root_user" size="30" type="text" /></td><td rowspan="2"><img id="s_db_root" alt="Good/bad icon" src="images/good.gif" /></td></tr>
        <tr><td><input onkeyup="verify();" name="db_root_pass" size="30" type="password" /></td></tr>
        <tr><td><b>PostgreSQL version</b></td><td id="e_mysql_version">PostgreSQL version information will<br />be checked when you click "Test<br />Connection". You need to have at<br />least PostgreSQL 8.2.0 to install Enano.</td><td><img id="s_mysql_version" alt="Good/bad icon" src="images/unknown.gif" /></td></tr>
        <tr><td><b>Delete existing tables?</b><br />If this option is checked, all the tables that will be used by Enano will be dropped (deleted) before the schema is executed. Do NOT use this option unless specifically instructed to.</td><td><input type="checkbox" name="drop_tables" id="dtcheck" />  <label for="dtcheck">Drop existing tables</label></td></tr>
        <tr><td colspan="3" style="text-align: center"><input type="button" value="Test connection" onclick="ajaxTestConnection();" /></td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return verify();" name="_cont" /></td><td><p><span style="font-weight: bold;">Before clicking continue:</span><br />&bull; Check your PostgreSQL connection using the "Test Connection" button.<br />&bull; Be aware that your database information will be transmitted unencrypted several times.</p></td>
       </tr>
       </table>
     </div>
    </form>
    <?php
    break;
  case "website":
    if ( !isset($_POST['_cont']) )
    {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.siteinfo;
        ret = true;
        if(frm.sitename.value.match(/^(.+)$/g) && frm.sitename.value != 'Enano')
        {
          document.getElementById('s_name').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.sitedesc.value.match(/^(.+)$/g))
        {
          document.getElementById('s_desc').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_desc').src='images/bad.gif';
          ret = false;
        }
        if(frm.copyright.value.match(/^(.+)$/g))
        {
          document.getElementById('s_copyright').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_copyright').src='images/bad.gif';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <form name="siteinfo" action="install.php?mode=login" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p><?php echo $lang->get('website_header_blurb'); ?></p>
      <table border="0">
        <tr>
          <td>
            <b><?php echo $lang->get('website_field_name_title'); ?></b><br />
            <?php echo $lang->get('website_field_name_body'); ?>
          </td>
          <td>
            <input onkeyup="verify();" name="sitename" type="text" size="30" />
          </td>
          <td>
            <img id="s_name" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('website_field_desc_title'); ?></b><br />
            <?php echo $lang->get('website_field_desc_body'); ?>
          </td>
          <td>
            <input onkeyup="verify();" name="sitedesc" type="text" size="30" />
          </td>
          <td>
            <img id="s_desc" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('website_field_copyright_title'); ?></b><br />
            <?php echo $lang->get('website_field_copyright_body'); ?>
          </td>
          <td>
            <input onkeyup="verify();" name="copyright" type="text" size="30" />
          </td>
          <td>
            <img id="s_copyright" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('website_field_wikimode_title'); ?></b><br />
            <?php echo $lang->get('website_field_wikimode_body'); ?>
          </td>
          <td>
            <input name="wiki_mode" type="checkbox" id="wmcheck" />  <label for="wmcheck"><?php echo $lang->get('website_field_wikimode_checkbox'); ?></label>
          </td>
          <td>
            &nbsp;
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('website_field_urlscheme_title'); ?></b><br />
            <?php echo $lang->get('website_field_urlscheme_body'); ?>
          </td>
          <td colspan="2">
            <input type="radio" <?php if(!is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="ugly" id="ugly"  />  <label for="ugly"><?php echo $lang->get('website_field_urlscheme_ugly'); ?></label><br />
            <input type="radio" <?php if(is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="short" id="short" />  <label for="short"><?php echo $lang->get('website_field_urlscheme_short'); ?></label><br />
            <input type="radio" name="urlscheme" value="tiny" id="petite">  <label for="petite"><?php echo $lang->get('website_field_urlscheme_tiny'); ?></label><br />
            <small><a href="install.php?mode=pophelp&amp;topic=url_schemes" onclick="window.open(this.href, 'pophelpwin', 'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes'); return false;"><?php echo $lang->get('website_field_urlscheme_helplink'); ?></a></small>
          </td>
        </tr>
      </table>
      <div class="pagenav">
       <table border="0">
         <tr>
           <td>
             <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" onclick="return verify();" name="_cont" />
           </td>
           <td>
             <p>
               <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
               &bull; <?php echo $lang->get('website_objective_verify'); ?>
             </p>
           </td>
         </tr>
       </table>
     </div>
    </form>
    <?php
    break;
  case "login":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    require('config.new.php');
    $aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
    if ( isset($crypto_key) )
    {
      $cryptkey = $crypto_key;
    }
    if(!isset($cryptkey) || ( isset($cryptkey) && strlen($cryptkey) != AES_BITS / 4) )
    {
      $cryptkey = $aes->gen_readymade_key();
      $handle = @fopen(ENANO_ROOT.'/config.new.php', 'w');
      if(!$handle)
      {
        echo '<p>ERROR: Despite my repeated attempts to verify that the configuration file can be written, I was indeed prevented from opening it for writing. Maybe you\'re still on <del>crack</del> Windows?</p>';
        $template->footer();
        exit;
      }
      fwrite($handle, '<?php $cryptkey = \''.$cryptkey.'\'; ?>');
      fclose($handle);
    }
    // Sorry for the ugly hack, but this f***s up jEdit badly.
    echo '
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.login;
        ret = true;
        if ( frm.admin_user.value.match(/^([^<>&\?\'"%\/]+)$/) && !frm.admin_user.value.match(/^(?:(?:\\d{1,2}|1\\d\\d|2[0-4]\\d|25[0-5])\\.){3}(?:\\d{1,2}|1\\d\\d|2[0-4]\\d|25[0-5])$/) && frm.admin_user.value.toLowerCase() != \'anonymous\' )
        {
          document.getElementById(\'s_user\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_user\').src = \'images/bad.gif\';
          ret = false;
        }
        if(frm.admin_pass.value.length >= 6 && frm.admin_pass.value == frm.admin_pass_confirm.value)
        {
          document.getElementById(\'s_password\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_password\').src = \'images/bad.gif\';
          ret = false;
        }
        if(frm.admin_email.value.match(/^(?:[\\w\\d_-]+\\.?)+@(?:(?:[\\w\\d-]\\-?)+\\.)+\\w{2,4}$/))
        {
          document.getElementById(\'s_email\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_email\').src = \'images/bad.gif\';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
      
      function cryptdata() 
      {
        if(!verify()) return false;
      }
    </script>
    ';
    ?>
    <form name="login" action="install.php?mode=confirm" method="post" onsubmit="runEncryption();">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p><?php echo $lang->get('login_header_blurb'); ?></p>
      <table border="0">
        <tr>
          <td><b><?php echo $lang->get('login_field_username_title'); ?></b><br /><small><?php echo $lang->get('login_field_username_body'); ?></small></td>
          <td><input onkeyup="verify();" name="admin_user" type="text" size="30" /></td>
          <td><img id="s_user" alt="Good/bad icon" src="images/bad.gif" /></td>
        </tr>
        <tr>
          <td><?php echo $lang->get('login_field_password_title'); ?></td>
          <td><input onkeyup="verify();" name="admin_pass" type="password" size="30" /></td>
          <td rowspan="2"><img id="s_password" alt="Good/bad icon" src="images/bad.gif" /></td>
        </tr>
        <tr>
          <td><?php echo $lang->get('login_field_password_confirm'); ?></td>
          <td><input onkeyup="verify();" name="admin_pass_confirm" type="password" size="30" /></td>
        </tr>
        <tr>
          <td><?php echo $lang->get('login_field_email_title'); ?></td>
          <td><input onkeyup="verify();" name="admin_email" type="text" size="30" /></td>
          <td><img id="s_email" alt="Good/bad icon" src="images/bad.gif" /></td>
        </tr>
        <tr>
          <td>
            <?php echo $lang->get('login_field_allowphp_title'); ?><br />
            <small>
              <span style="color: #D84308">
                <?php
                  echo $lang->get('login_field_allowphp_body',
                    array(
                      'important_notes' => '<a href="install.php?mode=pophelp&amp;topic=admin_embed_php" onclick="window.open(this.href, \'pophelpwin\', \'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes\'); return false;" style="color: #D84308; text-decoration: underline;">' . $lang->get('login_field_allowphp_isi') . '</a>'
                      )
                    );
                ?>
              </span>
            </small>
          </td>
          <td>
            <label><input type="radio" name="admin_embed_php" value="2" checked="checked" /> <?php echo $lang->get('login_field_allowphp_disabled'); ?></label>&nbsp;&nbsp;
            <label><input type="radio" name="admin_embed_php" value="4" /> <?php echo $lang->get('login_field_allowphp_enabled'); ?></label>
          </td>
          <td></td>
        </tr>
        <tr><td colspan="3"><?php echo $lang->get('login_aes_blurb'); ?></td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
         <tr>
           <td>
             <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" onclick="return cryptdata();" name="_cont" />
           </td>
           <td>
             <p>
               <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
               &bull; <?php echo $lang->get('login_objective_remember'); ?>
             </p>
           </td>
         </tr>
       </table>
      </div>
      <div id="cryptdebug"></div>
      <input type="hidden" name="use_crypt" value="no" />
      <input type="hidden" name="crypt_key" value="<?php echo $cryptkey; ?>" />
      <input type="hidden" name="crypt_data" value="" />
    </form>
    <script type="text/javascript">
    // <![CDATA[
      var frm = document.forms.login;
      frm.admin_user.focus();
      function runEncryption()
      {
        str = '';
        for(i=0;i<keySizeInBits/4;i++) str+='0';
        var key = hexToByteArray(str);
        var pt = hexToByteArray(str);
        var ct = rijndaelEncrypt(pt, key, "ECB");
        var ect = byteArrayToHex(ct);
        switch(keySizeInBits)
        {
          case 128:
            v = '66e94bd4ef8a2c3b884cfa59ca342b2e';
            break;
          case 192:
            v = 'aae06992acbf52a3e8f4a96ec9300bd7aae06992acbf52a3e8f4a96ec9300bd7';
            break;
          case 256:
            v = 'dc95c078a2408989ad48a21492842087dc95c078a2408989ad48a21492842087';
            break;
        }
        var testpassed = ( ect == v && md5_vm_test() );
        var frm = document.forms.login;
        if(testpassed)
        {
          // alert('encryption self-test passed');
          frm.use_crypt.value = 'yes';
          var cryptkey = frm.crypt_key.value;
          frm.crypt_key.value = '';
          if(cryptkey != byteArrayToHex(hexToByteArray(cryptkey)))
          {
            alert('Byte array conversion SUCKS');
            testpassed = false;
          }
          cryptkey = hexToByteArray(cryptkey);
          if(!cryptkey || ( ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ) && cryptkey.length != keySizeInBits / 8 )
          {
            frm._cont.disabled = true;
            len = ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ? '\nLen: '+cryptkey.length : '';
            alert('The key is messed up\nType: '+typeof(cryptkey)+len);
          }
        }
        else
        {
          // alert('encryption self-test FAILED');
        }
        if(testpassed)
        {
          pass = frm.admin_pass.value;
          pass = stringToByteArray(pass);
          cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
          //decrypted = rijndaelDecrypt(cryptstring, cryptkey, 'ECB');
          //decrypted = byteArrayToString(decrypted);
          //return false;
          if(!cryptstring)
          {
            return false;
          }
          cryptstring = byteArrayToHex(cryptstring);
          // document.getElementById('cryptdebug').innerHTML = '<pre>Data: '+cryptstring+'<br />Key:  '+byteArrayToHex(cryptkey)+'</pre>';
          frm.crypt_data.value = cryptstring;
          frm.admin_pass.value = '';
          frm.admin_pass_confirm.value = '';
        }
        return false;
      }
      // ]]>
    </script>
    <?php
    break;
  case "confirm":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=sysreqs">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <form name="confirm" action="install.php?mode=install" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <h3><?php echo $lang->get('confirm_header_blurb_title'); ?></h3>
       <p><?php echo $lang->get('confirm_header_blurb_body'); ?></p>
      <ul>
        <li><?php echo $lang->get('confirm_lbl_db_host'); ?> <?php echo $_POST['db_host']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_db_name'); ?> <?php echo $_POST['db_name']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_db_user'); ?> <?php echo $_POST['db_user']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_db_pass'); ?></li>
        <li><?php echo $lang->get('confirm_lbl_sitename'); ?> <?php echo $_POST['sitename']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_sitedesc'); ?> <?php echo $_POST['sitedesc']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_adminuser'); ?> <?php echo $_POST['admin_user']; ?></li>
        <li><?php echo $lang->get('confirm_lbl_aesbits'); ?> <?php echo $lang->get('confirm_lbl_aes_strength', array( 'aes_bits' => AES_BITS )); ?><br /><small><?php echo $lang->get('confirm_lbl_aes_change'); ?></small></li>
      </ul>
      <div class="pagenav">
        <table border="0">
          <tr>
            <td>
              <input type="submit" value="<?php echo $lang->get('confirm_btn_install_enano'); ?>" name="_cont" />
            </td>
            <td>
              <p>
                <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
                <!-- Like this even needs to be localized. :-P -->
                &bull; <?php echo $lang->get('confirm_objective_pray'); ?>
              </p>
            </td>
          </tr>
        </table>
      </div>
    </form>
    <?php
    break;
  case "install":
    if(!isset($_POST['db_host']) ||
       !isset($_POST['db_name']) ||
       !isset($_POST['db_user']) ||
       !isset($_POST['db_pass']) ||
       !isset($_POST['db_driver']) ||
       !isset($_POST['sitename']) ||
       !isset($_POST['sitedesc']) ||
       !isset($_POST['copyright']) ||
       !isset($_POST['admin_user']) ||
       !isset($_POST['admin_pass']) ||
       !isset($_POST['admin_embed_php']) || ( isset($_POST['admin_embed_php']) && !in_array($_POST['admin_embed_php'], array('2', '4')) ) ||
       !isset($_POST['urlscheme'])
       )
    {
      echo 'The installer has detected that one or more required form values is not set. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    if ( !in_array($_POST['db_driver'], array('mysql', 'postgresql')) )
    {
      echo 'Invalid database driver.';
      $template->footer();
      exit;
    }
    switch($_POST['urlscheme'])
    {
      case "ugly":
      default:
        $cp = scriptPath.'/index.php?title=';
        break;
      case "short":
        $cp = scriptPath.'/index.php/';
        break;
      case "tiny":
        $cp = scriptPath.'/';
        break;
    }
    function err($t) { global $template; echo $t; $template->footer(); exit; }
    
    // $stages = array('connect', 'decrypt', 'genkey', 'parse', 'sql', 'writeconfig', 'renameconfig', 'startapi', 'initlogs');
    
    if ( !preg_match('/^[a-z0-9_-]*$/', $_POST['table_prefix']) )
      err('Hacking attempt was detected in table_prefix.');
    
      start_install_table();
      
      // Are we just trying to auto-rename the config files? If so, skip everything else
      if ( !isset($_GET['stage']) || ( isset($_GET['stage']) && $_GET['stage'] != 'renameconfig' ) )
      {
      
        // The stages connect, decrypt, genkey, and parse are preprocessing and don't do any actual data modification.
        // Thus, they need to be run on each retry, e.g. never skipped.
        run_installer_stage('connect', $lang->get('install_stg_connect_title'), 'stg_mysql_connect', $lang->get('install_stg_connect_body'), false);
        if ( isset($_POST['drop_tables']) )
        {
          // Are we supposed to drop any existing tables? If so, do it now
          run_installer_stage('drop', $lang->get('install_stg_drop_title'), 'stg_drop_tables', 'This step never returns failure');
        }
        run_installer_stage('decrypt', $lang->get('install_stg_decrypt_title'), 'stg_decrypt_admin_pass', $lang->get('install_stg_decrypt_body'), false);
        run_installer_stage('genkey', $lang->get('install_stg_genkey_title', array( 'aes_bits' => AES_BITS )), 'stg_generate_aes_key', $lang->get('install_stg_genkey_body'), false);
        run_installer_stage('parse', $lang->get('install_stg_parse_title'), 'stg_parse_schema', $lang->get('install_stg_parse_body'), false);
        run_installer_stage('sql', $lang->get('install_stg_sql_title'), 'stg_install', $lang->get('install_stg_sql_body'), false);
        run_installer_stage('writeconfig', $lang->get('install_stg_writeconfig_title'), 'stg_write_config', $lang->get('install_stg_writeconfig_body'));
        
        // Mainstream installation complete - Enano should be usable now
        // The stage of starting the API is special because it has to be called out of function context.
        // To alleviate this, we have two functions, one that returns success and one that returns failure
        // If the Enano API load is successful, the success function is called to report the action to the user
        // If unsuccessful, the failure report is sent
        
        $template_bak = $template;
        
        $_GET['title'] = 'Main_Page';
        require('includes/common.php');
        
        if ( is_object($db) && is_object($session) )
        {
          run_installer_stage('startapi', $lang->get('install_stg_startapi_title'), 'stg_start_api_success', '...', false);
        }
        else
        {
          run_installer_stage('startapi', $lang->get('install_stg_startapi_title'), 'stg_start_api_failure', $lang->get('install_stg_startapi_body'), false);
        }
        
        // We need to be logged in (with admin rights) before logs can be flushed
        $admin_password = stg_decrypt_admin_pass(true);
        $session->login_without_crypto($_POST['admin_user'], $admin_password, false);
        
        // Now that login cookies are set, initialize the session manager and ACLs
        $session->start();
        $paths->init();
        
        run_installer_stage('importlang', $lang->get('install_stg_importlang_title'), 'stg_import_language', $lang->get('install_stg_importlang_body'));
        run_installer_stage('initlogs', $lang->get('install_stg_initlogs_title'), 'stg_init_logs', $lang->get('install_stg_initlogs_body'));
        
        run_installer_stage('buildindex', 'Initialize search index', 'stg_build_index', 'Something went wrong while the page manager was attempting to build a search index.');
        
        /*
         * HACKERS:
         * If you're making a custom distribution of Enano, put all your custom plugin-related code here.
         * You have access to the full Enano API as well as being logged in with complete admin rights.
         * Don't do anything horrendously fancy here, unless you add a new stage (or more than one) and
         * have the progress printed out properly.
         */
        
      } // check for stage == renameconfig
      else
      {
        // If we did skip the main installer routine, set $template_bak to make the reversal later work properly
        $template_bak = $template;
      }

      // Final step is to rename the config file
      // In early revisions of 1.0.2, this step was performed prior to the initialization of the Enano API. It was decided to move
      // this stage to the end because it will fail more often than any other stage, thus making alternate routes imperative. If this
      // stage fails, then no big deal, we'll just have the user rename the files manually and then let them see the pretty success message.
      run_installer_stage('renameconfig', $lang->get('install_stg_rename_title'), 'stg_rename_config', $lang->get('install_stg_rename_body'));
      
      close_install_table();
      
      unset($template);
      $template =& $template_bak;
    
      echo '<h3>' . $lang->get('install_msg_complete_title') . '</h3>';
      echo '<p>' . $lang->get('install_msg_complete_body', array('finish_link' => 'install.php?mode=finish')) . '</p>';
      
      // echo '<script type="text/javascript">window.location="'.scriptPath.'/install.php?mode=finish";</script>';
      
    break;
  case "finish":
    echo '<h3>' . $lang->get('finish_msg_congratulations') . '</h3>
           ' . $lang->get('finish_body') . '
           <p>' . $lang->get('finish_link_mainpage', array('mainpage_link' => 'index.php')) . '</p>';
    break;
  // this stage is never shown during the installation, but is provided for legal purposes
  case "showlicense":
    show_license(true);
    break;
}
$template->footer();
 
?>
