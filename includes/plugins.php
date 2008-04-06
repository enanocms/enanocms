<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.3 (Caoineag alpha 3)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
/**
 * Class used to handle and process plugin requests and loading. Singleton.
 * @package Enano
 * @author Dan Fuhry <dan@enanocms.org>
 * @copyright (C) 2006-2008 Enano Project
 * @license GNU General Public License <http://enanocms.org/Special:GNU_General_Public_License>
 */

class pluginLoader {
  
  /**
   * The list of hooks registered.
   * @var array
   * @access private
   */
  
  var $hook_list;
  
  /**
   * The list of plugins that should be loaded. Used only by common.php.
   * @var array
   * @access private
   */
  
  var $load_list;
  
  /**
   * The list of plugins that are loaded currently. This is only used by the loaded() method which in turn is
   * used by template files with the <!-- IFPLUGIN --> special tag.
   * @var array
   * @access private
   */
  
  var $loaded_plugins;
  
  /**
   * The list of plugins that are always loaded because they're part of the Enano core. This cannot be modified
   * by any external code because user plugins are loaded after the load_list is calculated. Can be useful in
   * alternative administration panel frameworks that need the list of system plugins.
   * @var array
   */
  
  var $system_plugins = Array('SpecialUserFuncs.php','SpecialUserPrefs.php','SpecialPageFuncs.php','SpecialAdmin.php','SpecialCSS.php','SpecialUpdownload.php','SpecialSearch.php','PrivateMessages.php','SpecialGroups.php', 'SpecialRecentChanges.php');
  
  /**
   * Name kept for compatibility. Effectively a constructor. Calculates the list of plugins that should be loaded
   * and puts that list in the $load_list property. Plugin developers have absolutely no use for this whatsoever.
   */
  
  function loadAll() 
  {
    $dir = ENANO_ROOT.'/plugins/';
    
    $this->load_list = Array();
    
    $plugins = Array();
    
    // Open a known directory, and proceed to read its contents
    
    if (is_dir($dir))
    {
      if ($dh = opendir($dir))
      {
        while (($file = readdir($dh)) !== false)
        {
          if(preg_match('#^(.*?)\.php$#is', $file))
          {
            if(getConfig('plugin_'.$file) == '1' || in_array($file, $this->system_plugins))
            {
              $this->load_list[] = $dir . $file;
              $plugid = substr($file, 0, strlen($file)-4);
              $f = @file_get_contents($dir . $file);
              if ( empty($f) )
                continue;
              $f = explode("\n", $f);
              $f = array_slice($f, 2, 7);
              $f[0] = substr($f[0], 13);
              $f[1] = substr($f[1], 12);
              $f[2] = substr($f[2], 13);
              $f[3] = substr($f[3], 8 );
              $f[4] = substr($f[4], 9 );
              $f[5] = substr($f[5], 12);
              $plugins[$plugid] = Array();
              $plugins[$plugid]['name'] = $f[0];
              $plugins[$plugid]['uri']  = $f[1];
              $plugins[$plugid]['desc'] = $f[2];
              $plugins[$plugid]['auth'] = $f[3];
              $plugins[$plugid]['vers'] = $f[4];
              $plugins[$plugid]['aweb'] = $f[5];
            }
          }
        }
        closedir($dh);
      }
    }
    $this->loaded_plugins = $plugins;
    //die('<pre>'.htmlspecialchars(print_r($plugins, true)).'</pre>');
  }
  
  /**
   * Name kept for compatibility. This method is used to add a new hook into the code somewhere. Plugins are encouraged
   * to set hooks and hook into other plugins in a fail-safe way, this encourages reuse of code. Returns an array, whose
   * values should be eval'ed.
   * @example <code>
   $code = $plugins->setHook('my_hook_name');
   foreach ( $code as $cmd )
   {
     eval($cmd);
   }
   </code>
   * @param string The name of the hook.
   * @param array Deprecated.
   */
  
  function setHook($name, $opts = Array()) {
    if(isset($this->hook_list[$name]) && is_array($this->hook_list[$name]))
    {
      return array(implode("\n", $this->hook_list[$name]));
    }
    else
    {
      return Array();
    }
  }
  
  /**
   * Attaches to a hook effectively scheduling some code to be run at that point. You should try to keep hooks clean by
   * making a function that has variables that need to be modified passed by reference.
   * @example Simple example: <code>
   $plugins->attachHook('render_wikiformat_pre', '$text = str_replace("Goodbye, Mr. Chips", "Hello, Mr. Carrots", $text);');
   </code>
   * @example More complicated example: <code>
   $plugins->attachHook('render_wikiformat_pre', 'myplugin_parser_ext($text);');
   
   // Notice that $text is passed by reference.
   function myplugin_parser_ext(&$text)
   {
     $text = str_replace("Goodbye, Mr. Chips", "Hello, Mr. Carrots", $text);
   }
   </code>
   */
  
  function attachHook($name, $code) {
    if(!isset($this->hook_list[$name]))
    {
      $this->hook_list[$name] = Array();
    }
    $this->hook_list[$name][] = $code;
  }
  
  /**
   * Tell whether a plugin is loaded or not.
   * @param string The filename of the plugin
   * @return bool
   */
  
  function loaded($plugid)
  {
    return isset( $this->loaded_plugins[$plugid] );
  }
  
  /**
   * Parses all special comment blocks in a plugin and returns an array in the format:
   <code>
   array(
       0 => array(
           'block' => 'upgrade',
           // parsed from the block's parameters section
             'release_from' => '1.0b1',
             'release_to' => '1.0b2',
           'value' => 'foo'
         ),
       1 => array(
           ...
         )
     );
   </code>
   * @param string Path to plugin file
   * @param string Optional. The type of block to fetch. If this is specified, only the block type specified will be read, all others will be discarded.
   * @return array
   */
  
  public static function parse_plugin_blocks($file, $type = false)
  {
    if ( !file_exists($file) )
    {
      return array();
    }
    $blocks = array();
    $contents = @file_get_contents($file);
    if ( empty($contents) )
    {
      return array();
    }
    
    $regexp = '#^/\*\*!([a-z0-9_]+)'  // block header and type
            . '(([\s]+[a-z0-9_]+[\s]*=[\s]*".+?"[\s]*;)*)' // parameters
            . '[\s]*\*\*' . "\n"      // spacing and header close
            . '([\w\W]+?)' . "\n"     // value
            . '\*\*!\*/'              // closing comment
            . '#m';
            
    // Match out all blocks
    
    $results = preg_match_all($regexp, $contents, $blocks);
    
    $return = array();
    foreach ( $blocks[0] as $i => $_ )
    {
      if ( is_string($type) && $blocks[1][$i] !== $type )
        continue;
      
      $value =& $blocks[4][$i];
      // parse includes
      preg_match_all('/^!include [\'"]?(.+?)[\'"]?$/m', $value, $includes);
      foreach ( $includes[0] as $i => $replace )
      {
        $filename = ENANO_ROOT . '/' . $includes[1][$i];
        if ( @file_exists( $filename ) && @is_readable( $filename ) )
        {
          $contents = @file_get_contents($filename);
          $value = str_replace_once($replace, $contents, $value);
        }
      }
      
      $el = self::parse_vars($blocks[2][$i]);
      $el['block'] = $blocks[1][$i];
      $el['value'] = $value;
      $return[] = $el;
    }
    
    return $return;
  }
  
  private static function parse_vars($var_block)
  {
    preg_match_all('/[\s]+([a-z0-9_]+)[\s]*=[\s]*"(.+?)";/', $var_block, $matches);
    $return = array();
    foreach ( $matches[0] as $i => $_ )
    {
      $return[ $matches[1][$i] ] = $matches[2][$i];
    }
    return $return;
  }
}

?>
