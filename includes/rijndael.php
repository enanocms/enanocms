<?php

/**
 * Phijndael - an implementation of the AES encryption standard in PHP
 * Originally written by Fritz Schneider <fritz AT cd DOT ucsd DOT edu>
 * Ported to PHP by Dan Fuhry <dan AT enano DOT homelinux DOT org>
 * @package phijndael
 * @author Fritz Schneider
 * @author Dan Fuhry
 * @license BSD-style license
 */

error_reporting(E_ALL);

define ('ENC_HEX', 201);
define ('ENC_BASE64', 202);
define ('ENC_BINARY', 203);

$_aes_objcache = array();

class AESCrypt {
  
  var $debug = false;
  var $mcrypt = false;
  var $decrypt_cache = array();

  // Rijndael parameters --  Valid values are 128, 192, or 256
  
  var $keySizeInBits = 128;
  var $blockSizeInBits = 128;
  
  ///////  You shouldn't have to modify anything below this line except for
  ///////  the function getRandomBytes().
  //
  // Note: in the following code the two dimensional arrays are indexed as
  //       you would probably expect, as array[row][column]. The state arrays
  //       are 2d arrays of the form state[4][Nb].
  
  
  // The number of rounds for the cipher, indexed by [Nk][Nb]
  var $roundsArray = Array(0,0,0,0,Array(0,0,0,0,10,0, 12,0, 14),0, 
                               Array(0,0,0,0,12,0, 12,0, 14),0, 
                               Array(0,0,0,0,14,0, 14,0, 14) );
  
  // The number of bytes to shift by in shiftRow, indexed by [Nb][row]
  var $shiftOffsets = Array(0,0,0,0,Array(0,1, 2, 3),0,Array(0,1, 2, 3),0,Array(0,1, 3, 4) );
  
  // The round constants used in subkey expansion
  var $Rcon = Array( 
  0x01, 0x02, 0x04, 0x08, 0x10, 0x20, 
  0x40, 0x80, 0x1b, 0x36, 0x6c, 0xd8, 
  0xab, 0x4d, 0x9a, 0x2f, 0x5e, 0xbc, 
  0x63, 0xc6, 0x97, 0x35, 0x6a, 0xd4, 
  0xb3, 0x7d, 0xfa, 0xef, 0xc5, 0x91 );
  
  // Precomputed lookup table for the SBox
  var $SBox = Array(
   99, 124, 119, 123, 242, 107, 111, 197,  48,   1, 103,  43, 254, 215, 171, 
  118, 202, 130, 201, 125, 250,  89,  71, 240, 173, 212, 162, 175, 156, 164, 
  114, 192, 183, 253, 147,  38,  54,  63, 247, 204,  52, 165, 229, 241, 113, 
  216,  49,  21,   4, 199,  35, 195,  24, 150,   5, 154,   7,  18, 128, 226, 
  235,  39, 178, 117,   9, 131,  44,  26,  27, 110,  90, 160,  82,  59, 214, 
  179,  41, 227,  47, 132,  83, 209,   0, 237,  32, 252, 177,  91, 106, 203, 
  190,  57,  74,  76,  88, 207, 208, 239, 170, 251,  67,  77,  51, 133,  69, 
  249,   2, 127,  80,  60, 159, 168,  81, 163,  64, 143, 146, 157,  56, 245, 
  188, 182, 218,  33,  16, 255, 243, 210, 205,  12,  19, 236,  95, 151,  68,  
  23,  196, 167, 126,  61, 100,  93,  25, 115,  96, 129,  79, 220,  34,  42, 
  144, 136,  70, 238, 184,  20, 222,  94,  11, 219, 224,  50,  58,  10,  73,
    6,  36,  92, 194, 211, 172,  98, 145, 149, 228, 121, 231, 200,  55, 109, 
  141, 213,  78, 169, 108,  86, 244, 234, 101, 122, 174,   8, 186, 120,  37,  
   46,  28, 166, 180, 198, 232, 221, 116,  31,  75, 189, 139, 138, 112,  62, 
  181, 102,  72,   3, 246,  14,  97,  53,  87, 185, 134, 193,  29, 158, 225,
  248, 152,  17, 105, 217, 142, 148, 155,  30, 135, 233, 206,  85,  40, 223,
  140, 161, 137,  13, 191, 230,  66, 104,  65, 153,  45,  15, 176,  84, 187,  
   22 );
  
  // Precomputed lookup table for the inverse SBox
  var $SBoxInverse = Array(
   82,   9, 106, 213,  48,  54, 165,  56, 191,  64, 163, 158, 129, 243, 215, 
  251, 124, 227,  57, 130, 155,  47, 255, 135,  52, 142,  67,  68, 196, 222, 
  233, 203,  84, 123, 148,  50, 166, 194,  35,  61, 238,  76, 149,  11,  66, 
  250, 195,  78,   8,  46, 161, 102,  40, 217,  36, 178, 118,  91, 162,  73, 
  109, 139, 209,  37, 114, 248, 246, 100, 134, 104, 152,  22, 212, 164,  92, 
  204,  93, 101, 182, 146, 108, 112,  72,  80, 253, 237, 185, 218,  94,  21,  
   70,  87, 167, 141, 157, 132, 144, 216, 171,   0, 140, 188, 211,  10, 247, 
  228,  88,   5, 184, 179,  69,   6, 208,  44,  30, 143, 202,  63,  15,   2, 
  193, 175, 189,   3,   1,  19, 138, 107,  58, 145,  17,  65,  79, 103, 220, 
  234, 151, 242, 207, 206, 240, 180, 230, 115, 150, 172, 116,  34, 231, 173,
   53, 133, 226, 249,  55, 232,  28, 117, 223, 110,  71, 241,  26, 113,  29, 
   41, 197, 137, 111, 183,  98,  14, 170,  24, 190,  27, 252,  86,  62,  75, 
  198, 210, 121,  32, 154, 219, 192, 254, 120, 205,  90, 244,  31, 221, 168,
   51, 136,   7, 199,  49, 177,  18,  16,  89,  39, 128, 236,  95,  96,  81,
  127, 169,  25, 181,  74,  13,  45, 229, 122, 159, 147, 201, 156, 239, 160,
  224,  59,  77, 174,  42, 245, 176, 200, 235, 187,  60, 131,  83, 153,  97, 
   23,  43,   4, 126, 186, 119, 214,  38, 225, 105,  20,  99,  85,  33,  12,
  125 );
  
  function AESCrypt($ks = 128, $bs = 128, $debug = false)
  {
    $this->__construct($ks, $bs, $debug);
  }
  
  function __construct($ks = 128, $bs = 128, $debug = false)
  {
    $this->keySizeInBits = $ks;
    $this->blockSizeInBits = $bs;
    
    // Use the Mcrypt library? This speeds things up dramatically.
    if(defined('MCRYPT_RIJNDAEL_' . $ks) && defined('MCRYPT_ACCEL'))
    {
      eval('$mcb = MCRYPT_RIJNDAEL_' . $ks.';');
      $bks = mcrypt_module_get_algo_block_size($mcb);
      $bks = $bks * 8;
      if ( $bks != $bs )
      {
        $mcb = false;
        echo (string)$bks;
      }
    }
    else
    {
      $mcb = false;
    }
      
    $this->mcrypt = $mcb;
    
    // Cipher parameters ... do not change these
    $this->Nk = $this->keySizeInBits / 32;
    $this->Nb = $this->blockSizeInBits / 32;
    $this->Nr = $this->roundsArray[$this->Nk][$this->Nb];
    $this->debug = $debug;
  }
  
  function singleton($key_size, $block_size)
  {
    global $_aes_objcache;
    if ( isset($_aes_objcache["$key_size,$block_size"]) )
    {
      return $_aes_objcache["$key_size,$block_size"];
    }
    
    $_aes_objcache["$key_size,$block_size"] = new AESCrypt($key_size, $block_size);
    return $_aes_objcache["$key_size,$block_size"];
  }
  
  // Error handler
  
  function trigger_error($text, $level = E_USER_NOTICE)
  {
    $bt = debug_backtrace();
    $lastfunc =& $bt[1];
    switch($level)
    {
      case E_USER_NOTICE:
      default:
        $desc = 'Notice';
        break;
      case E_USER_WARNING:
        $desc = 'Warning';
        break;
      case E_USER_ERROR:
        $desc = 'Fatal';
        break;
    }
    ob_start();
    if($this->debug || $level == E_USER_ERROR) echo "AES encryption: <b>{$desc}:</b> $text in {$lastfunc['file']} on line {$lastfunc['line']} in function {$lastfunc['function']}<br />";
    if($this->debug)
    {
      //echo '<pre>'.enano_debug_print_backtrace(true).'</pre>';
    }
    ob_end_flush();
    if($level == E_USER_ERROR)
    {
      echo '<p><b>This can sometimes happen if you are upgrading Enano to a new version and did not log out first.</b> <a href="'.$_SERVER['PHP_SELF'].'?do=diag&amp;sub=cookie_destroy">Click here</a> to force cookies to clear and try again. You will be logged out.</p>';
      exit;
    }
  }
  
  function array_slice_js_compat($array, $start, $finish = 0)
  {
    $len = $finish - $start;
    if($len < 0) $len = 0 - $len;
    //if($this->debug) echo (string)$len . ' ';
    //if(count($array) < $start + $len)
    //  $this->trigger_error('Index out of range', E_USER_WARNING);
    return array_slice($array, $start, $len);
  }
  
  function concat($s1, $s2)
  {
    if(is_array($s1) && is_array($s2))
      return array_merge($s1, $s2);
    elseif( ( is_array($s1) && !is_array($s2) ) || ( !is_array($s1) && is_array($s2) ) )
    {
      $this->trigger_error('incompatible types - you can\'t combine a non-array with an array', E_USER_WARNING);
      return false;
    }
    else
      return $s1 . $s2;
  }
  
  // This method circularly shifts the array left by the number of elements
  // given in its parameter. It returns the resulting array and is used for 
  // the ShiftRow step. Note that shift() and push() could be used for a more 
  // elegant solution, but they require IE5.5+, so I chose to do it manually. 
  
  function cyclicShiftLeft($theArray, $positions) {
    if(!is_int($positions))
    {
      $this->trigger_error('$positions is not an integer! Backtrace:<br /><pre>'.print_r(debug_backtrace(), true).'</pre>', E_USER_WARNING);
      return false;
    }
    $second = array_slice($theArray, 0, $positions);
    $first = array_slice($theArray, $positions);
    $theArray = array_merge($first, $second);
    return $theArray;
  }
  
  // Multiplies the element "poly" of GF(2^8) by x. See the Rijndael spec.
  
  function xtime($poly) {
    $poly <<= 1;
    return (($poly & 0x100) ? ($poly ^ 0x11B) : ($poly));
  }
  
  // Multiplies the two elements of GF(2^8) together and returns the result.
  // See the Rijndael spec, but should be straightforward: for each power of
  // the indeterminant that has a 1 coefficient in x, add y times that power
  // to the result. x and y should be bytes representing elements of GF(2^8)
  
  function mult_GF256($x, $y) {
    $result = 0;
    
    for ($bit = 1; $bit < 256; $bit *= 2, $y = $this->xtime($y)) {
      if ($x & $bit) 
        $result ^= $y;
    }
    return $result;
  }
  
  // Performs the substitution step of the cipher. State is the 2d array of
  // state information (see spec) and direction is string indicating whether
  // we are performing the forward substitution ("encrypt") or inverse 
  // substitution (anything else)
  
  function byteSub(&$state, $direction) {
    //global $this->SBox, $this->SBoxInverse, $this->Nb;
    if ($direction == "encrypt")           // Point S to the SBox we're using
      $S =& $this->SBox;
    else
      $S =& $this->SBoxInverse;
    for ($i = 0; $i < 4; $i++)           // Substitute for every byte in state
      for ($j = 0; $j < $this->Nb; $j++)
         $state[$i][$j] = $S[$state[$i][$j]];
  }
  
  // Performs the row shifting step of the cipher.
  
  function shiftRow(&$state, $direction) {
    //global $this->Nb, $this->shiftOffsets;
    for ($i=1; $i<4; $i++)               // Row 0 never shifts
      if ($direction == "encrypt")
         $state[$i] = $this->cyclicShiftLeft($state[$i], $this->shiftOffsets[$this->Nb][$i]);
      else
         $state[$i] = $this->cyclicShiftLeft($state[$i], $this->Nb - $this->shiftOffsets[$this->Nb][$i]);
  
  }
  
  // Performs the column mixing step of the cipher. Most of these steps can
  // be combined into table lookups on 32bit values (at least for encryption)
  // to greatly increase the speed. 
  
  function mixColumn(&$state, $direction) {
    //global $this->Nb;
    $b = Array();                                  // Result of matrix multiplications
    for ($j = 0; $j < $this->Nb; $j++) {                 // Go through each column...
      for ($i = 0; $i < 4; $i++) {                 // and for each row in the column...
        if ($direction == "encrypt")
          $b[$i] = $this->mult_GF256($state[$i][$j], 2) ^ // perform mixing
                   $this->mult_GF256($state[($i+1)%4][$j], 3) ^ 
                   $state[($i+2)%4][$j] ^ 
                   $state[($i+3)%4][$j];
        else 
          $b[$i] = $this->mult_GF256($state[$i][$j], 0xE) ^ 
                   $this->mult_GF256($state[($i+1)%4][$j], 0xB) ^
                   $this->mult_GF256($state[($i+2)%4][$j], 0xD) ^
                   $this->mult_GF256($state[($i+3)%4][$j], 9);
      }
      for ($i = 0; $i < 4; $i++)          // Place result back into column
        $state[$i][$j] = $b[$i];
    }
  }
  
  // Adds the current round key to the state information. Straightforward.
  
  function addRoundKey(&$state, $roundKey) {
    //global $this->Nb;
    for ($j = 0; $j < $this->Nb; $j++) {                      // Step through columns...
      $state[0][$j] ^= ( $roundKey[$j] & 0xFF);         // and XOR
      $state[1][$j] ^= (($roundKey[$j]>>8) & 0xFF);
      $state[2][$j] ^= (($roundKey[$j]>>16) & 0xFF);
      $state[3][$j] ^= (($roundKey[$j]>>24) & 0xFF);
    }
  }
  
  // This function creates the expanded key from the input (128/192/256-bit)
  // key. The parameter key is an array of bytes holding the value of the key.
  // The returned value is an array whose elements are the 32-bit words that 
  // make up the expanded key.
  
  function keyExpansion($key) {
    //global $this->keySizeInBits, $this->blockSizeInBits, $this->roundsArray, $this->Nk, $this->Nb, $this->Nr, $this->Nk, $this->SBox, $this->Rcon;
    $expandedKey = Array();
  
    // in case the key size or parameters were changed...
    $this->Nk = $this->keySizeInBits / 32;                   
    $this->Nb = $this->blockSizeInBits / 32;
    $this->Nr = $this->roundsArray[$this->Nk][$this->Nb];
  
    for ($j=0; $j < $this->Nk; $j++)     // Fill in input key first
      $expandedKey[$j] = 
        ($key[4*$j]) | ($key[4*$j+1]<<8) | ($key[4*$j+2]<<16) | ($key[4*$j+3]<<24);
  
    // Now walk down the rest of the array filling in expanded key bytes as
    // per Rijndael's spec
    for ($j = $this->Nk; $j < $this->Nb * ($this->Nr + 1); $j++) {    // For each word of expanded key
      $temp = $expandedKey[$j - 1];
      if ($j % $this->Nk == 0) 
        $temp = ( ($this->SBox[($temp>>8) & 0xFF]) |
                  ($this->SBox[($temp>>16) & 0xFF]<<8) |
                  ($this->SBox[($temp>>24) & 0xFF]<<16) |
                  ($this->SBox[$temp & 0xFF]<<24) ) ^ $this->Rcon[floor($j / $this->Nk) - 1];
      elseif  ($this->Nk > 6 && $j % $this->Nk == 4)
        $temp = ($this->SBox[($temp>>24) & 0xFF]<<24) |
               ($this->SBox[($temp>>16) & 0xFF]<<16) |
               ($this->SBox[($temp>>8) & 0xFF]<<8) |
               ($this->SBox[ $temp & 0xFF]);
      $expandedKey[$j] = $expandedKey[$j-$this->Nk] ^ $temp;
    }
    return $expandedKey;
  }
  
  // Rijndael's round functions... 
  
  function RijndaelRound(&$state, $roundKey) {
    $this->byteSub($state, "encrypt");
    $this->shiftRow($state, "encrypt");
    $this->mixColumn($state, "encrypt");
    $this->addRoundKey($state, $roundKey);
  }
  
  function InverseRijndaelRound(&$state, $roundKey) {
    $this->addRoundKey($state, $roundKey);
    $this->mixColumn($state, "decrypt");
    $this->shiftRow($state, "decrypt");
    $this->byteSub($state, "decrypt");
  }
  
  function FinalRijndaelRound(&$state, $roundKey) {
    $this->byteSub($state, "encrypt");
    $this->shiftRow($state, "encrypt");
    $this->addRoundKey($state, $roundKey);
  }
  
  function InverseFinalRijndaelRound(&$state, $roundKey){
    $this->addRoundKey($state, $roundKey);
    $this->shiftRow($state, "decrypt");
    $this->byteSub($state, "decrypt");  
  }
  
  // encrypt is the basic encryption function. It takes parameters
  // block, an array of bytes representing a plaintext block, and expandedKey,
  // an array of words representing the expanded key previously returned by
  // keyExpansion(). The ciphertext block is returned as an array of bytes.
  
  function cryptBlock($block, $expandedKey) {
    //global $this->blockSizeInBits, $this->Nb, $this->Nr;
    $t=count($block)*8;
    if (!is_array($block) || count($block)*8 != $this->blockSizeInBits)
    {
      $this->trigger_error('block is bad or block size is wrong<pre>'.print_r($block, true).'</pre><p>Aiming for size '.$this->blockSizeInBits.', got '.$t.'.', E_USER_WARNING); 
      return false;
    }
    if (!$expandedKey)
      return;
  
    $block = $this->packBytes($block);
    $this->addRoundKey($block, $expandedKey);
    for ($i=1; $i<$this->Nr; $i++) 
      $this->RijndaelRound($block, $this->array_slice_js_compat($expandedKey, $this->Nb*$i, $this->Nb*($i+1)));
    $this->FinalRijndaelRound($block, $this->array_slice_js_compat($expandedKey, $this->Nb*$this->Nr));
    $ret = $this->unpackBytes($block);
    return $ret;
  }
  
  // decrypt is the basic decryption function. It takes parameters
  // block, an array of bytes representing a ciphertext block, and expandedKey,
  // an array of words representing the expanded key previously returned by
  // keyExpansion(). The decrypted block is returned as an array of bytes.
  
  function unCryptBlock($block, $expandedKey) {
    $t = count($block)*8;
    if (!is_array($block) || count($block)*8 != $this->blockSizeInBits)
    {
      $this->trigger_error('$block is not a valid rijndael-block array: '.$this->byteArrayToHex($block).'<pre>'.print_r($block, true).'</pre><p>Block size is '.$t.', should be '.$this->blockSizeInBits.'</p>', E_USER_WARNING);
      return false;
    }
    if (!$expandedKey)
    {
      $this->trigger_error('$expandedKey is invalid', E_USER_WARNING);
      return false;
    }
  
    $block = $this->packBytes($block);
    $this->InverseFinalRijndaelRound($block, $this->array_slice_js_compat($expandedKey, $this->Nb*$this->Nr)); 
    for ($i = $this->Nr - 1; $i>0; $i--) 
    {
      $this->InverseRijndaelRound($block, $this->array_slice_js_compat($expandedKey, $this->Nb*$i, $this->Nb*($i+1)));
    }
    $this->addRoundKey($block, $expandedKey);
    $ret = $this->unpackBytes($block);
    if(!is_array($ret))
    {
      $this->trigger_error('$ret is not an array', E_USER_WARNING);
    }
    return $ret;
  }
  
  // This method takes a byte array (byteArray) and converts it to a string by
  // applying String.fromCharCode() to each value and concatenating the result.
  // The resulting string is returned. Note that this function SKIPS zero bytes
  // under the assumption that they are padding added in formatPlaintext().
  // Obviously, do not invoke this method on raw data that can contain zero
  // bytes. It is really only appropriate for printable ASCII/Latin-1 
  // values. Roll your own function for more robust functionality :)
  
  function byteArrayToString($byteArray) {
    $result = "";
    for($i=0; $i<count($byteArray); $i++)
      if ($byteArray[$i] != 0) 
        $result .= chr($byteArray[$i]);
    return $result;
  }
  
  // This function takes an array of bytes (byteArray) and converts them
  // to a hexadecimal string. Array element 0 is found at the beginning of 
  // the resulting string, high nibble first. Consecutive elements follow
  // similarly, for example [16, 255] --> "10ff". The function returns a 
  // string.
  
  /*
  function byteArrayToHex($byteArray) {
    $result = "";
    if (!$byteArray)
      return;
    for ($i=0; $i<count($byteArray); $i++)
      $result .= (($byteArray[$i]<16) ? "0" : "") + toString($byteArray[$i]); // magic number here is 16, not sure how to handle this...
  
    return $result;
  }
  */
  function byteArrayToHex($arr)
  {
    $ret = '';
    foreach($arr as $a)
    {
      $nibble = (string)dechex(intval($a));
      if(strlen($nibble) == 1) $nibble = '0' . $nibble;
      $ret .= $nibble;
    }
    return $ret;
  }
  
  // PHP equivalent of Javascript's toString()
  function toString($bool)
  {
    if(is_bool($bool))
      return ($bool) ? 'true' : 'false';
    elseif(is_array($bool))
      return implode(',', $bool);
    else
      return (string)$bool;
  }
  
  // This function converts a string containing hexadecimal digits to an 
  // array of bytes. The resulting byte array is filled in the order the
  // values occur in the string, for example "10FF" --> [16, 255]. This
  // function returns an array. 
  
  /*
  function hexToByteArray($hexString) {
    $byteArray = Array();
    if (strlen($hexString) % 2)             // must have even length
      return;
    if (strstr($hexString, "0x") == $hexString || strstr($hexString, "0X") == $hexString)
      $hexString = substr($hexString, 2);
    for ($i = 0; $i<strlen($hexString); $i++,$i++) 
      $byteArray[floor($i/2)] = intval(substr($hexString, $i, 2)); // again, that strange magic number: 16
    return $byteArray;
  }
  */
  function hexToByteArray($str)
  {
    if(substr($str, 0, 2) == '0x' || substr($str, 0, 2) == '0X')
      $str = substr($str, 2);
    $arr = Array();
    $str = $this->enano_str_split($str, 2);
    foreach($str as $s)
    {
      $arr[] = intval(hexdec($s));
    }
    return $arr;
  }
  
  // This function packs an array of bytes into the four row form defined by
  // Rijndael. It assumes the length of the array of bytes is divisible by
  // four. Bytes are filled in according to the Rijndael spec (starting with
  // column 0, row 0 to 3). This function returns a 2d array.
  
  function packBytes($octets) {
    $state = Array();
    if (!$octets || count($octets) % 4)
      return;
  
    $state[0] = Array(); $state[1] = Array(); 
    $state[2] = Array(); $state[3] = Array();
    for ($j=0; $j<count($octets); $j = $j+4) {
       $state[0][$j/4] = $octets[$j];
       $state[1][$j/4] = $octets[$j+1];
       $state[2][$j/4] = $octets[$j+2];
       $state[3][$j/4] = $octets[$j+3];
    }
    return $state;
  }
  
  // This function unpacks an array of bytes from the four row format preferred
  // by Rijndael into a single 1d array of bytes. It assumes the input "packed"
  // is a packed array. Bytes are filled in according to the Rijndael spec. 
  // This function returns a 1d array of bytes.
  
  function unpackBytes($packed) {
    $result = Array();
    for ($j=0; $j<count($packed[0]); $j++) {
      $result[] = $packed[0][$j];
      $result[] = $packed[1][$j];
      $result[] = $packed[2][$j];
      $result[] = $packed[3][$j];
    }
    return $result;
  }
  
  function charCodeAt($str, $i)
  {
    return ord(substr($str, $i, 1));
  }
  
  function fromCharCode($str)
  {
    return chr($str);
  }
  
  // This function takes a prospective plaintext (string or array of bytes)
  // and pads it with zero bytes if its length is not a multiple of the block 
  // size. If plaintext is a string, it is converted to an array of bytes
  // in the process. The type checking can be made much nicer using the 
  // instanceof operator, but this operator is not available until IE5.0 so I 
  // chose to use the heuristic below. 
  
  function formatPlaintext($plaintext) {
    //global $this->blockSizeInBits;
    $bpb = $this->blockSizeInBits / 8;               // bytes per block
  
    // if primitive string or String instance
    if (is_string($plaintext)) {
      $plaintext = $this->enano_str_split($plaintext);
      // Unicode issues here (ignoring high byte)
      for ($i=0; $i<sizeof($plaintext); $i++)
        $plaintext[$i] = $this->charCodeAt($plaintext[$i], 0) & 0xFF;
    } 
  
    for ($i = $bpb - (sizeof($plaintext) % $bpb); $i > 0 && $i < $bpb; $i--) 
      $plaintext[] = 0;
    
    return $plaintext;
  }
  
  // Returns an array containing "howMany" random bytes. YOU SHOULD CHANGE THIS
  // TO RETURN HIGHER QUALITY RANDOM BYTES IF YOU ARE USING THIS FOR A "REAL"
  // APPLICATION. (edit: done, mt_rand() is relatively secure)
  
  function getRandomBytes($howMany) {
    $bytes = Array();
    for ($i=0; $i<$howMany; $i++)
      $bytes[$i] = mt_rand(0, 255);
    return $bytes;
  }
  
  // rijndaelEncrypt(plaintext, key, mode)
  // Encrypts the plaintext using the given key and in the given mode. 
  // The parameter "plaintext" can either be a string or an array of bytes. 
  // The parameter "key" must be an array of key bytes. If you have a hex 
  // string representing the key, invoke hexToByteArray() on it to convert it 
  // to an array of bytes. The third parameter "mode" is a string indicating
  // the encryption mode to use, either "ECB" or "CBC". If the parameter is
  // omitted, ECB is assumed.
  // 
  // An array of bytes representing the cihpertext is returned. To convert 
  // this array to hex, invoke byteArrayToHex() on it. If you are using this 
  // "for real" it is a good idea to change the function getRandomBytes() to 
  // something that returns truly random bits.
  
  function rijndaelEncrypt($plaintext, $key, $mode = 'ECB') {
    //global $this->blockSizeInBits, $this->keySizeInBits;
    $bpb = $this->blockSizeInBits / 8;          // bytes per block
    // var ct;                                 // ciphertext
  
    if($mode == 'CBC')
    {
      if (!is_string($plaintext) || !is_array($key))
      {
        $this->trigger_error('In CBC mode the first and second parameters should be strings', E_USER_WARNING);
        return false;
      }
    } else {
      if (!is_array($plaintext) || !is_array($key))
      {
        $this->trigger_error('In ECB mode the first and second parameters should be byte arrays', E_USER_WARNING);
        return false;
      }
    }
    if (sizeof($key)*8 != $this->keySizeInBits)
    {
      $this->trigger_error('The key needs to be '. ( $this->keySizeInBits / 8 ) .' bytes in length', E_USER_WARNING);
      return false;
    }
    if ($mode == "CBC")
      $ct = $this->getRandomBytes($bpb);             // get IV
    else {
      $mode = "ECB";
      $ct = Array();
    }
    
    // convert plaintext to byte array and pad with zeros if necessary. 
    $plaintext = $this->formatPlaintext($plaintext);
    
    $expandedKey = $this->keyExpansion($key);
    
    for ($block=0; $block<sizeof($plaintext) / $bpb; $block++) {
      $aBlock = $this->array_slice_js_compat($plaintext, $block*$bpb, ($block+1)*$bpb);
      if ($mode == "CBC")
      {
        for ($i=0; $i<$bpb; $i++)
        {
          $aBlock[$i] ^= $ct[$block*$bpb + $i];
        }
      }
      $cp = $this->cryptBlock($aBlock, $expandedKey);
      $ct = $this->concat($ct, $cp);
    }
  
    return $ct;
  }
  
  // rijndaelDecrypt(ciphertext, key, mode)
  // Decrypts the using the given key and mode. The parameter "ciphertext" 
  // must be an array of bytes. The parameter "key" must be an array of key 
  // bytes. If you have a hex string representing the ciphertext or key, 
  // invoke hexToByteArray() on it to convert it to an array of bytes. The
  // parameter "mode" is a string, either "CBC" or "ECB".
  // 
  // An array of bytes representing the plaintext is returned. To convert 
  // this array to a hex string, invoke byteArrayToHex() on it. To convert it 
  // to a string of characters, you can use byteArrayToString().
  
  function rijndaelDecrypt($ciphertext, $key, $mode = 'ECB') {
    //global $this->blockSizeInBits, $this->keySizeInBits;
    $bpb = $this->blockSizeInBits / 8;          // bytes per block
    $pt = Array();                   // plaintext array
    // $aBlock;                             // a decrypted block
    // $block;                              // current block number
  
    if (!$ciphertext)
    {
      $this->trigger_error('$ciphertext should be a byte array', E_USER_WARNING);
      return false;
    }
    if(  !is_array($key) )
    {
      $this->trigger_error('$key should be a byte array', E_USER_WARNING);
      return false;
    }
    if( is_string($ciphertext) )
    {
      $this->trigger_error('$ciphertext should be a byte array', E_USER_WARNING);
      return false;
    }
    if (sizeof($key)*8 != $this->keySizeInBits)
    {
      $this->trigger_error('Encryption key is the wrong length', E_USER_WARNING);
      return false;
    }
    if (!$mode)
      $mode = "ECB";                         // assume ECB if mode omitted
  
    $expandedKey = $this->keyExpansion($key);
   
    // work backwards to accomodate CBC mode 
    for ($block=(sizeof($ciphertext) / $bpb)-1; $block>0; $block--)
    {
      if( ( $block*$bpb ) + ( ($block+1)*$bpb ) > count($ciphertext) )
      {
        //$this->trigger_error('$ciphertext index out of bounds', E_USER_ERROR);
      }
      $current_block = $this->array_slice_js_compat($ciphertext, $block*$bpb, ($block+1)*$bpb);
      if(count($current_block) * 8 != $this->blockSizeInBits)
      {
        // $c=count($current_block)*8;
        // $this->trigger_error('We got a '.$c.'-bit block, instead of '.$this->blockSizeInBits.'', E_USER_ERROR);
      }
      $aBlock = $this->uncryptBlock($current_block, $expandedKey);
      if(!$aBlock)
      {
        $this->trigger_error('Shared block decryption routine returned false', E_USER_WARNING);
        return false;
      }
      if ($mode == "CBC")
        for ($i=0; $i<$bpb; $i++) 
          $pt[($block-1)*$bpb + $i] = $aBlock[$i] ^ $ciphertext[($block-1)*$bpb + $i];
      else
        $pt = $this->concat($aBlock, $pt);
    }
  
    // do last block if ECB (skips the IV in CBC)
    if ($mode == "ECB")
    {
      $x = $this->uncryptBlock($this->array_slice_js_compat($ciphertext, 0, $bpb), $expandedKey);
      if(!$x)
      {
        $this->trigger_error('ECB block decryption routine returned false', E_USER_WARNING);
        return false;
      }
      $pt = $this->concat($x, $pt);
      if(!$pt)
      {
        $this->trigger_error('ECB concatenation routine returned false', E_USER_WARNING);
        return false;
      }
    }
  
    return $pt;
  }
  
  /**
   * Wrapper for encryption.
   * @param string $text the text to encrypt
   * @param string $key the raw binary key to encrypt with
   * @param int $return_encoding optional - can be ENC_BINARY, ENC_HEX or ENC_BASE64
   */
   
  function encrypt($text, $key, $return_encoding = ENC_HEX)
  {
    if ( $text == '' )
      return '';
    if ( $this->mcrypt && $this->blockSizeInBits == mcrypt_module_get_algo_block_size(eval('return MCRYPT_RIJNDAEL_'.$this->keySizeInBits.';')) )
    {
      $iv_size = mcrypt_get_iv_size($this->mcrypt, MCRYPT_MODE_ECB);
      $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
      $cryptext = mcrypt_encrypt($this->mcrypt, $key, $text, MCRYPT_MODE_ECB, $iv);
      switch($return_encoding)
      {
        case ENC_HEX:
        default:
          $cryptext = $this->strtohex($cryptext);
          break;
        case ENC_BINARY:
          $cryptext = $cryptext;
          break;
        case ENC_BASE64:
          $cryptext = base64_encode($cryptext);
          break;
      }
    }
    else
    {
      $key = $this->prepare_string($key);
      $text = $this->prepare_string($text);
      $cryptext = $this->rijndaelEncrypt($text, $key, 'ECB');
      if(!is_array($cryptext))
      {
        echo 'Warning: encryption failed for string: '.print_r($text,true).'<br />';
        return false;
      }
      switch($return_encoding)
      {
        case ENC_HEX:
        default:
          $cryptext = $this->byteArrayToHex($cryptext);
          break;
        case ENC_BINARY:
          $cryptext = $this->byteArrayToString($cryptext);
          break;
        case ENC_BASE64:
          $cryptext = base64_encode($this->byteArrayToString($cryptext));
          break;
      }
    }
    return $cryptext;
  }
  
  /**
   * Wrapper for decryption.
   * @param string $text the encrypted text
   * @param string $key the raw binary key used to encrypt the text
   * @param int $input_encoding the encoding used for the encrypted string. Can be ENC_BINARY, ENC_HEX, or ENC_BASE64.
   * @return string
   */
   
  function decrypt($text, $key, $input_encoding = ENC_HEX)
  {
    if ( $text == '' )
      return '';
    $text_orig = $text;
    if ( isset($this->decrypt_cache[$key]) && is_array($this->decrypt_cache[$key]) )
    {
      if ( isset($this->decrypt_cache[$key][$text]) )
      {
        return $this->decrypt_cache[$key][$text];
      }
    }
    switch($input_encoding)
    {
      case ENC_BINARY:
      default:
        break;
      case ENC_HEX:
        $text = $this->hextostring($text);
        break;
      case ENC_BASE64:
        $text = base64_decode($text);
        break;
    }
    //$mod = strlen($text) % $this->blockSizeInBits;
    //if($mod != 96)
      //die('modulus check failed: '.$mod);
    if ( $this->mcrypt )
    {
      $iv_size = mcrypt_get_iv_size($this->mcrypt, MCRYPT_MODE_ECB);
      $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
      $dypt = mcrypt_decrypt($this->mcrypt, $key, $text, MCRYPT_MODE_ECB, $iv);
    }
    else
    {
      $etext = $this->prepare_string($text);
      $ekey  = $this->prepare_string($key);
      $mod = count($etext) % $this->blockSizeInBits;
      $dypt = $this->rijndaelDecrypt($etext, $ekey, 'ECB');
      if(!$dypt)
      {
        echo '<pre>'.print_r($dypt, true).'</pre>';
        $this->trigger_error('Rijndael main decryption routine failed', E_USER_ERROR);
      }
      $dypt = $this->byteArrayToString($dypt);
    }
    if ( !isset($this->decrypt_cache[$key]) )
      $this->decrypt_cache[$key] = array();
    
    $this->decrypt_cache[$key][$text_orig] = $dypt;
    
    return $dypt;
  }
  
  /**
   * Enano-ese equivalent of str_split() which is only found in PHP5
   * @param $text string the text to split
   * @param $inc int size of each block
   * @return array
   */
   
  function enano_str_split($text, $inc = 1)
  {
    if($inc < 1) return false;
    if($inc >= strlen($text)) return Array($text);
    $len = ceil(strlen($text) / $inc);
    $ret = Array();
    for($i=0;$i<strlen($text);$i=$i+$inc)
    {
      $ret[] = substr($text, $i, $inc);
    }
    return $ret;
  }
  
  /**
   * Generates a random key suitable for encryption
   * @param int $len the length of the key, in bytes
   * @return string a BINARY key
   */
  
  function randkey($len = 32)
  {
    $key = '';
    for($i=0;$i<$len;$i++)
    {
      $key .= chr(mt_rand(0, 255));
    }
    if ( file_exists('/dev/urandom') && is_readable('/dev/urandom') )
    {
      // Let's use something a little more secure
      $ur = @fopen('/dev/urandom', 'r');
      if ( !$ur )
        return $key;
      $ukey = @fread($ur, $len);
      fclose($ur);
      if ( strlen($ukey) != $len )
        return $key;
      return $ukey;
    }
    return $key;
  }
  
  /*
  function byteArrayToString($arr)
  {
    if(!is_array($arr))
    {
      $this->trigger_error('First parameter should be an array', E_USER_WARNING);
      return false;
    }
    $ret = '';
    foreach($arr as $a)
    {
      if($a != 0) $ret .= chr($a);
    }
    return $ret;
  }
  */
  
  function strtohex($str)
  {
    $str = $this->enano_str_split($str);
    $ret = '';
    foreach($str as $s)
    {
      $chr = dechex(ord($s));
      if(strlen($chr) < 2) $chr = '0' . $chr;
      $ret .= $chr;
    }
    return $ret;
  }
  
  function gen_readymade_key()
  {
    $key = $this->strtohex($this->randkey($this->keySizeInBits / 8));
    return $key;
  }
  
  function prepare_string($text)
  {
    $ret = $this->hexToByteArray($this->strtohex($text));
    if(count($ret) != strlen($text))
    {
      die('Could not convert string "' . $text . '" to hex byte array for encryption');
    }
    return $ret;
  }
  
  /**
   * Decodes a hex string.
   * @param string $hex The hex code to decode
   * @return string
   */
  
  function hextostring($hex)
  {
    $hex = $this->enano_str_split($hex, 2);
    $bin_key = '';
    foreach($hex as $nibble)
    {
      $byte = chr(hexdec($nibble));
      $bin_key .= $byte;
    }
    return $bin_key;
  }
}

/**
 * XXTEA encryption arithmetic library.
 *
 * Copyright (C) 2006 Ma Bingyao <andot@ujn.edu.cn>
 * Version:      1.5
 * LastModified: Dec 5, 2006
 * This library is free.  You can redistribute it and/or modify it.
 * 
 * From dandaman32: I am treating this code as GPL, as implied by the license statement above.
 */
class TEACrypt extends AESCrypt {
  function long2str($v, $w) {
      $len = count($v);
      $n = ($len - 1) << 2;
      if ($w) {
          $m = $v[$len - 1];
          if (($m < $n - 3) || ($m > $n)) return false;
          $n = $m;
      }
      $s = array();
      for ($i = 0; $i < $len; $i++) {
          $s[$i] = pack("V", $v[$i]);
      }
      if ($w) {
          return substr(join('', $s), 0, $n);
      }
      else {
          return join('', $s);
      }
  }
   
  function str2long($s, $w) {
      $v = unpack("V*", $s. str_repeat("\0", (4 - strlen($s) % 4) & 3));
      $v = array_values($v);
      if ($w) {
          $v[count($v)] = strlen($s);
      }
      return $v;
  }
   
  function int32($n) {
      while ($n >= 2147483648) $n -= 4294967296;
      while ($n <= -2147483649) $n += 4294967296;
      return (int)$n;
  }
   
  function encrypt($str, $key) {
      if ($str == "")
      {
          return "";
      }
      $v = $this->str2long($str, true);
      $k = $this->str2long($key, false);
      if (count($k) < 4) {
          for ($i = count($k); $i < 4; $i++) {
              $k[$i] = 0;
          }
      }
      $n = count($v) - 1;
   
      $z = $v[$n];
      $y = $v[0];
      $delta = 0x9E3779B9;
      $q = floor(6 + 52 / ($n + 1));
      $sum = 0;
      while (0 < $q--) {
          $sum = $this->int32($sum + $delta);
          $e = $sum >> 2 & 3;
          for ($p = 0; $p < $n; $p++) {
              $y = $v[$p + 1];
              $mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
              $z = $v[$p] = $this->int32($v[$p] + $mx);
          }
          $y = $v[0];
          $mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
          $z = $v[$n] = $this->int32($v[$n] + $mx);
      }
      return $this->long2str($v, false);
  }
   
  function decrypt($str, $key) {
      if ($str == "") {
          return "";
      }
      $v = $this->str2long($str, false);
      $k = $this->str2long($key, false);
      if (count($k) < 4) {
          for ($i = count($k); $i < 4; $i++) {
              $k[$i] = 0;
          }
      }
      $n = count($v) - 1;
   
      $z = $v[$n];
      $y = $v[0];
      $delta = 0x9E3779B9;
      $q = floor(6 + 52 / ($n + 1));
      $sum = $this->int32($q * $delta);
      while ($sum != 0) {
          $e = $sum >> 2 & 3;
          for ($p = $n; $p > 0; $p--) {
              $z = $v[$p - 1];
              $mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
              $y = $v[$p] = $this->int32($v[$p] - $mx);
          }
          $z = $v[$n];
          $mx = $this->int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ $this->int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
          $y = $v[0] = $this->int32($v[0] - $mx);
          $sum = $this->int32($sum - $delta);
      }
      return $this->long2str($v, true);
  }
}

?>
