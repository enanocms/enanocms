<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.2 (Coblynau)
 * Copyright (C) 2006-2007 Dan Fuhry
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
  $debug = $debug[2]['file'] . ', line ' . $debug[2]['line'];
  echo "<b>$errtype:</b> $errstr<br />Error source:<pre>$debug</pre>";
}
 
class mysql {
  var $num_queries, $query_backtrace, $latest_result, $latest_query, $_conn, $sql_stack_fields, $sql_stack_values;
  var $row = array();
	var $rowset = array();
  var $errhandler;
  
  function enable_errorhandler()
  {
    if ( function_exists('debug_backtrace') )
    {
      $this->errhandler = set_error_handler('db_error_handler');
    }
  }
  
  function disable_errorhandler()
  {
    if ( $this->errhandler )
    {
      set_error_handler($this->errhandler);
    }
    else
    {
      restore_error_handler();
    }
  }
  
  function sql_backtrace() {
    $qb = explode("\n", $this->query_backtrace);
    $bt = '';
    //for($i=sizeof($qb)-1;$i>=0;$i--) {
    for($i=0;$i<sizeof($qb);$i++) {
      $bt .= $qb[$i]."\n";
    }
    return $bt;
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
  
  function die_json()
  {
    $e = addslashes(htmlspecialchars(mysql_error()));
    $q = addslashes($this->latest_query);
    $t = "{'mode':'error','error':'An error occurred during database query.\nQuery was:\n  $q\n\nError returned by MySQL: $e'}";
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
  
  function connect()
  {
    $this->enable_errorhandler();
    
    dc_here('dbal: trying to connect....');
    
    if ( defined('IN_ENANO_INSTALL') )
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
      dc_here('dbal: oops, looks like Enano isn\'t set up. Constants ENANO_INSTALLED, MIDGET_INSTALLED, and IN_ENANO_INSTALL are all undefined.');
      // scriptPath isn't set yet - we need to autodetect it to avoid infinite redirects
      if ( !defined('scriptPath') )
      {
        if ( isset($_SERVER['PATH_INFO']) )
        {
          $_SERVER['REQUEST_URI'] = preg_replace(';' . preg_quote($_SERVER['PATH_INFO']) . '$;', '', $_SERVER['REQUEST_URI']);
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
    $this->_conn = @mysql_connect($dbhost, $dbuser, $dbpasswd);
    unset($dbuser);
    unset($dbpasswd); // Security
    
    if ( !$this->_conn )
    {
      dc_here('dbal: uhoh!<br />'.mysql_error());
      grinding_halt('Enano is having a problem', '<p>Error: couldn\'t connect to MySQL.<br />'.mysql_error().'</p>');
    }
    
    // Reset some variables
    $this->query_backtrace = '';
    $this->num_queries = 0;
    
    dc_here('dbal: we\'re in, selecting database...');
    $q = $this->sql_query('USE `'.$dbname.'`;');
    
    if ( !$q )
      $this->_die('The database could not be selected.');
    
    // We're in!
    dc_here('dbal: connected to MySQL');
    
    $this->disable_errorhandler();
    return true;
  }
  
  function sql_query($q)
  {
    $this->enable_errorhandler();
    $this->num_queries++;
    $this->query_backtrace .= $q . "\n";
    $this->latest_query = $q;
    dc_here('dbal: making SQL query:<br /><tt>'.$q.'</tt>');
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
    
    $r = mysql_query($q, $this->_conn);
    $this->latest_result = $r;
    $this->disable_errorhandler();
    return $r;
  }
  
  function sql_unbuffered_query($q)
  {
    $this->enable_errorhandler();
    $this->num_queries++;
    $this->query_backtrace .= '(UNBUFFERED) ' . $q."\n";
    $this->latest_query = $q;
    dc_here('dbal: making SQL query:<br /><tt>'.$q.'</tt>');
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
    
    $r = mysql_unbuffered_query($q, $this->_conn);
    $this->latest_result = $r;
    $this->disable_errorhandler();
    return $r;
  }
  
  /**
   * Checks a SQL query for possible signs of injection attempts
   * @param string $q the query to check
   * @return bool true if query passed check, otherwise false
   */
  
  function check_query($q, $debug = false)
  {
    if($debug) echo "\$db-&gt;check_query(): checking query: ".htmlspecialchars($q).'<br />'."\n";
    $sz = strlen($q);
    $quotechar = false;
    $quotepos  = 0;
    $prev_is_quote = false;
    $just_started = false;
    for ( $i = 0; $i < strlen($q); $i++, $c = substr($q, $i, 1) )
    {
      $next = substr($q, $i+1, 1);
      $next2 = substr($q, $i+2, 1);
      $prev = substr($q, $i-1, 1);
      $prev2 = substr($q, $i-2, 1);
      if(isset($c) && in_array($c, Array('"', "'", '`')))
      {
        if($quotechar)
        {
          if (
              ( $quotechar == $c && $quotechar != $next && ( $quotechar != $prev || $just_started ) && $prev != '\\') ||
              ( $prev2 == '\\' && $prev == $quotechar && $quotechar == $c )
            )
          {
            $quotechar = false;
            if($debug) echo('$db-&gt;check_query(): just finishing a quote section, quoted string: '.htmlspecialchars(substr($q, $quotepos, $i - $quotepos + 1)) . '<br />');
            $q = substr($q, 0, $quotepos) . 'SAFE_QUOTE' . substr($q, $i + 1, strlen($q));
            if($debug) echo('$db-&gt;check_query(): Filtered query: '.$q.'<br />');
            $i = $quotepos;
          }
        }
        else
        {
          $quotechar = $c;
          $quotepos  = $i;
          $just_started = true;
        }
        if($debug) echo '$db-&gt;check_query(): found quote char as pos: '.$i.'<br />';
        continue;
      }
      $just_started = false;
    }
    if(substr(trim($q), strlen(trim($q))-1, 1) == ';') $q = substr(trim($q), 0, strlen(trim($q))-1);
    for($i=0;$i<strlen($q);$i++,$c=substr($q, $i, 1))
    {
      if ( 
           ( ( $c == ';' && $i != $sz-1 ) || $c . substr($q, $i+1, 1) == '--' )
        || ( in_array($c, Array('"', "'", '`')) )
         ) // Don't permit semicolons in mid-query, and never allow comments
      {
        // Injection attempt!
        if($debug)
        {
          $e = '';
          for($j=$i-5;$j<$i+5;$j++)
          {
            if($j == $i) $e .= '<span style="color: red; text-decoration: underline;">' . $c . '</span>';
            else $e .= $c;
          }
          echo 'Injection attempt caught at pos: '.$i.'<br />';
        }
        return false;
      }
    }
    if ( preg_match('/[\s]+(SAFE_QUOTE|[\S]+)=\\1($|[\s]+)/', $q, $match) )
    {
      if ( $debug ) echo 'Found always-true test in query, injection attempt caught, match:<br />' . '<pre>' . print_r($match, true) . '</pre>';
      return false;
    }
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
    return $row;
  }
  
  function fetchrow_num($r = false) {
    $this->enable_errorhandler();
    if(!$r) $r = $this->latest_result;
    if(!$r) $this->_die('$db->fetchrow(): an invalid MySQL resource was passed.');
    $row = mysql_fetch_row($r);
    $this->disable_errorhandler();
    return $row;
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
    mysql_free_result($result);
    $this->disable_errorhandler();
    return null;
  }
  
  function close() {
    dc_here('dbal: closing MySQL connection');
    mysql_close($this->_conn);
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
    mysql_free_result($r);
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
		if ( $this->_conn )
		{
			return mysql_error();
		}
		else
		{
			return array();
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
}

?>
