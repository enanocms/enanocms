<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * cli-core.php - CLI installation wizard/core
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 * 
 * Thanks to Stephan for helping out with l10n in the installer (his work is in includes/stages/*.php).
 */

require(dirname(__FILE__) . '/common.php');
if ( !defined('ENANO_CLI') )
{
  $ui = new Enano_Installer_UI('Enano installation', false);
  $ui->set_visible_stage($ui->add_stage('Error', true));

  $ui->step = 'Access denied';  
  $ui->show_header();
  echo '<h2>CLI only</h2>
        <p>This script must be run from the command line.</p>';
  $ui->show_footer();
  exit;
}

if ( defined('ENANO_INSTALLED') )
{
  // start up the API to let it error out if something's wrong
  require(ENANO_ROOT . '/includes/common.php');
  
  installer_fail('Enano is already installed. Uninstall it by deleting config.php and creating a blank file called config.new.php.');
}

// parse command line args
foreach ( array('silent', 'driver', 'dbhost', 'dbport', 'dbuser', 'dbpasswd', 'dbname', 'db_prefix', 'user', 'pass', 'email', 'sitename', 'sitedesc', 'copyright', 'urlscheme', 'lang_id', 'scriptpath') as $var )
{
  if ( !isset($$var) )
  {
    $$var = false;
  }
}

for ( $i = 1; $i < count($argv); $i++ )
{
  switch($argv[$i])
  {
    case '-q':
      $silent = true;
      break;
    case '--db-driver':
    case '-b':
      $driver = @$argv[++$i];
      break;
    case '--db-host':
    case '-h':
      $dbhost = @$argv[++$i];
      break;
    case '--db-port':
    case '-o':
      $dbport = @$argv[++$i];
      break;
    case '--db-user':
    case '-u':
      $dbuser = @$argv[++$i];
      break;
    case '--db-pass':
    case '-p':
      $dbpasswd = @$argv[++$i];
      break;
    case '--db-name':
    case '-d':
      $dbname = @$argv[++$i];
      break;
    case '--table-prefix':
    case '-t':
      $db_prefix = @$argv[++$i];
      break;
    case '--admin-user':
    case '-a':
      $user = @$argv[++$i];
      break;
    case '--admin-pass':
    case '-w':
      $pass = @$argv[++$i];
      break;
    case '--admin-email':
    case '-e':
      $email = @$argv[++$i];
      break;
    case '--site-name':
    case '-n':
      $sitename = @$argv[++$i];
      break;
    case '--site-desc':
    case '-s':
      $sitedesc = @$argv[++$i];
      break;
    case '--copyright':
    case '-c':
      $copyright = @$argv[++$i];
      break;
    case '--url-scheme':
    case '-r':
      $urlscheme_temp = @$argv[++$i];
      if ( in_array($urlscheme_temp, array('standard', 'short', 'rewrite')) )
        $urlscheme = $urlscheme_temp;
      break;
    case '--language':
    case '-l':
      $lang_id = @$argv[++$i];
      break;
    case '-i':
    case '--scriptpath':
      $scriptpath = @$argv[++$i];
      break;
    default:
      $vers = installer_enano_version();
      echo <<<EOF
Enano CMS v$vers - CLI Installer
Usage: {$argv[0]} [-q] [-b driver] [-h host] [-u username] [-p password]
                  [-d database] [-a adminuser] [-w adminpass] [-e email]
All arguments are optional; missing information will be prompted for.
  -q                Quiet mode (minimal output)
  -b, --db-driver   Database driver (mysql or postgresql)
  -h, --db-host     Hostname of database server
  -o, --db-port     TCP port on which to connect to database server
  -u, --db-user     Username to use on database server
  -p, --db-pass     Password to use on database server
  -d, --db-name     Name of database
  -a, --admin-user  Administrator username
  -w, --admin-pass  Administrator password
  -e, --admin-email Administrator e-mail address
  -n, --site-name   Name of site
  -s, --site-desc   *SHORT* Description of site
  -c, --copyright   Copyright notice shown on pages
  -r, --url-scheme  URL scheme (standard, short, rewrite, or tiny)
  -l, --language    Language to be used on site and in installer
  -i, --scriptpath  Where Enano is relative to your website root (no trailing
                    slash)


EOF;
      exit(1);
      break;
  }
}

if ( $silent )
{
  define('ENANO_LIBINSTALL_SILENT', '');
}

##
## PHP VERSION CHECK
##

if ( version_compare(PHP_VERSION, '5.0.0', '<' ) )
{
  if ( !$silent )
  {
    echo "\x1B[1mWelcome to the \x1B[34mEnano\x1B[0m CMS\x1B[1m installation wizard.\x1B[0m\n";
    echo "Installing Enano version \x1B[1m" . installer_enano_version() . "\x1B[0m on PHP " . PHP_VERSION . "\n";
  }
  installer_fail('Your version of PHP (' . PHP_VERSION . ') doesn\'t meet Enano requirements (5.0.0)');
}

##
## LANGUAGE STARTUP
##

// Include language lib and additional PHP5-only JSON functions
require_once( ENANO_ROOT . '/includes/json2.php' );
require_once( ENANO_ROOT . '/includes/lang.php' );

// Determine language ID to use
$langids = array_keys($languages);
if ( $silent )
{
  if ( !in_array($lang_id, $langids ) )
    $lang_id = $langids[0];
}
else if ( !in_array($lang_id, $langids) )
{
  echo "\x1B[1mPlease select a language.\x1B[0m\n";
  echo "\x1B[32mAvailable languages:\x1B[0m\n";
  foreach ( $languages as $id => $metadata )
  {
    $id_spaced = $id;
    while ( strlen($id_spaced) < 10 )
      $id_spaced = "$id_spaced ";
    echo "  \x1B[1;34m$id_spaced\x1B[0m {$metadata['name']} ({$metadata['name_eng']})\n";
  }
  while ( !in_array($lang_id, $langids) )
  {
    $lang_id = cli_prompt('Language: ', $langids[0]);
  }
}

// We have a language ID - init language
$language_dir = $languages[$lang_id]['dir'];

// Initialize language support
$lang = new Language($lang_id);
$lang->load_file(ENANO_ROOT . '/language/' . $language_dir . '/install.json');

##
## WELCOME MESSAGE
##

if ( !$silent )
{
  echo parse_shellcolor_string($lang->get('cli_welcome_line1'));
  echo parse_shellcolor_string($lang->get('cli_welcome_line2', array('enano_version' => installer_enano_version(), 'php_version' => PHP_VERSION)));
}

$defaults = array(
  'driver'  => 'mysql',
  'dbhost'    => 'localhost',
  'dbport'    => 3306,
  'dbuser'    => false,
  'dbpasswd'  => false,
  'dbname'    => false,
  'db_prefix'    => '',
  'user'      => 'admin',
  'pass'      => false,
  'email'     => false,
  'sitename'  => $lang->get('cli_default_site_name'),
  'sitedesc'  => $lang->get('cli_default_site_desc'),
  'copyright' => $lang->get('cli_default_copyright', array('year' => date('Y'))),
  'urlscheme' => 'standard',
  'scriptpath'=> '/enano'
);

$terms = array(
  'driver'  => $lang->get('cli_prompt_driver'),
  'dbhost'    => $lang->get('cli_prompt_dbhost'),
  'dbport'    => $lang->get('cli_prompt_dbport'),
  'dbuser'    => $lang->get('cli_prompt_dbuser'),
  'dbpasswd'  => $lang->get('cli_prompt_dbpasswd'),
  'dbname'    => $lang->get('cli_prompt_dbname'),
  'db_prefix'    => $lang->get('cli_prompt_db_prefix'),
  'user'      => $lang->get('cli_prompt_user'),
  'pass'      => $lang->get('cli_prompt_pass'),
  'email'     => $lang->get('cli_prompt_email'),
  'sitename'  => $lang->get('cli_prompt_sitename'),
  'sitedesc'  => $lang->get('cli_prompt_sitedesc'),
  'copyright' => $lang->get('cli_prompt_copyright'),
  'urlscheme' => $lang->get('cli_prompt_urlscheme'),
  'scriptpath'=> $lang->get('cli_prompt_scriptpath')
);

$defaults['dbport'] = ( strtolower($driver) == 'postgresql' ) ? 5432 : 3306;

foreach ( array('driver', 'dbhost', 'dbport', 'dbuser', 'dbpasswd', 'dbname', 'db_prefix', 'scriptpath', 'user', 'pass', 'email', 'sitename', 'sitedesc', 'copyright', 'urlscheme') as $var )
{
  if ( empty($$var) )
  {
    switch($var)
    {
      default:
        $$var = cli_prompt($terms[$var], $defaults[$var]);
        break;
      case 'driver':
        $$var = cli_prompt($terms[$var], $defaults[$var]);
        $defaults['dbport'] = ( strtolower($driver) == 'postgresql' ) ? 5432 : 3306;
        break;
      case 'pass':
      case 'dbpasswd':
        if ( @file_exists('/bin/stty') && @is_executable('/bin/stty') )
        {
          exec('/bin/stty -echo');
          while ( true )
          {
            $$var = cli_prompt($terms[$var], $defaults[$var]);
            echo "\n";
            $confirm = cli_prompt($lang->get('cli_prompt_confirm'), $defaults[$var]);
            echo "\n";
            if ( $$var === $confirm )
              break;
            else
              echo parse_shellcolor_string($lang->get('cli_err_pass_no_match'));
          }
          exec('/bin/stty echo');
        }
        else
        {
          $$var = cli_prompt("{$terms[$var]} " . $lang->get('cli_msg_echo_warning'), $defaults[$var]);
        }
        break;
      case 'urlscheme':
        $temp = '';
        while ( !in_array($temp, array('standard', 'short', 'rewrite', 'tiny')) )
        {
          $temp = cli_prompt($terms[$var], $defaults[$var]);
        }
        $$var = $temp;
        break;
      case 'db_prefix':
        while ( !preg_match('/^[a-z0-9_]*$/', $$var) )
        {
          $$var = cli_prompt($terms[$var], $defaults[$var]);
        }
        break;
      case 'dbport':
        $$var = cli_prompt($terms[$var], strval($defaults[$var]));
        while ( !preg_match('/^[0-9]*$/', $$var) )
        {
          $$var = cli_prompt($terms[$var], $defaults[$var]);
        }
        $$var = intval($$var);
        break;
    }
  }
}

##
## DB TEST
##

require( ENANO_ROOT . '/includes/dbal.php' );
require( ENANO_ROOT . '/includes/sql_parse.php' );
$dbal = new $driver();

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_testing_db'));

$result = $dbal->connect(true, $dbhost, $dbuser, $dbpasswd, $dbname, $dbport);
if ( !$result )
{
  if ( !$silent )
  {
    echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
    echo "[$driver] " . $dbal->sql_error() . "\n";
  }
  installer_fail($lang->get('cli_err_db_connect_fail'));
}

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";

##
## SERVER REQUIREMENTS
##

if ( !$silent )
{
  echo parse_shellcolor_string($lang->get('cli_stage_sysreqs'));
}

$failed = false;
$warnings = array();

// Test: PHP
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_php') . ': ';
if ( version_compare(PHP_VERSION, '5.2.0', '>=') )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
}
else if ( version_compare(PHP_VERSION, '5.0.0', '>=') )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_vwarn')) . "\n";
  $warnings[] = $lang->get('sysreqs_req_help_php', array('php_version' => PHP_VERSION));
}
else
{
  $failed = true;
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
}

// Test: MySQL
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_mysql') . ': ';
$req_mysql = function_exists('mysql_connect');
if ( $req_mysql )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
  $have_dbms = true;
}
else
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
}

// Test: PostgreSQL
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_postgresql') . ': ';
$req_pgsql = function_exists('pg_connect');
if ( $req_pgsql )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
  $have_dbms = true;
}
else
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
}

if ( !$have_dbms )
  $failed = true;

// Test: Safe Mode
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_safemode') . ': ';
$req_safemode = !intval(@ini_get('safe_mode'));
if ( !$req_safemode )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
  $warnings[] = $lang->get('sysreqs_req_help_safemode');
  $failed = true;
}
else
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
}

// Test: File uploads
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_uploads') . ': ';
$req_uploads = intval(@ini_get('file_uploads'));
if ( $req_uploads )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
}
else
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_warn')) . "\n";
}

// Test: ctype validation
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_ctype') . ': ';
$req_ctype = function_exists('ctype_digit');
if ( $req_ctype )
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_pass')) . "\n";
}
else
{
  if ( !$silent ) echo parse_shellcolor_string($lang->get('cli_test_fail')) . "\n";
  $failed = true;
}

// Write tests
$req_config_w = write_test('config.new.php');
$req_htaccess_w = write_test('.htaccess.new');
$req_files_w = write_test('files');
$req_cache_w = write_test('cache');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_config_writable') . ': ' . parse_shellcolor_string($lang->get($req_config_w ? 'cli_test_pass' : 'cli_test_fail')) . "\n";
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_htaccess_writable') . ': ' . parse_shellcolor_string($lang->get($req_htaccess_w ? 'cli_test_pass' : 'cli_test_warn')) . "\n";
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_files_writable') . ': ' . parse_shellcolor_string($lang->get($req_files_w ? 'cli_test_pass' : 'cli_test_warn')) . "\n";
if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_cache_writable') . ': ' . parse_shellcolor_string($lang->get($req_cache_w ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

if ( !$req_config_w || !$req_htaccess_w || !$req_files_w || !$req_cache_w )
  $warnings[] = $lang->get('sysreqs_req_help_writable');

if ( !$req_config_w )
  $failed = true;
      
// Extension test: GD
$req_gd = function_exists('imagecreatefrompng') && function_exists('getimagesize') && function_exists('imagecreatetruecolor') && function_exists('imagecopyresampled');
if ( !$req_gd )
  $warnings[] = $lang->get('sysreqs_req_help_gd2');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_gd2') . ': ' . parse_shellcolor_string($lang->get($req_gd ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

// FS test: ImageMagick
$req_imagick = which('convert');
if ( !$req_imagick )
  $warnings[] = $lang->get('sysreqs_req_help_imagemagick');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_imagemagick') . ': ' . parse_shellcolor_string($lang->get($req_imagick ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

// Extension test: GMP
$req_gmp = function_exists('gmp_init');
if ( !$req_gmp )
  $warnings[] = $lang->get('sysreqs_req_help_gmp');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_gmp') . ': ' . parse_shellcolor_string($lang->get($req_gmp ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

// Extension test: Big_Int
$req_bigint = function_exists('bi_from_str');
if ( !$req_bigint && !$req_gmp )
  $warnings[] = $lang->get('sysreqs_req_help_bigint');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_bigint') . ': ' . parse_shellcolor_string($lang->get($req_bigint ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

// Extension test: BCMath
$req_bcmath = function_exists('bcadd');
if ( !$req_bcmath && !$req_bigint && !$req_gmp )
  $warnings[] = $lang->get('sysreqs_req_help_bcmath');

if ( !$silent ) echo '  ' . $lang->get('sysreqs_req_bcmath') . ': ' . parse_shellcolor_string($lang->get($req_bcmath ? 'cli_test_pass' : 'cli_test_warn')) . "\n";

if ( !empty($warnings) && !$silent )
{
  echo parse_shellcolor_string($lang->get('cli_msg_test_warnings')) . "\n";
  echo "  " . implode("\n  ", $warnings) . "\n";
}

if ( !function_exists('mysql_connect') && !function_exists('pg_connect') )
{
  installer_fail($lang->get('cli_err_no_drivers'));
}
if ( $failed )
{
  installer_fail($lang->get('cli_err_sysreqs_fail'));
}

##
## STAGE 1 INSTALLATION
##

if ( !$silent )
{
  echo parse_shellcolor_string($lang->get('cli_msg_tests_passed'));
  echo parse_shellcolor_string($lang->get('cli_msg_installing_db_stage1'));
}

// Create the config table
try
{
  $sql_parser = new SQL_Parser( ENANO_ROOT . "/install/schemas/{$driver}_stage1.sql" );
}
catch ( Exception $e )
{
  if ( !$silent )
    echo "\n";
  installer_fail($lang->get('cli_err_schema_load'));
}
// Check to see if the config table already exists
$q = $dbal->sql_query('SELECT config_name, config_value FROM ' . $db_prefix . 'config LIMIT 1;');
if ( !$q )
{
  $sql_parser->assign_vars(array(
      'TABLE_PREFIX' => $db_prefix
    ));
  $sql = $sql_parser->parse();
  foreach ( $sql as $q )
  {
    if ( !$dbal->sql_query($q) )
    {
      if ( !$silent )
        echo "\n";
      echo "[$driver] " . $dbal->sql_error() . "\n";
      installer_fail($lang->get('cli_err_db_query'));
    }
  }
}
else
{
  $dbal->free_result();
  if ( !$dbal->sql_query('DELETE FROM ' . $db_prefix . 'config WHERE config_name = \'install_aes_key\';') )
  {
    if ( !$silent )
      echo "\n";
    echo "[$driver] " . $dbal->sql_error() . "\n";
    installer_fail($lang->get('cli_err_db_query'));
  }
}

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_ok')) . "\n";

define('table_prefix', $db_prefix);

##
## STAGE 2 INSTALLATION
##

$db =& $dbal;
$dbdriver =& $driver;

// Yes, I am predicting the future here. Because I have that kind of power.
$_SERVER['REMOTE_ADDR'] = ( intval(date('Y')) >= 2011 ) ? '::1' : '127.0.0.1';

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_parsing_schema'));

require_once( ENANO_ROOT . '/includes/rijndael.php' );
require_once( ENANO_ROOT . '/includes/hmac.php' );

$aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
$hmac_secret = hexencode(AESCrypt::randkey(20), '', '');

$admin_pass_clean =& $pass;
$admin_pass = hmac_sha1($admin_pass_clean, $hmac_secret);

unset($admin_pass_clean); // Security

try
{
  $sql_parser = new SQL_Parser( ENANO_ROOT . "/install/schemas/{$dbdriver}_stage2.sql" );
}
catch ( Exception $e )
{
  if ( !$silent )
    echo "\n";
  installer_fail($lang->get('cli_err_schema_load'));
}

$vars = array(
    'TABLE_PREFIX'         => table_prefix,
    'SITE_NAME'            => $db->escape($sitename),
    'SITE_DESC'            => $db->escape($sitedesc),
    'COPYRIGHT'            => $db->escape($copyright),
    'WIKI_MODE'            => '0',
    'ENABLE_CACHE'         => ( is_writable( ENANO_ROOT . '/cache/' ) ? '1' : '0' ),
    'VERSION'              => installer_enano_version(),
    'ADMIN_USER'           => $db->escape($user),
    'ADMIN_PASS'           => $admin_pass,
    'ADMIN_PASS_SALT'      => $hmac_secret,
    'ADMIN_EMAIL'          => $db->escape($email),
    'REAL_NAME'            => '', // This has always been stubbed.
    'ADMIN_EMBED_PHP'      => strval(AUTH_DISALLOW),
    'UNIX_TIME'            => strval(time()),
    'IP_ADDRESS'           => $_SERVER['REMOTE_ADDR']
  );

$sql_parser->assign_vars($vars);
$schema = $sql_parser->parse();

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_ok')) . "\n";

##
## PAYLOAD DELIVERY
##

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_installing_db_stage2'));

foreach ( $schema as $sql )
{
  if ( !$db->check_query($sql) )
  {
    if ( !$silent )
      echo "\n";
    installer_fail($lang->get('cli_err_query_sanity_failed'));
  }
}

foreach ( $schema as $sql )
{
  if ( !$db->sql_query($sql) )
  {
    if ( !$silent )
      echo "\n";
    echo "[$dbdriver] " . $db->sql_error() . "\n";
    installer_fail($lang->get('cli_err_db_query'));
  }
}

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_ok')) . "\n";

##
## CONFIG FILE GENERATION
##

require_once( ENANO_ROOT . '/install/includes/payload.php' );
require_once( ENANO_ROOT . '/install/includes/libenanoinstallcli.php' );
define('scriptPath', $scriptpath);
$urlscheme = strtr($urlscheme, array(
  'short' => 'shortened'
));
$_POST['url_scheme'] =& $urlscheme;

run_installer_stage('writeconfig', 'writing_config', 'stg_write_config', 'install_stg_writeconfig_body');

##
## FINAL STAGES
##

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_starting_api'));

// Start up the Enano API
$db->close();
@define('ENANO_ALLOW_LOAD_NOLANG', 1);
// If this fails, it fails hard.
require(ENANO_ROOT . '/includes/common.php');

if ( !$silent )
  echo parse_shellcolor_string($lang->get('cli_msg_ok')) . "\n";

$_POST['username'] =& $user;
$_POST['default_content_type'] = ( isset($start_with) ) ? $start_with : 'blank';

run_installer_stage('importlang', 'importing_language', 'stg_language_setup', $lang->get('install_stg_importlang_body'));
run_installer_stage('importcontent', 'importing_content', 'stg_add_content', $lang->get('install_stg_importcontent_body'));
run_installer_stage('initlogs', 'initting_logs', 'stg_init_logs', $lang->get('install_stg_initlogs_body'));
run_installer_stage('cleanup', 'cleaning_up', 'stg_aes_cleanup', $lang->get('install_stg_cleanup_body'), false);
run_installer_stage('buildindex', 'initting_index', 'stg_build_index', $lang->get('install_stg_buildindex_body'));
run_installer_stage('renameconfig', 'renaming_config', 'stg_rename_config', $lang->get('install_stg_rename_body', array('mainpage_link' => scriptPath . '/index.php')));

if ( !$silent )
{
  echo parse_shellcolor_string($lang->get('cli_msg_install_success'));
}

return true;

##
## FUNCTIONS
##

function cli_prompt($prompt, $default = false)
{
  if ( is_string($default) )
  {
    echo "$prompt [$default]: ";
    $stdin = fopen('php://stdin', 'r');
    $input = trim(fgets($stdin, 1024));
    fclose($stdin);
    if ( empty($input) )
      return $default;
    return $input;
  }
  else
  {
    while ( true )
    {
      echo "$prompt: ";
      $stdin = fopen('php://stdin', 'r');
      $input = trim(fgets($stdin, 1024));
      fclose($stdin);
      if ( !empty($input) )
        return $input;
    }
  }
}

function run_test($evalme, $test, $description, $warnonly = false)
{
  global $silent, $test_failed, $lang;
  if ( !$silent )
    echo "$test: ";
  $result = eval($evalme);
  if ( $result )
  {
    if ( !$silent )
      echo parse_shellcolor_string($lang->get('cli_test_pass'));
  }
  else
  {
    if ( !$silent )
      echo $warnonly ? parse_shellcolor_string($lang->get('cli_test_warn')) : parse_shellcolor_string($lang->get('cli_test_fail'));
    if ( !$silent )
      echo "\n" . preg_replace('/^/m', '  ', wordwrap(strip_tags($description)));
    if ( !$warnonly )
      $test_failed = true;
  }
  if ( !$silent )
    echo "\n";
}

function installer_fail($message)
{
  global $silent;
  if ( $silent )
    file_put_contents('php://stderr', "$message\n");
  else
    echo "\x1B[1;31m" . "Error:\x1B[0;1m $message\x1B[0m\n";
  exit(1);
}

function config_write_test()
{
  if ( !is_writable(ENANO_ROOT.'/config.new.php') )
    return false;
  // We need to actually _open_ the file to make sure it can be written, because sometimes this fails even when is_writable() returns
  // true on Windows/IIS servers. Don't ask me why.
  $h = @fopen( ENANO_ROOT . '/config.new.php', 'a+' );
  if ( !$h )
    return false;
  fclose($h);
  return true;
}

function parse_shellcolor_string($str)
{
  // only compute this once (saves some CPU time)
  static $do_colors = null;
  if ( $do_colors === null )
  {
    $do_colors = ( isset($_SERVER['TERM']) && $_SERVER['TERM'] != 'dumb' );
  }
  
  $expr = '/<c ((?:[0-9]+)(?:;[0-9]+)*)>([\w\W]*?)<\/c>/';
  while ( preg_match($expr, $str) )
    $str = $do_colors ? preg_replace($expr, "\x1B[\\1m\\2\x1B[0m", $str) : preg_replace($expr, "\\2", $str);
  
  return $str;
}

