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
  
  function enable_errorhandler()
  {
    if ( !defined('ENANO_DEBUG') )
      return true;
    // echo "DBAL: enabling error handler<br />";
    if ( function_exists('debug_backtrace') )
    {
      $this->errhandler = set_error_handler('db_error_handler');
    }
  }
  
  function disable_errorhandler()
  {
    if ( !defined('ENANO_DEBUG') )
      return true;
    // echo "DBAL: disabling error handler<br />";
    if ( $this->errhandler )
    {
      set_error_handler($this->errhandler);
    }
    else
    {
      restore_error_handler();
    }
  }
  
  function sql_backtrace()
  {
    return implode("\n-------------------------------------------------------------------\n", $this->query_backtrace);
  }
  
  function ensure_connection()
  {
    if(!$this->_conn)
    {
      $this->connect();
    }
  }
  
  function _die($t = '') {
    if(defined('ENANO_HEADERS_SENT')) {
      ob_clean();
    }
    header('HTTP/1.1 500 Internal Server Error');
    $bt = $this->latest_query; // $this->sql_backtrace();
    $e = htmlspecialchars(mysql_error());
    if($e=='') $e='&lt;none&gt;';
    $t = ( !empty($t) ) ? $t : '&lt;No error description provided&gt;';
    global $email;
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) ) ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;' : '';
    $internal_text = '<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site' . $email_info . '.</p>
                      <p>Description or location of error: '.$t.'<br />
                      Error returned by MySQL extension: ' . $e . '<br />
                      Most recent SQL query:</p>
                      <pre>'.$bt.'</pre>';
    if(defined('ENANO_CONFIG_FETCHED')) die_semicritical('Database error', $internal_text);
    else                                   grinding_halt('Database error', $internal_text);
    exit;
  }
  
  function die_json($loc = false)
  {
    $e = addslashes(htmlspecialchars(mysql_error()));
    $q = str_replace("\n", "\\n", addslashes($this->latest_query));
    $loc = ( $loc ) ? addslashes("\n\nDescription or location of error: $loc") : "";
    $loc .= "\n\nPlease report the full text of this error to the administrator of the site. If you believe that this is a bug with the software, please contact support@enanocms.org.";
    $loc = str_replace("\n", "\\n", $loc);
    $t = "{\"mode\":\"error\",\"error\":\"An error occurred during database query.\\nQuery was:\\n  $q\\n\\nError returned by MySQL: $e$loc\"}";
    die($t);
  }
  
  function get_error($t = '') {
    header('HTTP/1.1 500 Internal Server Error');
    $bt = $this->sql_backtrace();
    $e = htmlspecialchars(mysql_error());
    if($e=='') $e='&lt;none&gt;';
    global $email;
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) ) ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;' : '';
    $internal_text = '<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site' . $email_info . '.</p>
                      <p>Description or location of error: '.$t.'<br />
                      Error returned by MySQL extension: ' . $e . '<br />
                      Most recent SQL query:</p>
                      <pre>'.$bt.'</pre>';
    return $internal_text;
  }
  
  function connect($manual_credentials = false, $dbhost = false, $dbuser = false, $dbpasswd = false, $dbname = false)
  {
    $this->enable_errorhandler();
    
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
    
    $this->_conn = @mysql_connect($dbhost, $dbuser, $dbpasswd);
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
    
    $q = $this->sql_query('USE `'.$dbname.'`;');
    
    if ( !$q )
    {
      if ( $manual_credentials )
        return false;
      $this->_die('The database could not be selected.');
    }
    
    // We're in!
    $this->disable_errorhandler();
    return true;
  }
  
  function sql_query($q, $log_query = true)
  {
    if ( $log_query || defined('ENANO_DEBUG') )
      $this->enable_errorhandler();
    
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
    
    if ( $log_query )
      $this->disable_errorhandler();
    return $r;
  }
  
  function sql_unbuffered_query($q, $log_query = true)
  {
    $this->enable_errorhandler();
    
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
    $this->disable_errorhandler();
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
   * @param resource $result The MySQL result resource - if not given, the latest cached query is assumed
   * @return true on success, false on failure
   */
   
  function sql_data_seek($pos, $result = false)
  {
    $this->enable_errorhandler();
    if(!$result)
      $result = $this->latest_result;
    if(!$result)
    {
      $this->disable_errorhandler();
      return false;
    }
    if(mysql_data_seek($result, $pos))
    {
      $this->disable_errorhandler();
      return true;
    }
    else
    {
      $this->disable_errorhandler();
      return false;
    }
  }
  
  /**
   * Reports a bad query to the admin
   * @param string $query the naughty query
   * @access private
   */
   
  function report_query($query)
  {
    global $session;
    if(is_object($session) && defined('ENANO_MAINSTREAM'))
      $username = $session->username;
    else
      $username = 'Unavailable';
    $query = $this->escape($query);
    $q = $this->sql_query('INSERT INTO '.table_prefix.'logs(log_type,     action,         time_id,    date_string, page_text,      author,            edit_summary)
                                                     VALUES(\'security\', \'sql_inject\', '.time().', \'\',        \''.$query.'\', \''.$username.'\', \''.$_SERVER['REMOTE_ADDR'].'\');');
  }
  
  /**
   * Returns the ID of the row last inserted.
   * @return int
   */
  
  function insert_id()
  {
    return @mysql_insert_id();
  }
  
  function fetchrow($r = false) {
    $this->enable_errorhandler();
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $row = mysql_fetch_assoc($r);
    $this->disable_errorhandler();
    return integerize_array($row);
  }
  
  function fetchrow_num($r = false) {
    $this->enable_errorhandler();
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $row = mysql_fetch_row($r);
    $this->disable_errorhandler();
    return integerize_array($row);
  }
  
  function numrows($r = false) {
    $this->enable_errorhandler();
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $n = mysql_num_rows($r);
    $this->disable_errorhandler();
    return $n;
  }
  
  function escape($str)
  {
    $this->enable_errorhandler();
    $str = mysql_real_escape_string($str);
    $this->disable_errorhandler();
    return $str;
  }
  
  function free_result($result = false)
  {
    $this->enable_errorhandler();
    if(!$result)
      $result = $this->latest_result;
    if(!$result)
    {
      $this->disable_errorhandler();
      return null;
    }
    @mysql_free_result($result);
    $this->disable_errorhandler();
    return null;
  }
  
  function close() {
    @mysql_close($this->_conn);
    unset($this->_conn);
  }
  
  // phpBB DBAL compatibility
  function sql_fetchrow($r = false)
  {
    return $this->fetchrow($r);
  }
  function sql_freeresult($r = false)
  {
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    @mysql_free_result($r);
  }
  function sql_numrows($r = false)
  {
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    return mysql_num_rows($r);
  }
  function sql_affectedrows($r = false, $f, $n)
  {
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    return mysql_affected_rows();
  }
  
  function sql_type_cast(&$value)
	{
		if ( is_float($value) )
		{
			return doubleval($value);
		}
		if ( is_integer($value) || is_bool($value) )
		{
			return intval($value);
		}
		if ( is_string($value) || empty($value) )
		{
			return '\'' . $this->sql_escape_string($value) . '\'';
		}
		// uncastable var : let's do a basic protection on it to prevent sql injection attempt
		return '\'' . $this->sql_escape_string(htmlspecialchars($value)) . '\'';
	}

	function sql_statement(&$fields, $fields_inc='')
	{
		// init result
		$this->sql_fields = $this->sql_values = $this->sql_update = '';
		if ( empty($fields) && empty($fields_inc) )
		{
			return;
		}

		// process
		if ( !empty($fields) )
		{
			$first = true;
			foreach ( $fields as $field => $value )
			{
				// field must contain a field name
				if ( !empty($field) && is_string($field) )
				{
					$value = $this->sql_type_cast($value);
					$this->sql_fields .= ( $first ? '' : ', ' ) . $field;
					$this->sql_values .= ( $first ? '' : ', ' ) . $value;
					$this->sql_update .= ( $first ? '' : ', ' ) . $field . ' = ' . $value;
					$first = false;
				}
			}
		}
		if ( !empty($fields_inc) )
		{
			foreach ( $fields_inc as $field => $indent )
			{
				if ( $indent != 0 )
				{
					$this->sql_update .= (empty($this->sql_update) ? '' : ', ') . $field . ' = ' . $field . ($indent < 0 ? ' - ' : ' + ') . abs($indent);
				}
			}
		}
	}

	function sql_stack_reset($id='')
	{
		if ( empty($id) )
		{
			$this->sql_stack_fields = array();
			$this->sql_stack_values = array();
		}
		else
		{
			$this->sql_stack_fields[$id] = array();
			$this->sql_stack_values[$id] = array();
		}
	}

	function sql_stack_statement(&$fields, $id='')
	{
		$this->sql_statement($fields);
		if ( empty($id) )
		{
			$this->sql_stack_fields = $this->sql_fields;
			$this->sql_stack_values[] = '(' . $this->sql_values . ')';
		}
		else
		{
			$this->sql_stack_fields[$id] = $this->sql_fields;
			$this->sql_stack_values[$id][] = '(' . $this->sql_values . ')';
		}
	}

	function sql_stack_insert($table, $transaction=false, $line='', $file='', $break_on_error=true, $id='')
	{
		if ( (empty($id) && empty($this->sql_stack_values)) || (!empty($id) && empty($this->sql_stack_values[$id])) )
		{
			return false;
		}
		switch( SQL_LAYER )
		{
			case 'mysql':
			case 'mysql4':
				if ( empty($id) )
				{
					$sql = 'INSERT INTO ' . $table . '
								(' . $this->sql_stack_fields . ') VALUES ' . implode(",\n", $this->sql_stack_values);
				}
				else
				{
					$sql = 'INSERT INTO ' . $table . '
								(' . $this->sql_stack_fields[$id] . ') VALUES ' . implode(",\n", $this->sql_stack_values[$id]);
				}
				$this->sql_stack_reset($id);
				return $this->sql_query($sql, $transaction, $line, $file, $break_on_error);
				break;
			default:
				$count_sql_stack_values = empty($id) ? count($this->sql_stack_values) : count($this->sql_stack_values[$id]);
				$result = !empty($count_sql_stack_values);
				for ( $i = 0; $i < $count_sql_stack_values; $i++ )
				{
					if ( empty($id) )
					{
						$sql = 'INSERT INTO ' . $table . '
									(' . $this->sql_stack_fields . ') VALUES ' . $this->sql_stack_values[$i];
					}
					else
					{
						$sql = 'INSERT INTO ' . $table . '
									(' . $this->sql_stack_fields[$id] . ') VALUES ' . $this->sql_stack_values[$id][$i];
					}
					$result &= $this->sql_query($sql, $transaction, $line, $file, $break_on_error);
				}
				$this->sql_stack_reset($id);
				return $result;
				break;
		}
	}

	function sql_subquery($field, $sql, $line='', $file='', $break_on_error=true, $type=TYPE_INT)
	{
		// sub-queries doable
		$this->sql_get_version();
		if ( !in_array(SQL_LAYER, array('mysql', 'mysql4')) || (($this->sql_version[0] + ($this->sql_version[1] / 100)) >= 4.01) )
		{
			return $sql;
		}

		// no sub-queries
		$ids = array();
		$result = $this->sql_query(trim($sql), false, $line, $file, $break_on_error);
		while ( $row = $this->sql_fetchrow($result) )
		{
			$ids[] = $type == TYPE_INT ? intval($row[$field]) : '\'' . $this->sql_escape_string($row[$field]) . '\'';
		}
		$this->sql_freeresult($result);
		return empty($ids) ? 'NULL' : implode(', ', $ids);
	}

	function sql_col_id($expr, $alias)
	{
		$this->sql_get_version();
		return in_array(SQL_LAYER, array('mysql', 'mysql4')) && (($this->sql_version[0] + ($this->sql_version[1] / 100)) <= 4.01) ? $alias : $expr;
	}

	function sql_get_version()
	{
		if ( empty($this->sql_version) )
		{
			$this->sql_version = array(0, 0, 0);
			switch ( SQL_LAYER )
			{
				case 'mysql':
				case 'mysql4':
					if ( function_exists('mysql_get_server_info') )
					{
						$lo_version = explode('-', mysql_get_server_info());
						$this->sql_version = explode('.', $lo_version[0]);
						$this->sql_version = array(intval($this->sql_version[0]), intval($this->sql_version[1]), intval($this->sql_version[2]), $lo_version[1]);
					}
					break;

				case 'postgresql':
				case 'mssql':
				case 'mssql-odbc':
				default:
					break;
			}
		}
		return $this->sql_version;
	}

	function sql_error()
	{
    return mysql_error();
	}
  function sql_escape_string($t) 
  {
    return mysql_real_escape_string($t);
  }
  function sql_close()
  {
    $this->close();
  }
  function sql_fetchrowset($query_id = 0)
	{
		if( !$query_id )
		{
			$query_id = $this->query_result;
		}

		if( $query_id )
		{
			unset($this->rowset[$query_id]);
			unset($this->row[$query_id]);

			while($this->rowset[$query_id] = mysql_fetch_array($query_id, MYSQL_ASSOC))
			{
				$result[] = $this->rowset[$query_id];
			}

			return $result;
		}
		else
		{
			return false;
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

class postgresql {
  var $num_queries, $query_backtrace, $query_times, $query_sources, $latest_result, $latest_query, $_conn, $sql_stack_fields, $sql_stack_values, $debug;
  var $row = array();
	var $rowset = array();
  var $errhandler;
  
  function enable_errorhandler()
  {
    // echo "DBAL: enabling error handler<br />";
    if ( function_exists('debug_backtrace') )
    {
      $this->errhandler = set_error_handler('db_error_handler');
    }
  }
  
  function disable_errorhandler()
  {
    // echo "DBAL: disabling error handler<br />";
    if ( $this->errhandler )
    {
      set_error_handler($this->errhandler);
    }
    else
    {
      restore_error_handler();
    }
  }
  
  function sql_backtrace()
  {
    return implode("\n-------------------------------------------------------------------\n", $this->query_backtrace);
  }
  
  function ensure_connection()
  {
    if(!$this->_conn)
    {
      $this->connect();
    }
  }
  
  function _die($t = '') {
    if(defined('ENANO_HEADERS_SENT')) {
      ob_clean();
    }
    header('HTTP/1.1 500 Internal Server Error');
    $bt = $this->latest_query; // $this->sql_backtrace();
    $e = htmlspecialchars(pg_last_error());
    if($e=='') $e='&lt;none&gt;';
    $t = ( !empty($t) ) ? $t : '&lt;No error description provided&gt;';
    global $email;
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) ) ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;' : '';
    $internal_text = '<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site' . $email_info . '.</p>
                      <p>Description or location of error: '.$t.'<br />
                      Error returned by PostgreSQL extension: ' . $e . '<br />
                      Most recent SQL query:</p>
                      <pre>'.$bt.'</pre>';
    if(defined('ENANO_CONFIG_FETCHED')) die_semicritical('Database error', $internal_text);
    else                                   grinding_halt('Database error', $internal_text);
    exit;
  }
  
  function die_json()
  {
    $e = addslashes(htmlspecialchars(pg_last_error()));
    $q = addslashes($this->latest_query);
    $t = "{'mode':'error','error':'An error occurred during database query.\nQuery was:\n  $q\n\nError returned by PostgreSQL: $e'}";
    die($t);
  }
  
  function get_error($t = '') {
    @header('HTTP/1.1 500 Internal Server Error');
    $bt = $this->sql_backtrace();
    $e = htmlspecialchars(pg_last_error());
    if($e=='') $e='&lt;none&gt;';
    global $email;
    $email_info = ( defined('ENANO_CONFIG_FETCHED') && is_object($email) ) ? ', at &lt;' . $email->jscode() . $email->encryptEmail(getConfig('contact_email')) . '&gt;' : '';
    $internal_text = '<h3>The site was unable to finish serving your request.</h3>
                      <p>We apologize for the inconveience, but an error occurred in the Enano database layer. Please report the full text of this page to the administrator of this site' . $email_info . '.</p>
                      <p>Description or location of error: '.$t.'<br />
                      Error returned by MySQL extension: ' . $e . '<br />
                      Most recent SQL query:</p>
                      <pre>'.$bt.'</pre>';
    return $internal_text;
  }
  
  function connect($manual_credentials = false, $dbhost = false, $dbuser = false, $dbpasswd = false, $dbname = false)
  {
    $this->enable_errorhandler();
    
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
    $this->_conn = @pg_connect("host=$dbhost port=5432 dbname=$dbname user=$dbuser password=$dbpasswd");
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
    $this->disable_errorhandler();
    return true;
  }
  
  function sql_query($q)
  {
    $this->enable_errorhandler();
    
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
    $r = pg_query($q);
    $this->query_times[$q] = microtime_float() - $time_start;
    $this->latest_result = $r;
    $this->disable_errorhandler();
    return $r;
  }
  
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
   * @param resource $result The MySQL result resource - if not given, the latest cached query is assumed
   * @return true on success, false on failure
   */
   
  function sql_data_seek($pos, $result = false)
  {
    $this->enable_errorhandler();
    if(!$result)
      $result = $this->latest_result;
    if(!$result)
    {
      $this->disable_errorhandler();
      return false;
    }
    if(pg_result_seek($result, $pos))
    {
      $this->disable_errorhandler();
      return true;
    }
    else
    {
      $this->disable_errorhandler();
      return false;
    }
  }
  
  /**
   * Reports a bad query to the admin
   * @param string $query the naughty query
   * @access private
   */
   
  function report_query($query)
  {
    global $session;
    if(is_object($session) && defined('ENANO_MAINSTREAM'))
      $username = $session->username;
    else
      $username = 'Unavailable';
    $query = $this->escape($query);
    $q = $this->sql_query('INSERT INTO '.table_prefix.'logs(log_type,     action,         time_id,    date_string, page_text,      author,            edit_summary)
                                                     VALUES(\'security\', \'sql_inject\', '.time().', \'\',        \''.$query.'\', \''.$username.'\', \''.$_SERVER['REMOTE_ADDR'].'\');');
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
  
  function fetchrow($r = false) {
    $this->enable_errorhandler();
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $row = pg_fetch_assoc($r);
    $this->disable_errorhandler();
    return integerize_array($row);
  }
  
  function fetchrow_num($r = false) {
    $this->enable_errorhandler();
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $row = pg_fetch_row($r);
    $this->disable_errorhandler();
    return integerize_array($row);
  }
  
  function numrows($r = false) {
    $this->enable_errorhandler();
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $n = pg_num_rows($r);
    $this->disable_errorhandler();
    return $n;
  }
  
  function escape($str)
  {
    $this->enable_errorhandler();
    $str = pg_escape_string($str);
    $this->disable_errorhandler();
    return $str;
  }
  
  function free_result($result = false)
  {
    $this->enable_errorhandler();
    if(!$result)
      $result = $this->latest_result;
    if(!$result)
    {
      $this->disable_errorhandler();
      return null;
    }
    @pg_free_result($result);
    $this->disable_errorhandler();
    return null;
  }
  
  function close() {
    @pg_close($this->_conn);
    unset($this->_conn);
  }
  
  // phpBB DBAL compatibility
  function sql_fetchrow($r = false)
  {
    return $this->fetchrow($r);
  }
  function sql_freeresult($r = false)
  {
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $this->free_result($r);
  }
  function sql_numrows($r = false)
  {
    return $this->numrows();
  }
  function sql_affectedrows($r = false, $f, $n)
  {
    if(!$this->_conn) return false;
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    return pg_affected_rows();
  }
  
  function sql_type_cast(&$value)
	{
		if ( is_float($value) )
		{
			return doubleval($value);
		}
		if ( is_integer($value) || is_bool($value) )
		{
			return intval($value);
		}
		if ( is_string($value) || empty($value) )
		{
			return '\'' . $this->sql_escape_string($value) . '\'';
		}
		// uncastable var : let's do a basic protection on it to prevent sql injection attempt
		return '\'' . $this->sql_escape_string(htmlspecialchars($value)) . '\'';
	}

	function sql_statement(&$fields, $fields_inc='')
	{
		// init result
		$this->sql_fields = $this->sql_values = $this->sql_update = '';
		if ( empty($fields) && empty($fields_inc) )
		{
			return;
		}

		// process
		if ( !empty($fields) )
		{
			$first = true;
			foreach ( $fields as $field => $value )
			{
				// field must contain a field name
				if ( !empty($field) && is_string($field) )
				{
					$value = $this->sql_type_cast($value);
					$this->sql_fields .= ( $first ? '' : ', ' ) . $field;
					$this->sql_values .= ( $first ? '' : ', ' ) . $value;
					$this->sql_update .= ( $first ? '' : ', ' ) . $field . ' = ' . $value;
					$first = false;
				}
			}
		}
		if ( !empty($fields_inc) )
		{
			foreach ( $fields_inc as $field => $indent )
			{
				if ( $indent != 0 )
				{
					$this->sql_update .= (empty($this->sql_update) ? '' : ', ') . $field . ' = ' . $field . ($indent < 0 ? ' - ' : ' + ') . abs($indent);
				}
			}
		}
	}

	function sql_stack_reset($id='')
	{
		if ( empty($id) )
		{
			$this->sql_stack_fields = array();
			$this->sql_stack_values = array();
		}
		else
		{
			$this->sql_stack_fields[$id] = array();
			$this->sql_stack_values[$id] = array();
		}
	}

	function sql_stack_statement(&$fields, $id='')
	{
		$this->sql_statement($fields);
		if ( empty($id) )
		{
			$this->sql_stack_fields = $this->sql_fields;
			$this->sql_stack_values[] = '(' . $this->sql_values . ')';
		}
		else
		{
			$this->sql_stack_fields[$id] = $this->sql_fields;
			$this->sql_stack_values[$id][] = '(' . $this->sql_values . ')';
		}
	}

	function sql_stack_insert($table, $transaction=false, $line='', $file='', $break_on_error=true, $id='')
	{
		if ( (empty($id) && empty($this->sql_stack_values)) || (!empty($id) && empty($this->sql_stack_values[$id])) )
		{
			return false;
		}
		switch( SQL_LAYER )
		{
			case 'mysql':
			case 'mysql4':
				if ( empty($id) )
				{
					$sql = 'INSERT INTO ' . $table . '
								(' . $this->sql_stack_fields . ') VALUES ' . implode(",\n", $this->sql_stack_values);
				}
				else
				{
					$sql = 'INSERT INTO ' . $table . '
								(' . $this->sql_stack_fields[$id] . ') VALUES ' . implode(",\n", $this->sql_stack_values[$id]);
				}
				$this->sql_stack_reset($id);
				return $this->sql_query($sql, $transaction, $line, $file, $break_on_error);
				break;
			default:
				$count_sql_stack_values = empty($id) ? count($this->sql_stack_values) : count($this->sql_stack_values[$id]);
				$result = !empty($count_sql_stack_values);
				for ( $i = 0; $i < $count_sql_stack_values; $i++ )
				{
					if ( empty($id) )
					{
						$sql = 'INSERT INTO ' . $table . '
									(' . $this->sql_stack_fields . ') VALUES ' . $this->sql_stack_values[$i];
					}
					else
					{
						$sql = 'INSERT INTO ' . $table . '
									(' . $this->sql_stack_fields[$id] . ') VALUES ' . $this->sql_stack_values[$id][$i];
					}
					$result &= $this->sql_query($sql, $transaction, $line, $file, $break_on_error);
				}
				$this->sql_stack_reset($id);
				return $result;
				break;
		}
	}

	function sql_subquery($field, $sql, $line='', $file='', $break_on_error=true, $type=TYPE_INT)
	{
		// sub-queries doable
		$this->sql_get_version();
		if ( !in_array(SQL_LAYER, array('mysql', 'mysql4')) || (($this->sql_version[0] + ($this->sql_version[1] / 100)) >= 4.01) )
		{
			return $sql;
		}

		// no sub-queries
		$ids = array();
		$result = $this->sql_query(trim($sql), false, $line, $file, $break_on_error);
		while ( $row = $this->sql_fetchrow($result) )
		{
			$ids[] = $type == TYPE_INT ? intval($row[$field]) : '\'' . $this->sql_escape_string($row[$field]) . '\'';
		}
		$this->sql_freeresult($result);
		return empty($ids) ? 'NULL' : implode(', ', $ids);
	}

	function sql_col_id($expr, $alias)
	{
		$this->sql_get_version();
		return in_array(SQL_LAYER, array('mysql', 'mysql4')) && (($this->sql_version[0] + ($this->sql_version[1] / 100)) <= 4.01) ? $alias : $expr;
	}

	function sql_get_version()
	{
		if ( empty($this->sql_version) )
		{
			$this->sql_version = array(0, 0, 0);
			switch ( SQL_LAYER )
			{
				case 'mysql':
				case 'mysql4':
					if ( function_exists('mysql_get_server_info') )
					{
						$lo_version = explode('-', mysql_get_server_info());
						$this->sql_version = explode('.', $lo_version[0]);
						$this->sql_version = array(intval($this->sql_version[0]), intval($this->sql_version[1]), intval($this->sql_version[2]), $lo_version[1]);
					}
					break;

				case 'postgresql':
				case 'mssql':
				case 'mssql-odbc':
				default:
					break;
			}
		}
		return $this->sql_version;
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
  function sql_escape_string($t) 
  {
    return mysql_real_escape_string($t);
  }
  function sql_close()
  {
    $this->close();
  }
  function sql_fetchrowset($query_id = 0)
	{
		if( !$query_id )
		{
			$query_id = $this->query_result;
		}

		if( $query_id )
		{
			unset($this->rowset[$query_id]);
			unset($this->row[$query_id]);

			while($this->rowset[$query_id] = mysql_fetch_array($query_id, MYSQL_ASSOC))
			{
				$result[] = $this->rowset[$query_id];
			}

			return $result;
		}
		else
		{
			return false;
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
