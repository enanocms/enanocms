<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Fetch a value from the site configuration.
 * @param string The identifier of the value ("site_name" etc.)
 * @param string If specified, this is the "default" value for the configuration entry. Defaults to false.
 * @return string Configuration value, or bool(false) if the value is not set
 */

function getConfig($n, $default = false)
{
  global $enano_config;
  if ( isset( $enano_config[ $n ] ) )
  {
    return $enano_config[$n];
  }
  else
  {
    return $default;
  }
}

/**
 * Update or change a configuration value.
 * @param string The identifier of the value ("site_name" etc.)
 * @param string The new value
 * @return null
 */

function setConfig($n, $v)
{
  global $enano_config, $db;
  
  if ( isset($enano_config[$n]) )
  {
    if ( $enano_config[$n] === $v )
    {
      // configuration already matches this value
      return true;
    }
  }
  
  $enano_config[$n] = $v;
  $v = $db->escape($v);

  $e = $db->sql_query('DELETE FROM '.table_prefix.'config WHERE config_name=\''.$n.'\';');
  if ( !$e )
  {
    $db->_die('Error during generic setConfig() call row deletion.');
  }

  $e = $db->sql_query('INSERT INTO '.table_prefix.'config(config_name, config_value) VALUES(\''.$n.'\', \''.$v.'\')');
  if ( !$e )
  {
    $db->_die('Error during generic setConfig() call row insertion.');
  }
}

/**
 * Create a URI for an internal link.
 * @param string The full identifier of the page to link to (Special:Administration)
 * @param string The GET query string to append
 * @param bool   If true, perform htmlspecialchars() on the return value to make it HTML-safe
 * @return string
 */

if ( !function_exists('makeUrl') )
{
  function makeUrl($t, $query = false, $escape = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $flags = '';
    $sep = urlSeparator;
    $t = sanitize_page_id($t);
    if ( isset($_GET['printable'] ) )
    {
      $flags .= $sep . 'printable=yes';
      $sep = '&';
    }
    if ( isset($_GET['theme'] ) )
    {
      $flags .= $sep . 'theme='.$session->theme;
      $sep = '&';
    }
    if ( isset($_GET['style'] ) )
    {
      $flags .= $sep . 'style='.$session->style;
      $sep = '&';
    }
    if ( isset($_GET['lang']) && preg_match('/^[a-z0-9_]+$/', @$_GET['lang']) )
    {
      $flags .= $sep . 'lang=' . urlencode($_GET['lang']);
      $sep = '&';
    }
  
    $url = $session->append_sid(contentPath.$t.$flags);
    if($query)
    {
      $sep = strstr($url, '?') ? '&' : '?';
      $url = $url . $sep . $query;
    }
  
    return ($escape) ? htmlspecialchars($url) : $url;
  }
}

/**
 * Create a URI for an internal link, and be namespace-friendly. Watch out for this one because it's different from most other Enano functions, in that the namespace is the first parameter.
 * @param string The namespace ID
 * @param string The page ID
 * @param string The GET query string to append
 * @param bool   If true, perform htmlspecialchars() on the return value to make it HTML-safe
 * @return string
 */

if ( !function_exists('makeUrlNS') )
{
  function makeUrlNS($n, $t, $query = false, $escape = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $flags = '';
  
    if(defined('ENANO_BASE_CLASSES_INITIALIZED'))
    {
      $sep = urlSeparator;
    }
    else
    {
      $sep = (strstr($_SERVER['REQUEST_URI'], '?')) ? '&' : '?';
    }
    if ( isset( $_GET['printable'] ) ) {
      $flags .= $sep . 'printable';
      $sep = '&';
    }
    if ( isset( $_GET['theme'] ) )
    {
      $flags .= $sep . 'theme='.$session->theme;
      $sep = '&';
    }
    if ( isset( $_GET['style'] ) )
    {
      $flags .= $sep . 'style='.$session->style;
      $sep = '&';
    }
    if ( isset($_GET['lang']) && preg_match('/^[a-z0-9_]+$/', @$_GET['lang']) )
    {
      $flags .= $sep . 'lang=' . urlencode($_GET['lang']);
      $sep = '&';
    }
    
    $ns_prefix = "$n:";
  
    if(defined('ENANO_BASE_CLASSES_INITIALIZED'))
    {
      $ns_prefix = ( isset($paths->nslist[$n]) ) ? $paths->nslist[$n] : $n . substr($paths->nslist['Special'], -1);
      $url = contentPath . $ns_prefix . $t . $flags;
    }
    else
    {
      // If the path manager hasn't been initted yet, take an educated guess at what the URI should be
      $url = contentPath . $n . ':' . $t . $flags;
    }
  
    if($query)
    {
      if(strstr($url, '?'))
      {
        $sep =  '&';
      }
      else
      {
        $sep = '?';
      }
      $url = $url . $sep . $query . $flags;
    }
  
    if(defined('ENANO_BASE_CLASSES_INITIALIZED'))
    {
      $url = $session->append_sid($url);
    }
  
    return ($escape) ? htmlspecialchars($url) : $url;
  }
}

/**
 * Create a URI for an internal link, be namespace-friendly, and add http://hostname/scriptpath to the beginning if possible. Watch out for this one because it's different from most other Enano functions, in that the namespace is the first parameter.
 * @param string The namespace ID
 * @param string The page ID
 * @param string The GET query string to append
 * @param bool   If true, perform htmlspecialchars() on the return value to make it HTML-safe
 * @return string
 */

function makeUrlComplete($n, $t, $query = false, $escape = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $flags = '';

  if(defined('ENANO_BASE_CLASSES_INITIALIZED'))
  {
    $sep = urlSeparator;
  }
  else
  {
    $sep = (strstr($_SERVER['REQUEST_URI'], '?')) ? '&' : '?';
  }
  if ( isset( $_GET['printable'] ) ) {
    $flags .= $sep . 'printable';
    $sep = '&';
  }
  if ( isset( $_GET['theme'] ) )
  {
    $flags .= $sep . 'theme='.$session->theme;
    $sep = '&';
  }
  if ( isset( $_GET['style'] ) )
  {
    $flags .= $sep . 'style='.$session->style;
    $sep = '&';
  }
  if ( isset($_GET['lang']) && preg_match('/^[a-z0-9_]+$/', @$_GET['lang']) )
  {
    $flags .= $sep . 'lang=' . urlencode($_GET['lang']);
    $sep = '&';
  }

  if(defined('ENANO_BASE_CLASSES_INITIALIZED'))
  {
    $url = $session->append_sid(contentPath . $paths->nslist[$n] . $t . $flags);
  }
  else
  {
    // If the path manager hasn't been initted yet, take an educated guess at what the URI should be
    $url = contentPath . $n . ':' . $t . $flags;
  }
  if($query)
  {
    if(strstr($url, '?')) $sep =  '&';
    else $sep = '?';
    $url = $url . $sep . $query . $flags;
  }

  $baseprot = 'http' . ( isset($_SERVER['HTTPS']) ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'];
  $url = $baseprot . $url;

  return ($escape) ? htmlspecialchars($url) : $url;
}

/**
 * Returns the full page ID string of the main page.
 * @return string
 */

function get_main_page($force_logged_in = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $logged_in = false;
  if ( is_object($session) && !$force_logged_in )
  {
    $logged_in = $session->user_logged_in;
  }
  else if ( $force_logged_in )
  {
    $logged_in = true;
  }
  return $logged_in && getConfig('main_page_alt_enable', '0') == '1' ? getConfig('main_page_alt', getConfig('main_page')) : getConfig('main_page');
}

/**
 * Enano replacement for date(). Accounts for individual users' timezone preferences.
 * @param string Date-formatted string
 * @param int Optional - UNIX timestamp value to use. If omitted, the current time is used.
 * @return string Formatted string
 */

function enano_date($string, $timestamp = false)
{
  if ( !is_int($timestamp) && !is_double($timestamp) && strval(intval($timestamp)) !== $timestamp )
    $timestamp = time();
  
  // perform timestamp offset
  global $timezone;
  // it's gonna be in minutes, so multiply by 60 to offset the unix timestamp
  $timestamp = $timestamp + ( $timezone * 60 );
  
  // are we in DST?
  global $dst_params;
  if ( check_timestamp_dst($timestamp, $dst_params[0], $dst_params[1], $dst_params[2], $dst_params[3]) )
  {
    // offset for DST
    $timestamp += ( $dst_params[4] * 60 );
  }
  
  // Let PHP do the work for us =)
  return gmdate($string, $timestamp);
}

/**
 * Determine if a timestamp is within DST.
 * @param int Timestamp
 * @param int Start month (1-12) of DST
 * @param int Which Sunday DST starts on (*_SUNDAY constants)
 * @param int End month of DST
 * @param int Which Sunday DST ends on
 * @return bool
 */

function check_timestamp_dst($time, $start_month, $start_sunday, $end_month, $end_sunday)
{
  static $sundays = array(FIRST_SUNDAY, SECOND_SUNDAY, THIRD_SUNDAY, LAST_SUNDAY);
  
  // perform timestamp offset
  global $timezone;
  // it's gonna be in minutes, so multiply by 60 to offset the unix timestamp
  $time = $time + ( $timezone * 60 );
  $year = intval(gmdate('Y', $time));
  
  // one-pass validation
  if ( !in_array($start_sunday, $sundays) || !in_array($end_sunday, $sundays) ||
       $start_month < 1 || $start_month > 12 || $end_month < 1 || $end_month > 12 )
    return false;
    
  // get timestamp of the selected sunday (start)
  $dst_start = get_sunday_timestamp($start_month, $start_sunday, $year);
  $dst_end   = get_sunday_timestamp($end_month, $end_sunday, $year);
  
  if ( $dst_start > $dst_end )
  {
    // start time is past the end time, this means we're in the southern hemisphere
    // as a result, if we're within the range, DST is NOT in progress.
    return !( $time >= $dst_start && $time <= $dst_end );
  }
  
  return $time >= $dst_start && $time <= $dst_end;
}

/**
 * Returns a timestamp for the given *_SUNDAY index.
 * @param int Month
 * @param int Which Sunday (FIRST, SECOND, THIRD, or LAST)
 * @param int Year that we're doing our calculations in
 * @return int
 */

function get_sunday_timestamp($month, $sunday, $year)
{
  $days_in_month = array(
    1 => 31,
    2 => $year % 4 == 0 && ( $year % 100 != 0 || ( $year % 100 == 0 && $year % 400 == 0 ) ) ? 29 : 28,
    3 => 31,
    4 => 30,
    5 => 31,
    6 => 30,
    7 => 31,
    8 => 31,
    9 => 30,
    10 => 31,
    11 => 30,
    12 => 31
  );
  
  $result = mktime(0, 0, 0, $month, 1, $year);
  
  // hack. allows a specific day of the month to be set instead of a sunday. not a good place to do this.
  if ( is_string($sunday) && substr($sunday, -1) === 'd' )
  {
    $result += 86400 * ( intval($sunday) - 1);
    return $result;
  }
  
  $tick = 0;
  $days_remaining = $days_in_month[$month];
  while ( true )
  {
    if ( date('D', $result) == 'Sun' )
    {
      $tick++;
      if ( ( $tick == 1 && $sunday == FIRST_SUNDAY ) ||
           ( $tick == 2 && $sunday == SECOND_SUNDAY ) ||
           ( $tick == 3 && $sunday == THIRD_SUNDAY ) ||
           ( $sunday == LAST_SUNDAY && $days_remaining < 7 ) )
        break;
    }
    $days_remaining--;
    $result += 86400;
  }
  
  return $result;
}

/**
 * Tells you the title for the given page ID string
 * @param string Page ID string (ex: Special:Administration)
 * @param bool Optional. If true, and if the namespace turns out to be something other than Article, the namespace prefix will be prepended to the return value.
 * @return string
 */

function get_page_title($page_id, $show_ns = true)
{
  global $db, $session, $paths, $template, $plugins; // Common objects

  $idata = RenderMan::strToPageID($page_id);
  $page_id_key = $paths->nslist[ $idata[1] ] . $idata[0];
  $page_id_key = sanitize_page_id($page_id_key);
  $page_data = @$paths->pages[$page_id_key];
  $title = ( isset($page_data['name']) ) ?
    ( ( $page_data['namespace'] == 'Article' || !$show_ns ) ?
      '' :
      $paths->nslist[ $idata[1] ] )
    . $page_data['name'] :
    ( $show_ns ? $paths->nslist[$idata[1]] : '' ) . str_replace('_', ' ', dirtify_page_id( $idata[0] ) );
  return $title;
}

/**
 * Tells you the title for the given page ID and namespace
 * @param string Page ID
 * @param string Namespace
 * @return string
 */

function get_page_title_ns($page_id, $namespace)
{
  global $db, $session, $paths, $template, $plugins; // Common objects

  $ns_prefix = ( isset($paths->nslist[ $namespace ]) ) ? $paths->nslist[ $namespace ] : $namespace . substr($paths->nslist['Special'], -1);
  $page_id_key = $ns_prefix . $page_id;
  if ( isset($paths->pages[$page_id_key]) )
  {
    $page_data = $paths->pages[$page_id_key];
  }
  else
  {
    $page_data = array();
  }
  $title = ( isset($page_data['name']) ) ? $page_data['name'] : $ns_prefix . str_replace('_', ' ', dirtify_page_id( $page_id ) );
  return $title;
}

/**
 * Redirect the user to the specified URL.
 * @param string $url The URL, either relative or absolute.
 * @param string $title The title of the message
 * @param string $message A short message to show to the user
 * @param string $timeout Timeout, in seconds, to delay the redirect. Defaults to 3. If 0, sends a 307 Temporary Redirect.
 */

function redirect($url, $title = 'etc_redirect_title', $message = 'etc_redirect_body', $timeout = 3)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;

  // POST check added in 1.1.x because Firefox asks us if we want to "resend the form
  // data to the new location", which can be confusing for some users.
  if ( $timeout == 0 && empty($_POST) )
  {
    header('Location: ' . $url);
    header('Content-length: 0');
    header('HTTP/1.1 307 Temporary Redirect');
    
    // with 3xx codes HTTP clients expect a response of 0 bytes, so just die here
    exit();
  }
  
  if ( !is_object($template) )
  {
    $template = new template_nodb();
    $template->load_theme('oxygen', 'bleu', false);
    $template->assign_vars(array(
        'SITE_NAME' => 'Enano',
        'SITE_DESC' => 'This site is experiencing a critical error and cannot load.',
        'COPYRIGHT' => 'Powered by Enano CMS - &copy; 2006-2008 Dan Fuhry. This program is Free Software; see the <a href="' . scriptPath . '/install.php?mode=license">GPL file</a> included with this package for details.',
        'PAGE_NAME' => htmlspecialchars($title)
      ));
  }

  $template->add_header('<meta http-equiv="refresh" content="' . $timeout . '; url=' . str_replace('"', '\\"', $url) . '" />');
  $template->add_header('<script type="text/javascript">
      function __r() {
        // FUNCTION AUTOMATICALLY GENERATED
        window.location="' . str_replace('"', '\\"', $url) . '";
      }
      setTimeout(\'__r();\', ' . $timeout . '000);
    </script>
    ');
  
  if ( get_class($template) == 'template_nodb' )
    $template->init_vars();

  $template->assign_vars(array('PAGE_NAME' => $title));
  $template->header(true);
  echo '<p>' . $message . '</p>';
  $subst = array(
      'timeout' => $timeout,
      'redirect_url' => str_replace('"', '\\"', $url)
    );
  echo '<p>' . $lang->get('etc_redirect_timeout', $subst) . '</p>';
  $template->footer(true);

  $db->close();
  exit(0);

}

/**
 * Generates a confirmation form if a CSRF check fails. Will terminate execution.
 */

function csrf_request_confirm()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output;
  
  // If the token was overridden with the correct one, the user confirmed the action using this form. Continue exec.
  if ( isset($_POST['cstok']) || isset($_GET['cstok']) )
  {
    // using the if() check makes sure that the token isn't in a cookie, since $_REQUEST includes $_COOKIE.
    $token_check =& $_REQUEST['cstok'];
    if ( $token_check === $session->csrf_token )
    {
      // overridden token matches, continue exec
      return true;
    }
  }
  
  $output->set_title($lang->get('user_csrf_confirm_title'));
  $output->header();
  
  // initial info
  echo '<p>' . $lang->get('user_csrf_confirm_body') . '</p>';
  
  // start form
  $form_method = ( empty($_POST) ) ? 'get' : 'post';
  echo '<form action="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '" method="' . $form_method . '" enctype="multipart/form-data">';
  
  echo '<fieldset enano:expand="closed">';
  echo '<legend>' . $lang->get('user_csrf_confirm_btn_viewrequest') . '</legend><div>';
  
  if ( empty($_POST) )
  {
    // GET request
    echo csrf_confirm_get_recursive();
  }
  else
  {
    // POST request
    echo csrf_confirm_post_recursive();
  }
  echo '</div></fieldset>';
  // insert the right CSRF token
  echo '<input type="hidden" name="cstok" value="' . $session->csrf_token . '" />';
  echo '<p><input type="submit" value="' . $lang->get('user_csrf_confirm_btn_continue') . '" /></p>';
  echo '</form><script type="text/javascript">addOnloadHook(function(){load_component(\'expander\');});</script>';
  
  $output->footer();
  
  exit;
}

function csrf_confirm_get_recursive($_inner = false, $pfx = false, $data = false)
{
  // make posted arrays work right
  if ( !$data )
    ( $_inner == 'post' ) ? $data =& $_POST : $data =& $_GET;
  foreach ( $data as $key => $value )
  {
    $pfx_this = ( empty($pfx) ) ? $key : "{$pfx}[{$key}]";
    if ( is_array($value) )
    {
      csrf_confirm_get_recursive(true, $pfx_this, $value);
    }
    else if ( empty($value) )
    {
      echo htmlspecialchars($pfx_this . " = <nil>") . "<br />\n";
      echo '<input type="hidden" name="' . htmlspecialchars($pfx_this) . '" value="" />';
    }
    else
    {
      echo htmlspecialchars($pfx_this . " = " . $value) . "<br />\n";
      echo '<input type="hidden" name="' . htmlspecialchars($pfx_this) . '" value="' . htmlspecialchars($value) . '" />';
    }
  }
}

function csrf_confirm_post_recursive()
{
  csrf_confirm_get_recursive('post');
}

// Removed wikiFormat() from here, replaced with RenderMan::render

/**
 * Tell me if the page exists or not.
 * @param string the full page ID (Special:Administration) of the page to check for
 * @return bool True if the page exists, false otherwise
 */

function isPage($p) {
  global $db, $session, $paths, $template, $plugins; // Common objects

  // Try the easy way first ;-)
  if ( isset( $paths->pages[ $p ] ) )
  {
    return true;
  }

  // Special case for Special, Template, and Admin pages that can't have slashes in their URIs
  $ns_test = RenderMan::strToPageID( $p );

  if($ns_test[1] != 'Special' && $ns_test[1] != 'Template' && $ns_test[1] != 'Admin')
  {
    return false;
  }

  $particles = explode('/', $p);
  if ( isset ( $paths->pages[ $particles[ 0 ] ] ) )
  {
    return true;
  }
  else
  {
    return false;
  }
}

/**
 * Returns the appropriate Namespace_* object for a page.
 * @param string Page ID
 * @param string Namespace
 * @param int Revision ID
 */

function namespace_factory($page_id, $namespace, $revision_id = 0)
{
  if ( !class_exists("Namespace_$namespace") )
  {
    if ( file_exists(ENANO_ROOT . "/includes/namespaces/" . strtolower($namespace) . ".php") )
    {
      require(ENANO_ROOT . "/includes/namespaces/" . strtolower($namespace) . ".php");
    }
  }
  if ( class_exists("Namespace_$namespace") )
  {
    $class = "Namespace_$namespace";
    $ns = new $class($page_id, $namespace, $revision_id);
    return $ns;
  }
  else
  {
    $ns = new Namespace_Default($page_id, $namespace, $revision_id);
    return $ns;
  }
}

/**
 * These are some old functions that were used with the Midget codebase. They are deprecated and should not be used any more.
 */

function arrayItemUp($arr, $keyname) {
  $keylist = array_keys($arr);
  $keyflop = array_flip($keylist);
  $idx = $keyflop[$keyname];
  $idxm = $idx - 1;
  $temp = $arr[$keylist[$idxm]];
  if($arr[$keylist[0]] == $arr[$keyname]) return $arr;
  $arr[$keylist[$idxm]] = $arr[$keylist[$idx]];
  $arr[$keylist[$idx]] = $temp;
  return $arr;
}

function arrayItemDown($arr, $keyname) {
  $keylist = array_keys($arr);
  $keyflop = array_flip($keylist);
  $idx = $keyflop[$keyname];
  $idxm = $idx + 1;
  $temp = $arr[$keylist[$idxm]];
  $sz = sizeof($arr); $sz--;
  if($arr[$keylist[$sz]] == $arr[$keyname]) return $arr;
  $arr[$keylist[$idxm]]  =  $arr[$keylist[$idx]];
  $arr[$keylist[$idx]]   =  $temp;
  return $arr;
}

function arrayItemTop($arr, $keyname) {
  $keylist = array_keys($arr);
  $keyflop = array_flip($keylist);
  $idx = $keyflop[$keyname];
  while( $orig != $arr[$keylist[0]] ) {
    // echo 'Keyname: '.$keylist[$idx] . '<br />'; flush(); ob_flush(); // Debugger
    if($idx < 0) return $arr;
    if($keylist[$idx] == '' || $keylist[$idx] < 0 || !$keylist[$idx]) {
      return $arr;
    }
    $arr = arrayItemUp($arr, $keylist[$idx]);
    $idx--;
  }
  return $arr;
}

function arrayItemBottom($arr, $keyname) {
  $b = $arr[$keyname];
  unset($arr[$keyname]);
  $arr[$keyname] = $b;
  unset($b);
  return $arr;
}

/**
 * Implementation of array_merge() that preserves key names. $arr2 takes precedence over $arr1.
 * @param array $arr1
 * @param array $arr2
 * @return array
 */

function enano_safe_array_merge($arr1, $arr2)
{
  $arr3 = $arr1;
  foreach($arr2 as $k => $v)
  {
    $arr3[$k] = $v;
  }
  return $arr3;
}

/**
 * Looks at all values in an array and casts them to integers if they are strings with digits. Recursive.
 * @param array Array to process
 * @return array
 */

function integerize_array($arr)
{
  if ( !is_array($arr) )
    return $arr;
  
  foreach ( $arr as &$val )
  {
    if ( is_string($val) && ctype_digit($val) && strlen($val) < 10 )
    {
      $val = intval($val);
    }
    else if ( is_array($val) )
    {
      $val = integerize_array($val);
    }
  }
  return $arr;
}

// Convert IP address to hex string
// Input:  127.0.0.1  (string)
// Output: 0x7f000001 (string)
// Updated 12/8/06 to work with PHP4 and not use eval() (blech)
function ip2hex($ip) {
  if ( preg_match('/^([0-9a-f:]+)$/', $ip) )
  {
    // this is an ipv6 address
    return str_replace(':', '', $ip);
  }
  $nums = explode('.', $ip);
  if(sizeof($nums) != 4) return false;
  $str = '0x';
  foreach($nums as $n)
  {
    $byte = (string)dechex($n);
    if ( strlen($byte) < 2 )
      $byte = '0' . $byte;
  }
  return $str;
}

// Convert DWord to IP address
// Input:  0x7f000001
// Output: 127.0.0.1
// Updated 12/8/06 to work with PHP4 and not use eval() (blech)
function hex2ip($in) {
  if(substr($in, 0, 2) == '0x') $ip = substr($in, 2, 8);
  else $ip = substr($in, 0, 8);
  $octets = enano_str_split($ip, 2);
  $str = '';
  $newoct = Array();
  foreach($octets as $o)
  {
    $o = (int)hexdec($o);
    $newoct[] = $o;
  }
  return implode('.', $newoct);
}

// Function strip_php moved to RenderMan class

/**
 * Immediately brings the site to a halt with an error message. Unlike grinding_halt() this can only be called after the config has been
 * fetched (plugin developers don't even need to worry since plugins are always loaded after the config) and shows the site name and
 * description.
 * @param string The title of the error message
 * @param string The body of the message, this can be HTML, and should be separated into paragraphs using the <p> tag
 * @param bool Added in 1.1.3. If true, only the error is output. Defaults to false.
 */

function die_semicritical($t, $p, $no_wrapper = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $db->close();
  
  if ( ob_get_status() )
    ob_end_clean();

  // If the config hasn't been fetched yet, call grinding_halt.
  if ( !defined('ENANO_CONFIG_FETCHED') )
  {
    grinding_halt($t, $p);
  }
  
  // also do grinding_halt() if we're in CLI mode
  if ( defined('ENANO_CLI') )
  {
    grinding_halt($t, $p);
  }

  if ( $no_wrapper )
  {
    echo '<h2>' . htmlspecialchars($t) . '</h2>';
    echo "<p>$p</p>";
    exit;
  }
  
  $output = new Output_Safe();
  $output->set_title($t);
  $output->header();
  echo $p;
  $output->footer();

  exit;
}

/**
 * Halts Enano execution with a message. This doesn't have to be an error message, it's sometimes used to indicate success at an operation.
 * @param string The title of the message
 * @param string The body of the message, this can be HTML, and should be separated into paragraphs using the <p> tag
 */

function die_friendly($t, $p)
{
  global $db, $session, $paths, $template, $plugins; // Common objects

  if ( ob_get_status() )
    ob_end_clean();

  $paths->cpage['name'] = $t;
  $template->tpl_strings['PAGE_NAME'] = $t;
  $template->header();
  echo $p;
  $template->footer();
  $db->close();

  exit;
}

/**
 * Immediately brings the site to a halt with an error message, and focuses on immediately closing the database connection and shutting down Enano in the event that an attack may happen. This should only be used very early on to indicate very severe errors, or if the site may be under attack (like if the DBAL detects a malicious query). In the vast majority of cases, die_semicritical() is more appropriate.
 * @param string The title of the error message
 * @param string The body of the message, this can be HTML, and should be separated into paragraphs using the <p> tag
 */

function grinding_halt($t, $p)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !defined('scriptPath') )
    require( ENANO_ROOT . '/config.php' );

  if ( is_object($db) )
    $db->close();
  
  if ( ob_get_status() )
    ob_end_clean();
  
  if ( defined('ENANO_CLI') )
  {
    // set console color
    echo "\x1B[31;1m";
    // error title
    echo "Critical error in Enano runtime: ";
    // unbold
    echo "$t\n";
    // bold
    echo "\x1B[37;1m";
    echo "Error: ";
    // unbold
    echo "\x1B[0m";
    echo "$p\n";
    exit(1);
  }
  $theme = ( defined('ENANO_CONFIG_FETCHED') ) ? getConfig('theme_default') : 'oxygen';
  $style = ( defined('ENANO_CONFIG_FETCHED') ) ? '__foo__' : 'bleu';
  
  $tpl = new template_nodb();
  $tpl->load_theme($theme, $style);
  $tpl->tpl_strings['SITE_NAME'] = 'Critical error';
  $tpl->tpl_strings['SITE_DESC'] = 'This website is experiencing a serious error and cannot load.';
  $tpl->tpl_strings['COPYRIGHT'] = 'Unable to retrieve copyright information';
  $tpl->tpl_strings['PAGE_NAME'] = $t;
  $tpl->header();
  echo $p;
  $tpl->footer();
  
  exit;
}

/**
 * Prints out the categorization box found on most regular pages. Doesn't take or return anything, but assumes that the page information is already set in $paths.
 */

function show_category_info()
{
  throw new Exception('show_category_info() is deprecated. Use Namespace_*::display_categories().');
}

/**
 * Prints out the file information box seen on File: pages. Doesn't take or return anything, but assumes that the page information is already set in $paths, and expects $paths->namespace to be File.
 */

function show_file_info($page = false)
{
  throw new Exception('show_file_info() is deprecated. Use Namespace_File::show_info().');
}

/**
 * Shows header information on the current page. Currently this is only the delete-vote feature. Doesn't take or return anything, but assumes that the page information is already set in $paths.
 */

function display_page_headers()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  if($session->get_permissions('vote_reset') && $paths->cpage['delvotes'] > 0)
  {
    $delvote_ips = unserialize($paths->cpage['delvote_ips']);
    $hr = htmlspecialchars(implode(', ', $delvote_ips['u']));
    
    $string_id = ( $paths->cpage['delvotes'] == 1 ) ? 'delvote_lbl_votes_one' : 'delvote_lbl_votes_plural';
    $string = $lang->get($string_id, array('num_users' => $paths->cpage['delvotes']));
    
    echo '<div class="info-box" style="margin-left: 0; margin-top: 5px;" id="mdgDeleteVoteNoticeBox">
            <b>' . $lang->get('etc_lbl_notice') . '</b> ' . $string . '<br />
            <b>' . $lang->get('delvote_lbl_users_that_voted') . '</b> ' . $hr . '<br />
            <a href="'.makeUrl($paths->page, 'do=deletepage').'" onclick="ajaxDeletePage(); return false;">' . $lang->get('delvote_btn_deletepage') . '</a>  |  <a href="'.makeUrl($paths->page, 'do=resetvotes').'" onclick="ajaxResetDelVotes(); return false;">' . $lang->get('delvote_btn_resetvotes') . '</a>
          </div>';
  }
}

/**
 * Displays page footer information including file and category info. This also has the send_page_footers hook. Doesn't take or return anything, but assumes that the page information is already set in $paths.
 */

function display_page_footers()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( isset($_GET['nofooters']) )
  {
    return;
  }
  
  $code = $plugins->setHook('send_page_footers');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
}

/**
 * Essentially an return code reader for a socket. Don't use this unless you're writing mail code and smtp_send_email doesn't cut it. Ported from phpBB's smtp.php.
 * @param socket A socket resource
 * @param string The expected response from the server, this needs to be exactly three characters.
 */

function smtp_get_response($socket, $response, $line = __LINE__)
{
  $server_response = '';
  while (substr($server_response, 3, 1) != ' ')
  {
    if (!($server_response = fgets($socket, 256)))
    {
      die_friendly('SMTP Error', "<p>Couldn't get mail server response codes</p>");
    }
  }

  if (!(substr($server_response, 0, 3) == $response))
  {
    die_friendly('SMTP Error', "<p>Ran into problems sending mail. Response: $server_response</p>");
  }
}

/**
 * Wrapper for smtp_send_email_core that takes the sender as the fourth parameter instead of additional headers.
 * @param string E-mail address to send to
 * @param string Subject line
 * @param string The body of the message
 * @param string Address of the sender
 */

function smtp_send_email($to, $subject, $message, $from)
{
  return smtp_send_email_core($to, $subject, $message, "From: <$from>\n");
}

/**
 * Replacement or substitute for PHP's mail() builtin function.
 * @param string E-mail address to send to
 * @param string Subject line
 * @param string The body of the message
 * @param string Message headers, separated by a single newline ("\n")
 * @copyright (C) phpBB Group
 * @license GPL
 */

function smtp_send_email_core($mail_to, $subject, $message, $headers = '')
{
  // Fix any bare linefeeds in the message to make it RFC821 Compliant.
  $message = preg_replace("#(?<!\r)\n#si", "\r\n", $message);

  if ($headers != '')
  {
    if (is_array($headers))
    {
      if (sizeof($headers) > 1)
      {
        $headers = join("\n", $headers);
      }
      else
      {
        $headers = $headers[0];
      }
    }
    $headers = chop($headers);

    // Make sure there are no bare linefeeds in the headers
    $headers = preg_replace('#(?<!\r)\n#si', "\r\n", $headers);

    // Ok this is rather confusing all things considered,
    // but we have to grab bcc and cc headers and treat them differently
    // Something we really didn't take into consideration originally
    $header_array = explode("\r\n", $headers);
    @reset($header_array);

    $headers = '';
    $cc = '';
    $bcc = '';
    while(list(, $header) = each($header_array))
    {
      if (preg_match('#^cc:#si', $header))
      {
        $cc = preg_replace('#^cc:(.*)#si', '\1', $header);
      }
      else if (preg_match('#^bcc:#si', $header))
      {
        $bcc = preg_replace('#^bcc:(.*)#si', '\1', $header);
        $header = '';
      }
      $headers .= ($header != '') ? $header . "\r\n" : '';
    }

    $headers = chop($headers);
    $cc = explode(', ', $cc);
    $bcc = explode(', ', $bcc);
  }

  if (trim($subject) == '')
  {
    die_friendly(GENERAL_ERROR, "No email Subject specified");
  }

  if (trim($message) == '')
  {
    die_friendly(GENERAL_ERROR, "Email message was blank");
  }

  // setup SMTP
  $host = getConfig('smtp_server');
  if ( empty($host) )
    return 'No smtp_host in config';
  if ( strstr($host, ':' ) )
  {
    $n = explode(':', $host);
    $smtp_host = $n[0];
    $port = intval($n[1]);
  }
  else
  {
    $smtp_host = $host;
    $port = 25;
  }

  $smtp_user = getConfig('smtp_user');
  $smtp_pass = getConfig('smtp_password');

  // Ok we have error checked as much as we can to this point let's get on
  // it already.
  if( !$socket = @fsockopen($smtp_host, $port, $errno, $errstr, 20) )
  {
    die_friendly(GENERAL_ERROR, "Could not connect to smtp host : $errno : $errstr");
  }

  // Wait for reply
  smtp_get_response($socket, "220", __LINE__);

  // Do we want to use AUTH?, send RFC2554 EHLO, else send RFC821 HELO
  // This improved as provided by SirSir to accomodate
  if( !empty($smtp_user) && !empty($smtp_pass) )
  {
    enano_fputs($socket, "EHLO " . $smtp_host . "\r\n");
    smtp_get_response($socket, "250", __LINE__);

    enano_fputs($socket, "AUTH LOGIN\r\n");
    smtp_get_response($socket, "334", __LINE__);

    enano_fputs($socket, base64_encode($smtp_user) . "\r\n");
    smtp_get_response($socket, "334", __LINE__);

    enano_fputs($socket, base64_encode($smtp_pass) . "\r\n");
    smtp_get_response($socket, "235", __LINE__);
  }
  else
  {
    enano_fputs($socket, "HELO " . $smtp_host . "\r\n");
    smtp_get_response($socket, "250", __LINE__);
  }

  // From this point onward most server response codes should be 250
  // Specify who the mail is from....
  enano_fputs($socket, "MAIL FROM: <" . getConfig('contact_email') . ">\r\n");
  smtp_get_response($socket, "250", __LINE__);

  // Specify each user to send to and build to header.
  $to_header = '';

  // Add an additional bit of error checking to the To field.
  $mail_to = (trim($mail_to) == '') ? 'Undisclosed-recipients:;' : trim($mail_to);
  if (preg_match('#[^ ]+\@[^ ]+#', $mail_to))
  {
    enano_fputs($socket, "RCPT TO: <$mail_to>\r\n");
    smtp_get_response($socket, "250", __LINE__);
  }

  // Ok now do the CC and BCC fields...
  @reset($bcc);
  while(list(, $bcc_address) = each($bcc))
  {
    // Add an additional bit of error checking to bcc header...
    $bcc_address = trim($bcc_address);
    if (preg_match('#[^ ]+\@[^ ]+#', $bcc_address))
    {
      enano_fputs($socket, "RCPT TO: <$bcc_address>\r\n");
      smtp_get_response($socket, "250", __LINE__);
    }
  }

  @reset($cc);
  while(list(, $cc_address) = each($cc))
  {
    // Add an additional bit of error checking to cc header
    $cc_address = trim($cc_address);
    if (preg_match('#[^ ]+\@[^ ]+#', $cc_address))
    {
      enano_fputs($socket, "RCPT TO: <$cc_address>\r\n");
      smtp_get_response($socket, "250", __LINE__);
    }
  }

  // Ok now we tell the server we are ready to start sending data
  enano_fputs($socket, "DATA\r\n");

  // This is the last response code we look for until the end of the message.
  smtp_get_response($socket, "354", __LINE__);

  // Send the Subject Line...
  enano_fputs($socket, "Subject: $subject\r\n");

  // Now the To Header.
  enano_fputs($socket, "To: $mail_to\r\n");

  // Now any custom headers....
  enano_fputs($socket, "$headers\r\n\r\n");

  // Ok now we are ready for the message...
  enano_fputs($socket, "$message\r\n");

  // Ok the all the ingredients are mixed in let's cook this puppy...
  enano_fputs($socket, ".\r\n");
  smtp_get_response($socket, "250", __LINE__);

  // Now tell the server we are done and close the socket...
  enano_fputs($socket, "QUIT\r\n");
  fclose($socket);

  return TRUE;
}

/**
 * Tell which version of Enano we're running.
 * @param bool $long if true, uses English version names (e.g. alpha, beta, release candidate). If false (default) uses abbreviations (1.0a1, 1.0b3, 1.0RC2, etc.)
 * @param bool If true, prevents nightly build information from being appended, useful for upgrade/versioning checks.
 * @return string
 */

function enano_version($long = false, $no_nightly = false)
{
  $r = getConfig('enano_version');
  $rc = ( $long ) ? ' release candidate ' : 'RC';
  $b = ( $long ) ? ' beta ' : 'b';
  $a = ( $long ) ? ' alpha ' : 'a';
  if($v = getConfig('enano_rc_version')) $r .= $rc.$v;
  if($v = getConfig('enano_beta_version')) $r .= $b.$v;
  if($v = getConfig('enano_alpha_version')) $r .= $a.$v;
  if ( defined('ENANO_NIGHTLY') && !$no_nightly )
  {
    $nightlytag  = ENANO_NIGHTLY_MONTH . '-' . ENANO_NIGHTLY_DAY . '-' . ENANO_NIGHTLY_YEAR;
    $nightlylong = ' nightly; build date: ' . ENANO_NIGHTLY_MONTH . '-' . ENANO_NIGHTLY_DAY . '-' . ENANO_NIGHTLY_YEAR;
    $r = ( $long ) ? $r . $nightlylong : $r . '-nightly-' . $nightlytag;
  }
  return $r;
}

/**
 * Give the codename of the release of Enano being run.
 * @return string
 */

function enano_codename()
{
  $names = array(
      '1.0RC1' => 'Leprechaun',
      '1.0RC2' => 'Clurichaun',
      '1.0RC3' => 'Druid',
      '1.0'    => 'Banshee',
      '1.0.1'  => 'Loch Ness',
      '1.0.1.1'=> 'Loch Ness internal bugfix build',
      '1.0.2b1'=> 'Coblynau unstable',
      '1.0.2'  => 'Coblynau',
      '1.0.3'  => 'Dyrad',
      '1.1.1'  => 'Caoineag alpha 1',
      '1.1.2'  => 'Caoineag alpha 2',
      '1.1.3'  => 'Caoineag alpha 3',
      '1.1.4'  => 'Caoineag alpha 4',
      '1.1.5'  => 'Caoineag alpha 5',
    );
  $version = enano_version();
  if ( isset($names[$version]) )
  {
    return $names[$version];
  }
  return 'Anonymous build';
}

/**
 * Badly named function to send back eval'able Javascript code with an error message. Deprecated, use JSON instead.
 * @param string Message to send
 */

function _die($t) {
  $_ob = 'document.getElementById("ajaxEditContainer").innerHTML = unescape(\'' . rawurlencode('' . $t . '') . '\')';
  die($_ob);
}

/**
 * Same as _die(), but sends an SQL backtrace with the error message, and doesn't halt execution.
 * @param string Message to send
 */

function jsdie($text) {
  global $db, $session, $paths, $template, $plugins; // Common objects
  $text = rawurlencode($text . "\n\nSQL Backtrace:\n" . $db->sql_backtrace());
  echo 'document.getElementById("ajaxEditContainer").innerHTML = unescape(\''.$text.'\');';
}

/**
 * Capitalizes the first letter of a string
 * @param $text string the text to be transformed
 * @return string
 */

function capitalize_first_letter($text)
{
  return strtoupper(substr($text, 0, 1)) . substr($text, 1);
}

/**
 * Checks if a value in a bitfield is on or off
 * @param $bitfield int the bit-field value
 * @param $value int the value to switch off
 * @return bool
 */

function is_bit($bitfield, $value)
{
  return ( $bitfield & $value ) ? true : false;
}

/**
 * Trims spaces/newlines from the beginning and end of a string
 * @param $text the text to process
 * @return string
 */

function trim_spaces($text)
{
  $d = true;
  while($d)
  {
    $c = substr($text, 0, 1);
    $a = substr($text, strlen($text)-1, strlen($text));
    if($c == "\n" || $c == "\r" || $c == "\t" || $c == ' ') $text = substr($text, 1, strlen($text));
    elseif($a == "\n" || $a == "\r" || $a == "\t" || $a == ' ') $text = substr($text, 0, strlen($text)-1);
    else $d = false;
  }
  return $text;
}

/**
 * Enano-ese equivalent of str_split() which is only found in PHP5
 * @param $text string the text to split
 * @param $inc int size of each block
 * @return array
 */

function enano_str_split($text, $inc = 1)
{
  if($inc < 1)
  {
    return false;
  }
  if($inc >= strlen($text))
  {
    return Array($text);
  }
  $len = ceil(strlen($text) / $inc);
  $ret = Array();
  for ( $i = 0; $i < strlen($text); $i = $i + $inc )
  {
    $ret[] = substr($text, $i, $inc);
  }
  return $ret;
}

/**
 * Converts a hexadecimal number to a binary string.
 * @param text string hexadecimal number
 * @return string
 */
function hex2bin($text)
{
  $arr = enano_str_split($text, 2);
  $ret = '';
  for ($i=0; $i<sizeof($arr); $i++)
  {
    $ret .= chr(hexdec($arr[$i]));
  }
  return $ret;
}

/**
 * Generates and/or prints a human-readable backtrace
 * @param bool $return - if true, this function returns a string, otherwise returns null and prints the backtrace
 * @return mixed
 */

function enano_debug_print_backtrace($return = false)
{
  ob_start();
  if ( !$return )
    echo '<pre>';
  if ( function_exists('debug_print_backtrace') )
  {
    debug_print_backtrace();
  }
  else
  {
    echo '<b>Warning:</b> No debug_print_backtrace() support!';
  }
  if ( !$return )
    echo '</pre>';
  $c = ob_get_contents();
  ob_end_clean();
  if($return) return $c;
  else echo $c;
  return null;
}

/**
 * Like rawurlencode(), but encodes all characters
 * @param string $text the text to encode
 * @param optional string $prefix text before each hex character
 * @param optional string $suffix text after each hex character
 * @return string
 */

function hexencode($text, $prefix = '%', $suffix = '')
{
  $arr = enano_str_split($text);
  $r = '';
  foreach($arr as $a)
  {
    $nibble = (string)dechex(ord($a));
    if(strlen($nibble) == 1) $nibble = '0' . $nibble;
    $r .= $prefix . $nibble . $suffix;
  }
  return $r;
}

/**
 * Enano-ese equivalent of get_magic_quotes_gpc()
 * @return bool
 */

function enano_get_magic_quotes_gpc()
{
  if(function_exists('get_magic_quotes_gpc'))
  {
    return ( get_magic_quotes_gpc() == 1 );
  }
  else
  {
    return ( strtolower(@ini_get('magic_quotes_gpc')) == '1' );
  }
}

/**
 * Recursive stripslashes()
 * @param array
 * @return array
 */

function stripslashes_recurse($arr)
{
  foreach($arr as $k => $xxxx)
  {
    $val =& $arr[$k];
    if(is_string($val))
      $val = stripslashes($val);
    elseif(is_array($val))
      $val = stripslashes_recurse($val);
  }
  return $arr;
}

/**
 * Recursive function to remove all NUL bytes from a string
 * @param array
 * @return array
 */

function strip_nul_chars($arr)
{
  foreach($arr as $k => $xxxx_unused)
  {
    $val =& $arr[$k];
    if(is_string($val))
      $val = str_replace("\000", '', $val);
    elseif(is_array($val))
      $val = strip_nul_chars($val);
  }
  return $arr;
}

/**
 * If magic_quotes_gpc is on, calls stripslashes() on everything in $_GET/$_POST/$_COOKIE. Also strips any NUL characters from incoming requests, as these are typically malicious.
 * @ignore - this doesn't work too well in my tests
 * @todo port version from the PHP manual
 * @return void
 */
function strip_magic_quotes_gpc()
{
  if(enano_get_magic_quotes_gpc())
  {
    $_POST    = stripslashes_recurse($_POST);
    $_GET     = stripslashes_recurse($_GET);
    $_COOKIE  = stripslashes_recurse($_COOKIE);
    $_REQUEST = stripslashes_recurse($_REQUEST);
  }
  $_POST    = strip_nul_chars($_POST);
  $_GET     = strip_nul_chars($_GET);
  $_COOKIE  = strip_nul_chars($_COOKIE);
  $_REQUEST = strip_nul_chars($_REQUEST);
  $_POST    = decode_unicode_array($_POST);
  $_GET     = decode_unicode_array($_GET);
  $_COOKIE  = decode_unicode_array($_COOKIE);
  $_REQUEST = decode_unicode_array($_REQUEST);
}

/**
 * A very basic single-character compression algorithm for binary strings/bitfields
 * @param string $bits the text to compress, should be only 1s and 0s
 * @return string
 */

function compress_bitfield($bits)
{
  $crc32 = crc32($bits);
  $bits .= '0';
  $start_pos = 0;
  $current = substr($bits, 1, 1);
  $last    = substr($bits, 0, 1);
  $chunk_size = 1;
  $len = strlen($bits);
  $crc = $len;
  $crcval = 0;
  for ( $i = 1; $i < $len; $i++ )
  {
    $current = substr($bits, $i, 1);
    $last    = substr($bits, $i - 1, 1);
    $next    = substr($bits, $i + 1, 1);
    // Are we on the last character?
    if($current == $last && $i+1 < $len)
      $chunk_size++;
    else
    {
      if($i+1 == $len && $current == $next)
      {
        // This character completes a chunk
        $chunk_size++;
        $i++;
        $chunk = substr($bits, $start_pos, $chunk_size);
        $chunklen = strlen($chunk);
        $newchunk = $last . '[' . $chunklen . ']';
        $newlen   = strlen($newchunk);
        $bits = substr($bits, 0, $start_pos) . $newchunk . substr($bits, $i, $len);
        $chunk_size = 1;
        $i = $start_pos + $newlen;
        $start_pos = $i;
        $len = strlen($bits);
        $crcval = $crcval + $chunklen;
      }
      else
      {
        // Last character completed a chunk
        $chunk = substr($bits, $start_pos, $chunk_size);
        $chunklen = strlen($chunk);
        $newchunk = $last . '[' . $chunklen . '],';
        $newlen   = strlen($newchunk);
        $bits = substr($bits, 0, $start_pos) . $newchunk . substr($bits, $i, $len);
        $chunk_size = 1;
        $i = $start_pos + $newlen;
        $start_pos = $i;
        $len = strlen($bits);
        $crcval = $crcval + $chunklen;
      }
    }
  }
  if($crc != $crcval)
  {
    echo __FUNCTION__.'(): ERROR: length check failed, this is a bug in the algorithm<br />Debug info: aiming for a CRC val of '.$crc.', got '.$crcval;
    return false;
  }
  $compressed = 'cbf:len='.$crc.';crc='.dechex($crc32).';data='.$bits.'|end';
  return $compressed;
}

/**
 * Uncompresses a bitfield compressed with compress_bitfield()
 * @param string $bits the compressed bitfield
 * @return string the uncompressed, original (we hope) bitfield OR bool false on error
 */

function uncompress_bitfield($bits)
{
  if(substr($bits, 0, 4) != 'cbf:')
  {
    echo __FUNCTION__.'(): ERROR: Invalid stream';
    return false;
  }
  $len = intval(substr($bits, strpos($bits, 'len=')+4, strpos($bits, ';')-strpos($bits, 'len=')-4));
  $crc = substr($bits, strpos($bits, 'crc=')+4, 8);
  $data = substr($bits, strpos($bits, 'data=')+5, strpos($bits, '|end')-strpos($bits, 'data=')-5);
  $data = explode(',', $data);
  foreach($data as $a => $b)
  {
    $d =& $data[$a];
    $char = substr($d, 0, 1);
    $dlen = intval(substr($d, 2, strlen($d)-1));
    $s = '';
    for($i=0;$i<$dlen;$i++,$s.=$char);
    $d = $s;
    unset($s, $dlen, $char);
  }
  $decompressed = implode('', $data);
  $decompressed = substr($decompressed, 0, -1);
  $dcrc = (string)dechex(crc32($decompressed));
  if($dcrc != $crc)
  {
    echo __FUNCTION__.'(): ERROR: CRC check failed<br />debug info:<br />original crc: '.$crc.'<br />decomp\'ed crc: '.$dcrc.'<br />';
    return false;
  }
  return $decompressed;
}

/**
 * Exports a MySQL table into a SQL string.
 * @param string $table The name of the table to export
 * @param bool $structure If true, include a CREATE TABLE command
 * @param bool $data If true, include the contents of the table
 * @param bool $compact If true, omits newlines between parts of SQL statements, use in Enano database exporter
 * @return string
 */

function export_table($table, $structure = true, $data = true, $compact = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $struct_keys = '';
  $divider   = (!$compact) ? "\n" : "\n";
  $spacer1   = (!$compact) ? "\n" : " ";
  $spacer2   = (!$compact) ? "  " : " ";
  $rowspacer = (!$compact) ? "\n  " : " ";
  $index_list = Array();
  $cols = $db->sql_query('SHOW COLUMNS IN '.$table.';');
  if(!$cols)
  {
    echo 'export_table(): Error getting column list: '.$db->get_error_text().'<br />';
    return false;
  }
  $col = Array();
  $sqlcol = Array();
  $collist = Array();
  $pri_keys = Array();
  // Using fetchrow_num() here to compensate for MySQL l10n
  while( $row = $db->fetchrow_num() )
  {
    $field =& $row[0];
    $type  =& $row[1];
    $null  =& $row[2];
    $key   =& $row[3];
    $def   =& $row[4];
    $extra =& $row[5];
    $col[] = Array(
      'name'=>$field,
      'type'=>$type,
      'null'=>$null,
      'key'=>$key,
      'default'=>$def,
      'extra'=>$extra,
      );
    $collist[] = $field;
  }

  if ( $structure )
  {
    $db->sql_query('SET SQL_QUOTE_SHOW_CREATE = 0;');
    $struct = $db->sql_query('SHOW CREATE TABLE '.$table.';');
    if ( !$struct )
      $db->_die();
    $row = $db->fetchrow_num();
    $db->free_result();
    $struct = $row[1];
    $struct = preg_replace("/\n\) ENGINE=(.+)$/", "\n);", $struct);
    unset($row);
    if ( $compact )
    {
      $struct_arr = explode("\n", $struct);
      foreach ( $struct_arr as $i => $leg )
      {
        if ( $i == 0 )
          continue;
        $test = trim($leg);
        if ( empty($test) )
        {
          unset($struct_arr[$i]);
          continue;
        }
        $struct_arr[$i] = preg_replace('/^([\s]*)/', ' ', $leg);
      }
      $struct = implode("", $struct_arr);
    }
  }

  // Structuring complete
  if($data)
  {
    $datq = $db->sql_query('SELECT * FROM '.$table.';');
    if(!$datq)
    {
      echo 'export_table(): Error getting column list: '.$db->get_error_text().'<br />';
      return false;
    }
    if($db->numrows() < 1)
    {
      if($structure) return $struct;
      else return '';
    }
    $rowdata = Array();
    $dataqs = Array();
    $insert_strings = Array();
    $z = false;
    while($row = $db->fetchrow_num())
    {
      $z = false;
      foreach($row as $i => $cell)
      {
        $str = mysql_encode_column($cell, $col[$i]['type']);
        $rowdata[] = $str;
      }
      $dataqs2 = implode(",$rowspacer", $dataqs) . ",$rowspacer" . '( ' . implode(', ', $rowdata) . ' )';
      $ins = 'INSERT INTO '.$table.'( '.implode(',', $collist).' ) VALUES' . $dataqs2 . ";";
      if ( strlen( $ins ) > MYSQL_MAX_PACKET_SIZE )
      {
        // We've exceeded the maximum allowed packet size for MySQL - separate this into a different query
        $insert_strings[] = 'INSERT INTO '.$table.'( '.implode(',', $collist).' ) VALUES' . implode(",$rowspacer", $dataqs) . ";";;
        $dataqs = Array('( ' . implode(', ', $rowdata) . ' )');
        $z = true;
      }
      else
      {
        $dataqs[] = '( ' . implode(', ', $rowdata) . ' )';
      }
      $rowdata = Array();
    }
    if ( !$z )
    {
      $insert_strings[] = 'INSERT INTO '.$table.'( '.implode(',', $collist).' ) VALUES' . implode(",$rowspacer", $dataqs) . ";";;
      $dataqs = Array();
    }
    $datstring = implode($divider, $insert_strings);
  }
  if($structure && !$data) return $struct;
  elseif(!$structure && $data) return $datstring;
  elseif($structure && $data) return $struct . $divider . $datstring;
  elseif(!$structure && !$data) return '';
}

/**
 * Encodes a string value for use in an INSERT statement for given column type $type.
 * @access private
 */

function mysql_encode_column($input, $type)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  // Decide whether to quote the string or not
  if(substr($type, 0, 7) == 'varchar' || $type == 'datetime' || $type == 'text' || $type == 'tinytext' || $type == 'smalltext' || $type == 'longtext' || substr($type, 0, 4) == 'char')
  {
    $str = "'" . $db->escape($input) . "'";
  }
  elseif(in_array($type, Array('blob', 'longblob', 'mediumblob', 'smallblob')) || substr($type, 0, 6) == 'binary' || substr($type, 0, 9) == 'varbinary')
  {
    $str = '0x' . hexencode($input, '', '');
  }
  elseif(is_null($input))
  {
    $str = 'NULL';
  }
  else
  {
    $str = (string)$input;
  }
  return $str;
}

/**
 * Creates an associative array defining which file extensions are allowed and which ones aren't
 * @return array keyname will be a file extension, value will be true or false
 */

function fetch_allowed_extensions()
{
  global $mime_types;
  $bits = getConfig('allowed_mime_types');
  if(!$bits) return Array(false);
  $bits = uncompress_bitfield($bits);
  if(!$bits) return Array(false);
  $bits = enano_str_split($bits, 1);
  $ret = Array();
  $mt = array_keys($mime_types);
  foreach($bits as $i => $b)
  {
    $ret[$mt[$i]] = ( $b == '1' ) ? true : false;
  }
  return $ret;
}

/**
 * Generates a random key suitable for encryption
 * @param int $len the length of the key
 * @return string a BINARY key
 */

function randkey($len = 32)
{
  $key = '';
  for($i=0;$i<$len;$i++)
  {
    $key .= chr(mt_rand(0, 255));
  }
  return $key;
}

/**
 * Decodes a hex string.
 * @param string $hex The hex code to decode
 * @return string
 */

function hexdecode($hex)
{
  $hex = enano_str_split($hex, 2);
  $bin_key = '';
  foreach($hex as $nibble)
  {
    $byte = chr(hexdec($nibble));
    $bin_key .= $byte;
  }
  return $bin_key;
}

/**
 * Enano's own (almost) bulletproof HTML sanitizer.
 * @param string $html The input HTML
 * @return string cleaned HTML
 */

function sanitize_html($html, $filter_php = true)
{
  // Random seed for substitution
  $rand_seed = md5( sha1(microtime()) . mt_rand() );
  
  // We need MediaWiki
  require_once(ENANO_ROOT . '/includes/wikiengine/Tables.php');
  
  // Strip out comments that are already escaped
  preg_match_all('/&lt;!--(.*?)--&gt;/', $html, $comment_match);
  $i = 0;
  foreach ( $comment_match[0] as $comment )
  {
    $html = str_replace_once($comment, "{HTMLCOMMENT:$i:$rand_seed}", $html);
    $i++;
  }
  
  // Strip out code sections that will be postprocessed by Text_Wiki
  preg_match_all(';^<code(\s[^>]*)?>((?:(?R)|.)*?)\n</code>(\s|$);msi', $html, $code_match);
  $i = 0;
  foreach ( $code_match[0] as $code )
  {
    $html = str_replace_once($code, "{TW_CODE:$i:$rand_seed}", $html);
    $i++;
  }

  $html = preg_replace('#<([a-z]+)([\s]+)([^>]+?)'.htmlalternatives('javascript:').'(.+?)>(.*?)</\\1>#is', '&lt;\\1\\2\\3javascript:\\59&gt;\\60&lt;/\\1&gt;', $html);
  $html = preg_replace('#<([a-z]+)([\s]+)([^>]+?)'.htmlalternatives('javascript:').'(.+?)>#is', '&lt;\\1\\2\\3javascript:\\59&gt;', $html);

  if($filter_php)
    $html = str_replace(
      Array('<?php',    '<?',    '<%',    '?>',    '%>'),
      Array('&lt;?php', '&lt;?', '&lt;%', '?&gt;', '%&gt;'),
      $html);

  $tag_whitelist = array_keys ( setupAttributeWhitelist() );
  if ( !$filter_php )
    $tag_whitelist[] = '?php';
  // allow HTML comments
  $tag_whitelist[] = '!--';
  $len = strlen($html);
  $in_quote = false;
  $quote_char = '';
  $tag_start = 0;
  $tag_name = '';
  $in_tag = false;
  $trk_name = false;
  for ( $i = 0; $i < $len; $i++ )
  {
    $chr = $html{$i};
    $prev = ( $i == 0 ) ? '' : $html{ $i - 1 };
    $next = ( ( $i + 1 ) == $len ) ? '' : $html { $i + 1 };
    if ( $in_quote && $in_tag )
    {
      if ( $quote_char == $chr && $prev != '\\' )
        $in_quote = false;
    }
    elseif ( ( $chr == '"' || $chr == "'" ) && $prev != '\\' && $in_tag )
    {
      $in_quote = true;
      $quote_char = $chr;
    }
    if ( $chr == '<' && !$in_tag && $next != '/' )
    {
      // start of a tag
      $tag_start = $i;
      $in_tag = true;
      $trk_name = true;
    }
    elseif ( !$in_quote && $in_tag && $chr == '>' )
    {
      $full_tag = substr($html, $tag_start, ( $i - $tag_start ) + 1 );
      $l = strlen($tag_name) + 2;
      $attribs_only = trim( substr($full_tag, $l, ( strlen($full_tag) - $l - 1 ) ) );

      // Debugging message
      // echo htmlspecialchars($full_tag) . '<br />';

      if ( !in_array($tag_name, $tag_whitelist) && substr($tag_name, 0, 3) != '!--' )
      {
        // Illegal tag
        //echo $tag_name . ' ';

        $s = ( empty($attribs_only) ) ? '' : ' ';

        $sanitized = '&lt;' . $tag_name . $s . $attribs_only . '&gt;';

        $html = substr($html, 0, $tag_start) . $sanitized . substr($html, $i + 1);
        $html = str_replace('</' . $tag_name . '>', '&lt;/' . $tag_name . '&gt;', $html);
        $new_i = $tag_start + strlen($sanitized);

        $len = strlen($html);
        $i = $new_i;

        $in_tag = false;
        $tag_name = '';
        continue;
      }
      else
      {
        // If not filtering PHP, don't bother to strip
        if ( $tag_name == '?php' && !$filter_php )
          continue;
        // If this is a comment, likewise skip this "tag"
        if ( $tag_name == '!--' )
          continue;
        $f = fixTagAttributes( $attribs_only, $tag_name );
        $s = ( empty($f) ) ? '' : ' ';

        $sanitized = '<' . $tag_name . $f . '>';
        $new_i = $tag_start + strlen($sanitized);

        $html = substr($html, 0, $tag_start) . $sanitized . substr($html, $i + 1);
        $len = strlen($html);
        $i = $new_i;

        $in_tag = false;
        $tag_name = '';
        continue;
      }
    }
    elseif ( $in_tag && $trk_name )
    {
      $is_alphabetical = ( strtolower($chr) != strtoupper($chr) || in_array($chr, array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')) || $chr == '?' || $chr == '!' || $chr == '-' );
      if ( $is_alphabetical )
        $tag_name .= $chr;
      else
      {
        $trk_name = false;
      }
    }

  }
  
  // Vulnerability from ha.ckers.org/xss.html:
  // <script src="http://foo.com/xss.js"
  // <
  // The rule is so specific because everything else will have been filtered by now
  $html = preg_replace('/<(script|iframe)(.+?)src=([^>]*)</i', '&lt;\\1\\2src=\\3&lt;', $html);

  // Restore stripped comments
  $i = 0;
  foreach ( $comment_match[0] as $comment )
  {
    $html = str_replace_once("{HTMLCOMMENT:$i:$rand_seed}", $comment, $html);
    $i++;
  }
  
  // Restore stripped code
  $i = 0;
  foreach ( $code_match[0] as $code )
  {
    $html = str_replace_once("{TW_CODE:$i:$rand_seed}", $code, $html);
    $i++;
  }

  return $html;
}

/**
 * Using the same parsing code as sanitize_html(), this function adds <litewiki> tags around certain block-level elements
 * @param string $html The input HTML
 * @return string formatted HTML
 */

function wikiformat_process_block($html)
{

  $tok1 = "<litewiki>";
  $tok2 = "</litewiki>";

  $block_tags = array('div', 'p', 'table', 'blockquote', 'pre');

  $len = strlen($html);
  $in_quote = false;
  $quote_char = '';
  $tag_start = 0;
  $tag_name = '';
  $in_tag = false;
  $trk_name = false;

  $diag = 0;

  $block_tagname = '';
  $in_blocksec = 0;
  $block_start = 0;

  for ( $i = 0; $i < $len; $i++ )
  {
    $chr = $html{$i};
    $prev = ( $i == 0 ) ? '' : $html{ $i - 1 };
    $next = ( ( $i + 1 ) == $len ) ? '' : $html { $i + 1 };

    // Are we inside of a quoted section?
    if ( $in_quote && $in_tag )
    {
      if ( $quote_char == $chr && $prev != '\\' )
        $in_quote = false;
    }
    elseif ( ( $chr == '"' || $chr == "'" ) && $prev != '\\' && $in_tag )
    {
      $in_quote = true;
      $quote_char = $chr;
    }

    if ( $chr == '<' && !$in_tag && $next == '/' )
    {
      // Iterate through until we've got a tag name
      $tag_name = '';
      $i++;
      while(true)
      {
        $i++;
        // echo $i . ' ';
        $chr = $html{$i};
        $prev = ( $i == 0 ) ? '' : $html{ $i - 1 };
        $next = ( ( $i + 1 ) == $len ) ? '' : $html { $i + 1 };
        $tag_name .= $chr;
        if ( $next == '>' )
          break;
      }
      // echo '<br />';
      if ( in_array($tag_name, $block_tags) )
      {
        if ( $block_tagname == $tag_name )
        {
          $in_blocksec -= 1;
          if ( $in_blocksec == 0 )
          {
            $block_tagname = '';
            $i += 2;
            // echo 'Finished wiki litewiki wraparound calc at pos: ' . $i;
            $full_litewiki = substr($html, $block_start, ( $i - $block_start ));
            $new_text = "{$tok1}{$full_litewiki}{$tok2}";
            $html = substr($html, 0, $block_start) . $new_text . substr($html, $i);

            $i += ( strlen($tok1) + strlen($tok2) ) - 1;
            $len = strlen($html);

            //die('<pre>' . htmlspecialchars($html) . '</pre>');
          }
        }
      }

      $in_tag = false;
      $in_quote = false;
      $tag_name = '';

      continue;
    }
    else if ( $chr == '<' && !$in_tag && $next != '/' )
    {
      // start of a tag
      $tag_start = $i;
      $in_tag = true;
      $trk_name = true;
    }
    else if ( !$in_quote && $in_tag && $chr == '>' )
    {
      if ( !in_array($tag_name, $block_tags) )
      {
        // Inline tag - reset and go to the next one
        // echo '&lt;inline ' . $tag_name . '&gt; ';

        $in_tag = false;
        $tag_name = '';
        continue;
      }
      else
      {
        // echo '&lt;block: ' . $tag_name . ' @ ' . $i . '&gt;<br/>';
        if ( $in_blocksec == 0 )
        {
          //die('Found a starting tag for a block element: ' . $tag_name . ' at pos ' . $tag_start);
          $block_tagname = $tag_name;
          $block_start = $tag_start;
          $in_blocksec++;
        }
        else if ( $block_tagname == $tag_name )
        {
          $in_blocksec++;
        }

        $in_tag = false;
        $tag_name = '';
        continue;
      }
    }
    elseif ( $in_tag && $trk_name )
    {
      $is_alphabetical = ( strtolower($chr) != strtoupper($chr) || in_array($chr, array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')) || $chr == '?' || $chr == '!' || $chr == '-' );
      if ( $is_alphabetical )
        $tag_name .= $chr;
      else
      {
        $trk_name = false;
      }
    }

    // Tokenization complete

  }

  $regex = '/' . str_replace('/', '\\/', preg_quote($tok2)) . '([\s]*)' . preg_quote($tok1) . '/is';
  // die(htmlspecialchars($regex));
  $html = preg_replace($regex, '\\1', $html);

  return $html;

}

function htmlalternatives($string)
{
  $ret = '';
  for ( $i = 0; $i < strlen($string); $i++ )
  {
    $chr = $string{$i};
    $ch1 = ord($chr);
    $ch2 = dechex($ch1);
    $byte = '(&\\#([0]*){0,7}' . $ch1 . ';|\\\\([0]*){0,7}' . $ch1 . ';|\\\\([0]*){0,7}' . $ch2 . ';|&\\#x([0]*){0,7}' . $ch2 . ';|%([0]*){0,7}' . $ch2 . '|' . preg_quote($chr) . ')';
    $ret .= $byte;
    $ret .= '([\s]){0,2}';
  }
  return $ret;
}

/**
 * Paginates (breaks into multiple pages) a MySQL result resource, which is treated as unbuffered.
 * @param resource The MySQL result resource. This should preferably be an unbuffered query.
 * @param string A template, with variables being named after the column name
 * @param int The number of total results. This should be determined by a second query.
 * @param string sprintf-style formatting string for URLs for result pages. First parameter will be start offset.
 * @param int Optional. Start offset in individual results. Defaults to 0.
 * @param int Optional. The number of results per page. Defualts to 10.
 * @param int Optional. An associative array of functions to call, with key names being column names, and values being function names. Values can also be an array with key 0 being either an object or a string(class name) and key 1 being a [static] method.
 * @param string Optional. The text to be sent before the result list, only if there are any results. Possibly the start of a table.
 * @param string Optional. The text to be sent after the result list, only if there are any results. Possibly the end of a table.
 * @return string
 */

function paginate($q, $tpl_text, $num_results, $result_url, $start = 0, $perpage = 10, $callers = Array(), $header = '', $footer = '')
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $parser = $template->makeParserText($tpl_text);
  $num_pages = ceil ( $num_results / $perpage );
  $out = '';
  $i = 0;
  $this_page = ceil ( $start / $perpage );

  // Build paginator
  $pg_css = ( strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ) ?
            // IE-specific hack
            'display: block; width: 1px;':
            // Other browsers
            'display: table; margin: 10px 0 0 auto;';
  $begin = '<div class="tblholder" style="'. $pg_css . '">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr><th>' . $lang->get('paginate_lbl_page') . '</th>';
  $block = '<td class="row1" style="text-align: center;">{LINK}</td>';
  $end = '</tr></table></div>';
  $blk = $template->makeParserText($block);
  $inner = '';
  $cls = 'row2';
  if ( $num_pages < 5 )
  {
    for ( $i = 0; $i < $num_pages; $i++ )
    {
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($i * $perpage);
      $url = htmlspecialchars(sprintf($result_url, $offset));
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>$j</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>$j</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }
  }
  else
  {
    if ( $this_page + 5 > $num_pages )
    {
      $list = Array();
      $tp = $this_page;
      if ( $this_page + 0 == $num_pages ) $tp = $tp - 3;
      if ( $this_page + 1 == $num_pages ) $tp = $tp - 2;
      if ( $this_page + 2 == $num_pages ) $tp = $tp - 1;
      for ( $i = $tp - 1; $i <= $tp + 1; $i++ )
      {
        $list[] = $i;
      }
    }
    else
    {
      $list = Array();
      $current = $this_page;
      $lower = ( $current < 3 ) ? 1 : $current - 1;
      for ( $i = 0; $i < 3; $i++ )
      {
        $list[] = $lower + $i;
      }
    }
    $url = sprintf($result_url, '0');
    $link = ( 0 == $start ) ? "<b>" . $lang->get('paginate_btn_first') . "</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>&laquo; " . $lang->get('paginate_btn_first') . "</a>";
    $blk->assign_vars(array(
      'CLASS'=>$cls,
      'LINK'=>$link
      ));
    $inner .= $blk->run();

    // if ( !in_array(1, $list) )
    // {
    //   $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    //   $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
    //   $inner .= $blk->run();
    // }

    foreach ( $list as $i )
    {
      if ( $i == $num_pages )
        break;
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($i * $perpage);
      $url = sprintf($result_url, $offset);
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>$j</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>$j</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }

    $total = $num_pages * $perpage - $perpage;

    if ( $this_page < $num_pages )
    {
      // $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      // $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
      // $inner .= $blk->run();

      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($total);
      $url = sprintf($result_url, $offset);
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>" . $lang->get('paginate_btn_last') . "</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>" . $lang->get('paginate_btn_last') . " &raquo;</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }

  }

  $inner .= '<td class="row2" style="cursor: pointer;" onclick="paginator_goto(this, '.$this_page.', '.$num_pages.', '.$perpage.', unescape(\'' . rawurlencode($result_url) . '\'));">&darr;</td>';

  $paginator = "\n$begin$inner$end\n";
  $out .= $paginator;

  $cls = 'row2';

  if ( $row = $db->fetchrow($q) )
  {
    $i = 0;
    $out .= $header;
    do {
      $i++;
      if ( $i <= $start )
      {
        continue;
      }
      if ( ( $i - $start ) > $perpage )
      {
        break;
      }
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      foreach ( $row as $j => $val )
      {
        if ( isset($callers[$j]) )
        {
          $tmp = ( is_callable($callers[$j]) ) ? @call_user_func($callers[$j], $val, $row) : $val;

          if ( is_string($tmp) )
          {
            $row[$j] = $tmp;
          }
        }
      }
      $parser->assign_vars($row);
      $parser->assign_vars(array('_css_class' => $cls));
      $out .= $parser->run();
    } while ( $row = @$db->fetchrow($q) );
    $out .= $footer;
  }

  $out .= $paginator;

  return $out;
}

/**
 * This is the same as paginate(), but it processes an array instead of a MySQL result resource.
 * @param array The results. Each value is simply echoed.
 * @param int The number of total results. This should be determined by a second query.
 * @param string sprintf-style formatting string for URLs for result pages. First parameter will be start offset.
 * @param int Optional. Start offset in individual results. Defaults to 0.
 * @param int Optional. The number of results per page. Defualts to 10.
 * @param string Optional. The text to be sent before the result list, only if there are any results. Possibly the start of a table.
 * @param string Optional. The text to be sent after the result list, only if there are any results. Possibly the end of a table.
 * @return string
 */

function paginate_array($q, $num_results, $result_url, $start = 0, $perpage = 10, $header = '', $footer = '')
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $num_pages = ceil ( $num_results / $perpage );
  $out = '';
  $i = 0;
  $this_page = ceil ( $start / $perpage );

  // Build paginator
  $begin = '<div class="tblholder" style="display: table; margin: 10px 0 0 auto;">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr><th>' . $lang->get('paginate_lbl_page') . '</th>';
  $block = '<td class="row1" style="text-align: center;">{LINK}</td>';
  $end = '</tr></table></div>';
  $blk = $template->makeParserText($block);
  $inner = '';
  $cls = 'row2';
  $total = $num_pages * $perpage - $perpage;
  /*
  if ( $start > 0 )
  {
    $url = sprintf($result_url, abs($start - $perpage));
    $link = "<a href=".'"'."$url".'"'." style='text-decoration: none;'>&laquo; " . $lang->get('paginate_btn_prev') . "</a>";
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    $blk->assign_vars(array(
      'CLASS'=>$cls,
      'LINK'=>$link
      ));
    $inner .= $blk->run();
  }
  */
  if ( $num_pages < 5 )
  {
    for ( $i = 0; $i < $num_pages; $i++ )
    {
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($i * $perpage);
      $url = htmlspecialchars(sprintf($result_url, $offset));
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>$j</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>$j</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }
  }
  else
  {
    if ( $this_page + 5 > $num_pages )
    {
      $list = Array();
      $tp = $this_page;
      if ( $this_page + 0 == $num_pages ) $tp = $tp - 3;
      if ( $this_page + 1 == $num_pages ) $tp = $tp - 2;
      if ( $this_page + 2 == $num_pages ) $tp = $tp - 1;
      for ( $i = $tp - 1; $i <= $tp + 1; $i++ )
      {
        $list[] = $i;
      }
    }
    else
    {
      $list = Array();
      $current = $this_page;
      $lower = ( $current < 3 ) ? 1 : $current - 1;
      for ( $i = 0; $i < 3; $i++ )
      {
        $list[] = $lower + $i;
      }
    }
    $url = sprintf($result_url, '0');
    $link = ( 0 == $start ) ? "<b>" . $lang->get('paginate_btn_first') . "</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>&laquo; " . $lang->get('paginate_btn_first') . "</a>";
    $blk->assign_vars(array(
      'CLASS'=>$cls,
      'LINK'=>$link
      ));
    $inner .= $blk->run();

    // if ( !in_array(1, $list) )
    // {
    //   $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    //   $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
    //   $inner .= $blk->run();
    // }

    foreach ( $list as $i )
    {
      if ( $i == $num_pages )
        break;
      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($i * $perpage);
      $url = sprintf($result_url, $offset);
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>$j</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>$j</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }

    if ( $this_page < $num_pages )
    {
      // $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      // $blk->assign_vars(array('CLASS'=>$cls,'LINK'=>'...'));
      // $inner .= $blk->run();

      $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
      $offset = strval($total);
      $url = sprintf($result_url, $offset);
      $j = $i + 1;
      $link = ( $offset == strval($start) ) ? "<b>" . $lang->get('paginate_btn_last') . "</b>" : "<a href=".'"'."$url".'"'." style='text-decoration: none;'>" . $lang->get('paginate_btn_last') . " &raquo;</a>";
      $blk->assign_vars(array(
        'CLASS'=>$cls,
        'LINK'=>$link
        ));
      $inner .= $blk->run();
    }

  }

  /*
  if ( $start < $total )
  {
    $link_offset = abs($start + $perpage);
    $url = htmlspecialchars(sprintf($result_url, strval($link_offset)));
    $link = "<a href=".'"'."$url".'"'." style='text-decoration: none;'>" . $lang->get('paginate_btn_next') . " &raquo;</a>";
    $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
    $blk->assign_vars(array(
      'CLASS'=>$cls,
      'LINK'=>$link
      ));
    $inner .= $blk->run();
  }
  */

  $inner .= '<td class="row2" style="cursor: pointer;" onclick="paginator_goto(this, '.$this_page.', '.$num_pages.', '.$perpage.', unescape(\'' . rawurlencode($result_url) . '\'));">&darr;</td>';

  $paginator = "\n$begin$inner$end\n";
  if ( $total > 1 )
  {
    $out .= $paginator;
  }

  $cls = 'row2';

  if ( sizeof($q) > 0 )
  {
    $i = 0;
    $out .= $header;
    foreach ( $q as $val ) {
      $i++;
      if ( $i <= $start )
      {
        continue;
      }
      if ( ( $i - $start ) > $perpage )
      {
        break;
      }
      $out .= $val;
    }
    $out .= $footer;
  }

  if ( $total > 1 )
    $out .= $paginator;

  return $out;
}

/**
 * Enano version of fputs for debugging
 */

function enano_fputs($socket, $data)
{
  // echo '<pre>' . htmlspecialchars($data) . '</pre>';
  // flush();
  // ob_flush();
  // ob_end_flush();
  return fputs($socket, $data);
}

/**
 * Sanitizes a page URL string so that it can safely be stored in the database.
 * @param string Page ID to sanitize
 * @return string Cleaned text
 */

function sanitize_page_id($page_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( isset($paths->nslist['User']) )
  {
    if ( preg_match('/^' . str_replace('/', '\\/', preg_quote($paths->nslist['User'])) . '/', $page_id) )
    {
      $ip = preg_replace('/^' . str_replace('/', '\\/', preg_quote($paths->nslist['User'])) . '/', '', $page_id);
      if ( is_valid_ip($ip) )
      {
        return $page_id;
      }
    }
  }
  
  if ( empty($page_id) )
    return '';
  
  // Remove character escapes
  $page_id = dirtify_page_id($page_id);

  $pid_clean = preg_replace('/[\w\.\/:;\(\)@\[\]_-]/', 'X', $page_id);
  $pid_dirty = enano_str_split($pid_clean, 1);
  
  foreach ( $pid_dirty as $id => $char )
  {
    if ( $char == 'X' )
      continue;
    $cid = ord($char);
    $cid = dechex($cid);
    $cid = strval($cid);
    if ( strlen($cid) < 2 )
    {
      $cid = strtoupper("0$cid");
    }
    $pid_dirty[$id] = ".$cid";
  }

  $pid_chars = enano_str_split($page_id, 1);
  $page_id_cleaned = '';

  foreach ( $pid_chars as $id => $char )
  {
    if ( $pid_dirty[$id] == 'X' )
      $page_id_cleaned .= $char;
    else
      $page_id_cleaned .= $pid_dirty[$id];
  }
  
  // global $mime_types;

  // $exts = array_keys($mime_types);
  // $exts = '(' . implode('|', $exts) . ')';

  // $page_id_cleaned = preg_replace('/\.2e' . $exts . '$/', '.\\1', $page_id_cleaned);

  return $page_id_cleaned;
}

/**
 * Removes character escapes in a page ID string
 * @param string Page ID string to dirty up
 * @return string
 */

function dirtify_page_id($page_id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  // First, replace spaces with underscores
  $page_id = str_replace(' ', '_', $page_id);

  // Exception for userpages for IP addresses
  $pid_ip_check = ( is_object($paths) ) ? preg_replace('+^' . preg_quote($paths->nslist['User']) . '+', '', $page_id) : $page_id;
  if ( is_valid_ip($pid_ip_check) )
  {
    return $page_id;
  }

  preg_match_all('/\.[A-Fa-f0-9][A-Fa-f0-9]/', $page_id, $matches);

  foreach ( $matches[0] as $id => $char )
  {
    $char = substr($char, 1);
    $char = strtolower($char);
    $char = intval(hexdec($char));
    $char = chr($char);
    if ( preg_match('/^[\w\.\/:;\(\)@\[\]_-]$/', $char) )
      continue;
    $page_id = str_replace($matches[0][$id], $char, $page_id);
  }
  
  return $page_id;
}

/**
 * Inserts commas into a number to make it more human-readable. Floating point-safe and doesn't flirt with the number like number_format() does.
 * @param int The number to process
 * @return string Input number with commas added
 */

function commatize($num)
{
  $num = (string)$num;
  if ( strpos($num, '.') )
  {
    $whole = explode('.', $num);
    $num = $whole[0];
    $dec = $whole[1];
  }
  else
  {
    $whole = $num;
  }
  $offset = ( strlen($num) ) % 3;
  $len = strlen($num);
  $offset = ( $offset == 0 )
    ? 3
    : $offset;
  for ( $i = $offset; $i < $len; $i=$i+3 )
  {
    $num = substr($num, 0, $i) . ',' . substr($num, $i, $len);
    $len = strlen($num);
    $i++;
  }
  if ( isset($dec) )
  {
    return $num . '.' . $dec;
  }
  else
  {
    return $num;
  }
}

/**
 * Injects a string into another string at the specified position.
 * @param string The haystack
 * @param string The needle
 * @param int    Position at which to insert the needle
 */

function inject_substr($haystack, $needle, $pos)
{
  $str1 = substr($haystack, 0, $pos);
  $pos++;
  $str2 = substr($haystack, $pos);
  return "{$str1}{$needle}{$str2}";
}

/**
 * Tells if a given IP address is valid.
 * @param string suspected IP address
 * @return bool true if valid, false otherwise
 */

function is_valid_ip($ip)
{
  // This next one came from phpBB3.
  $ipv4 = '(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])';
  $ipv6 = '(?:[a-f0-9]{0,4}):(?:[a-f0-9]{0,4}):(?:[a-f0-9]{0,4}:|:)?(?:[a-f0-9]{0,4}:|:)?(?:[a-f0-9]{0,4}:|:)?(?:[a-f0-9]{0,4}:|:)?(?:[a-f0-9]{0,4}:|:)?(?:[a-f0-9]{1,4})';

  if ( preg_match("/^{$ipv4}$/", $ip) || preg_match("/^{$ipv6}$/", $ip) )
    return true;
  else
    return false;
}

/**
 * Replaces the FIRST given occurrence of needle within haystack with thread
 * @param string Needle
 * @param string Thread
 * @param string Haystack
 */

function str_replace_once($needle, $thread, $haystack)
{
  $needle_len = strlen($needle);
  if ( $pos = strstr($haystack, $needle) )
  {
    $upto = substr($haystack, 0, ( strlen($haystack) - strlen($pos) ));
    $from = substr($pos, $needle_len);
    return "{$upto}{$thread}{$from}";
  }
  return $haystack;
}

/**
 * Replaces all given occurences of needle in haystack, case insensitively.
 * @param string Needle
 * @param string Thread
 * @param string Haystack
 * @return string
 */

function str_replace_i($needle, $thread, $haystack)
{
  $needle_len = strlen($needle);
  $haystack_len = strlen($haystack);
  for ( $i = 0; $i < $haystack_len; $i++ )
  {
    $test = substr($haystack, $i, $needle_len);
    if ( strtolower($test) == strtolower($needle) )
    {
      // Got it!
      $upto = substr($haystack, 0, $i);
      $from = substr($haystack, ( $i + $needle_len ));
      $haystack = "{$upto}{$thread}{$from}";
      $haystack_len = strlen($haystack);
      $i = $i + strlen($thread);
    }
  }
  return $haystack;
}

/**
 * Highlights a term in a string.
 * @param string Needle (term to highlight)
 * @param string Haystack (search string)
 * @param string Starting tag (<b>)
 * @param string Ending tag (</b>)
 * @return string
 */

function highlight_term($needle, $haystack, $start_tag = '<b>', $end_tag = '</b>')
{
  $needle_len = strlen($needle);
  $haystack_len = strlen($haystack);
  for ( $i = 0; $i < $haystack_len; $i++ )
  {
    $test = substr($haystack, $i, $needle_len);
    if ( strtolower($test) == strtolower($needle) )
    {
      // Got it!
      $upto = substr($haystack, 0, $i);
      $from = substr($haystack, ( $i + $needle_len ));
      $haystack = "{$upto}{$start_tag}{$test}{$end_tag}{$from}";
      $haystack_len = strlen($haystack);
      $i = $i + strlen($needle) + strlen($start_tag) + strlen($end_tag);
    }
  }
  return $haystack;
}

/**
 * Registers a new type of search result. Because this is so tricky to do but keep clean, this function takes an associative array as its
 * only parameter. This array configures the function. The required keys are:
 *  - table: the database table to search
 *  - titlecolumn: the column that will be used as the title of the search result. This will have a weight of 1.5
 *  - uniqueid: a TPL-format string, variables being column names, that will be unique for every result. This should contain a string that
 *              will be specific to your *type* of search result in addition to a primary key or other unique identifier.
 *  - linkformat: an array with the keys page_id and namespace which are where your result will link, plus the following additional options:
 *     - append: added to the full generated URL
 *     - query: query string without initial "?"
 * Additional options:
 *  - datacolumn
 *  - additionalcolumns: additional data to select if you want to use a custom formatting callback
 *  - formatcallback: a callback or TPL string. If a callback, it will be called with the parameters being the current result row and an
 *                    array of words in case you want to highlight anything; the callback will be expected to return a string containing
 *                    a fully formatted and sanitized blob of HTML. If formatcallback is a TPL string, variables will be named after table
 *                    columns.
 *  - additionalwhere: additional SQL to inject into WHERE clause, in the format of "AND foo = bar"
 * @example Working example of adding users to search results:
 <code>
  register_search_handler(array(
      'table' => 'users',
      'titlecolumn' => 'username',
      'uniqueid' => 'ns=User;cid={username}',
      'additionalcolumns' => array('user_id'),
      'resultnote' => '[Member]',
      'linkformat' => array(
          'page_id' => '{username}',
          'namespace' => 'User'
        ),
      'formatcallback' => 'format_user_search_result',
    ));
  
  function format_user_search_result($row)
  {
    global $session, $lang;
    $rankdata = $session->get_user_rank(intval($row['user_id']));
    $rankspan = '<span style="' . $rankdata['rank_style'] . '">' . $lang->get($rankdata['rank_title']) . '</span>';
    if ( empty($rankdata['user_title']) )
    {
      return $rankspan;
    }
    else
    {
      return '"' . htmlspecialchars($rankdata['user_title']) . "\" (<b>$rankspan</b>)";
    }
  }
 </code>
 * @param array Options array - see function documentation
 * @return null
 */

global $search_handlers;
$search_handlers = array();

function register_search_handler($options)
{
  global $search_handlers;
  
  $required = array('table', 'titlecolumn', 'uniqueid', 'linkformat');
  foreach ( $required as $key )
  {
    if ( !isset($options[$key]) )
    {
      throw new Exception("Required search handler option '$key' is missing");
    }
  }
  $search_handlers[] = $options;
  return null;
}

/**
 * From http://us2.php.net/urldecode - decode %uXXXX
 * @param string The urlencoded string
 * @return string
 */

function decode_unicode_url($str)
{
  $res = '';

  $i = 0;
  $max = strlen($str) - 6;
  while ($i <= $max)
  {
    $character = $str[$i];
    if ($character == '%' && $str[$i + 1] == 'u')
    {
      $value = hexdec(substr($str, $i + 2, 4));
      $i += 6;

      if ($value < 0x0080)
      {
        // 1 byte: 0xxxxxxx
        $character = chr($value);
      }
      else if ($value < 0x0800)
      {
        // 2 bytes: 110xxxxx 10xxxxxx
        $character =
            chr((($value & 0x07c0) >> 6) | 0xc0)
          . chr(($value & 0x3f) | 0x80);
      }
      else
      {
        // 3 bytes: 1110xxxx 10xxxxxx 10xxxxxx
        $character =
            chr((($value & 0xf000) >> 12) | 0xe0)
          . chr((($value & 0x0fc0) >> 6) | 0x80)
          . chr(($value & 0x3f) | 0x80);
      }
    }
    else
    {
      $i++;
    }

    $res .= $character;
  }

  return $res . substr($str, $i);
}

/**
 * Recursively decodes an array with UTF-8 characters in its strings
 * @param array Can be multi-depth
 * @return array
 */

function decode_unicode_array($array)
{
  foreach ( $array as $i => $val )
  {
    if ( is_string($val) )
    {
      $array[$i] = decode_unicode_url($val);
    }
    else if ( is_array($val) )
    {
      $array[$i] = decode_unicode_array($val);
    }
  }
  return $array;
}

/**
 * Sanitizes a page tag.
 * @param string
 * @return string
 */

function sanitize_tag($tag)
{
  $tag = strtolower($tag);
  $tag = preg_replace('/[^\w @\$%\^&-]+/', '', $tag);
  $tag = str_replace('_', ' ', $tag);
  $tag = trim($tag);
  return $tag;
}

/**
 * Replacement for gzencode() which doesn't always work.
 * @param string Data to compress
 * @param int Compression level - 0 (no compression) to 9 (maximum compression)
 * @param string Filename to encode into the compressed data - defaults to blank
 * @param string Comment for archive - defaults to blank
 * @return string Compressed data
 */

function enano_gzencode($data = "", $level = 6, $filename = "", $comments = "")
{
  $flags = (empty($comment)? 0 : 16) + (empty($filename)? 0 : 8);
  $mtime = time();
  
  if ( !function_exists('gzdeflate') )
    return false;
 
  return (pack("C1C1C1C1VC1C1", 0x1f, 0x8b, 8, $flags, $mtime, 2, 0xFF) .
          (empty($filename) ? "" : $filename . "\0") .
          (empty($comment) ? "" : $comment . "\0") .
          gzdeflate($data, $level) .
          pack("VV", crc32($data), strlen($data)));
}

$php_errors = array();

/**
 * Enano's PHP error handler.
 * handler  ( int $errno  , string $errstr  [, string $errfile  [, int $errline  [, array $errcontext  ]]] )
 * @access private
 */

function enano_handle_error($errno, $errstr, $errfile, $errline)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $er = error_reporting();
  if ( ! $er & $errno || $er == 0 )
  {
    return true;
  }
  global $do_gzip, $php_errors;
  
  if ( defined('ENANO_DEBUG') )
  {
    // turn off gzip and echo out error immediately for debug installs
    $do_gzip = false;
  }
  
  $error_type = 'error';
  if ( in_array($errno, array(E_WARNING, E_USER_WARNING)) )
    $error_type = 'warning';
  else if ( in_array($errno, array(E_NOTICE, E_USER_NOTICE)) )
    $error_type = 'notice';
  
  if ( @is_object(@$plugins) )
  {
    $code = $plugins->setHook('php_error');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
  }
  
  // bypass errors in date() and mktime() (Enano has its own code for this anyway)
  if ( strstr($errstr, "It is not safe to rely on the system's timezone settings. Please use the date.timezone setting, the TZ environment variable or the date_default_timezone_set() function. In case you used any of those methods and you are still getting this warning, you most likely misspelled the timezone identifier.") )
  {
    return true;
  }
  
  if ( $do_gzip )
  {
    $php_errors[] = array(
        'num' => $errno,
        'type' => $error_type,
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline
      );
  }
  else
  {
    echo "[ <b>PHP $error_type:</b> $errstr in <b>$errfile</b>:<b>$errline</b> ]<br />";
  }
}

set_error_handler('enano_handle_error');

/**
 * Gzips the output buffer.
 */

function gzip_output()
{
  global $do_gzip;
  
  //
  // Compress buffered output if required and send to browser
  // Sorry, doesn't work in IE. What else is new?
  //
  if ( $do_gzip && function_exists('gzdeflate') && !strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') && !headers_sent() )
  {
    $gzip_contents = ob_get_contents();
    ob_end_clean();
    
    global $php_errors;
    if ( !empty($php_errors) )
    {
      $errors = '';
      foreach ( $php_errors as $error )
      {
        $errors .= "[ <b>PHP {$error['type']}:</b> {$error['error']} in <b>{$error['file']}</b>:<b>{$error['line']}</b> ]<br />";
      }
      $gzip_contents = str_replace("</body>", "$errors</body>", $gzip_contents);
    }
    
    $return = @enano_gzencode($gzip_contents);
    if ( $return )
    {
      header('Content-encoding: gzip');
      echo $return;
    }
    else
    {
      echo $gzip_contents;
    }
  }
}

/**
 * Aggressively and hopefully non-destructively optimizes a blob of HTML.
 * @param string HTML to process
 * @return string much smaller HTML
 */

function aggressive_optimize_html($html)
{
  $size_before = strlen($html);
  
  // kill carriage returns
  $html = str_replace("\r", "", $html);
  
  // Which tags to strip for JAVASCRIPT PROCESSING ONLY - you can change this if needed
  $strip_tags = Array('enano:no-opt');
  $strip_tags = implode('|', $strip_tags);
  
  // Strip out the tags and replace with placeholders
  preg_match_all("#<($strip_tags)([ ]+.*?)?>(.*?)</($strip_tags)>#is", $html, $matches);
  $seed = md5(microtime() . mt_rand()); // Random value used for placeholders
  for ($i = 0;$i < sizeof($matches[1]); $i++)
  {
    $html = str_replace($matches[0][$i], "{DONT_STRIP_ME_NAKED:$seed:$i}", $html);
  }
  
  // Optimize (but don't obfuscate) Javascript
  preg_match_all('/<script([ ]+.*?)?>(.*?)(\]\]>)?<\/script>/is', $html, $jscript);
  require_once(ENANO_ROOT . '/includes/js-compressor.php');
  $jsc = new JavascriptCompressor();
  
  // list of Javascript reserved words - from about.com
  $reserved_words = array('abstract', 'as', 'boolean', 'break', 'byte', 'case', 'catch', 'char', 'class', 'continue', 'const', 'debugger', 'default', 'delete', 'do',
                          'double', 'else', 'enum', 'export', 'extends', 'false', 'final', 'finally', 'float', 'for', 'function', 'goto', 'if', 'implements', 'import',
                          'in', 'instanceof', 'int', 'interface', 'is', 'long', 'namespace', 'native', 'new', 'null', 'package', 'private', 'protected', 'public',
                          'return', 'short', 'static', 'super', 'switch', 'synchronized', 'this', 'throw', 'throws', 'transient', 'true', 'try', 'typeof', 'use', 'var',
                          'void', 'volatile', 'while', 'with');
  
  $reserved_words = '(' . implode('|', $reserved_words) . ')';
  
  for ( $i = 0; $i < count($jscript[0]); $i++ )
  {
    $js =& $jscript[2][$i];
    if ( empty($js) )
      continue;
    
    $js = $jsc->getClean($js);
    
    $replacement = "<script{$jscript[1][$i]}>/* <![CDATA[ */ $js /* ]]> */</script>";
    // apply changes
    $html = str_replace($jscript[0][$i], $replacement, $html);
     
  }
  
  // Re-insert untouchable tags
  for ($i = 0;$i < sizeof($matches[1]); $i++)
  {
    $html = str_replace("{DONT_STRIP_ME_NAKED:$seed:$i}", "<{$matches[1][$i]}{$matches[2][$i]}>{$matches[3][$i]}</{$matches[4][$i]}>", $html);
  }
  
  // Which tags to strip - you can change this if needed
  $strip_tags = Array('pre', 'script', 'style', 'enano:no-opt', 'textarea');
  $strip_tags = implode('|', $strip_tags);
  
  // Strip out the tags and replace with placeholders
  preg_match_all("#<($strip_tags)(.*?)>(.*?)</($strip_tags)>#is", $html, $matches);
  $seed = md5(microtime() . mt_rand()); // Random value used for placeholders
  for ($i = 0;$i < sizeof($matches[1]); $i++)
  {
    $html = str_replace($matches[0][$i], "{DONT_STRIP_ME_NAKED:$seed:$i}", $html);
  }
  
  // Finally, process the HTML
  $html = preg_replace("#\n([ ]*)#", " ", $html);
  
  // Remove annoying spaces between tags
  $html = preg_replace("#>([ ][ ]+)<#", "> <", $html);
  
  // Re-insert untouchable tags
  for ($i = 0;$i < sizeof($matches[1]); $i++)
  {
    $html = str_replace("{DONT_STRIP_ME_NAKED:$seed:$i}", "<{$matches[1][$i]}{$matches[2][$i]}>{$matches[3][$i]}</{$matches[4][$i]}>", $html);
  }
  
  // Remove <enano:no-opt> blocks (can be used by themes that don't want their HTML optimized)
  $html = preg_replace('#<(\/|)enano:no-opt(.*?)>#', '', $html);
  
  $size_after = strlen($html);
  
  // Tell snoopish users what's going on
  $html = str_replace('<html', "\n".'<!-- NOTE: Enano has performed an HTML optimization routine on the HTML you see here. This is to enhance page loading speeds.
     To view the uncompressed source of this page, add the "nocompress" parameter to the URI of this page: index.php?title=Main_Page&nocompress or Main_Page?nocompress'."
     Size before compression: $size_before bytes
     Size after compression:  $size_after bytes
     -->\n<html", $html);
  return $html;
}

/**
 * For an input range of numbers (like 25-256) returns an array filled with all numbers in the range, inclusive.
 * @param string
 * @return array
 */

function int_range($range)
{
  if ( strval(intval($range)) == $range )
    return $range;
  if ( !preg_match('/^[0-9]+(-[0-9]+)?$/', $range) )
    return false;
  $ends = explode('-', $range);
  if ( count($ends) != 2 )
    return $range;
  $ret = array();
  if ( $ends[1] < $ends[0] )
    $ends = array($ends[1], $ends[0]);
  else if ( $ends[0] == $ends[1] )
    return array($ends[0]);
  for ( $i = $ends[0]; $i <= $ends[1]; $i++ )
  {
    $ret[] = $i;
  }
  return $ret;
}

/**
 * Parses a range or series of IP addresses, and returns the raw addresses. Only parses ranges in the last two octets to prevent DOSing.
 * Syntax for ranges: x.x.x.x; x|y.x.x.x; x.x.x-z.x; x.x.x-z|p.q|y
 * @param string IP address range string
 * @return array
 */

function parse_ip_range($range)
{
  $octets = explode('.', $range);
  if ( count($octets) != 4 )
    // invalid range
    return $range;
  $i = 0;
  $possibilities = array( 0 => array(), 1 => array(), 2 => array(), 3 => array() );
  foreach ( $octets as $octet )
  {
    $existing =& $possibilities[$i];
    $inner = explode('|', $octet);
    foreach ( $inner as $bit )
    {
      if ( $i >= 2 )
      {
        $bits = int_range($bit);
        if ( $bits === false )
          return false;
        else if ( !is_array($bits) )
          $existing[] = intval($bits);
        else
          $existing = array_merge($existing, $bits);
      }
      else
      {
        $bit = intval($bit);
        $existing[] = $bit;
      }
    }
    $existing = array_unique($existing);
    $i++;
  }
  $ips = array();
  
  // The only way to combine all those possibilities. ;-)
  foreach ( $possibilities[0] as $oc1 )
    foreach ( $possibilities[1] as $oc2 )
      foreach ( $possibilities[2] as $oc3 )
        foreach ( $possibilities[3] as $oc4 )
          $ips[] = "$oc1.$oc2.$oc3.$oc4";
        
  return $ips;
}

/**
 * Parses a valid IP address range into a regular expression.
 * @param string IP range string
 * @return string
 */

function parse_ip_range_regex($range)
{
  if ( strstr($range, ':') )
  {
    return parse_ipv6_range_regex($range);
  }
  else
  {
    return parse_ipv4_range_regex($range);
  }
}

/**
 * Parses a valid IPv4 address range into a regular expression.
 * @param string IP range string
 * @return string
 */

function parse_ipv4_range_regex($range)
{
  // Regular expression to test the range string for validity
  $regex = '/^(([0-9]+(-[0-9]+)?)(\|([0-9]+(-[0-9]+)?))*)\.'
           . '(([0-9]+(-[0-9]+)?)(\|([0-9]+(-[0-9]+)?))*)\.'
           . '(([0-9]+(-[0-9]+)?)(\|([0-9]+(-[0-9]+)?))*)\.'
           . '(([0-9]+(-[0-9]+)?)(\|([0-9]+(-[0-9]+)?))*)$/';
  if ( !preg_match($regex, $range) )
  {
    return false;
  }
  $octets = array(0 => array(), 1 => array(), 2 => array(), 3 => array());
  list($octets[0], $octets[1], $octets[2], $octets[3]) = explode('.', $range);
  $return = '^';
  foreach ( $octets as $octet )
  {
    // alternatives array
    $alts = array();
    if ( strpos($octet, '|') )
    {
      $particles = explode('|', $octet);
    }
    else
    {
      $particles = array($octet);
    }
    foreach ( $particles as $atom )
    {
      // each $atom will be either
      if ( strval(intval($atom)) == $atom )
      {
        $alts[] = $atom;
        continue;
      }
      else
      {
        // it's a range - parse it out
        $alt2 = int_range($atom);
        if ( !$alt2 )
          return false;
        foreach ( $alt2 as $neutrino )
          $alts[] = $neutrino;
      }
    }
    $alts = array_unique($alts);
    $alts = '|' . implode('|', $alts) . '|';
    // we can further optimize/compress this by weaseling our way into using some character ranges
    for ( $i = 1; $i <= 25; $i++ )
    {
      $alts = str_replace("|{$i}0|{$i}1|{$i}2|{$i}3|{$i}4|{$i}5|{$i}6|{$i}7|{$i}8|{$i}9|", "|{$i}[0-9]|", $alts);
    }
    $alts = str_replace("|1|2|3|4|5|6|7|8|9|", "|[1-9]|", $alts);
    $alts = '(' . substr($alts, 1, -1) . ')';
    $return .= $alts . '\.';
  }
  $return = substr($return, 0, -2);
  $return .= '$';
  return $return;
}

/**
 * Parses a valid IPv6 address range into a regular expression.
 * @param string IP range string
 * @return string
 */

function parse_ipv6_range_regex($range)
{
  $range = strtolower(trim($range));
  $valid = '/^';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}):';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}):';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}:|:)?';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}:|:)?';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}:|:)?';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}:|:)?';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4}:|:)?';
  $valid .= '(?:[0-9a-f]{0,4}|[0-9a-f]{1,4}-[0-9a-f]{1,4})$/';
  if ( !preg_match($valid, $range) )
    return false;
  
  // expand address range.
  // this takes short ranges like:
  //   2001:470-471:054-b02b::5-bb
  // up to:
  //   2001:0470-0471:0054-b02b:0000:0000:0000:0005-00bb
  $range = explode(':', $range);
  $expanded = '';
  $size = count($range);
  foreach ( $range as $byteset )
  {
    if ( empty($byteset) )
    {
      // ::
      while ( $size < 9 )
      {
        $expanded .= '0000:';
        $size++;
      }
    }
    else
    {
      if ( strstr($byteset, '-') ) 
      {
        // this is a range
        $sides = explode('-', $byteset);
        foreach ( $sides as &$bytepair )
        {
          while ( strlen($bytepair) < 4 )
          {
            $bytepair = "0$bytepair";
          }
        }
        $byteset = implode('-', $sides);
      }
      else
      {
        while ( strlen($byteset) < 4 )
        {
          $byteset = "0$byteset";
        }
      }
      $expanded .= "$byteset:";
    }
  }
  $expanded = explode(':', rtrim($expanded, ':'));
  
  // ready to dive in and start generating range regexes.
  // this has to be pretty optimized... we want to end up with regexes like:
  // range: 54-b12b
  /*
  /005[4-9a-f]|
  00[6-9a-f][0-9a-f]|
  0[1-9a-f][0-9a-f][0-9a-f]|
  [1-9a][0-9a-f][0-9a-f][0-9a-f]|
  b[0-0][0-1][0-9a-f]|
  b0[0-1][0-9a-f]|
  b02[0-9a-b]/x
  */
  foreach ( $expanded as &$word )
  {
    if ( strstr($word, '-') )
    {
      // oh... damn.
      $word = '(?:' . generate_hex_numeral_range($word) . ')';
    }
  }

  // return print_r($expanded, true);  
  return '^' . implode(':', $expanded) . '$';
}

/**
 * Take a hex numeral range and parse it in to a PCRE.
 * @param string
 * @return string
 * @access private
 */

function generate_hex_numeral_range($word)
{
  list($low, $high) = explode('-', $word);
  
  if ( hexdec($low) > hexdec($high) )
  {
    $_ = $low;
    $low = $high;
    $high = $_;
    unset($_);
  }
  
  while ( strlen($low) < strlen($high) )
  {
    $low = "0$low";
  }
  
  // trim off everything that's the same
  $trimmed = '';
  $len = strlen($low);
  for ( $i = 0; $i < $len; $i++ )
  {
    if ( $low{0} === $high{0} )
    {
      $trimmed .= $low{0};
      $low = substr($low, 1);
      $high = substr($high, 1);
    }
    else
    {
      break;
    }
  }
  
  $len = strlen($high);
  if ( $len == 1 )
  {
    // this does happen sometimes, so we can save a bit of CPU power here.
    return $trimmed . __hexdigitrange($low, $high);
  }
    
  $return = '';
  // lower half
  for ( $i = $len - 1; $i > 0; $i-- )
  {
    if ( $low{$i} == 'f' )
      continue;
    $return .= $trimmed;
    for ( $j = 0; $j < $len; $j++ )
    {
      if ( $j < $i )
      {
        $return .= $low{$j};
      }
      else if ( $j == $i && ( $i == $len - 1 || $low{$j} == 'f' ) )
      {
        $return .= __hexdigitrange($low{$j}, 'f');
      }
      else if ( $j == $i && $i != $len - 1 )
      {
        $return .= __hexdigitrange(dechex(hexdec($low{$j}) + 1), 'f');
      }
      else
      {
        $return .= __hexdigitrange('0', 'f');
      }
    }
    $return .= '|';
  }
  // middle block
  if ( hexdec($low{0}) + 1 < hexdec($high{0}) )
  {
    if ( hexdec($low{0}) + 1 < hexdec($high{0}) - 1 )
      $return .= $trimmed . __hexdigitrange(dechex(hexdec($low{0}) + 1), dechex(hexdec($high{0}) - 1));
    else
      $return .= $trimmed . __hexdigitrange($low{0}, $high{0});
    if ( $len - 1 > 0 )
      $return .= '[0-9a-f]{' . ( $len - 1 ) . '}|';
  }
  // higher half
  for ( $i = 1; $i < $len; $i++ )
  {
    if ( $high{$i} == '0' )
      continue;
    $return .= $trimmed;
    for ( $j = 0; $j < $len; $j++ )
    {
      if ( $j < $i )
      {
        $return .= $high{$j};
      }
      else if ( $j == $i && ( $i == $len - 1 || $high{$j} == '0' ) )
      {
        $return .= __hexdigitrange('0', $high{$j});
      }
      else if ( $j == $i && $i != $len - 1 )
      {
        $return .= __hexdigitrange('0', dechex(hexdec($high{$j}) - 1));
      }
      else if ( $j > $i )
      {
        $return .= __hexdigitrange('0', 'f');
      }
      else
      {
        die("I don't know what to do! i $i j $j");
      }
    }
    $return .= '|';
  }
  
  return rtrim($return, '|');
}

function __hexdigitrange($low, $high)
{
  if ( $low == $high )
    return $low;
  if ( empty($low) )
    $low = '0';
  
  $low_type = ( preg_match('/[0-9]/', $low) ) ? 'num' : 'alph';
  $high_type = ( preg_match('/[0-9]/', $high) ) ? 'num' : 'alph';
  if ( ( $low_type == 'num' && $high_type == 'num') || ( $low_type == 'alph' && $high_type == 'alph' ) )
  {
    return "[$low-$high]";
  }
  else if ( $low_type == 'num' && $high_type == 'alph' )
  {
    $ret = '[';
    
    if ( $low == '9' )
      $ret .= '9';
    else
      $ret .= "$low-9";
    if ( $high == 'a' )
      $ret .= 'a';
    else
      $ret .= "a-$high";
      
    $ret .= "]";
    return $ret;
  }
  else if ( $low_type == 'alph' && $high_type == 'num' )
  {
    // ???? this should never happen
    return __hexdigitrange($high, $low); 
  }
}

/**
 * Validates an e-mail address. Uses a compacted version of the regular expression generated by the scripts at <http://examples.oreilly.com/regex/>.
 * @param string E-mail address
 * @return bool
 */

function check_email_address($email)
{
  static $regexp = '(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*|(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*(?:(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*)*<[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*(?:,[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*)*:[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)?(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*>)';
  return ( preg_match("/^$regexp$/", $email) ) ? true : false;
}

function password_score_len($password)
{
  if ( !is_string($password) )
  {
    return -10;
  }
  $len = strlen($password);
  $score = $len - 7;
  return $score;
}

/**
 * Give a numerical score for how strong a password is. This is an open-ended scale based on a score added to or subtracted
 * from based on certain complexity rules. Anything less than about 1 or 0 is weak, 3-4 is strong, and 10 is not to be easily cracked.
 * Based on the Javascript function of the same name.
 * @param string Password to test
 * @param null Will be filled with an array of debugging info
 * @return int
 */

function password_score($password, &$debug = false)
{
  if ( !is_string($password) )
  {
    return -10;
  }
  $score = 0;
  $debug = array();
  // length check
  $lenscore = password_score_len($password);
  
  $debug[] = "<b>How this score was calculated</b>\nYour score was tallied up based on an extensive algorithm which outputted\nthe following scores based on traits of your password. Above you can see the\ncomposite score; your individual scores based on certain tests are below.\n\nThe scale is open-ended, with a minimum score of -10. 10 is very strong, 4\nis strong, 1 is good and -3 is fair. Below -3 scores \"Weak.\"\n";
  
  $debug[] = 'Adding '.$lenscore.' points for length';
  
  $score += $lenscore;
    
  $has_upper_lower = false;
  $has_symbols     = false;
  $has_numbers     = false;
  
  // contains uppercase and lowercase
  if ( preg_match('/[A-z]+/', $password) && strtolower($password) != $password )
  {
    $score += 1;
    $has_upper_lower = true;
    $debug[] = 'Adding 1 point for having uppercase and lowercase';
  }
  
  // contains symbols
  if ( preg_match('/[^A-z0-9]+/', $password) )
  {
    $score += 1;
    $has_symbols = true;
    $debug[] = 'Adding 1 point for having nonalphanumeric characters (matching /[^A-z0-9]+/)';
  }
  
  // contains numbers
  if ( preg_match('/[0-9]+/', $password) )
  {
    $score += 1;
    $has_numbers = true;
    $debug[] = 'Adding 1 point for having numbers';
  }
  
  if ( $has_upper_lower && $has_symbols && $has_numbers && strlen($password) >= 9 )
  {
    // if it has uppercase and lowercase letters, symbols, and numbers, and is of considerable length, add some serious points
    $score += 4;
    $debug[] = 'Adding 4 points for having uppercase and lowercase, numbers, and nonalphanumeric and being more than 8 characters';
  }
  else if ( $has_upper_lower && $has_symbols && $has_numbers )
  {
    // still give some points for passing complexity check
    $score += 2;
    $debug[] = 'Adding 2 points for having uppercase and lowercase, numbers, and nonalphanumeric';
  }
  else if ( ( $has_upper_lower && $has_symbols ) ||
            ( $has_upper_lower && $has_numbers ) ||
            ( $has_symbols && $has_numbers ) )
  {
    // if 2 of the three main complexity checks passed, add a point
    $score += 1;
    $debug[] = 'Adding 1 point for having 2 of 3 complexity checks';
  }
  else if ( preg_match('/^[0-9]*?([a-z]+)[0-9]?$/', $password) )
  {
    // password is something like magnum1 which will be cracked in seconds
    $score += -4;
    $debug[] = 'Adding -4 points for being of the form [number][word][number]';
  }
  else if ( ( !$has_upper_lower && !$has_numbers && $has_symbols ) ||
            ( !$has_upper_lower && !$has_symbols && $has_numbers ) ||
            ( !$has_numbers && !$has_symbols && $has_upper_lower ) )
  {
    $score += -2;
    $debug[] = 'Adding -2 points for only meeting 1 complexity check';
  }
  else if ( !$has_upper_lower && !$has_numbers && !$has_symbols )
  {
    $debug[] = 'Adding -3 points for not meeting any complexity checks';
    $score += -3;
  }
  
  //
  // Repetition
  // Example: foobar12345 should be deducted points, where f1o2o3b4a5r should be given points
  //
  
  if ( preg_match('/([A-Z][A-Z][A-Z][A-Z]|[a-z][a-z][a-z][a-z])/', $password) )
  {
    $debug[] = 'Adding -2 points for having more than 4 letters of the same case in a row';
    $score += -2;
  }
  else if ( preg_match('/([A-Z][A-Z][A-Z]|[a-z][a-z][a-z])/', $password) )
  {
    $debug[] = 'Adding -1 points for having more than 3 letters of the same case in a row';
    $score += -1;
  }
  else if ( preg_match('/[A-z]/', $password) && !preg_match('/([A-Z][A-Z][A-Z]|[a-z][a-z][a-z])/', $password) )
  {
    $debug[] = 'Adding 1 point for never having more than 2 letters of the same case in a row';
    $score += 1;
  }
  
  if ( preg_match('/[0-9][0-9][0-9][0-9]/', $password) )
  {
    $debug[] = 'Adding -2 points for having 4 or more numbers in a row';
    $score += -2;
  }
  else if ( preg_match('/[0-9][0-9][0-9]/', $password) )
  {
    $debug[] = 'Adding -1 points for having 3 or more numbers in a row';
    $score += -1;
  }
  else if ( $has_numbers && !preg_match('/[0-9][0-9][0-9]/', $password) )
  {
    $debug[] = 'Adding 1 point for never more than 2 numbers in a row';
    $score += -1;
  }
  
  // make passwords like fooooooooooooooooooooooooooooooooooooo totally die by subtracting a point for each character repeated at least 3 times in a row
  $prev_char = '';
  $warn = false;
  $loss = 0;
  for ( $i = 0; $i < strlen($password); $i++ )
  {
    $chr = $password{$i};
    if ( $chr == $prev_char && $warn )
    {
      $loss += -1;
    }
    else if ( $chr == $prev_char && !$warn )
    {
      $warn = true;
    }
    else if ( $chr != $prev_char && $warn )
    {
      $warn = false;
    }
    $prev_char = $chr;
  }
  if ( $loss < 0 )
  {
    $debug[] = 'Adding '.$loss.' points for immediate character repetition';
    $score += $loss;
    // this can bring the score below -10 sometimes
    if ( $score < -10 )
    {
      $debug[] = 'Setting score to -10 because it went below ('.$score.')';
      $score = -10;
    }
  }
  
  return $score;
}

/**
 * Registers a task that will be run every X hours. Scheduled tasks should always be scheduled at runtime - they are not stored in the DB.
 * @param string Function name to call, or array(object, string method)
 * @param int Interval between runs, in hours. Defaults to 24.
 */

function register_cron_task($func, $hour_interval = 24)
{
  global $cron_tasks;
  $hour_interval = strval($hour_interval);
  if ( !isset($cron_tasks[$hour_interval]) )
    $cron_tasks[$hour_interval] = array();
  $cron_tasks[$hour_interval][] = $func;
}

/**
 * Gets the timestamp for the next estimated cron run.
 * @return int
 */

function get_cron_next_run()
{
  global $cron_tasks;
  $lowest_ivl = min(array_keys($cron_tasks));
  $last_run = intval(getConfig("cron_lastrun_ivl_$lowest_ivl"));
  return intval($last_run + ( 3600 * $lowest_ivl )) - 30;
}

/**
 * Installs a language.
 * @param string The ISO-639-3 identifier for the language. Maximum of 6 characters, usually 3.
 * @param string The name of the language in English (Spanish)
 * @param string The name of the language natively (Español)
 * @param string The path to the file containing the language's strings. Optional.
 */

function install_language($lang_code, $lang_name_neutral, $lang_name_local, $lang_file = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'language WHERE lang_code = \'' . $db->escape($lang_code) . '\';');
  if ( !$q )
    $db->_die('functions.php - checking for language existence');
  
  if ( $db->numrows() > 0 )
    // Language already exists
    return false;
  
  $q = $db->sql_query('INSERT INTO ' . table_prefix . 'language(lang_code, lang_name_default, lang_name_native) 
                         VALUES(
                           \'' . $db->escape($lang_code) . '\',
                           \'' . $db->escape($lang_name_neutral) . '\',
                           \'' . $db->escape($lang_name_local) . '\'
                         );');
  if ( !$q )
    $db->_die('functions.php - installing language');
  
  if ( ENANO_DBLAYER == 'PGSQL' )
  {
    // exception for Postgres, which doesn't support insert IDs
    // This will cause the Language class to just load by lang code
    // instead of by numeric ID
    $lang_id = $lang_code;
  }
  else
  {
    $lang_id = $db->insert_id();
    if ( empty($lang_id) || $lang_id == 0 )
    {
      $db->_die('functions.php - invalid returned lang_id');
    }
  }
  
  // Do we also need to install a language file?
  if ( is_string($lang_file) && file_exists($lang_file) )
  {
    $lang = new Language($lang_id);
    $lang->import($lang_file);
  }
  else if ( is_string($lang_file) && !file_exists($lang_file) )
  {
    echo '<b>Notice:</b> Can\'t load language file, so the specified language wasn\'t fully installed.<br />';
    return false;
  }
  return true;
}

/**
 * Lists available languages.
 * @return array Multi-depth. Associative, with children associative containing keys name, name_eng, and dir.
 */

function list_available_languages()
{
  // Pulled from install/includes/common.php
  
  // Build a list of available languages
  $dir = @opendir( ENANO_ROOT . '/language' );
  if ( !$dir )
    die('CRITICAL: could not open language directory');
  
  $languages = array();
  
  while ( $dh = @readdir($dir) )
  {
    if ( $dh == '.' || $dh == '..' )
      continue;
    if ( file_exists( ENANO_ROOT . "/language/$dh/meta.json" ) )
    {
      // Found a language directory, determine metadata
      $meta = @file_get_contents( ENANO_ROOT . "/language/$dh/meta.json" );
      if ( empty($meta) )
        // Could not read metadata file, continue silently
        continue;
        
      // Do some syntax correction on the metadata
      $meta = enano_clean_json($meta);
        
      $meta = enano_json_decode($meta);
      if ( isset($meta['lang_name_english']) && isset($meta['lang_name_native']) && isset($meta['lang_code']) )
      {
        $languages[$meta['lang_code']] = array(
            'name' => $meta['lang_name_native'],
            'name_eng' => $meta['lang_name_english'],
            'dir' => $dh
          );
      }
    }
  }
  
  return $languages;
}

/**
 * Scales an image to the specified width and height, and writes the output to the specified
 * file. Will use ImageMagick if present, but if not will attempt to scale with GD. This will
 * always scale images proportionally.
 * @param string Path to image file
 * @param string Path to output file
 * @param int Image width, in pixels
 * @param int Image height, in pixels
 * @param bool If true, the output file will be deleted if it exists before it is written
 * @return bool True on success, false on failure
 */

function scale_image($in_file, $out_file, $width = 225, $height = 225, $unlink = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !is_int($width) || !is_int($height) )
    return false;
  
  if ( !file_exists($in_file) )
    return false;
  
  $in_file = escapeshellarg($in_file);
  $out_file = escapeshellarg($out_file);
  
  if ( file_exists($out_file) && !$unlink )
    return false;
  else if ( file_exists($out_file) && $unlink )
    @unlink($out_file);
  if ( file_exists($out_file) )
    // couldn't unlink (delete) the output file
    return false;
    
  $file_ext = substr($in_file, ( strrpos($in_file, '.') + 1));
  switch($file_ext)
  {
    case 'png':
      $func = 'imagecreatefrompng';
      break;
    case 'jpg':
    case 'jpeg':
      $func = 'imagecreatefromjpeg';
      break;
    case 'gif':
      $func = 'imagecreatefromgif';
      break;
    case 'xpm':
      $func = 'imagecreatefromxpm';
      break;
    default:
      return false;
  }
    
  $magick_path = getConfig('imagemagick_path');
  $can_use_magick = (
      getConfig('enable_imagemagick') == '1' &&
      file_exists($magick_path)              &&
      is_executable($magick_path)
    );
  $can_use_gd = (
      function_exists('getimagesize')         &&
      function_exists('imagecreatetruecolor') &&
      function_exists('imagecopyresampled')   &&
      function_exists($func)
    );
  if ( $can_use_magick )
  {
    if ( !preg_match('/^([\/A-z0-9_-]+)$/', $magick_path) )
    {
      die('SECURITY: ImageMagick path is screwy');
    }
    $cmdline = "$magick_path \"$in_file\" -resize \"{$width}x{$height}>\" \"$out_file\"";
    system($cmdline, $return);
    if ( !file_exists($out_file) )
      return false;
    return true;
  }
  else if ( $can_use_gd )
  {
    @list($width_orig, $height_orig) = @getimagesize($in_file);
    if ( !$width_orig || !$height_orig )
      return false;
    // calculate new width and height
    
    $ratio = $width_orig / $height_orig;
    if ( $ratio > 1 )
    {
      // orig. width is greater that height
      $new_width = $width;
      $new_height = round( $width / $ratio );
    }
    else if ( $ratio < 1 )
    {
      // orig. height is greater than width
      $new_width = round( $height / $ratio );
      $new_height = $height;
    }
    else if ( $ratio == 1 )
    {
      $new_width = $width;
      $new_height = $width;
    }
    if ( $new_width > $width_orig || $new_height > $height_orig )
    {
      // Too big for our britches here; set it to only convert the file
      $new_width = $width_orig;
      $new_height = $height_orig;
    }
    
    $newimage = @imagecreatetruecolor($new_width, $new_height);
    if ( !$newimage )
      return false;
    $oldimage = @$func($in_file);
    if ( !$oldimage )
      return false;
    
    // Perform scaling
    imagecopyresampled($newimage, $oldimage, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);
    
    // Get output format
    $out_ext = substr($out_file, ( strrpos($out_file, '.') + 1));
    switch($out_ext)
    {
      case 'png':
        $outfunc = 'imagepng';
        break;
      case 'jpg':
      case 'jpeg':
        $outfunc = 'imagejpeg';
        break;
      case 'gif':
        $outfunc = 'imagegif';
        break;
      case 'xpm':
        $outfunc = 'imagexpm';
        break;
      default:
        imagedestroy($newimage);
        imagedestroy($oldimage);
        return false;
    }
    
    // Write output
    $outfunc($newimage, $out_file);
    
    // clean up
    imagedestroy($newimage);
    imagedestroy($oldimage);
    
    // done!
    return true;
  }
  // Neither scaling method worked; we'll let plugins try to scale it, and then if the file still doesn't exist, die
  $code = $plugins->setHook('scale_image_failure');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
  if ( file_exists($out_file) )
    return true;
  return false;
}

/**
 * Determines whether a GIF file is animated or not. Credit goes to ZeBadger from <http://www.php.net/imagecreatefromgif>.
 * Modified to conform to Enano coding standards.
 * @param string Path to GIF file
 * @return bool If animated, returns true
 */

 
function is_gif_animated($filename)
{
  $filecontents = @file_get_contents($filename);
  if ( empty($filecontents) )
    return false;

  $str_loc = 0;
  $count = 0;
  while ( $count < 2 ) // There is no point in continuing after we find a 2nd frame
  {
    $where1 = strpos($filecontents,"\x00\x21\xF9\x04", $str_loc);
    if ( $where1 === false )
    {
      break;
    }
    else
    {
      $str_loc = $where1 + 1;
      $where2 = strpos($filecontents,"\x00\x2C", $str_loc);
      if ( $where2 === false )
      {
        break;
      }
      else
      {
        if ( $where1 + 8 == $where2 )
        {
          $count++;
        }
        $str_loc = $where2 + 1;
      }
    }
  }
  
  return ( $count > 1 ) ? true : false;
}

/**
 * Retrieves the dimensions of a GIF image.
 * @param string The path to the GIF file.
 * @return array Key 0 is width, key 1 is height
 */

function gif_get_dimensions($filename)
{
  $filecontents = @file_get_contents($filename);
  if ( empty($filecontents) )
    return false;
  if ( strlen($filecontents) < 10 )
    return false;
  
  $width = substr($filecontents, 6, 2);
  $height = substr($filecontents, 8, 2);
  $width = unpack('v', $width);
  $height = unpack('v', $height);
  return array($width[1], $height[1]);
}

/**
 * Determines whether a PNG image is animated or not. Based on some specification information from <http://wiki.mozilla.org/APNG_Specification>.
 * @param string Path to PNG file.
 * @return bool If animated, returns true
 */

function is_png_animated($filename)
{
  $filecontents = @file_get_contents($filename);
  if ( empty($filecontents) )
    return false;
  
  $parsed = parse_png($filecontents);
  if ( !$parsed )
    return false;
  
  if ( !isset($parsed['fdAT']) )
    return false;
  
  if ( count($parsed['fdAT']) > 1 )
    return true;
}

/**
 * Gets the dimensions of a PNG image.
 * @param string Path to PNG file
 * @return array Key 0 is width, key 1 is length.
 */

function png_get_dimensions($filename)
{
  $filecontents = @file_get_contents($filename);
  if ( empty($filecontents) )
    return false;
  
  $parsed = parse_png($filecontents);
  if ( !$parsed )
    return false;
  
  $ihdr_stream = $parsed['IHDR'][0];
  $width = substr($ihdr_stream, 0, 4);
  $height = substr($ihdr_stream, 4, 4);
  $width = unpack('N', $width);
  $height = unpack('N', $height);
  $x = $width[1];
  $y = $height[1];
  return array($x, $y);
}

/**
 * Internal function to parse out the streams of a PNG file. Based on the W3 PNG spec: http://www.w3.org/TR/PNG/
 * @param string The contents of the PNG
 * @return array Associative array containing the streams
 */

function parse_png($data)
{
  // Trim off first 8 bytes to check for PNG header
  $header = substr($data, 0, 8);
  if ( $header != "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a" )
  {
    return false;
  }
  $return = array();
  $data = substr($data, 8);
  while ( strlen($data) > 0 )
  {
    $chunklen_bin = substr($data, 0, 4);
    $chunk_type = substr($data, 4, 4);
    $chunklen = unpack('N', $chunklen_bin);
    $chunklen = $chunklen[1];
    $chunk_data = substr($data, 8, $chunklen);
    
    // If the chunk type is not valid, this may be a malicious PNG with bad offsets. Break out of the loop.
    if ( !preg_match('/^[A-z]{4}$/', $chunk_type) )
      break;
    
    if ( !isset($return[$chunk_type]) )
      $return[$chunk_type] = array();
    $return[$chunk_type][] = $chunk_data;
    
    $offset_next = 4 // Length
                 + 4 // Type
                 + $chunklen // Data
                 + 4; // CRC
    $data = substr($data, $offset_next);
  }
  return $return;
}

/**
 * Retreives information about the intrinsic characteristics of the jpeg image, such as Bits per Component, Height and Width. This function is from the PHP JPEG Metadata Toolkit. Licensed under the GPLv2 or later.
 * @param array The JPEG header data, as retrieved from the get_jpeg_header_data function
 * @return array An array containing the intrinsic JPEG values FALSE - if the comment segment couldnt be found
 * @license GNU General Public License
 * @copyright Copyright Evan Hunter 2004 
 */

function get_jpeg_intrinsic_values( $jpeg_header_data )
{
  // Create a blank array for the output
  $Outputarray = array( );

  //Cycle through the header segments until Start Of Frame (SOF) is found or we run out of segments
  $i = 0;
  while ( ( $i < count( $jpeg_header_data) )  && ( substr( $jpeg_header_data[$i]['SegName'], 0, 3 ) != "SOF" ) )
  {
    $i++;
  }

  // Check if a SOF segment has been found
  if ( substr( $jpeg_header_data[$i]['SegName'], 0, 3 ) == "SOF" )
  {
    // SOF segment was found, extract the information

    $data = $jpeg_header_data[$i]['SegData'];

    // First byte is Bits per component
    $Outputarray['Bits per Component'] = ord( $data{0} );

    // Second and third bytes are Image Height
    $Outputarray['Image Height'] = ord( $data{ 1 } ) * 256 + ord( $data{ 2 } );

    // Forth and fifth bytes are Image Width
    $Outputarray['Image Width'] = ord( $data{ 3 } ) * 256 + ord( $data{ 4 } );

    // Sixth byte is number of components
    $numcomponents = ord( $data{ 5 } );

    // Following this is a table containing information about the components
    for( $i = 0; $i < $numcomponents; $i++ )
    {
      $Outputarray['Components'][] = array (  'Component Identifier' => ord( $data{ 6 + $i * 3 } ),
                'Horizontal Sampling Factor' => ( ord( $data{ 7 + $i * 3 } ) & 0xF0 ) / 16,
                'Vertical Sampling Factor' => ( ord( $data{ 7 + $i * 3 } ) & 0x0F ),
                'Quantization table destination selector' => ord( $data{ 8 + $i * 3 } ) );
    }
  }
  else
  {
    // Couldn't find Start Of Frame segment, hence can't retrieve info
    return FALSE;
  }

  return $Outputarray;
}

/**
 * Reads all the JPEG header segments from an JPEG image file into an array. This function is from the PHP JPEG Metadata Toolkit. Licensed under the GPLv2 or later. Modified slightly for Enano coding standards and to remove unneeded capability.
 * @param string the filename of the file to JPEG file to read
 * @return string Array of JPEG header segments, or FALSE - if headers could not be read
 * @license GNU General Public License
 * @copyright Copyright Evan Hunter 2004
 */

function get_jpeg_header_data( $filename )
{
  // Attempt to open the jpeg file - the at symbol supresses the error message about
  // not being able to open files. The file_exists would have been used, but it
  // does not work with files fetched over http or ftp.
  $filehnd = @fopen($filename, 'rb');

  // Check if the file opened successfully
  if ( ! $filehnd  )
  {
    // Could't open the file - exit
    return FALSE;
  }


  // Read the first two characters
  $data = fread( $filehnd, 2 );

  // Check that the first two characters are 0xFF 0xDA  (SOI - Start of image)
  if ( $data != "\xFF\xD8" )
  {
    // No SOI (FF D8) at start of file - This probably isn't a JPEG file - close file and return;
    fclose($filehnd);
    return FALSE;
  }


  // Read the third character
  $data = fread( $filehnd, 2 );

  // Check that the third character is 0xFF (Start of first segment header)
  if ( $data{0} != "\xFF" )
  {
    // NO FF found - close file and return - JPEG is probably corrupted
    fclose($filehnd);
    return FALSE;
  }

  // Flag that we havent yet hit the compressed image data
  $hit_compressed_image_data = FALSE;


  // Cycle through the file until, one of: 1) an EOI (End of image) marker is hit,
  //               2) we have hit the compressed image data (no more headers are allowed after data)
  //               3) or end of file is hit

  while ( ( $data{1} != "\xD9" ) && (! $hit_compressed_image_data) && ( ! feof( $filehnd ) ))
  {
    // Found a segment to look at.
    // Check that the segment marker is not a Restart marker - restart markers don't have size or data after them
    if (  ( ord($data{1}) < 0xD0 ) || ( ord($data{1}) > 0xD7 ) )
    {
      // Segment isn't a Restart marker
      // Read the next two bytes (size)
      $sizestr = fread( $filehnd, 2 );

      // convert the size bytes to an integer
      $decodedsize = unpack ("nsize", $sizestr);

      // Save the start position of the data
      $segdatastart = ftell( $filehnd );

      // Read the segment data with length indicated by the previously read size
      $segdata = fread( $filehnd, $decodedsize['size'] - 2 );


      // Store the segment information in the output array
      $headerdata[] = array(  "SegType" => ord($data{1}),
            "SegName" => $GLOBALS[ "JPEG_Segment_Names" ][ ord($data{1}) ],
            "SegDataStart" => $segdatastart,
            "SegData" => $segdata );
    }

    // If this is a SOS (Start Of Scan) segment, then there is no more header data - the compressed image data follows
    if ( $data{1} == "\xDA" )
    {
      // Flag that we have hit the compressed image data - exit loop as no more headers available.
      $hit_compressed_image_data = TRUE;
    }
    else
    {
      // Not an SOS - Read the next two bytes - should be the segment marker for the next segment
      $data = fread( $filehnd, 2 );

      // Check that the first byte of the two is 0xFF as it should be for a marker
      if ( $data{0} != "\xFF" )
      {
        // NO FF found - close file and return - JPEG is probably corrupted
        fclose($filehnd);
        return FALSE;
      }
    }
  }

  // Close File
  fclose($filehnd);

  // Return the header data retrieved
  return $headerdata;
}

/**
 * Returns the dimensions of a JPEG image in the same format as {php,gif}_get_dimensions().
 * @param string JPEG file to check
 * @return array Key 0 is width, key 1 is height
 */

function jpg_get_dimensions($filename)
{
  if ( !file_exists($filename) )
  {
    echo "Doesn't exist<br />";
    return false;
  }
  
  $headers = get_jpeg_header_data($filename);
  if ( !$headers )
  {
    echo "Bad headers<br />";
    return false;
  }
  
  $metadata = get_jpeg_intrinsic_values($headers);
  if ( !$metadata )
  {
    echo "Bad metadata: <pre>" . print_r($metadata, true) . "</pre><br />";
    return false;
  }
  
  if ( !isset($metadata['Image Width']) || !isset($metadata['Image Height']) )
  {
    echo "No metadata<br />";
    return false;
  }
  
  return array($metadata['Image Width'], $metadata['Image Height']);
}

/**
 * Generates a URL for the avatar for the given user ID and avatar type.
 * @param int User ID
 * @param string Image type - must be one of jpg, png, or gif.
 * @param string User's e-mail address, makes Special:Avatar redirect if not specified
 * @return string
 */

function make_avatar_url($user_id, $avi_type, $user_email = false)
{
  static $img_types = array(
      'png' => IMAGE_TYPE_PNG,
      'gif' => IMAGE_TYPE_GIF,
      'jpg' => IMAGE_TYPE_JPG,
      'grv' => IMAGE_TYPE_GRV
    );
  
  if ( !is_int($user_id) )
    return false;
  if ( !isset($img_types[$avi_type]) )
    return false;
  
  if ( $avi_type == 'grv' )
  {
    if ( $user_email )
    {
      return make_gravatar_url($user_email);
    }
  }
  else
  {
    $avi_relative_path = '/' . getConfig('avatar_directory') . '/' . $user_id . '.' . $avi_type;
    if ( !file_exists(ENANO_ROOT . $avi_relative_path) )
    {
      return '';
    }
  }
  
  $img_type = $img_types[$avi_type];
  
  $dateline = @filemtime(ENANO_ROOT . $avi_relative_path);
  $avi_id = pack('VVv', $dateline, $user_id, $img_type);
  $avi_id = hexencode($avi_id, '', '');
    
  // return scriptPath . $avi_relative_path;
  return makeUrlNS('Special', "Avatar/$avi_id");
}

/**
 * Generates a URL to the Gravatar for a user based on his/her e-mail address.
 * @param string E-mail address
 * @param int Size - defaults to site-wide limits
 * @return string URL
 */

function make_gravatar_url($email, $size = false)
{
  $email = md5($email);
  
  // gravatar parameters
  if ( $size )
  {
    $max_size = intval($size);
  }
  else
  {
    $max_x = intval(getConfig('avatar_max_width', '150'));
    $max_y = intval(getConfig('avatar_max_height', '150'));
    // ?s=
    $max_size = ( $max_x > $max_y ) ? $max_y : $max_x;
  }
  
  // ?r=
  $rating = getConfig('gravatar_rating', 'g');
  
  // final URL
  $url = "http://www.gravatar.com/avatar/$email?r=$rating&s=$max_size";
  
  return $url;
}

/**
 * Determines an image's filetype based on its signature.
 * @param string Path to image file
 * @return string One of gif, png, or jpg, or false if none of these.
 */

function get_image_filetype($filename)
{
  $filecontents = @file_get_contents($filename);
  if ( empty($filecontents) )
    return false;
  
  if ( substr($filecontents, 0, 8) == "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a" )
    return 'png';
  
  if ( substr($filecontents, 0, 6) == 'GIF87a' || substr($filecontents, 0, 6) == 'GIF89a' )
    return 'gif';
  
  if ( substr($filecontents, 0, 2) == "\xFF\xD8" )
    return 'jpg';
  
  return false;
}

/**
 * Generates a JSON encoder/decoder singleton.
 * @return object
 */

function enano_json_singleton()
{
  static $json_obj;
  if ( !is_object($json_obj) )
    $json_obj = new Services_JSON(SERVICES_JSON_LOOSE_TYPE | SERVICES_JSON_SUPPRESS_ERRORS);
  
  return $json_obj;
}

/**
 * Wrapper for JSON encoding.
 * @param mixed Variable to encode
 * @return string JSON-encoded string
 */

function enano_json_encode($data)
{
  /*
  if ( function_exists('json_encode') )
  {
    // using PHP5 with JSON support
    return json_encode($data);
  }
  */
  
  return Zend_Json::encode($data, true);
}

/**
 * Wrapper for JSON decoding.
 * @param string JSON-encoded string
 * @return mixed Decoded value
 */

function enano_json_decode($data)
{
  /*
  if ( function_exists('json_decode') )
  {
    // using PHP5 with JSON support
    return json_decode($data);
  }
  */
  
  return Zend_Json::decode($data, Zend_Json::TYPE_ARRAY);
}

/**
 * Cleans a snippet of JSON for closer standards compliance (shuts up the picky Zend parser)
 * @param string Dirty JSON
 * @return string Clean JSON
 */

function enano_clean_json($json)
{
  // eliminate comments
  $json = preg_replace(array(
          // eliminate single line comments in '// ...' form
          '#^\s*//(.+)$#m',
          // eliminate multi-line comments in '/* ... */' form, at start of string
          '#^\s*/\*(.+)\*/#Us',
          // eliminate multi-line comments in '/* ... */' form, at end of string
          '#/\*(.+)\*/\s*$#Us'
        ), '', $json);
    
  $json = preg_replace('/([,\{\[])([\s]*?)([a-z0-9_]+)([\s]*?):/', '\\1\\2"\\3" :', $json);
  
  return $json;
}

/**
 * Trims a snippet of text to the first and last curly braces. Useful for JSON.
 * @param string Text to trim
 * @return string
 */

function enano_trim_json($json)
{
  return preg_replace('/^([^{]+)\{/', '{', preg_replace('/\}([^}]+)$/', '}', $json));
}

/**
 * Starts the profiler.
 */

function profiler_start()
{
  global $_profiler;
  $_profiler = array();
  
  if ( !defined('ENANO_DEBUG') )
    return false;
  
  $_profiler[] = array(
      'point' => 'Profiling started',
      'time' => microtime_float(),
      'backtrace' => false,
      'mem' => false
    );
  if ( function_exists('memory_get_usage') )
  {
    $_profiler[ count($_profiler) - 1 ]['mem'] = memory_get_usage();
  }
}

/**
 * Logs something in the profiler.
 * @param string Point name or message
 * @param bool Optional. If true (default), a backtrace will be generated and added to the profiler data. False disables this, often for security reasons.
 */

function profiler_log($point, $allow_backtrace = true)
{
  if ( !defined('ENANO_DEBUG') )
    return false;
  
  global $_profiler;
  $backtrace = false;
  if ( $allow_backtrace && function_exists('debug_print_backtrace') )
  {
    list(, $backtrace) = explode("\n", enano_debug_print_backtrace(true));
  }
  $_profiler[] = array(
      'point' => $point,
      'time' => microtime_float(),
      'backtrace' => $backtrace,
      'mem' => false
    );
  if ( function_exists('memory_get_usage') )
  {
    $_profiler[ count($_profiler) - 1 ]['mem'] = memory_get_usage();
  }
  return true;
}

/**
 * Returns the profiler's data (so far).
 * @return array
 */

function profiler_dump()
{
  return $GLOBALS['_profiler'];
}

/**
 * Generates an HTML version of the performance profile. Not localized because only used as a debugging tool.
 * @return string
 */

function profiler_make_html()
{
  if ( !defined('ENANO_DEBUG') )
    return '';
    
  $profile = profiler_dump();
  
  $html = '<div class="tblholder">';
  $html .= '<table border="0" cellspacing="1" cellpadding="4">';
  
  $time_start = $time_last = $profile[0]['time'];
  
  foreach ( $profile as $i => $entry )
  {
    // $time_since_last = $entry['time'] - $time_last;
    // if ( $time_since_last < 0.01 )
    //   continue;
    
    $html .= "<!-- ########################################################## -->\n<tr>\n  <th colspan=\"2\">Event $i</th>\n</tr>";
    
    $html .= '<tr>' . "\n";
    $html .= '  <td class="row2">Event:</td>' . "\n";
    $html .= '  <td class="row1">' . htmlspecialchars($entry['point']) . '</td>' . "\n";
    $html .= '</tr>' . "\n";
    
    $time = $entry['time'] - $time_start;
    
    $html .= '<tr>' . "\n";
    $html .= '  <td class="row2">Time since start:</td>' . "\n";
    $html .= '  <td class="row1">' . $time . 's</td>' . "\n";
    $html .= '</tr>' . "\n";
    
    $time = $entry['time'] - $time_last;
    if ( $time < 0.0001 )
      $time = 'Marginal';
    else
      $time = "{$time}s";
    
    $html .= '<tr>' . "\n";
    $html .= '  <td class="row2">Time since last event:</td>' . "\n";
    $html .= '  <td class="row1">' . $time . '</td>' . "\n";
    $html .= '</tr>' . "\n";
    
    if ( $entry['backtrace'] )
    {
      $html .= '<tr>' . "\n";
      $html .= '  <td class="row2">Called from:</td>' . "\n";
      $html .= '  <td class="row1">' . htmlspecialchars($entry['backtrace']) . '</td>' . "\n";
      $html .= '</tr>' . "\n";
    }
    
    if ( $entry['mem'] )
    {
      $html .= '<tr>' . "\n";
      $html .= '  <td class="row2">Total mem usage:</td>' . "\n";
      $html .= '  <td class="row1">' . htmlspecialchars($entry['mem']) . ' (bytes)</td>' . "\n";
      $html .= '</tr>' . "\n";
    }
    
    $html .= "\n";
    
    $time_last = $entry['time'];
  }
  $html .= '</table></div>';
  
  return $html;
}

// Might as well start the profiler, it has no external dependencies except from this file.
profiler_start();

/**
 * Returns the number of times a character occurs in a given string.
 * @param string Haystack
 * @param string Needle
 * @return int
 */

function get_char_count($string, $char)
{
  $char = substr($char, 0, 1);
  $count = 0;
  for ( $i = 0; $i < strlen($string); $i++ )
  {
    if ( $string{$i} == $char )
      $count++;
  }
  return $count;
}

/**
 * Returns the number of lines in a string.
 * @param string String to check
 * @return int
 */

function get_line_count($string)
{
  return ( get_char_count($string, "\n") ) + 1;
}

if ( !function_exists('sys_get_temp_dir') )
{
    // Based on http://www.phpit.net/
    // article/creating-zip-tar-archives-dynamically-php/2/
    /**
     * Attempt to get the system's temp directory.
     * @return string or bool false on failure
     */
    
    function sys_get_temp_dir()
    {
        // Try to get from environment variable
        if ( !empty($_ENV['TMP']) )
        {
            return realpath( $_ENV['TMP'] );
        }
        else if ( !empty($_ENV['TMPDIR']) )
        {
            return realpath( $_ENV['TMPDIR'] );
        }
        else if ( !empty($_ENV['TEMP']) )
        {
            return realpath( $_ENV['TEMP'] );
        }

        // Detect by creating a temporary file
        else
        {
            // Try to use system's temporary directory
            // as random name shouldn't exist
            $temp_file = tempnam( md5(uniqid(rand(), TRUE)), '' );
            if ( $temp_file )
            {
                $temp_dir = realpath( dirname($temp_file) );
                unlink( $temp_file );
                return $temp_dir;
            }
            else
            {
                return FALSE;
            }
        }
    }
}

/**
 * Grabs and processes all rank information directly from the database.
 */

function fetch_rank_data()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $sql = $session->generate_rank_sql();
  $q = $db->sql_query($sql);
  if ( !$q )
    $db->_die();
  
  $GLOBALS['user_ranks'] = array();
  global $user_ranks;
  
  while ( $row = $db->fetchrow($q) )
  {
    $user_id = $row['user_id'];
    $username = $row['username'];
    $row = $session->calculate_user_rank($row);
    $user_ranks[$username] =  $row;
    $user_ranks[$user_id]  =& $user_ranks[$username];
  }
}

/**
 * Caches the computed user rank information.
 */

function generate_cache_userranks()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $user_ranks;
  
  fetch_rank_data();
  
  $user_ranks_stripped = array();
  foreach ( $user_ranks as $key => $value )
  {
    if ( is_int($key) )
      $user_ranks_stripped[$key] = $value;
  }
  
  $ranks_exported = "<?php\n\n// Automatically generated user rank cache.\nglobal \$user_ranks;\n" . '$user_ranks = ' . $lang->var_export_string($user_ranks_stripped) . ';';
  $uid_map = array();
  foreach ( $user_ranks as $id => $row )
  {
    if ( !is_int($id) )
    {
      $username = $id;
      continue;
    }
    
    $un_san = addslashes($username);
    $ranks_exported .= "\n\$user_ranks['$un_san'] =& \$user_ranks[{$row['user_id']}];";
  }
  $ranks_exported .= "\n\ndefine('ENANO_RANKS_CACHE_LOADED', 1); \n?>";
  
  // open ranks cache file
  $fh = @fopen( ENANO_ROOT . '/cache/cache_ranks.php', 'w' );
  if ( !$fh )
    return false;
  fwrite($fh, $ranks_exported);
  fclose($fh);
  
  return true;
}

/**
 * Loads the rank data, first attempting the cache file and then the database.
 */

function load_rank_data()
{
  if ( file_exists( ENANO_ROOT . '/cache/cache_ranks.php' ) )
  {
    @include(ENANO_ROOT . '/cache/cache_ranks.php');
  }
  if ( !defined('ENANO_RANKS_CACHE_LOADED') )
  {
    fetch_rank_data();
  }
}

/**
 * Function to purge all caches used in Enano. Useful for upgrades, maintenance, backing the site up, etc.
 */

function purge_all_caches()
{
  global $cache;
  if ( $dh = opendir(ENANO_ROOT . '/cache') )
  {
    $cache->purge('page_meta');
    $cache->purge('anon_sidebar');
    $cache->purge('plugins');
    
    $data_files = array(
        'aes_decrypt.php',
        // ranks cache is stored using a custom engine (not enano's default cache)
        'cache_ranks.php'
      );
    while ( $file = @readdir($dh) )
    {
      $fullpath = ENANO_ROOT . "/cache/$file";
      // we don't want to mess with directories
      if ( !is_file($fullpath) )
        continue;
      
      // data files
      if ( in_array($file, $data_files) )
        unlink($fullpath);
      // template files
      else if ( preg_match('/\.(?:tpl|css)\.php$/', $file) )
        unlink($fullpath);
      // compressed javascript
      else if ( preg_match('/^jsres_(?:[A-z0-9_-]+)\.js\.json$/', $file) )
        unlink($fullpath);
      // tinymce stuff
      else if ( preg_match('/^tiny_mce_(?:[a-f0-9]+)\.gz$/', $file) )
        unlink($fullpath);
      // language files
      else if ( preg_match('/^lang_json_(?:[a-f0-9]+?)\.php$/', $file) || preg_match('/^(?:cache_)?lang_(?:[0-9]+?)\.php$/', $file) )
        unlink($fullpath);
    }
    return true;
  }
  return false;
}

//die('<pre>Original:  01010101010100101010100101010101011010'."\nProcessed: ".uncompress_bitfield(compress_bitfield('01010101010100101010100101010101011010')).'</pre>');

?>
