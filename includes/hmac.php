<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * hmac.php - HMAC cryptographic functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function hmac_core($message, $key, $hashfunc)
{
  if ( strlen($key) % 2 == 1 )
    $key .= '0';
  
  if ( strlen($key) > 128 )
    $key = $hashfunc($key);
  
  while ( strlen($key) < 128 )
  {
    $key .= '00';
  }
  $opad = hmac_hexbytearray($key);
  $ipad = $opad;
  for ( $i = 0; $i < count($ipad); $i++ )
  {
    $opad[$i] = $opad[$i] ^ 0x5c;
    $ipad[$i] = $ipad[$i] ^ 0x36;
  }
  $opad = hmac_bytearraytostring($opad);
  $ipad = hmac_bytearraytostring($ipad);
  return $hashfunc($opad . hexdecode($hashfunc($ipad . $message)));
}

function hmac_hexbytearray($val)
{
  $val = hexdecode($val);
  return hmac_bytearray($val);
}

function hmac_bytearray($val)
{
  $val = str_split($val, 1);
  foreach ( $val as &$char )
  {
    $char = ord($char);
  }
  return $val;
}

function hmac_bytearraytostring($val)
{
  foreach ( $val as &$char )
  {
    $char = chr($char);
  }
  return implode('', $val);
}

function hmac_md5($message, $key)
{
  return hmac_core($message, $key, 'md5');
}

function hmac_sha1($message, $key)
{
  return hmac_core($message, $key, 'sha1');
}

function hmac_sha256($message, $key)
{
  require_once(ENANO_ROOT . '/includes/math.php');
  require_once(ENANO_ROOT . '/includes/diffiehellman.php');
  return hmac_core($message, $key, 'sha256');
}

?>
