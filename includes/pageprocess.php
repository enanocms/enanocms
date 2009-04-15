<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * pageprocess.php - intelligent retrieval of pages
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Class to handle fetching page text (possibly from a cache) and formatting it.
 * As of 1.0.4, this also handles the fetching and editing of certain data for pages.
 * @package Enano
 * @subpackage UI
 * @copyright 2007 Dan Fuhry
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl-2.0.html>
 */

class PageProcessor
{
  
  /**
   * Page ID and namespace of the page handled by this instance
   * @var string
   */
  
  var $page_id;
  var $namespace;
  
  /**
   * The instance of the namespace processor for the namespace we're doing.
   * @var object
   */
  
  var $ns;
  
  /**
   * The title of the page sent to the template parser
   * @var string
   */
  
  var $title = '';
  
  /**
   * The information about the page(s) we were redirected from
   * @var array
   */
  
  var $redirect_stack = array();
  
  /**
   * The revision ID (history entry) to send. If set to 0 (the default) then the most recent revision will be sent.
   * @var int
   */
  
  var $revision_id = 0;
  
  /**
   * The time this revision was saved, as a UNIX timestamp
   * @var int
   */
  
  var $revision_time = 0;
  
  /**
   * Unsanitized page ID.
   * @var string
   */
  
  var $page_id_unclean;
  
  /**
   * Tracks if the page we're loading exists in the database or not.
   * @var bool
   */
  
  var $page_exists = false;
  
  /**
   * Permissions!
   * @var object
   */
  
  var $perms = null;
  
  /**
   * The SHA1 hash of the user-inputted password for the page
   * @var string
   */
   
  var $password = '';
  
  /**
   * Switch to track if redirects are allowed. Defaults to true.
   * @var bool
   */
  
  var $allow_redir = true;
  
  /**
   * If this is set to true, this will call the header and footer funcs on $template when render() is called.
   * @var bool
   */
  
  var $send_headers = false;
  
  /**
   * Cache the fetched text so we don't fetch it from the DB twice.
   * @var string
   */
  
  var $text_cache = '';
  
  /**
   * Debugging information to track errors. You can set enable to false to disable sending debug information.
   * @var array
   */
  
  var $debug = array(
      'enable' => false,
      'works'  => false
    );
  
  /**
   * The list of errors raised in the class.
   * @var array
   */
  
  var $_errors = array();
  
  /**
   * Constructor.
   * @param string The page ID (urlname) of the page
   * @param string The namespace of the page
   * @param int Optional. The revision ID to send.
   */
  
  function __construct( $page_id, $namespace, $revision_id = 0 )
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    profiler_log("PageProcessor [{$namespace}:{$page_id}]: Started constructor");
    
    // See if we can get some debug info
    if ( function_exists('debug_backtrace') && $this->debug['enable'] )
    {
      $this->debug['works'] = true;
      $this->debug['backtrace'] = enano_debug_print_backtrace(true);
    }
    
    // First things first - check page existence and permissions
    
    if ( !isset($paths->nslist[$namespace]) )
    {
      $this->send_error('The namespace "' . htmlspecialchars($namespace) . '" does not exist.');
    }
    
    if ( !is_int($revision_id) )
      $revision_id = 0;
    
    $this->_setup( $page_id, $namespace, $revision_id );
  }
  
  /**
   * The main method to send the page content. Also responsible for checking permissions and calling the statistics counter.
   * @param bool If true, the stat counter is called. Defaults to false.
   */
  
  function send( $do_stats = false )
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    profiler_log('PageProcessor: send() called');
    
    if ( !$this->perms->get_permissions('read') )
    {
      if ( $this->send_headers )
      {
        $template->init_vars($this);
      }
      // Permission denied to read page. Is this one of our core pages that must always be allowed?
      // NOTE: Not even the administration panel will work if ACLs deny access to it.
      if ( $this->namespace == 'Special' && in_array($this->page_id, array('Login', 'Logout', 'LangExportJSON', 'CSS')) )
      {
        // Do nothing; allow execution to continue
      }
      else
      {
        // Page isn't whitelisted, behave as normal
        $this->err_access_denied();
        return false;
      }
    }
    if ( $this->revision_id > 0 && !$this->perms->get_permissions('history_view') )
    {
      $this->err_access_denied();
      return false;
    }
    
    // Is there a custom function registered for handling this namespace?
    // DEPRECATED (even though it only saw its way into one alpha release.)
    if ( $proc = $paths->get_namespace_processor($this->namespace) )
    {
      // yes, just call that
      // this is protected aggressively by the PathManager against overriding critical namespaces
      return call_user_func($proc, $this);
    }
    
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    $strict_no_headers = false;
    $admin_fail = false;
    if ( $this->namespace == 'Admin' && strstr($this->page_id, '/') )
    {
      if ( $this->send_headers )
      {
        $template->init_vars($this);
      }
      $this->page_id = substr($this->page_id, 0, strpos($this->page_id, '/'));
      $funcname = "page_{$this->namespace}_{$this->page_id}";
      if ( function_exists($funcname) )
      {
        $this->page_exists = true;
      }
    }
    if ( isPage($pathskey) )
    {
      if ( $this->send_headers )
      {
        $template->init_vars($this);
      }
      if ( $paths->pages[$pathskey]['special'] == 1 )
      {
        $this->send_headers = false;
        $strict_no_headers = true;
        $GLOBALS['output'] = new Output_Naked();
      }
      if ( isset($paths->pages[$pathskey]['password']) )
      {
        if ( $paths->pages[$pathskey]['password'] != '' && $paths->pages[$pathskey]['password'] != sha1('') )
        {
          $password =& $paths->pages[$pathskey]['password'];
          if ( $this->password != $password )
          {
            $this->err_wrong_password();
            return false;
          }
        }
      }
      if ( isset($paths->pages[$pathskey]['require_admin']) && $paths->pages[$pathskey]['require_admin'] )
      {
        if ( $session->auth_level < USER_LEVEL_ADMIN )
        {
          $admin_fail = true;
        }
      }
    }
    else if ( $this->namespace === $paths->namespace && $this->page_id == $paths->page_id )
    {
      if ( isset($paths->cpage['require_admin']) && $paths->cpage['require_admin'] )
      {
        if ( $session->auth_level < USER_LEVEL_ADMIN )
        {
          $admin_fail = true;
        }
      }
    }
    if ( $admin_fail )
    {
      header('Content-type: text/javascript');
      echo enano_json_encode(array(
          'mode' => 'error',
          'error' => 'need_auth_to_admin'
        ));
      return true;
    }
    if ( $this->page_exists && $this->namespace != 'Special' && $this->namespace != 'Admin' && $do_stats )
    {
      require_once(ENANO_ROOT.'/includes/stats.php');
      doStats($this->page_id, $this->namespace);
    }
    
    // We are all done. Ship off the page.
    
    if ( $this->send_headers )
    {
      $template->init_vars($this);
    }
    
    $this->ns->send();
  }
  
  /**
   * Sends the page through by fetching it from the database.
   */
   
  function send_from_db($strict_no_headers = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $this->ns->send_from_db();
  }
  
  /**
   * Fetches the wikitext or HTML source for the page.
   * @return string
   */
  
  function fetch_source()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !$this->perms->get_permissions('view_source') )
    {
      return false;
    }
    if ( !$this->page_exists )
    {
      return '';
    }
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    if ( isPage($pathskey) )
    {
      if ( isset($paths->pages[$pathskey]['password']) )
      {
        if ( $paths->pages[$pathskey]['password'] != sha1('') && $paths->pages[$pathskey]['password'] !== $this->password && !empty($paths->pages[$pathskey]['password']) )
        {
          return false;
        }
      }
    }
    return $this->fetch_text();
  }
  
  /**
   * Updates (saves/changes/edits) the content of the page.
   * @param string The new text for the page
   * @param string A summary of edits made to the page.
   * @param bool If true, the edit is marked as a minor revision
   * @param string Page format - wikitext or xhtml. REQUIRED, and new in 1.1.6.
   * @return bool True on success, false on failure. When returning false, it will push errors to the PageProcessor error stack; read with $page->pop_error()
   */
  
  function update_page($text, $edit_summary = false, $minor_edit = false, $page_format)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    // Create the page if it doesn't exist
    if ( !$this->page_exists )
    {
      if ( !$this->create_page() )
      {
        return false;
      }
    }
      
    //
    // Validation
    //
    
    $page_id = $db->escape($this->page_id);
    $namespace = $db->escape($this->namespace);
    
    $q = $db->sql_query('SELECT protected FROM ' . table_prefix . "pages WHERE urlname='$page_id' AND namespace='$namespace';");
    if ( !$q )
      $db->_die('PageProcess updating page content');
    if ( $db->numrows() < 1 )
    {
      $this->raise_error($lang->get('editor_err_no_rows'));
      return false;
    }
    
    // Do we have permission to edit the page?
    if ( !$this->perms->get_permissions('edit_page') )
    {
      $this->raise_error($lang->get('editor_err_no_permission'));
      return false;
    }
    
    list($protection) = $db->fetchrow_num();
    $db->free_result();
    
    if ( $protection == 1 )
    {
      // The page is protected - do we have permission to edit protected pages?
      if ( !$this->perms->get_permissions('even_when_protected') )
      {
        $this->raise_error($lang->get('editor_err_page_protected'));
        return false;
      }
    }
    else if ( $protection == 2 )
    {
      // The page is semi-protected.
      if (
           ( !$session->user_logged_in || // Is the user logged in?
             ( $session->user_logged_in && $session->reg_time + ( 4 * 86400 ) >= time() ) ) // If so, have they been registered for 4 days?
           && !$this->perms->get_permissions('even_when_protected') ) // And of course, is there an ACL that overrides semi-protection?
      {
        $this->raise_error($lang->get('editor_err_page_protected'));
        return false;
      }
    }
    
    // Spam check
    if ( !spamalyze($text) )
    {
      $this->raise_error($lang->get('editor_err_spamcheck_failed'));
      return false;
    }
    
    // Page format check
    if ( !in_array($page_format, array('xhtml', 'wikitext')) )
    {
      $this->raise_error("format \"$page_format\" not one of [xhtml, wikitext]");
      return false;
    }
    
    //
    // Protection validated; update page content
    //
    
    $text_undb = RenderMan::preprocess_text($text, false, false);
    $text = $db->escape($text_undb);
    $author = $db->escape($session->username);
    $time = time();
    $edit_summary = ( strval($edit_summary) === $edit_summary ) ? $db->escape($edit_summary) : '';
    $minor_edit = ( $minor_edit ) ? '1' : '0';
    $date_string = enano_date('d M Y h:i a');
    
    // Insert log entry
    $sql = 'INSERT INTO ' . table_prefix . "logs ( time_id, date_string, log_type, action, page_id, namespace, author, page_text, edit_summary, minor_edit, page_format )\n"
         . "  VALUES ( $time, '$date_string', 'page', 'edit', '{$this->page_id}', '{$this->namespace}', '$author', '$text', '$edit_summary', $minor_edit, '$page_format' );";
    if ( !$db->sql_query($sql) )
    {
      $this->raise_error($db->get_error());
      return false;
    }
    
    // Update the master text entry
    $sql = 'UPDATE ' . table_prefix . "page_text SET page_text = '$text' WHERE page_id = '{$this->page_id}' AND namespace = '{$this->namespace}';";
    if ( !$db->sql_query($sql) )
    {
      $this->raise_error($db->get_error());
      return false;
    }
    
    // If there's an identical draft copy, delete it
    $sql = 'DELETE FROM ' . table_prefix . "logs WHERE is_draft = 1 AND page_id = '{$this->page_id}' AND namespace = '{$this->namespace}' AND page_text = '{$text}';";
    if ( !$db->sql_query($sql) )
    {
      $this->raise_error($db->get_error());
      return false;
    }
    
    // Set page_format
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    // Using @ due to warning thrown when saving new page
    if ( @$paths->pages[ $pathskey ]['page_format'] !== $page_format )
    {
      // Note: no SQL injection to worry about here. Everything that goes into this is sanitized already, barring some rogue plugin.
      // (and if there's a rogue plugin running, we have bigger things to worry about anyway.)
      if ( !$db->sql_query('UPDATE ' . table_prefix . "pages SET page_format = '$page_format' WHERE urlname = '$this->page_id' AND namespace = '$this->namespace';") )
      {
        $this->raise_error($db->get_error());
        return false;
      }
      $paths->update_metadata_cache();
    }
    
    // Rebuild the search index
    $paths->rebuild_page_index($this->page_id, $this->namespace);
    
    $this->text_cache = $text;
    
    return true;
    
  }
  
  /**
   * Creates the page if it doesn't already exist.
   * @param string Optional page title.
   * @param bool Visibility (allow indexing) flag
   * @return bool True on success, false on failure.
   */
  
  function create_page($title = false, $visible = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    // Do we have permission to create the page?
    if ( !$this->perms->get_permissions('create_page') )
    {
      $this->raise_error($lang->get('pagetools_create_err_no_permission'));
      return false;
    }
    
    // Does it already exist?
    if ( $this->page_exists )
    {
      $this->raise_error($lang->get('pagetools_create_err_already_exists'));
      return false;
    }
    
    // It's not in there. Perform validation.
    
    // We can't create special, admin, or external pages.
    if ( $this->namespace == 'Special' || $this->namespace == 'Admin' || $this->namespace == 'API' )
    {
      $this->raise_error($lang->get('pagetools_create_err_nodb_namespace'));
      return false;
    }
    
    // Guess the proper title
    $name = ( !empty($title) ) ? $title : str_replace('_', ' ', dirtify_page_id($this->page_id));
    
    // Check for the restricted Project: prefix
    if ( substr($this->page_id, 0, 8) == 'Project:' )
    {
      $this->raise_error($lang->get('pagetools_create_err_reserved_prefix'));
      return false;
    }
    
    // Validation successful - insert the page
    
    $metadata = array(
        'urlname' => $this->page_id,
        'namespace' => $this->namespace,
        'name' => $name,
        'special' => 0,
        'visible' => $visible ? 1 : 0,
        'comments_on' => 1,
        'protected' => ( $this->namespace == 'System' ? 1 : 0 ),
        'delvotes' => 0,
        'delvote_ips' => serialize(array()),
        'wiki_mode' => 2
      );
    
    $paths->add_page($metadata);
    
    $page_id = $db->escape($this->page_id);
    $namespace = $db->escape($this->namespace);
    $name = $db->escape($name);
    $protect = ( $this->namespace == 'System' ) ? '1' : '0';
    $blank_array = $db->escape(serialize(array()));
    
    // Query 1: Metadata entry
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "pages(name, urlname, namespace, visible, protected, delvotes, delvote_ips, wiki_mode)\n"
                      . "  VALUES ( '$name', '$page_id', '$namespace', {$metadata['visible']}, $protect, 0, '$blank_array', 2 );");
    if ( !$q )
      $db->_die('PageProcessor page creation - metadata stage');
    
    // Query 2: Text insertion
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "page_text(page_id, namespace, page_text)\n"
                        . "VALUES ( '$page_id', '$namespace', '' );");
    if ( !$q )
      $db->_die('PageProcessor page creation - text stage');
    
    // Query 3: Log entry
    $db->sql_query('INSERT INTO ' . table_prefix."logs(time_id, date_string, log_type, action, author, page_id, namespace)\n"
                   . "  VALUES ( " . time() . ", '" . enano_date('d M Y h:i a') . "', 'page', 'create', \n"
                   . "          '" . $db->escape($session->username) . "', '" . $db->escape($this->page_id) . "', '" . $this->namespace . "');");
    if ( !$q )
      $db->_die('PageProcessor page creation - logging stage');
    
    // Update the cache
    $paths->update_metadata_cache();
    
    // Make sure that when/if we save the page later in this instance it doesn't get re-created
    $this->page_exists = true;
    
    // Page created. We're good!
    return true;
  }
  
  /**
   * Rolls back a non-edit action in the logs
   * @param int Log entry (log_id) to roll back
   * @return array Standard Enano error/success protocol
   */
  
  function rollback_log_entry($log_id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $cache;
    
    // Verify permissions
    if ( !$this->perms->get_permissions('history_rollback') )
    {
      return array(
        'success' => false,
        'error' => 'access_denied'
        );
    }
    
    // Check input
    $log_id = intval($log_id);
    if ( empty($log_id) )
    {
      return array(
        'success' => false,
        'error' => 'invalid_parameter'
        );
    }
    
    // Fetch the log entry
    $q = $db->sql_query('SELECT * FROM ' . table_prefix . "logs WHERE log_type = 'page' AND page_id='{$this->page_id}' AND namespace='{$this->namespace}' AND log_id = $log_id;");
    if ( !$q )
      $db->_die();
    
    // Is this even a valid log entry for this context?
    if ( $db->numrows() < 1 )
    {
      return array(
        'success' => false,
        'error' => 'entry_not_found'
        );
    }
    
    // All good, fetch and free the result
    $log_entry = $db->fetchrow();
    $db->free_result();
    
    $dateline = enano_date('d M Y h:i a', $log_entry['time_id']);
    
    // Let's see, what do we have here...
    switch ( $log_entry['action'] )
    {
      case 'rename':
        // Page was renamed, let the rename method handle this
        return array_merge($this->rename_page($log_entry['edit_summary']), array('dateline' => $dateline, 'action' => $log_entry['action']));
        break;
      case 'prot':
      case 'unprot':
      case 'semiprot':
        return array_merge($this->protect_page(intval($log_entry['page_text']), '__REVERSION__'), array('dateline' => $dateline, 'action' => $log_entry['action']));
        break;
      case 'delete':
        
        // Raising a previously dead page has implications...
        
        // FIXME: l10n
        // rollback_extra is required because usually only moderators can undo page deletion AND restore the content.
        // potential flaw here - once recreated, can past revisions be restored by users without rollback_extra? should
        // probably modify editor routine to deny revert access if the timestamp < timestamp of last deletion if any.
        if ( !$this->perms->get_permissions('history_rollback_extra') )
          return 'Administrative privileges are required for page undeletion.';
        
        // Rolling back the deletion of a page that was since created?
        $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
        if ( isPage($pathskey) )
          return array(
              'success' => false,
              // This is a clean Christian in-joke.
              'error' => 'seeking_living_among_dead'
            );
        
        // Generate a crappy page name
        $name = $db->escape( str_replace('_', ' ', dirtify_page_id($this->page_id)) );
        
        // Stage 1 - re-insert page
        $e = $db->sql_query('INSERT INTO ' . table_prefix.'pages(name,urlname,namespace) VALUES( \'' . $name . '\', \'' . $this->page_id . '\',\'' . $this->namespace . '\' )');
        if ( !$e )
          $db->die_json();
        
        // Select the latest published revision
        $q = $db->sql_query('SELECT page_text FROM ' . table_prefix . "logs WHERE\n"
                          . "      log_type  = 'page'\n"
                          . "  AND action    = 'edit'\n"
                          . "  AND page_id   = '$this->page_id'\n"
                          . "  AND namespace = '$this->namespace'\n"
                          . "  AND is_draft != 1\n"
                          . "ORDER BY time_id DESC LIMIT 1;");
        if ( !$q )
          $db->die_json();
        list($page_text) = $db->fetchrow_num();
        $db->free_result($q);
        
        // Apply the latest revision as the current page text
        $page_text = $db->escape($page_text);
        $e = $db->sql_query('INSERT INTO ' . table_prefix."page_text(page_id, namespace, page_text) VALUES\n"
                          . "  ( '$this->page_id', '$this->namespace', '$page_text' );");
        if ( !$e )
          $db->die_json();
        
        $cache->purge('page_meta');
        
        return array(
            'success' => true,
            'dateline' => $dateline,
            'action' => $log_entry['action']
          );
        
        break;
      case 'reupload':
        
        // given a log id and some revision info, restore the old file.
        // get the timestamp of the file before this one
        $q = $db->sql_query('SELECT time_id, file_key, file_extension, filename, size, mimetype FROM ' . table_prefix . "files WHERE time_id < {$log_entry['time_id']} ORDER BY time_id DESC LIMIT 1;");
        if ( !$q )
          $db->_die();
        
        $row = $db->fetchrow();
        $db->free_result();
        
        // If the file hasn't been renamed to the new format (omitting timestamp), do that now.
        $fname = ENANO_ROOT . "/files/{$row['file_key']}_{$row['time_id']}{$row['file_extension']}";
        if ( @file_exists($fname) )
        {
          // it's stored in the old format - rename
          $fname_new = ENANO_ROOT . "/files/{$row['file_key']}{$row['file_extension']}";
          if ( !@rename($fname, $fname_new) )
          {
            return array(
              'success' => false,
              'error' => 'rb_file_rename_failed',
              'action' => $log_entry['action']
              );
          }
        }
        
        // Insert a new file entry
        $time = time();
        $filename = $db->escape($row['filename']);
        $mimetype = $db->escape($row['mimetype']);
        $ext = $db->escape($row['file_extension']);
        $key = $db->escape($row['file_key']);
        
        $q = $db->sql_query('INSERT INTO ' . table_prefix . "files ( time_id, page_id, filename, size, mimetype, file_extension, file_key ) VALUES\n"
              . "  ( $time, '$this->page_id', '$filename', {$row['size']}, '$mimetype', '$ext', '$key' );");
        if ( !$q )
          $db->die_json();
        
        // add reupload log entry
        $username = $db->escape($session->username);
        $q = $db->sql_query('INSERT INTO ' . table_prefix . "logs ( log_type, action, time_id, page_id, namespace, author, edit_summary ) VALUES\n"
                          . "  ( 'page', 'reupload', $time, '$this->page_id', '$this->namespace', '$username', '__ROLLBACK__' )");
        if ( !$q )
          $db->die_json();
        
        return array(
            'success' => true,
            'dateline' => $dateline,
            'action' => $log_entry['action']
          );
        
        break;
      case 'votereset':
        if ( !$this->perms->get_permissions('history_rollback_extra') )
          return 'Denied!';
        
        // pull existing vote data
        $q = $db->sql_query('SELECT delvotes, delvote_ips FROM ' . table_prefix . "pages WHERE urlname = '$this->page_id' AND namespace = '$this->namespace';");
        if ( !$q )
          $db->_die();
        
        if ( $db->numrows() < 1 )
          return array(
              'success' => false,
              'error' => 'page_not_exist',
              'action' => $log_entry['action']
            );
          
        list($curr_delvotes, $curr_delvote_ips) = $db->fetchrow_num();
        $db->free_result();
        
        // merge with existing votes
        $old_delvote_ips = unserialize($log_entry['page_text']);
        $new_delvote_ips = unserialize($curr_delvote_ips);
        $new_delvote_ips['u'] = array_unique(array_merge($new_delvote_ips['u'], $old_delvote_ips['u']));
        $new_delvote_ips['ip'] = array_unique(array_merge($new_delvote_ips['ip'], $old_delvote_ips['ip']));
        $new_delvotes = count($new_delvote_ips['ip']);
        $new_delvote_ips = $db->escape(serialize($new_delvote_ips));
        
        // update pages table
        $q = $db->sql_query('UPDATE ' . table_prefix . "pages SET delvotes = $new_delvotes, delvote_ips = '$new_delvote_ips' WHERE urlname = '$this->page_id' AND namespace = '$this->namespace';");
        
        $cache->purge('page_meta');
        
        return array(
            'success' => true,
            'dateline' => $dateline,
            'action' => $log_entry['action']
          );
        break;
      default:
        
        return array(
            'success' => false,
            'error' => 'rb_action_not_supported',
            'action' => $log_entry['action']
          );
        
        break;
    }
  }
  
  /**
   * Renames the page
   * @param string New name
   * @return array Standard Enano error/success protocol
   */
  
  function rename_page($new_name)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Check permissions
    if ( !$this->perms->get_permissions('rename') )
    {
      return array(
        'success' => false,
        'error' => 'access_denied'
        );
    }
    
    // If this is the same as the current name, return success
    $page_name = get_page_title_ns($this->page_id, $this->namespace);
    if ( $page_name === $new_name )
    {
      return array(
        'success' => true
        );
    }
    
    // Make sure the name is valid
    $new_name = trim($new_name);
    if ( empty($new_name) )
    {
      return array(
        'success' => false,
        'error' => 'invalid_parameter'
        );
    }
    
    // Log the action
    $username = $db->escape($session->username);
    $page_name = $db->escape($page_name);
    $time = time();
    
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "logs ( log_type, action, page_id, namespace, author, edit_summary, time_id, date_string ) VALUES\n"
                      . "  ( 'page', 'rename', '{$this->page_id}', '{$this->namespace}', '$username', '$page_name', '$time', 'DATE_STRING COLUMN OBSOLETE, USE time_id' );");
    if ( !$q )
      $db->_die();
    
    // Not much to do but to rename it now
    $new_name = $db->escape($new_name);
    $q = $db->sql_query('UPDATE ' . table_prefix . "pages SET name = '$new_name' WHERE urlname = '{$this->page_id}' AND namespace = '{$this->namespace}';");
    if ( !$q )
      $db->_die();
    
    // Update the cache
    $paths->update_metadata_cache();
    
    return array(
      'success' => true
      );
  }
  
  /**
   * Sets the protection level of the page
   * @param int Protection level, one of PROTECT_{FULL,SEMI,NONE}
   * @param string Reason for protection - required
   */
  
  function protect_page($protection_level, $reason)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $cache;
    
    // Validate permissions
    if ( !$this->perms->get_permissions('protect') )
    {
      return array(
        'success' => false,
        'error' => 'access_denied'
        );
    }
    
    // Validate re-auth
    if ( !$session->sid_super )
    {
      return array(
        'success' => false,
        'error' => 'access_denied_need_reauth'
        );
    }
    
    // Validate input
    $reason = trim($reason);
    if ( !in_array($protection_level, array(PROTECT_NONE, PROTECT_FULL, PROTECT_SEMI)) || empty($reason) )
    {
      return array(
        'success' => false,
        'error' => 'invalid_parameter'
        );
    }
    
    // Retrieve page metadata
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    if ( !isPage($pathskey) )
    {
      return array(
        'success' => false,
        'error' => 'page_metadata_not_found'
        );
    }
    $metadata =& $paths->pages[$pathskey];
    
    // Log the action
    $username = $db->escape($session->username);
    $time = time();
    $existing_protection = intval($metadata['protected']);
    $reason = $db->escape($reason);
    
    if ( $existing_protection == $protection_level )
    {
      return array(
        'success' => false,
        'error' => 'protection_already_there'
        );
    }
    
    $action = '[ insanity ]';
    switch($protection_level)
    {
      case PROTECT_FULL: $action = 'prot'; break;
      case PROTECT_NONE: $action = 'unprot'; break;
      case PROTECT_SEMI: $action = 'semiprot'; break;
    }
    
    $sql = 'INSERT INTO ' . table_prefix . "logs ( log_type, action, page_id, namespace, author, edit_summary, time_id, page_text, date_string ) VALUES\n"
         . "  ( 'page', '$action', '{$this->page_id}', '{$this->namespace}', '$username', '$reason', '$time', '$existing_protection', 'DATE_STRING COLUMN OBSOLETE, USE time_id' );";
    if ( !$db->sql_query($sql) )
    {
      $db->die_json();
    }
    
    // Perform the actual protection
    $q = $db->sql_query('UPDATE ' . table_prefix . "pages SET protected = $protection_level WHERE urlname = '{$this->page_id}' AND namespace = '{$this->namespace}';");
    if ( !$q )
      $db->die_json();
    
    $cache->purge('page_meta');
    
    return array(
      'success' => true
      );
  }
  
  /**
   * Sets internal variables.
   * @access private
   */
  
  function _setup($page_id, $namespace, $revision_id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $page_id_cleaned = sanitize_page_id($page_id);
    
    $this->page_id = $page_id_cleaned;
    $this->namespace = $namespace;
    $this->revision_id = $revision_id;
    $this->page_id_unclean = dirtify_page_id($page_id);
    
    $this->perms = $session->fetch_page_acl( $page_id, $namespace );
    
    // resolve namespace
    $this->ns = namespace_factory($this->page_id, $this->namespace, $this->revision_id);
    
    $this->page_exists = $this->ns->exists();
    $this->title = get_page_title_ns($this->page_id, $this->namespace);
    
    profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Ran _setup()");
  }
  
  /**
   * Renders it all in one go, and echoes it out. This assumes that the text is in the DB.
   * @access private
   */
  
  function render($incl_inner_headers = true, $_errormsg = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $output, $lang;
    
    if ( count($this->redirect_stack) > 0 )
    {
      $stack = array_reverse($this->redirect_stack);
      foreach ( $stack as $oldtarget )
      {
        $url = makeUrlNS($oldtarget[1], $oldtarget[0], 'redirect=no', true);
        $page_id_key = $paths->nslist[ $oldtarget[1] ] . $oldtarget[0];
        $page_data = $paths->pages[$page_id_key];
        $title = ( isset($page_data['name']) ) ? $page_data['name'] : $paths->nslist[$oldtarget[1]] . htmlspecialchars( str_replace('_', ' ', dirtify_page_id( $oldtarget[0] ) ) );
        $a = '<a href="' . $url . '">' . $title . '</a>';
        $output->add_after_header('<small>' . $lang->get('page_msg_redirected_from', array('from' => $a)) . '<br /></small>');
      }
    }
    $this->ns->send($incl_inner_headers, $_errormsg);
  }
  
  /**
   * Sends the page header, dependent on, of course, whether we're supposed to.
   */
  
  function header()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( $this->send_headers )
      $template->header();
  }
  
  /**
   * Sends the page footer, dependent on, of course, whether we're supposed to.
   */
  
  function footer()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( $this->send_headers )
      $template->footer();
  }
  
  /**
   * Fetches the raw, unfiltered page text.
   * @access public
   */
  
  function fetch_text()
  {
    return $this->ns->fetch_text();
  }
  
  /**
   * Pushes to the redirect stack and resets the instance. This depends on the page ID and namespace already being validated and sanitized, and does not check the size of the redirect stack.
   * @param string Page ID to redirect to
   * @param string Namespace to redirect to
   * @access private
   */
  
  function _handle_redirect($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    $arr_pid = array($this->page_id, $this->namespace);
    if ( $namespace == 'Special' || $namespace == 'Admin' )
    {
      return $lang->get('page_err_redirect_to_special');
    }
    $looped = false;
    foreach ( $this->redirect_stack as $page )
    {
      if ( $page[0] == $arr_pid[0] && $page[1] == $arr_pid[1] )
      {
        $looped = true;
        break;
      }
    }
    if ( $looped )
    {
      return $lang->get('page_err_redirect_infinite_loop');
    }
    $page_id_key = $paths->nslist[ $namespace ] . sanitize_page_id($page_id);
    if ( !isPage($page_id_key) )
    {
      return $lang->get('page_err_redirect_to_nonexistent');
    }
    $this->redirect_stack[] = $arr_pid;
    
    
    // Nuke the text cache to avoid infinite loops, gah...
    $this->text_cache = '';
    $this->_setup($page_id, $namespace, 0);
    $this->send();
    return true;
  }
  
  /**
   * Send the error message to the user that the access to this page is denied.
   * @access private
   */
  
  function err_access_denied()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $email;
    
    // Log it for crying out loud
    $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(log_type,action,time_id,date_string,author,edit_summary,page_text) VALUES(\'security\', \'illegal_page\', '.time().', \''.enano_date('d M Y h:i a').'\', \''.$db->escape($session->username).'\', \''.$db->escape($_SERVER['REMOTE_ADDR']).'\', \'' . $db->escape(serialize(array($this->page_id, $this->namespace))) . '\')');
    
    $ob = '';
    //$template->tpl_strings['PAGE_NAME'] = 'Access denied';
    $template->tpl_strings['PAGE_NAME'] = htmlspecialchars( $this->title );
      
    if ( $this->send_headers )
    {
      $ob .= $template->getHeader();
    }
    
    if ( count($this->redirect_stack) > 0 )
    {
      $stack = array_reverse($this->redirect_stack);
      foreach ( $stack as $oldtarget )
      {
        $url = makeUrlNS($oldtarget[1], $oldtarget[0], 'redirect=no', true);
        $page_id_key = $paths->nslist[ $oldtarget[1] ] . $oldtarget[0];
        $page_data = $paths->pages[$page_id_key];
        $title = ( isset($page_data['name']) ) ? $page_data['name'] : $paths->nslist[$oldtarget[1]] . htmlspecialchars( str_replace('_', ' ', dirtify_page_id( $oldtarget[0] ) ) );
        $a = '<a href="' . $url . '">' . $title . '</a>';
        
        $url = makeUrlNS($this->namespace, $this->page_id, 'redirect=no', true);
        $page_id_key = $paths->nslist[ $this->namespace ] . $this->page_id;
        $page_data = $paths->pages[$page_id_key];
        $title = ( isset($page_data['name']) ) ? $page_data['name'] : $paths->nslist[$this->namespace] . htmlspecialchars( str_replace('_', ' ', dirtify_page_id( $this->page_id ) ) );
        $b = '<a href="' . $url . '">' . $title . '</a>';
        
        $ob .= '<small>' . $lang->get('page_msg_redirected_from_to', array('from' => $a, 'to' => $b)) . '<br /></small>';
      }
    }
    
    $email_link = $email->encryptEmail(getConfig('contact_email'), '', '', $lang->get('page_err_access_denied_siteadmin'));
    
    $ob .= "<h3>" . $lang->get('page_err_access_denied_title') . "</h3>";
    $ob .= "<p>" . $lang->get('page_err_access_denied_body', array('site_administration' => $email_link)) . "</p>";
    
    if ( $this->send_headers )
    {
      $ob .= $template->getFooter();
    }
    echo $ob;
  }
  
  /**
   * Inform the user of an incorrect or absent password
   * @access private
   */
   
  function err_wrong_password()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $title = $lang->get('page_msg_passrequired_title');
    $message = ( empty($this->password) ) ?
                 '<p>' . $lang->get('page_msg_passrequired') . '</p>' :
                 '<p>' . $lang->get('page_msg_pass_wrong') . '</p>';
    $message .= '<form action="' . makeUrlNS($this->namespace, $this->page_id) . '" method="post">
                   <p>
                     <label>' . $lang->get('page_lbl_password') . ' <input name="pagepass" type="password" /></label>&nbsp;&nbsp;<input type="submit" value="' . $lang->get('page_btn_password_submit') . '" />
                   </p>
                 </form>';
    if ( $this->send_headers )
    {
      $template->tpl_strings['PAGE_NAME'] = $title;
      $template->header();
      echo "$message";
      $template->footer();
    }
    else
    {
      echo "<h2>$title</h2>
            $message";
    }
  }
  
  /**
   * Send the error message to the user complaining that there weren't any rows.
   * @access private
   */
  
  function err_no_rows()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $title = 'No text rows';
    $message = 'While the page\'s existence was verified, there were no rows in the database that matched the query for the text. This may indicate a bug with the software; ask the webmaster for more information. The offending query was:<pre>' . $db->latest_query . '</pre>';
    if ( $this->send_headers )
    {
      $template->tpl_strings['PAGE_NAME'] = $title;
      $template->header();
      echo "<p>$message</p>";
      $template->footer();
    }
    else
    {
      echo "<h2>$title</h2>
            <p>$message</p>";
    }
  }
  
  /**
   * Send an error message and die. For debugging or critical technical errors only - nothing that would under normal circumstances be shown to the user.
   * @param string Error message
   * @param bool If true, send DBAL's debugging information as well
   */
   
  function send_error($message, $sql = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $content = "<p>$message</p>";
    $template->tpl_strings['PAGE_NAME'] = $lang->get('page_msg_general_error');
    
    if ( $this->debug['works'] )
    {
      $content .= $this->debug['backtrace'];
    }
    
    header('HTTP/1.1 500 Internal Server Error');
    
    $template->header();
    echo $content;
    $template->footer();
    
    $db->close();
    
    exit;
    
  }
  
  /**
   * Raises an error.
   * @param string Error string
   */
   
  function raise_error($string)
  {
    if ( !is_string($string) )
      return false;
    $this->_errors[] = $string;
  }
  
  /**
   * Retrieves the latest error from the error stack and returns it ('pops' the error stack)
   * @return string
   */
  
  function pop_error()
  {
    if ( count($this->_errors) < 1 )
      return false;
    return array_pop($this->_errors);
  }
  
} // class PageProcessor

?>
