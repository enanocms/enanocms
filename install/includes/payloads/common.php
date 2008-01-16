<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * payloads/common.php - Installer payload, common stages
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

