<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
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
	$aes_key = hexdecode($aes_key);
	
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
	global $db, $dbdriver, $installer_version, $lang_id, $languages;
	static $sql_parser = false;
	
	if ( is_object($sql_parser) )
		return $sql_parser->parse();
	
	$aes = AESCrypt::singleton(AES_BITS, AES_BLOCKSIZE);
	$hmac_secret = hexencode(AESCrypt::randkey(20), '', '');
	
	$site_key = stg_make_private_key();
	$site_key = hexdecode($site_key);
	$admin_pass_clean = stg_password_decode();
	$admin_pass = hmac_sha1($admin_pass_clean, $hmac_secret);
	
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
			'TABLE_PREFIX'         => table_prefix,
			'SITE_NAME'            => $db->escape($_POST['site_name']),
			'SITE_DESC'            => $db->escape($_POST['site_desc']),
			'COPYRIGHT'            => $db->escape($_POST['copyright']),
			// FIXME: update form
			'WIKI_MODE'            => ( isset($_POST['wiki_mode']) ? '1' : '0' ),
			'ENABLE_CACHE'         => ( is_writable( ENANO_ROOT . '/cache/' ) ? '1' : '0' ),
			'VERSION'              => $installer_version['version'],
			'ADMIN_USER'           => $db->escape($_POST['username']),
			'ADMIN_PASS'           => $admin_pass,
			'ADMIN_PASS_SALT'      => $hmac_secret,
			'ADMIN_EMAIL'          => $db->escape($_POST['email']),
			'REAL_NAME'            => '', // This has always been stubbed.
			'ADMIN_EMBED_PHP'      => strval(AUTH_DISALLOW),
			'UNIX_TIME'            => strval(time()),
			'IP_ADDRESS'           => $db->escape($_SERVER['REMOTE_ADDR'])
		);
	
	$sql_parser->assign_vars($vars);
	return true;
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
	global $dbhost, $dbuser, $dbpasswd, $dbname, $dbdriver, $dbport;
	$db_data = array(
			'host' => str_replace("'", "\\'", $dbhost),
			'user' => str_replace("'", "\\'", $dbuser),
			'pass' => str_replace("'", "\\'", $dbpasswd),
			'name' => str_replace("'", "\\'", $dbname),
			'port' => intval($dbport),
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
			$sp_append = '/index.php?title=';
			break;
		case 'shortened':
			$sp_append = '/index.php/';
			break;
		case 'tiny':
			$sp_append = '/?/';
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

// Port number for database server, probably 3306 (MySQL) or 5432 (PostgreSQL)
\$dbport = '{$db_data['port']}';

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

// The AES encryption key used for encrypting various bits of information,
// such as cookies, that should not be editable by users. Read about
// Enano's security model at:
//   http://docs.enanocms.org/Help:Appendix_B
// This key was at one point used for passwords as well, but this is no
// longer true.
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

# Required under some aliased setups
RewriteBase {$scriptpath}/

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
	
	$q = $db->sql_query('SELECT lang_id FROM ' . table_prefix . 'language ORDER BY lang_id DESC LIMIT 1;');
	if ( !$q )
		$db->_die();
	
	list($lang_id_int) = $db->fetchrow_num();
	$db->free_result();
	setConfig('default_language', $lang_id_int);
	
	return true;
}

function stg_add_content()
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	global $cache;
	
	global $languages;
	global $lang_id;
	$lang_info =& $languages[$lang_id];
	if ( !is_array($lang_info) )
		return false;
	
	if ( $_POST['default_content_type'] === 'tutorial' )
	{
		$dir = ENANO_ROOT . "/language/{$lang_info['dir']}/install/default-tutorial";
	}
	else
	{
		$dir = ENANO_ROOT . "/language/{$lang_info['dir']}/install/default-blank";
	}
	
	if ( !$dr = @opendir($dir) )
		return false;
	
	while ( $dh = @readdir($dr) )
	{
		if ( !preg_match('/\.txt$/', $dh) )
			continue;
		
		$page_contents = @file_get_contents("$dir/$dh");
		if ( empty($page_contents) )
			return false;
		
		$page_name = preg_replace('/\.txt$/', '', $dh);
		
		if ( !install_primitive_page_creator($page_name, 'Article', $page_contents) )
			return false;
	}
	
	closedir($dr);
	
	$cache->purge('page_meta');
	
	return true;
}

function install_primitive_page_creator($page_id, $namespace, $content)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	
	$page_title = $db->escape(str_replace('_', ' ', dirtify_page_id($page_id)));
	$author = $db->escape($_POST['username']);
	$page_id = $db->escape($page_id);
	$namespace = $db->escape($namespace);
	// yes, we do probably want strip_all_php ON.
	$content = RenderMan::preprocess_text($content, true, true);
	$now = time();
	
	// query 1: logs
	$q = $db->sql_query('INSERT INTO ' . table_prefix . "logs(time_id, date_string, log_type, action, page_id, namespace, author, page_text) VALUES\n"
										. "  ( $now, 'DEPRECATED', 'page', 'edit', '$page_id', '$namespace', '$author', '$content');");
	if ( !$q )
	{
		echo $db->get_error();
		return false;
	}
	
	// query 2: page_text
	$q = $db->sql_query('INSERT INTO ' . table_prefix . "page_text(page_id, namespace, page_text) VALUES\n"
										. "  ( '$page_id', '$namespace', '$content');");
	if ( !$q )
	{
		echo $db->get_error();
		return false;
	}
	
	// query 3: pages
	$q = $db->sql_query('INSERT INTO ' . table_prefix . "pages(page_order, name, urlname, namespace, special, visible, comments_on, protected, delvotes, delvote_ips) VALUES\n"
										. "  (NULL, '$page_title', '$page_id', '$namespace', 0, 1, 1, 1, 0, '');");
	if ( !$q )
	{
		echo $db->get_error();
		return false;
	}
	
	return true;
}

function stg_init_logs()
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	global $installer_version;
	
	$q = $db->sql_query('INSERT INTO ' . table_prefix . 'logs(log_type,action,time_id,date_string,author,author_uid,page_text,edit_summary) VALUES(\'security\', \'install_enano\', ' . time() . ', \'' . enano_date(ED_DATE | ED_TIME) . '\', \'' . $db->escape($_POST['username']) . '\', 2, \'' . $db->escape(enano_version()) . '\', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\');');
	if ( !$q )
	{
		echo '<p><tt>MySQL return: ' . $db->sql_error() . '</tt></p>';
		return false;
	}
	
	return true;
}

function stg_aes_cleanup()
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	$q = $db->sql_query('DELETE FROM ' . table_prefix . 'config WHERE config_name = \'install_aes_key\' OR config_name = \'site_aes_key\';');
	if ( !$q )
		$db->_die();
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
	global $db, $session, $paths, $template, $plugins; // Common objects
	if ( $paths->rebuild_search_index() )
		return true;
	return false;
}

function stg_rename_config()
{
	if ( !@rename(ENANO_ROOT . '/config.new.php', ENANO_ROOT . '/config.php') )
	{
		echo '<p>Can\'t rename config.php</p>';
		_stg_rename_config_revert();
		return false;
	}
	
	if ( @filesize(ENANO_ROOT . '/.htaccess.new') > 1 )
	{
		// rename/possibly concatenate .htaccess.new
		$htaccess_base = '';
		if ( file_exists(ENANO_ROOT . '/.htaccess') )
			$htaccess_base .= @file_get_contents(ENANO_ROOT . '/.htaccess');
		if ( strlen($htaccess_base) > 0 && !preg_match("/\n$/", $htaccess_base) )
			$htaccess_base .= "\n\n";
		$htaccess_base .= @file_get_contents(ENANO_ROOT . '/.htaccess.new');
		if ( file_exists(ENANO_ROOT . '/.htaccess') )
		{
			$hh = @fopen(ENANO_ROOT . '/.htaccess', 'w');
			if ( !$hh )
				return false;
			fwrite($hh, $htaccess_base);
			fclose($hh);
			@unlink(ENANO_ROOT . '/.htaccess.new');
			return true;
		}
		else
		{
			return @rename(ENANO_ROOT . '/.htaccess.new', ENANO_ROOT . '/.htaccess');
		}
	}
	else
	{
		@unlink(ENANO_ROOT . '/.htaccess.new');
	}
	return true;
}

/**
 * UPGRADE STAGES
 */

function stg_lang_import()
{
	global $db, $languages, $do_langimport;
	
	define('IN_ENANO_UPGRADE_POST', 1);
	
	//
	// IMPORT NEW STRINGS
	//
	
	// for each installed language, look for the json files in the filesystem and if they're ok, import new strings from them
	$q = $db->sql_query('SELECT lang_id, lang_code FROM ' . table_prefix . "language;");
	if ( !$q )
		$db->_die();
	
	while ( $row = $db->fetchrow($q) )
	{
		if ( isset($languages[$row['lang_code']]) )
		{
			// found a language and it's good on the filesystem; load it and call a reimport
			$lang_local = new Language($row['lang_id']);
			// call fetch to make sure we're up to date
			$lang_local->fetch();
			// import
			foreach ( array('core', 'admin', 'user', 'tools') as $language_file )
			{
				// generate full path
				$language_file = ENANO_ROOT . "/language/{$languages[$row['lang_code']]['dir']}/$language_file.json";
				// setting the second parameter to bool(true) causes it to skip existing strings
				if ( !$lang_local->import($language_file, ( !$do_langimport )) )
					// on failure, report failure to libenanoinstall
					return false;
			}
			// unload this lang_local object to save memory
			unset($lang_local);
		}
	}
	
	return true;
}

function stg_flush_cache()
{
	return purge_all_caches();
}

function stg_set_version()
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	// log the upgrade
	$q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,author_uid,page_text,edit_summary) VALUES'
 				. '(\'security\', \'upgrade_enano\', ' . time() . ', \'[DEPRECATED]\', \'' . $db->escape($session->username) . '\', ' . $session->user_id . ', \'' . $db->escape(installer_enano_version()) . '\', \'' . $db->escape($_SERVER['REMOTE_ADDR']) . '\');');
	if ( !$q )
	{
		$db->_die();
		return false;
	}
	setConfig('enano_version', installer_enano_version());
	return true;
}
