<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 * captcha.php - visual confirmation system used during registration
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * The base class for CAPTCHA engines.
 * @package Enano
 * @subpackage User management
 * @copyright 2008 Dan Fuhry
 */
 
class captcha_base
{
  
  /**
   * Our session ID
   * @var string
   */
  
  private $session_id;
  
  /**
   * Our saved session data
   * @var array
   */
  
  private $session_data;
  
  /**
   * The confirmation code we're generating.
   * @var string
   */
  
  private $code = '';
  
  /**
   * Numerical ID (primary key) for our session
   * @var int
   */
  
  private $id = 0;
  
  /**
   * Constructor.
   * @param string Session ID for captcha
   */
  
  function __construct($session_id, $row = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !preg_match('/^[a-f0-9]{32}([a-z0-9]{8})?$/', $session_id) )
    {
      throw new Exception('Invalid session ID');
    }
    $this->session_id = $session_id;
    // If we weren't supplied with session info, retreive it
    if ( !is_array($row) )
    {
      $q = $db->sql_query('SELECT code_id, code, session_data FROM ' . table_prefix . "captcha WHERE session_id = '$session_id';");
      if ( !$q )
        $db->_die();
      $row = $db->fetchrow();
      $row['code_id'] = intval($row['code_id']);
      $db->free_result();
    }
    if ( !isset($row['code']) || !isset($row['session_data']) || !is_int(@$row['code_id']) )
    {
      throw new Exception('Row doesn\'t contain what we need (code and session_data)');
    }
    $this->session_data = ( is_array($x = @unserialize($row['session_data'])) ) ? $x : array();
    $this->code = $row['code'];
    $this->id = $row['code_id'];
    
    // run any custom init functions
    if ( function_exists(array($this, 'construct_hook')) )
      $this->construct_hook();
  }
  
  /**
   * Retrieves a key from the session data set
   * @param int|string Key to fetch
   * @param mixed Default value for key
   * @return mixed
   */
   
  function session_fetch($key, $default = false)
  {
    return ( isset($this->session_data[$key]) ) ? $this->session_data[$key] : $default;
  }
  
  /**
   * Stores a value in the session's data set. Change must be committed using $captcha->session_commit()
   * @param int|string Name of key
   * @param mixed Value - can be an array, string, int, or double, but probably not objects :-)
   */
  
  function session_store($key, $value)
  {
    $this->session_data[$key] = $value;
  }
  
  /**
   * Commits changes to the session data set to the database.
   */
  
  function session_commit()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $session_data = serialize($this->session_data);
    $session_data = $db->escape($session_data);
    $code = $db->escape($this->code);
    
    $q = $db->sql_query('UPDATE ' . table_prefix . "captcha SET code = '$code', session_data = '$session_data' WHERE code_id = {$this->id};");
    if ( !$q )
      $db->_die();
  }
  
  /**
   * Changes the confirmation code
   * @param string New string
   */
  
  function set_code($code)
  {
    if ( !is_string($code) )
      return false;
    
    $this->code = $code;
  }
  
  /**
   * Returns the confirmation code
   * @return string
   */
  
  function get_code()
  {
    return $this->code;
  }
  
}

/**
 * Returns a new captcha object
 * @param string Session ID
 * @param string Optional - engine to load
 * @param array Optional row to send to the captcha engine
 */

function captcha_object($session_id, $engine = false, $row = false)
{
  static $singletons = array();
  if ( !$engine )
  {
    $engine = getConfig('captcha_engine');
    if ( !$engine )
    {
      $engine = 'freecap';
    }
  }
  if( !extension_loaded("gd") || !function_exists("gd_info") || !function_exists('imagettftext') || !function_exists('imagepng') || !function_exists('imagecreatefromjpeg') )
  {
    $engine = 'failsafe';
  }
  if ( !class_exists("captcha_engine_$engine") )
  {
    require_once ENANO_ROOT . "/includes/captcha/engine_{$engine}.php";
  }
  if ( !class_exists("captcha_engine_$engine") )
  {
    throw new Exception("Expected but couldn't find class for captcha engine: captcha_engine_$engine");
  }
  $class = "captcha_engine_$engine";
  return new $class($session_id, $row);
}

?>
