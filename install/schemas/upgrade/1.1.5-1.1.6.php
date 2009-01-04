<?php

// Migrate passwords to the new encryption scheme

global $db, $session, $paths, $template, $plugins; // Common objects
require_once(ENANO_ROOT . '/includes/hmac.php');

@set_time_limit(0);

$q = $db->sql_query('UPDATE ' . table_prefix . "users SET old_encryption = 2 WHERE user_id > 1 AND old_encryption = 0;");
if ( !$q )
  $db->_die();

$q = $db->sql_query('SELECT user_id, password FROM ' . table_prefix . "users WHERE user_id > 1 AND old_encryption = 2;");
if ( !$q )
  $db->_die();

while ( $row = $db->fetchrow($q) )
{
  $password = $session->pk_decrypt($row['password']);
  if ( empty($password) )
  {
    global $ui;
    echo '<p>1.1.5-1.1.6 migration script: ERROR: bad password returned from $session->pk_decrypt()</p>';
    $ui->show_footer();
    exit;
  }
  $hmac_secret = hexencode(AESCrypt::randkey(20), '', '');
  $password = hmac_sha1($password, $hmac_secret);
  $e = $db->sql_query('UPDATE ' . table_prefix . "users SET password = '{$password}', password_salt = '{$hmac_secret}', old_encryption = 0 WHERE user_id = {$row['user_id']};");
  if ( !$e )
    $db->_die();
}


