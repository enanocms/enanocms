<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * Installation package
 * sql_parse.php - SQL query splitter and templater
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Parses a full file of SQL into individual queries. Also includes substitution (template) functions.
 * @package Enano
 * @subpackage Installer
 * @author Dan Fuhry
 */

class SQL_Parser
{
  /**
   * The SQL to be parsed.
   * @var string
   * @access private
   */
  
  private $sql_string;
  
  /**
   * Parsed SQL array
   * @var array
   * @access private
   */
  
  private $sql_array;
  
  /**
   * Template variables.
   * @var array
   * @access private
   */
  
  private $tpl_strings;
  
  /**
   * Constructor.
   * @param string If this contains newlines, it will be treated as the target SQL. If not, will be treated as a filename.
   */
  
  public function __construct($sql)
  {
    if ( strpos($sql, "\n") )
    {
      $this->sql_string = $sql;
    }
    else
    {
      if ( file_exists($sql) )
      {
        $this->sql_string = @file_get_contents($sql);
        if ( empty($this->sql_string) )
        {
          throw new Exception('SQL file is blank or permissions are bad');
        }
      }
      else
      {
        throw new Exception('SQL file doesn\'t exist');
      }
    }
    $this->sql_array = false;
    $this->tpl_strings = array();
  }
  
  /**
   * Sets template variables.
   * @param array Associative array of template variables to assign
   */
  
  public function assign_vars($vars)
  {
    if ( !is_array($vars) )
      return false;
    $this->tpl_strings = array_merge($this->tpl_strings, $vars);
  }
  
  /**
   * Internal function to parse the SQL.
   * @access private
   */
  
  private function parse_sql()
  {
    $this->sql_array = $this->sql_string;
    foreach ( $this->tpl_strings as $key => $value )
    {
      $this->sql_array = str_replace("{{{$key}}}", $value, $this->sql_array);
    }
    
    // Build an array of queries
    $this->sql_array = explode("\n", $this->sql_array);
    
    foreach ( $this->sql_array as $i => $sql )
    {
      $query =& $this->sql_array[$i];
      $t = trim($query);
      if ( empty($t) || preg_match('/^(\#|--)/i', $t) )
      {
        unset($this->sql_array[$i]);
        unset($query);
      }
    }
    unset($query);
    
    $this->sql_array = array_values($this->sql_array);
    $this->sql_array = implode("\n", $this->sql_array);
    $this->sql_array = explode(";\n", $this->sql_array);
    
    foreach ( $this->sql_array as $i => $sql )
    {
      $query =& $this->sql_array[$i];
      if ( substr($query, ( strlen($query) - 1 ), 1 ) != ';' )
      {
        $query .= ';';
      }
    }
    unset($query);
  }
  
  /**
   * Returns the parsed array of SQL queries.
   * @param bool Optional. Defaults to false. If true, a parse is performed even if it already happened.
   * @return array
   */
  
  public function parse($force_reparse = false)
  {
    if ( !$this->sql_array || $force_reparse )
      $this->parse_sql();
    return $this->sql_array;
  }
}

?>
