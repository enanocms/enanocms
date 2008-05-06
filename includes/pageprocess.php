<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
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
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl.html>
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
    
    profiler_log("PageProcessor [{$namespace}:{$page_id}]: Ran initial checks");
    
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
    
    profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Started send process");
    
    if ( !$this->perms->get_permissions('read') )
    {
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
        profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
        return false;
      }
    }
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    $strict_no_headers = false;
    if ( $this->namespace == 'Admin' && strstr($this->page_id, '/') )
    {
      $this->page_id = substr($this->page_id, 0, strpos($this->page_id, '/'));
      $funcname = "page_{$this->namespace}_{$this->page_id}";
      if ( function_exists($funcname) )
      {
        $this->page_exists = true;
      }
    }
    if ( isset($paths->pages[$pathskey]) )
    {
      if ( $paths->pages[$pathskey]['special'] == 1 )
      {
        $this->send_headers = false;
        $strict_no_headers = true;
      }
      if ( isset($paths->pages[$pathskey]['password']) )
      {
        if ( $paths->pages[$pathskey]['password'] != '' && $paths->pages[$pathskey]['password'] != sha1('') )
        {
          $password =& $paths->pages[$pathskey]['password'];
          if ( $this->password != $password )
          {
            $this->err_wrong_password();
            profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
            return false;
          }
        }
      }
    }
    if ( $this->page_exists && $this->namespace != 'Special' && $this->namespace != 'Admin' && $do_stats )
    {
      doStats($this->page_id, $this->namespace);
    }
    if ( $this->namespace == 'Special' || $this->namespace == 'Admin' )
    {
      if ( !$this->page_exists )
      {
        $func_name = "page_{$this->namespace}_{$this->page_id}";
        
        die_semicritical($lang->get('page_msg_admin_404_title'), $lang->get('page_msg_admin_404_body', array('func_name' => $func_name)), (!$this->send_headers));
      }
      $func_name = "page_{$this->namespace}_{$this->page_id}";
      if ( function_exists($func_name) )
      {
        profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Calling special/admin page");
        $result = @call_user_func($func_name);
        profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
        return $result;
      }
      else
      {
        $title = $lang->get('page_err_custompage_function_missing_title');
        $message = $lang->get('page_err_custompage_function_missing_body', array( 'function_name' => $fname ));
                    
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
        profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
        return false;
      }
    }
    else if ( $this->namespace == 'User' && strpos($this->page_id, '/') === false )
    {
      $this->_handle_userpage();
    }
    else if ( ( $this->namespace == 'Template' || $this->namespace == 'System' ) && $this->page_exists )
    {
      $this->header();
      
      $text = $this->fetch_text();
      $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '\\1', $text);
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '', $text);
      
      $text = RenderMan::render( $text );
      
      eval( '?>' . $text );
      
      $this->footer();
    }
    else if ( $this->namespace == 'Anonymous' )
    {
      $uri = scriptPath . '/' . $this->page_id;
      if ( !$this->send_headers )
      {
        $sep = ( strstr($uri, '?') ) ? '&' : '?';
        $uri .= "{$sep}noheaders";
      }
      redirect( $uri, '', '', 0 );
    }
    else if ( !$this->page_exists )
    {
      // Perhaps this is hooked?
      ob_start();
      
      $code = $plugins->setHook('page_not_found');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      
      $ob = ob_get_contents();
      
      if ( empty($ob) )
      {
        $this->err_page_not_existent();
      }
      else
      {
        // Something sent content, so we'll assume the page exist...ed at least according to the plugin
        if ( $this->namespace != 'Special' && $this->namespace != 'Admin' && $do_stats )
        {
          doStats($this->page_id, $this->namespace);
        }
      }
    }
    else // (disabled for compatibility reasons) if ( in_array($this->namespace, array('Article', 'User', 'Project', 'Help', 'File', 'Category')) && $this->page_exists )
    {
      // Send as regular page
      
      // die($this->page_id);
      
      $text = $this->fetch_text();
      if ( $text == 'err_no_text_rows' )
      {
        $this->err_no_rows();
        profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
        return false;
      }
      else
      {
        $redirect = ( isset($_GET['redirect']) ) ? $_GET['redirect'] : 'YES YOU IDIOT';
        if ( preg_match('/^#redirect \[\[([^\]]+)\]\]/i', $text, $match) && $redirect != 'no' )
        {
          // Redirect page!
          $page_to = sanitize_page_id($match[1]);
          $page_id_data = RenderMan::strToPageID($page_to);
          if ( count($this->redirect_stack) >= 3 )
          {
            $this->render( (!$strict_no_headers), '<div class="usermessage"><b>' . $lang->get('page_err_redirects_exceeded') . '</b></div>' );
          }
          else
          {
            $result = $this->_handle_redirect($page_id_data[0], $page_id_data[1]);
            if ( $result !== true )
            {
              // There was some error during the redirect process - usually an infinite redirect
              $this->render( (!$strict_no_headers), '<div class="usermessage"><b>' . $result . '</b></div>' );
            }
          }
        }
        else
        {
          $this->render( (!$strict_no_headers) );
        }
      }
    }
    profiler_log("PageProcessor [{$this->namespace}:{$this->page_id}]: Finished send process");
  }
  
  /**
   * Fetches the wikitext or HTML source for the page.
   * @return string
   */
  
  function fetch_source()
  {
    if ( !$this->perms->get_permissions('view_source') )
    {
      return false;
    }
    if ( !$this->page_exists )
    {
      return '';
    }
    return $this->fetch_text();
  }
  
  /**
   * Updates (saves/changes/edits) the content of the page.
   * @param string The new text for the page
   * @param string A summary of edits made to the page.
   * @param bool If true, the edit is marked as a minor revision
   * @return bool True on success, false on failure. When returning false, it will push errors to the PageProcessor error stack; read with $page->pop_error()
   */
  
  function update_page($text, $edit_summary = false, $minor_edit = false)
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
    $sql = 'INSERT INTO ' . table_prefix . "logs ( time_id, date_string, log_type, action, page_id, namespace, author, page_text, edit_summary, minor_edit )\n"
         . "  VALUES ( $time, '$date_string', 'page', 'edit', '{$this->page_id}', '{$this->namespace}', '$author', '$text', '$edit_summary', $minor_edit );";
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
    
    // Rebuild the search index
    $paths->rebuild_page_index($this->page_id, $this->namespace);
    
    $this->text_cache = $text;
    
    return true;
    
  }
  
  /**
   * Creates the page if it doesn't already exist.
   * @param string Optional page title.
   * @return bool True on success, false on failure.
   */
  
  function create_page($title = false)
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
    if ( $this->namespace == 'Special' || $this->namespace == 'Admin' || $this->namespace == 'Anonymous' )
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
        'visible' => 1,
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
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "pages(name, urlname, namespace, protected, delvotes, delvote_ips, wiki_mode)\n"
                        . "VALUES ( '$name', '$page_id', '$namespace', $protect, 0, '$blank_array', 2 );");
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
        return array_merge($this->rename($log_entry['edit_summary']), array('dateline' => $dateline, 'action' => $log_entry['action']));
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
        if ( !$this->perms->get_permissions('history_rollback_extra') )
          return 'Administrative privileges are required for page undeletion.';
        
        // Rolling back the deletion of a page that was since created?
        $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
        if ( isset($paths->pages[$pathskey]) )
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
    
    // Validate permissions
    if ( !$this->perms->get_permissions('protect') )
    {
      return array(
        'success' => false,
        'error' => 'access_denied'
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
    if ( !isset($paths->pages[$pathskey]) )
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
    
    // Exception for Admin: pages
    if ( $this->namespace == 'Admin' )
    {
      $fname = "page_Admin_{$this->page_id}";
    }
    
    // Does the page "exist"?
    $pathskey = $paths->nslist[$namespace] . $page_id_cleaned;
    
    if ( $paths->page_id == $page_id && $paths->namespace == $namespace && !$paths->page_exists && ( $this->namespace != 'Admin' || ($this->namespace == 'Admin' && !function_exists($fname) ) ) )
    {
      $this->page_exists = false;
    }
    else if ( !isset( $paths->pages[ $pathskey ] ) && ( ( $this->namespace == 'Admin' && !function_exists($fname) ) || ( $this->namespace != 'Admin' ) ) )
    {
      $this->page_exists = false;
    }
    else
    {
      $this->page_exists = true;
    }
    
    // Compatibility with older databases
    if ( strstr($this->page_id, '.2e') && !$this->page_exists )
    {
      $page_id = str_replace('.2e', '.', $page_id);
      
      if ( $paths->page_id == $page_id && $paths->namespace == $namespace && !$paths->page_exists && ( $this->namespace != 'Admin' || ($this->namespace == 'Admin' && !function_exists($fname) ) ) )
      {
        $this->page_exists = false;
      }
      else if ( !isset( $paths->pages[ $paths->nslist[$namespace] . $page_id ] ) && ( $this->namespace == 'Admin' && !function_exists($fname) ) )
      {
        $this->page_exists = false;
      }
      else
      {
        $this->page_exists = true;
      }
      
    }
    
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
    global $lang;
    
    $text = $this->fetch_text();
    $text = preg_replace('/([\s]*)__NOBREADCRUMBS__([\s]*)/', '', $text);
    $text = preg_replace('/([\s]*)__NOTOC__([\s]*)/', '', $text);
    
    $redir_enabled = false;
    if ( preg_match('/^#redirect \[\[([^\]]+?)\]\]/i', $text, $match ) )
    {
      $redir_enabled = true;
      
      $oldtarget = RenderMan::strToPageID($match[1]);
      $oldtarget[0] = sanitize_page_id($oldtarget[0]);
      
      $url = makeUrlNS($oldtarget[1], $oldtarget[0], false, true);
      $page_id_key = $paths->nslist[ $oldtarget[1] ] . $oldtarget[0];
      $page_data = $paths->pages[$page_id_key];
      $title = ( isset($page_data['name']) ) ? $page_data['name'] : $paths->nslist[$oldtarget[1]] . htmlspecialchars( str_replace('_', ' ', dirtify_page_id( $oldtarget[0] ) ) );
      if ( !isset($page_data['name']) )
      {
        $cls = 'class="wikilink-nonexistent"';
      }
      else
      {
        $cls = '';
      }
      $a = '<a ' . $cls . ' href="' . $url . '">' . $title . '</a>';
      $redir_html = '<br /><div class="mdg-infobox">
              <table border="0" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td valign="top">
                    <img alt="Cute wet-floor icon" src="'.scriptPath.'/images/redirector.png" />
                  </td>
                  <td valign="top" style="padding-left: 10px;">
                    ' . $lang->get('page_msg_this_is_a_redirector', array( 'redirect_target' => $a )) . '
                  </td>
                </tr>
              </table>
            </div>
            <br />
            <hr style="margin-left: 1em; width: 200px;" />';
      $text = str_replace($match[0], '', $text);
      $text = trim($text);
    }
    
    $template->tpl_strings['PAGE_NAME'] = htmlspecialchars( $this->title );
    
    $this->header();
    $this->do_breadcrumbs();
    
    if ( $_errormsg )
    {
      echo $_errormsg;
    }
    
    if ( $incl_inner_headers )
    {
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
          echo '<small>' . $lang->get('page_msg_redirected_from', array('from' => $a)) . '<br /></small>';
        }
      }
      display_page_headers();
    }
    
    if ( $this->revision_id )
    {
      echo '<div class="info-box" style="margin-left: 0; margin-top: 5px;">
              <b>' . $lang->get('page_msg_archived_title') . '</b><br />
              ' . $lang->get('page_msg_archived_body', array(
                  'archive_date' => enano_date('F d, Y', $this->revision_time),
                  'archive_time' => enano_date('h:i a', $this->revision_time),
                  'current_link' => makeUrlNS($this->namespace, $this->page_id),
                  'restore_link' => makeUrlNS($this->namespace, $this->page_id, 'do=edit&amp;revid='.$this->revision_id),
                  'restore_onclick' => 'ajaxEditor(\''.$this->revision_id.'\'); return false;',
                )) . '
            </div>';
    }
    
    if ( $redir_enabled )
    {
      echo $redir_html;
    }
    
    $code = $plugins->setHook('pageprocess_render_head');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if ( $incl_inner_headers )
    {
      $text = '?>' . RenderMan::render($text);
    }
    else
    {
      $text = '?>' . $text;
      $text = preg_replace('/<nowiki>(.*?)<\/nowiki>/s', '\\1', $text);
    }
    // echo('<pre>'.htmlspecialchars($text).'</pre>');
    eval ( $text );
    
    $code = $plugins->setHook('pageprocess_render_tail');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if ( $incl_inner_headers )
    {
      display_page_footers();
    }
    
    $this->footer();
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
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !empty($this->text_cache) )
    {
      return $this->text_cache;
    }
    
    if ( $this->revision_id > 0 && is_int($this->revision_id) )
    {
    
      $q = $db->sql_query('SELECT page_text, char_tag, time_id FROM '.table_prefix.'logs WHERE log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $this->page_id . '\' AND namespace=\'' . $this->namespace . '\' AND log_id=' . $this->revision_id . ';');
      if ( !$q )
      {
        $this->send_error('Error during SQL query.', true);
      }
      if ( $db->numrows() < 1 )
      {
        // Compatibility fix for old pages with dots in the page ID
        if ( strstr($this->page_id, '.2e') )
        {
          $db->free_result();
          $page_id = str_replace('.2e', '.', $this->page_id);
          $q = $db->sql_query('SELECT page_text, char_tag, time_id FROM '.table_prefix.'logs WHERE log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $this->namespace . '\' AND log_id=' . $this->revision_id . ';');
          if ( !$q )
          {
            $this->send_error('Error during SQL query.', true);
          }
          if ( $db->numrows() < 1 )
          {
            $this->page_exists = false;
            return 'err_no_text_rows';
          }
        }
        else
        {
          $this->page_exists = false;
          return 'err_no_text_rows';
        }
      }
      else
      {
        $row = $db->fetchrow();
      }
      
      $db->free_result();
      
    }
    else
    {
      
      $q = $db->sql_query('SELECT t.page_text, t.char_tag, l.time_id FROM '.table_prefix."page_text AS t\n"
                        . "  LEFT JOIN " . table_prefix . "logs AS l\n"
                        . "    ON ( l.page_id = t.page_id AND l.namespace = t.namespace )\n"
                        . "  WHERE t.page_id='$this->page_id' AND t.namespace='$this->namespace'\n"
                        . "  ORDER BY l.time_id DESC LIMIT 1;");
      if ( !$q )
      {
        $this->send_error('Error during SQL query.', true);
      }
      if ( $db->numrows() < 1 )
      {
        // Compatibility fix for old pages with dots in the page ID
        if ( strstr($this->page_id, '.2e') )
        {
          $db->free_result();
          $page_id = str_replace('.2e', '.', $this->page_id);
          $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $this->namespace . '\';');
          if ( !$q )
          {
            $this->send_error('Error during SQL query.', true);
          }
          if ( $db->numrows() < 1 )
          {
            $this->page_exists = false;
            return 'err_no_text_rows';
          }
        }
        else
        {
          $this->page_exists = false;
          return 'err_no_text_rows';
        }
      }
      
      $row = $db->fetchrow();
      $db->free_result();
      
    }
    
    if ( !empty($row['char_tag']) )
    {
      // This page text entry uses the old text-escaping format
      $from = array(
          "{APOS:{$row['char_tag']}}",
          "{QUOT:{$row['char_tag']}}",
          "{SLASH:{$row['char_tag']}}"
        );
      $to = array("'", '"',  '\\');
      $row['page_text'] = str_replace($from, $to, $row['page_text']);
    }
    
    $this->text_cache = $row['page_text'];
    
    if ( isset($row['time_id']) )
    {
      $this->revision_time = intval($row['time_id']);
    }
    
    return $row['page_text'];
    
  }
  
  /**
   * Handles the extra overhead required for user pages.
   * @access private
   */
   
  function _handle_userpage()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $email;
    global $lang;
    
    $page_urlname = dirtify_page_id($this->page_id);
    if ( $this->page_id == $paths->page_id && $this->namespace == $paths->namespace )
    {
      $page_name = ( isset($paths->cpage['name']) ) ? $paths->cpage['name'] : $this->page_id;
    }
    else
    {
      $page_name = ( isset($paths->pages[$this->page_id]) ) ? $paths->pages[$this->page_id]['name'] : $this->page_id;
    }
    
    $target_username = strtr($page_urlname, 
      Array(
        '_' => ' ',
        '<' => '&lt;',
        '>' => '&gt;'
        ));
    
    $target_username = preg_replace('/^' . str_replace('/', '\\/', preg_quote($paths->nslist['User'])) . '/', '', $target_username);
    list($target_username) = explode('/', $target_username);
    
    if ( ( $page_name == str_replace('_', ' ', $this->page_id) || $page_name == $paths->nslist['User'] . str_replace('_', ' ', $this->page_id) ) || !$this->page_exists )
    {
      $page_name = $lang->get('userpage_page_title', array('username' => htmlspecialchars($target_username)));
    }
    else
    {
      // User has a custom title for their userpage
      $page_name = $paths->pages[ $paths->nslist[$this->namespace] . $this->page_id ]['name'];
    }
    
    $template->tpl_strings['PAGE_NAME'] = htmlspecialchars($page_name);
    
    $q = $db->sql_query('SELECT u.username, u.user_id AS authoritative_uid, u.real_name, u.email, u.reg_time, u.user_has_avatar, u.avatar_type, x.*, COUNT(c.comment_id) AS n_comments
                           FROM '.table_prefix.'users u
                           LEFT JOIN '.table_prefix.'users_extra AS x
                             ON ( u.user_id = x.user_id OR x.user_id IS NULL ) 
                           LEFT JOIN '.table_prefix.'comments AS c
                             ON ( ( c.user_id=u.user_id AND c.name=u.username AND c.approved=1 ) OR ( c.comment_id IS NULL AND c.approved IS NULL ) )
                           WHERE u.username=\'' . $db->escape($target_username) . '\'
                           GROUP BY u.username, u.user_id, u.real_name, u.email, u.reg_time, u.user_has_avatar, u.avatar_type, x.user_id, x.user_aim, x.user_yahoo, x.user_msn, x.user_xmpp, x.user_homepage, x.user_location, x.user_job, x.user_hobbies, x.email_public;');
    if ( !$q )
      $db->_die();
    
    $user_exists = true;
    
    if ( $db->numrows() < 1 )
    {
      $user_exists = false;
    }
    else
    {
      $userdata = $db->fetchrow();
      if ( $userdata['authoritative_uid'] == 1 )
      {
        // Hide data for anonymous user
        $user_exists = false;
        unset($userdata);
      }
    }
    
    // get the user's rank
    $rank_data = $session->get_user_rank(intval($userdata['authoritative_uid']));
    
    $this->header();
    
    // if ( $send_headers )
    // {
    //  display_page_headers();
    // }
   
    // Start left sidebar: basic user info, latest comments
    
    if ( $user_exists ):
    
    echo '<table border="0" cellspacing="4" cellpadding="0" style="width: 100%;">';
    echo '<tr><td style="width: 150px;" valign="top">';
    
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    
    //
    // Main part of sidebar
    //
    
    // Basic user info
    
    echo '<tr><th class="subhead">' . $lang->get('userpage_heading_basics', array('username' => htmlspecialchars($target_username))) . '</th></tr>';
    
    echo '<tr><td class="row1" style="text-align: center;">';
    if ( $userdata['user_has_avatar'] == '1' )
    {
      echo '<img alt="' . $lang->get('usercp_avatar_image_alt', array('username' => $userdata['username'])) . '" src="' . make_avatar_url(intval($userdata['authoritative_uid']), $userdata['avatar_type']) . '" /><br />';
    }
    // username
    echo '<big><span style="' . $rank_data['rank_style'] . '">' . htmlspecialchars($target_username) . '</span></big><br />';
    // user title, if appropriate
    if ( $rank_data['user_title'] )
      echo htmlspecialchars($rank_data['user_title']) . '<br />';
    // rank
    echo htmlspecialchars($lang->get($rank_data['rank_title']));
    echo '</td></tr>';
    echo '<tr><td class="row3">' . $lang->get('userpage_lbl_joined') . ' ' . enano_date('F d, Y h:i a', $userdata['reg_time']) . '</td></tr>';
    echo '<tr><td class="row1">' . $lang->get('userpage_lbl_num_comments') . ' ' . $userdata['n_comments'] . '</td></tr>';
    
    if ( !empty($userdata['real_name']) )
    {
      echo '<tr><td class="row3">' . $lang->get('userpage_lbl_real_name') . ' ' . $userdata['real_name'] . '</td></tr>';
    }
    
    // Administer user button
    
    if ( $session->user_level >= USER_LEVEL_ADMIN )
    {
      echo '<tr><td class="row1"><a href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'UserManager&src=get&user=' . urlencode($target_username), true) . '" onclick="ajaxAdminUser(\'' . addslashes($target_username) . '\'); return false;">' . $lang->get('userpage_btn_administer_user') . '</a></td></tr>';
    }
    
    // Comments
    
    echo '<tr><th class="subhead">' . $lang->get('userpage_heading_comments', array('username' => htmlspecialchars($target_username))) . '</th></tr>';
    $q = $db->sql_query('SELECT page_id, namespace, subject, time FROM '.table_prefix.'comments WHERE name=\'' . $db->escape($target_username) . '\' AND user_id=' . $userdata['authoritative_uid'] . ' AND approved=1 ORDER BY time DESC LIMIT 5;');
    if ( !$q )
      $db->_die();
    
    $comments = Array();
    $no_comments = false;
    
    if ( $row = $db->fetchrow() )
    {
      do 
      {
        $row['time'] = enano_date('F d, Y', $row['time']);
        $comments[] = $row;
      }
      while ( $row = $db->fetchrow() );
    }
    else
    {
      $no_comments = true;
    }
    
    echo '<tr><td class="row3">';
    echo '<div style="border: 1px solid #000000; padding: 0px; margin: 0; max-height: 200px; clip: rect(0px,auto,auto,0px); overflow: auto; background-color: transparent;" class="tblholder">';
    
    echo '<table border="0" cellspacing="1" cellpadding="4">';
    $class = 'row1';
    
    $tpl = '<tr>
              <td class="{CLASS}">
                <a href="{PAGE_LINK}" <!-- BEGINNOT page_exists -->class="wikilink-nonexistent"<!-- END page_exists -->>{PAGE}</a><br />
                <small>{lang:userpage_comments_lbl_posted} {DATE}<br /></small>
                <b><a href="{COMMENT_LINK}">{SUBJECT}</a></b>
              </td>
            </tr>';
    $parser = $template->makeParserText($tpl);
    
    if ( count($comments) > 0 )
    {
      foreach ( $comments as $comment )
      {
        $c_page_id = $paths->nslist[ $comment['namespace'] ] . sanitize_page_id($comment['page_id']);
        if ( isset($paths->pages[ $c_page_id ]) )
        {
          $parser->assign_bool(array(
            'page_exists' => true
            ));
          $page_title = htmlspecialchars($paths->pages[ $c_page_id ]['name']);
        }
        else
        {
          $parser->assign_bool(array(
            'page_exists' => false
            ));
          $page_title = htmlspecialchars(dirtify_page_id($c_page_id));
        }
        $parser->assign_vars(array(
            'CLASS' => $class,
            'PAGE_LINK' => makeUrlNS($comment['namespace'], sanitize_page_id($comment['page_id'])),
            'PAGE' => $page_title,
            'SUBJECT' => $comment['subject'],
            'DATE' => $comment['time'],
            'COMMENT_LINK' => makeUrlNS($comment['namespace'], sanitize_page_id($comment['page_id']), 'do=comments', true)
          ));
        $class = ( $class == 'row3' ) ? 'row1' : 'row3';
        echo $parser->run();
      }
    }
    else
    {
      echo '<tr><td class="' . $class . '">' . $lang->get('userpage_msg_no_comments') . '</td></tr>';
    }
    echo '</table>';
    
    echo '</div>';
    echo '</td></tr>';
    
    $code = $plugins->setHook('userpage_sidebar_left');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    echo '  </table>
          </div>';
    
    echo '</td><td valign="top" style="padding: 0 10px;">';
    
    else:
    
    // Nothing for now
    
    endif;
    
    // User's own content
    
    $send_headers = $this->send_headers;
    $this->send_headers = false;
    
    if ( $this->page_exists )
    {
      $this->render();
    }
    else
    {
      $this->err_page_not_existent(true);
    }
    
    // Right sidebar
    
    if ( $user_exists ):
    
    echo '</td><td style="width: 150px;" valign="top">';
    
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    
    //
    // Main part of sidebar
    //
    
    // Contact information
    
    echo '<tr><th class="subhead">' . $lang->get('userpage_heading_contact') . '</th></tr>';
    
    $class = 'row3';
    
    if ( $userdata['email_public'] == 1 )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['email']);
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_email') . ' ' . $email_link . '</td></tr>';
    }
    
    $class = ( $class == 'row1' ) ? 'row3' : 'row1';
    if ( $session->user_logged_in )
    {
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_btn_send_pm', array('username' => htmlspecialchars($target_username), 'pm_link' => makeUrlNS('Special', 'PrivateMessages/Compose/to/' . $this->page_id, false, true))) . '</td></tr>';
    }
    else
    {
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_btn_send_pm_guest', array('username' => htmlspecialchars($target_username), 'login_flags' => 'href="' . makeUrlNS('Special', 'Login/' . $paths->nslist[$this->namespace] . $this->page_id) . '" onclick="ajaxStartLogin(); return false;"')) . '</td></tr>';
    }
    
    if ( !empty($userdata['user_aim']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_aim') . ' ' . $userdata['user_aim'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_yahoo']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_yim') . ' ' . $userdata['user_yahoo'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_msn']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['user_msn']);
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_wlm') . ' ' . $email_link . '</td></tr>';
    }
    
    if ( !empty($userdata['user_xmpp']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['user_xmpp']);
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_xmpp') . ' ' . $email_link . '</td></tr>';
    }
    
    // Real life
    
    echo '<tr><th class="subhead">' . $lang->get('userpage_heading_real_life', array('username' => htmlspecialchars($target_username))) . '</th></tr>';
    
    if ( !empty($userdata['user_location']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_location') . ' ' . $userdata['user_location'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_job']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_job') . ' ' . $userdata['user_job'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_hobbies']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_lbl_hobbies') . ' ' . $userdata['user_hobbies'] . '</td></tr>';
    }
    
    if ( empty($userdata['user_location']) && empty($userdata['user_job']) && empty($userdata['user_hobbies']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . $lang->get('userpage_msg_no_contact_info', array('username' => htmlspecialchars($target_username))) . '</td></tr>';
    }
    
    $code = $plugins->setHook('userpage_sidebar_right');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    echo '  </table>
          </div>';
          
    echo '</tr></table>';
    
    else:
    
    if ( !is_valid_ip($target_username) )
    {
      echo '<p>' . $lang->get('userpage_msg_user_not_exist', array('username' => htmlspecialchars($target_username))) . '</p>';
    }
    
    endif;
    
    // if ( $send_headers )
    // {
    //  display_page_footers();
    // }
    
    $this->send_headers = $send_headers;
    unset($send_headers);
    
    $this->footer();
    
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
    if ( !isset($paths->pages[$page_id_key]) )
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
    
    $title = 'Password required';
    $message = ( empty($this->password) ) ?
                 '<p>' . $lang->get('page_msg_passrequired') . '</p>' :
                 '<p>' . $lang->get('page_msg_pass_wrong') . '</p>';
    $message .= '<form action="' . makeUrlNS($this->namespace, $this->page_id) . '" method="post">
                   <p>
                     <label>' . $lang->get('page_lbl_password') . ' <input name="pagepass" type="password" /></label>&nbsp;&nbsp;<input type="submit" value="Submit" />
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
   * Tell the user the page doesn't exist, and present them with their options.
   * @access private
   */
   
  function err_page_not_existent($userpage = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    header('HTTP/1.1 404 Not Found');
    
    $this->header();
    $this->do_breadcrumbs();
    
    $msg = $paths->sysmsg('Page_not_found');
    if ( $msg )
    {
      $msg = RenderMan::render($msg);
      eval( '?>' . $msg );
    }
    else
    {
      if ( $userpage )
      {
        echo '<h3>' . $lang->get('page_msg_404_title') . '</h3>
               <p>' . $lang->get('page_msg_404_body_userpage');
      }
      else
      {
        echo '<h3>' . $lang->get('page_msg_404_title') . '</h3>
               <p>' . $lang->get('page_msg_404_body');
      }
      if ( $session->get_permissions('create_page') )
      {
        echo ' ' . $lang->get('page_msg_404_create', array(
            'create_flags' => 'href="'.makeUrlNS($this->namespace, $this->page_id, 'do=edit', true).'" onclick="ajaxEditor(); return false;"',
            'mainpage_link' => makeUrl(getConfig('main_page'), false, true)
          ));
      }
      else
      {
        echo ' ' . $lang->get('page_msg_404_gohome', array(
            'mainpage_link' => makeUrl(getConfig('main_page'), false, true)
          ));
      }
      echo '</p>';
      if ( $session->get_permissions('history_rollback') )
      {
        $e = $db->sql_query('SELECT * FROM ' . table_prefix . 'logs WHERE action=\'delete\' AND page_id=\'' . $this->page_id . '\' AND namespace=\'' . $this->namespace . '\' ORDER BY time_id DESC;');
        if ( !$e )
        {
          $db->_die('The deletion log could not be selected.');
        }
        if ( $db->numrows() > 0 )
        {
          $r = $db->fetchrow();
          echo '<p>' . $lang->get('page_msg_404_was_deleted', array(
                    'delete_time' => enano_date('d M Y h:i a', $r['time_id']),
                    'delete_reason' => htmlspecialchars($r['edit_summary']),
                    'rollback_flags' => 'href="'.makeUrl($paths->page, 'do=rollback&amp;id='.$r['log_id']).'" onclick="ajaxRollback(\''.$r['log_id'].'\'); return false;"'
                  ))
                . '</p>';
          if ( $session->user_level >= USER_LEVEL_ADMIN )
          {
            echo '<p>' . $lang->get('page_msg_404_admin_opts', array(
                      'detag_link' => makeUrl($paths->page, 'do=detag', true)
                    ))
                  . '</p>';
          }
        }
        $db->free_result();
      }
      echo '<p>
              ' . $lang->get('page_msg_404_http_response') . '
            </p>';
    }
    $this->footer();
  }
  
  /**
   * Echoes out breadcrumb data, if appropriate.
   * @access private
   */
  
  function do_breadcrumbs()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( strpos($this->text_cache, '__NOBREADCRUMBS__') !== false )
      return false;
    
    $mode = getConfig('breadcrumb_mode');
    
    if ( $mode == 'never' )
      // Breadcrumbs are disabled
      return true;
      
    // Minimum depth for breadcrumb display
    $threshold = ( $mode == 'always' ) ? 0 : 1;
    
    $breadcrumb_data = explode('/', $this->page_id);
    if ( count($breadcrumb_data) > $threshold )
    {
      // If we're not on a subpage of the main page, add "Home" to the list
      $show_home = false;
      if ( $mode == 'always' )
      {
        $show_home = true;
      }
      echo '<!-- Start breadcrumbs -->
            <div class="breadcrumbs">
              ';
      if ( $show_home )
      {
        // Display the "home" link first.
        $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
        if ( $pathskey !== getConfig('main_page') )
          echo '<a href="' . makeUrl(getConfig('main_page'), false, true) . '">';
        echo $lang->get('onpage_btn_breadcrumbs_home');
        if ( $pathskey !== getConfig('main_page') )
          echo '</a>';
      }
      foreach ( $breadcrumb_data as $i => $crumb )
      {
        $cumulative = implode('/', array_slice($breadcrumb_data, 0, ( $i + 1 )));
        if ( $show_home && $cumulative === getConfig('main_page') )
          continue;
        if ( $show_home || $i > 0 )
          echo ' &raquo; ';
        $title = ( isPage($cumulative) ) ? get_page_title($cumulative) : get_page_title($crumb);
        if ( $i + 1 == count($breadcrumb_data) )
        {
          echo htmlspecialchars($title);
        }
        else
        {
          $exists = ( isPage($cumulative) ) ? '' : ' class="wikilink-nonexistent"';
          echo '<a href="' . makeUrl($cumulative, false, true) . '"' . $exists . '>' . htmlspecialchars($title) . '</a>';
        }
      }
      echo '</div>
            <!-- End breadcrumbs -->
            ';
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
