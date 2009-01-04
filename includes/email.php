<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 * 
 * The "emailer" class was ported from phpBB 2.0. Copyright (C) 2002-2006 phpBB Group. phpBB is licensed under the GPLv2.
 */
 
//
// The emailer class has support for attaching files, that isn't implemented
// in the 2.0 release but we can probable find some way of using it in a future
// release
//
class emailer
{
  var $msg, $subject, $extra_headers;
  var $addresses, $reply_to, $from;
  var $use_smtp;

  var $tpl_msg;

  function __construct($use_smtp)
  {
    $this->reset();
    $this->use_smtp = $use_smtp;
    $this->reply_to = $this->from = '';
  }

  // Resets all the data (address, template file, etc etc to default
  function reset()
  {
    $this->addresses = array();
    $this->vars = $this->msg = $this->extra_headers = '';
  }

  // Sets an email address to send to
  function email_address($address)
  {
    $this->addresses['to'] = trim($address);
  }

  function cc($address)
  {
    $this->addresses['cc'][] = trim($address);
  }

  function bcc($address)
  {
    $this->addresses['bcc'][] = trim($address);
  }

  function replyto($address)
  {
    $this->reply_to = trim($address);
  }

  function from($address)
  {
    $this->from = trim($address);
  }

  // set up subject for mail
  function set_subject($subject = '')
  {
    $this->subject = trim(preg_replace('#[\n\r]+#s', '', $subject));
  }

  // set up extra mail headers
  function extra_headers($headers)
  {
    $this->extra_headers .= trim($headers) . "\n";
  }

  function use_template($template_code)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->tpl_msg = $template->makeParserText($template_code);

    return true;
  }

  // assign variables
  function assign_vars($vars)
  {
    if ( is_object($this->tpl_msg) )
    {
      $this->tpl_msg->assign_vars($vars);
    }
    else
    {
      die_friendly(GENERAL_ERROR, 'Can\'t set vars, the template is not set');
    }
  }

  // Send the mail out to the recipients set previously in var $this->address
  function send()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->msg = $this->tpl_msg->run();
    if ( empty($this->msg) )
    {
      die_friendly(GENERAL_ERROR, 'Template for e-mail message returned a blank');
    }

    // We now try and pull a subject from the email body ... if it exists,
    // do this here because the subject may contain a variable
    $drop_header = '';
    $match = array();
    if (preg_match('#^(Subject:(.*?))$#m', $this->msg, $match))
    {
      $this->subject = (trim($match[2]) != '') ? trim($match[2]) : (($this->subject != '') ? $this->subject : 'No Subject');
      $drop_header .= '[\r\n]*?' . preg_quote($match[1], '#');
    }
    else
    {
      $this->subject = (($this->subject != '') ? $this->subject : 'No Subject');
    }

    if (preg_match('#^(Charset:(.*?))$#m', $this->msg, $match))
    {
      $this->encoding = (trim($match[2]) != '') ? trim($match[2]) : trim('iso-8859-1');
      $drop_header .= '[\r\n]*?' . preg_quote($match[1], '#');
    }
    else
    {
      $this->encoding = trim('iso-8859-1');
    }

    if ($drop_header != '')
    {
      $this->msg = trim(preg_replace('#' . $drop_header . '#s', '', $this->msg));
    }

    $to = $this->addresses['to'];

    $cc = (count($this->addresses['cc'])) ? implode(', ', $this->addresses['cc']) : '';
    $bcc = (count($this->addresses['bcc'])) ? implode(', ', $this->addresses['bcc']) : '';

    // Build header
    $this->extra_headers = (($this->reply_to != '') ? "Reply-to: $this->reply_to\n" : '') .
                           (($this->from != '') ? "From: $this->from\n" : "From: " . getConfig('contact_email') . "\n") .
                           "Return-Path: " . getConfig('contact_email') .
                           "\nMessage-ID: <" . md5(uniqid(time())) . "@" . $_SERVER['SERVER_NAME'] . ">\nMIME-Version: 1.0\nContent-type: text/plain; charset=" . $this->encoding .
                           "\nContent-transfer-encoding: 8bit\nDate: " . enano_date('r', time()) .
                           "\nX-Priority: 3\nX-MSMail-Priority: Normal\nX-Mailer: PHP\nX-MimeOLE: Produced By Enano CMS\n" .
                           $this->extra_headers .
                           (($cc != '') ? "Cc: $cc\n" : '')  .
                           (($bcc != '') ? "Bcc: $bcc\n" : '');
    
    //die('<pre>'.print_r($this,true).'</pre>');

    // Send message ... removed $this->encode() from subject for time being
    if ( $this->use_smtp )
    {
      $result = smtp_send_email_core($to, $this->subject, $this->msg, $this->extra_headers);
    }
    else
    {
      $empty_to_header = ($to == '') ? TRUE : FALSE;
      $to = ($to == '') ? ((getConfig('sendmail_fix')=='1') ? ' ' : 'Undisclosed-recipients:;') : $to;
  
      $result = @mail($to, $this->subject, preg_replace("#(?<!\r)\n#s", "\n", $this->msg), $this->extra_headers);
      
      if (!$result && !getConfig('sendmail_fix') && $empty_to_header)
      {
        $to = ' ';

        setConfig('sendmail_fix', '1');
        
        $result = @mail($to, $this->subject, preg_replace("#(?<!\r)\n#s", "\n", $this->msg), $this->extra_headers);
      }
    }

    // Did it work?
    if (!$result || ( $this->use_smtp && $result != 'success' ))
    {
      die_friendly(GENERAL_ERROR, 'Failed sending email :: ' . (($this->use_smtp) ? 'SMTP' : 'PHP') . ' :: ' . $result);
    }

    return true;
  }

  // Encodes the given string for proper display for this encoding ... nabbed 
  // from php.net and modified. There is an alternative encoding method which 
  // may produce lesd output but it's questionable as to its worth in this 
  // scenario IMO
  function encode($str)
  {
    if ($this->encoding == '')
    {
      return $str;
    }

    // define start delimimter, end delimiter and spacer
    $end = "?=";
    $start = "=?$this->encoding?B?";
    $spacer = "$end\r\n $start";

    // determine length of encoded text within chunks and ensure length is even
    $length = 75 - strlen($start) - strlen($end);
    $length = floor($length / 2) * 2;

    // encode the string and split it into chunks with spacers after each chunk
    $str = chunk_split(base64_encode($str), $length, $spacer);

    // remove trailing spacer and add start and end delimiters
    $str = preg_replace('#' . preg_quote($spacer, '#') . '$#', '', $str);

    return $start . $str . $end;
  }

  //
  // Attach files via MIME.
  //
  function attachFile($filename, $mimetype = "application/octet-stream", $szFromAddress, $szFilenameToDisplay)
  {
    global $lang;
    $mime_boundary = "--==================_846811060==_";

    $this->msg = '--' . $mime_boundary . "\nContent-Type: text/plain;\n\tcharset=".'"' . $lang['ENCODING'] . '"'."\n\n" . $this->msg;

    if ($mime_filename)
    {
      $filename = $mime_filename;
      $encoded = $this->encode_file($filename);
    }

    $fd = fopen($filename, "r");
    $contents = fread($fd, filesize($filename));

    $this->mimeOut = "--" . $mime_boundary . "\n";
    $this->mimeOut .= "Content-Type: " . $mimetype . ";\n\tname=".'"'."$szFilenameToDisplay".'"'."\n";
    $this->mimeOut .= "Content-Transfer-Encoding: quoted-printable\n";
    $this->mimeOut .= "Content-Disposition: attachment;\n\tfilename=".'"'."$szFilenameToDisplay".'"'."\n\n";

    if ( $mimetype == "message/rfc822" )
    {
      $this->mimeOut .= "From: ".$szFromAddress."\n";
      $this->mimeOut .= "To: ".$this->emailAddress."\n";
      $this->mimeOut .= "Date: ".enano_date("D, d M Y H:i:s") . " UT\n";
      $this->mimeOut .= "Reply-To:".$szFromAddress."\n";
      $this->mimeOut .= "Subject: ".$this->mailSubject."\n";
      $this->mimeOut .= "X-Mailer: PHP/".phpversion()."\n";
      $this->mimeOut .= "MIME-Version: 1.0\n";
    }

    $this->mimeOut .= $contents."\n";
    $this->mimeOut .= "--" . $mime_boundary . "--" . "\n";

    return $out;
    // added -- to notify email client attachment is done
  }

  function getMimeHeaders($filename, $mime_filename="")
  {
    $mime_boundary = "--==================_846811060==_";

    if ($mime_filename)
    {
      $filename = $mime_filename;
    }

    $out = "MIME-Version: 1.0\n";
    $out .= "Content-Type: multipart/mixed;\n\tboundary=".'"'."$mime_boundary".'"'."\n\n";
    $out .= "This message is in MIME format. Since your mail reader does not understand\n";
    $out .= "this format, some or all of this message may not be legible.";

    return $out;
  }

  //
   // Split string by RFC 2045 semantics (76 chars per line, end with \r\n).
  //
  function myChunkSplit($str)
  {
    $stmp = $str;
    $len = strlen($stmp);
    $out = "";

    while ($len > 0)
    {
      if ($len >= 76)
      {
        $out .= substr($stmp, 0, 76) . "\r\n";
        $stmp = substr($stmp, 76);
        $len = $len - 76;
      }
      else
      {
        $out .= $stmp . "\r\n";
        $stmp = "";
        $len = 0;
      }
    }
    return $out;
  }

  //
   // Split the specified file up into a string and return it
  //
  function encode_file($sourcefile)
  {
    if (is_readable(@realpath($sourcefile)))
    {
      $fd = fopen($sourcefile, "r");
      $contents = fread($fd, filesize($sourcefile));
        $encoded = $this->myChunkSplit(base64_encode($contents));
        fclose($fd);
    }

    return $encoded;
  }

} // class emailer


/**
 * This code is copyright (C) 2004 Jim Tucek
 * PHP version ported from Javascript by Dan Fuhry
 * All rights reserved.
 * @link http://www.jracademy.com/~jtucek/email/
 * @license GNU General Public License v2, permission obtained specifically for Enano
 */

class EmailEncryptor
{
 
  var $primes = Array(2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97, 101, 103, 107, 109, 113, 127, 131, 137, 139, 149, 151, 157, 163, 167, 173, 179, 181, 191, 193, 197, 199);
  
  function __construct()
  {
    $i = 0;
    $this->p = 0;
    $this->q = 0;
    while($this->p * $this->q < 255 || $this->p == $this->q)
    {
      $this->p = $this->primes[mt_rand(0, sizeof($this->primes)-1)];
      $this->q = $this->primes[mt_rand(0, sizeof($this->primes)-1)];
    }
  }
  
  function testAll() {
    $size = sizeof($this->primes);
    
    $allCharacters = "";
    for($c = 33; $c <= 126; $c++)
      $allCharacters = $allCharacters . $this->fromCharCode($c);
    
    for($i = 0; $i < $size - 1; $i++) {
      for($j = $i + 1; $j < $size; $j++) {
        $this->p = $this->primes[$i];
        $this->q = $this->primes[$j];
        if($this->p*$this->q < 255)
          break;
        $k = $this->makeKey($allCharacters);
        $encrypted = $k['X'];
        $decrypted = $this->goForth($encrypted,$this->p*$this->q,$k['D']);
        if($decrypted != $allCharacters) {
          die('Test failed');
        }
      }
    }
    return 'GOOD';
  }
  
  function charCodeAt($str, $i)
  {
    return ord(substr($str, $i, 1));
  }
  
  function fromCharCode($str)
  {
    return chr($str);
  }
  
  function MakeArray($l) {
    $a = Array();
    $i=0;
    do {
      $a[$i]=null;
      $i++;
    } while($i < $l);
    return $a;
  }
  
  function makeKey($addr,$subj = '',$body = '') {
    $value = "";
    
    if($this->p * $this->q < 255)
    {
      return("P*Q must be greater than 255! P*Q = " . $this->p*$this->q);
    }
    elseif($this->p == $this->q)
    {
      return("P cannot be equal to Q!");
    }
    elseif($addr == "")
    {
      return("You must enter an address to encrypt!");
    }
    else
    {
      // Make the key
      $c = 0;
      $z = ($this->p-1)*($this->q-1);
      $e = 0;
      $n = $this->p*$this->q;
      $d = 0;
    
      do {
        $e++;
        $d = $this->getKey($this->primes[$e],$z);
      } while($d==1);
      $e = $this->primes[$e];
      
      // Turn the string into an array of numbers < 255
      $m = $addr;
      $emailLength = strlen($m);
      $justEmail = "";
      $sep = ( strstr('?', $m) ) ? '&' : '?';
      if($subj != "") {
        $m = $m . "{$sep}subject=" . $subj;
      }
      $sep = ( strstr($m, '?') ) ? '&' : '?';
      if($body != "") {
        $m = $m . "{$sep}body=" . $body;
      }
    
      $length = strlen($m);
      $theString = $this->MakeArray($length);
      for($i = 0; $i < $length; $i++) {
        $theString[$i] = $this->charCodeAt($m, $i);
      }
      
      // Encrypt each of the numbers
      $theCode = $this->MakeArray($length);
      $c = "";
      $temp = 0;
      for($i = 0; $i < $length; $i++) {
        if($i != 0)
          $c .= " ";
        $temp = $this->myMod($theString[$i],$e,$n);
        $theCode[$i] = $temp;
        $c .= $temp;
        if($i == $emailLength - 1)
          $justEmail = $c;
      }
    }
    return Array('X'=>$justEmail, 'N'=>$n, 'D'=>$d, 'E'=>$e, 'C'=>$c, 'M'=>$m);
  }
    
  // Finds x^e % y for large values of (x^e)
  function myMod($x,$e,$y) {
    if ($e % 2 == 0) {
      $answer = 1;
      for($i = 1; $i <= e/2; $i++) {
        $temp = ($x*$x) % $y;
        $answer = ($temp*$answer) % $y;
      }
    } else {
      $answer = $x;
      for($i = 1; $i <= $e/2; $i++) {
        $temp = ($x*$x) % $y;
        $answer = ($temp*$answer) % $y;
      }
    }
    return $answer;
  }
  
  
  function getKey($e,$z) {
    $A = 1;
    $B = 0;
    $C = $z;
    $F = 0;
    $G = 1;
    $bar = $e;    
    // Euclid's Algorithm:
    while ($bar != 0) {
      $foo = floor($C/$bar);
      $K = $A - $foo * $F;
      $L = $B - $foo * $G;
      $M = $C - $foo * $bar;
      $A = $F;
      $B = $G;
      $C = $bar;
      $F = $K;
      $G = $L;
      $bar = $M;
    }
    if ($B < 0)
    {
      return ($B + $z);
    }
    else
    {
      return ($B);
    }
  }
  
  function goForth($c,$n,$d) {
    $c .= " "; 
    $length = strlen($c);
    $number = 0;
    $bar = 0;
    $answer = "";
  
    for($i = 0; $i < $length; $i++) {
      $number = 0;
      $bar = 0;
      while($this->charCodeAt($c, $i) != 32) { 
        $number = $number * 10;
        $number = $number + $this->charCodeAt($c, $i)-48;
        $i++;
      }
      $answer .= $this->fromCharCode($this->decrypt($number,$n,$d));
    }
    return $answer;
  }
  
  function decrypt($c,$n,$d) {
    // Split exponents up
    if ($d % 2== 0) {
      $bar = 1;
      for($i = 1; $i <= $d/2; $i++) {
        $foo = ($c*$c) % $n;
        $bar = ($foo*$bar) % $n;
      }
    } else {
      $bar = $c;
      for($i = 1; $i <= $d/2; $i++) {
        $foo = ($c*$c) % $n;
        $bar = ($foo*$bar) % $n;
      }
    }
    return $bar;
  }
  
  function writeOptions() {
    $size = sizeof($this->primes);
    for($i = 0; $i < $size; $i++)
      echo("<option value=".'"'.""+$this->primes[$i]+"".'"'.">"+$this->primes[$i]+"</option>");
  }
  
  function jscode() {
    return "<script type='text/javascript'>\n// <![CDATA[\nfunction dive(absorption,alchemy,friendship) { absorption += ' '; var file = absorption.length; var sand = 0; var closet = ''; for(var assistant = 0; assistant < file; assistant++) { sand = 0; while(absorption.charCodeAt(assistant) != 32) { sand = sand * 10; sand = sand + absorption.charCodeAt(assistant)-48; assistant++; } closet += String.fromCharCode(say(sand,alchemy,friendship)); } parent.location = 'm'+'a'+'i'+'l'+'t'+'o'+':'+closet; }; function forbid(landing,atmosphere,aviation) { landing += ' '; var kiss = landing.length; var coordinated = 0; for(var day = 0; day < kiss; day++) { coordinated = 0; while(landing.charCodeAt(day) != 32) { coordinated = coordinated * 10; coordinated = coordinated + landing.charCodeAt(day)-48; day++; } document.write(String.fromCharCode(say(coordinated,atmosphere,aviation))); }; }; function say(scene,photograph,fraction) { if (fraction % 2 == 0) { integrity = 1; for(var male = 1; male <= fraction/2; male++) { moon = (scene*scene) % photograph; integrity = (moon*integrity) % photograph; } } else { integrity = scene; for(var night = 1; night <= fraction/2; night++) { moon = (scene*scene) % photograph; integrity = (moon*integrity) % photograph; }; }; return integrity; };\n// ]]>\n</script>";
  }
  
  /**
   * Wrapper - spits out ready-to-use HTML
   * @param string $address The e-mail address
   * @param string $subject The subject of the e-mail. OPTIONAL.
   * @param string $body The main content of the e-mail. OPTIONAL and doesn't work in many e-mail clients.
   * @param string $text The text to be shown on the e-mail link. Leave as false to make the e-mail address be shown in the link (but still fully encrypted)
   */
  
  function encryptEmail($address, $subject = '', $body = '', $text = false)
  {
    $key = $this->makeKey($address, $subject, $body);
    if ( $text )
    {
      if(preg_match('/^(mailto:)?(?:[\w\d]+\.?)+@(?:(?:[\w\d]\-?)+\.)+\w{2,4}$/', $text))
      {
        // This is a mailto link and normal obfuscation should be used
        $text = false;
      }
    }
    $text1 = ( $text ) ? '<script type="text/javascript">document.write(unescape(\''.rawurlencode($text).'\'));</script>' : '<script type=\'text/javascript\'>forbid("'.$key['X'].'",'.$key['N'].','.$key['D'].')</script>';
    $text2 = ( $text ) ? "$text &lt;".$this->obfuscate_text($this->mask_address($address))."&gt;" : $this->obfuscate_text($this->mask_address($address));
    $email = '<a href="#" onclick=\'dive("'.$key['C'].'",'.$key['N'].','.$key['D'].'); return false;\' onmouseover="self.status=\'\'; return true;" onmouseout="self.status=\' \'; return true;">'.$text1.'</a><noscript><div style="display: inline">'.$text2.'</div></noscript>';
    return $email;
  }
  
  /** 
   * Replace @ symbols with " <AT> " and dots with " <DOT> ".
   * @param string $email An e-mail address.
   * @return string
   */
   
  function mask_address($email)
  {
    $at = array(' (AT) ', ' __AT__ ', ' *AT* ', ' [AT] ', ' <AT> ', ' <__AT__> ');
    $dot = array(' (DOT) ', ' __DOT__ ', ' *DOT* ', ' [DOT] ', ' <DOT> ', ' <__DOT__> ');
    while(strstr($email, '@'))
    {
      $my_at = $at[ array_rand($at) ];
      $email = str_replace_once('@', $my_at, $email);
    }
    while(strstr($email, '.'))
    {
      $my_dot = $dot[ array_rand($dot) ];
      $email = str_replace_once('.', $my_dot, $email);
    }
    return $email;
  }
  
  /**
   * Turn a string of text into hex-encoded HTML entities
   * @param string $text the text to encode
   * @return string
   */

  function obfuscate_text($text)
  {
    $a = enano_str_split($text, 1);
    $s = '';
    foreach($a as $k => $c)
    {
      $ch = (string)dechex(ord($a[$k]));
      if(strlen($ch) < 2) $ch = '0' . $ch;
      $s .= '&#x'.$ch.';';
    }
    return $s;
  }

}

?>
