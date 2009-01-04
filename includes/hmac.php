<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * hmac.php - HMAC cryptographic functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function hmac_gen_padding($val, $len = 32)
{
  $ret = array();
  for ( $i = 0; $i < $len; $i++ )
  {
    $ret[] = $val;
  }
  return $ret;
}

function hmac_core($message, $key, $hashfunc)
{
  static $block_sizes = array();
  if ( !isset($block_sizes[$hashfunc]) )
  {
    $block_sizes[$hashfunc] = strlen($hashfunc(''))/2;
  }
  $blocksize = $block_sizes[$hashfunc];
  $ipad = hmac_gen_padding(0x5c, $blocksize);
  $opad = hmac_gen_padding(0x36, $blocksize);
  if ( strlen($key) != ( $blocksize * 2 ) )
    $key = $hashfunc($key);
  $key = hmac_hexbytearray($key);
  for ( $i = 0; $i < count($key); $i++ )
  {
    $ipad[$i] = $ipad[$i] ^ $key[$i];
    $opad[$i] = $opad[$i] ^ $key[$i];
  }
  return $hashfunc(hmac_bytearraytostring($opad) . $hashfunc(hmac_bytearraytostring($ipad) . $message));
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
