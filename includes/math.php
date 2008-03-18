<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.3 (Caoineag alpha 3)
 * Copyright (C) 2006-2007 Dan Fuhry
 * diffiehellman.php - Diffie Hellman key exchange and supporting functions
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * EnanoMath GMP backend
 */

class EnanoMath_GMP
{
  var $api = 'GMP';
  
  /**
   * Initializes a number to a GMP integer.
   * @param string String representation of the number
   * @param int Base the number is currently in, defaults to 10
   * @return resource
   */
  
  function init($int, $base = 10)
  {
    return ( is_resource($int) ) ? $int : gmp_init($int, $base);
  }
  
  /**
   * Converts a number from a GMP integer to a string
   * @param resource
   * @param int Base, default is 10
   * @return string
   */
  
  function str($int, $base = 10)
  {
    return ( is_string($int) ) ? $int : gmp_strval($int, $base);
  }
  
  /**
   * Converts a number between bases.
   * @param resource BigInt to convert
   * @param int Base to convert from
   * @param int Base to convert to
   */
   
  function basecon($int, $from, $to)
  {
    return $this->init(gmp_strval(gmp_init($this->str($int), $from), $to));
  }
  
  /**
   * Generates a random integer.
   * @param int Length
   * @return resource
   */
  
  function random($len)
  {
    return gmp_random($len);
  }
  
  /**
   * Powmod operation (calculates (a ^ b) mod m)
   * @param resource a
   * @param resource b
   * @param resource m
   * @return resource
   */
  
  function powmod($a, $b, $m)
  {
    $a = $this->init($a);
    $b = $this->init($b);
    $m = $this->init($m);
    return ( function_exists('gmp_powm') ) ? gmp_powm($a, $b, $m) : gmp_mod(gmp_pow($a, $b), $m);
  }
}

/**
 * EnanoMath big_int backend
 */

class EnanoMath_BigInt
{
  var $api = 'big_int';
  
  /**
   * Initializes a number to a BigInt integer.
   * @param string String representation of the number
   * @param int Base the number is in, defaults to 10
   * @return resource
   */
  
  function init($int, $base = 10)
  {
    return (is_resource($int)) ? $int : bi_from_str($int, $base);
  }
  
  /**
   * Converts a number from a BigInt integer to a string
   * @param resource
   * @param int Base, default is 10
   * @return string
   */
  
  function str($int, $base = 10)
  {
    return ( is_string($int) ) ? $int : bi_to_str($int, $base);
  }
  
  /**
   * Generates a random integer
   * @param int Length (bits)
   * @return resource
   */
  
  function random($len)
  {
    return bi_rand($len);
  }
  
  /**
   * Converts a number between bases.
   * @param resource BigInt to convert
   * @param int Base to convert from
   * @param int Base to convert to
   */
  
  function basecon($int, $from, $to)
  {
    return bi_base_convert($this->str($int, $from), $from, $to);
  }
  
  /**
   * Powmod operation (calculates (a ^ b) mod m)
   * @param resource a
   * @param resource b
   * @param resource m
   * @return resource
   */
  
  function powmod($a, $b, $m)
  {
    $a = $this->init($a);
    $b = $this->init($b);
    $m = $this->init($m);
    return bi_powmod($a, $b, $m);
  }
}

/**
 * EnanoMath BCMath backend
 */

class EnanoMath_BCMath
{
  var $api = 'BCMath';
  
  /**
   * Initializes a number to a BCMath integer.
   * @param string String representation of the number
   * @param int Base the number is in, defaults to 10
   * @return resource
   */
  
  function init($int, $base = 10)
  {
    return $this->basecon($int, $base, 10);
  }
  
  /**
   * Converts a number from a BCMath integer to a string
   * @param resource
   * @param int Base, default is 10
   * @return string
   */
   
  function str($res)
  {
    return ( is_string($res) ) ? $res : strval($this->basecon($res, 10, $base));
  }
  
  /**
   * Generates a random integer
   * @param int Length in bits
   * @return resource
   */
  
  function random($len)
  {
    $len = 4 * $len;
    $chars = '0123456789abcdef';
    $ret = '';
    for ( $i = 0; $i < $len; $i++ )
    {
      $chid = mt_rand ( 0, strlen($chars) - 1 );
      $chr = $chars{$chid};
      $ret .= $chr;
    }
    return $this->basecon($this->init($ret), 16, 10);
  }
  
  /**
   * Converts a number between bases.
   * @param resource BigInt to convert
   * @param int Base to convert from
   * @param int Base to convert to
   */
  
  function basecon($int, $from, $to)
  {
    if ( $from != 10 )
      $int = $this->_bcmath_base2dec($int, $from);
    if ( $to != 10 )
      $int = $this->_bcmath_dec2base($int, $to);
    return $int;
  }
  
  /**
   * Powmod operation (calculates (a ^ b) mod m)
   * @param resource a
   * @param resource b
   * @param resource m
   * @return resource
   */
  
  function powmod($a, $b, $m)
  {
    $a = $this->init($a);
    $b = $this->init($b);
    $m = $this->init($m);
    return ( function_exists('bcpowmod') ) ? bcpowmod($a, $b, $m) : bcmod( bcpow($a, $b), $m );
  }
  
  // from us.php.net/bc:
  // convert a decimal value to any other base value
  function _bcmath_dec2base($dec,$base,$digits=FALSE) {
      if($base<2 or $base>256) die("Invalid Base: ".$base);
      bcscale(0);
      $value="";
      if(!$digits) $digits=$this->_bcmath_digits($base);
      while($dec>$base-1) {
          $rest=bcmod($dec,$base);
          $dec=bcdiv($dec,$base);
          $value=$digits[$rest].$value;
      }
      $value=$digits[intval($dec)].$value;
      return (string) $value;
  }
  
  // convert another base value to its decimal value
  function _bcmath_base2dec($value,$base,$digits=FALSE) {
      if($base<2 or $base>256) die("Invalid Base: ".$base);
      bcscale(0);
      if($base<37) $value=strtolower($value);
      if(!$digits) $digits=$this->_bcmath_digits($base);
      $size=strlen($value);
      $dec="0";
      for($loop=0;$loop<$size;$loop++) {
          $element=strpos($digits,$value[$loop]);
          $power=bcpow($base,$size-$loop-1);
          $dec=bcadd($dec,bcmul($element,$power));
      }
      return (string) $dec;
  }
  
  function _bcmath_digits($base) {
      if($base>64) {
          $digits="";
          for($loop=0;$loop<256;$loop++) {
              $digits.=chr($loop);
          }
      } else {
          $digits ="0123456789abcdefghijklmnopqrstuvwxyz";
          $digits.="ABCDEFGHIJKLMNOPQRSTUVWXYZ-_";
      }
      $digits=substr($digits,0,$base);
      return (string) $digits;
  }
}

/**
 * Creates a new math object based on what libraries are available.
 * @return object
 */

function enanomath_create()
{
  if ( function_exists('gmp_init') )
    return new EnanoMath_GMP();
  else if ( function_exists('bi_from_str') )
    return new EnanoMath_BigInt();
  else if ( function_exists('bcadd') )
    return new EnanoMath_BCMath();
  else
    throw new Exception('dh_err_not_supported');
}

?>
