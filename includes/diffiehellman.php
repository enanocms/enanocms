<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * diffiehellman.php - Diffie Hellman key exchange and supporting functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * The Diffie-Hellman key exchange protocol
 */

global $dh_supported;
$dh_supported = true;
try
{
  $GLOBALS['_math'] = enanomath_create();
}
catch ( Exception $e )
{
  $dh_supported = false;
}
// Our prime number as a base for operations.
$GLOBALS['dh_prime'] = '7916586051748534588306961133067968196965257961415756656521818848750723547477673457670019632882524164647651492025728980571833579341743988603191694784406703';

// g, a primitive root used as an exponent
// (2 and 5 are acceptable, but BigInt is faster with odd numbers)
$GLOBALS['dh_g'] = '5';

/**
 * Generates a Diffie-Hellman private key
 * @return string(BigInt)
 */

function dh_gen_private()
{
  global $_math;
  return $_math->random(256);
}

/**
 * Calculates the public key from the private key
 * @param string(BigInt)
 * @return string(BigInt)
 */

function dh_gen_public($b)
{
  global $_math, $dh_g, $dh_prime;
  return $_math->powmod($dh_g, $b, $dh_prime);
}

/**
 * Calculates the shared secret.
 * @param string(BigInt) Our private key
 * @param string(BigInt) Remote party's public key
 * @return string(BigInt)
 */

function dh_gen_shared_secret($a, $B)
{
  global $_math, $dh_g, $dh_prime;
  return $_math->powmod($B, $a, $dh_prime);
}

/*
SHA-256 algorithm - ported from Javascript

Copyright (c) 2003-2004, Angel Marin
All rights reserved.
Portions copyright (c) 2008 Dan Fuhry.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.
 * Neither the name of the <ORGANIZATION> nor the names of its contributors may
   be used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
OF THE POSSIBILITY OF SUCH DAMAGE.
*/
class SHA256
{
  var $chrsz = 8;  /* bits per input character. 8 - ASCII; 16 - Unicode  */
  
  function safe_add ($x, $y) {
    $lsw = ($x & 0xFFFF) + ($y & 0xFFFF);
    $msw = ($x >> 16) + ($y >> 16) + ($lsw >> 16);
    // 2009-07-02 Added & 0xFFFFFFFF here to fix problem on PHP w/ native 64-bit integer support (rev. 1030)
    return (($msw << 16) | ($lsw & 0xFFFF)) & 0xFFFFFFFF;
  }
  function rshz($X, $n)
  {
    // equivalent to $X >>> $n in javascript
    // pulled from http://www.tapouillo.com/firefox_extension/sourcecode.txt, public domain
    $z = hexdec(80000000); 
    if ($z & $X) 
    { 
        $X = ($X>>1); 
        $X &= (~$z); 
        $X |= 0x40000000; 
        $X = ($X>>($n-1)); 
    } 
    else 
    { 
        $X = ($X>>$n); 
    } 
    return $X; 
  }
  function S ($X, $n) {return ( $this->rshz($X, $n) ) | ($X << (32 - $n));}
  function R ($X, $n) {return ( $this->rshz($X, $n) );}
  function Ch($x, $y, $z)  {return (($x & $y) ^ ((~$x) & $z));}
  function Maj($x, $y, $z) {return (($x & $y) ^ ($x & $z) ^ ($y & $z));}
  function Sigma0256($x) {return ($this->S($x, 2)  ^ $this->S($x, 13) ^ $this->S($x, 22));}
  function Sigma1256($x) {return ($this->S($x, 6)  ^ $this->S($x, 11) ^ $this->S($x, 25));}
  function Gamma0256($x) {return ($this->S($x, 7)  ^ $this->S($x, 18) ^ $this->R($x, 3));}
  function Gamma1256($x) {return ($this->S($x, 17) ^ $this->S($x, 19) ^ $this->R($x, 10));}
  function core_sha256 ($m, $l) {
      $K = Array(0x428A2F98,0x71374491,0xB5C0FBCF,0xE9B5DBA5,0x3956C25B,0x59F111F1,0x923F82A4,0xAB1C5ED5,0xD807AA98,0x12835B01,0x243185BE,0x550C7DC3,0x72BE5D74,0x80DEB1FE,0x9BDC06A7,0xC19BF174,0xE49B69C1,0xEFBE4786,0xFC19DC6,0x240CA1CC,0x2DE92C6F,0x4A7484AA,0x5CB0A9DC,0x76F988DA,0x983E5152,0xA831C66D,0xB00327C8,0xBF597FC7,0xC6E00BF3,0xD5A79147,0x6CA6351,0x14292967,0x27B70A85,0x2E1B2138,0x4D2C6DFC,0x53380D13,0x650A7354,0x766A0ABB,0x81C2C92E,0x92722C85,0xA2BFE8A1,0xA81A664B,0xC24B8B70,0xC76C51A3,0xD192E819,0xD6990624,0xF40E3585,0x106AA070,0x19A4C116,0x1E376C08,0x2748774C,0x34B0BCB5,0x391C0CB3,0x4ED8AA4A,0x5B9CCA4F,0x682E6FF3,0x748F82EE,0x78A5636F,0x84C87814,0x8CC70208,0x90BEFFFA,0xA4506CEB,0xBEF9A3F7,0xC67178F2);
      $HASH = Array(0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A, 0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19);
      $W = Array(64);
      /* append padding */
      $m[$l >> 5] |= 0x80 << (24 - $l % 32);
      $m[(($l + 64 >> 9) << 4) + 15] = $l;
      for ( $i = 0; $i<count($m); $i+=16 ) {
          $a = $HASH[0];
          $b = $HASH[1];
          $c = $HASH[2];
          $d = $HASH[3];
          $e = $HASH[4];
          $f = $HASH[5];
          $g = $HASH[6];
          $h = $HASH[7];
          for ( $j = 0; $j<64; $j++)
          {
              if ( $j < 16 )
              {
                $W[$j] = ( isset($m[$j + $i]) ) ? $m[$j + $i] : 0;
              }
              else
              {
                $W[$j] = $this->safe_add(
                  $this->safe_add(
                    $this->safe_add(
                      $this->Gamma1256($W[$j - 2]), $W[$j - 7]),
                    $this->Gamma0256($W[$j - 15])),
                  $W[$j - 16]);
              }
              $T1 = $this->safe_add(
                $this->safe_add(
                  $this->safe_add(
                    $this->safe_add($h, $this->Sigma1256($e)
                      ),
                    $this->Ch($e, $f, $g)),
                  $K[$j]),
                $W[$j]);
              $T2 = $this->safe_add($this->Sigma0256($a), $this->Maj($a, $b, $c));
              $h = $g;
              $g = $f;
              $f = $e;
              $e = $this->safe_add($d, $T1);
              $d = $c;
              $c = $b;
              $b = $a;
              $a = $this->safe_add($T1, $T2);
          }
          $HASH[0] = $this->safe_add($a, $HASH[0]);
          $HASH[1] = $this->safe_add($b, $HASH[1]);
          $HASH[2] = $this->safe_add($c, $HASH[2]);
          $HASH[3] = $this->safe_add($d, $HASH[3]);
          $HASH[4] = $this->safe_add($e, $HASH[4]);
          $HASH[5] = $this->safe_add($f, $HASH[5]);
          $HASH[6] = $this->safe_add($g, $HASH[6]);
          $HASH[7] = $this->safe_add($h, $HASH[7]);
      }
      return $HASH;
  }
  function str2binb ($str) {
    $bin = Array();
    for ( $i = 0; $i < strlen($str); $i++ )
    {
      $byte = ord($str{$i});
      $block = floor($i / 4);
      $stage = $i % 4;
      if ( $stage == 0 )
      {
        $bin[$block] = $byte;
      }
      else
      {
        $bin[$block] <<= 8;
        $bin[$block] |= $byte;
      }
    }
    while ( $stage < 3 )
    {
      $stage++;
      $bin[$block] <<= 8;
    }
    return $bin;
  }
  function byte2hex($byte)
  {
    $b = dechex(ord($byte));
    return ( strlen($b) < 2 ) ? "0$b" : $b;
  }
  function binb2hex ($binarray) {
    $hexcase = 0; /* hex output format. 0 - lowercase; 1 - uppercase */
    $hex_tab = $hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
    $str = "";
    foreach ( $binarray as $bytes )
    {
      $str .= implode('', array(
          $this->byte2hex(chr(( $bytes >> 24 ) & 0xFF)),
          $this->byte2hex(chr(( $bytes >> 16 ) & 0xFF)),
          $this->byte2hex(chr(( $bytes >> 8 ) & 0xFF)),
          $this->byte2hex(chr($bytes & 0xFF))
        ));
    }
    return $str;
  }
  function hex_sha256 ( $s )
  {
    return $this->binb2hex(
      $this->core_sha256(
        $this->str2binb($s),
        strlen($s) * $this->chrsz)
      );
  }
}

if ( !function_exists('sha256') )
{
  function sha256($text)
  {
    static $sha_obj = false;
    if ( !is_object($sha_obj) )
      $sha_obj = new SHA256();
    return $sha_obj->hex_sha256($text);
  }
}

?>
