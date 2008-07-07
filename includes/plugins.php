<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
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
    global $db, $session, $paths, $template, $plugins; // Common objects
    $GLOBALS['plugins_cache'] = array();
    
    // if we're in an upgrade, just skip this step
    if ( defined('IN_ENANO_UPGRADE') )
    {
      $this->load_list = array();
      return false;
    }
    
    $dir = ENANO_ROOT.'/plugins/';
    
    $this->load_list = $this->system_plugins;
    $q = $db->sql_query('SELECT plugin_filename, plugin_version FROM ' . table_prefix . 'plugins WHERE plugin_flags & ~' . PLUGIN_DISABLED . ' = plugin_flags;');
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      $this->load_list[] = $row['plugin_filename'];
    }
    
    $this->loaded_plugins = $this->get_plugin_list($this->load_list);
    
    // check for out-of-date plugins
    foreach ( $this->load_list as $i => $plugin )
    {
      if ( in_array($plugin, $this->system_plugins) )
        continue;
      if ( $this->loaded_plugins[$plugin]['status'] & PLUGIN_OUTOFDATE )
      {
        // it's out of date, don't load
        unset($this->load_list[$i]);
        unset($this->loaded_plugins[$plugin]);
      }
    }
    
    $this->load_list = array_unique($this->load_list);
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
  
  /**
   * Reads all plugins in the filesystem and cross-references them with the database, providing a very complete summary of plugins
   * on the site.
   * @param array If specified, will restrict scanned files to this list. Defaults to null, which means all PHP files will be scanned.
   * @param bool If true, allows using cached information. Defaults to true.
   * @return array
   */
  
  function get_plugin_list($restrict = null, $use_cache = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Scan all plugins
    $plugin_list = array();
    $ta = 0;
    // won't load twice (failsafe automatic skip)
    $this->load_plugins_cache();
    if ( $use_cache )
    {
      global $plugins_cache;
    }
    else
    {
      // blank array - effectively skips importing the cache
      $plugins_cache = array();
    }
    
    if ( $dirh = @opendir( ENANO_ROOT . '/plugins' ) )
    {
      while ( $dh = @readdir($dirh) )
      {
        if ( !preg_match('/\.php$/i', $dh) )
          continue;
        
        if ( is_array($restrict) )
          if ( !in_array($dh, $restrict) )
            continue;
          
        // it's a PHP file, attempt to read metadata
        $fullpath = ENANO_ROOT . "/plugins/$dh";
        $plugin_meta = $this->get_plugin_info($fullpath, $use_cache);
        
        if ( is_array($plugin_meta) )
        {
          // all checks passed
          $plugin_list[$dh] = $plugin_meta;
        }
      }
    }
    // gather info about installed plugins
    $q = $db->sql_query('SELECT plugin_id, plugin_filename, plugin_version, plugin_flags FROM ' . table_prefix . 'plugins;');
    if ( !$q )
      $db->_die();
    while ( $row = $db->fetchrow() )
    {
      if ( !isset($plugin_list[ $row['plugin_filename'] ]) )
      {
        // missing plugin file, don't report (for now)
        continue;
      }
      $filename =& $row['plugin_filename'];
      $plugin_list[$filename]['installed'] = true;
      $plugin_list[$filename]['status'] = PLUGIN_INSTALLED;
      $plugin_list[$filename]['plugin id'] = $row['plugin_id'];
      if ( $row['plugin_version'] != $plugin_list[$filename]['version'] )
      {
        $plugin_list[$filename]['status'] |= PLUGIN_OUTOFDATE;
        $plugin_list[$filename]['version installed'] = $row['plugin_version'];
      }
      if ( $row['plugin_flags'] & PLUGIN_DISABLED )
      {
        $plugin_list[$filename]['status'] |= PLUGIN_DISABLED;
      }
    }
    $db->free_result();
    
    // sort it all out by filename
    ksort($plugin_list);
    
    // done
    return $plugin_list;
  }
  
  /**
   * Retrieves the metadata block from a plugin file
   * @param string Path to plugin file (full path)
   * @return array
   */
  
  function get_plugin_info($fullpath, $use_cache = true)
  {
    global $plugins_cache;
    $dh = basename($fullpath);
    
    // first can we use cached info?
    if ( isset($plugins_cache[$dh]) && $plugins_cache[$dh]['file md5'] === $this->md5_header($fullpath) )
    {
      $plugin_meta = $plugins_cache[$dh];
    }
    else
    {
      // the cache is out of date if we reached here -- regenerate
      if ( $use_cache )
        $this->generate_plugins_cache();
      
      // pass 1: try to read a !info block
      $blockdata = $this->parse_plugin_blocks($fullpath, 'info');
      if ( empty($blockdata) )
      {
        // no !info block, check for old header
        $fh = @fopen($fullpath, 'r');
        if ( !$fh )
          // can't read, bail out
          return false;
        $plugin_data = array();
        for ( $i = 0; $i < 8; $i++ )
        {
          $plugin_data[] = @fgets($fh, 8096);
        }
        // close our file handle
        fclose($fh);
        // is the header correct?
        if ( trim($plugin_data[0]) != '<?php' || trim($plugin_data[1]) != '/*' )
        {
          // nope. get out.
          return false;
        }
        // parse all the variables
        $plugin_meta = array();
        for ( $i = 2; $i <= 7; $i++ )
        {
          if ( !preg_match('/^([A-z0-9 ]+?): (.+?)$/', trim($plugin_data[$i]), $match) )
            return false;
          $plugin_meta[ strtolower($match[1]) ] = $match[2];
        }
      }
      else
      {
        // parse JSON block
        $plugin_data =& $blockdata[0]['value'];
        $plugin_data = enano_clean_json(enano_trim_json($plugin_data));
        try
        {
          $plugin_meta_uc = enano_json_decode($plugin_data);
        }
        catch ( Exception $e )
        {
          return false;
        }
        // convert all the keys to lowercase
        $plugin_meta = array();
        foreach ( $plugin_meta_uc as $key => $value )
        {
          $plugin_meta[ strtolower($key) ] = $value;
        }
      }
    }
    if ( !isset($plugin_meta) || !is_array(@$plugin_meta) )
    {
      // parsing didn't work.
      return false;
    }
    // check for required keys
    $required_keys = array('plugin name', 'plugin uri', 'description', 'author', 'version', 'author uri');
    foreach ( $required_keys as $key )
    {
      if ( !isset($plugin_meta[$key]) )
        // not set, skip this plugin
        return false;
    }
    // decide if it's a system plugin
    $plugin_meta['system plugin'] = in_array($dh, $this->system_plugins);
    // reset installed variable
    $plugin_meta['installed'] = false;
    $plugin_meta['status'] = 0;
    
    return $plugin_meta;
  }
  
  
  /**
   * Attempts to cache plugin information in a file to speed fetching.
   */
  
  function generate_plugins_cache()
  {
    if ( getConfig('cache_thumbs') != '1' )
      return;
    
    // fetch the most current info
    $plugin_info = $this->get_plugin_list(null, false);
    foreach ( $plugin_info as $plugin => &$info )
    {
      $info['file md5'] = $this->md5_header(ENANO_ROOT . "/plugins/$plugin");
    }
    
    $this->update_plugins_cache($plugin_info);
    $GLOBALS['plugins_cache'] = $plugin_info;
  }
  
  /**
   * Writes an information array to the cache file.
   * @param array
   * @access private
   */
  
  function update_plugins_cache($plugin_info)
  {
    global $cache;
    return $cache->store('plugins', $plugin_info, -1);
  }
  
  /**
   * Loads the plugins cache if any.
   */
  
  function load_plugins_cache()
  {
    global $cache;
    if ( $data = $cache->fetch('plugins') )
    {
      $GLOBALS['plugins_cache'] = $data;
    }
  }
  
  /**
   * Calculates the MD5 sum of the first 10 lines of a file. Useful for caching plugin header information.
   * @param string File
   * @return string
   */
  
  function md5_header($file)
  {
    $fh = @fopen($file, 'r');
    if ( !$fh )
      return false;
    $i = 0;
    $h = '';
    while ( $i < 10 )
    {
      $line = fgets($fh, 8096);
      $h .= $line . "\n";
      $i++;
    }
    fclose($fh);
    return md5($h);
  }
  
  /**
   * Installs a plugin.
   * @param string Filename of plugin.
   * @param array The list of plugins as output by pluginLoader::get_plugin_list(). If not passed, the function is called, possibly wasting time.
   * @return array JSON-formatted but not encoded response
   */
  
  function install_plugin($filename, $plugin_list = null)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( !$plugin_list )
      $plugin_list = $this->get_plugin_list();
    
    // we're gonna need this
    require_once ( ENANO_ROOT . '/includes/sql_parse.php' );
    
    switch ( true ): case true:
      
    // is the plugin in the directory and awaiting installation?
    if ( !isset($plugin_list[$filename]) || (
        isset($plugin_list[$filename]) && $plugin_list[$filename]['installed']
      ))
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Invalid plugin specified.',
        'debug' => $filename
      );
      break;
    }
    
    $dataset =& $plugin_list[$filename];
    
    // load up the installer schema
    $schema = $this->parse_plugin_blocks( ENANO_ROOT . '/plugins/' . $filename, 'install' );
    
    $sql = array();
    if ( !empty($schema) )
    {
      // parse SQL
      $parser = new SQL_Parser($schema[0]['value'], true);
      $parser->assign_vars(array(
        'TABLE_PREFIX' => table_prefix
        ));
      $sql = $parser->parse();
    }
    
    // schema is final, check queries
    foreach ( $sql as $query )
    {
      if ( !$db->check_query($query) )
      {
        // aww crap, a query is bad
        $return = array(
          'mode' => 'error',
          'error' => $lang->get('acppl_err_upgrade_bad_query'),
        );
        break 2;
      }
    }
    
    // this is it, perform installation
    foreach ( $sql as $query )
    {
      if ( substr($query, 0, 1) == '@' )
      {
        $query = substr($query, 1);
        $db->sql_query($query);
      }
      else
      {
        if ( !$db->sql_query($query) )
          $db->die_json();
      }
    }
    
    // log action
    $time        = time();
    $ip_db       = $db->escape($_SERVER['REMOTE_ADDR']);
    $username_db = $db->escape($session->username);
    $file_db     = $db->escape($filename);
    $q = $db->sql_query('INSERT INTO '.table_prefix."logs(log_type, action, time_id, edit_summary, author, page_text) VALUES\n"
                      . "  ('security', 'plugin_install', $time, '$ip_db', '$username_db', '$file_db');");
    if ( !$q )
      $db->_die();
    
    // register plugin
    $version_db = $db->escape($dataset['version']);
    $filename_db = $db->escape($filename);
    $flags = PLUGIN_INSTALLED;
    
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "plugins ( plugin_version, plugin_filename, plugin_flags )\n"
                      . "  VALUES ( '$version_db', '$filename_db', $flags );");
    if ( !$q )
      $db->die_json();
    
    $return = array(
      'success' => true
    );
    
    endswitch;
    
    return $return;
  }
  
  /**
   * Uninstalls a plugin, removing it completely from the database and calling any custom uninstallation code the plugin specifies.
   * @param string Filename of plugin.
   * @param array The list of plugins as output by pluginLoader::get_plugin_list(). If not passed, the function is called, possibly wasting time.
   * @return array JSON-formatted but not encoded response
   */
  
  function uninstall_plugin($filename, $plugin_list = null)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( !$plugin_list )
      $plugin_list = $this->get_plugin_list();
    
    // we're gonna need this
    require_once ( ENANO_ROOT . '/includes/sql_parse.php' );
    
    switch ( true ): case true:
    
    // is the plugin in the directory and already installed?
    if ( !isset($plugin_list[$filename]) || (
        isset($plugin_list[$filename]) && !$plugin_list[$filename]['installed']
      ))
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Invalid plugin specified.',
      );
      break;
    }
    // get plugin id
    $dataset =& $plugin_list[$filename];
    if ( empty($dataset['plugin id']) )
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Couldn\'t retrieve plugin ID.',
      );
      break;
    }
    
    // load up the installer schema
    $schema = $this->parse_plugin_blocks( ENANO_ROOT . '/plugins/' . $filename, 'uninstall' );
    
    $sql = array();
    if ( !empty($schema) )
    {
      // parse SQL
      $parser = new SQL_Parser($schema[0]['value'], true);
      $parser->assign_vars(array(
        'TABLE_PREFIX' => table_prefix
        ));
      $sql = $parser->parse();
    }
    
    // schema is final, check queries
    foreach ( $sql as $query )
    {
      if ( !$db->check_query($query) )
      {
        // aww crap, a query is bad
        $return = array(
          'mode' => 'error',
          'error' => $lang->get('acppl_err_upgrade_bad_query'),
        );
        break 2;
      }
    }
    
    // this is it, perform uninstallation
    foreach ( $sql as $query )
    {
      if ( substr($query, 0, 1) == '@' )
      {
        $query = substr($query, 1);
        $db->sql_query($query);
      }
      else
      {
        if ( !$db->sql_query($query) )
          $db->die_json();
      }
    }
    
    // log action
    $time        = time();
    $ip_db       = $db->escape($_SERVER['REMOTE_ADDR']);
    $username_db = $db->escape($session->username);
    $file_db     = $db->escape($filename);
    $q = $db->sql_query('INSERT INTO '.table_prefix."logs(log_type, action, time_id, edit_summary, author, page_text) VALUES\n"
                      . "  ('security', 'plugin_uninstall', $time, '$ip_db', '$username_db', '$file_db');");
    if ( !$q )
      $db->_die();
    
    // deregister plugin
    $q = $db->sql_query('DELETE FROM ' . table_prefix . "plugins WHERE plugin_id = {$dataset['plugin id']};");
    if ( !$q )
      $db->die_json();
    
    $return = array(
      'success' => true
    );
    
    endswitch;
    
    return $return;
  }
  
  /**
   * Very intelligently upgrades a plugin to the version specified in the filesystem.
   * @param string Filename of plugin.
   * @param array The list of plugins as output by pluginLoader::get_plugin_list(). If not passed, the function is called, possibly wasting time.
   * @return array JSON-formatted but not encoded response
   */
  
  function upgrade_plugin($filename, $plugin_list = null)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( !$plugin_list )
      $plugin_list = $this->get_plugin_list();
    
    // we're gonna need this
    require_once ( ENANO_ROOT . '/includes/sql_parse.php' );
    
    switch ( true ): case true:
    
    // is the plugin in the directory and already installed?
    if ( !isset($plugin_list[$filename]) || (
        isset($plugin_list[$filename]) && !$plugin_list[$filename]['installed']
      ))
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Invalid plugin specified.',
      );
      break;
    }
    // get plugin id
    $dataset =& $plugin_list[$filename];
    if ( empty($dataset['plugin id']) )
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Couldn\'t retrieve plugin ID.',
      );
      break;
    }
    
    //
    // Here we go with the main upgrade process. This is the same logic that the
    // Enano official upgrader uses, in fact it's the same SQL parser. We need
    // list of all versions of the plugin to continue, though.
    //
    
    if ( !isset($dataset['version list']) || ( isset($dataset['version list']) && !is_array($dataset['version list']) ) )
    {
      // no version list - update the version number but leave the rest alone
      $version = $db->escape($dataset['version']);
      $q = $db->sql_query('UPDATE ' . table_prefix . "plugins SET plugin_version = '$version' WHERE plugin_id = {$dataset['plugin id']};");
      if ( !$q )
        $db->die_json();
      
      // send an error and notify the user even though it was technically a success
      $return = array(
        'mode' => 'error',
        'error' => $lang->get('acppl_err_upgrade_not_supported'),
      );
      break;
    }
    
    // build target list
    $versions  = $dataset['version list'];
    $indices   = array_flip($versions);
    $installed = $dataset['version installed'];
    
    // is the current version upgradeable?
    if ( !isset($indices[$installed]) )
    {
      $return = array(
        'mode' => 'error',
        'error' => $lang->get('acppl_err_upgrade_bad_version'),
      );
      break;
    }
    
    // does the plugin support upgrading to its own version?
    if ( !isset($indices[$installed]) )
    {
      $return = array(
        'mode' => 'error',
        'error' => $lang->get('acppl_err_upgrade_bad_target_version'),
      );
      break;
    }
    
    // list out which versions to do
    $index_start = @$indices[$installed] + 1;
    $index_stop  = @$indices[$dataset['version']];
    
    // Are we trying to go backwards?
    if ( $index_stop <= $index_start )
    {
      $return = array(
        'mode' => 'error',
        'error' => $lang->get('acppl_err_upgrade_to_older'),
      );
      break;
    }
    
    // build the list of version sets
    $ver_previous = $installed;
    $targets = array();
    for ( $i = $index_start; $i <= $index_stop; $i++ )
    {
      $targets[] = array($ver_previous, $versions[$i]);
      $ver_previous = $versions[$i];
    }
    
    // parse out upgrade sections in plugin file
    $plugin_blocks = $this->parse_plugin_blocks( ENANO_ROOT . '/plugins/' . $filename, 'upgrade' );
    $sql_blocks = array();
    foreach ( $plugin_blocks as $block )
    {
      if ( !isset($block['from']) || !isset($block['to']) )
      {
        continue;
      }
      $key = "{$block['from']} TO {$block['to']}";
      $sql_blocks[$key] = $block['value'];
    }
    
    // do version list check
    // for now we won't fret if a specific version set isn't found, we'll just
    // not do that version and assume there were no DB changes.
    foreach ( $targets as $i => $target )
    {
      list($from, $to) = $target;
      $key = "$from TO $to";
      if ( !isset($sql_blocks[$key]) )
      {
        unset($targets[$i]);
      }
    }
    $targets = array_values($targets);
    
    // parse and finalize schema
    $schema = array();
    foreach ( $targets as $i => $target )
    {
      list($from, $to) = $target;
      $key = "$from TO $to";
      try
      {
        $parser = new SQL_Parser($sql_blocks[$key], true);
      }
      catch ( Exception $e )
      {
        $return = array(
          'mode' => 'error',
          'error' => 'SQL parser init exception',
          'debug' => "$e"
        );
        break 2;
      }
      $parser->assign_vars(array(
        'TABLE_PREFIX' => table_prefix
        ));
      $parsed = $parser->parse();
      foreach ( $parsed as $query )
      {
        $schema[] = $query;
      }
    }
    
    // schema is final, check queries
    foreach ( $schema as $query )
    {
      if ( !$db->check_query($query) )
      {
        // aww crap, a query is bad
        $return = array(
          'mode' => 'error',
          'error' => $lang->get('acppl_err_upgrade_bad_query'),
        );
        break 2;
      }
    }
    
    // this is it, perform upgrade
    foreach ( $schema as $query )
    {
      if ( substr($query, 0, 1) == '@' )
      {
        $query = substr($query, 1);
        $db->sql_query($query);
      }
      else
      {
        if ( !$db->sql_query($query) )
          $db->die_json();
      }
    }
    
    // log action
    $time        = time();
    $ip_db       = $db->escape($_SERVER['REMOTE_ADDR']);
    $username_db = $db->escape($session->username);
    $file_db     = $db->escape($filename);
    $q = $db->sql_query('INSERT INTO '.table_prefix."logs(log_type, action, time_id, edit_summary, author, page_text) VALUES\n"
                      . "  ('security', 'plugin_upgrade', $time, '$ip_db', '$username_db', '$file_db');");
    if ( !$q )
      $db->_die();
    
    // update version number
    $version = $db->escape($dataset['version']);
    $q = $db->sql_query('UPDATE ' . table_prefix . "plugins SET plugin_version = '$version' WHERE plugin_id = {$dataset['plugin id']};");
    if ( !$q )
      $db->die_json();
    
    // all done :-)
    $return = array(
      'success' => true
    );
    
    endswitch;
    
    return $return;
  }
  
  /**
   * Re-imports the language strings from a plugin.
   * @param string File name
   * @return array Enano JSON response protocol
   */
  
  function reimport_plugin_strings($filename, $plugin_list = null)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( !$plugin_list )
      $plugin_list = $this->get_plugin_list();
    
    switch ( true ): case true:
    
    // is the plugin in the directory and already installed?
    if ( !isset($plugin_list[$filename]) || (
        isset($plugin_list[$filename]) && !$plugin_list[$filename]['installed']
      ))
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Invalid plugin specified.',
      );
      break;
    }
    // get plugin data
    $dataset =& $plugin_list[$filename];
    
    // check for a language block
    $blocks = self::parse_plugin_blocks(ENANO_ROOT . '/plugins/' . $filename, 'language');
    if ( count($blocks) < 1 )
    {
      return array(
          'mode' => 'error',
          'error' => $lang->get('acppl_err_import_no_strings')
        );
    }
    
    $result = $lang->import_plugin(ENANO_ROOT . '/plugins/' . $filename);
    if ( $result )
    {
      return array(
        'success' => true
      );
    }
    else
    {
      return array(
        'mode' => 'error',
        'error' => 'Language API returned error'
      );
    }
    
    endswitch;
  }
}

?>
