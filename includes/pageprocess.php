<?php
/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 3 (Druid)
 * pageprocess.php - intelligent retrieval of pages
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Class to handle fetching page text (possibly from a cache) and formatting it.
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
      'enable' => true,
      'works'  => false
    );
  
  /**
   * Constructor.
   * @param string The page ID (urlname) of the page
   * @param string The namespace of the page
   */
  
  function __construct( $page_id, $namespace )
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
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
    
    $this->_setup( $page_id, $namespace );
    
  }
  
  /**
   * The main method to send the page content. Also responsible for checking permissions.
   */
  
  function send()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !$this->perms->get_permissions('read') )
    {
      $this->err_access_denied();
      return false;
    }
    if ( $this->namespace == 'Special' || $this->namespace == 'Admin' )
    {
      if ( !$this->page_exists )
      {
        redirect( makeUrl(getConfig('main_page')), 'Can\'t find special page', 'The special or administration page you requested does not exist. You will now be transferred to the main page.', 2 );
      }
      $func_name = "page_{$this->namespace}_{$this->page_id}";
      if ( function_exists($func_name) )
      {
        return @call_user_func($func_name);
      }
      else
      {
        $title = 'Page backend not found';
        $message = "The administration page you are looking for was properly registered using the page API, but the backend function
                    (<tt>$fname</tt>) was not found. If this is a plugin page, then this is almost certainly a bug with the plugin.";
                    
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
        return false;
      }
    }
    else if ( $this->namespace == 'User' )
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
      
      echo $text;
      
      $this->footer();
      
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
    }
    else // (disabled for compatibility reasons) if ( in_array($this->namespace, array('Article', 'User', 'Project', 'Help', 'File', 'Category')) && $this->page_exists )
    {
      // Send as regular page
      $text = $this->fetch_text();
      if ( $text == 'err_no_text_rows' )
      {
        $this->err_no_rows();
        return false;
      }
      else
      {
        $this->render();
      }
    }
  }
  
  /**
   * Sets internal variables.
   * @access private
   */
  
  function _setup($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $page_id_cleaned = sanitize_page_id($page_id);
    
    $this->page_id = $page_id_cleaned;
    $this->namespace = $namespace;
    $this->page_id_unclean = dirtify_page_id($page_id);
    
    $this->perms = $session->fetch_page_acl( $page_id, $namespace );
    
    // Exception for Admin: pages
    if ( $this->namespace == 'Admin' )
    {
      $fname = "page_Admin_{$this->page_id}";
    }
    
    // Does the page "exist"?
    if ( $paths->cpage['urlname_nons'] == $page_id && $paths->namespace == $namespace && !$paths->page_exists && ( $this->namespace != 'Admin' || ($this->namespace == 'Admin' && !function_exists($fname) ) ) )
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
  
  /**
   * Renders it all in one go, and echoes it out. This assumes that the text is in the DB.
   * @access private
   */
  
  function render()
  {
    $text = $this->fetch_text();
    
    $this->header();
    if ( $this->send_headers )
    {
      display_page_headers();
    }
    
    $text = '?>' . RenderMan::render($text);
    // echo('<pre>'.htmlspecialchars($text).'</pre>');
    eval ( $text );
    
    if ( $this->send_headers )
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
    
    $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\'' . $this->page_id . '\' AND namespace=\'' . $this->namespace . '\';');
    if ( !$q )
    {
      $this->send_error('Error during SQL query.', true);
    }
    if ( $db->numrows() < 1 )
    {
      $this->page_exists = false;
      return 'err_no_text_rows';
    }
    
    $row = $db->fetchrow();
    $db->free_result();
    
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
    
    return $row['page_text'];
    
  }
  
  /**
   * Handles the extra overhead required for user pages.
   * @access private
   */
   
  function _handle_userpage()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( $this->page_id == $paths->cpage['urlname_nons'] && $this->namespace == $paths->namespace )
    {
      $page_name = ( isset($paths->cpage['name']) ) ? $paths->cpage['name'] : $this->page_id;
    }
    else
    {
      $page_name = ( isset($paths->pages[$this->page_id]) ) ? $paths->pages[$this->page_id]['name'] : $this->page_id;
    }
    
    if ( $page_name == str_replace('_', ' ', $this->page_id) || $page_name == $paths->nslist['User'] . str_replace('_', ' ', $this->page_id) )
    {
      $target_username = strtr($page_name, 
        Array(
          '_' => ' ',
          '<' => '&lt;',
          '>' => '&gt;'
          ));
      $target_username = preg_replace('/^' . preg_quote($paths->nslist['User']) . '/', '', $target_username);
      $page_name = "$target_username's user page";
    }
    else
    {
      // User has a custom title for their userpage
      $page_name = $paths->pages[ $paths->nslist[$this->namespace] . $this->page_id ]['name'];
    }
    
    $template->tpl_strings['PAGE_NAME'] = htmlspecialchars($page_name);
    
    $this->header();
    
    if ( $send_headers )
    {
      display_page_headers();
    }
   
    /*
    // Start left sidebar: basic user info, latest comments
    
    echo '<table border="0" cellspacing="4" cellpadding="0" style="width: 100%;">';
    echo '<tr><td style="width: 150px;">';
    
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    
    // Main part of sidebar
            
    echo '  </table>
          </div>';
    
    echo '</td><td>';
    */
    
    // User's own content
    
    $send_headers = $this->send_headers;
    $this->send_headers = false;
    
    if ( $this->page_exists )
    {
      $this->render();
    }
    else
    {
      $this->err_page_not_existent();
    }
    
    /*
    
    // Right sidebar
    
    echo '</td><td style="width: 150px;">';
    
    echo '<div class="tblholder">
            <table border="0" cellspacing="1" cellpadding="4">';
    
    // Main part of sidebar
            
    echo '  </table>
          </div>';
          
    echo '</tr></table>';
    
    if ( $send_headers )
    {
      display_page_footers();
    }
    
    */
    
    $this->send_headers = $send_headers;
    unset($send_headers);
    
    $this->footer();
    
  }
  
  /**
   * Send the error message to the user that the access to this page is denied.
   * @access private
   */
  
  function err_access_denied()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $ob = '';
    $template->tpl_strings['PAGE_NAME'] = 'Access denied';
      
    if ( $this->send_headers )
    {
      $ob .= $template->getHeader();
    }
    
    $ob .= '<div class="error-box"><b>Access to this page is denied.</b><br />This may be because you are not logged in or you have not met certain criteria for viewing this page.</div>';
    
    if ( $this->send_headers )
    {
      $ob .= $template->getFooter();
    }
    echo $ob;
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
   
  function err_page_not_existent()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->header();
    header('HTTP/1.1 404 Not Found');
    echo '<h3>There is no page with this title yet.</h3>
           <p>You have requested a page that doesn\'t exist yet.';
    if ( $session->get_permissions('create_page') )
    {
      echo ' You can <a href="'.makeUrlNS($this->namespace, $this->page_id, 'do=edit', true).'" onclick="ajaxEditor(); return false;">create this page</a>, or return to the <a href="'.makeUrl(getConfig('main_page')).'">homepage</a>.';
    }
    else
    {
      echo ' Return to the <a href="'.makeUrl(getConfig('main_page')).'">homepage</a>.</p>';
    }
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
        echo '<p>This page also appears to have some log entries in the database - it seems that it was deleted on ' . $r['date_string'] . '. You can probably <a href="'.makeUrl($paths->page, 'do=rollback&amp;id='.$r['time_id']).'" onclick="ajaxRollback(\''.$r['time_id'].'\'); return false;">roll back</a> the deletion.</p>';
      }
      $db->free_result();
    }
    echo '<p>
            HTTP Error: 404 Not Found
          </p>';
    $this->footer();
  }
  
  /**
   * PHP 4 constructor.
   * @see PageProcessor::__construct()
   */
  
  function PageProcessor( $page_id, $namespace )
  {
    $this->__construct($page_id, $namespace);
  }
  
  /**
   * Send an error message and die
   * @var string Error message
   * @var bool If true, send DBAL's debugging information as well
   */
   
  function send_error($message, $sql = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $content = "<p>$message</p>";
    $template->tpl_strings['PAGE_NAME'] = 'General error in page fetcher';
    
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
  
} // class PageProcessor

?>
