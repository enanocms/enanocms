<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
function db_error_handler($errno, $errstr, $errfile = false, $errline = false, $errcontext = Array() )
{
  if ( !defined('ENANO_DEBUG') )
    return;
  $e = error_reporting(0);
  error_reporting($e);
  if ( $e < $errno )
    return;
  $errtype = 'Notice';
  switch ( $errno )
  {
    case E_ERROR: case E_USER_ERROR: case E_CORE_ERROR: case E_COMPILE_ERROR: $errtype = 'Error'; break;
    case E_WARNING: case E_USER_WARNING: case E_CORE_WARNING: case E_COMPILE_WARNING: $errtype = 'Warning'; break;
  }
  $debug = debug_backtrace();
  if ( !isset($debug[0]['file']) )
    return false;
  $debug = $debug[0]['file'] . ', line ' . $debug[0]['line'];
  echo "<b>$errtype:</b> $errstr<br />Error source:<pre>$debug</pre>";
}

global $db_sql_parse_time;
$db_sql_parse_time = 0;

class mysql {
  var $num_queries, $query_backtrace, $query_times, $query_sources, $latest_result, $latest_query, $_conn, $sql_stack_fields, $sql_stack_values, $debug;
  var $row = array();
	var $rowset = array();
  var $errhandler;
  var $dbms_name = 'MySQL';
  
  /**
   * Get a flat textual list of queries that have been made.
   */
  
  function sql_backtrace()
  {
    return implode("\n-------------------------------------------------------------------\n", $this->query_backtrace);
  }
  
  /**
   * Connect to the database, but only if a connection isn't already up.
   */
  
  function ensure_connection()
  {
    if(!$this->_conn)
    {
      $this->connect();
    }
  }
  
  /**
   * Exit Enano, dumping out a friendly error message indicating a database error on the way out.
   * @param string Description or location of error; defaults to none
   */
 
  function _die($t = '')
  {
    if ( defined('ENANO_HEADERS_SENT') )
      ob_clean();
    
    $internal_text = $this->get_error($t);
    
    if ( defined('ENANO_CONFIG_FETCHED') )
      // config is in, we can show a slightly nicer looking error page
      die_semicritical('Database error', $internal_text);
    else
      // no config, display using no-DB template engine
      grinding_halt('Database error', $internal_text);
    
    exit;
  }
  
  /**
   * Get the internal text used for a database error message.
   * @param string Description or location of error; defaults to none
   */
  
  function get_error($t = '')
  {
    @header('HTTP/1.1 500 Internal Server Error');
    
    $bt = $this->latest_query;
    $e = htmlspecialchars($this->sql_error());
    if ( empty($e) )
      $e = '&lt;none&gt;';
    
    global $email;
    
    // As long as the admin's e-mail is accessible, display it.
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) )
                    ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;'
                    : '';
    
    $internal_text = "<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site{$email_info}.</p>
                      <p>Description or location of error: $t<br />
                      Error returned by $this->dbms_name extension: $e</p>
                      <p>Most recent SQL query:</p>
                      <pre>$bt</pre>";
    return $internal_text;
  }
  
  /**
   * Exit Enano and output a JSON format datbase error.
   * @param string Description or location of error; defaults to none
   */
  
  function die_json($loc = false)
  {
    $e = str_replace("\n", "\\n", addslashes(htmlspecialchars($this->sql_error())));
    $q = str_replace("\n", "\\n", addslashes($this->latest_query));
    $loc = ( $loc ) ? addslashes("\n\nDescription or location of error: $loc") : "";
    $loc .= "\n\nPlease report the full text of this error to the administrator of the site. If you believe that this is a bug with the software, please contact support@enanocms.org.";
    $loc = str_replace("\n", "\\n", $loc);
    $t = "{\"mode\":\"error\",\"error\":\"An error occurred during database query.\\nQuery was:\\n  $q\\n\\nError returned by {$this->dbms_name}: $e$loc\"}";
    die($t);
  }
  
  /**
   * Connect to the database.
   * @param bool If true, enables all other parameters. Defaults to false, which emans that you can call this function with no arguments and it will fetch information from the config file.
   * @param string Database server hostname
   * @param string Database server username
   * @param string Database server password
   * @param string Name of the database
   * @param int Optional port number to connect over
   */
  
  function connect($manual_credentials = false, $dbhost = false, $dbuser = false, $dbpasswd = false, $dbname = false, $dbport = false)
  {
    if ( !defined('ENANO_SQL_CONSTANTS') )
    {
      define('ENANO_SQL_CONSTANTS', '');
      define('ENANO_DBLAYER', 'MYSQL');
      define('ENANO_SQLFUNC_LOWERCASE', 'lcase');
      define('ENANO_SQL_MULTISTRING_PRFIX', '');
      define('ENANO_SQL_BOOLEAN_TRUE', 'true');
      define('ENANO_SQL_BOOLEAN_FALSE', 'false');
    }
    
    if ( !$manual_credentials )
    {
      if ( defined('IN_ENANO_INSTALL') && !defined('IN_ENANO_UPGRADE') && !defined('ENANO_INSTALLED') )
      {
        @include(ENANO_ROOT.'/config.new.php');
      }
      else
      {
        @include(ENANO_ROOT.'/config.php');
      }
      
      if ( isset($crypto_key) )
        unset($crypto_key); // Get this sucker out of memory fast
      if ( empty($dbport) )
        $dbport = 3306;
      
      if ( !defined('ENANO_INSTALLED') && !defined('MIDGET_INSTALLED') && !defined('IN_ENANO_INSTALL') )
      {
        // scriptPath isn't set yet - we need to autodetect it to avoid infinite redirects
        if ( !defined('scriptPath') )
        {
          if ( isset($_SERVER['PATH_INFO']) && !preg_match('/index\.php$/', $_SERVER['PATH_INFO']) )
          {
            $_SERVER['REQUEST_URI'] = preg_replace(';' . preg_quote($_SERVER['PATH_INFO']) . '$;', '', $_SERVER['REQUEST_URI']);
          }
          if ( !preg_match('/\.php$/', $_SERVER['REQUEST_URI']) )
          {
            // user requested http://foo/enano as opposed to http://foo/enano/index.php
            $_SERVER['REQUEST_URI'] .= '/index.php';
          }
          $sp = dirname($_SERVER['REQUEST_URI']);
          if($sp == '/' || $sp == '\\') $sp = '';
          define('scriptPath', $sp);
          define('contentPath', "$sp/index.php?title=");
        }
        $loc = scriptPath . '/install/index.php';
        define('IN_ENANO_INSTALL', 1);
        $GLOBALS['lang'] = new Language('eng');
        global $lang;
        $lang->load_file('./language/english/core.json');
        $lang->load_file('./language/english/install.json');
        // header("Location: $loc");
        redirect($loc, 'Enano not installed', 'We can\'t seem to find an Enano installation (valid config file). You will be transferred to the installation wizard momentarily...', 0);
        exit;
      }
    }
    
    if ( !$dbport )
      $dbport = 3306;
    
    if ( $dbhost && !empty($dbport) && $dbport != 3306 )
      $dbhost = '127.0.0.1';
    
    $host_line = ( preg_match('/^:/', $dbhost) ) ? $dbhost : "{$dbhost}:{$dbport}";
    
    $this->_conn = @mysql_connect($host_line, $dbuser, $dbpasswd);
    unset($dbuser);
    unset($dbpasswd); // Security
    
    if ( !$this->_conn && !$manual_credentials )
    {
      grinding_halt('Enano is having a problem', '<p>Error: couldn\'t connect to MySQL.<br />'.mysql_error().'</p>');
    }
    else if ( !$this->_conn && $manual_credentials )
    {
      return false;
    }
    
    // Reset some variables
    $this->query_backtrace = array();
    $this->query_times = array();
    $this->query_sources = array();
    $this->num_queries = 0;
    
    $this->debug = ( defined('ENANO_DEBUG') );
    
    $q = @mysql_select_db($dbname);
    
    if ( !$q )
    {
      if ( $manual_credentials )
        return false;
      $this->_die('The database could not be selected.');
    }
    
    // We're in!
    return true;
  }
  
  /**
   * Make a SQL query.
   * @param string Query
   * @param bool If false, skips all checks and logging stages. If you're doing a ton of queries, set this to true; in all other cases, leave at the default of false.
   * @return resource or false on failure
   */
  
  function sql_query($q, $log_query = true)
  {
    if ( $this->debug && function_exists('debug_backtrace') )
    {
      $backtrace = @debug_backtrace();
      if ( is_array($backtrace) )
      {
        $bt = $backtrace[0];
        if ( isset($backtrace[1]['class']) )
        {
          if ( $backtrace[1]['class'] == 'sessionManager' )
          {
            $bt = $backtrace[1];
          }
        }
        $this->query_sources[$q] = substr($bt['file'], strlen(ENANO_ROOT) + 1) . ', line ' . $bt['line'];
      }
      unset($backtrace);
    }
    
    $this->num_queries++;
    if ( $log_query || defined('ENANO_DEBUG') )
    {
      $this->query_backtrace[] = $q;
      $this->latest_query = $q;
    }
    // First make sure we have a connection
    if ( !$this->_conn )
    {
      $this->_die('A database connection has not yet been established.');
    }
    // Start the timer
    if ( $log_query || defined('ENANO_DEBUG') )
      $time_start = microtime_float();
    // Does this query look malicious?
    if ( $log_query || defined('ENANO_DEBUG') )
    {
      if ( !$this->check_query($q) )
      {
        $this->report_query($q);
        $debug = ( defined('ENANO_DEBUG') ) ? '<p>Query was:</p><pre>'.htmlspecialchars($q).'</pre>' : '';
        grinding_halt('SQL Injection attempt', '<p>Enano has caught and prevented an SQL injection attempt. Your IP address has been recorded and the administrator has been notified.</p>' . $debug);
      }
    }
    
    $r = mysql_query($q, $this->_conn);
    
    if ( $log_query )
      $this->query_times[$q] = microtime_float() - $time_start;
    
    $this->latest_result = $r;
    
    return $r;
  }
  
  /**
   * Make a SQL query, but do not have PHP buffer all the results. Useful for queries that are expected to return a huge number of results.
   * @param string Query
   * @param bool If false, skips all checks and logging stages. If you're doing a ton of queries, set this to true; in all other cases, leave at the default of false.
   * @return resource or false on failure
   */
  
  function sql_unbuffered_query($q, $log_query = true)
  {
    $this->num_queries++;
    if ( $log_query || defined('ENANO_DEBUG') )
      $this->query_backtrace[] = '(UNBUFFERED) ' . $q;
    $this->latest_query = $q;
    // First make sure we have a connection
    if ( !$this->_conn )
    {
      $this->_die('A database connection has not yet been established.');
    }
    // Does this query look malicious?
    if ( !$this->check_query($q) )
    {
      $this->report_query($q);
      $debug = ( defined('ENANO_DEBUG') ) ? '<p>Query was:</p><pre>'.htmlspecialchars($q).'</pre>' : '';
      grinding_halt('SQL Injection attempt', '<p>Enano has caught and prevented an SQL injection attempt. Your IP address has been recorded and the administrator has been notified.</p>' . $debug);
    }
    
    $time_start = microtime_float();
    $r = @mysql_unbuffered_query($q, $this->_conn);
    $this->query_times[$q] = microtime_float() - $time_start;
    $this->latest_result = $r;
    return $r;
  }
  
  /**
   * Performs heuristic analysis on a SQL query to check for known attack patterns.
   * @param string $q the query to check
   * @return bool true if query passed check, otherwise false
   */
  
  function check_query($q, $debug = false)
  {
    global $db_sql_parse_time;
    $ts = microtime_float();
    
    // remove properly escaped quotes
    $q = str_replace('\\\\', '', $q);
    $q = str_replace(array("\\\"", "\\'"), '', $q);
    
    // make sure quotes match
    foreach ( array("'", '"') as $quote )
    {
      $n_quotes = get_char_count($q, $quote);
      if ( $n_quotes % 2 == 1 )
      {
        // mismatched quotes
        if ( $debug ) echo "Found mismatched quotes in query; parsed:\n$q\n";
        return false;
      }
      // this quote is now confirmed to be matching; we can safely move all quoted strings out and replace with a token
      $q = preg_replace("/$quote(.*?)$quote/s", 'SAFE_QUOTE', $q);
    }
    $q = preg_replace("/(SAFE_QUOTE)+/", 'SAFE_QUOTE', $q);
    
    // quotes are now matched out. does this string have a comment marker in it?
    if ( strstr($q, '--') )
    {
      return false;
    }
    
    if ( preg_match('/[\s]+(SAFE_QUOTE|[\S]+)=\\1($|[\s]+)/', $q, $match) )
    {
      if ( $debug ) echo 'Found always-true test in query, injection attempt caught, match:<br />' . '<pre>' . print_r($match, true) . '</pre>';
      return false;
    }
    
    $ts = microtime_float() - $ts;
    $db_sql_parse_time += $ts;
    return true;
  }
  
  /**
   * Set the internal result pointer to X
   * @param int $pos The number of the row
   * @param resource $result The MySQL result resource - if not given, the latest cached query is assumed
   * @return true on success, false on failure
   */
   
  function sql_data_seek($pos, $result = false)
  {
    if ( !$result )
      $result = $this->latest_result;
    if ( !$result )
      return false;
    
    return mysql_data_seek($result, $pos) ? true : false;
  }
  
  /**
   * Reports a bad query to the admin
   * @param string $query the naughty query
   * @access private
   */
   
  function report_query($query)
  {
    global $session;
    if ( is_object($session) && defined('ENANO_MAINSTREAM') )
    {
      $username = $session->username;
      $user_id = $session->user_id;
    }
    else
    {
      $username = 'Unavailable';
      $user_id = 1;
    } 
    
    $query = $this->escape($query);
    $q = $this->sql_query('INSERT INTO '.table_prefix.'logs(log_type,     action,         time_id,    date_string, page_text,      author,            author_uid,       edit_summary)
                                                     VALUES(\'security\', \'sql_inject\', '.time().', \'\',        \''.$query.'\', \''.$username.'\', ' . $user_id . ', \''.$_SERVER['REMOTE_ADDR'].'\');');
  }
  
  /**
   * Returns the ID of the row last inserted.
   * @return int
   */
  
  function insert_id()
  {
    return @mysql_insert_id();
  }
  
  /**
   * Fetch one row from the given query as an associative array.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function fetchrow($r = false)
  {
    if ( !$this->_conn )
      return false;
    
    if ( !$r )
      $r = $this->latest_result;
    
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    
    $row = mysql_fetch_assoc($r);
    
    return integerize_array($row);
  }
  
  /**
   * Fetch one row from the given query as a numeric array.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function fetchrow_num($r = false)
  {
    if ( !$r )
      $r = $this->latest_result;
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    
    $row = mysql_fetch_row($r);
    return integerize_array($row);
  }
  
  /**
   * Get the number of results for a given query.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function numrows($r = false)
  {
    if ( !$r )
      $r = $this->latest_result;
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    
    return mysql_num_rows($r);
  }
  
  /**
   * Escape a string so that it may safely be included in a SQL query.
   * @param string String to escape
   * @return string Escaped string
   */
  
  function escape($str)
  {
    $str = mysql_real_escape_string($str);
    return $str;
  }
  
  /**
   * Free the given result from memory. Use this when completely finished with a result resource.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return null
   */
  
  function free_result($result = false)
  {
    if ( !$result )
      $result = $this->latest_result;
    if ( !$result )
      return null;
    
    @mysql_free_result($result);
    return null;
  }
  
  /**
   * Close the database connection
   */
  
  function close()
  {
    @mysql_close($this->_conn);
    unset($this->_conn);
  }
  
  /**
   * Get a list of columns in the given table
   * @param string Table
   * @return array
   */
  
  function columns_in($table)
  {
    if ( !is_string($table) )
      return false;
    $q = $this->sql_query("SHOW COLUMNS IN $table;");
    if ( !$q )
      $this->_die();
    
    $columns = array();
    while ( $row = $this->fetchrow_num() )
    {
      $columns[] = $row[0];
    }
    return $columns;
  }
  
  /**
   * Get the text of the most recent error.
   * @return string
   */
  
  function sql_error()
	{
    return mysql_error();
	}
  
  /**
   * Generates and outputs a report of all the SQL queries made during execution. Should only be called after everything's over with.
   */
  
  function sql_report()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !$session->get_permissions('mod_misc') )
    {
      die_friendly('Access denied', '<p>You are not authorized to generate a SQL backtrace.</p>');
    }
    // Create copies of variables that may be changed after header is called
    $backtrace = $this->query_backtrace;
    $times = $this->query_times;
    $template->header();
    echo '<h3>SQL query log and timetable</h3>';
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    $i = 0;
    foreach ( $backtrace as $query )
    {
      $i++;
      $unbuffered = false;
      if ( substr($query, 0, 13) == '(UNBUFFERED) ' )
      {
        $query = substr($query, 13);
        $unbuffered = true;
      }
      if ( $i == 1 )
      {
        echo '<tr>
                <th colspan="2">SQL backtrace for a normal page load of ' . htmlspecialchars($paths->cpage['urlname']) . '</th>
              </tr>';
      }
      else
      {
        echo '<tr>
                <th class="subhead" colspan="2">&nbsp;</th>
              </tr>';
      }
      echo '<tr>
              <td class="row2">Query:</td>
              <td class="row1"><pre>' . htmlspecialchars($query) . '</pre></td>
            </tr>
            <tr>
              <td class="row2">Time:</td>
              <td class="row1">' . number_format($this->query_times[$query], 6) . ' seconds</td>
            </tr>
            <tr>
              <td class="row2">Unbuffered:</td>
              <td class="row1">' . ( $unbuffered ? 'Yes' : 'No' ) . '</td>
            </tr>';
      if ( isset($this->query_sources[$query]) )
      {
        echo '<tr>
                <td class="row2">Called from:</td>
                <td class="row1">' . $this->query_sources[$query] . '</td>
              </tr>';
      }
    }
    if ( function_exists('array_sum') )
    {
      $query_time_total = array_sum($this->query_times);
      echo '<tr>
              <th class="subhead" colspan="2">
                Total time taken for SQL queries: ' . round( $query_time_total, 6 ) . ' seconds
              </th>
            </tr>';
    }
    echo '  </table>
          </div>';
    $template->footer();
  }
}

class postgresql
{
  var $num_queries, $query_backtrace, $query_times, $query_sources, $latest_result, $latest_query, $_conn, $sql_stack_fields, $sql_stack_values, $debug;
  var $row = array();
	var $rowset = array();
  var $errhandler;
  var $dbms_name = 'PostgreSQL';
  
  /**
   * Get a flat textual list of queries that have been made.
   */
  
  function sql_backtrace()
  {
    return implode("\n-------------------------------------------------------------------\n", $this->query_backtrace);
  }
  
  /**
   * Connect to the database, but only if a connection isn't already up.
   */
  
  function ensure_connection()
  {
    if(!$this->_conn)
    {
      $this->connect();
    }
  }
  
  /**
   * Exit Enano, dumping out a friendly error message indicating a database error on the way out.
   * @param string Description or location of error; defaults to none
   */
 
  function _die($t = '')
  {
    if ( defined('ENANO_HEADERS_SENT') )
      ob_clean();
    
    $internal_text = $this->get_error($t);
    
    if ( defined('ENANO_CONFIG_FETCHED') )
      // config is in, we can show a slightly nicer looking error page
      die_semicritical('Database error', $internal_text);
    else
      // no config, display using no-DB template engine
      grinding_halt('Database error', $internal_text);
    
    exit;
  }
  
  /**
   * Get the internal text used for a database error message.
   * @param string Description or location of error; defaults to none
   */
  
  function get_error($t = '')
  {
    @header('HTTP/1.1 500 Internal Server Error');
    
    $bt = $this->latest_query;
    $e = htmlspecialchars($this->sql_error());
    if ( empty($e) )
      $e = '&lt;none&gt;';
    
    global $email;
    
    // As long as the admin's e-mail is accessible, display it.
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) )
                    ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;'
                    : '';
    
    $internal_text = "<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site{$email_info}.</p>
                      <p>Description or location of error: $t<br />
                      Error returned by $this->dbms_name extension: $e</p>
                      <p>Most recent SQL query:</p>
                      <pre>$bt</pre>";
    return $internal_text;
  }
  
  /**
   * Exit Enano and output a JSON format datbase error.
   * @param string Description or location of error; defaults to none
   */
  
  function die_json($loc = false)
  {
    $e = str_replace("\n", "\\n", addslashes(htmlspecialchars($this->sql_error())));
    $q = str_replace("\n", "\\n", addslashes($this->latest_query));
    $loc = ( $loc ) ? addslashes("\n\nDescription or location of error: $loc") : "";
    $loc .= "\n\nPlease report the full text of this error to the administrator of the site. If you believe that this is a bug with the software, please contact support@enanocms.org.";
    $loc = str_replace("\n", "\\n", $loc);
    $t = "{\"mode\":\"error\",\"error\":\"An error occurred during database query.\\nQuery was:\\n  $q\\n\\nError returned by {$this->dbms_name}: $e$loc\"}";
    die($t);
  }
  
  /**
   * Connect to the database.
   * @param bool If true, enables all other parameters. Defaults to false, which emans that you can call this function with no arguments and it will fetch information from the config file.
   * @param string Database server hostname
   * @param string Database server username
   * @param string Database server password
   * @param string Name of the database
   * @param int Optional port number to connect over
   */
  
  function connect($manual_credentials = false, $dbhost = false, $dbuser = false, $dbpasswd = false, $dbname = false, $dbport = false)
  {
    if ( !defined('ENANO_SQL_CONSTANTS') )
    {
      define('ENANO_SQL_CONSTANTS', '');
      define('ENANO_DBLAYER', 'PGSQL');
      define('ENANO_SQLFUNC_LOWERCASE', 'lower');
      define('ENANO_SQL_MULTISTRING_PRFIX', 'E');
      define('ENANO_SQL_BOOLEAN_TRUE', '1');
      define('ENANO_SQL_BOOLEAN_FALSE', '0');
    }
    
    if ( !$manual_credentials )
    {
      if ( defined('IN_ENANO_INSTALL') && !defined('IN_ENANO_UPGRADE') )
      {
        @include(ENANO_ROOT.'/config.new.php');
      }
      else
      {
        @include(ENANO_ROOT.'/config.php');
      }
        
      if ( isset($crypto_key) )
        unset($crypto_key); // Get this sucker out of memory fast
      if ( empty($dbport) )
        $dbport = 5432;
      
      if ( !defined('ENANO_INSTALLED') && !defined('MIDGET_INSTALLED') && !defined('IN_ENANO_INSTALL') )
      {
        // scriptPath isn't set yet - we need to autodetect it to avoid infinite redirects
        if ( !defined('scriptPath') )
        {
          if ( isset($_SERVER['PATH_INFO']) && !preg_match('/index\.php$/', $_SERVER['PATH_INFO']) )
          {
            $_SERVER['REQUEST_URI'] = preg_replace(';' . preg_quote($_SERVER['PATH_INFO']) . '$;', '', $_SERVER['REQUEST_URI']);
          }
          if ( !preg_match('/\.php$/', $_SERVER['REQUEST_URI']) )
          {
            // user requested http://foo/enano as opposed to http://foo/enano/index.php
            $_SERVER['REQUEST_URI'] .= '/index.php';
          }
          $sp = dirname($_SERVER['REQUEST_URI']);
          if($sp == '/' || $sp == '\\') $sp = '';
          define('scriptPath', $sp);
          define('contentPath', "$sp/index.php?title=");
        }
        $loc = scriptPath . '/install.php';
        // header("Location: $loc");
        redirect($loc, 'Enano not installed', 'We can\'t seem to find an Enano installation (valid config file). You will be transferred to the installation wizard momentarily...', 3);
        exit;
      }
    }
    
    if ( empty($dbport) )
      $dbport = 5432;
    
    $this->_conn = @pg_connect("host=$dbhost port=$dbport dbname=$dbname user=$dbuser password=$dbpasswd");
    unset($dbuser);
    unset($dbpasswd); // Security
    
    if ( !$this->_conn && !$manual_credentials )
    {
      grinding_halt('Enano is having a problem', '<p>Error: couldn\'t connect to PostgreSQL.<br />'.pg_last_error().'</p>');
    }
    else if ( !$this->_conn && $manual_credentials )
    {
      return false;
    }
    
    // Reset some variables
    $this->query_backtrace = array();
    $this->query_times = array();
    $this->query_sources = array();
    $this->num_queries = 0;
    
    $this->debug = ( defined('ENANO_DEBUG') );
    
    // We're in!
    return true;
  }
  
  /**
   * Make a SQL query.
   * @param string Query
   * @param bool If false, skips all checks and logging stages. If you're doing a ton of queries, set this to true; in all other cases, leave at the default of false.
   * @return resource or false on failure
   */
  
  function sql_query($q)
  {
    if ( $this->debug && function_exists('debug_backtrace') )
    {
      $backtrace = @debug_backtrace();
      if ( is_array($backtrace) )
      {
        $bt = $backtrace[0];
        if ( isset($backtrace[1]['class']) )
        {
          if ( $backtrace[1]['class'] == 'sessionManager' )
          {
            $bt = $backtrace[1];
          }
        }
        $this->query_sources[$q] = substr($bt['file'], strlen(ENANO_ROOT) + 1) . ', line ' . $bt['line'];
      }
      unset($backtrace);
    }
    
    $this->num_queries++;
    $this->query_backtrace[] = $q;
    $this->latest_query = $q;
    // First make sure we have a connection
    if ( !$this->_conn )
    {
      $this->_die('A database connection has not yet been established.');
    }
    // Does this query look malicious?
    if ( !$this->check_query($q) )
    {
      $this->report_query($q);
      grinding_halt('SQL Injection attempt', '<p>Enano has caught and prevented an SQL injection attempt. Your IP address has been recorded and the administrator has been notified.</p><p>Query was:</p><pre>'.htmlspecialchars($q).'</pre>');
    }
    
    $time_start = microtime_float();
    $r = @pg_query($this->_conn, $q);
    $this->query_times[$q] = microtime_float() - $time_start;
    $this->latest_result = $r;
    return $r;
  }
  
  /**
   * Make a SQL query, but do not have PHP buffer all the results. Useful for queries that are expected to return a huge number of results.
   * @param string Query
   * @param bool If false, skips all checks and logging stages. If you're doing a ton of queries, set this to true; in all other cases, leave at the default of false.
   * @return resource or false on failure
   */
  
  function sql_unbuffered_query($q)
  {
    return $this->sql_query($q);
  }
  
  /**
   * Checks a SQL query for possible signs of injection attempts
   * @param string $q the query to check
   * @return bool true if query passed check, otherwise false
   */
  
  function check_query($q, $debug = false)
  {
    global $db_sql_parse_time;
    $ts = microtime_float();
    
    // remove properly escaped quotes
    $q = str_replace(array("\\\"", "\\'"), '', $q);
    
    // make sure quotes match
    foreach ( array("'", '"') as $quote )
    {
      if ( get_char_count($q, $quote) % 2 == 1 )
      {
        // mismatched quotes
        return false;
      }
      // this quote is now confirmed to be matching; we can safely move all quoted strings out and replace with a token
      $q = preg_replace("/$quote(.*?)$quote/s", 'SAFE_QUOTE', $q);
    }
    $q = preg_replace("/(SAFE_QUOTE)+/", 'SAFE_QUOTE', $q);
    
    // quotes are now matched out. does this string have a comment marker in it?
    if ( strstr($q, '--') )
    {
      return false;
    }
    
    if ( preg_match('/[\s]+(SAFE_QUOTE|[\S]+)=\\1($|[\s]+)/', $q, $match) )
    {
      if ( $debug ) echo 'Found always-true test in query, injection attempt caught, match:<br />' . '<pre>' . print_r($match, true) . '</pre>';
      return false;
    }
    
    $ts = microtime_float() - $ts;
    $db_sql_parse_time += $ts;
    return true;
  }
  
  /**
   * Set the internal result pointer to X
   * @param int $pos The number of the row
   * @param resource $result The PostgreSQL result resource - if not given, the latest cached query is assumed
   * @return true on success, false on failure
   */
   
  function sql_data_seek($pos, $result = false)
  {
    if ( !$result )
      $result = $this->latest_result;
    if ( !$result )
      return false;
    
    return pg_result_seek($result, $pos) ? true : false;
  }
  
  /**
   * Reports a bad query to the admin
   * @param string $query the naughty query
   * @access private
   */
   
  function report_query($query)
  {
    global $session;
    if ( is_object($session) && defined('ENANO_MAINSTREAM') )
    {
      $username = $session->username;
      $user_id = $session->user_id;
    }
    else
    {
      $username = 'Unavailable';
      $user_id = 1;
    } 
    
    $query = $this->escape($query);
    $q = $this->sql_query('INSERT INTO '.table_prefix.'logs(log_type,     action,         time_id,    date_string, page_text,      author,            author_uid,       edit_summary)
                                                     VALUES(\'security\', \'sql_inject\', '.time().', \'\',        \''.$query.'\', \''.$username.'\', ' . $user_id . ', \''.$_SERVER['REMOTE_ADDR'].'\');');
  }
  
  /**
   * Returns the ID of the row last inserted.
   * @return int
   */
  
  function insert_id()
  {
    // list of primary keys in Enano tables
    // this is a bit hackish, but not much choice.
    static $primary_keys = false;
    if ( !is_array($primary_keys) )
    {
      $primary_keys = array(
        table_prefix . 'comments' => 'comment_id',
        table_prefix . 'logs' => 'log_id',
        table_prefix . 'users' => 'user_id',
        table_prefix . 'banlist' => 'ban_id',
        table_prefix . 'files' => 'file_id',
        table_prefix . 'buddies' => 'buddy_id',
        table_prefix . 'privmsgs' => 'message_id',
        table_prefix . 'sidebar' => 'item_id',
        table_prefix . 'hits' => 'hit_id',
        table_prefix . 'groups' => 'group_id',
        table_prefix . 'group_members' => 'member_id',
        table_prefix . 'acl' => 'rule_id',
        table_prefix . 'page_groups' => 'pg_id',
        table_prefix . 'page_group_members' => 'pg_member_id',
        table_prefix . 'tags' => 'tag_id',
        table_prefix . 'lockout' => 'id',
        table_prefix . 'language' => 'lang_id',
        table_prefix . 'language_strings' => 'string_id',
        table_prefix . 'ranks' => 'rank_id',
        table_prefix . 'captcha' => 'code_id',
        table_prefix . 'diffiehellman' => 'key_id',
        table_prefix . 'plugins' => 'plugin_id'
      );
      // allow plugins to patch this if needed
      global $plugins;
      $code = $plugins->setHook('pgsql_set_serial_list');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
    }
    $last_was_insert = preg_match('/^INSERT INTO ([a-z0-9_]+)/i', $this->latest_query, $match);
    if ( $last_was_insert )
    {
      // trick based on PunBB's PostgreSQL driver
      $table =& $match[1];
      if ( isset($primary_keys[$table]) )
      {
        $primary_key = "{$table}_{$primary_keys[$table]}_seq";
        $q = pg_query("SELECT CURRVAL('$primary_key');");
        return ( $q ) ? intval(@pg_fetch_result($q, 0)) : false;
      }
    }
    return false;
  }
  
  /**
   * Fetch one row from the given query as an associative array.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function fetchrow($r = false)
  {
    if ( !$this->_conn )
      return false;
    if ( !$r )
      $r = $this->latest_result;
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid ' . $this->dbms_name . ' resource was passed.');
    
    $row = pg_fetch_assoc($r);
    return integerize_array($row);
  }
  
  /**
   * Fetch one row from the given query as a numeric array.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function fetchrow_num($r = false)
  {
    if ( !$r )
      $r = $this->latest_result;
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid ' . $this->dbms_name . ' resource was passed.');
    
    $row = pg_fetch_row($r);
    return integerize_array($row);
  }
  
  /**
   * Get the number of results for a given query.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return array
   */
  
  function numrows($r = false)
  {
    if ( !$r )
      $r = $this->latest_result;
    if ( !$r )
      $this->_die('$db->fetchrow(): an invalid ' . $this->dbms_name . ' resource was passed.');
    
    $n = pg_num_rows($r);
    return $n;
  }
  
  /**
   * Escape a string so that it may safely be included in a SQL query.
   * @param string String to escape
   * @return string Escaped string
   */
  
  function escape($str)
  {
    $str = pg_escape_string($this->_conn, $str);
    return $str;
  }
  
  /**
   * Free the given result from memory. Use this when completely finished with a result resource.
   * @param resource The resource returned from sql_query; if this isn't provided, the last result resource is used.
   * @return null
   */
  
  function free_result($result = false)
  {
    if ( !$result )
      $result = $this->latest_result;
    
    if ( !$result )
      return null;
    
    @pg_free_result($result);
    return null;
  }
  
  /**
   * Close the database connection
   */
  
  function close()
  {
    @pg_close($this->_conn);
    unset($this->_conn);
  }
  
  /**
   * Get a list of columns in the given table
   * @param string Table
   * @return array
   */
  
  function columns_in($table)
  {
    if ( !is_string($table) )
      return false;
    $q = $this->sql_query("SELECT * FROM $table LIMIT 1 OFFSET 0;");
    if ( !$q )
      $this->_die();
    if ( $this->numrows() < 1 )
    {
      // FIXME: Have another way to do this if the table is empty
      return false;
    }
    
    $row = $this->fetchrow();
    $this->free_result();
    return array_keys($row);
  }
  
	function sql_error()
	{
		if ( $this->_conn )
		{
			return pg_last_error();
		}
		else
		{
			return ( defined('IN_ENANO_INSTALL') ) ? $GLOBALS["lang"]->get('dbpgsql_msg_err_auth') : 'Access to the database was denied. Ensure that your database exists and that your username and password are correct.';
		}
	}

  /**
   * Generates and outputs a report of all the SQL queries made during execution. Should only be called after everything's over with.
   */
  
  function sql_report()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !$session->get_permissions('mod_misc') )
    {
      die_friendly('Access denied', '<p>You are not authorized to generate a SQL backtrace.</p>');
    }
    // Create copies of variables that may be changed after header is called
    $backtrace = $this->query_backtrace;
    $times = $this->query_times;
    $template->header();
    echo '<h3>SQL query log and timetable</h3>';
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    $i = 0;
    foreach ( $backtrace as $query )
    {
      $i++;
      $unbuffered = false;
      if ( substr($query, 0, 13) == '(UNBUFFERED) ' )
      {
        $query = substr($query, 13);
        $unbuffered = true;
      }
      if ( $i == 1 )
      {
        echo '<tr>
                <th colspan="2">SQL backtrace for a normal page load of ' . htmlspecialchars($paths->cpage['urlname']) . '</th>
              </tr>';
      }
      else
      {
        echo '<tr>
                <th class="subhead" colspan="2">&nbsp;</th>
              </tr>';
      }
      echo '<tr>
              <td class="row2">Query:</td>
              <td class="row1"><pre>' . htmlspecialchars($query) . '</pre></td>
            </tr>
            <tr>
              <td class="row2">Time:</td>
              <td class="row1">' . number_format($this->query_times[$query], 6) . ' seconds</td>
            </tr>
            <tr>
              <td class="row2">Unbuffered:</td>
              <td class="row1">' . ( $unbuffered ? 'Yes' : 'No' ) . '</td>
            </tr>';
      if ( isset($this->query_sources[$query]) )
      {
        echo '<tr>
                <td class="row2">Called from:</td>
                <td class="row1">' . $this->query_sources[$query] . '</td>
              </tr>';
      }
    }
    if ( function_exists('array_sum') )
    {
      $query_time_total = array_sum($this->query_times);
      echo '<tr>
              <th class="subhead" colspan="2">
                Total time taken for SQL queries: ' . round( $query_time_total, 6 ) . ' seconds
              </th>
            </tr>';
    }
    echo '  </table>
          </div>';
    $template->footer();
  }
}

?>
