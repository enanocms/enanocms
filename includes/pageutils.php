<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
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
  
  public static function checkusername($name)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $name = str_replace('_', ' ', $name);
    $q = $db->sql_query('SELECT username FROM ' . table_prefix.'users WHERE username=\'' . $db->escape(rawurldecode($name)) . '\'');
    if ( !$q )
    {
      die($db->get_error());
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
   
  public static function getsource($page, $password = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!isPage($page))
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
   * DEPRECATED. Previously returned the full rendered contents of a page.
   * @param $page the full page id (Namespace:Pagename)
   * @param $send_headers true if the theme headers should be sent (still dependent on current page settings), false otherwise
   * @return string
   */
  
  public static function getpage($page, $send_headers = false, $hist_id = false)
  {
    die('PageUtils->getpage is deprecated.');
  }
  
  /**
   * Writes page data to the database, after verifying permissions and running the XSS filter
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $message the text to save
   * @return string
   */
   
  public static function savepage($page_id, $namespace, $message, $summary = 'No edit summary given', $minor = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $uid = sha1(microtime());
    $pname = $paths->nslist[$namespace] . $page_id;
    
    if(!$session->get_permissions('edit_page'))
      return 'Access to edit pages is denied.';
    
    if(!isPage($pname))
    {
      $create = PageUtils::createPage($page_id, $namespace);
      if ( $create != 'good' )
        return 'The page did not exist, and I was not able to create it. The reported error was: ' . $create;
      $paths->page_exists = true;
    }
    
    // Check page protection
    
    $is_protected = false;
    $page_data =& $paths->pages[$pname];
    // Is the protection semi?
    if ( $page_data['protected'] == 2 )
    {
      $is_protected = true;
      // Page is semi-protected. Has the user been here for at least 4 days?
      // 345600 seconds = 4 days
      if ( $session->user_logged_in && ( $session->reg_time + 345600 ) <= time() )
        $is_protected = false;
    }
    // Is the protection full?
    else if ( $page_data['protected'] == 1 )
    {
      $is_protected = true;
    }
    
    // If it's protected and we DON'T have even_when_protected rights, bail out
    if ( $is_protected && !$session->get_permissions('even_when_protected') )
    {
      return 'You don\'t have the necessary permissions to edit this page.';
    }
    
    // We're skipping the wiki mode check here because by default edit_page pemissions are AUTH_WIKIMODE.
    // The exception here is the user's own userpage, which is overridden at the time of account creation.
    // At that point it's set to AUTH_ALLOW, but obviously only for the user's own userpage.
    
    // Strip potentially harmful tags and PHP from the message, dependent upon permissions settings
    $message = RenderMan::preprocess_text($message, false, false);
    
    $msg = $db->escape($message);
    
    $minor = $minor ? ENANO_SQL_BOOLEAN_TRUE : ENANO_SQL_BOOLEAN_FALSE;
    $q='INSERT INTO ' . table_prefix.'logs(log_type,action,time_id,date_string,page_id,namespace,page_text,char_tag,author,edit_summary,minor_edit) VALUES(\'page\', \'edit\', '.time().', \''.enano_date('d M Y h:i a').'\', \'' . $paths->page_id . '\', \'' . $paths->namespace . '\', ' . ENANO_SQL_MULTISTRING_PRFIX . '\'' . $msg . '\', \'' . $uid . '\', \'' . $session->username . '\', \'' . $db->escape(htmlspecialchars($summary)) . '\', ' . $minor . ');';
    if(!$db->sql_query($q)) $db->_die('The history (log) entry could not be inserted into the logs table.');
    
    $q = 'UPDATE ' . table_prefix.'page_text SET page_text=' . ENANO_SQL_MULTISTRING_PRFIX . '\'' . $msg . '\',char_tag=\'' . $uid . '\' WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';';
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
  
  public static function createPage($page_id, $namespace, $name = false, $visible = 1)
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
    if(isPage($pname))
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
    
    /*
    // Dunno why this was here. Enano can handle more flexible names than this...
    $regex = '#^([A-z0-9 _\-\.\/\!\@\(\)]*)$#is';
    if(!preg_match($regex, $name))
    {
      //echo '<b>Notice:</b> PageUtils::createPage: Name contains invalid characters<br />';
      return 'Name contains invalid characters';
    }
    */
    
    $page_id = dirtify_page_id($page_id);
    
    if ( !$name )
      $name = str_replace('_', ' ', $page_id);
    
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
    $qc = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'create\', \'' . $session->username . '\', \'' . $db->escape($page_id) . '\', \'' . $namespace . '\');');
    
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
  public static function protect($page_id, $namespace, $level, $reason)
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
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'unprot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
        break;
      case 1:
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'prot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
        break;
      case 2:
        $q = 'INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'semiprot\', \'' . $session->username . '\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\');';
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
   * @param string the page ID
   * @param string the namespace
   * @param string page password
   * @return string
   */
  
  public static function histlist($page_id, $namespace, $password = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if(!$session->get_permissions('history_view'))
      return 'Access denied';
    
    ob_start();
    
    $pname = $paths->nslist[$namespace] . $page_id;
    
    if ( !isPage($pname) )
    {
      return 'DNE';
    }
    
    if ( isPage($pname['password']) )
    {
      $password_exists = ( !empty($paths->pages[$pname]['password']) && $paths->pages[$pname]['password'] !== sha1('') );
      if ( $password_exists && $password !== $paths->pages[$pname]['password'] )
      {
        return '<p>' . $lang->get('history_err_wrong_password') . '</p>';
      }
    }
    
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    
    $q = 'SELECT log_id,time_id,date_string,page_id,namespace,author,edit_summary,minor_edit FROM ' . table_prefix.'logs WHERE log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND is_draft != 1 ORDER BY time_id DESC;';
    if(!$db->sql_query($q)) $db->_die('The history data for the page "' . $paths->cpage['name'] . '" could not be selected.');
    echo $lang->get('history_page_subtitle') . '
          <h3>' . $lang->get('history_heading_edits') . '</h3>';
    $numrows = $db->numrows();
    if ( $numrows < 1 )
    {
      echo $lang->get('history_no_entries');
    }
    else
    {
      echo '<form action="'.makeUrlNS($namespace, $page_id, 'do=diff').'" onsubmit="ajaxHistDiff(); return false;" method="get">
            <input type="submit" value="' . $lang->get('history_btn_compare') . '" />
            ' . ( urlSeparator == '&' ? '<input type="hidden" name="title" value="' . htmlspecialchars($paths->nslist[$namespace] . $page_id) . '" />' : '' ) . '
            ' . ( $session->sid_super ? '<input type="hidden" name="auth"  value="' . $session->sid_super . '" />' : '') . '
            <input type="hidden" name="do" value="diff" />
            <br /><span>&nbsp;</span>
            <div class="tblholder">
            <table border="0" width="100%" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">' . $lang->get('history_col_diff') . '</th>
              <th>' . $lang->get('history_col_datetime') . '</th>
              <th>' . $lang->get('history_col_user') . '</th>
              <th>' . $lang->get('history_col_summary') . '</th>
              <th>' . $lang->get('history_col_minor') . '</th>
              <th colspan="3">' . $lang->get('history_col_actions') . '</th>
            </tr>'."\n"."\n";
      $cls = 'row2';
      $ticker = 0;
      
      while ( $r = $db->fetchrow() )
      {
        
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
        echo '<td class="' . $cls . '" style="white-space: nowrap;">' . enano_date('d M Y h:i a', intval($r['time_id'])) . '</td class="' . $cls . '">'."\n";
        
        // User
        if ( $session->get_permissions('mod_misc') && is_valid_ip($r['author']) )
        {
          $rc = ' style="cursor: pointer;" title="' . $lang->get('history_tip_rdns') . '" onclick="ajaxReverseDNS(this, \'' . $r['author'] . '\');"';
        }
        else
        {
          $rc = '';
        }
        echo '<td class="' . $cls . '"' . $rc . '><a href="'.makeUrlNS('User', sanitize_page_id($r['author'])).'" ';
        if ( !isPage($paths->nslist['User'] . sanitize_page_id($r['author'])) )
        {
          echo 'class="wikilink-nonexistent"';
        }
        echo '>' . $r['author'] . '</a></td class="' . $cls . '">'."\n";
        
        // Edit summary
        if ( $r['edit_summary'] == 'Automatic backup created when logs were purged' )
        {
          $r['edit_summary'] = $lang->get('history_summary_clearlogs');
        }
        echo '<td class="' . $cls . '">' . $r['edit_summary'] . '</td>'."\n";
        
        // Minor edit
        echo '<td class="' . $cls . '" style="text-align: center;">'. (( $r['minor_edit'] ) ? 'M' : '' ) .'</td>'."\n";
        
        // Actions!
        echo '<td class="' . $cls . '" style="text-align: center;"><a rel="nofollow" href="'.makeUrlNS($namespace, $page_id, 'oldid=' . $r['log_id']) . '" onclick="ajaxHistView(\'' . $r['log_id'] . '\'); return false;">' . $lang->get('history_action_view') . '</a></td>'."\n";
        echo '<td class="' . $cls . '" style="text-align: center;"><a rel="nofollow" href="'.makeUrl($paths->nslist['Special'].'Contributions/' . $r['author']) . '">' . $lang->get('history_action_contrib') . '</a></td>'."\n";
        echo '<td class="' . $cls . '" style="text-align: center;"><a rel="nofollow" href="'.makeUrlNS($namespace, $page_id, 'do=edit&amp;revid=' . $r['log_id']) . '" onclick="ajaxEditor(\'' . $r['log_id'] . '\'); return false;">' . $lang->get('history_action_restore') . '</a></td>'."\n";
        
        echo '</tr>'."\n"."\n";
        
      }
      echo '</table>
            </div>
            <br />
            <input type="hidden" name="do" value="diff" />
            <input type="submit" value="' . $lang->get('history_btn_compare') . '" />
            </form>
            <script type="text/javascript">if ( !KILL_SWITCH ) { buildDiffList(); }</script>';
    }
    $db->free_result();
    echo '<h3>' . $lang->get('history_heading_other') . '</h3>';
    $q = 'SELECT log_id,time_id,action,date_string,page_id,namespace,author,edit_summary,minor_edit FROM ' . table_prefix.'logs WHERE log_type=\'page\' AND action!=\'edit\' AND page_id=\'' . $paths->page_id . '\' AND namespace=\'' . $paths->namespace . '\' ORDER BY time_id DESC;';
    if ( !$db->sql_query($q) )
    {
      $db->_die('The history data for the page "' . htmlspecialchars($paths->cpage['name']) . '" could not be selected.');
    }
    if ( $db->numrows() < 1 )
    {
      echo $lang->get('history_no_entries');
    }
    else
    {
      
      echo '<div class="tblholder">
              <table border="0" width="100%" cellspacing="1" cellpadding="4"><tr>
                <th>' . $lang->get('history_col_datetime') . '</th>
                <th>' . $lang->get('history_col_user') . '</th>
                <th>' . $lang->get('history_col_minor') . '</th>
                <th>' . $lang->get('history_col_action_taken') . '</th>
                <th>' . $lang->get('history_col_extra') . '</th>
                <th colspan="2"></th>
              </tr>';
      $cls = 'row2';
      while($r = $db->fetchrow()) {
        
        if($cls == 'row2') $cls = 'row1';
        else $cls = 'row2';
        
        echo '<tr>';
        
        // Date and time
        echo '<td class="' . $cls . '">' . enano_date('d M Y h:i a', intval($r['time_id'])) . '</td class="' . $cls . '">';
        
        // User
        echo '<td class="' . $cls . '"><a href="'.makeUrlNS('User', sanitize_page_id($r['author'])).'" ';
        if(!isPage($paths->nslist['User'] . sanitize_page_id($r['author']))) echo 'class="wikilink-nonexistent"';
        echo '>' . $r['author'] . '</a></td class="' . $cls . '">';
        
        
        // Minor edit
        echo '<td class="' . $cls . '" style="text-align: center;">'. (( $r['minor_edit'] ) ? 'M' : '' ) .'</td>';
        
        // Action taken
        echo '<td class="' . $cls . '">';
        // Some of these are sanitized at insert-time. Others follow the newer Enano policy of stripping HTML at runtime.
        if    ($r['action']=='prot')     echo $lang->get('history_log_protect') . '</td><td class="' . $cls . '">'     . $lang->get('history_extra_reason') . ' ' . ( $r['edit_summary'] === '__REVERSION__' ? $lang->get('history_extra_protection_reversion') : htmlspecialchars($r['edit_summary']) );
        elseif($r['action']=='unprot')   echo $lang->get('history_log_unprotect') . '</td><td class="' . $cls . '">'   . $lang->get('history_extra_reason') . ' ' . ( $r['edit_summary'] === '__REVERSION__' ? $lang->get('history_extra_protection_reversion') : htmlspecialchars($r['edit_summary']) );
        elseif($r['action']=='semiprot') echo $lang->get('history_log_semiprotect') . '</td><td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . ( $r['edit_summary'] === '__REVERSION__' ? $lang->get('history_extra_protection_reversion') : htmlspecialchars($r['edit_summary']) );
        elseif($r['action']=='rename')   echo $lang->get('history_log_rename') . '</td><td class="' . $cls . '">' . $lang->get('history_extra_oldtitle') . ' '.htmlspecialchars($r['edit_summary']);
        elseif($r['action']=='create')   echo $lang->get('history_log_create') . '</td><td class="' . $cls . '">';
        elseif($r['action']=='delete')   echo $lang->get('history_log_delete') . '</td><td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . $r['edit_summary'];
        elseif($r['action']=='reupload') echo $lang->get('history_log_uploadnew') . '</td><td class="' . $cls . '">' . $lang->get('history_extra_reason') . ' ' . ( $r['edit_summary'] === '__ROLLBACK__' ? $lang->get('history_extra_upload_reversion') : htmlspecialchars($r['edit_summary']) );
        echo '</td>';
        
        // Actions!
        echo '<td class="' . $cls . '" style="text-align: center;"><a rel="nofollow" href="'.makeUrl($paths->nslist['Special'].'Contributions/' . $r['author']) . '">' . $lang->get('history_action_contrib') . '</a></td>';
        echo '<td class="' . $cls . '" style="text-align: center;"><a rel="nofollow" href="'.makeUrlNS($namespace, $page_id, 'do=rollback&amp;id=' . $r['log_id']) . '" onclick="ajaxRollback(\'' . $r['log_id'] . '\'); return false;">' . $lang->get('history_action_revert') . '</a></td>';
        
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
   
  public static function rollback($id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    // placeholder
    return 'PageUtils->rollback() is deprecated - use PageProcessor instead.';
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
   
  public static function addcomment($page_id, $namespace, $name, $subject, $text, $captcha_code = false, $captcha_id = false)
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
      if(strtolower($captcha_code) != strtolower($result)) _die('The confirmation code you entered was incorrect.');
    }
    $text = RenderMan::preprocess_text($text);
    $name = $session->user_logged_in ? RenderMan::preprocess_text($session->username) : RenderMan::preprocess_text($name);
    $subj = RenderMan::preprocess_text($subject);
    if(getConfig('approve_comments', '0')=='1') $appr = '0'; else $appr = '1';
    $q = 'INSERT INTO ' . table_prefix.'comments(page_id,namespace,subject,comment_data,name,user_id,approved,time) VALUES(\'' . $page_id . '\',\'' . $namespace . '\',\'' . $subj . '\',\'' . $text . '\',\'' . $name . '\',' . $session->user_id . ',' . $appr . ','.time().')';
    $e = $db->sql_query($q);
    if(!$e) die('alert(unescape(\''.rawurlencode('Error inserting comment data: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'))');
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
   
  public static function comments_raw($page_id, $namespace, $action = false, $flags = Array(), $_ob = '')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
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
        if(!$e) die('alert(unesape(\''.rawurlencode('Error during query: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'));');
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
        if(!$e) die('alert(unesape(\''.rawurlencode('Error selecting approval status: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'));');
        $r = $db->fetchrow();
        $db->free_result();
        $a = ( $r['approved'] ) ? '0' : '1';
        $q = 'UPDATE ' . table_prefix.'comments SET approved=' . $a . ' WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND ' . $where . ';';
        $e=$db->sql_query($q);
        if(!$e) die('alert(unesape(\''.rawurlencode('Error during query: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'));');
        if($a=='1') $v = $lang->get('comment_btn_mod_unapprove');
        else $v = $lang->get('comment_btn_mod_approve');
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
    $lq = $db->sql_query('SELECT c.comment_id,c.subject,c.name,c.comment_data,c.approved,c.time,c.user_id,c.ip_address,u.user_level,u.email,u.signature,u.user_has_avatar,u.avatar_type
                  FROM ' . table_prefix.'comments AS c
                  LEFT JOIN ' . table_prefix.'users AS u
                    ON c.user_id=u.user_id
                  WHERE page_id=\'' . $page_id . '\'
                  AND namespace=\'' . $namespace . '\' ORDER BY c.time ASC;');
    if(!$lq) _die('The comment text data could not be selected. '.$db->get_error());
    $_ob .= '<h3>' . $lang->get('comment_heading') . '</h3>';
    
    $n = ( $session->get_permissions('mod_comments')) ? $db->numrows() : $num_app;
    
    $subst = array(
        'num_comments' => $n,
        'page_type' => $template->namespace_string
      );
    
    $_ob .= '<p>';
    $_ob .= ( $n == 0 ) ? $lang->get('comment_msg_count_zero', $subst) : ( $n == 1 ? $lang->get('comment_msg_count_one', $subst) : $lang->get('comment_msg_count_plural', $subst) );
    
    if ( $session->get_permissions('mod_comments') && $num_unapp > 0 )
    {
      $_ob .= ' <span style="color: #D84308">' . $lang->get('comment_msg_count_unapp_mod', array( 'num_unapp' => $num_unapp )) . '</span>';
    }
    else if ( !$session->get_permissions('mod_comments') && $num_unapp > 0 )
    {
      $ls = ( $num_unapp == 1 ) ? 'comment_msg_count_unapp_one' : 'comment_msg_count_unapp_plural';
      $_ob .= ' <span>' . $lang->get($ls, array( 'num_unapp' => $num_unapp )) . '</span>';
    }
    $_ob .= '</p>';
    $list = 'list = { ';
    // _die(htmlspecialchars($ttext));
    $i = -1;
    while ( $row = $db->fetchrow($lq) )
    {
      $i++;
      $strings = Array();
      $bool = Array();
      if ( $session->get_permissions('mod_comments') || $row['approved'] == COMMENT_APPROVED )
      {
        $list .= $i . ' : { \'comment\' : unescape(\''.rawurlencode($row['comment_data']).'\'), \'name\' : unescape(\''.rawurlencode($row['name']).'\'), \'subject\' : unescape(\''.rawurlencode($row['subject']).'\'), }, ';
        
        // Comment ID (used in the Javascript apps)
        $strings['ID'] = (string)$i;
        
        // Determine the name, and whether to link to the user page or not
        $name = '';
        if($row['user_id'] > 1) $name .= '<a href="'.makeUrlNS('User', sanitize_page_id(' ', '_', $row['name'])).'">';
        $name .= $row['name'];
        if($row['user_id'] > 1) $name .= '</a>';
        $strings['NAME'] = $name; unset($name);
        
        // Subject
        $s = $row['subject'];
        if(!$row['approved']) $s .= ' <span style="color: #D84308">' . $lang->get('comment_msg_note_unapp') . '</span>';
        $strings['SUBJECT'] = $s;
        
        // Date and time
        $strings['DATETIME'] = enano_date('F d, Y h:i a', $row['time']);
        
        // User level
        switch($row['user_level'])
        {
          default:
          case USER_LEVEL_GUEST:
            $l = $lang->get('user_type_guest');
            break;
          case USER_LEVEL_MEMBER:
          case USER_LEVEL_CHPREF:
            $l = $lang->get('user_type_member');
            break;
          case USER_LEVEL_MOD:
            $l = $lang->get('user_type_mod');
            break;
          case USER_LEVEL_ADMIN:
            $l = $lang->get('user_type_admin');
            break;
        }
        $strings['USER_LEVEL'] = $l; unset($l);
        
        // The actual comment data
        $strings['DATA'] = RenderMan::render($row['comment_data']);
        
        if($session->get_permissions('edit_comments'))
        {
          // Edit link
          $strings['EDIT_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=editcomment&amp;id=' . $row['comment_id']) . '" id="editbtn_' . $i . '">' . $lang->get('comment_btn_edit') . '</a>';
        
          // Delete link
          $strings['DELETE_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=deletecomment&amp;id=' . $row['comment_id']) . '">' . $lang->get('comment_btn_delete') . '</a>';
        }
        else
        {
          // Edit link
          $strings['EDIT_LINK'] = '';
        
          // Delete link
          $strings['DELETE_LINK'] = '';
        }
        
        // Send PM link
        $strings['SEND_PM_LINK'] = ( $session->user_logged_in && $row['user_id'] > 1 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/Compose/To/' . $row['name']) . '">' . $lang->get('comment_btn_send_privmsg') . '</a><br />' : '';
        
        // Add Buddy link
        $strings['ADD_BUDDY_LINK'] = ( $session->user_logged_in && $row['user_id'] > 1 ) ? '<a href="'.makeUrlNS('Special', 'PrivateMessages/FriendList/Add/' . $row['name']) . '">' . $lang->get('comment_btn_add_buddy') . '</a>' : '';
        
        // Mod links
        $applink = '';
        $applink .= '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=admin&amp;action=approve&amp;id=' . $row['comment_id']) . '" id="mdgApproveLink' . $i . '">';
        if($row['approved']) $applink .= $lang->get('comment_btn_mod_unapprove');
        else $applink .= $lang->get('comment_btn_mod_approve');
        $applink .= '</a>';
        $strings['MOD_APPROVE_LINK'] = $applink; unset($applink);
        $strings['MOD_DELETE_LINK'] = '<a href="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=admin&amp;action=delete&amp;id=' . $row['comment_id']) . '">' . $lang->get('comment_btn_mod_delete') . '</a>';
        $strings['MOD_IP_LINK'] = '<span style="opacity: 0.5; filter: alpha(opacity=50);">' . ( ( empty($row['ip_address']) ) ? $lang->get('comment_btn_mod_ip_missing') : $lang->get('comment_btn_mod_ip_notimplemented') ) . '</span>';
        
        // Signature
        $strings['SIGNATURE'] = '';
        if($row['signature'] != '') $strings['SIGNATURE'] = RenderMan::render($row['signature']);
        
        // Avatar
        if ( $row['user_has_avatar'] == 1 )
        {
          $bool['user_has_avatar'] = true;
          $strings['AVATAR_ALT'] = $lang->get('usercp_avatar_image_alt', array('username' => $row['name']));
          $strings['AVATAR_URL'] = make_avatar_url(intval($row['user_id']), $row['avatar_type'], $row['email']);
          $strings['USERPAGE_LINK'] = makeUrlNS('User', $row['name']);
        }
        
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
      if($session->get_permissions('post_comments'))
      {
        $_ob .= '<h3>' . $lang->get('comment_postform_title') . '</h3>';
        $_ob .= $lang->get('comment_postform_blurb');
        if(getConfig('approve_comments', '0')=='1') $_ob .= ' ' . $lang->get('comment_postform_blurb_unapp');
        if(getConfig('comments_need_login') == '1' && !$session->user_logged_in)
        {
          $_ob .= ' ' . $lang->get('comment_postform_blurb_captcha');
        }
        $sn = $session->user_logged_in ? $session->username . '<input name="name" id="mdgScreenName" type="hidden" value="' . $session->username . '" />' : '<input name="name" id="mdgScreenName" type="text" size="35" />';
        $_ob .= '  <a href="#" id="mdgCommentFormLink" style="display: none;" onclick="document.getElementById(\'mdgCommentForm\').style.display=\'block\';this.style.display=\'none\';return false;">' . $lang->get('comment_postform_blurb_link') . '</a>
        <div id="mdgCommentForm">
        <form action="'.makeUrlNS($namespace, $page_id, 'do=comments&amp;sub=postcomment').'" method="post" style="margin-left: 1em">
        <table border="0">
        <tr><td>' . $lang->get('comment_postform_field_name') . '</td><td>' . $sn . '</td></tr>
        <tr><td>' . $lang->get('comment_postform_field_subject') . '</td><td><input name="subj" id="mdgSubject" type="text" size="35" /></td></tr>';
        if(getConfig('comments_need_login') == '1' && !$session->user_logged_in)
        {
          $session->kill_captcha();
          $captcha = $session->make_captcha();
          $_ob .= '<tr><td>' . $lang->get('comment_postform_field_captcha_title') . '<br /><small>' . $lang->get('comment_postform_field_captcha_blurb') . '</small></td><td><img src="'.makeUrlNS('Special', 'Captcha/' . $captcha) . '" alt="Visual confirmation" style="cursor: pointer;" onclick="this.src = \''.makeUrlNS("Special", "Captcha/".$captcha).'/\'+Math.floor(Math.random() * 100000);" /><input name="captcha_id" id="mdgCaptchaID" type="hidden" value="' . $captcha . '" /><br />' . $lang->get('comment_postform_field_captcha_label') . ' <input name="captcha_input" id="mdgCaptchaInput" type="text" size="10" /><br /><small><script type="text/javascript">document.write("' . $lang->get('comment_postform_field_captcha_cantread_js') . '");</script><noscript>' . $lang->get('comment_postform_field_captcha_cantread_nojs') . '</noscript></small></td></tr>';
        }
        $_ob .= '
        <tr><td valign="top">' . $lang->get('comment_postform_field_comment') . '</td><td><textarea name="text" id="mdgCommentArea" rows="10" cols="40"></textarea></td></tr>
        <tr><td colspan="2" style="text-align: center;"><input type="submit" value="' . $lang->get('comment_postform_btn_submit') . '" /></td></tr>
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
   
  public static function comments($page_id, $namespace, $action = false, $id = -1, $_ob = '')
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
  
  public static function comments_html($page_id, $namespace, $action = false, $id = -1, $_ob = '')
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
  
  public static function savecomment($page_id, $namespace, $subject, $text, $old_subject, $old_text, $id = -1)
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
      if(!$s) _die('SQL error during safety check: '.$db->get_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
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
    
      Error returned by MySQL: '.$db->get_error()).'");';
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
  
  public static function savecomment_neater($page_id, $namespace, $subject, $text, $id)
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
      if(!$s) _die('SQL error during safety check: '.$db->get_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
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
    
    Error returned by MySQL: '.$db->get_error();
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
  
  public static function deletecomment($page_id, $namespace, $name, $subj, $text, $id)
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
      if(!$s) _die('SQL error during safety check: '.$db->get_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
      $db->free_result();
    }
    $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND name=\'' . $n . '\' AND subject=\'' . $s . '\' AND comment_data=\'' . $t . '\' LIMIT 1;';
    $e=$db->sql_query($q);
    if(!$e) return('alert(unesape(\''.rawurlencode('Error during query: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'));');
    return('good');
  }
  
  /**
   * Deletes a comment in a cleaner fashion.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $id the comment ID (primary key)
   * @return string
   */
  
  public static function deletecomment_neater($page_id, $namespace, $id)
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
      if(!$s) _die('SQL error during safety check: '.$db->get_error().'<br /><br />Attempted SQL:<br /><pre>'.htmlspecialchars($q).'</pre>');
      $r = $db->fetchrow($s);
      if($db->numrows() < 1 || $r['name'] != $session->username) _die('Safety check failed, probably due to a hacking attempt.');
      $db->free_result();
    }
    $q = 'DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\' AND comment_id=' . $id . ' LIMIT 1;';
    $e=$db->sql_query($q);
    if(!$e) return('alert(unesape(\''.rawurlencode('Error during query: '.$db->get_error().'\n\nQuery:\n' . $q) . '\'));');
    return('good');
  }
  
  /**
   * Renames a page.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @param $name the new name for the page
   * @return string error string or success message
   */
   
  public static function rename($page_id, $namespace, $name)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $pname = $paths->nslist[$namespace] . $page_id;
    
    $prot = ( ( $paths->pages[$pname]['protected'] == 2 && $session->user_logged_in && $session->reg_time + 60*60*24*4 < time() ) || $paths->pages[$pname]['protected'] == 1) ? true : false;
    $wiki = ( ( $paths->pages[$pname]['wiki_mode'] == 2 && getConfig('wiki_mode') == '1') || $paths->pages[$pname]['wiki_mode'] == 1) ? true : false;
    
    if( empty($name)) 
    {
      return($lang->get('ajax_rename_too_short'));
    }
    if( ( $session->get_permissions('rename') && ( ( $prot && $session->get_permissions('even_when_protected') ) || !$prot ) ) && ( $paths->namespace != 'Special' && $paths->namespace != 'Admin' ))
    {
      $e = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,page_id,namespace,author,edit_summary) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'rename\', \'' . $db->escape($paths->page_id) . '\', \'' . $paths->namespace . '\', \'' . $db->escape($session->username) . '\', \'' . $db->escape($paths->cpage['name']) . '\')');
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
        $subst = array(
          'page_name_old' => $paths->pages[$pname]['name'],
          'page_name_new' => $name
          );
        return $lang->get('ajax_rename_success', $subst);
      }
    }
    else
    {
      return($lang->get('etc_access_denied'));
    }
  }
  
  /**
   * Flushes (clears) the action logs for a given page
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string error/success string
   */
   
  public static function flushlogs($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    if ( !is_object($lang) && defined('IN_ENANO_INSTALL') )
    {
      // This is a special exception for the Enano installer, which doesn't init languages yet.
      $lang = new Language('eng');
    }
    if(!$session->get_permissions('clear_logs') && !defined('IN_ENANO_INSTALL'))
    {
      return $lang->get('etc_access_denied');
    }
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
      $minor_edit = ( ENANO_DBLAYER == 'MYSQL' ) ? 'false' : '0';
      $q='INSERT INTO ' . table_prefix.'logs(log_type,action,time_id,date_string,page_id,namespace,page_text,char_tag,author,edit_summary,minor_edit) VALUES(\'page\', \'edit\', '.time().', \''.enano_date('d M Y h:i a').'\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $db->escape($row['page_text']) . '\', \'' . $row['char_tag'] . '\', \'' . $session->username . '\', \''."Automatic backup created when logs were purged".'\', '.$minor_edit.');';
      if(!$db->sql_query($q)) $db->_die('The history (log) entry could not be inserted into the logs table.');
    }
    return $lang->get('ajax_clearlogs_success');
  }
  
  /**
   * Deletes a page.
   * @param string $page_id the condemned page ID
   * @param string $namespace the condemned namespace
   * @param string The reason for deleting the page in question
   * @return string
   */
   
  public static function deletepage($page_id, $namespace, $reason)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $cache;
    $perms = $session->fetch_page_acl($page_id, $namespace);
    $x = trim($reason);
    if ( empty($x) )
    {
      return $lang->get('ajax_delete_need_reason');
    }
    if(!$perms->get_permissions('delete_page')) return('Administrative privileges are required to delete pages, you loser.');
    $e = $db->sql_query('INSERT INTO ' . table_prefix.'logs(time_id,date_string,log_type,action,page_id,namespace,author,edit_summary) VALUES('.time().', \''.enano_date('d M Y h:i a').'\', \'page\', \'delete\', \'' . $page_id . '\', \'' . $namespace . '\', \'' . $session->username . '\', \'' . $db->escape(htmlspecialchars($reason)) . '\')');
    if(!$e) $db->_die('The page log entry could not be inserted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'categories WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page categorization entries could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'comments WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page comments could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'page_text WHERE page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page text entry could not be deleted.');
    $e = $db->sql_query('DELETE FROM ' . table_prefix.'pages WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'');
    if(!$e) $db->_die('The page entry could not be deleted.');
    if ( $namespace == 'File' )
    {
      $e = $db->sql_query('DELETE FROM ' . table_prefix.'files WHERE page_id=\'' . $page_id . '\'');
      if(!$e) $db->_die('The file entry could not be deleted.');
    }
    $cache->purge('page_meta');
    return $lang->get('ajax_delete_success');
  }
  
  /**
   * Deletes files associated with a File page.
   * @param string Page ID
   */
  
  public static function delete_page_files($page_id)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $q = $db->sql_query('SELECT file_id, filename, file_key, time_id, file_extension FROM ' . table_prefix . "files WHERE page_id = '{$db->escape($page_id)}';");
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      // wipe original file
      foreach ( array(
          ENANO_ROOT . "/files/{$row['file_key']}_{$row['time_id']}{$row['file_extension']}",
          ENANO_ROOT . "/files/{$row['file_key']}{$row['file_extension']}"
        ) as $orig_file )
      {
        if ( file_exists($orig_file) )
          @unlink($orig_file);
      }
      
      // wipe cached files
      if ( $dr = @opendir(ENANO_ROOT . '/cache/') )
      {
        // lol404.jpg-1217958283-200x320.jpg
        while ( $dh = @readdir($dr) )
        {
          $regexp = ':^' . preg_quote("{$row['filename']}-{$row['time_id']}-") . '[0-9]+x[0-9]+\.' . ltrim($row['file_extension'], '.') . '$:';
          if ( preg_match($regexp, $dh) )
          {
            @unlink(ENANO_ROOT . "/cache/$dh");
          }
        }
        @closedir($dr);
      }
    }
    
    $q = $db->sql_query('DELETE FROM ' . table_prefix . "files WHERE page_id = '{$db->escape($page_id)}';");
    if ( !$q )
      $db->die();
    
    return true;
  }
  
  /**
   * Increments the deletion votes for a page by 1, and adds the current username/IP to the list of users that have voted for the page to prevent dual-voting
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string
   */
   
  public static function delvote($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $cache;
    
    if ( !$session->get_permissions('vote_delete') )
    {
      return $lang->get('etc_access_denied');
    }
    
    if ( $namespace == 'Admin' || $namespace == 'Special' || $namespace == 'System' )
    {
      return 'Special pages and system messages can\'t be voted for deletion.';
    }
    
    $pname = $paths->nslist[$namespace] . sanitize_page_id($page_id);
    
    if ( !isPage($pname) )
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
      return $lang->get('ajax_delvote_already_voted');
    }
    
    $ips['u'][] = $session->username;
    $ips['ip'][] = $_SERVER['REMOTE_ADDR'];
    $ips = $db->escape( serialize($ips) );
    
    $cv++;
    
    $q = 'UPDATE ' . table_prefix.'pages SET delvotes=' . $cv . ',delvote_ips=\'' . $ips . '\' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $w = $db->sql_query($q);
    if ( !$w )
      $db->_die();
    
    // all done, flush page cache to mark it up
    $cache->purge('page_meta');
    
    return $lang->get('ajax_delvote_success');
  }
  
  /**
   * Resets the number of votes against a page to 0.
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string
   */
  
  public static function resetdelvotes($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $cache;
    
    if(!$session->get_permissions('vote_reset'))
    {
      return $lang->get('etc_access_denied');
    }
    $q = 'UPDATE ' . table_prefix.'pages SET delvotes=0,delvote_ips=\'' . $db->escape(serialize(array('ip'=>array(),'u'=>array()))) . '\' WHERE urlname=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\'';
    $e = $db->sql_query($q);
    if ( !$e )
    {
      $db->_die('The number of delete votes was not reset.');
    }
    else
    {
      $cache->purge('page_meta');
      return $lang->get('ajax_delvote_reset_success');
    }
  }
  
  /**
   * Gets a list of styles for a given theme name. As of Banshee, this returns JSON.
   * @param $id the name of the directory for the theme
   * @return string JSON string with an array containing a list of themes
   */
   
  public static function getstyles()
  {
    
    if ( !preg_match('/^([a-z0-9_-]+)$/', $_GET['id']) )
      return enano_json_encode(false);
    
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
      return(enano_json_encode(Array('mode' => 'error', 'error' => $dir.' is not a dir')));
    }
    
    return enano_json_encode($list);
  }
  
  /**
   * Assembles a Javascript app with category information
   * @param $page_id the page ID
   * @param $namespace the namespace
   * @return string Javascript code
   */
   
  public static function catedit($page_id, $namespace)
  {
    $d = PageUtils::catedit_raw($page_id, $namespace);
    return $d[0] . ' /* BEGIN CONTENT */ document.getElementById("ajaxEditContainer").innerHTML = unescape(\''.rawurlencode($d[1]).'\');';
  }
  
  /**
   * Does the actual HTML/javascript generation for cat editing, but returns an array
   * @access private
   */
   
  public static function catedit_raw($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    ob_start();
    $_ob = '';
    $e = $db->sql_query('SELECT category_id FROM ' . table_prefix.'categories WHERE page_id=\'' . $paths->page_id . '\' AND namespace=\'' . $paths->namespace . '\'');
    if(!$e) jsdie('Error selecting category information for current page: '.$db->get_error());
    $cat_current = Array();
    while($r = $db->fetchrow())
    {
      $cat_current[] = $r;
    }
    $db->free_result();
    $cat_all = Array();
    foreach ( $paths->pages as $i => $_ )
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
    $_ob .= '<h3>' . $lang->get('catedit_title') . '</h3>
             <form name="mdgCatForm" action="'.makeUrlNS($namespace, $page_id, 'do=catedit').'" method="post">';
    if ( sizeof($cat_info) < 1 )
    {
      $_ob .= '<p>' . $lang->get('catedit_no_categories') . '</p>';
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
      
    $_ob .= '<div style="border-top: 1px solid #CCC; padding-top: 5px; margin-top: 10px;"><input name="__enanoSaveButton" ' . $disabled . ' style="font-weight: bold;" type="submit" onclick="ajaxCatSave(); return false;" value="' . $lang->get('etc_save_changes') . '" /> <input name="__enanoCatCancel" type="submit" onclick="ajaxReset(); return false;" value="' . $lang->get('etc_cancel') . '" /></div></form>';
    
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
  
  public static function catsave($page_id, $namespace, $which_cats)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$session->get_permissions('edit_cat')) return('Insufficient privileges to change category information');
    
    $page_perms = $session->fetch_page_acl($page_id, $namespace);
    $page_data =& $paths->pages[$paths->nslist[$namespace].$page_id];
    
    $cat_all = Array();
    foreach ( $paths->pages as $i => $_ )
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
  
  public static function setwikimode($page_id, $namespace, $level)
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
      return('Error during update query: '.$db->get_error()."\n\nSQL Backtrace:\n".$db->sql_backtrace());
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
  
  public static function setpass($page_id, $namespace, $pass)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang, $cache;
    // Determine permissions
    if($paths->pages[$paths->nslist[$namespace].$page_id]['password'] != '')
      $a = $session->get_permissions('password_reset');
    else
      $a = $session->get_permissions('password_set');
    if(!$a)
      return $lang->get('etc_access_denied');
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
      die('PageUtils::setpass(): Error during update query: '.$db->get_error()."\n\nSQL Backtrace:\n".$db->sql_backtrace());
    }
    $cache->purge('page_meta');
    // Is the new password blank?
    if ( $p == '' )
    {
      return $lang->get('ajax_password_disable_success');
    }
    else
    {
      return $lang->get('ajax_password_success');
    }
  }
  
  /**
   * Generates some preview HTML
   * @param $text string the wikitext to use
   * @return string
   */
   
  public static function genPreview($text)
  {
    global $lang;
    $ret = '<div class="info-box">' . $lang->get('editor_preview_blurb') . '</div><div style="background-color: #F8F8F8; padding: 10px; border: 1px dashed #406080; max-height: 250px; overflow: auto; margin: 10px 0;">';
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
   
  public static function scrollBox($text, $height = 250)
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
   
  public static function pagediff($page_id, $namespace, $id1, $id2)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if ( !$session->get_permissions('history_view') )
      return $lang->get('etc_access_denied');
    
    if(!preg_match('#^([0-9]+)$#', (string)$id1) ||
       !preg_match('#^([0-9]+)$#', (string)$id2  )) return 'SQL injection attempt';
    // OK we made it through security
    // Safest way to make sure we don't end up with the revisions in wrong columns is to make 2 queries
    if ( !$q1 = $db->sql_query('SELECT time_id,page_text,char_tag,author,edit_summary FROM ' . table_prefix.'logs WHERE log_id = ' . $id1 . ' AND log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';')) return 'MySQL error: ' . $db->get_error();
    if ( !$q2 = $db->sql_query('SELECT time_id,page_text,char_tag,author,edit_summary FROM ' . table_prefix.'logs WHERE log_id = ' . $id2 . ' AND log_type=\'page\' AND action=\'edit\' AND page_id=\'' . $page_id . '\' AND namespace=\'' . $namespace . '\';')) return 'MySQL error: ' . $db->get_error();
    $row1 = $db->fetchrow($q1);
    $db->free_result($q1);
    $row2 = $db->fetchrow($q2);
    $db->free_result($q2);
    if(sizeof($row1) < 1 || sizeof($row2) < 2) return 'Couldn\'t find any rows that matched the query. The time ID probably doesn\'t exist in the logs table.';
    $text1 = $row1['page_text'];
    $text2 = $row2['page_text'];
    $time1 = enano_date('F d, Y h:i a', $row1['time_id']);
    $time2 = enano_date('F d, Y h:i a', $row2['time_id']);
    $_ob = "
    <p>" . $lang->get('history_lbl_comparingrevisions') . " {$time1} &rarr; {$time2}</p>
    ";
    // Free some memory
    unset($row1, $row2, $q1, $q2);
    
    $_ob .= RenderMan::diff($text1, $text2);
    return $_ob;
  }
  
  /**
   * Gets ACL information about the selected page for target type X and target ID Y.
   * @param array $parms What to select. This is an array purely for JSON compatibility. It should be an associative array with keys target_type and target_id.
   * @return array
   */
   
  public static function acl_editor($parms = Array())
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    if(!$session->get_permissions('edit_acl') && ( $session->user_level < USER_LEVEL_ADMIN || !defined('ACL_ALWAYS_ALLOW_ADMIN_EDIT_ACL')) )
    {
      return Array(
        'mode' => 'error',
        'error' => $lang->get('acl_err_access_denied')
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
        'error' => $lang->get('acl_err_missing_template'),
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
        case 'seltarget_id':
          if ( !is_int($parms['target_id']) )
          {
            return Array(
              'mode' => 'error',
              'error' => 'Expected parameter target_id type int'
              );
          }
          $q = $db->sql_query('SELECT target_id, target_type, page_id, namespace, rules FROM ' . table_prefix . "acl WHERE rule_id = {$parms['target_id']};");
          if ( !$q )
            return Array(
              'mode' => 'error',
              'error' => $db->get_error()
              );
          if ( $db->numrows() < 1 )
            return Array(
              'mode' => 'error',
              'error' => "No rule with ID {$parms['target_id']} found"
              );
            $parms = $db->fetchrow();
            $db->free_result();
            
            // regenerate page selection
            $parms['page_id'] = ( isset($parms['page_id']) ) ? $parms['page_id'] : false;
            $parms['namespace'] = ( isset($parms['namespace']) ) ? $parms['namespace'] : false;
            $parms['mode'] = 'seltarget_id';
            $page_id =& $parms['page_id'];
            $namespace =& $parms['namespace'];
            $page_where_clause      = ( empty($page_id) || empty($namespace) ) ? 'AND a.page_id IS NULL AND a.namespace IS NULL' : 'AND a.page_id=\'' . $db->escape($page_id) . '\' AND a.namespace=\'' . $db->escape($namespace) . '\'';
            $page_where_clause_lite = ( empty($page_id) || empty($namespace) ) ? 'AND page_id IS NULL AND namespace IS NULL' : 'AND page_id=\'' . $db->escape($page_id) . '\' AND namespace=\'' . $db->escape($namespace) . '\'';
            
            $return['page_id'] = $parms['page_id'];
            $return['namespace'] = $parms['namespace'];
            
            // From here, let the seltarget handler take over
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
              $user_col = ( $parms['mode'] == 'seltarget_id' ) ? 'user_id' : 'username';
              $q = $db->sql_query('SELECT a.rules,u.user_id,u.username FROM ' . table_prefix.'users AS u
                  LEFT JOIN ' . table_prefix.'acl AS a
                    ON a.target_id=u.user_id
                  WHERE a.target_type='.ACL_TYPE_USER.'
                    AND u.' . $user_col . ' = \'' . $db->escape($parms['target_id']) . '\'
                    ' . $page_where_clause . ';');
              if(!$q)
                return(Array('mode'=>'error','error'=>$db->get_error()));
              if($db->numrows() < 1)
              {
                $return['type'] = 'new';
                $q = $db->sql_query('SELECT user_id,username FROM ' . table_prefix.'users WHERE username=\'' . $db->escape($parms['target_id']) . '\';');
                if(!$q)
                  return(Array('mode'=>'error','error'=>$db->get_error()));
                if($db->numrows() < 1)
                  return Array('mode'=>'error','error'=>$lang->get('acl_err_user_not_found'),'debug' => $db->sql_backtrace());
                $row = $db->fetchrow();
                $return['target_name'] = $row['username'];
                $return['target_id'] = intval($row['user_id']);
                $return['current_perms'] = array();
              }
              else
              {
                $return['type'] = 'edit';
                $row = $db->fetchrow();
                $return['target_name'] = $row['username'];
                $return['target_id'] = intval($row['user_id']);
                $return['current_perms'] = $session->string_to_perm($row['rules']);
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
                return(Array('mode'=>'error','error'=>$db->get_error()));
              if($db->numrows() < 1)
              {
                $return['type'] = 'new';
                $q = $db->sql_query('SELECT group_id,group_name FROM ' . table_prefix.'groups WHERE group_id=\''.intval($parms['target_id']).'\';');
                if(!$q)
                  return(Array('mode'=>'error','error'=>$db->get_error()));
                if($db->numrows() < 1)
                  return Array('mode'=>'error','error'=>$lang->get('acl_err_bad_group_id'));
                $row = $db->fetchrow();
                $return['target_name'] = $row['group_name'];
                $return['target_id'] = intval($row['group_id']);
                $return['current_perms'] = array();
              }
              else
              {
                $return['type'] = 'edit';
                $row = $db->fetchrow();
                $return['target_name'] = $row['group_name'];
                $return['target_id'] = intval($row['group_id']);
                $return['current_perms'] = $session->string_to_perm($row['rules']);
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
            return Array('mode'=>'error','error'=>$lang->get('acl_err_demo'));
          }
          $q = $db->sql_query('DELETE FROM ' . table_prefix.'acl WHERE target_type='.intval($parms['target_type']).' AND target_id='.intval($parms['target_id']).'
            ' . $page_where_clause_lite . ';');
          if(!$q)
            return Array('mode'=>'error','error'=>$db->get_error());
          if ( sizeof ( $parms['perms'] ) < 1 )
          {
            // As of 1.1.x, this returns success because the rule length is zero if the user selected "inherit" in all columns
            return Array(
              'mode' => 'success',
              'target_type' => $parms['target_type'],
              'target_id' => $parms['target_id'],
              'target_name' => $parms['target_name'],
              'page_id' => $page_id,
              'namespace' => $namespace,
            );
          }
          $rules = $session->perm_to_string($parms['perms']);
          $q = ($page_id && $namespace) ? 'INSERT INTO ' . table_prefix.'acl ( target_type, target_id, page_id, namespace, rules )
                                             VALUES( '.intval($parms['target_type']).', '.intval($parms['target_id']).', \'' . $db->escape($page_id) . '\', \'' . $db->escape($namespace) . '\', \'' . $db->escape($rules) . '\' )' :
                                          'INSERT INTO ' . table_prefix.'acl ( target_type, target_id, rules )
                                             VALUES( '.intval($parms['target_type']).', '.intval($parms['target_id']).', \'' . $db->escape($rules) . '\' )';
          if(!$db->sql_query($q)) return Array('mode'=>'error','error'=>$db->get_error());
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
            return Array('mode'=>'error','error'=>$lang->get('acl_err_demo'));
          }
          $sql = 'DELETE FROM ' . table_prefix.'acl WHERE target_type='.intval($parms['target_type']).' AND target_id='.intval($parms['target_id']).'
            ' . $page_where_clause_lite . ';';
          $q = $db->sql_query($sql);
          if(!$q)
            return Array('mode'=>'error','error'=>$db->get_error());
          return Array(
              'mode' => 'delete',
              'target_type' => $parms['target_type'],
              'target_id' => $parms['target_id'],
              'target_name' => $parms['target_name'],
              'page_id' => $page_id,
              'namespace' => $namespace,
            );
          break;
        case 'list_existing':
          
          $return = array(
              'mode'  => 'list_existing',
              'key'   => acl_list_draw_key(),
              'rules' => array()
            );
          
          $q = $db->sql_query("SELECT a.rule_id, u.username, g.group_name, a.target_type, a.target_id, a.page_id, a.namespace, a.rules, p.pg_name\n"
                  . "  FROM " . table_prefix . "acl AS a\n"
                  . "  LEFT JOIN " . table_prefix . "users AS u\n"
                  . "    ON ( (a.target_type = " . ACL_TYPE_USER . " AND a.target_id = u.user_id) OR (u.user_id IS NULL) )\n"
                  . "  LEFT JOIN " . table_prefix . "groups AS g\n"
                  . "    ON ( (a.target_type = " . ACL_TYPE_GROUP . " AND a.target_id = g.group_id) OR (g.group_id IS NULL) )\n"
                  . "  LEFT JOIN " . table_prefix . "page_groups as p\n"
                  . "    ON ( (a.namespace = '__PageGroup' AND a.page_id = p.pg_id) OR (p.pg_id IS NULL) )\n"
                  . "  WHERE ( a.target_type = " . ACL_TYPE_USER . " OR a.target_type = " . ACL_TYPE_GROUP . " )\n"
                  . "  GROUP BY a.rule_id\n"
                  . "  ORDER BY a.target_type ASC, a.rule_id ASC;"
                );
          
          if ( !$q )
            $db->_die();
          
          while ( $row = $db->fetchrow($q) )
          {
            if ( $row['target_type'] == ACL_TYPE_USER && empty($row['username']) )
            {
              // This is only done if we have an ACL affecting a user that doesn't exist.
              // Nice little bit of maintenance to have.
              if ( !$db->sql_query("DELETE FROM " . table_prefix . "acl WHERE rule_id = {$row['rule_id']};") )
                $db->_die();
              continue;
            }
            $score = get_acl_rule_score($row['rules']);
            $deep_limit = ACL_SCALE_MINIMAL_SHADE;
            // Determine background color of cell by score
            if ( $score > 5 )
            {
              // high score, show in green
              $color = 2.5 * $score;
              if ( $color > 255 )
                $color = 255;
              $color = round($color);
              // blend with the colordepth limit
              $color = $deep_limit + ( ( 0xFF - $deep_limit ) - ( ( $color / 0xFF ) * ( 0xFF - $deep_limit ) ) );
              $color = dechex($color);
              $color = "{$color}ff{$color}";
            }
            else if ( $score < -5 )
            {
              // low score, show in red
              $color = 0 - $score;
              $color = 2.5 * $color;
              if ( $color > 255 )
                $color = 255;
              $color = round($color);
              // blend with the colordepth limit
              $color = $deep_limit + ( ( 0xFF - $deep_limit ) - ( ( $color / 0xFF ) * ( 0xFF - $deep_limit ) ) );
              $color = dechex($color);
              $color = "ff{$color}{$color}";
            }
            else
            {
              $color = 'efefef';
            }
            
            // Rate rule textually based on its score
            if ( $score >= 70 )
              $desc = $lang->get('acl_msg_scale_allow');
            else if ( $score >= 50 )
              $desc = $lang->get('acl_msg_scale_mostly_allow');
            else if ( $score >= 25 )
              $desc = $lang->get('acl_msg_scale_some_allow');
            else if ( $score >= -25 )
              $desc = $lang->get('acl_msg_scale_mixed');
            else if ( $score <= -70 )
              $desc = $lang->get('acl_msg_scale_deny');
            else if ( $score <= -50 )
              $desc = $lang->get('acl_msg_scale_mostly_deny');
            else if ( $score <= -25 )
              $desc = $lang->get('acl_msg_scale_some_deny');
            
            // group and user target info
            $info = '';
            if ( $row['target_type'] == ACL_TYPE_USER )
              $info = $lang->get('acl_msg_list_user', array( 'username' => $row['username'] )); // "(User: {$row['username']})";
            else if ( $row['target_type'] == ACL_TYPE_GROUP )
              $info = $lang->get('acl_msg_list_group', array( 'group' => $row['group_name'] ));
            
            // affected pages info
            if ( $row['page_id'] && $row['namespace'] && $row['namespace'] != '__PageGroup' )
              $info .= $lang->get('acl_msg_list_on_page', array( 'page_name' => "{$row['namespace']}:{$row['page_id']}" ));
            else if ( $row['page_id'] && $row['namespace'] && $row['namespace'] == '__PageGroup' )
              $info .= $lang->get('acl_msg_list_on_page_group', array( 'page_group' => $row['pg_name'] ));
            else
              $info .= $lang->get('acl_msg_list_entire_site');
              
            $score_string = $lang->get('acl_msg_list_score', array
              (
                'score' => $score,
                'desc'  => $desc,
                'info'  => $info
                ));
            $return['rules'][] = array(
              'score_string' => $score_string,
              'rule_id'      => $row['rule_id'],
              'color'        => $color
              );
          }
          
          break;
        case 'list_presets':
          $presets = array();
          $q = $db->sql_query('SELECT page_id AS preset_name, rule_id, rules FROM ' . table_prefix . "acl WHERE target_type = " . ACL_TYPE_PRESET . ";");
          if ( !$q )
            $db->die_json();
          
          while ( $row = $db->fetchrow() )
          {
            $row['rules'] = $session->string_to_perm($row['rules']);
            $presets[] = $row;
          }
          
          return array(
            'mode' => 'list_existing',
            'presets' => $presets
          );
          break;
        case 'save_preset':
          if ( empty($parms['preset_name']) )
          {
            return array(
              'mode' => 'error',
              'error' => $lang->get('acl_err_preset_name_empty')
            );
          }
          $preset_name = $db->escape($parms['preset_name']);
          $q = $db->sql_query('DELETE FROM ' . table_prefix . "acl WHERE target_type = " . ACL_TYPE_PRESET . " AND page_id = '$preset_name';");
          if ( !$q )
            $db->die_json();
          
          $perms = $session->perm_to_string($parms['perms']);
          if ( !$perms )
          {
            return array(
              'mode' => 'error',
              'error' => $lang->get('acl_err_preset_is_blank')
            );
          }
          
          $perms = $db->escape($perms);
          $q = $db->sql_query('INSERT INTO ' . table_prefix . "acl(page_id, target_type, rules) VALUES\n"
                            . "  ( '$preset_name', " . ACL_TYPE_PRESET . ", '$perms' );");
          if ( !$q )
            $db->die_json();
          
          return array(
              'mode' => 'success'
            );
          break;
        case 'trace':
          list($targetpid, $targetns) = RenderMan::strToPageID($parms['page']);
          try
          {
            $perms = $session->fetch_page_acl_user($parms['user'], $targetpid, $targetns);
            $perm_table = array(
                AUTH_ALLOW => 'acl_lbl_field_allow',
                AUTH_WIKIMODE => 'acl_lbl_field_wikimode',
                AUTH_DISALLOW => 'acl_lbl_field_disallow',
                AUTH_DENY => 'acl_lbl_field_deny'
              );
            
            $return = array(
              'mode' => 'trace',
              'perms' => array()
            );
            
            foreach ( $perms->perm_resolve_table as $perm_type => $lookup_data )
            {
              if ( !$session->check_acl_scope($perm_type, $targetns) )
                continue;
              
              $src_l10n = $lang->get($session->acl_inherit_lang_table[$lookup_data['src']], $lookup_data);
              $divclass = preg_replace('/^acl_inherit_/', '', $session->acl_inherit_lang_table[$lookup_data['src']]);
              $perm_string = $lang->get($perm_table[$perms->perms[$perm_type]]);
              $perm_name = $lang->get($session->acl_descs[$perm_type]);
              
              $return['perms'][$perm_type] = array(
                  'divclass' => "acl_inherit acl_$divclass",
                  'perm_type' => $perm_type,
                  'perm_name' => $perm_name,
                  'perm_value' => $perm_string,
                  'perm_src' => $src_l10n,
                  'rule_id' => intval($lookup_data['rule_id']),
                  'bad_deps' => $perms->acl_check_deps($perm_type, true)
                );
            }
            
            // group rules if possible
            $return['groups'] = array();
            foreach ( $return['perms'] as $rule )
            {
              if ( !isset($return['groups'][$rule['rule_id']]) )
              {
                $return['groups'][$rule['rule_id']] = array();
              }
              $return['groups'][$rule['rule_id']][] = $rule['perm_type'];
            }
          }
          catch ( Exception $e )
          {
            $return = array(
                'mode' => 'error',
                'error' => $e->getMessage()
              );
          }
          
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
   
  public static function acl_json($parms = '{ }')
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    try
    {
      $parms = enano_json_decode($parms);
    }
    catch ( Zend_Json_Exception $e )
    {
      $parms = array();
    }
    $ret = PageUtils::acl_editor($parms);
    $ret = enano_json_encode($ret);
    return $ret;
  }
  
  /**
   * A non-Javascript frontend for the ACL API.
   * @param array The request data, if any, this should be in the format required by PageUtils::acl_editor()
   */
   
  public static function aclmanager($parms)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
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
        echo '<h3>' . $lang->get('acl_lbl_welcome_title') . '</h3>
              <p>' . $lang->get('acl_lbl_welcome_body') . '</p>';
        echo $formstart;
        echo '<p><label><input type="radio" name="data[target_type]" value="' . ACL_TYPE_GROUP . '" checked="checked" /> ' . $lang->get('acl_radio_usergroup') . '</label></p>
              <p><select name="data[target_id_grp]">';
        foreach ( $response['groups'] as $group )
        {
          echo '<option value="' . $group['id'] . '">' . $group['name'] . '</option>';
        }
        
        // page group selector
        $groupsel = '';
        if ( count($response['page_groups']) > 0 )
        {
          $groupsel = '<p><label><input type="radio" name="data[scope]" value="page_group" /> ' . $lang->get('acl_radio_scope_pagegroup') . '</label></p>
                       <p><select name="data[pg_id]">';
          foreach ( $response['page_groups'] as $grp )
          {
            $groupsel .= '<option value="' . $grp['id'] . '">' . htmlspecialchars($grp['name']) . '</option>';
          }
          $groupsel .= '</select></p>';
        }
        
        echo '</select></p>
              <p><label><input type="radio" name="data[target_type]" value="' . ACL_TYPE_USER . '" /> ' . $lang->get('acl_radio_user') . '</label></p>
              <p>' . $template->username_field('data[target_id_user]') . '</p>
              <p>' . $lang->get('acl_lbl_scope') . '</p>
              <p><label><input name="data[scope]" value="only_this" type="radio" checked="checked" /> ' . $lang->get('acl_radio_scope_thispage') . '</p>
              ' . $groupsel . '
              <p><label><input name="data[scope]" value="entire_site" type="radio" /> ' . $lang->get('acl_radio_scope_wholesite') . '</p>
              <div style="margin: 0 auto 0 0; text-align: right;">
                <input name="data[mode]" value="seltarget" type="hidden" />
                <input type="hidden" name="data[page_id]" value="' . $paths->page_id . '" />
                <input type="hidden" name="data[namespace]" value="' . $paths->namespace . '" />
                <input type="submit" value="' . htmlspecialchars($lang->get('etc_wizard_next')) . '" />
              </div>';
        echo $formend;
        break;
      case 'success':
        echo '<div class="info-box">
                <b>' . $lang->get('acl_lbl_save_success_title') . '</b><br />
                ' . $lang->get('acl_lbl_save_success_body', array( 'target_name' => $response['target_name'] )) . '<br />
                ' . $formstart . '
                <input type="hidden" name="data[mode]" value="seltarget" />
                <input type="hidden" name="data[target_type]" value="' . $response['target_type'] . '" />
                <input type="hidden" name="data[target_id_user]" value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[target_id_grp]"  value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[scope]" value="' . ( ( $response['page_id'] ) ? 'only_this' : 'entire_site' ) . '" />
                <input type="hidden" name="data[page_id]" value="' . ( ( $response['page_id'] ) ? $response['page_id'] : 'false' ) . '" />
                <input type="hidden" name="data[namespace]" value="' . ( ( $response['namespace'] ) ? $response['namespace'] : 'false' ) . '" />
                <input type="submit" value="' . $lang->get('acl_btn_returnto_editor') . '" /> <input type="submit" name="data[act_go_stage1]" value="' . $lang->get('acl_btn_returnto_userscope') . '" />
                ' . $formend . '
              </div>';
        break;
      case 'delete':
        echo '<div class="info-box">
                <b>' . $lang->get('acl_lbl_delete_success_title') . '</b><br />
                ' . $lang->get('acl_lbl_delete_success_body', array('target_name' => $response['target_name'])) . '<br />
                ' . $formstart . '
                <input type="hidden" name="data[mode]" value="seltarget" />
                <input type="hidden" name="data[target_type]" value="' . $response['target_type'] . '" />
                <input type="hidden" name="data[target_id_user]" value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[target_id_grp]"  value="' . ( ( intval($response['target_type']) == ACL_TYPE_USER ) ? $response['target_name'] : $response['target_id'] ) .'" />
                <input type="hidden" name="data[scope]" value="' . ( ( $response['page_id'] ) ? 'only_this' : 'entire_site' ) . '" />
                <input type="hidden" name="data[page_id]" value="' . ( ( $response['page_id'] ) ? $response['page_id'] : 'false' ) . '" />
                <input type="hidden" name="data[namespace]" value="' . ( ( $response['namespace'] ) ? $response['namespace'] : 'false' ) . '" />
                <input type="submit" value="' . $lang->get('acl_btn_returnto_editor') . '" /> <input type="submit" name="data[act_go_stage1]" value="' . $lang->get('acl_btn_returnto_userscope') . '" />
                ' . $formend . '
              </div>';
        break;
      case 'seltarget':
        if ( $response['type'] == 'edit' )
        {
          echo '<h3>' . $lang->get('acl_lbl_editwin_title_edit') . '</h3>';
        }
        else
        {
          echo '<h3>' . $lang->get('acl_lbl_editwin_title_create') . '</h3>';
        }
        $type  = ( $response['target_type'] == ACL_TYPE_GROUP ) ? $lang->get('acl_target_type_group') : $lang->get('acl_target_type_user');
        $scope = ( $response['page_id'] ) ? ( $response['namespace'] == '__PageGroup' ? $lang->get('acl_scope_type_pagegroup') : $lang->get('acl_scope_type_thispage') ) : $lang->get('acl_scope_type_wholesite');
        $subs = array(
            'target_type' => $type,
            'target' => $response['target_name'],
            'scope_type' => $scope
          );
        echo $lang->get('acl_lbl_editwin_body', $subs);
        echo $formstart;
        $parser = $template->makeParserText( $response['template']['acl_field_begin'] );
        echo $parser->run();
        $parser = $template->makeParserText( $response['template']['acl_field_item'] );
        $cls = 'row2';
        foreach ( $response['acl_types'] as $acl_type => $value )
        {
          $vars = Array(
              'FIELD_INHERIT_CHECKED' => '',
              'FIELD_DENY_CHECKED' => '',
              'FIELD_DISALLOW_CHECKED' => '',
              'FIELD_WIKIMODE_CHECKED' => '',
              'FIELD_ALLOW_CHECKED' => '',
            );
          $cls = ( $cls == 'row1' ) ? 'row2' : 'row1';
          $vars['ROW_CLASS'] = $cls;
          
          switch ( $response['current_perms'][$acl_type] )
          {
            case 'i':
            default:
              $vars['FIELD_INHERIT_CHECKED'] = 'checked="checked"';
              break;
            case AUTH_ALLOW:
              $vars['FIELD_ALLOW_CHECKED'] = 'checked="checked"';
              break;
            case AUTH_WIKIMODE:
              $vars['FIELD_WIKIMODE_CHECKED'] = 'checked="checked"';
              break;
            case AUTH_DISALLOW:
              $vars['FIELD_DISALLOW_CHECKED'] = 'checked="checked"';
              break;
             case AUTH_DENY:
              $vars['FIELD_DENY_CHECKED'] = 'checked="checked"';
              break;
          }
          $vars['FIELD_NAME'] = 'data[perms][' . $acl_type . ']';
          if ( preg_match('/^([a-z0-9_]+)$/', $response['acl_descs'][$acl_type]) )
          {
            $vars['FIELD_DESC'] = $lang->get($response['acl_descs'][$acl_type]);
          }
          else
          {
            $vars['FIELD_DESC'] = $response['acl_descs'][$acl_type];
          }
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
                ' . ( ( $response['type'] == 'edit' ) ? '<input type="submit" value="' . $lang->get('etc_save_changes') . '" />&nbsp;&nbsp;<input type="submit" name="data[act_delete_rule]" value="' . $lang->get('acl_btn_deleterule') . '" style="color: #AA0000;" onclick="return confirm(\'' . addslashes($lang->get('acl_msg_deleterule_confirm')) . '\');" />' : '<input type="submit" value="' . $lang->get('acl_btn_createrule') . '" />' ) . '
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
   
  public static function acl_preprocess($parms)
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
  
  public static function acl_postprocess($response)
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

/**
 * Generates a graphical key showing how the ACL rule list works.
 * @return string
 */

function acl_list_draw_key()
{
  $out  = '<div style="width: 460px; margin: 0 auto; text-align: center; margin-bottom: 10px;">';
  $out .= '<div style="float: left;">&larr; Deny</div>';
  $out .= '<div style="float: right;">Allow &rarr;</div>';
  $out .= 'Neutral';
  $out .= '<div style="clear: both;"></div>';
  // 11 boxes on each side of the center
  $inc = ceil ( ( 0xFF - ACL_SCALE_MINIMAL_SHADE ) / 11 );
  for ( $i = ACL_SCALE_MINIMAL_SHADE; $i <= 0xFF; $i+= $inc )
  {
    $octet = dechex($i);
    $color = "ff$octet$octet";
    $out .= '<div style="background-color: #' . $color . '; float: left; width: 20px;">&nbsp;</div>';
  }
  $out .= '<div style="background-color: #efefef; float: left; width: 20px;">&nbsp;</div>';
  for ( $i = 0xFF; $i >= ACL_SCALE_MINIMAL_SHADE; $i-= $inc )
  {
    $octet = dechex($i);
    $color = "{$octet}ff{$octet}";
    $out .= '<div style="background-color: #' . $color . '; float: left; width: 20px;">&nbsp;</div>';
  }
  $out .= '<div style="clear: both;"></div>';
  $out .= '<div style="float: left;">-100</div>';
  $out .= '<div style="float: right;">+100</div>';
  $out .= '0';
  $out .= '</div>';
  return $out;
}

/**
 * Gets the numerical score for the serialized form of an ACL rule
 */

function get_acl_rule_score($perms)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( is_string($perms) )
    $perms = $session->string_to_perm($perms);
  else if ( !is_array($perms) )
    return false;
  $score = 0;
  foreach ( $perms as $item )
  {
    switch ( $item )
    {
      case AUTH_ALLOW :
        $inc = 2;
        break;
      case AUTH_WIKIMODE:
        $inc = 1;
        break;
      case AUTH_DISALLOW:
        $inc = -1;
        break;
      case AUTH_DENY:
        $inc = -2;
        break;
      default:
        $inc = 0;
        break;
    }
    $score += $inc;
  }
  // this is different from the beta; calculate highest score and
  // get percentage to be fairer to smaller/less broad rules
  $divisor = count($perms) * 2;
  if ( $divisor == 0 )
  {
    return 0;
  }
  $score = 100 * ( $score / $divisor );
  return round($score);
}

?>
