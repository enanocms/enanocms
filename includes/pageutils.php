<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * pageutils.php - a class that handles raw page manipulations, used mostly by AJAX requests or their old-fashioned form-based counterparts
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class PageUtils {
  
  /**
   * Tell if a username is used or not.
   * @param $name the name to check for
   * @return string
   */
  
  function checkusername($name)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $q = $db->sql_query('SELECT username FROM ' . table_prefix.'users WHERE username=\'' . $db->escape(rawurldecode($name)) . '\'');
    if ( !$q )
    {
      die(mysql_error());
    }
    if ( $db->numrows() < 1)
    {
      $db->free_result(); return('good');
    }
    else
    {
      $db->free_result(); return('bad');
    }
  }
  
  /**
   * Get the wiki formatting source for a page
   * @param $page the full page id (Namespace:Pagename)
   * @return string
   * @todo (DONE) Make it require a password (just for security purposes)
   */
   
  function getsource($page, $password = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!isset($paths->pages[$page]))
    {
      return '';
    }
    
    if(strlen($paths->pages[$page]['password']) == 40)
    {
      if(!$password || ( $password != $paths->pages[$page]['password']))
      {
        return 'invalid_password';
      }
    }
    
    if(!$session->get_permissions('view_source')) // Dependencies handle this for us - this also checks for read privileges
      return 'access_denied';
    $pid = RenderMan::strToPageID($page);
    if($pid[1] == 'Special' || $pid[1] == 'Admin')
    {
      die('This type of page (' . $paths->nslist[$pid[1]] . ') cannot be edited because the page source code is not stored in the database.');
    }
    
    $e = $db->sql_query('SELECT page_text,char_tag FROM ' . table_prefix.'page_text WHERE page_id=\'' . $pid[0] . '\' AND namespace=\'' . $pid[1] . '\'');
    if ( !$e )
    {
      $db->_die('The page text could not be selected.');
    }
    if( $db->numrows() < 1 )
    {
      return ''; //$db->_die('There were no rows in the text table that matched the page text query.');
    }
    
    $r = $db->fetchrow();
    $db->free_result();
    $message = $r['page_text'];
    
    return htmlspecialchars($message);
  }
  
  /**
   * Basically a frontend to RenderMan::getPage(), with the ability to send valid data for nonexistent pages
   * @param $page the full page id (Namespace:Pagename)
   * @param $send_headers true if the theme headers should be sent (still dependent on current page settings), false otherwise
   * @return string
   */
  
  function getpage($page, $send_headers = false, $hist_id = false)
  {
    die('PageUtils->getpage is deprecated.');
    global $db, $session, $paths, $template, $plugins; // Common objects
    ob_start();
    $pid = RenderMan::strToPageID($page);
    //die('<pre>'.print_r($pid, true).'</pre>');
    if(isset($paths->pages[$page]['password']) && strlen($paths->pages[$page]['password']) == 40)
    {
      password_prompt($page);
    }
    if(isset($paths->pages[$page]))
    {
      doStats($pid[0], $pid[1]);
    }
    if($paths->custom_page || $pid[1] == 'Special')
    {
      // If we don't have access to the page, get out and quick!
      if(!$session->get_permissions('read') && $pid[0] != 'Login' && $pid[0] != 'Register')
      {
        $template->tpl_strings['PAGE_NAME'] = 'Access denied';
        
        if ( $send_headers )
        {
          $template->header();
        }
        
        echo '<div class="error-box"><b>Access to this page is denied.</b><br />This may be because you are not logged in or you have not met certain criteria for viewing this page.</div>';
        
        if ( $send_headers )
        {
          $template->footer();
        }
        
        $r = ob_get_contents();
        ob_end_clean();
        return $r;
      }
      
      $fname = 'page_' . $pid[1] . '_' . $paths->pages[$page]['urlname_nons'];
      @call_user_func($fname);
      
    }
    else if ( $pid[1] == 'Admin' )
    {
      // If we don't have access to the page, get out and quick!
      if(!$session->get_permissions('read'))
      {
        $template->tpl_strings['PAGE_NAME'] = 'Access denied';
        if ( $send_headers )
        {
          $template->header();
        }
        echo '<div class="error-box"><b>Access to this page is denied.</b><br />This may be because you are not logged in or you have not met certain criteria for viewing this page.</div>';
        if ( $send_headers )
        {
          $template->footer();
        }
        $r = ob_get_contents();
        ob_end_clean();
        return $r;
      }
      
      $fname = 'page_' . $pid[1] . '_' . $pid[0];
      if ( !function_exists($fname) )
      {
        $title = 'Page backend not found';
        $message = "The administration page you are looking for was properly registered using the page API, but the backend function
                    (<tt>$fname</tt>) was not found. If this is a plugin page, then this is almost certainly a bug with the plugin.";
        if ( $send_headers )
        {
          die_friendly($title, "<p>$message</p>");
        }
        else
        {
          echo "<h2>$title</h2>\n<p>$message</p>";
        }
      }
      @call_user_func($fname);
    }
    else if ( !isset( $paths->pages[$page] ) )
    {
      ob_start();
      $code = $plugins->setHook('page_not_found');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      $text = ob_get_contents();
      if ( $text != '' )
      {
        ob_end_clean();
        return $text;
      }
      $template->header();
      if($m = $paths->sysmsg('Page_not_found'))
      {
        eval('?>'.RenderMan::render($m));
      }
      else
      {
        header('HTTP/1.1 404 Not Found');
        echo '<h3>There is no page with this title yet.</h3>
               <p>You have requested a page that doesn\'t exist yet.';
        if($session->get_permissions('create_page')) echo ' You can <a href="'.makeUrl($paths->page, 'do=edit', true).'" onclick="ajaxEditor(); return false;">create this page</a>, or return to the <a href="'.makeUrl(getConfig('main_page')).'">homepage</a>.';
        else echo ' Return to the <a href="'.makeUrl(getConfig('main_page')).'">homepage</a>.</p>';
        if ( $session->get_permissions('history_rollback') )
        {
          $e = $db->sql_query('SELECT * FROM ' . table_prefix.'logs WHERE action=\'delete\' AND page_id=\'' . $paths->cpage['urlname_nons'] . '\' AND namespace=\'' . $pid[1] . '\' ORDER BY time_id DESC;');
          if ( !$e )
          {
            $db->_die('The deletion log could not be selected.');
          }
          if ($db->numrows() > 0 )
          {
            $r = $db->fetchrow();
            echo '<p>This page also appears to have some log entries in the database - it seems that it was deleted on ' . $r['date_string'] . '. You can probably <a href="'.makeUrl($paths->page, 'do=rollback&amp;id=' . $r['time_id']) . '" onclick="ajaxRollback(\'' . $r['time_id'] . '\'); return false;">roll back</a> the deletion.</p>';
          }
          $db->free_result();
        }
        echo '<p>
                HTTP Error: 404 Not Found
              </p>';
      }
      $template->footer();
    }
    else
    {
      
      // If we don't have access to the page, get out and quick!
      if(!$session->get_permissions('read'))
      {
        $template->tpl_strings['PAGE_NAME'] = 'Access denied';
        if($send_headers) $template->header();
        echo '<div class="error-box"><b>Access to this page is denied.</b><br />This may be because you are not logged in or you have not met certain criteria for viewing this page.</div>';
        if($send_headers) $template->footer();
        $r = ob_get_contents();
        ob_end_clean();
        return $r;
      }
      
      ob_start();
      $code = $plugins->setHook('page_custom_handler');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      $text = ob_get_contents();
      if ( $text != '' )
      {
        ob_end_clean();
        return $text;
      }
      
      if ( $hist_id )
      {
        $e = $db->sql_query('SELECT page_text,date_string,char_tag FROM ' . table_prefix.'logs WHERE page_id=\'' . $paths->pages[$page]['urlname_nons'] . '\' AND namespace=\'' . $pid[1] . '\' AND log_type=\'page\' AND action=\'edit\' AND time_id=' . $db->escape($hist_id) . '');
        if($db->numrows() < 1)
        {
          $db->_die('There were no rows in the text table that matched the page text query.');
        }
        $r = $db->fetchrow();
        $db->free_result();
        $message = '<div class="info-box" style="margin-left: 0; margin-top: 5px;"><b>Notice:</b><br />The page you are viewing was archived on ' . $r['date_string'] . '.<br /><a href="'.makeUrl($page).'" onclick="ajaxReset(); return false;">View current version</a>  |  <a href="'.makeUrl($page, 'do=rollback&amp;id=' . $hist_id) . '" onclick="ajaxRollback(\'' . $hist_id . '\')">Restore this version</a></div><br />'.RenderMan::render($r['page_text']);
        
        if( !$paths->pages[$page]['special'] )
        {
          if($send_headers)
          {
            $template->header(); 
          }
          display_page_headers();
        }
        
        eval('?>' . $message);
        
        if( !$paths->pages[$page]['special'] )
        {
          display_page_footers();
          if($send_headers)
          {
            $template->footer();
          }
        }
        
      } else {
        if(!$paths->pages[$page]['special'])
        {
          $message = RenderMan::getPage($paths->pages[$page]['urlname_nons'], $pid[1]);
        }
        else
        {
          $message = RenderMan::getPage($paths->pages[$page]['urlname_nons'], $pid[1], 0, false, false, false, false);
        }
        // This line is used to debug wikiformatted code
        // die('<pre>'.htmlspecialchars($message).'</pre>');
        
        if( !$paths->pages[$page]['special'] )
        {
          if($send_headers)
          {
            $template->header(); 
          }
          display_page_headers();
        }

        // This is it, this is what all of Enano has been working up to...
        
        eval('?>' . $message);
        
        if( !$paths->pages[$page]['special'] )
        {
          display_page_footers();
          if($send_headers)
          {
            $template->footer();
          }
        }
      }
    }
    $ret = ob_get_contents();
    ob_end_clean();
    return $ret;
  }
  
  /**
   * Writes page data to the database, after verifying permissions and running the XSS filter
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $message the text to save
   * @return string
   */
   
  function savepage($page_id, $namespace, $message, $summary = 'No edit summary given', $minor = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $uid = sha1(microtime());
    $pname = $paths->nslist[$namespace] . $page_id;
    
    if(!$session->get_permissions('edit_page'))
      return 'Access to edit pages is denied.';
    
    if(!isset($paths->pages[$pname]))
    {
      $create = PageUtils::createPage($page_id, $namespace);
      if ( $create != 'good' )
        return 'The page did not exist, and I was not able to create it. The reported error was: ' . $create;
      $paths->page_exists = true;
    }
    
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    if(($prot || !$wiki) && $session->user_level < USER_LEVEL_ADMIN ) return('You are not authorized to edit this page.');
    
    // Strip potentially harmful tags and PHP from the message, dependent upon permissions settings
    $message = RenderMan::preprocess_text($message, false, false);
    
    $msg = $db->escape($message);
    
    $minor = $minor ? 'true' : 'false';
    $q='INSERT INTO ' . table_prefix.'logs(log_type,action,time_id,date_string,page_id,namespace,page_text,char_tag,author,edit_summary,minor_edit) VALUES(\'page\', \'edit\', '.time().', \''.date('d M Y h:i a').'\', \'' . $paths->cpage['urlname_nons'] . '\', \'' . $paths->namespace . '\', \'' . $msg . '\', \'' . $uid . '\', \'' . $session->username . '\', \'' . $db->escape(htmlspecialchars($summary)) . '\', ' . $minor . ');';
    if(!$db->sql_query($q)) $db->_die('The history (log) entry could not be inserted into the logs table.');
    
    $q = 'UPDATE ' . table_prefix.'page_text SET page_text=\'' . $msg . '\',char_tag=\'' . $uid . '\' WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';';
    $e = $db->sql_query($q);
    if(!$e) $db->_die('Enano was unable to save the page contents. Your changes have been lost <tt>:\'(</tt>.');
      
    $paths->rebuild_page_index($page_id, $namespace);
      
    return 'good';
  }
  
  /**
   * Creates a page, both in memory and in the database.
   * @param string $page_id
   * @param string $namespace
   * @return bool true on success, false on failure
   */
  
  function createPage($page_id, $namespace, $name = false, $visible = 1)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(in_array($namespace, Array('Special', 'Admin')))
    {
      // echo '<b>Notice:</b> PageUtils::createPage: You can\'t create a special page in the database<br />';
      return 'You can\'t create a special page in the database';
    }
    
    if(!isset($paths->nslist[$namespace]))
    {
      // echo '<b>Notice:</b> PageUtils::createPage: Couldn\'t look up the namespace<br />';
      return 'Couldn\'t look up the namespace';
    }
    
    $pname = $paths->nslist[$namespace] . $page_id;
    if(isset($paths->pages[$pname]))
    {
      // echo '<b>Notice:</b> PageUtils::createPage: Page already exists<br />';
      return 'Page already exists';
    }
    
    if(!$session->get_permissions('create_page'))
    {
      // echo '<b>Notice:</b> PageUtils::createPage: Not authorized to create pages<br />';
      return 'Not authorized to create pages';
    }
    
    if($session->user_level < USER_LEVEL_ADMIN && $namespace == 'System')
    {
      // echo '<b>Notice:</b> PageUtils::createPage: Not authorized to create system messages<br />';
      return 'Not authorized to create system messages';
    }
    
    if ( substr($page_id, 0, 8) == 'Project:' )
    {
      // echo '<b>Notice:</b> PageUtils::createPage: Prefix "Project:" is reserved<br />';
      return 'The prefix "Project:" is reserved for a parser shortcut; if a page was created using this prefix, it would not be possible to link to it.';
    }
    
    $page_id = dirtify_page_id($page_id);
    
    if ( !$name )
      $name = str_replace('_', ' ', $page_id);
    $regex = '#^([A-z0-9 _\-\.\/\!\@\(\)]*)$#is';
    if(!preg_match($regex, $page))
    {
      //echo '<b>Notice:</b> PageUtils::createPage: Name contains invalid characters<br />';
      return 'Name contains invalid characters';
    }
    
    $page_id = sanitize_page_id( $page_id );
    
    $prot = ( $namespace == 'System' ) ? 1 : 0;
    
    $ips = array(
      'ip' => array(),
      'u' => array()
      );
    
    $page_data = Array(
      'name'=>$name,
      'urlname'=>$page_id,
      'namespace'=>$namespace,
      'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>$prot,'delvotes'=>0,'delvote_ips'=>serialize($ips),'wiki_mode'=>2,
    );
    
    // die('PageUtils::createpage: Creating page with this data:<pre>' . print_r($page_data, true) . '</pre>');
    
    $paths->add_page($page_data);
    
    $qa = $db->sql_query('INSERT INTO ' . table_prefix.'pages(name,urlname,namespace,visible,protected,delvote_ips) VALUES(\'' . $db->escape($name) . '\', \'' . $db->escape($page_id) . '\', \'' . $namespace . '\', '. ( $visible ? '1' : '0' ) .', ' . $prot . ', \'' . $db->escape(serialize($ips)) . '\');');
    $qb = $db->sql_query('INSERT INTO ' . table_prefix.'page_text(page_id,namespace) VALUES(\'' . $db->escape($page_id) . '\', \'' . $namespace . '\');');
    $qc = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'create\', \'' . $session->username . '\', \'' . $db->escape($page_id) . '\', \'' . $namespace . '\');');
    
    if($qa && $qb && $qc)
      return 'good';
    else
    {
      return $db->get_error();
    }
  }
  
  /**
   * Sets the protection level on a page.
   * @param $page_id string the page ID
   * @param $namespace string the namespace
   * @param $level int level of protection - 0 is off, 1 is full, 2 is semi
   * @param $reason string why the page is being (un)protected
   * @return string - "good" on success, in all other cases, an error string (on query failure, calls $db->_die() )
   */
  function protect($page_id, $namespace, $level, $reason)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $pname = $paths->nslist[$namespace] . $page_id;
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    
    if ( !$session->get_permissions('protect') )
    {
      return('Insufficient access rights');
    }
    if ( !$wiki )
    {
      return('Page protection only has an effect when Wiki Mode is enabled.');
    }
    if ( !preg_match('#^([0-9]+){1}$#', (string)$level) )
    {
      return('Invalid $level parameter.');
    }
    
    switch($level)
    {
      case 0:
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'unprot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
        break;
      case 1:
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'prot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
        break;
      case 2:
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'semiprot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
        break;
      default:
        return 'PageUtils::protect(): Invalid value for $level';
        break;
    }
    if(!$db->sql_query($q)) $db->_die('The log entry for the page protection could not be inserted.');
    
    $q = $db->sql_query('UPDATE ' . table_prefix.'pages SET protected=' . $level . ' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
    if ( !$q )
    {
      $db->_die('The pages table was not updated.');
    }
    
    return('good');
  }
  
  /**
   * Generates an HTML table with history information in it.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string
   */
  
  function histlist($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if(!$session->get_permissions('history_view'))
      return 'Access denied';
    
    ob_start();
    
    $pname = $paths->nslist[$namespace] . $page_id;
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    
    $q = 'SELECT time_id,date_string,page_id,namespace,author,edit_summary,minor_edit FROM ' . table_prefix.'logs WHERE log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' ORDER BY time_id DESC;';
    if(!$db->sql_query($q)) $db->_die('The history data for the page "' . $paths->cpage['name'] . '" could not be selected.');
    echo 'History of edits and actions<h3>Edits:</h3>';
    $numrows = $db->numrows();
    if($numrows < 1) echo 'No history entries in this category.';
    else
    {
      
      echo '<form action="'.makeUrlNS($namespace, $page_id, 'do=diff').'" onsubmit="ajaxHistDiff(); return false;" method="get">
            <input type="submit" value="Compare selected revisions" />
            ' . ( urlSeparator == '&' ? '<input type="hidden" name="title" value="' . htmlspecialchars($paths->nslist[$namespace] . $page_id) . '" />' : '' ) . '
            ' . ( $session->sid_super ? '<input type="hidden" name="auth"  value="' . $session->sid_super . '" />' : '') . '
            <input type="hidden" name="do" value="diff" />
            <br /><span>&nbsp;</span>
            <div class="tblholder">
            <table border="0" width="100%" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">Diff</th>
              <th>Date/time</th>
              <th>User</th>
              <th>Edit summary</th>
              <th>Minor</th>
              <th colspan="3">Actions</th>
            </tr>'."\n"."\n";
      $cls = 'row2';
      $ticker = 0;
      
      while($r = $db->fetchrow()) {
        
        $ticker++;
        
        if($cls == 'row2') $cls = 'row1';
        else $cls = 'row2';
        
        echo '<tr>'."\n";
        
        // Diff selection
        if($ticker == 1)
        {
          $s1 = '';
          $s2 = 'checked="checked" ';
        }
        elseif($ticker == 2)
        {
          $s1 = 'checked="checked" ';
          $s2 = '';
        }
        else
        {
          $s1 = '';
          $s2 = '';
        }
        if($ticker > 1)        echo '<td class="' . $cls . '" style="padding: 0;"><input ' . $s1 . 'name="diff1" type="radio" value="' . $r['time_id'] . '" id="diff1_' . $r['time_id'] . '" class="clsDiff1Radio" onclick="selectDiff1Button(this);" /></td>'."\n"; else echo '<td class="' . $cls . '"></td>';
        if($ticker < $numrows) echo '<td class="' . $cls . '" style="padding: 0;"><input ' . $s2 . 'name="diff2" type="radio" value="' . $r['time_id'] . '" id="diff2_' . $r['time_id'] . '" class="clsDiff2Radio" onclick="selectDiff2Button(this);" /></td>'."\n"; else echo '<td class="' . $cls . '"></td>';
        
        // Date and time
        echo '<td class="' . $cls . '">' . $r['date_string'] . '</td class="' . $cls . '">'."\n";
        
        // User
        if ( $session->get_permissions('mod_misc') && is_valid_ip($r['author']) )
        {
          $rc = ' style="cursor: pointer;" title="Click cell background for reverse DNS info" onclick="ajaxReverseDNS(this, \'' . $r['author'] . '\');"';
        }
        else
        {
          $rc = '';
        }
        echo '<td class="' . $cls . '"' . $rc . '><a href="'.makeUrlNS('User', $r['author']).'" ';
        if ( !isPage($paths->nslist['User'] . $r['author']) )
        {
          echo 'class="wikilink-nonexistent"';
        }
        echo '>' . $r['author'] . '</a></td class="' . $cls . '">'."\n";
        
        // Edit summary
        echo '<td class="' . $cls . '">' . $r['edit_summary'] . '</td>'."\n";
        
        // Minor edit
        echo '<td class="' . $cls . '" style="text-align: center;">'. (( $r['minor_edit'] ) ? 'M' : '' ) .'</td>'."\n";
        
        // Actions!
        echo '<td class="' . $cls . '" style="text-align: center;"><a href="'.makeUrlNS($namespace, $page_id, 'oldid=' . $r['time_id']) . '" onclick="ajaxHistView(\'' . $r['time_id'] . '\'); return false;">View revision</a></td>'."\n";
        echo '<td class="' . $cls . '" style="text-align: center;"><a href="'.makeUrl($paths->nslist['Special'].'Contributions/' . $r['author']) . '">View user contribs</a></td>'."\n";
        echo '<td class="' . $cls . '" style="text-align: center;"><a href="'.makeUrlNS($namespace, $page_id, 'do=rollback&amp;id=' . $r['time_id']) . '" onclick="ajaxRollback(\'' . $r['time_id'] . '\'); return false;">Revert to this revision</a></td>'."\n";
        
        echo '</tr>'."\n"."\n";
        
      }
      echo '</table>
            </div>
            <br />
            <input type="hidden" name="do" value="diff" />
            <input type="submit" value="Compare selected revisions" />
            </form>
            <script type="text/javascript">if ( !KILL_SWITCH ) { buildDiffList(); }</script>';
    }
    $db->free_result();
    echo '<h3>Other changes:</h3>';
    $q = 'SELECT time_id,action,date_string,page_id,namespace,author,edit_summary,minor_edit FROM ' . table_prefix.'logs WHERE log_type=\'page\' AND action!=\'edit\' AND page_id=\'' . $paths->cpage['urlname_nons'] . '\' AND namespace=\'' . $paths->namespace . '\' ORDER BY time_id DESC;';
    if(!$db->sql_query($q)) $db->_die('The history data for the page "' . $paths->cpage['name'] . '" could not be selected.');
    if($db->numrows() < 1) echo 'No history entries in this category.';
    else {
      
      echo '<div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4"><tr><th>Date/time</th><th>User</th><th>Minor</th><th>Action taken</th><th>Extra info</th><th colspan="2"></th></tr>';
      $cls = 'row2';
      while($r = $db->fetchrow()) {
        
        if($cls == 'row2') $cls = 'row1';
        else $cls = 'row2';
        
        echo '<tr>';
        
        // Date and time
        echo '<td class="' . $cls . '">' . $r['date_string'] . '</td class="' . $cls . '">';
        
        // User
        echo '<td class="' . $cls . '"><a href="'.makeUrlNS('User', $r['author']).'" ';
        if(!isPage($paths->nslist['User'] . $r['author'])) echo 'class="wikilink-nonexistent"';
        echo '>' . $r['author'] . '</a></td class="' . $cls . '">';
        
        
        // Minor edit
        echo '<td class="' . $cls . '" style="text-align: center;">'. (( $r['minor_edit'] ) ? 'M' : '' ) .'</td>';
        
        // Action taken
        echo '<td class="' . $cls . '">';
        // Some of these are sanitized at insert-time. Others follow the newer Enano policy of stripping HTML at runtime.
        if    ($r['action']=='prot')     echo 'Protected page</td><td class="' . $cls . '">Reason: ' . $r['edit_summary'];
        elseif($r['action']=='unprot')   echo 'Unprotected page</td><td class="' . $cls . '">Reason: ' . $r['edit_summary'];
        elseif($r['action']=='semiprot') echo 'Semi-protected page</td><td class="' . $cls . '">Reason: ' . $r['edit_summary'];
        elseif($r['action']=='rename')   echo 'Renamed page</td><td class="' . $cls . '">Old title: '.htmlspecialchars($r['edit_summary']);
        elseif($r['action']=='create')   echo 'Created page</td><td class="' . $cls . '">';
        elseif($r['action']=='delete')   echo 'Deleted page</td><td class="' . $cls . '">Reason: ' . $r['edit_summary'];
        elseif($r['action']=='reupload') echo 'Uploaded new file version</td><td class="' . $cls . '">Reason: '.htmlspecialchars($r['edit_summary']);
        echo '</td>';
        
        // Actions!
        echo '<td class="' . $cls . '" style="text-align: center;"><a href="'.makeUrl($paths->nslist['Special'].'Contributions/' . $r['author']) . '">View user contribs</a></td>';
        echo '<td class="' . $cls . '" style="text-align: center;"><a href="'.makeUrlNS($namespace, $page_id, 'do=rollback&amp;id=' . $r['time_id']) . '" onclick="ajaxRollback(\'' . $r['time_id'] . '\'); return false;">Revert action</a></td>';
        
        //echo '(<a href="#" onclick="ajaxRollback(\'' . $r['time_id'] . '\'); return false;">rollback</a>) <i>' . $r['date_string'] . '</i> ' . $r['author'] . ' (<a href="'.makeUrl($paths->nslist['User'].$r['author']).'">Userpage</a>, <a href="'.makeUrl($paths->nslist['Special'].'Contributions/' . $r['author']) . '">Contrib</a>): ';
        
        if($r['minor_edit']) echo '<b> - minor edit</b>';
        echo '<br />';
        
        echo '</tr>';
      }
      echo '</table></div>';
    }
    $db->free_result();
    $ret = ob_get_contents();
    ob_end_clean();
    return $ret;
  }
  
  /**
   * Rolls back a logged action
   * @param $id the time ID, a.k.a. the primary key in the logs table
   * @return string
   */
   
  function rollback($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !$session->get_permissions('history_rollback') )
    {
      return('You are not authorized to perform rollbacks.');
    }
    if ( !preg_match('#^([0-9]+)$#', (string)$id) )
    {
      return('The value "id" on the query string must be an integer.');
    }
    $e = $db->sql_query('SELECT log_type,action,date_string,page_id,namespace,page_text,char_tag,author,edit_summary FROM ' . table_prefix.'logs WHERE time_id=' . $id . ';');
    if ( !$e )
    {
      $db->_die('The rollback data could not be selected.');
    }
    $rb = $db->fetchrow();
    $db->free_result();
    
    if ( $rb['log_type'] == 'page' && $rb['action'] != 'delete' )
    {
      $pagekey = $paths->nslist[$rb['namespace']] . $rb['page_id'];
      if ( !isset($paths->pages[$pagekey]) )
      {
        return "Page doesn't exist";
      }
      $pagedata =& $paths->pages[$pagekey];
      $protected = false;
      // Special case: is the page protected? if so, check for even_when_protected permissions
      if($pagedata['protected'] == 2)
      {
        // The page is semi-protected, determine permissions
        if($session->user_logged_in && $session->reg_time + 60*60*24*4 < time()) 
        {
          $protected = false;
        }
        else
        {
          $protected = true;
        }
      }
      else
      {
        $protected = ( $pagedata['protected'] == 1 );
      }
      
      $perms = $session->fetch_page_acl($rb['page_id'], $rb['namespace']);
      
      if ( $protected && !$perms->get_permissions('even_when_protected') )
      {
        return "Because this page is protected, you need moderator rights to roll back changes.";
      }
    }
    else
    {
      $perms =& $session;
    }
    
    switch($rb['log_type'])
    {
      case "page":
        switch($rb['action'])
        {
          case "edit":
            if ( !$perms->get_permissions('edit_page') )
              return "You don't have permission to edit pages, so rolling back edits can't be allowed either.";
            $t = $db->escape($rb['page_text']);
            $e = $db->sql_query('UPDATE ' . table_prefix.'page_text SET page_text=\'' . $t . '\',char_tag=\'' . $rb['char_tag'] . '\' WHERE page_id=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\'');
            if ( !$e )
            {
              return("An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace());
            }
            else
            {
              return 'The page "' . $paths->pages[$paths->nslist[$rb['namespace']].$rb['page_id']]['name'].'" has been rolled back to the state it was in on ' . $rb['date_string'] . '.';
            }
            break;
          case "rename":
            if ( !$perms->get_permissions('rename') )
              return "You don't have permission to rename pages, so rolling back renames can't be allowed either.";
            $t = $db->escape($rb['edit_summary']);
            $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET name=\'' . $t . '\' WHERE urlname=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\'');
            if ( !$e )
            {
              return "An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace();
            }
            else
            {
              return 'The page "' . $paths->pages[$paths->nslist[$rb['namespace']].$rb['page_id']]['name'].'" has been rolled back to the name it had ("' . $rb['edit_summary'] . '") before ' . $rb['date_string'] . '.';
            }
            break;
          case "prot":
            if ( !$perms->get_permissions('protect') )
              return "You don't have permission to protect pages, so rolling back protection can't be allowed either.";
            $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET protected=0 WHERE urlname=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\'');
            if ( !$e )
              return "An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace();
            else
              return 'The page "' . $paths->pages[$paths->nslist[$rb['namespace']].$rb['page_id']]['name'].'" has been unprotected according to the log created at ' . $rb['date_string'] . '.';
            break;
          case "semiprot":
            if ( !$perms->get_permissions('protect') )
              return "You don't have permission to protect pages, so rolling back protection can't be allowed either.";
            $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET protected=0 WHERE urlname=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\'');
            if ( !$e )
              return "An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace();
            else
              return 'The page "' . $paths->pages[$paths->nslist[$rb['namespace']].$rb['page_id']]['name'].'" has been unprotected according to the log created at ' . $rb['date_string'] . '.';
            break;
          case "unprot":
            if ( !$perms->get_permissions('protect') )
              return "You don't have permission to protect pages, so rolling back protection can't be allowed either.";
            $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET protected=1 WHERE urlname=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\'');
            if ( !$e )
              return "An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace();
            else
              return 'The page "' . $paths->pages[$paths->nslist[$rb['namespace']].$rb['page_id']]['name'].'" has been protected according to the log created at ' . $rb['date_string'] . '.';
            break;
          case "delete":
            if ( !$perms->get_permissions('history_rollback_extra') )
              return 'Administrative privileges are required for page undeletion.';
            if ( isset($paths->pages[$paths->cpage['urlname']]) )
              return 'You cannot raise a dead page that is alive.';
            $name = str_replace('_', ' ', $rb['page_id']);
            $e = $db->sql_query('INSERT INTO ' . table_prefix.'pages(name,urlname,namespace) VALUES( \'' . $name . '\', \'' . $rb['page_id'] . '\',\'' . $rb['namespace'] . '\' )');if(!$e) return("An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace());
            $e = $db->sql_query('SELECT page_text,char_tag FROM ' . table_prefix.'logs WHERE page_id=\'' . $rb['page_id'] . '\' AND namespace=\'' . $rb['namespace'] . '\' AND log_type=\'page\' AND action=\'edit\' ORDER BY time_id DESC;'); if(!$e) return("An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace());
            $r = $db->fetchrow();
            $e = $db->sql_query('INSERT INTO ' . table_prefix.'page_text(page_id,namespace,page_text,char_tag) VALUES(\'' . $rb['page_id'] . '\',\'' . $rb['namespace'] . '\',\'' . $db->escape($r['page_text']) . '\',\'' . $r['char_tag'] . '\')'); if(!$e) return("An error occurred during the rollback operation.\nMySQL said: ".mysql_error()."\n\nSQL backtrace:\n".$db->sql_backtrace());
            return 'The page "' . $name . '" has been undeleted according to the log created at ' . $rb['date_string'] . '.';
            break;
          case "reupload":
            if ( !$session->get_permissions('history_rollbacks_extra') )
            {
              return 'Administrative privileges are required for file rollbacks.';
            }
            $newtime = time();
            $newdate = date('d M Y h:i a');
            if(!$db->sql_query('UPDATE ' . table_prefix.'logs SET time_id=' . $newtime . ',date_string=\'' . $newdate . '\' WHERE time_id=' . $id))
              return 'Error during query: '.mysql_error();
            if(!$db->sql_query('UPDATE ' . table_prefix.'files SET time_id=' . $newtime . ' WHERE time_id=' . $id))
              return 'Error during query: '.mysql_error();
            return 'The file has been rolled back to the version uploaded on '.date('d M Y h:i a', (int)$id).'.';
            break;
          default:
            return('Rollback of the action "' . $rb['action'] . '" is not yet supported.');
            break;
        }
        break;
      case "security":
      case "login":
        return('A ' . $rb['log_type'] . '-related log entry cannot be rolled back.');
        break;
      default:
        return('Unknown log entry type: "' . $rb['log_type'] . '"');
    }
  }
  
  /**
   * Posts a comment.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $name the name of the person posting, defaults to current username/IP
   * @param $subject the subject line of the comment
   * @param $text the comment text
   * @return string javascript code
   */
   
  function addcomment($page_id, $namespace, $name, $subject, $text, $captcha_code = false, $captcha_id = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $_ob = '';
    if(!$session->get_permissions('post_comments'))
      return 'Access denied';
    if(getConfig('comments_need_login') == '2' && !$session->user_logged_in) _die('Access denied to post comments: you need to be logged in first.');
    if(getConfig('comments_need_login') == '1' && !$session->user_logged_in)
    {
      if(!$captcha_code || !$captcha_id) _die('BUG: PageUtils::addcomment: no CAPTCHA data passed to method');
      $result = $session->get_captcha($captcha_id);
      if($captcha_code != $result) _die('The confirmation code you entered was incorrect.');
    }
    $text = RenderMan::preprocess_text($text);
    $name = $session->user_logged_in ? RenderMan::preprocess_text($session->username) : RenderMan::preprocess_text($name);
    $subj = RenderMan::preprocess_text($subject);
    if(getConfig('approve_comments')=='1') $appr = '0'; else $appr = '1';
    $q = 'INSERT INTO ' . table_prefix.'comments(page_id,namespace,subject,comment_data,name,user_id,approved,time) VALUES(\'' . $page_id . '\',\'' . $namespace . '\',\'' . $subj . '\',\'' . $text . '\',\'' . $name . '\',' . $session->user_id . ',' . $appr . ','.time().')';
    $e = $db->sql_query($q);
    if(!$e) die('alert(unescape(\''.rawurlencode('Error inserting comment data: '.mysql_error().'\n\nQuery:\n' . $q) . '\'))');
    else $_ob .= '<div class="info-box">Your comment has been posted.</div>';
    return PageUtils::comments($page_id, $namespace, false, Array(), $_ob);
  }
  
  /**
   * Generates partly-compiled HTML/Javascript code to be eval'ed by the user's browser to display comments
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $action administrative action to perform, default is false
   * @param $flags additional info for $action, shouldn't be used except when deleting/approving comments, etc.
   * @param $_ob text to prepend to output, used by PageUtils::addcomment
   * @return array
   * @access private
   */
   
  function comments_raw($page_id, $namespace, $action = false, $flags = Array(), $_ob = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $pname = $paths->nslist[$namespace] . $page_id;
    
    ob_start();
    
    if($action && $session->get_permissions('mod_comments')) // Nip hacking attempts in the bud
    {
      switch($action) {
      case "delete":
        if(isset($flags['id']))
        {
          $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND comment_id='.intval($flags['id']).' LIMIT 1;';
        } else {
          $n = $db->escape($flags['name']);
          $s = $db->escape($flags['subj']);
          $t = $db->escape($flags['text']);
          $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND name=\'' . $n . '\' AND subject=\'' . $s . '\' AND comment_data=\'' . $t . '\' LIMIT 1;';
        }
        $e=$db->sql_query($q);
        if(!$e) die('alert(unesape(\''.rawurlencode('Error during query: '.mysql_error().'\n\nQuery:\n' . $q) . '\'));');
        break;
      case "approve":
        if(isset($flags['id']))
        {
          $where = 'comment_id='.intval($flags['id']);
        } else {
          $n = $db->escape($flags['name']);
          $s = $db->escape($flags['subj']);
          $t = $db->escape($flags['text']);
          $where = 'name=\'' . $n . '\' AND subject=\'' . $s . '\' AND comment_data=\'' . $t . '\'';
        }
        $q = 'SELECT approved FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND ' . $where . ' LIMIT 1;';
        $e = $db->sql_query($q);
        if(!$e) die('alert(unesape(\''.rawurlencode('Error selecting approval status: '.mysql_error().'\n\nQuery:\n' . $q) . '\'));');
        $r = $db->fetchrow();
        $db->free_result();
        $a = ( $r['approved'] ) ? '0' : '1';
        $q = 'UPDATE ' . table_prefix.'comments SET approved=' . $a . ' WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND ' . $where . ';';
        $e=$db->sql_query($q);
        if(!$e) die('alert(unesape(\''.rawurlencode('Error during query: '.mysql_error().'\n\nQuery:\n' . $q) . '\'));');
        if($a=='1') $v = 'Unapprove';
        else $v = 'Approve';
        echo 'document.getElementById("mdgApproveLink'.intval($_GET['id']).'").innerHTML="' . $v . '";';
        break;
      }
    }
    
    if(!defined('ENANO_TEMPLATE_LOADED'))
    {
      $template->load_theme($session->theme, $session->style);
    }
    
    $tpl = $template->makeParser('comment.tpl');
    
    $e = $db->sql_query('SELECT * FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND approved=0;');
    if(!$e) $db->_die('The comment text data could not be selected.');
    $num_unapp = $db->numrows();
    $db->free_result();
    $e = $db->sql_query('SELECT * FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND approved=1;');
    if(!$e) $db->_die('The comment text data could not be selected.');
    $num_app = $db->numrows();
    $db->free_result();
    $lq = $db->sql_query('SELECT c.comment_id,c.subject,c.name,c.comment_data,c.approved,c.time,c.user_id,u.user_level,u.signature
                  FROM ' . table_prefix.'comments AS c
                  LEFT JOIN ' . table_prefix.'users AS u
                    ON c.user_id=u.user_id
                  WHERE page_id=\'' . $page_id . '\'
                  AND namespace=\'' . $namespace . '\' ORDER BY c.time ASC;');
    if(!$lq) _die('The comment text data could not be selected. '.mysql_error());
    $_ob .= '<h3>Article Comments</h3>';
    $n = ( $session->get_permissions('mod_comments')) ? $db->numrows() : $num_app;
    if($n==1) $s = 'is ' . $n . ' comment'; else $s = 'are ' . $n . ' comments';
    if($n < 1)
    {
      $_ob .= '<p>There are currently no comments on this '.strtolower($namespace).'';
      if($namespace != 'Article') $_ob .= ' page';
      $_ob .= '.</p>';
    } else $_ob .= '<p>There ' . $s . ' on this article.';
    if($session->get_permissions('mod_comments') && $num_unapp > 0) $_ob .= ' <span style="color: #D84308">' . $num_unapp . ' of those are unapproved.</span>';
    elseif(!$session->get_permissions('mod_comments') && $num_unapp > 0) { $u = ($num_unapp == 1) ? "is $num_unapp comment" : "are $num_unapp comments"; $_ob .= ' However, there ' . $u . ' awating approval.'; }
    $_ob .= '</p>';
    $list = 'list = { ';
    // _die(htmlspecialchars($ttext));
    $i = -1;
    while($row = $db->fetchrow($lq))
    {
      $i++;
      $strings = Array();
      $bool = Array();
      if ( $session->get_permissions('mod_comments') || $row['approved'] )
      {
        $list .= $i . ' : { \'comment\' : unescape(\''.rawurlencode($row['comment_data']).'\'), \'name\' : unescape(\''.rawurlencode($row['name']).'\'), \'subject\' : unescape(\''.rawurlencode($row['subject']).'\'), }, ';
        
        // Comment ID (used in the Javascript apps)
        $strings['ID'] = (string)$i;
        
        // Determine the name, and whether to link to the user page or not
        $name = '';
        if($row['user_id'] > 0) $name .= '<a href="'.makeUrlNS('User', str_replace(' ', '_', $row['name'])).'">';
        $name .= $row['name'];
        if($row['user_id'] > 0) $name .= '</a>';
        $strings['NAME'] = $name; unset($name);
        
        // Subject
        $s = $row['subject'];
        if(!$row['approved']) $s .= ' <span style="color: #D84308">(Unapproved)</span>';
        $strings['SUBJECT'] = $s;
        
        // Date and time
        $strings['DATETIME'] = date('F d, Y h:i a', $row['time']);
        
        // User level
        switch($row['user_level'])
        {
          default:
          case USER_LEVEL_GUEST:
            $l = 'Guest';
            break;
          case USER_LEVEL_MEMBER:
            $l = 'Member';
            break;
          case USER_LEVEL_MOD:
            $l = 'Moderator';
            break;
          case USER_LEVEL_ADMIN:
            $l = 'Administrator';
            break;
        }
        $strings['USER_LEVEL'] = $l; unset($l);
        
        // The actual comment data
        $strings['DATA'] = RenderMan::render($row['comment_data']);
        
        if($session->get_permissions('edit_comments'))
        {
          // Edit link
          $strings['EDIT_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=editcomment&amp;id=' . $row['comment_id']) . '" id="editbtn_' . $i . '">edit</a>';
        
          // Delete link
          $strings['DELETE_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=deletecomment&amp;id=' . $row['comment_id']) . '">delete</a>';
        }
        else
        {
          // Edit link
          $strings['EDIT_LINK'] = '';
        
          // Delete link
          $strings['DELETE_LINK'] = '';
        }
        
        // Send PM link
        $strings['SEND_PM_LINK'] = ( $session->user_logged_in && $row['user_id'] > 0 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/To/' . $row['name']) . '">Send private message</a><br />' : '';
        
        // Add Buddy link
        $strings['ADD_BUDDY_LINK'] = ( $session->user_logged_in && $row['user_id'] > 0 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' . $row['name']) . '">Add to buddy list</a>' : '';
        
        // Mod links
        $applink = '';
        $applink .= '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=admin&amp;action=approve&amp;id=' . $row['comment_id']) . '" id="mdgApproveLink' . $i . '">';
        if($row['approved']) $applink .= 'Unapprove';
        else $applink .= 'Approve';
        $applink .= '</a>';
        $strings['MOD_APPROVE_LINK'] = $applink; unset($applink);
        $strings['MOD_DELETE_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=admin&amp;action=delete&amp;id=' . $row['comment_id']) . '">Delete</a>';
        
        // Signature
        $strings['SIGNATURE'] = '';
        if($row['signature'] != '') $strings['SIGNATURE'] = RenderMan::render($row['signature']);
        
        $bool['auth_mod'] = ($session->get_permissions('mod_comments')) ? true : false;
        $bool['can_edit'] = ( ( $session->user_logged_in && $row['name'] == $session->username && $session->get_permissions('edit_comments') ) || $session->get_permissions('mod_comments') ) ? true : false;
        $bool['signature'] = ( $strings['SIGNATURE'] == '' ) ? false : true;
        
        // Done processing and compiling, now let's cook it into HTML
        $tpl->assign_vars($strings);
        $tpl->assign_bool($bool);
        $_ob .= $tpl->run();
      }
    }
    if(getConfig('comments_need_login') != '2' || $session->user_logged_in)
    {
      if(!$session->get_permissions('post_comments'))
      {
        $_ob .= '<h3>Got something to say?</h3><p>Access to post comments on this page is denied.</p>';
      }
      else
      {
        $_ob .= '<h3>Got something to say?</h3>If you have comments or suggestions on this article, you can shout it out here.';
        if(getConfig('approve_comments')=='1') $_ob .= '  Before your comment will be visible to the public, a moderator will have to approve it.';
        if(getConfig('comments_need_login') == '1' && !$session->user_logged_in) $_ob .= ' Because you are not logged in, you will need to enter a visual confirmation before your comment will be posted.';
        $sn = $session->user_logged_in ? $session->username . '<input name="name" id="mdgScreenName" type="hidden" value="' . $session->username . '" />' : '<input name="name" id="mdgScreenName" type="text" size="35" />';
        $_ob .= '  <a href="#" id="mdgCommentFormLink" style="display: none;" onclick="document.getElementById(\'mdgCommentForm\').style.display=\'block\';this.style.display=\'none\';return false;">Leave a comment...</a>
        <div id="mdgCommentForm">
        <h3>Comment form</h3>
        <form action="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=postcomment').'" method="post" style="margin-left: 1em">
        <table border="0">
        <tr><td>Your name or screen name:</td><td>' . $sn . '</td></tr>
        <tr><td>Comment subject:</td><td><input name="subj" id="mdgSubject" type="text" size="35" /></td></tr>';
        if(getConfig('comments_need_login') == '1' && !$session->user_logged_in)
        {
          $session->kill_captcha();
          $captcha = $session->make_captcha();
          $_ob .= '<tr><td>Visual confirmation:<br /><small>Please enter the code you see on the right.</small></td><td><img src="'.makeUrlNS('Special', 'Captcha/' . $captcha) . '" alt="Visual confirmation" style="cursor: pointer;" onclick="this.src = \''.makeUrlNS("Special", "Captcha/".$captcha).'/\'+Math.floor(Math.random() * 100000);" /><input name="captcha_id" id="mdgCaptchaID" type="hidden" value="' . $captcha . '" /><br />Code: <input name="captcha_input" id="mdgCaptchaInput" type="text" size="10" /><br /><small><script type="text/javascript">document.write("If you can\'t read the code, click on the image to generate a new one.");</script><noscript>If you can\'t read the code, please refresh this page to generate a new one.</noscript></small></td></tr>';
        }
        $_ob .= '
        <tr><td valign="top">Comment text:<br />(most HTML will be stripped)</td><td><textarea name="text" id="mdgCommentArea" rows="10" cols="40"></textarea></td></tr>
        <tr><td colspan="2" style="text-align: center;"><input type="submit" value="Submit Comment" /></td></tr>
        </table>
        </form>
        </div>';
      }
    } else {
      $_ob .= '<h3>Got something to say?</h3><p>You need to be logged in to post comments. <a href="'.makeUrlNS('Special', 'Login/' . $pname . '%2523comments').'">Log in</a></p>';
    }
    $list .= '};';
    echo 'document.getElementById(\'ajaxEditContainer\').innerHTML = unescape(\''. rawurlencode($_ob) .'\');
    ' . $list;
    echo 'Fat.fade_all(); document.getElementById(\'mdgCommentForm\').style.display = \'none\'; document.getElementById(\'mdgCommentFormLink\').style.display="inline";';
    
    $ret = ob_get_contents();
    ob_end_clean();
    return Array($ret, $_ob);
    
  }
  
  /**
   * Generates ready-to-execute Javascript code to be eval'ed by the user's browser to display comments
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $action administrative action to perform, default is false
   * @param $flags additional info for $action, shouldn't be used except when deleting/approving comments, etc.
   * @param $_ob text to prepend to output, used by PageUtils::addcomment
   * @return string
   */
   
  function comments($page_id, $namespace, $action = false, $id = -1, $_ob = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $r = PageUtils::comments_raw($page_id, $namespace, $action, $id, $_ob);
    return $r[0];
  }
  
  /**
   * Generates HTML code for comments - used in browser compatibility mode
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $action administrative action to perform, default is false
   * @param $flags additional info for $action, shouldn't be used except when deleting/approving comments, etc.
   * @param $_ob text to prepend to output, used by PageUtils::addcomment
   * @return string
   */
  
  function comments_html($page_id, $namespace, $action = false, $id = -1, $_ob = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $r = PageUtils::comments_raw($page_id, $namespace, $action, $id, $_ob);
    return $r[1];
  }
  
  /**
   * Updates comment data.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $subject new subject
   * @param $text new text
   * @param $old_subject the old subject, unprocessed and identical to the value in the DB
   * @param $old_text the old text, unprocessed and identical to the value in the DB
   * @param $id the javascript list ID, used internally by the client-side app
   * @return string
   */
  
  function savecomment($page_id, $namespace, $subject, $text, $old_subject, $old_text, $id = -1)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('edit_comments'))
      return 'result="BAD";error="Access denied"';
    // Avoid SQL injection
    $old_text    = $db->escape($old_text);
    $old_subject = $db->escape($old_subject);
    // Safety check - username/login
    if(!$session->get_permissions('mod_comments')) // allow mods to edit comments
    {
      if(!$session->user_logged_in) _die('AJAX comment save safety check failed because you are not logged in. Sometimes this can happen because you are using a browser that does not send cookies as part of AJAX requests.<br /><br />Please log in and try again.');
      $q = 'SELECT c.name FROM ' . table_prefix.'comments c, ' . table_prefix.'users u WHERE comment_data=\'' . $old_text . '\' AND subject=\'' . $old_subject . '\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND u.user_id=c.user_id;';
      $s = $db->sql_query($q);
      if(!$s) _die('SQL error during safety check: '.mysql_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      $db->free_result();
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
    }
    $s = RenderMan::preprocess_text($subject);
    $t = RenderMan::preprocess_text($text);
    $sql  = 'UPDATE ' . table_prefix.'comments SET subject=\'' . $s . '\',comment_data=\'' . $t . '\' WHERE comment_data=\'' . $old_text . '\' AND subject=\'' . $old_subject . '\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $result = $db->sql_query($sql);
    if($result)
    {
      return 'result="GOOD";
                      list[' . $id . '][\'subject\'] = unescape(\''.str_replace('%5Cn', '%0A', rawurlencode(str_replace('{{EnAnO:Newline}}', '\\n', stripslashes(str_replace('\\n', '{{EnAnO:Newline}}', $s))))).'\');
                      list[' . $id . '][\'comment\'] = unescape(\''.str_replace('%5Cn', '%0A', rawurlencode(str_replace('{{EnAnO:Newline}}', '\\n', stripslashes(str_replace('\\n', '{{EnAnO:Newline}}', $t))))).'\'); id = ' . $id . ';
      s = unescape(\''.rawurlencode($s).'\');
      t = unescape(\''.str_replace('%5Cn', '<br \\/>', rawurlencode(RenderMan::render(str_replace('{{EnAnO:Newline}}', "\n", stripslashes(str_replace('\\n', '{{EnAnO:Newline}}', $t)))))).'\');';
    }
    else
    {
      return 'result="BAD"; error=unescape("'.rawurlencode('Enano encountered a problem whilst saving the comment.
      Performed SQL:
      ' . $sql . '
    
      Error returned by MySQL: '.mysql_error()).'");';
    }
  }
  
  /**
   * Updates comment data using the comment_id column instead of the old, messy way
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $subject new subject
   * @param $text new text
   * @param $id the comment ID (primary key in enano_comments table)
   * @return string
   */
  
  function savecomment_neater($page_id, $namespace, $subject, $text, $id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!is_int($id)) die('PageUtils::savecomment: $id is not an integer, aborting for safety');
    if(!$session->get_permissions('edit_comments'))
      return 'Access denied';
    // Safety check - username/login
    if(!$session->get_permissions('mod_comments')) // allow mods to edit comments
    {
      if(!$session->user_logged_in) _die('AJAX comment save safety check failed because you are not logged in. Sometimes this can happen because you are using a browser that does not send cookies as part of AJAX requests.<br /><br />Please log in and try again.');
      $q = 'SELECT c.name FROM ' . table_prefix.'comments c, ' . table_prefix.'users u WHERE comment_id=' . $id . ' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND u.user_id=c.user_id;';
      $s = $db->sql_query($q);
      if(!$s) _die('SQL error during safety check: '.mysql_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
      $db->free_result();
    }
    $s = RenderMan::preprocess_text($subject);
    $t = RenderMan::preprocess_text($text);
    $sql  = 'UPDATE ' . table_prefix.'comments SET subject=\'' . $s . '\',comment_data=\'' . $t . '\' WHERE comment_id=' . $id . ' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $result = $db->sql_query($sql);
    if($result)
    return 'good';
    else return 'Enano encountered a problem whilst saving the comment.
    Performed SQL:
    ' . $sql . '
    
    Error returned by MySQL: '.mysql_error();
  }
  
  /**
   * Deletes a comment.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $name the name the user posted under
   * @param $subj the subject of the comment to be deleted
   * @param $text the text of the comment to be deleted
   * @param $id the javascript list ID, used internally by the client-side app
   * @return string
   */
  
  function deletecomment($page_id, $namespace, $name, $subj, $text, $id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if(!$session->get_permissions('edit_comments'))
      return 'alert("Access to delete/edit comments is denied");';
    
    if(!preg_match('#^([0-9]+)$#', (string)$id)) die('$_GET[id] is improperly formed.');
    $n = $db->escape($name);
    $s = $db->escape($subj);
    $t = $db->escape($text);
    
    // Safety check - username/login
    if(!$session->get_permissions('mod_comments')) // allows mods to delete comments
    {
      if(!$session->user_logged_in) _die('AJAX comment save safety check failed because you are not logged in. Sometimes this can happen because you are using a browser that does not send cookies as part of AJAX requests.<br /><br />Please log in and try again.');
      $q = 'SELECT c.name FROM ' . table_prefix.'comments c, ' . table_prefix.'users u WHERE comment_data=\'' . $t . '\' AND subject=\'' . $s . '\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND u.user_id=c.user_id;';
      $s = $db->sql_query($q);
      if(!$s) _die('SQL error during safety check: '.mysql_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
      $db->free_result();
    }
    $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND name=\'' . $n . '\' AND subject=\'' . $s . '\' AND comment_data=\'' . $t . '\' LIMIT 1;';
    $e=$db->sql_query($q);
    if(!$e) return('alert(unesape(\''.rawurlencode('Error during query: '.mysql_error().'\n\nQuery:\n' . $q) . '\'));');
    return('good');
  }
  
  /**
   * Deletes a comment in a cleaner fashion.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $id the comment ID (primary key)
   * @return string
   */
  
  function deletecomment_neater($page_id, $namespace, $id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if(!preg_match('#^([0-9]+)$#', (string)$id)) die('$_GET[id] is improperly formed.');
    
    if(!$session->get_permissions('edit_comments'))
      return 'alert("Access to delete/edit comments is denied");';
    
    // Safety check - username/login
    if(!$session->get_permissions('mod_comments')) // allows mods to delete comments
    {
      if(!$session->user_logged_in) _die('AJAX comment save safety check failed because you are not logged in. Sometimes this can happen because you are using a browser that does not send cookies as part of AJAX requests.<br /><br />Please log in and try again.');
      $q = 'SELECT c.name FROM ' . table_prefix.'comments c, ' . table_prefix.'users u WHERE comment_id=' . $id . ' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND u.user_id=c.user_id;';
      $s = $db->sql_query($q);
      if(!$s) _die('SQL error during safety check: '.mysql_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
      $db->free_result();
    }
    $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND comment_id=' . $id . ' LIMIT 1;';
    $e=$db->sql_query($q);
    if(!$e) return('alert(unesape(\''.rawurlencode('Error during query: '.mysql_error().'\n\nQuery:\n' . $q) . '\'));');
    return('good');
  }
  
  /**
   * Renames a page.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $name the new name for the page
   * @return string error string or success message
   */
   
  function rename($page_id, $namespace, $name)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $pname = $paths->nslist[$namespace] . $page_id;
    
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    
    if( empty($name)) 
    {
      die('Name is too short');
    }
    if( ( $session->get_permissions('rename') && ( ( $prot && $session->get_permissions('even_when_protected') ) || !$prot ) ) && ( $paths->namespace != 'Special' && $paths->namespace != 'Admin' ))
    {
      $e = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,page_id,namespace,author,edit_summary) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'rename\', \'' . $db->escape($paths->cpage['urlname_nons']) . '\', \'' . $paths->namespace . '\', \'' . $db->escape($session->username) . '\', \'' . $db->escape($paths->cpage['name']) . '\')');
      if ( !$e )
      {
        $db->_die('The page title could not be updated.');
      }
      $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET name=\'' . $db->escape($name) . '\' WHERE urlname=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $db->escape($namespace) . '\';');
      if ( !$e )
      {
        $db->_die('The page title could not be updated.');
      }
      else
      {
        return('The page "' . $paths->pages[$pname]['name'] . '" has been renamed to "' . $name . '". You are encouraged to leave a comment explaining your action.' . "\n\n" . 'You will see the change take effect the next time you reload this page.');
      }
    }
    else
    {
      return('Access is denied.');
    }
  }
  
  /**
   * Flushes (clears) the action logs for a given page
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string error/success string
   */
   
  function flushlogs($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('clear_logs')) die('Administrative privileges are required to flush logs, you loser.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'logs WHERE page_id=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $db->escape($namespace) . '\';');
    if(!$e) $db->_die('The log entries could not be deleted.');
    
    // If the page exists, make a backup of it in case it gets spammed/vandalized
    // If not, the admin's probably deleting a trash page
    if ( isset($paths->pages[ $paths->nslist[$namespace] . $page_id ]) )
    {
      $e = $db->sql_query('SELECT page_text,char_tag FROM ' . table_prefix.'page_text WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
      if(!$e) $db->_die('The current page text could not be selected; as a result, creating the backup of the page failed. Please make a backup copy of the page by clicking Edit this page and then clicking Save Changes.');
      $row = $db->fetchrow();
      $db->free_result();
      $q='INSERT INTO ' . table_prefix.'logs(log_type,action,time_id,date_string,page_id,namespace,page_text,char_tag,author,edit_summary,minor_edit) VALUES(\'page\', \'edit\', '.time().', \''.date('d M Y h:i a').'\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape($row['page_text']) . '\', \'' . $row['char_tag'] . '\', \'' . $session->username . '\', \''."Automatic backup created when logs were purged".'\', '.'false'.');';
      if(!$db->sql_query($q)) $db->_die('The history (log) entry could not be inserted into the logs table.');
    }
    return('The logs for this page have been cleared. A backup of this page has been added to the logs table so that this page can be restored in case of vandalism or spam later.');
  }
  
  /**
   * Deletes a page.
   * @param string $page_id the condemned page ID
   * @param string $namespace the condemned namespace
   * @param string The reason for deleting the page in question
   * @return string
   */
   
  function deletepage($page_id, $namespace, $reason)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $perms = $session->fetch_page_acl($page_id, $namespace);
    $x = trim($reason);
    if ( empty($x) )
    {
      return 'Invalid reason for deletion passed';
    }
    if(!$perms->get_permissions('delete_page')) return('Administrative privileges are required to delete pages, you loser.');
    $e = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,page_id,namespace,author,edit_summary) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'delete\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $session->username . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\')');
    if(!$e) $db->_die('The page log entry could not be inserted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'categories WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page categorization entries could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page comments could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'page_text WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page text entry could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'pages WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page entry could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'files WHERE page_id=\'' . $page_id . '\'');
    if(!$e) $db->_die('The file entry could not be deleted.');
    return('This page has been deleted. Note that there is still a log of edits and actions in the database, and anyone with admin rights can raise this page from the dead unless the log is cleared. If the deleted file is an image, there may still be cached thumbnails of it in the cache/ directory, which is inaccessible to users.');
  }
  
  /**
   * Increments the deletion votes for a page by 1, and adds the current username/IP to the list of users that have voted for the page to prevent dual-voting
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string
   */
   
  function delvote($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( !$session->get_permissions('vote_delete') )
    {
      return 'Access denied';
    }
    
    if ( $namespace == 'Admin' || $namespace == 'Special' || $namespace == 'System' )
    {
      return 'Special pages and system messages can\'t be voted for deletion.';
    }
    
    $pname = $paths->nslist[$namespace] . sanitize_page_id($page_id);
    
    if ( !isset($paths->pages[$pname]) )
    {
      return 'The page does not exist.';
    }
    
    $cv  =& $paths->pages[$pname]['delvotes'];
    $ips =  $paths->pages[$pname]['delvote_ips'];
    
    if ( empty($ips) )
    {
      $ips = array(
        'ip' => array(),
        'u' => array()
        );
    }
    else
    {
      $ips = @unserialize($ips);
      if ( !$ips )
      {
        $ips = array(
        'ip' => array(),
        'u' => array()
        );
      }
    }
    
    if ( in_array($session->username, $ips['u']) || in_array($_SERVER['REMOTE_ADDR'], $ips['ip']) )
    {
      return 'It appears that you have already voted to have this page deleted.';
    }
    
    $ips['u'][] = $session->username;
    $ips['ip'][] = $_SERVER['REMOTE_ADDR'];
    $ips = $db->escape( serialize($ips) );
    
    $cv++;
    
    $q = 'UPDATE ' . table_prefix.'pages SET delvotes=' . $cv . ',delvote_ips=\'' . $ips . '\' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $w = $db->sql_query($q);
    
    return 'Your vote to have this page deleted has been cast.'."\nYou are encouraged to leave a comment explaining the reason for your vote.";
  }
  
  /**
   * Resets the number of votes against a page to 0.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string
   */
  
  function resetdelvotes($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('vote_reset')) die('You need moderator rights in order to do this, stinkin\' hacker.');
    $q = 'UPDATE ' . table_prefix.'pages SET delvotes=0,delvote_ips=\'' . $db->escape(serialize(array('ip'=>array(),'u'=>array()))) . '\' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $e = $db->sql_query($q);
    if(!$e) $db->_die('The number of delete votes was not reset.');
    else return('The number of votes for having this page deleted has been reset to zero.');
  }
  
  /**
   * Gets a list of styles for a given theme name. As of Banshee, this returns JSON.
   * @param $id the name of the directory for the theme
   * @return string JSON string with an array containing a list of themes
   */
   
  function getstyles()
  {
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    
    if ( !preg_match('/^([a-z0-9_-]+)$/', $_GET['id']) )
      return $json->encode(false);
    
    $dir = './themes/' . $_GET['id'] . '/css/';
    $list = Array();
    // Open a known directory, and proceed to read its contents
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
          if ( preg_match('#^(.*?)\.css$#is', $file) && $file != '_printable.css' ) // _printable.css should be included with every theme
          {                                                                         // it should be a copy of the original style, but
                                                                                    // mostly black and white
                                                                                    // Note to self: document this
            $list[] = substr($file, 0, strlen($file)-4);
          }
        }
        closedir($dh);
      }
    }
    else
    {
      return($json->encode(Array('mode' => 'error', 'error' => $dir.' is not a dir')));
    }
    
    return $json->encode($list);
  }
  
  /**
   * Assembles a Javascript app with category information
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string Javascript code
   */
   
  function catedit($page_id, $namespace)
  {
    $d = PageUtils::catedit_raw($page_id, $namespace);
    return $d[0] . ' /* BEGIN CONTENT */ document.getElementById("ajaxEditContainer").innerHTML = unescape(\''.rawurlencode($d[1]).'\');';
  }
  
  /**
   * Does the actual HTML/javascript generation for cat editing, but returns an array
   * @access private
   */
   
  function catedit_raw($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    ob_start();
    $_ob = '';
    $e = $db->sql_query('SELECT category_id FROM ' . table_prefix.'categories WHERE page_id=\'' . $paths->cpage['urlname_nons'] . '\' AND namespace=\'' . $paths->namespace . '\'');
    if(!$e) jsdie('Error selecting category information for current page: '.mysql_error());
    $cat_current = Array();
    while($r = $db->fetchrow())
    {
      $cat_current[] = $r;
    }
    $db->free_result();
    $cat_all = Array();
    for($i=0;$i<sizeof($paths->pages)/2;$i++)
    {
      if($paths->pages[$i]['namespace']=='Category') $cat_all[] = $paths->pages[$i];
    }
    
    // Make $cat_all an associative array, like $paths->pages
    $sz = sizeof($cat_all);
    for($i=0;$i<$sz;$i++)
    {
      $cat_all[$cat_all[$i]['urlname_nons']] = $cat_all[$i];
    }
    // Now, the "zipper" function - join the list of categories with the list of cats that this page is a part of
    $cat_info = $cat_all;
    for($i=0;$i<sizeof($cat_current);$i++)
    {
      $un = $cat_current[$i]['category_id'];
      $cat_info[$un]['member'] = true;
    }
    // Now copy the information we just set into the numerically named keys
    for($i=0;$i<sizeof($cat_info)/2;$i++)
    {
      $un = $cat_info[$i]['urlname_nons'];
      $cat_info[$i] = $cat_info[$un];
    }
    
    echo 'catlist = new Array();'; // Initialize the client-side category list
    $_ob .= '<h3>Select which categories this page should be included in.</h3>
             <form name="mdgCatForm" action="'.makeUrlNS($namespace, $page_id, 'do=catedit').'" method="post">';
    if ( sizeof($cat_info) < 1 )
    {
      $_ob .= '<p>There are no categories on this site yet.</p>';
    }
    for ( $i = 0; $i < sizeof($cat_info) / 2; $i++ )
    {
      // Protection code added 1/3/07
      // Updated 3/4/07
      $is_prot = false;
      $perms = $session->fetch_page_acl($cat_info[$i]['urlname_nons'], 'Category');
      if ( !$session->get_permissions('edit_cat') || !$perms->get_permissions('edit_cat') ||
         ( $cat_info[$i]['really_protected'] && !$perms->get_permissions('even_when_protected') ) )
         $is_prot = true;
      $prot = ( $is_prot ) ? ' disabled="disabled" ' : '';
      $prottext = ( $is_prot ) ? ' <img alt="(protected)" width="16" height="16" src="'.scriptPath.'/images/lock16.png" />' : '';
      echo 'catlist[' . $i . '] = \'' . $cat_info[$i]['urlname_nons'] . '\';';
      $_ob .= '<span class="catCheck"><input ' . $prot . ' name="' . $cat_info[$i]['urlname_nons'] . '" id="mdgCat_' . $cat_info[$i]['urlname_nons'] . '" type="checkbox"';
      if(isset($cat_info[$i]['member'])) $_ob .= ' checked="checked"';
      $_ob .= '/>  <label for="mdgCat_' . $cat_info[$i]['urlname_nons'] . '">' . $cat_info[$i]['name'].$prottext.'</label></span><br />';
    }
    
    $disabled = ( sizeof($cat_info) < 1 ) ? 'disabled="disabled"' : '';
      
    $_ob .= '<div style="border-top: 1px solid #CCC; padding-top: 5px; margin-top: 10px;"><input name="__enanoSaveButton" ' . $disabled . ' style="font-weight: bold;" type="submit" onclick="ajaxCatSave(); return false;" value="Save changes" /> <input name="__enanoCatCancel" type="submit" onclick="ajaxReset(); return false;" value="Cancel" /></div></form>';
    
    $cont = ob_get_contents();
    ob_end_clean();
    return Array($cont, $_ob);
  }
  
  /**
   * Saves category information
   * WARNING: If $which_cats is empty, all the category information for the selected page will be nuked!
   * @param $page_id string the page ID
   * @param $namespace string the namespace
   * @param $which_cats array associative array of categories to put the page in
   * @return string "GOOD" on success, error string on failure
   */
  
  function catsave($page_id, $namespace, $which_cats)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('edit_cat')) return('Insufficient privileges to change category information');
    
    $page_perms = $session->fetch_page_acl($page_id, $namespace);
    $page_data =& $paths->pages[$paths->nslist[$namespace].$page_id];
    
    $cat_all = Array();
    for($i=0;$i<sizeof($paths->pages)/2;$i++)
    {
      if($paths->pages[$i]['namespace']=='Category') $cat_all[] = $paths->pages[$i];
    }
    
    // Make $cat_all an associative array, like $paths->pages
    $sz = sizeof($cat_all);
    for($i=0;$i<$sz;$i++)
    {
      $cat_all[$cat_all[$i]['urlname_nons']] = $cat_all[$i];
    }
    
    $rowlist = Array();
    
    for($i=0;$i<sizeof($cat_all)/2;$i++)
    {
      $auth = true;
      $perms = $session->fetch_page_acl($cat_all[$i]['urlname_nons'], 'Category');
      if ( !$session->get_permissions('edit_cat') || !$perms->get_permissions('edit_cat') ||
         ( $cat_all[$i]['really_protected'] && !$perms->get_permissions('even_when_protected') ) ||
         ( !$page_perms->get_permissions('even_when_protected') && $page_data['protected'] == '1' ) )
         $auth = false;
      if(!$auth)
      {
        // Find out if the page is currently in the category
        $q = $db->sql_query('SELECT * FROM ' . table_prefix.'categories WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
        if(!$q)
          return 'MySQL error: ' . $db->get_error();
        if($db->numrows() > 0)
        {
          $auth = true;
          $which_cats[$cat_all[$i]['urlname_nons']] = true; // Force the category to stay in its current state
        }
        $db->free_result();
      }
      if(isset($which_cats[$cat_all[$i]['urlname_nons']]) && $which_cats[$cat_all[$i]['urlname_nons']] == true /* for clarity ;-) */ && $auth ) $rowlist[] = '(\'' . $page_id . '\', \'' . $namespace . '\', \'' . $cat_all[$i]['urlname_nons'] . '\')';
    }
    if(sizeof($rowlist) > 0)
    {
      $val = implode(',', $rowlist);
      $q = 'INSERT INTO ' . table_prefix.'categories(page_id,namespace,category_id) VALUES' . $val . ';';
      $e = $db->sql_query('DELETE FROM ' . table_prefix.'categories WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
      if(!$e) $db->_die('The old category data could not be deleted.');
      $e = $db->sql_query($q);
      if(!$e) $db->_die('The new category data could not be inserted.');
      return('GOOD');
    }
    else
    {
      $e = $db->sql_query('DELETE FROM ' . table_prefix.'categories WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
      if(!$e) $db->_die('The old category data could not be deleted.');
      return('GOOD');
    }
  }
  
  /**
   * Sets the wiki mode level for a page.
   * @param $page_id string the page ID
   * @param $namespace string the namespace
   * @param $level int 0 for off, 1 for on, 2 for use global setting
   * @return string "GOOD" on success, error string on failure
   */
  
  function setwikimode($page_id, $namespace, $level)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('set_wiki_mode')) return('Insufficient access rights');
    if ( !isset($level) || ( isset($level) && !preg_match('#^([0-2]){1}$#', (string)$level) ) )
    {
      return('Invalid mode string');
    }
    $q = $db->sql_query('UPDATE ' . table_prefix.'pages SET wiki_mode=' . $level . ' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
    if ( !$q )
    {
      return('Error during update query: '.mysql_error()."\n\nSQL Backtrace:\n".$db->sql_backtrace());
    }
    return('GOOD');
  }
  
  /**
   * Sets the access password for a page.
   * @param $page_id string the page ID
   * @param $namespace string the namespace
   * @param $pass string the SHA1 hash of the password - if the password doesn't match the regex ^([0-9a-f]*){40,40}$ it will be sha1'ed
   * @return string
   */
  
  function setpass($page_id, $namespace, $pass)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // Determine permissions
    if($paths->pages[$paths->nslist[$namespace].$page_id]['password'] != '')
      $a = $session->get_permissions('password_reset');
    else
      $a = $session->get_permissions('password_set');
    if(!$a)
      return 'Access is denied';
    if(!isset($pass)) return('Password was not set on URL');
    $p = $pass;
    if ( !preg_match('#([0-9a-f]){40,40}#', $p) )
    {
      $p = sha1($p);
    }
    if ( $p == 'da39a3ee5e6b4b0d3255bfef95601890afd80709' )
      // sha1('') = da39a3ee5e6b4b0d3255bfef95601890afd80709
      $p = '';
    $e = $db->sql_query('UPDATE ' . table_prefix.'pages SET password=\'' . $p . '\' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';');
    if ( !$e )
    {
      die('PageUtils::setpass(): Error during update query: '.mysql_error()."\n\nSQL Backtrace:\n".$db->sql_backtrace());
    }
    // Is the new password blank?
    if ( $p == '' )
    {
      return('The password for this page has been disabled.');
    }
    else return('The password for this page has been set.');
  }
  
  /**
   * Generates some preview HTML
   * @param $text string the wikitext to use
   * @return string
   */
   
  function genPreview($text)
  {
    $ret = '<div class="info-box"><b>Reminder:</b> This is only a preview - your changes to this page have not yet been saved.</div><div style="background-color: #F8F8F8; padding: 10px; border: 1px dashed #406080; max-height: 250px; overflow: auto; margin: 1em 0 1em 1em;">';
    $text = RenderMan::render(RenderMan::preprocess_text($text, false, false));
    ob_start();
    eval('?>' . $text);
    $text = ob_get_contents();
    ob_end_clean();
    $ret .= $text;
    $ret .= '</div>';
    return $ret;
  }
  
  /**
   * Makes a scrollable box
   * @param string $text the inner HTML
   * @param int $height Optional - the maximum height. Defaults to 250.
   * @return string
   */
   
  function scrollBox($text, $height = 250)
  {
    return '<div style="background-color: #F8F8F8; padding: 10px; border: 1px dashed #406080; max-height: '.(string)intval($height).'px; overflow: auto; margin: 1em 0 1em 1em;">' . $text . '</div>';
  }
  
  /**
   * Generates a diff summary between two page revisions.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $id1 the time ID of the first revision
   * @param $id2 the time ID of the second revision
   * @return string XHTML-formatted diff
   */
   
  function pagediff($page_id, $namespace, $id1, $id2)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('history_view'))
      return 'Access denied';
    if(!preg_match('#^([0-9]+)$#', (string)$id1) ||
       !preg_match('#^([0-9]+)$#', (string)$id2  )) return 'SQL injection attempt';
    // OK we made it through security
    // Safest way to make sure we don't end up with the revisions in wrong columns is to make 2 queries
    if(!$q1 = $db->sql_query('SELECT page_text,char_tag,author,edit_summary FROM ' . table_prefix.'logs WHERE time_id=' . $id1 . ' AND log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';')) return 'MySQL error: '.mysql_error();
    if(!$q2 = $db->sql_query('SELECT page_text,char_tag,author,edit_summary FROM ' . table_prefix.'logs WHERE time_id=' . $id2 . ' AND log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';')) return 'MySQL error: '.mysql_error();
    $row1 = $db->fetchrow($q1);
    $db->free_result($q1);
    $row2 = $db->fetchrow($q2);
    $db->free_result($q2);
    if(sizeof($row1) < 1 || sizeof($row2) < 2) return 'Couldn\'t find any rows that matched the query. The time ID probably doesn\'t exist in the logs table.';
    $text1 = $row1['page_text'];
    $text2 = $row2['page_text'];
    $time1 = date('F d, Y h:i a', $id1);
    $time2 = date('F d, Y h:i a', $id2);
    $_ob = "
    <p>Comparing revisions: {$time1} &rarr; {$time2}</p>
    ";
    // Free some memory
    unset($row1, $row2, $q1, $q2);
    
    $_ob .= RenderMan::diff($text1, $text2);
    return $_ob;
  }
  
  /**
   * Gets ACL information about the selected page for target type X and target ID Y.
   * @param string $page_id The page ID
   * @param string $namespace The namespace
   * @param array $parms What to select. This is an array purely for JSON compatibility. It should be an associative array with keys target_type and target_id.
   * @return array
   */
   
  function acl_editor($parms = Array())
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('edit_acl') && $session->user_level < USER_LEVEL_ADMIN)
    {
      return Array(
        'mode' => 'error',
        'error' => 'You are not authorized to view or edit access control lists.'
        );
    }
    $parms['page_id'] = ( isset($parms['page_id']) ) ? $parms['page_id'] : false;
    $parms['namespace'] = ( isset($parms['namespace']) ) ? $parms['namespace'] : false;
    $page_id =& $parms['page_id'];
    $namespace =& $parms['namespace'];
    $page_where_clause      = ( empty($page_id) || empty($namespace) ) ? 'AND a.page_id IS NULL AND a.namespace IS NULL' : 'AND a.page_id=\'' . $db->escape($page_id) . '\' AND a.namespace=\'' . $db->escape($namespace) . '\'';
    $page_where_clause_lite = ( empty($page_id) || empty($namespace) ) ? 'AND page_id IS NULL AND namespace IS NULL' : 'AND page_id=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $db->escape($namespace) . '\'';
    //die(print_r($page_id,true));
    $template->load_theme();
    // $perms_obj = $session->fetch_page_acl($page_id, $namespace);
    $perms_obj =& $session;
    $return = Array();
    if ( !file_exists(ENANO_ROOT . '/themes/' . $session->theme . '/acledit.tpl') )
    {
      return Array(
        'mode' => 'error',
        'error' => 'It seems that (a) the file acledit.tpl is missing from these theme, and (b) the JSON response is working.',
      );
    }
    $return['template'] = $template->extract_vars('acledit.tpl');
    $return['page_id'] = $page_id;
    $return['namespace'] = $namespace;
    if(isset($parms['mode']))
    {
      switch($parms['mode'])
      {
        case 'listgroups':
          $return['groups'] = Array();
          $q = $db->sql_query('SELECT group_id,group_name FROM ' . table_prefix.'groups ORDER BY group_name ASC;');
          while($row = $db->fetchrow())
          {
            $return['groups'][] = Array(
              'id' => $row['group_id'],
              'name' => $row['group_name'],
              );
          }
          $db->free_result();
          $return['page_groups'] = Array();
          $q = $db->sql_query('SELECT pg_id,pg_name FROM ' . table_prefix.'page_groups ORDER BY pg_name ASC;');
          if ( !$q )
            return Array(
              'mode' => 'error',
              'error' => $db->get_error()
              );
          while ( $row = $db->fetchrow() )
          {
            $return['page_groups'][] = Array(
                'id' => $row['pg_id'],
                'name' => $row['pg_name']
              );
          }
          break;
        case 'seltarget':
          $return['mode'] = 'seltarget';
          $return['acl_types'] = $perms_obj->acl_types;
          $return['acl_deps'] = $perms_obj->acl_deps;
          $return['acl_descs'] = $perms_obj->acl_descs;
          $return['target_type'] = $parms['target_type'];
          $return['target_id'] = $parms['target_id'];
          switch($parms['target_type'])
          {
            case ACL_TYPE_USER:
              $q = $db->sql_query('SELECT a.rules,u.user_id FROM ' . table_prefix.'users AS u
                  LEFT JOIN ' . table_prefix.'acl AS a
                    ON a.target_id=u.user_id
                  WHERE a.target_type='.ACL_TYPE_USER.'
                    AND u.username=\'' . $db->escape($parms['target_id']) . '\'
                    ' . $page_where_clause . ';');
              if(!$q)
                return(Array('mode'=>'error','error'=>mysql_error()));
              if($db->numrows() < 1)
              {
                $return['type'] = 'new';
                $q = $db->sql_query('SELECT user_id FROM ' . table_prefix.'users WHERE username=\'' . $db->escape($parms['target_id']) . '\';');
                if(!$q)
                  return(Array('mode'=>'error','error'=>mysql_error()));
                if($db->numrows() < 1)
                  return Array('mode'=>'error','error'=>'The username you entered was not found.');
                $row = $db->fetchrow();
                $return['target_name'] = $return['target_id'];
                $return['target_id'] = intval($row['user_id']);
                $return['current_perms'] = $session->acl_types;
              }
              else
              {
                $return['type'] = 'edit';
                $row = $db->fetchrow();
                $return['target_name'] = $return['target_id'];
                $return['target_id'] = intval($row['user_id']);
                $return['current_perms'] = $session->acl_merge($perms_obj->acl_types, $session->string_to_perm($row['rules']));
              }
              $db->free_result();
              // Eliminate types that don't apply to this namespace
              if ( $namespace && $namespace != '__PageGroup' )
              {
                foreach ( $return['current_perms'] AS $i => $perm )
                {
                  if ( ( $page_id != null && $namespace != null ) && ( !in_array ( $namespace, $session->acl_scope[$i] ) && !in_array('All', $session->acl_scope[$i]) ) )
                  {
                    // echo "// SCOPE CONTROL: eliminating: $i\n";
                    unset($return['current_perms'][$i]);
                    unset($return['acl_types'][$i]);
                    unset($return['acl_descs'][$i]);
                    unset($return['acl_deps'][$i]);
                  }
                }
              }
              break;
            case ACL_TYPE_GROUP:
              $q = $db->sql_query('SELECT a.rules,g.group_name,g.group_id FROM ' . table_prefix.'groups AS g
                  LEFT JOIN ' . table_prefix.'acl AS a
                    ON a.target_id=g.group_id
                  WHERE a.target_type='.ACL_TYPE_GROUP.'
                    AND g.group_id=\''.intval($parms['target_id']).'\'
                    ' . $page_where_clause . ';');
              if(!$q)
                return(Array('mode'=>'error','error'=>mysql_error()));
              if($db->numrows() < 1)
              {
                $return['type'] = 'new';
                $q = $db->sql_query('SELECT group_id,group_name FROM ' . table_prefix.'groups WHERE group_id=\''.intval($parms['target_id']).'\';');
                if(!$q)
                  return(Array('mode'=>'error','error'=>mysql_error()));
                if($db->numrows() < 1)
                  return Array('mode'=>'error','error'=>'The group ID you submitted is not valid.');
                $row = $db->fetchrow();
                $return['target_name'] = $row['group_name'];
                $return['target_id'] = intval($row['group_id']);
                $return['current_perms'] = $session->acl_types;
              }
              else
              {
                $return['type'] = 'edit';
                $row = $db->fetchrow();
                $return['target_name'] = $row['group_name'];
                $return['target_id'] = intval($row['group_id']);
                $return['current_perms'] = $session->acl_merge($session->acl_types, $session->string_to_perm($row['rules']));
              }
              $db->free_result();
              // Eliminate types that don't apply to this namespace
              if ( $namespace && $namespace != '__PageGroup' )
              {
                foreach ( $return['current_perms'] AS $i => $perm )
                {
                  if ( ( $page_id != false && $namespace != false ) && ( !in_array ( $namespace, $session->acl_scope[$i] ) && !in_array('All', $session->acl_scope[$i]) ) )
                  {
                    // echo "// SCOPE CONTROL: eliminating: $i\n"; //; ".print_r($namespace,true).":".print_r($page_id,true)."\n";
                    unset($return['current_perms'][$i]);
                    unset($return['acl_types'][$i]);
                    unset($return['acl_descs'][$i]);
                    unset($return['acl_deps'][$i]);
                  }
                }
              }
              //return Array('mode'=>'debug','text'=>print_r($return, true));
              break;
            default:
              return Array('mode'=>'error','error','Invalid ACL type ID');
              break;
          }
          return $return;
          break;
        case 'save_new':
        case 'save_edit':
          if ( defined('ENANO_DEMO_MODE') )
          {
            return Array('mode'=>'error','error'=>'Editing access control lists is disabled in the administration demo.');
          }
          $q = $db->sql_query('DELETE FROM ' . table_prefix.'acl WHERE target_type='.intval($parms['target_type']).' AND target_id='.intval($parms['target_id']).'
            ' . $page_where_clause_lite . ';');
          if(!$q)
            return Array('mode'=>'error','error'=>mysql_error());
          $rules = $session->perm_to_string($parms['perms']);
          if ( sizeof ( $rules ) < 1 )
          {
            return array(
                'mode' => 'error', 
                'error' => 'Supplied rule list has a length of zero'
              );
          }
          $q = ($page_id && $namespace) ? 'INSERT INTO ' . table_prefix.'acl ( target_type, target_id, page_id, namespace, rules )
                                             VALUES( '.intval($parms['target_type']).', '.intval($parms['target_id']).', \'' . $db->escape($page_id) . '\', \'' . $db->escape($namespace) . '\', \'' . $db->escape($rules) . '\' )' :
                                          'INSERT INTO ' . table_prefix.'acl ( target_type, target_id, rules )
                                             VALUES( '.intval($parms['target_type']).', '.intval($parms['target_id']).', \'' . $db->escape($rules) . '\' )';
          if(!$db->sql_query($q)) return Array('mode'=>'error','error'=>mysql_error());
          return Array(
              'mode' => 'success',
              'target_type' => $parms['target_type'],
              'target_id' => $parms['target_id'],
              'target_name' => $parms['target_name'],
              'page_id' => $page_id,
              'namespace' => $namespace,
            );
          break;
        case 'delete':
          if ( defined('ENANO_DEMO_MODE') )
          {
            return Array('mode'=>'error','error'=>'Editing access control lists is disabled in the administration demo.');
          }
          $q = $db->sql_query('DELETE FROM ' . table_prefix.'acl WHERE target_type='.intval($parms['target_type']).' AND target_id='.intval($parms['target_id']).'
            ' . $page_where_clause_lite . ';');
          if(!$q)
            return Array('mode'=>'error','error'=>mysql_error());
          return Array(
              'mode' => 'delete',
              'target_type' => $parms['target_type'],
              'target_id' => $parms['target_id'],
              'target_name' => $parms['target_name'],
              'page_id' => $page_id,
              'namespace' => $namespace,
            );
          break;
        default:
          return Array('mode'=>'error','error'=>'Hacking attempt');
          break;
      }
    }
    return $return;
  }
  
  /**
   * Same as PageUtils::acl_editor(), but the parms are a JSON string instead of an array. This also returns a JSON string.
   * @param string $parms Same as PageUtils::acl_editor/$parms, but should be a valid JSON string.
   * @return string
   */
   
  function acl_json($parms = '{ }')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $parms = $json->decode($parms);
    $ret = PageUtils::acl_editor($parms);
    $ret = $json->encode($ret);
    return $ret;
  }
  
  /**
   * A non-Javascript frontend for the ACL API.
   * @param array The request data, if any, this should be in the format required by PageUtils::acl_editor()
   */
   
  function aclmanager($parms)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    ob_start();
    // Convenience
    $formstart = '<form 
                    action="' . makeUrl($paths->page, 'do=aclmanager', true) . '"
                    method="post" enctype="multipart/form-data"
                    onsubmit="if(!submitAuthorized) return false;"
                    >';
    $formend   = '</form>';
    $parms    = PageUtils::acl_preprocess($parms);
    $response = PageUtils::acl_editor($parms);
    $response = PageUtils::acl_postprocess($response);
    
    //die('<pre>' . htmlspecialchars(print_r($response, true)) . '</pre>');
    
    switch($response['mode'])
    {
      case 'debug':
        echo '<pre>' . htmlspecialchars($response['text']) . '</pre>';
        break;
      case 'stage1':
        echo '<h3>Manage page access</h3>
              <p>Please select who should be affected by this access rule.</p>';
        echo $formstart;
        echo '<p><label><input type="radio" name="data[target_type]" value="' . ACL_TYPE_GROUP . '" checked="checked" /> A usergroup</label></p>
              <p><select name="data[target_id_grp]">';
        foreach ( $response['groups'] as $group )
        {
          echo '<option value="' . $group['id'] . '">' . $group['name'] . '</option>';
        }
        // page group selector
        $groupsel = '';
        if ( count($response['page_groups']) > 0 )
        {
          $groupsel = '<p><label><input type="radio" name="data[scope]" value="page_group" /> A group of pages</label></p>
                       <p><select name="data[pg_id]">';
          foreach ( $response['page_groups'] as $grp )
          {
            $groupsel .= '<option value="' . $grp['id'] . '">' . htmlspecialchars($grp['name']) . '</option>';
          }
          $groupsel .= '</select></p>';
        }
        
        echo '</select></p>
              <p><label><input type="radio" name="data[target_type]" value="' . ACL_TYPE_USER . '" /> A specific user</label></p>
              <p>' . $template->username_field('data[target_id_user]') . '</p>
              <p>What should this access rule control?</p>
              <p><label><input name="data[scope]" value="only_this" type="radio" checked="checked" /> Only this page</p>
              ' . $groupsel . '
              <p><label><input name="data[scope]" value="entire_site" type="radio" /> The entire site</p>
              <div style="margin: 0 auto 0 0; text-align: right;">
                <input name="data[mode]" value="seltarget" type="hidden" />
                <input type="hidden" name="data[page_id]" value="' . $paths->cpage['urlname_nons'] . '" />
                <input type="hidden" name="data[namespace]" value="' . $paths->namespace . '" />
                <input type="submit" value="Next &gt;" />
              </div>';
        echo $formend;
        break;
      case 'success':
        echo '<div class="info-box">
                <b>Permissions updated</b><br />
                The permissions for ' . $response['target_name'] . ' on this page have been updated successfully.<br />
                ' . $formstart . '
                <input type="hidden" name="data[mode]" value="seltarget" />
                <input type="hidden" name="data[target_type]" value="' . $response['target_type'] . '" />
                <input type="hidden" name="data[target_id_user]" value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[target_id_grp]"  value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[scope]" value="' . ( ( $response['page_id'] ) ? 'only_this' : 'entire_site' ) . '" />
                <input type="hidden" name="data[page_id]" value="' . ( ( $response['page_id'] ) ? $response['page_id'] : 'false' ) . '" />
                <input type="hidden" name="data[namespace]" value="' . ( ( $response['namespace'] ) ? $response['namespace'] : 'false' ) . '" />
                <input type="submit" value="Return to ACL editor" /> <input type="submit" name="data[act_go_stage1]" value="Return to user/scope selection" />
                ' . $formend . '
              </div>';
        break;
      case 'delete':
        echo '<div class="info-box">
                <b>Rule deleted</b><br />
                The selected access rule has been successfully deleted.<br />
                ' . $formstart . '
                <input type="hidden" name="data[mode]" value="seltarget" />
                <input type="hidden" name="data[target_type]" value="' . $response['target_type'] . '" />
                <input type="hidden" name="data[target_id_user]" value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[target_id_grp]"  value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[scope]" value="' . ( ( $response['page_id'] ) ? 'only_this' : 'entire_site' ) . '" />
                <input type="hidden" name="data[page_id]" value="' . ( ( $response['page_id'] ) ? $response['page_id'] : 'false' ) . '" />
                <input type="hidden" name="data[namespace]" value="' . ( ( $response['namespace'] ) ? $response['namespace'] : 'false' ) . '" />
                <input type="submit" value="Return to ACL editor" /> <input type="submit" name="data[act_go_stage1]" value="Return to user/scope selection" />
                ' . $formend . '
              </div>';
        break;
      case 'seltarget':
        if ( $response['type'] == 'edit' )
        {
          echo '<h3>Editing permissions</h3>';
        }
        else
        {
          echo '<h3>Create new rule</h3>';
        }
        $type  = ( $response['target_type'] == ACL_TYPE_GROUP ) ? 'group' : 'user';
        $scope = ( $response['page_id'] ) ? ( $response['namespace'] == '__PageGroup' ? 'this group of pages' : 'this page' ) : 'this entire site';
        echo 'This panel allows you to edit what the ' . $type . ' "' . $response['target_name'] . '" can do on <b>' . $scope . '</b>. Unless you set a permission to "Deny", these permissions may be overridden by other rules.';
        echo $formstart;
        $parser = $template->makeParserText( $response['template']['acl_field_begin'] );
        echo $parser->run();
        $parser = $template->makeParserText( $response['template']['acl_field_item'] );
        $cls = 'row2';
        foreach ( $response['acl_types'] as $acl_type => $value )
        {
          $vars = Array(
              'FIELD_DENY_CHECKED' => '',
              'FIELD_DISALLOW_CHECKED' => '',
              'FIELD_WIKIMODE_CHECKED' => '',
              'FIELD_ALLOW_CHECKED' => '',
            );
          $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
          $vars['ROW_CLASS'] = $cls;
          
          switch ( $response['current_perms'][$acl_type] )
          {
            case AUTH_ALLOW:
              $vars['FIELD_ALLOW_CHECKED'] = 'checked="checked"';
              break;
            case AUTH_WIKIMODE:
              $vars['FIELD_WIKIMODE_CHECKED'] = 'checked="checked"';
              break;
            case AUTH_DISALLOW:
            default:
              $vars['FIELD_DISALLOW_CHECKED'] = 'checked="checked"';
              break;
             case AUTH_DENY:
              $vars['FIELD_DENY_CHECKED'] = 'checked="checked"';
              break;
          }
          $vars['FIELD_NAME'] = 'data[perms][' . $acl_type . ']';
          $vars['FIELD_DESC'] = $response['acl_descs'][$acl_type];
          $parser->assign_vars($vars);
          echo $parser->run();
        }
        $parser = $template->makeParserText( $response['template']['acl_field_end'] );
        echo $parser->run();
        echo '<div style="margin: 10px auto 0 0; text-align: right;">
                <input name="data[mode]" value="save_' . $response['type'] . '" type="hidden" />
                <input type="hidden" name="data[page_id]" value="'   . (( $response['page_id']   ) ? $response['page_id']   : 'false') . '" />
                <input type="hidden" name="data[namespace]" value="' . (( $response['namespace'] ) ? $response['namespace'] : 'false') . '" />
                <input type="hidden" name="data[target_type]" value="' . $response['target_type'] . '" />
                <input type="hidden" name="data[target_id]" value="' . $response['target_id'] . '" />
                <input type="hidden" name="data[target_name]" value="' . $response['target_name'] . '" />
                ' . ( ( $response['type'] == 'edit' ) ? '<input type="submit" value="Save changes" />&nbsp;&nbsp;<input type="submit" name="data[act_delete_rule]" value="Delete rule" style="color: #AA0000;" onclick="return confirm(\'Do you really want to delete this ACL rule?\');" />' : '<input type="submit" value="Create rule" />' ) . '
              </div>';
        echo $formend;
        break;
      case 'error':
        ob_end_clean();
        die_friendly('Error occurred', '<p>Error returned by permissions API:</p><pre>' . htmlspecialchars($response['error']) . '</pre>');
        break;
    }
    $ret = ob_get_contents();
    ob_end_clean();
    echo
      $template->getHeader() .
      $ret .
      $template->getFooter();
  }
  
  /**
   * Preprocessor to turn the form-submitted data from the ACL editor into something the backend can handle
   * @param array The posted data
   * @return array
   * @access private
   */
   
  function acl_preprocess($parms)
  {
    if ( !isset($parms['mode']) )
      // Nothing to do
      return $parms;
    switch ( $parms['mode'] )
    {
      case 'seltarget':
        
        // Who's affected?
        $parms['target_type'] = intval( $parms['target_type'] );
        $parms['target_id'] = ( $parms['target_type'] == ACL_TYPE_GROUP ) ? $parms['target_id_grp'] : $parms['target_id_user'];
        
      case 'save_edit':
      case 'save_new':
        if ( isset($parms['act_delete_rule']) )
        {
          $parms['mode'] = 'delete';
        }
        
        // Scope (just this page or entire site?)
        if ( $parms['scope'] == 'entire_site' || ( $parms['page_id'] == 'false' && $parms['namespace'] == 'false' ) )
        {
          $parms['page_id']   = false;
          $parms['namespace'] = false;
        }
        else if ( $parms['scope'] == 'page_group' )
        {
          $parms['page_id'] = $parms['pg_id'];
          $parms['namespace'] = '__PageGroup';
        }
        
        break;
    }
    
    if ( isset($parms['act_go_stage1']) )
    {
      $parms = array(
          'mode' => 'listgroups'
        );
    }
    
    return $parms;
  }
  
  function acl_postprocess($response)
  {
    if(!isset($response['mode']))
    {
      if ( isset($response['groups']) )
        $response['mode'] = 'stage1';
      else
        $response = Array(
            'mode' => 'error',
            'error' => 'Invalid action passed by API backend.',
          );
    }
    return $response;
  }
   
}

?>
