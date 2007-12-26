<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Language class - processes, stores, and retrieves language strings.
 * @package Enano
 * @subpackage Localization
 * @copyright 2007 Dan Fuhry
 * @license GNU General Public License
 */

class Language
{
  
  /**
   * The numerical ID of the loaded language.
   * @var int
   */
  
  var $lang_id;
  
  /**
   * The ISO-639-3 code for the loaded language. This should be grabbed directly from the database.
   * @var string
   */
  
  var $lang_code;

  /**
   * Used to track when a language was last changed, to allow browsers to cache language data
   * @var int
   */
  
  var $lang_timestamp;
  
  /**
   * Will be an object that holds an instance of the class configured with the site's default language. Only instanciated when needed.
   * @var object
   */
  
  var $default;
  
  /**
   * The list of loaded strings.
   * @var array
   * @access private
   */
  
  var $strings = array();
  
  /**
   * Constructor.
   * @param int|string Language ID or code to load.
   */
  
  function __construct($lang)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( defined('IN_ENANO_INSTALL') )
    {
      // special case for the Enano installer: it will load its own strings from a JSON file and just use this API for fetching and templatizing them.
      $this->lang_id   = 1;
      $this->lang_code = $lang;
      return true;
    }
    if ( is_string($lang) )
    {
      $sql_col = 'lang_code="' . $db->escape($lang) . '"';
    }
    else if ( is_int($lang) )
    {
      $sql_col = 'lang_id=' . $lang . '';
    }
    else
    {
      $db->_die('lang.php - attempting to pass invalid value to constructor');
    }
    
    $lang_default = ( $x = getConfig('default_language') ) ? intval($x) : '\'def\'';
    $q = $db->sql_query("SELECT lang_id, lang_code, last_changed, ( lang_id = $lang_default ) AS is_default FROM " . table_prefix . "language WHERE $sql_col OR lang_id = $lang_default ORDER BY is_default DESC LIMIT 1;");
    
    if ( !$q )
      $db->_die('lang.php - main select query');
    
    if ( $db->numrows() < 1 )
      $db->_die('lang.php - There are no languages installed');
    
    $row = $db->fetchrow();
    
    $this->lang_id   = intval( $row['lang_id'] );
    $this->lang_code = $row['lang_code'];
    $this->lang_timestamp = $row['last_changed'];
  }
  
  /**
   * PHP 4 constructor.
   * @param int|string Language ID or code to load.
   */
  
  function Language($lang)
  {
    $this->__construct($lang);
  }
  
  /**
   * Fetches language strings from the database, or a cache file if it's available.
   * @param bool If true (default), allows the cache to be used.
   */
  
  function fetch($allow_cache = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $lang_file = ENANO_ROOT . "/cache/lang_{$this->lang_id}.php";
    // Attempt to load the strings from a cache file
    if ( file_exists($lang_file) && $allow_cache )
    {
      // Yay! found it
      $this->load_cache_file($lang_file);
    }
    else
    {
      // No cache file - select and retrieve from the database
      $q = $db->sql_unbuffered_query("SELECT string_category, string_name, string_content FROM " . table_prefix . "language_strings WHERE lang_id = {$this->lang_id};");
      if ( !$q )
        $db->_die('lang.php - selecting language string data');
      if ( $row = $db->fetchrow() )
      {
        $strings = array();
        do
        {
          $cat =& $row['string_category'];
          if ( !is_array($strings[$cat]) )
          {
            $strings[$cat] = array();
          }
          $strings[$cat][ $row['string_name'] ] = $row['string_content'];
        }
        while ( $row = $db->fetchrow() );
        // all done fetching
        $this->merge($strings);
      }
      else
      {
        if ( !defined('ENANO_ALLOW_LOAD_NOLANG') )
          $db->_die('lang.php - No strings for language ' . $this->lang_code);
      }
    }
  }
  
  /**
   * Loads a file from the disk cache (treated as PHP) and merges it into RAM.
   * @param string File to load
   */
  
  function load_cache_file($file)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // We're using eval() here because it makes handling scope easier.
    
    if ( !file_exists($file) )
      $db->_die('lang.php - requested cache file doesn\'t exist');
    
    $contents = file_get_contents($file);
    $contents = preg_replace('/([\s]*)<\?php/', '', $contents);
    
    @eval($contents);
    
    if ( !isset($lang_cache) || ( isset($lang_cache) && !is_array($lang_cache) ) )
      $db->_die('lang.php - the cache file is invalid (didn\'t set $lang_cache as an array)');
    
    $this->merge($lang_cache);
  }
  
  /**
   * Loads a JSON language file and parses the strings into RAM. Will use the cache if possible, but stays far away from the database,
   * which we assume doesn't exist yet.
   */
  
  function load_file($file)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !file_exists($file) )
      $db->_die('lang.php - requested JSON file doesn\'t exist');
    
    $contents = trim(@file_get_contents($file));
    if ( empty($contents) )
      $db->_die('lang.php - empty language file...');
    
    // Trim off all text before and after the starting and ending braces
    $contents = preg_replace('/^([^{]+)\{/', '{', $contents);
    $contents = preg_replace('/\}([^}]+)$/', '}', $contents);
    $contents = trim($contents);
    
    if ( empty($contents) )
      $db->_die('lang.php - no meat to the language file...');
    
    $checksum = md5($contents);
    if ( file_exists("./cache/lang_json_{$checksum}.php") )
    {
      $this->load_cache_file("./cache/lang_json_{$checksum}.php");
    }
    else
    {
      $langdata = enano_json_decode($contents);
    
      if ( !is_array($langdata) )
        $db->_die('lang.php - invalid language file');
      
      if ( !isset($langdata['categories']) || !isset($langdata['strings']) )
        $db->_die('lang.php - language file does not contain the proper items');
      
      $this->merge($langdata['strings']);
      
      $lang_file = "./cache/lang_json_{$checksum}.php";
      
      $handle = @fopen($lang_file, 'w');
      if ( !$handle )
        // Couldn't open the file. Silently fail and let the strings come from RAM.
        return false;
        
      // The file's open, that means we should be good.
      fwrite($handle, '<?php
// This file was generated automatically by Enano. You should not edit this file because any changes you make
// to it will not be visible in the ACP and all changes will be lost upon any changes to strings in the admin panel.

$lang_cache = ');
      
      $exported = $this->var_export_string($this->strings);
      if ( empty($exported) )
        // Ehh, that's not good
        $db->_die('lang.php - load_file(): var_export_string() failed');
      
      fwrite($handle, $exported . '; ?>');
      
      // Clean up
      unset($exported, $langdata);
      
      // Done =)
      fclose($handle);
    }
  }
  
  /**
   * Merges a standard language assoc array ($arr[cat][stringid]) with the master in RAM.
   * @param array
   */
  
  function merge($strings)
  {
    // This is stupidly simple.
    foreach ( $strings as $cat_id => $contents )
    {
      if ( !isset($this->strings[$cat_id]) || ( isset($this->strings[$cat_id]) && !is_array($this->strings[$cat_id]) ) )
        $this->strings[$cat_id] = array();
      foreach ( $contents as $string_id => $string )
      {
        $this->strings[$cat_id][$string_id] = $string;
      }
    }
  }
  
  /**
   * Imports a JSON-format language file into the database and merges with current strings.
   * @param string Path to the JSON file to load
   */
  
  function import($file)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !file_exists($file) )
      $db->_die('lang.php - can\'t import language file: string file doesn\'t exist');
    
    if ( $this->lang_id == 0 )
      $db->_die('lang.php - BUG: trying to perform import when $lang->lang_id == 0');
    
    $contents = trim(@file_get_contents($file));
    
    if ( empty($contents) )
      $db->_die('lang.php - can\'t load the contents of the language file');
    
    // Trim off all text before and after the starting and ending braces
    $contents = preg_replace('/^([^{]+)\{/', '{', $contents);
    $contents = preg_replace('/\}([^}]+)$/', '}', $contents);
    
    // Correct syntax to be nice to the json parser
    
    // eliminate comments
    $contents = preg_replace(array(
            // eliminate single line comments in '// ...' form
            '#^\s*//(.+)$#m',
            // eliminate multi-line comments in '/* ... */' form, at start of string
            '#^\s*/\*(.+)\*/#Us',
            // eliminate multi-line comments in '/* ... */' form, at end of string
            '#/\*(.+)\*/\s*$#Us'
          ), '', $contents);
    
    $contents = preg_replace('/([,\{\[])([\s]*?)([a-z0-9_]+)([\s]*?):/', '\\1\\2"\\3" :', $contents);
    
    try
    {
      $langdata = enano_json_decode($contents);
    }
    catch(Zend_Json_Exception $e)
    {
      $db->_die('lang.php - Exception caught by JSON parser');
      exit;
    }
    
    if ( !is_array($langdata) )
    {
      $db->_die('lang.php - invalid or non-well-formed language file');
    }
    
    if ( !isset($langdata['categories']) || !isset($langdata['strings']) )
      $db->_die('lang.php - language file does not contain the proper items');
    
    $insert_list = array();
    $delete_list = array();
    
    foreach ( $langdata['categories'] as $category )
    {
      if ( isset($langdata['strings'][$category]) )
      {
        foreach ( $langdata['strings'][$category] as $string_name => $string_value )
        {
          $string_name = $db->escape($string_name);
          $string_value = $db->escape($string_value);
          $category_name = $db->escape($category);
          $insert_list[] = "({$this->lang_id}, '$category_name', '$string_name', '$string_value')";
          $delete_list[] = "( lang_id = {$this->lang_id} AND string_category = '$category_name' AND string_name = '$string_name' )";
        }
      }
    }
    
    $delete_list = implode(" OR\n  ", $delete_list);
    $sql = "DELETE FROM " . table_prefix . "language_strings WHERE $delete_list;";
    
    // Free some memory
    unset($delete_list);
    
    // Run the query
    $q = $db->sql_query($sql);
    if ( !$q )
      $db->_die('lang.php - couldn\'t kill off them old strings');
    
    $insert_list = implode(",\n  ", $insert_list);
    $sql = "INSERT INTO " . table_prefix . "language_strings(lang_id, string_category, string_name, string_content) VALUES\n  $insert_list;";
    
    // Free some memory
    unset($insert_list);
    
    // Run the query
    $q = $db->sql_query($sql);
    if ( !$q )
      $db->_die('lang.php - couldn\'t insert strings in import()');
    
    // YAY! done!
    // This will regenerate the cache file if possible.
    $this->regen_caches();
  }
  
  /**
   * Refetches the strings and writes out the cache file.
   */
  
  function regen_caches()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $lang_file = ENANO_ROOT . "/cache/lang_{$this->lang_id}.php";
    
    // Refresh the strings in RAM to the latest copies in the DB
    $this->fetch(false);
    
    $handle = @fopen($lang_file, 'w');
    if ( !$handle )
      // Couldn't open the file. Silently fail and let the strings come from the database.
      return false;
      
    // The file's open, that means we should be good.
    fwrite($handle, '<?php
// This file was generated automatically by Enano. You should not edit this file because any changes you make
// to it will not be visible in the ACP and all changes will be lost upon any changes to strings in the admin panel.

$lang_cache = ');
    
    $exported = $this->var_export_string($this->strings);
    if ( empty($exported) )
      // Ehh, that's not good
      $db->_die('lang.php - var_export_string() failed');
    
    fwrite($handle, $exported . '; ?>');
    
    // Update timestamp in database
    $q = $db->sql_query('UPDATE ' . table_prefix . 'language SET last_changed = ' . time() . ' WHERE lang_id = ' . $this->lang_id . ';');
    if ( !$q )
      $db->_die('lang.php - updating timestamp on language');
    
    // Done =)
    fclose($handle);
  }
  
  /**
   * Calls var_export() on whatever, and returns the function's output.
   * @param mixed Whatever you want var_exported. Usually an array.
   * @return string
   */
  
  function var_export_string($val)
  {
    ob_start();
    var_export($val);
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
  }
  
  /**
   * Fetches a language string from the cache in RAM. If it isn't there, it will call fetch() again and then try. If it still can't find it, it will ask for the string
   * in the default language. If even then the string can't be found, this function will return what was passed to it.
   *
   * This will also templatize strings. If a string contains variables in the format %foo%, you may specify the second parameter as an associative array in the format
   * of 'foo' => 'foo substitute'.
   *
   * @param string ID of the string to fetch. This will always be in the format of category_stringid.
   * @param array Optional. Associative array of substitutions.
   * @return string
   */
  
  function get($string_id, $substitutions = false)
  {
    // Extract the category and string ID
    $category = substr($string_id, 0, ( strpos($string_id, '_') ));
    $string_name = substr($string_id, ( strpos($string_id, '_') + 1 ));
    $found = false;
    if ( isset($this->strings[$category]) && isset($this->strings[$category][$string_name]) )
    {
      $found = true;
      $string = $this->strings[$category][$string_name];
    }
    if ( !$found )
    {
      // Ehh, the string wasn't found. Rerun fetch() and try again.
      if ( defined('IN_ENANO_INSTALL') )
      {
        return $string_id;
      }
      $this->fetch();
      if ( isset($this->strings[$category]) && isset($this->strings[$category][$string_name]) )
      {
        $found = true;
        $string = $this->strings[$category][$string_name];
      }
      if ( !$found )
      {
        // STILL not found. Check the default language.
        $lang_default = ( $x = getConfig('default_language') ) ? intval($x) : $this->lang_id;
        if ( $lang_default != $this->lang_id )
        {
          if ( !is_object($this->default) )
            $this->default = new Language($lang_default);
          return $this->default->get($string_id, $substitutions);
        }
      }
    }
    if ( !$found )
    {
      // Alright, it's nowhere. Return the input, grumble grumble...
      return $string_id;
    }
    // Found it!
    // Perform substitutions.
    // if ( is_array($substitutions) )
    //   die('<pre>' . print_r($substitutions, true) . '</pre>');
    if ( !is_array($substitutions) )
      $substitutions = array();
    return $this->substitute($string, $substitutions);
  }
  
  /**
   * Processes substitutions.
   * @param string
   * @param array
   * @return string
   */
  
  function substitute($string, $subs)
  {
    preg_match_all('/%this\.([a-z0-9_]+)%/', $string, $matches);
    if ( count($matches[0]) > 0 )
    {
      foreach ( $matches[1] as $i => $string_id )
      {
        $result = $this->get($string_id);
        $string = str_replace($matches[0][$i], $result, $string);
      }
    }
    preg_match_all('/%config\.([a-z0-9_]+)%/', $string, $matches);
    if ( count($matches[0]) > 0 )
    {
      foreach ( $matches[1] as $i => $string_id )
      {
        $result = getConfig($string_id);
        $string = str_replace($matches[0][$i], $result, $string);
      }
    }
    foreach ( $subs as $key => $value )
    {
      $subs[$key] = strval($value);
      $string = str_replace("%{$key}%", "{$subs[$key]}", $string);
    }
    return "{$string}*";
  }
  
} // class Language

?>
