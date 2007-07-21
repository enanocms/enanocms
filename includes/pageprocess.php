<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
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
   * Constructor.
   * @param string The page ID (urlname) of the page
   * @param string The namespace of the page
   * @param int Optional. The revision ID to send.
   */
  
  function __construct( $page_id, $namespace, $revision_id = 0 )
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
    if ( !$this->perms->get_permissions('read') )
    {
      $this->err_access_denied();
      return false;
    }
    $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
    $strict_no_headers = false;
    if ( isset($paths->pages[$pathskey]) )
    {
      if ( $paths->pages[$pathskey]['special'] == 1 )
      {
        $this->send_headers = false;
        $strict_no_headers = true;
      }
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
    if ( $this->page_exists && $this->namespace != 'Special' && $this->namespace != 'Admin' && $do_stats )
    {
      doStats($this->page_id, $this->namespace);
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
            $this->render( (!$strict_no_headers), '<div class="usermessage"><b>The maximum number of internal redirects has been exceeded.</b></div>' );
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
    
    // Compatibility with older databases
    if ( strstr($this->page_id, '.2e') && !$this->page_exists )
    {
      $page_id = str_replace('.2e', '.', $page_id);
      
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
    
    $this->title = get_page_title_ns($this->page_id, $this->namespace);
    
  }
  
  /**
   * Renders it all in one go, and echoes it out. This assumes that the text is in the DB.
   * @access private
   */
  
  function render($incl_inner_headers = true, $_errormsg = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $text = $this->fetch_text();
    
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
                    <b>This page is a <i>redirector</i>.</b><br />
                    This means that this page will not show its own content by default. Instead it will display the contents of the page it redirects to.<br /><br />
                    To create a redirect page, make the <i>first characters</i> in the page content <tt>#redirect [[Page_ID]]</tt>. For more information, see the
                    Enano <a href="http://enanocms.org/Help:Wiki_formatting" onclick="window.open(this.href); return false;">Wiki formatting guide</a>.<br /><br />
                    This page redirects to ' . $a . '.
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
          echo '<small>(Redirected from ' . $a . ')<br /></small>';
        }
      }
      display_page_headers();
    }
    
    if ( $this->revision_id )
    {
      echo '<div class="info-box" style="margin-left: 0; margin-top: 5px;"><b>Notice:</b><br />The page you are viewing was archived on '.date('F d, Y \a\t h:i a', $this->revision_id).'.<br /><a href="'.makeUrlNS($this->namespace, $this->page_id).'" onclick="ajaxReset(); return false;">View current version</a>  |  <a href="'.makeUrlNS($this->namespace, $this->pageid, 'do=rollback&amp;id='.$this->revision_id).'" onclick="ajaxRollback(\''.$this->revision_id.'\')">Restore this version</a></div><br />';
    }
    
    if ( $redir_enabled )
    {
      echo $redir_html;
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
    
      $q = $db->sql_query('SELECT page_text, char_tag, date_string FROM '.table_prefix.'logs WHERE page_id=\'' . $this->page_id . '\' AND namespace=\'' . $this->namespace . '\' AND time_id=' . $this->revision_id . ';');
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
          $q = $db->sql_query('SELECT page_text, char_tag, date_string FROM '.table_prefix.'logs WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $this->namespace . '\' AND time_id=' . $this->revision_id . ';');
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
      
      $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\'' . $this->page_id . '\' AND namespace=\'' . $this->namespace . '\';');
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
    
    if ( $this->page_id == $paths->cpage['urlname_nons'] && $this->namespace == $paths->namespace )
    {
      $page_name = ( isset($paths->cpage['name']) ) ? $paths->cpage['name'] : $this->page_id;
    }
    else
    {
      $page_name = ( isset($paths->pages[$this->page_id]) ) ? $paths->pages[$this->page_id]['name'] : $this->page_id;
    }
    
    $target_username = strtr($page_name, 
      Array(
        '_' => ' ',
        '<' => '&lt;',
        '>' => '&gt;'
        ));
    
    $target_username = preg_replace('/^' . preg_quote($paths->nslist['User']) . '/', '', $target_username);
    
    if ( ( $page_name == str_replace('_', ' ', $this->page_id) || $page_name == $paths->nslist['User'] . str_replace('_', ' ', $this->page_id) ) || !$this->page_exists )
    {
      $page_name = "$target_username's user page";
    }
    else
    {
      // User has a custom title for their userpage
      $page_name = $paths->pages[ $paths->nslist[$this->namespace] . $this->page_id ]['name'];
    }
    
    $template->tpl_strings['PAGE_NAME'] = htmlspecialchars($page_name);
    
    $q = $db->sql_query('SELECT u.username, u.user_id AS authoritative_uid, u.real_name, u.email, u.reg_time, x.*, COUNT(c.comment_id) AS n_comments
                           FROM '.table_prefix.'users u
                           LEFT JOIN '.table_prefix.'users_extra AS x
                             ON ( u.user_id = x.user_id OR x.user_id IS NULL ) 
                           LEFT JOIN '.table_prefix.'comments AS c
                             ON ( ( c.user_id=u.user_id AND c.name=u.username AND c.approved=1 ) OR ( c.comment_id IS NULL AND c.approved IS NULL ) )
                           WHERE u.username=\'' . $db->escape($target_username) . '\'
                           GROUP BY u.user_id;');
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
    
    echo '<tr><th class="subhead">All about ' . htmlspecialchars($target_username) . '</th></tr>';
    echo '<tr><td class="row3">Joined: ' . date('F d, Y h:i a', $userdata['reg_time']) . '</td></tr>';
    echo '<tr><td class="row1">Total comments: ' . $userdata['n_comments'] . '</td></tr>';
    
    if ( !empty($userdata['real_name']) )
    {
      echo '<tr><td class="row3">Real name: ' . $userdata['real_name'] . '</td></tr>';
    }
    
    // Comments
    
    echo '<tr><th class="subhead">' . htmlspecialchars($target_username) . '\'s latest comments</th></tr>';
    $q = $db->sql_query('SELECT page_id, namespace, subject, time FROM '.table_prefix.'comments WHERE name=\'' . $db->escape($target_username) . '\' AND user_id=' . $userdata['authoritative_uid'] . ' AND approved=1 ORDER BY time DESC LIMIT 5;');
    if ( !$q )
      $db->_die();
    
    $comments = Array();
    $no_comments = false;
    
    if ( $row = $db->fetchrow() )
    {
      do 
      {
        $row['time'] = date('F d, Y', $row['time']);
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
                <small>Posted {DATE}<br /></small>
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
          $page_title = $paths->pages[ $c_page_id ]['name'];
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
      echo '<tr><td class="' . $class . '">This user has not posted any comments.</td></tr>';
    }
    echo '</table>';
    
    echo '</div>';
    echo '</td></tr>';
            
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
    
    echo '<tr><th class="subhead">Get in touch</th></tr>';
    
    $class = 'row3';
    
    if ( $userdata['email_public'] == 1 )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['email']);
      echo '<tr><td class="'.$class.'">E-mail address: ' . $email_link . '</td></tr>';
    }
    
    $class = ( $class == 'row1' ) ? 'row3' : 'row1';
    if ( $session->user_logged_in )
    {
      echo '<tr><td class="'.$class.'">Send ' . htmlspecialchars($target_username) . ' a <a href="' . makeUrlNS('Special', 'PrivateMessages/Compose/to/' . $this->page_id, false, true) . '">Private Message</a>!</td></tr>';
    }
    else
    {
      echo '<tr><td class="'.$class.'">You could send ' . htmlspecialchars($target_username) . ' a private message if you were <a href="' . makeUrlNS('Special', 'Login/' . $paths->nslist[$this->namespace] . $this->page_id) . '">logged in</a>.</td></tr>';
    }
    
    if ( !empty($userdata['user_aim']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">AIM: ' . $userdata['user_aim'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_yahoo']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">Yahoo! IM: ' . $userdata['user_yahoo'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_msn']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['user_msn']);
      echo '<tr><td class="'.$class.'">WLM: ' . $email_link . '</td></tr>';
    }
    
    if ( !empty($userdata['user_xmpp']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      $email_link = $email->encryptEmail($userdata['user_xmpp']);
      echo '<tr><td class="'.$class.'">XMPP/Jabber: ' . $email_link . '</td></tr>';
    }
    
    // Real life
    
    echo '<tr><th class="subhead">' . htmlspecialchars($target_username) . ' in real life</th></tr>';
    
    if ( !empty($userdata['user_location']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">Location: ' . $userdata['user_location'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_job']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">Job/occupation: ' . $userdata['user_job'] . '</td></tr>';
    }
    
    if ( !empty($userdata['user_hobbies']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">Enjoys: ' . $userdata['user_hobbies'] . '</td></tr>';
    }
    
    if ( empty($userdata['user_location']) && empty($userdata['user_job']) && empty($userdata['user_hobbies']) )
    {
      $class = ( $class == 'row1' ) ? 'row3' : 'row1';
      echo '<tr><td class="'.$class.'">' . htmlspecialchars($target_username) . ' hasn\'t posted any real-life contact information.</td></tr>';
    }
    
    echo '  </table>
          </div>';
          
    echo '</tr></table>';
    
    else:
    
    echo '<p>Additional information: user "' . htmlspecialchars($target_username) . '" does not exist.</p>';
    
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
    $arr_pid = array($this->page_id, $this->namespace);
    if ( $namespace == 'Special' || $namespace == 'Admin' )
    {
      return 'This page redirects to a Special or Administration page, which is not allowed.';
    }
    if ( in_array($this->redirect_stack, $arr_pid) )
    {
      return 'This page infinitely redirects with another page (or another series of pages), and the infinite redirect was trapped.';
    }
    $page_id_key = $paths->nslist[ $namespace ] . $page_id;
    if ( !isset($paths->pages[$page_id_key]) )
    {
      return 'This page redirects to another page that doesn\'t exist.';
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
   * Inform the user of an incorrect or absent password
   * @access private
   */
   
  function err_wrong_password()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $title = 'Password required';
    $message = ( empty($this->password) ) ? '<p>Access to this page requires a password. Please enter the password for this page below:</p>' : '<p>The password you entered for this page was incorrect. Please enter the password for this page below:</p>';
    $message .= '<form action="' . makeUrlNS($this->namespace, $this->page_id) . '" method="post">
                   <p>
                     <label>Password: <input name="pagepass" type="password" /></label>&nbsp;&nbsp;<input type="submit" value="Submit" />
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
    
    $this->header();
    header('HTTP/1.1 404 Not Found');
    if ( $userpage )
    {
      echo '<h3>There is no page with this title yet.</h3>
             <p>This user has not created his or her user page yet.';
    }
    else
    {
      echo '<h3>There is no page with this title yet.</h3>
             <p>You have requested a page that doesn\'t exist yet.';
    }
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
        echo '<p><b>This page was deleted on ' . $r['date_string'] . '.</b> The stated reason was:</p><blockquote>' . $r['edit_summary'] . '</blockquote><p>You can probably <a href="'.makeUrl($paths->page, 'do=rollback&amp;id='.$r['time_id']).'" onclick="ajaxRollback(\''.$r['time_id'].'\'); return false;">roll back</a> the deletion.</p>';
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
  
  function PageProcessor( $page_id, $namespace, $revision_id = 0 )
  {
    $this->__construct($page_id, $namespace, $revision_id);
  }
  
  /**
   * Send an error message and die. For debugging or critical technical errors only - nothing that would under normal circumstances be shown to the user.
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
