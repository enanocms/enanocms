<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * class_http.php - Pure PHP HTTP client library
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

//
// HTTP status codes
//

// Informational
define('HTTP_CONTINUE', 100);
define('HTTP_SWITCHING_PROTOCOLS', 101);
define('HTTP_PROCESSING', 102);

// Success
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_ACCEPTED', 202);
define('HTTP_NON_AUTHORITATIVE', 203);
define('HTTP_NO_CONTENT', 204);
define('HTTP_RESET_CONTENT', 205);
define('HTTP_PARTIAL_CONTENT', 206);
define('HTTP_MULTI_STATUS', 207);

// Redirection
define('HTTP_MULTIPLE_CHOICES', 300);
define('HTTP_MOVED_PERMANENTLY', 301);
define('HTTP_FOUND', 302);
define('HTTP_SEE_OTHER', 303);
define('HTTP_NOT_MODIFIED', 304);
define('HTTP_USE_PROXY', 305);
define('HTTP_SWITCH_PROXY', 306);
define('HTTP_TEMPORARY_REDIRECT', 307);

// Client Error
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_PAYMENT_REQUIRED', 402);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_NOT_ACCEPTABLE', 406);
define('HTTP_PROXY_AUTHENTICATION_REQUIRED', 407);
define('HTTP_REQUEST_TIMEOUT', 408);
define('HTTP_CONFLICT', 409);
define('HTTP_GONE', 410);
define('HTTP_LENGTH_REQUIRED', 411);
define('HTTP_PRECONDITION_FAILED', 412);
define('HTTP_REQUEST_ENTITY_TOO_LARGE', 413);
define('HTTP_REQUEST_URI_TOO_LONG', 414);
define('HTTP_UNSUPPORTED_MEDIA_TYPE', 415);
define('HTTP_REQUESTED_RANGE_NOT_SATISFIABLE', 416);
define('HTTP_EXPECTATION_FAILED', 417);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_LOCKED', 423);
define('HTTP_FAILED_DEPENDENCY', 424);
define('HTTP_UNORDERED_COLLECTION', 425);
define('HTTP_UPGRADE_REQUIRED', 426);
define('HTTP_RETRY_WITH', 449);

// Server error
define('HTTP_INTERNAL_SERVER_ERROR', 500);
define('HTTP_NOT_IMPLEMENTED', 501);
define('HTTP_BAD_GATEWAY', 502);
define('HTTP_SERVICE_TEMPORARILY_UNAVAILABLE', 503);
define('HTTP_GATEWAY_TIMEOUT', 504);
define('HTTP_HTTP_VERSION_NOT_SUPPORTED', 505);
define('HTTP_VARIANT_ALSO_NEGOTIATES', 506);
define('HTTP_INSUFFICIENT_STORAGE', 507);
define('HTTP_BANDWIDTH_LIMIT_EXCEEDED', 509);
define('HTTP_NOT_EXTENDED', 510);

/**
 * Class for making HTTP requests. This can do GET and POST, and when used properly it consumes under a meg of memory, even with huge files.
 * @package Enano
 * @subpackage Backend functions
 * @copyright 2007 Dan Fuhry
 */

class Request_HTTP
{
	
	/**
 	* Switch to enable or disable debugging. You want this off on production sites.
 	* @var bool
 	*/
	
	var $debug = false;
	
	/**
 	* The host the request will be sent to.
 	* @var string
 	*/
	
	var $host = '';
	
	/**
 	* The TCP port our connection is (will be) on.
 	* @var int
 	*/
	
	var $port = 80;
	
	/**
 	* The request method. Can be GET or POST, defaults to GET.
 	* @var string
 	*/
	
	var $method = 'GET';
	
	/**
 	* The URI to the remote script.
 	* @var string
 	*/
	
	var $uri = '';
	
	/**
 	* The parameters to be sent on GET.
 	* @var array (associative)
 	*/
	
	var $parms_get = array();
	
	/**
 	* The parameters to be sent on POST. Ignored if $this->method == GET.
 	* @var array (associative)
 	*/
	
	var $parms_post = array();
	
	/**
 	* The list of cookies that will be sent.
 	* @var array (associative)
 	*/
	
	var $cookies_out = array();
	
	/**
 	* Additional request headers.
 	* @var array (associative)
 	*/
	
	var $headers = array();
	
	/**
 	* Follow server-side redirects; defaults to true.
 	* @var bool
 	*/
	
	var $follow_redirects = true;
	
	/**
 	* Cached response.
 	* @var string, or bool:false if the request hasn't been sent yet
 	*/
	
	var $response = false;
	
	/**
 	* Cached response code
 	* @var int set to -1 if request hasn't been sent yet
 	*/
	
	var $response_code = -1;
	
	/**
 	* Cached response code string
 	* @var string or bool:false if the request hasn't been sent yet
 	*/
	
	var $response_string = false;
	
	/**
 	* Resource for the socket. False if a connection currently isn't going.
 	* @var resource
 	*/
	
	var $socket = false;
	
	/**
 	* True if SSL is on, defaults to false
 	* @var bool
 	*/
	
	var $ssl = false;
	
	/**
 	* The state of our request. 0 means it hasn't been made yet. 1 means the socket is open, 2 means the socket is open and the request has been written, 3 means the headers have been fetched, and 4 means the request is completed.
 	* @var int
 	*/
	
	var $state = 0;
	
	/**
 	* Constructor.
 	* @param string Hostname to send to
 	* @param string URI (/index.php)
 	* @param string Request method - GET or POST.
 	* @param int Optional. The port to open the request on. Defaults to 80.
 	* @param bool If true, uses SSL (and defaults the port to 443)
 	*/
	
	function Request_HTTP($host, $uri, $method = 'GET', $port = 'default', $ssl = false)
	{
		if ( !preg_match('/^(?:(([a-z0-9-]+\.)*?)([a-z0-9-]+)|\[[a-f0-9:]+\])$/', $host) )
			throw new Exception(__CLASS__ . ': Invalid hostname');
		if ( $ssl )
		{
			$this->ssl = true;
			$port = ( $port === 'default' ) ? 443 : $port;
		}
		else
		{
			$this->ssl = false;
			$port = ( $port === 'default' ) ? 80 : $port;
		}
		// Yes - this really does support IPv6 URLs!
		$this->host = $host;
		$this->uri = $uri;
		if ( is_int($port) && $port >= 1 && $port <= 65535 )
			$this->port = $port;
		else
			throw new Exception(__CLASS__ . ': Invalid port');
		$method = strtoupper($method);
		if ( $method == 'GET' || $method == 'POST' )
			$this->method = $method;
		else
			throw new Exception(__CLASS__ . ': Invalid request method');
			
		$newline = "\r\n";
		$php_ver = PHP_VERSION;
		$server = ( isset($_SERVER['SERVER_SOFTWARE']) ) ? "Server: {$_SERVER['SERVER_SOFTWARE']}" : "CLI";
		$this->add_header('User-Agent', "PHP/$php_ver ({$server}; automated bot request)");
	}
	
	/**
 	* Sets one or more cookies to be sent to the server.
 	* @param string or array If a string, the cookie name. If an array, associative array in the form of cookiename => cookievalue
 	* @param string or bool If a string, the cookie value. If boolean, defaults to false, param 1 should be an array, and this should not be passed.
 	*/
	
	function add_cookie($cookiename, $cookievalue = false)
	{
		if ( is_array($cookiename) && !$cookievalue )
		{
			foreach ( $cookiename as $name => $value )
			{
				$this->cookies_out[$name] = $value;
			}
		}
		else if ( is_string($cookiename) && is_string($cookievalue) )
		{
			$this->cookies_out[$cookiename] = $cookievalue;
		}
		else
		{
			throw new Exception(__METHOD__ . ': Invalid argument(s)');
		}
	}
	
	/**
 	* Sets one or more request header values.
 	* @param string or array If a string, the header name. If an array, associative array in the form of headername => headervalue
 	* @param string or bool If a string, the header value. If boolean, defaults to false, param 1 should be an array, and this should not be passed.
 	*/
	
	function add_header($headername, $headervalue = false)
	{
		if ( is_array($headername) && !$headervalue )
		{
			foreach ( $headername as $name => $value )
			{
				$this->headers[$name] = $value;
			}
		}
		else if ( is_string($headername) && is_string($headervalue) )
		{
			$this->headers[$headername] = $headervalue;
		}
		else
		{
			throw new Exception(__METHOD__ . ': Invalid argument(s)');
		}
	}
	
	/**
 	* Adds one or more values to be passed on GET.
 	* @param string or array If a string, the parameter name. If an array, associative array in the form of parametername => parametervalue
 	* @param string or bool If a string, the parameter value. If boolean, defaults to false, param 1 should be an array, and this should not be passed.
 	*/
	
	function add_get($getname, $getvalue = false)
	{
		if ( is_array($getname) && !$getvalue )
		{
			foreach ( $getname as $name => $value )
			{
				$this->parms_get[$name] = $value;
			}
		}
		else if ( is_string($getname) && is_string($getvalue) )
		{
			$this->parms_get[$getname] = $getvalue;
		}
		else
		{
			throw new Exception(__METHOD__ . ': Invalid argument(s)');
		}
	}
	
	/**
 	* Adds one or more values to be passed on POST.
 	* @param string or array If a string, the header name. If an array, associative array in the form of headername => headervalue
 	* @param string or bool If a string, the header value. If boolean, defaults to false, param 1 should be an array, and this should not be passed.
 	*/
	
	function add_post($postname, $postvalue = false)
	{
		if ( is_array($postname) && !$postvalue )
		{
			foreach ( $postname as $name => $value )
			{
				$this->parms_post[$name] = $value;
			}
		}
		else if ( is_string($postname) && is_string($postvalue) )
		{
			$this->parms_post[$postname] = $postvalue;
		}
		else
		{
			throw new Exception(__METHOD__ . ': Invalid argument(s)');
		}
	}
	
	/**
 	* Internal function to open up the socket.
 	* @access private
 	*/
	
	function _sock_open(&$connection)
	{
		// Open connection
		$ssl_prepend = ( $this->ssl ) ? 'ssl://' : '';
		$connection = fsockopen($ssl_prepend . $this->host, $this->port, $errno, $errstr);
		if ( !$connection )
			throw new Exception(__METHOD__ . ": Could not make connection"); // to {$this->host}:{$this->port}: error $errno: $errstr");
		
		// 1 = socket open
		$this->state = 1;
	}
	
	/**
 	* Internal function to actually write the request into the socket.
 	* @access private
 	*/
	
	function _write_request(&$connection, &$headers, &$cookies, &$get, &$post)
	{
		$newline = "\r\n";
		
		if ( $this->debug )
			echo '<p>Connection opened. Writing main request to socket. Raw socket data follows.</p><pre>';
		
		if ( $this->debug )
		{
			echo '<hr /><div style="white-space: nowrap;">';
			echo '<p><b>' . __CLASS__ . ': Sending request</b></p><p>Request parameters:</p>';
			echo "<p><b>Headers:</b></p><pre>$headers</pre>";
			echo "<p><b>Cookies:</b> $cookies</p>";
			echo "<p><b>GET URI:</b> " . htmlspecialchars($this->uri . $get) . "</p>";
			echo "<p><b>POST DATA:</b> " . htmlspecialchars($post) . "</p>";
			echo "<pre>";
		}
		
		$portline = ( $this->port == 80 ) ? '' : ":$this->port";
		
		$this->_fputs($connection, "{$this->method} {$this->uri}{$get} HTTP/1.1{$newline}");
		$this->_fputs($connection, "Host: {$this->host}$portline{$newline}");
		$this->_fputs($connection, $headers);
		$this->_fputs($connection, $cookies);
		
		if ( $this->method == 'POST' )
		{
			// POST-specific parameters
			$post_length = strlen($post);
			$this->_fputs($connection, "Content-type: application/x-www-form-urlencoded{$newline}");
			$this->_fputs($connection, "Content-length: {$post_length}{$newline}");
		}
		
		$this->_fputs($connection, "Connection: close{$newline}");
		$this->_fputs($connection, "{$newline}");
		
		if ( $this->method == 'POST' )
		{
			$this->_fputs($connection, $post);
		}
		
		if ( $this->debug )
			echo '</pre><p>Request written. Fetching response.</p>';
		
		// 2 = request written
		$this->state = 2;
	}
	
	/**
 	* Wrap up and close the socket. Nothing more than a call to fsockclose() except in debug mode.
 	* @access private
 	*/
	
	function sock_close(&$connection)
	{
		if ( $this->debug )
		{
			echo '<p>Response fetched. Closing connection. Response text follows.</p><pre>';
			echo htmlspecialchars($this->response);
			echo '</pre></div><hr />';
		}
		
		fclose($connection);
		$this->state = 0;
	}
	
	/**
 	* Internal function to grab the response code and status string
 	* @access string
 	*/
	
	function _parse_response_code($buffer)
	{
		// Retrieve response code and status
		$pos_newline = strpos($buffer, "\n");
		$pos_carriage_return = strpos($buffer, "\r");
		$pos_end_first_line = ( $pos_carriage_return < $pos_newline && $pos_carriage_return > 0 ) ? $pos_carriage_return : $pos_newline;
		
		// First line is in format of:
		// HTTP/1.1 ### Blah blah blah(\r?)\n
		$response_code = substr($buffer, 9, 3);
		$response_string = substr($buffer, 13, ( $pos_end_first_line - 13 ) );
		$this->response_code = intval($response_code);
		$this->response_string = $response_string;
	}
	
	/**
 	* Internal function to send the request.
 	* @access private
 	*/
	
	function _send_request()
	{
		$this->concat_headers($headers, $cookies, $get, $post);
		
		if ( $this->state < 1 )
		{
			$this->_sock_open($this->socket);
		}
		if ( $this->state < 2 )
		{
			$this->_write_request($this->socket, $headers, $cookies, $get, $post);
		}
		if ( $this->state == 2 )
		{
			$buffer = $this->_read_until_newlines($this->socket);
			$this->state = 3;
			$this->_parse_response_code($buffer);
			$this->response = $buffer;
		}
		// obey redirects
		$i = 0;
		while ( $i < 20 && $this->follow_redirects )
		{
			$incoming_headers = $this->get_response_headers_array();
			if ( !$incoming_headers )
				break;
			if ( isset($incoming_headers['Location']) )
			{
				// we've been redirected...
				$new_uri = $this->_resolve_uri($incoming_headers['Location']);
				if ( !$new_uri )
				{
					// ... bad URI, ignore Location header.
					break;
				}
				// change location
				$this->host = $new_uri['host'];
				$this->port = $new_uri['port'];
				$this->uri  = $new_uri['uri'];
				$get = '';
				
				// reset
				$this->sock_close($this->socket);
				$this->_sock_open($this->socket);
				$this->_write_request($this->socket, $headers, $cookies, $get, $post);
				$buffer = $this->_read_until_newlines($this->socket);
				$this->state = 3;
				$this->_parse_response_code($buffer);
				$this->response = $buffer;
				$i++;
			}
			else
			{
				break;
			}
		}
		if ( $i == 20 )
		{
			throw new Exception(__METHOD__ . ": Redirect trap. Request_HTTP doesn't do cookies, btw.");
		}
		
		if ( $this->state == 3 )
		{
			// Determine transfer encoding
			$is_chunked = preg_match("/Transfer-Encoding: (chunked)\r?\n/", $this->response);
			if ( preg_match("/^Content-Length: ([0-9]+)[\s]*$/mi", $this->response, $match) && !$is_chunked )
			{
				$size = intval($match[1]);
				if ( $this->debug )
				{
					echo "Pulling response using fread(), size $size\n";
				}
				$this->response .= fread($this->socket, $size);
			}
			else
			{
				if ( $this->debug )
					echo "Pulling response using chunked handler\n";
					
				$buffer = '';
				while ( !feof($this->socket) )
				{
					$part = fgets($this->socket, 1024);
					if ( $is_chunked && preg_match("/^([a-f0-9]+)\x0D\x0A$/", $part, $match) )
					{
						$chunklen = hexdec($match[1]);
						$part = ( $chunklen > 0 ) ? fread($this->socket, $chunklen) : '';
						// remove the last newline from $part
						$part = preg_replace("/\r?\n\$/", "", $part);
					}
					$buffer .= $part;
				}
				$this->response .= $buffer;
			}
		}
		$this->state = 4;
		
		$this->sock_close($this->socket);
		$this->socket = false;
	}
	
	/**
 	* Internal function to send the request but only fetch the headers. Leaves a connection open for a finish-up function.
 	* @access private
 	*/
	
	function _send_request_headers_only()
	{
		$this->concat_headers($headers, $cookies, $get, $post);
		
		if ( $this->state < 1 )
		{
			$this->_sock_open($this->socket);
		}
		if ( $this->state < 2 )
		{
			$this->_write_request($this->socket, $headers, $cookies, $get, $post);
		}
		if ( $this->state == 2 )
		{
			$buffer = $this->_read_until_newlines($this->socket);
			$this->state = 3;
			$this->_parse_response_code($buffer);
			$this->response = $buffer;
		}
	}
	
	/**
 	* Internal function to read from a socket until two consecutive newlines are hit.
 	* @access private
 	*/
	
	function _read_until_newlines($sock)
	{
		$prev_char = '';
		$prev1_char = '';
		$prev2_char = '';
		$buf = '';
		while ( !feof($sock) )
		{
			$chr = fread($sock, 1);
			$buf .= $chr;
			if ( ( $chr == "\n" && $prev_char == "\n" ) ||
 					( $chr == "\n" && $prev_char == "\r" && $prev1_char == "\n" && $prev2_char == "\r" ) )
			{
				return $buf;
			}
			$prev2_char = $prev1_char;
			$prev1_char = $prev_char;
			$prev_char = $chr;
		}
		return $buf;
	}
	
	/**
 	* Returns the response text. If the request hasn't been sent, it will be sent here.
 	* @return string
 	*/
	
	function get_response()
	{
		if ( $this->state == 4 )
			return $this->response;
		$this->_send_request();
		return $this->response;
	}
	
	/**
 	* Writes the response body to a file. This is good for conserving memory when downloading large files. If the file already exists it will be overwritten.
 	* @param string File to write to
 	* @param int Chunk size in KB to read from the socket. Optional and should only be needed in circumstances when extreme memory conservation is needed. Defaults to 768.
 	* @param int Maximum file size. Defaults to 0, which means no limit.
 	* @return bool True on success, false on failure
 	*/
	
	function write_response_to_file($file, $chunklen = 768, $max_file_size = 0)
	{
		if ( !is_writeable( dirname($file) ) || !file_exists( dirname($file) ) )
		{
			return false;
		}
		$handle = @fopen($file, 'w');
		if ( !$handle )
			return false;
		$chunklen = intval($chunklen);
		if ( $chunklen < 1 )
			return false;
		if ( $this->state == 4 )
		{
			// we already have the response, so cheat
			$response = $this->get_response_body();
			fwrite($handle, $response);
		}
		else
		{
			// read data from the socket, write it immediately, and unset to free memory
			$headers = $this->get_response_headers();
			$transferred_bytes = 0;
			$bandwidth_exceeded = false;
			// if transfer-encoding is chunked, read using chunk sizes the server specifies
			$is_chunked = preg_match("/Transfer-Encoding: (chunked)\r?\n/", $this->response);
			if ( $is_chunked )
			{
				$buffer = '';
				while ( !feof($this->socket) )
				{
					$part = fgets($this->socket, ( 1024 * $chunklen ));
					// Theoretically if the encoding is really chunked then this should always match.
					if ( $is_chunked && preg_match("/^([a-f0-9]+)\x0D\x0A$/", $part, $match) )
					{
						$chunk_length = hexdec($match[1]);
						$part = ( $chunk_length > 0 ) ? fread($this->socket, $chunk_length) : '';
						// remove the last newline from $part
						$part = preg_replace("/\r?\n\$/m", "", $part);
					}
					
					$transferred_bytes += strlen($part);
					if ( $max_file_size && $transferred_bytes > $max_file_size )
					{
						// truncate output to $max_file_size bytes
						$partlen = $max_file_size - ( $transferred_bytes - strlen($part) );
						$part = substr($part, 0, $partlen);
						$bandwidth_exceeded = true;
					}
					fwrite($handle, $part);
					if ( $bandwidth_exceeded )
					{
						break;
					}
				}
			}
			else
			{
				$first_chunk = fread($this->socket, ( 1024 * $chunklen ));
				fwrite($handle, $first_chunk);
				while ( !feof($this->socket) )
				{
					$chunk = fread($this->socket, ( 1024 * $chunklen ));
					
					$transferred_bytes += strlen($chunk);
					if ( $max_file_size && $transferred_bytes > $max_file_size )
					{
						// truncate output to $max_file_size bytes
						$partlen = $max_file_size - ( $transferred_bytes - strlen($chunk) );
						$chunk = substr($chunk, 0, $partlen);
						$bandwidth_exceeded = true;
					}
					
					fwrite($handle, $chunk);
					unset($chunk);
					
					if ( $bandwidth_exceeded )
					{
						break;
					}
				}
			}
		}
		fclose($handle);
		// close socket and reset state, since we haven't cached the response
		$this->sock_close($this->socket);
		$this->state = 0;
		return ($bandwidth_exceeded) ? false : true;
	}
	
	/**
 	* Resolves, based on current settings and URI, a URI string to an array consisting of a host, port, and new URI. Returns false on error.
 	* @param string
 	* @return array
 	*/
	
	function _resolve_uri($uri)
	{
		// long ass regexp w00t
		if ( !preg_match('#^(?:https?://((?:(?:[a-z0-9-]+\.)*)(?:[a-z0-9-]+)|\[[a-f0-9:]+\])(?::([0-9]+))?)?(/)(.*)$#i', $uri, $match) )
		{
			// bad target URI
			return false;
		}
		$hostpart = $match[1];
		if ( empty($hostpart) )
		{
			// use existing host
			$host = $this->host;
			$port = $this->port;
		}
		else
		{
			$host = $match[1];
			$port = empty($match[2]) ? 80 : intval($match[2]);
		}
		// is this an absolute URI, or relative?
		if ( empty($match[3]) )
		{
			// relative
			$uri = dirname($this->uri) . $match[4];
		}
		else
		{
			// absolute
			$uri = '/' . $match[4];
		}
		return array(
				'host' => $host,
				'port' => $port,
				'uri'  => $uri
			);
	}
	
	/**
 	* Returns only the response headers.
 	* @return string
 	*/
	
	function get_response_headers()
	{
		if ( $this->state == 3 )
		{
			return $this->response;
		}
		else if ( $this->state == 4 )
		{
			$pos_end = strpos($this->response, "\r\n\r\n");
			if ( empty($pos_end) )
			{
				$pos_end = strpos($this->response, "\n\n");
			}
			$data = substr($this->response, 0, $pos_end);
			return $data;
		}
		else
		{
			$this->_send_request_headers_only();
			return $this->response;
		}
	}
	
	/**
 	* Returns only the response headers, as an associative array.
 	* @return array
 	*/
	
	function get_response_headers_array()
	{
		$data = $this->get_response_headers();
		preg_match_all("/(^|\n)([A-z0-9_-]+?): (.+?)(\r|\n|\$)/", $data, $matches);
		$headers = array();
		for ( $i = 0; $i < count($matches[0]); $i++ )
		{
			$headers[ $matches[2][$i] ] = $matches[3][$i];
		}
		return $headers;
	}
	
	/**
 	* Returns only the body (not the headers) of the response. If the request hasn't been sent, it will be sent here.
 	* @return string
 	*/
	
	function get_response_body()
	{
		$data = $this->get_response();
		$pos_start = strpos($data, "\r\n\r\n") + 4;
		if ( $pos_start == 4 )
		{
			$pos_start = strpos($data, "\n\n") + 4;
		}
		$data = substr($data, $pos_start);
		return $data;
	}
	
	/**
 	* Returns all cookies requested to be set by the server as an associative array. If the request hasn't been sent, it will be sent here.
 	* @return array
 	*/
	
	function get_cookies()
	{
		$data = $this->get_response();
		$data = str_replace("\r\n", "\n", $data);
		$pos = strpos($data, "\n\n");
		$headers = substr($data, 0, $pos);
		preg_match_all("/Set-Cookie: ([a-z0-9_]+)=([^;]+);( expires=([^;]+);)?( path=(.*?))?\n/", $headers, $cookiematch);
		if ( count($cookiematch[0]) < 1 )
			return array();
		$cookies = array();
		foreach ( $cookiematch[0] as $i => $cookie )
		{
			$cookies[$cookiematch[1][$i]] = $cookiematch[2][$i];
		}
		return $cookies;
	}
	
	/**
 	* Internal method to write data to a socket with debugging possibility.
 	* @access private
 	*/
	
	function _fputs($socket, $data)
	{
		if ( $this->debug )
			echo htmlspecialchars($data);
		
		return fputs($socket, $data);
	}
	
	/**
 	* Internal function to stringify cookies, headers, get, and post.
 	* @access private
 	*/
	
	function concat_headers(&$headers, &$cookies, &$get, &$post)
	{
		$headers = '';
		$cookies = '';
		foreach ( $this->headers as $name => $value )
		{
			$value = str_replace('\\n', '\\\\n', $value);
			$value = str_replace("\n", '\\n', $value);
			$headers .= "$name: $value\r\n";
		}
		unset($value);
		if ( count($this->cookies_out) > 0 )
		{
			$i = 0;
			$cookie_header = 'Cookie: ';
			foreach ( $this->cookies_out as $name => $value )
			{
				$i++;
				if ( $i > 1 )
					$cookie_header .= '; ';
				$value = str_replace(';', rawurlencode(';'), $value);
				$value = str_replace('\\n', '\\\\n', $value);
				$value = str_replace("\n", '\\n', $value);
				$cookie_header .= "$name=$value";
			}
			$cookie_header .= "\r\n";
			$cookies = $cookie_header;
			unset($value, $cookie_header);
		}
		if ( count($this->parms_get) > 0 )
		{
			$get = '?';
			$i = 0;
			foreach ( $this->parms_get as $name => $value )
			{
				$i++;
				if ( $i > 1 )
					$get .= '&';
				$value = urlencode($value);
				if ( !empty($value) || is_string($value) )
					$get .= "$name=$value";
				else
					$get .= "$name";
			}
		}
		if ( count($this->parms_post) > 0 )
		{
			$post = '';
			$i = 0;
			foreach ( $this->parms_post as $name => $value )
			{
				$i++;
				if ( $i > 1 )
					$post .= '&';
				$value = urlencode($value);
				$post .= "$name=$value";
			}
		}
	}
	
}

