<?php
/*
Plugin Name: Newsboy
Plugin URI: javascript: // No URL yet, stay tuned!
Description: Newsboy adds a news management system to Enano. It can integrate with the Feed Me plugin to provide an additional RSS feed. 
Author: Dan Fuhry
Version: 0.1
Author URI: http://www.enanocms.org/
*/

/*
 * Newsboy
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

// Insert our News namespace
$plugins->attachHook('acl_rule_init', 'NewsBoy_namespace_setup($this);');

// Hook into page rendering
$plugins->attachHook('page_not_found', 'NewsBoy_namespace_handler();');
$plugins->attachHook('send_page_footers', 'NewsBoy_PortalLink();');

// String to determine page type string
$plugins->attachHook('page_type_string_set', 'NewsBoy_set_page_string();');

// Attach to the Feed Me plugin, if it's loaded (if not, the feed handler simply won't get called)
$plugins->attachHook('feed_me_request', 'NewsBoy_feed_handler($mode);');

function NewsBoy_namespace_setup(&$paths)
{
  $paths->create_namespace('NewsBoy', 'News:');
  $paths->addAdminNode('Newsboy portal', 'Configuration', 'NewsboyConfiguration');
  $paths->addAdminNode('Newsboy portal', 'Manage news items', 'NewsboyItemManager');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $session->acl_extend_scope('read',                   'NewsBoy', $paths);
  $session->acl_extend_scope('post_comments',          'NewsBoy', $paths);
  $session->acl_extend_scope('edit_comments',          'NewsBoy', $paths);
  $session->acl_extend_scope('edit_page',              'NewsBoy', $paths);
  $session->acl_extend_scope('view_source',            'NewsBoy', $paths);
  $session->acl_extend_scope('mod_comments',           'NewsBoy', $paths);
  $session->acl_extend_scope('history_view',           'NewsBoy', $paths);
  $session->acl_extend_scope('history_rollback',       'NewsBoy', $paths);
  $session->acl_extend_scope('history_rollback_extra', 'NewsBoy', $paths);
  $session->acl_extend_scope('protect',                'NewsBoy', $paths);
  $session->acl_extend_scope('rename',                 'NewsBoy', $paths);
  $session->acl_extend_scope('clear_logs',             'NewsBoy', $paths);
  $session->acl_extend_scope('vote_delete',            'NewsBoy', $paths);
  $session->acl_extend_scope('vote_reset',             'NewsBoy', $paths);
  $session->acl_extend_scope('delete_page',            'NewsBoy', $paths);
  $session->acl_extend_scope('set_wiki_mode',          'NewsBoy', $paths);
  $session->acl_extend_scope('password_set',           'NewsBoy', $paths);
  $session->acl_extend_scope('password_reset',         'NewsBoy', $paths);
  $session->acl_extend_scope('mod_misc',               'NewsBoy', $paths);
  $session->acl_extend_scope('edit_cat',               'NewsBoy', $paths);
  $session->acl_extend_scope('even_when_protected',    'NewsBoy', $paths);
  $session->acl_extend_scope('upload_files',           'NewsBoy', $paths);
  $session->acl_extend_scope('upload_new_version',     'NewsBoy', $paths);
  $session->acl_extend_scope('create_page',            'NewsBoy', $paths);
  $session->acl_extend_scope('php_in_pages',           'NewsBoy', $paths);
  $session->acl_extend_scope('edit_acl',               'NewsBoy', $paths);
  
}

function NewsBoy_namespace_handler()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( defined('ENANO_FEEDBURNER_INCLUDED') )
  {
    $template->add_header('<link rel="alternate" title="'.getConfig('site_name').' News feed" href="'.makeUrlNS('Special', 'RSS/news', null, true).'" type="application/rss+xml" />');
  }
  
  if ( $paths->namespace != 'NewsBoy' )
    return;
  
  $chk = $paths->page;
  $chk1 = substr($chk, 0, ( strlen($paths->nslist['NewsBoy']) + 8 ));
  $chk2 = substr($chk, 0, ( strlen($paths->nslist['NewsBoy']) + 7 ));
  
  if ( $paths->cpage['urlname_nons'] == 'Portal' || $paths->cpage['urlname_nons'] == 'Archive' || $chk1 == $paths->nslist['NewsBoy'] . 'Archive/' || $chk2 == $paths->nslist['NewsBoy'] . 'Archive' )
  {
    
    // Add admin opener Javascript function
    $template->add_header('<!-- NewsBoy: admin panel nav function -->
    <script type="text/javascript">
      function newsboy_open_admin()
      {
        if ( auth_level < USER_LEVEL_ADMIN )
        {
          ajaxPromptAdminAuth(function(k) {
            ENANO_SID = k;
            auth_level = USER_LEVEL_ADMIN;
            var loc = String(window.location + \'\');
            window.location = append_sid(loc);
            var loc = makeUrlNS(\'Special\', \'Administration\', \'module=\' + namespace_list[\'Admin\'] + \'NewsboyItemManager\');
            if ( (ENANO_SID + \' \').length > 1 )
              window.location = loc;
          }, 9);
          return false;
        }
        var loc = makeUrlNS(\'Special\', \'Administration\', \'module=\' + namespace_list[\'Admin\'] + \'NewsboyItemManager\');
        window.location = loc;
      }
    </script>');
    
    $x = getConfig('nb_portal_title');
    
    $template->tpl_strings['PAGE_NAME'] = ( $paths->cpage['urlname_nons'] == 'Portal' ) ?
          ( ( empty($x) ) ?
              'Welcome to ' . getConfig('site_name') :
              $x ) :
          'News Archive';
    
    if ( !$session->get_permissions('read') )
    {
      die_friendly('Access denied', '<div class="error-box"><b>Access to this page is denied.</b><br />This may be because you are not logged in or you have not met certain criteria for viewing this page.</div>');
    }
    
    $paths->cpage['comments_on'] = 0;
    
    $template->header();
    ( $paths->cpage['urlname_nons'] == 'Portal' ) ? NewsBoy_portal() : NewsBoy_archive();
    $template->footer();
  }
}

function NewsBoy_set_page_string()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $paths->namespace == 'NewsBoy' )
  {
    if ( $paths->cpage['urlname_nons'] == 'Portal' )
    {
      $template->namespace_string = 'portal';
      
      // block editing
      $perm_arr = Array('edit_page' => AUTH_DENY, 'view_source' => AUTH_DENY);
      $session->acl_merge_with_current($perm_arr, false, 2);
    }
    else
    {
      $template->namespace_string = 'news item';
    }
  }
}

function NewsBoy_format_title($title)
{
  $title = strtolower($title);
  $title = preg_replace('/\W/', '-', $title);
  $title = preg_replace('/([-]+)/', '-', $title);
  $title = trim($title, '-');
  return $title;
}

function NewsBoy_feed_handler($mode)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $mode != 'news' )
    return;
  
  $limit = ( $x = $paths->getParam(1) ) ? $x : 20;
  $limit = intval($limit);
  if ( $limit > 50 )
    $limit = 50;
  
  $title = getConfig('site_name') . ': Site news';
  
  $x = getConfig('nb_portal_title');
  $desc = ( empty($x) ) ? 'Welcome to ' . getConfig('site_name') : $x;
  
  $link = makeUrlComplete('NewsBoy', 'Portal');
  $generator = 'Enano CMS ' . enano_version() . ' - NewsBoy plugin';
  $email = getConfig('contact_email');
  
  $rss = new RSS($title, $desc, $link, $generator, $email);
  
  $sql = 'SELECT p.*, l.time_id, l.author, u.user_level,COUNT(c.comment_id) AS num_comments,t.page_text FROM '.table_prefix.'pages AS p
         LEFT JOIN '.table_prefix.'comments AS c
           ON ( c.page_id=p.urlname AND c.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'logs AS l
           ON ( l.page_id=p.urlname AND l.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'users AS u
           ON ( u.username=l.author )
         LEFT JOIN '.table_prefix.'page_text AS t
           ON ( t.page_id=p.urlname AND t.namespace=p.namespace )
         WHERE p.namespace=\'NewsBoy\'
           AND l.action=\'create\'
           AND p.urlname REGEXP \'^([0-9]+)$\'
           AND p.visible=1
         GROUP BY p.urlname
         ORDER BY urlname DESC
         LIMIT '.$limit.';';
  
  $q = $db->sql_unbuffered_query($sql);
  
  if ( !$q )
    $db->_die();
  
  $formatter = new NewsBoyFormatter();
  
  if ( $row = $db->fetchrow() )
  {
    do {
      
      $title = $row['name'];
      $link = makeUrlComplete('NewsBoy', $row['urlname']);
      $desc = RenderMan::render($row['page_text']);
      $time = intval($row['urlname']);
      
      $rss->add_item($title, $link, $desc, $time);
      
    } while ( $row = $db->fetchrow() );
  }
  else
  {
    $rss->add_item('Error', $link, 'No news items yet.', time());
  }
  
  echo $rss->render();
  
}

function NewsBoy_portal()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $news_template = <<<TPLCODE
  <div class="tblholder news">
    <table border="0" cellspacing="1" cellpadding="4" style="width: 100%;">
      <tr>
        <th><a href="{LINK}" style="color: inherit;">{TITLE}</a></th>
      </tr>
      <tr>
        <td class="row3">
          {CONTENT}
        </td>
      </tr>
      <tr>
        <th class="subhead" style="font-weight: normal; font-size: 67%;">
          Posted by {USER_LINK} on {DATE}<br />
          [ {NUM_COMMENTS} comment{COMMENT_S} | {COMMENT_LINK} ]
        </th>
      </tr>
    </table>
  </div>
TPLCODE;
  
  /*
  $p = RenderMan::strToPageID(getConfig('main_page'));
  if ( $p[1] != 'NewsBoy' )
  {
    echo RenderMan::getPage($p[0], $p[1]);
  }
  else
  { */
    /*
    $s = $paths->nslist['NewsBoy'] . 'Announce';
    if ( isPage($s) )
    {
      $p = RenderMan::getPage('Announce', 'NewsBoy');
      echo $p;
    }
  /* } */
  
  $s = $paths->nslist['NewsBoy'] . 'Announce';
  $announce_page = getConfig('nb_announce_page');
  if ( !empty($announce_page) && isPage($announce_page) )
  {
    $s = $announce_page;
  }
  else if ( !isPage($s) )
  {
    $s = false;
  }
  if ( $s )
  {
    $stuff = RenderMan::strToPageID($s);
    $p = RenderMan::getPage($stuff[0], $stuff[1]);
    echo $p;
  }
  
  echo '<h2>Latest news</h2>';
    
  $q = $db->sql_unbuffered_query('SELECT p.*, COUNT(c.comment_id) AS num_comments, t.page_text, l.time_id, l.author, u.user_level FROM '.table_prefix.'pages AS p
         LEFT JOIN '.table_prefix.'comments AS c
           ON ( c.page_id=p.urlname AND c.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'page_text AS t
           ON ( t.page_id=p.urlname AND t.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'logs AS l
           ON ( l.page_id=p.urlname AND l.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'users AS u
           ON ( u.username=l.author OR u.user_id=1 )
         WHERE p.namespace=\'NewsBoy\'
           AND l.action=\'create\'
           AND p.urlname!=\'Announce\'
           AND p.visible=1
         GROUP BY p.urlname
         ORDER BY urlname DESC;');
  if ( !$q )
    $db->_die();
  
  if ( $row = $db->fetchrow() )
  {
    $i = 0;
    $parser = $template->makeParserText($news_template);
    do
    {
      if ( $i < 5 )
      {
        $title = htmlspecialchars($row['name']);
        $content = RenderMan::render($row['page_text']);
        if ( strlen($content) > 400 )
        {
          $content = nb_trim_paragraph($content, 400, $trimmed);
        }
        if ( $trimmed )
        {
          $content .= ' <a href="' . makeUrlNS('NewsBoy', $row['urlname'], false, true) . '">Read more...</a>';
        }
        $user_link = nb_make_username_link($row['author'], $row['user_level']);
        $date = date('F d, Y h:i:s a', $row['urlname']);
        $num_comments = $row['num_comments'];
        $comment_s = ( $num_comments == 1 ) ? '' : 's';
        $comment_link = '<a href="' . makeUrlNS('NewsBoy', $row['urlname'], false, true) . '#do:comments" style="color: inherit;">add a comment</a>';
        $parser->assign_vars(array(
            'TITLE' => $title,
            'LINK' => makeUrlNS('NewsBoy', $row['urlname']),
            'CONTENT' => $content,
            'USER_LINK' => $user_link,
            'DATE' => $date,
            'NUM_COMMENTS' => $num_comments,
            'COMMENT_S' => $comment_s,
            'COMMENT_LINK' => $comment_link
          ));
        echo $parser->run();
      }
      else
      {
        echo '<p><a href="'.makeUrlNS('NewsBoy', 'Archive').'">Older news...</a></p>';
        break;
      }
      $i++;
    } while ( $row = $db->fetchrow() );
  }
  else
  {
    echo '<p>No news items yet.</p>';
  }
  if ( $session->user_level >= USER_LEVEL_ADMIN )
  {
    echo '<div class="tblholder" style="margin: 10px auto 0 auto; display: table;">
            <table border="0" cellspacing="1" cellpadding="4">
              <tr>
                <th>Administrative tools:</th>
                <td class="row3" style="text-align: center;"><a style="color: inherit;" href="' . makeUrlNS('NewsBoy', 'Announce', '', true) . '#do:edit">Edit announcement &raquo;</a></td>
                <td class="row3" style="text-align: center;"><a style="color: inherit;" href="' . makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'NewsboyItemManager', true) . '" onclick="newsboy_open_admin(); return false;">Portal Administration</a></td>
              </tr>
            </table>
          </div><br />';
  }
}

/**
 * Formats row data in the archive.
 * @package Enano
 * @subpackage Newsboy
 * @license GNU General Public License
 */

class NewsBoyFormatter
{
  function article_link($name, $row)
  {
    $article_link = '<a href="' . makeUrlNS('NewsBoy', $row['urlname']) . '">' . $row['name'] . '</a>';
    return $article_link;
  }
  function format_date($date, $row)
  {
    $date = date('Y-m-j g:m', intval ( $date ));
    return $date;
  }
  function format_username($x, $row)
  {
    $ul = intval($row['user_level']);
    $author = nb_make_username_link($row['author'], $ul);
    return $author;
  }
  function format_commentlink($x, $row)
  {
    $comments = '<a href="' . makeUrlNS('NewsBoy', $row['urlname']) . '#do:comments">' . $row['num_comments'] . '</a>';
    return $comments;
  }
}

function NewsBoy_archive()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $lower_limit = ( isset($_GET['start']) ) ? intval($_GET['start']) : ( ( $xx = $paths->getParam(0) ) ? intval($xx) : 0 );
  $entries_per_page = 50;
  
  $row_count = $entries_per_page + 1;
  
  // Determine number of total news entries
  $q = $db->sql_query('SELECT urlname FROM '.table_prefix.'pages WHERE namespace=\'NewsBoy\' AND urlname REGEXP \'^([0-9]+)$\' AND visible=1;');
  if ( !$q )
    $db->_die();
  $r = $db->fetchrow();
  $num_total = intval($db->numrows());
  $db->free_result();
  
  if ( $lower_limit >= $num_total )
    $lower_limit = 0;
  
  $sql = 'SELECT p.*, l.time_id, l.author, u.user_level,COUNT(c.comment_id) AS num_comments FROM '.table_prefix.'pages AS p
         LEFT JOIN '.table_prefix.'comments AS c
           ON ( c.page_id=p.urlname AND c.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'logs AS l
           ON ( l.page_id=p.urlname AND l.namespace=p.namespace )
         LEFT JOIN '.table_prefix.'users AS u
           ON ( u.username=l.author )
         WHERE p.namespace=\'NewsBoy\'
           AND l.action=\'create\'
           AND p.urlname REGEXP \'^([0-9]+)$\'
           AND p.visible=1
         GROUP BY p.urlname
         ORDER BY urlname DESC;';
  
  $q = $db->sql_unbuffered_query($sql);
  
  if ( !$q )
    $db->_die();
  
  $formatter = new NewsBoyFormatter();
  
  $callers = Array(
      'name' => Array($formatter, 'article_link'),
      'urlname' => Array($formatter, 'format_date'),
      'author' => Array($formatter, 'format_username'),
      'num_comments' => Array($formatter, 'format_commentlink')
    );
  
  $head = '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th>Article</th><th>Date</th><th>Author</th><th>Comments</th>
            </tr>';
  $foot = "</table></div>";
  
  $content = paginate($q, "\n".'<tr><td class="{_css_class}">{name}</td><td class="{_css_class}">{urlname}</td><td class="{_css_class}">{author}</td><td class="{_css_class}">{num_comments}</td></tr>',
                      $num_total, makeUrlNS('NewsBoy', 'Archive/%s'), $lower_limit, 20, $callers, $head, $foot);
  echo $content;  
  
  $code = $plugins->setHook('send_page_footers');
  foreach ( $code as $cmd )
  {
    eval($cmd);
  }
  
}

function nb_make_username_link($username, $user_level)
{
  $color = '#0000AA';
  $user_level = intval($user_level);
  if ( $user_level < USER_LEVEL_MEMBER ) return $username;
  if ( $user_level >= USER_LEVEL_MOD ) $color = '#00AA00';
  if ( $user_level >= USER_LEVEL_ADMIN ) $color = '#AA0000';
  $link = '<a style="color: ' . $color . '" href="' . makeUrlNS('User', str_replace(' ', '_', $username) ) . '">' . $username . '</a>';
  return $link;
}

function NewsBoy_PortalLink()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $paths->namespace == 'NewsBoy' )
    echo '<div class="tblholder"><table border="0" style="width: 100%;" cellspacing="1" cellpadding="4"><tr><th><a style="color: inherit;" href="' . makeUrlNS('NewsBoy', 'Portal') . '">&laquo; Return to News Portal</a></th></tr></table></div><br />';
}

// Administration panel
function page_Admin_NewsboyItemManager()
{
  global $db, $session, $paths, $template, $plugins; if($session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN) { redirect(makeUrlNS('Special', 'Administration', 'noheaders', true), '', '', 0); die('Hacking attempt'); }
  
  $done = false;
  
  if ( isset( $_GET['act'] ) )
  {
    switch ( $_GET['act'] )
    {
      case 'edit':
        
        // Error list
        $errors = Array();
        
        if ( isset ( $_POST['submitting'] ) )
        {
          // Generate timestamp
          $year = intval($_POST['pub_year']);
          $month = intval($_POST['pub_month']);
          $day = intval($_POST['pub_day']);
          $hour = intval($_POST['pub_hour']);
          $minute = intval($_POST['pub_minute']);
          $second = intval($_POST['pub_second']);
          
          // Validation
          if ( $year < 1500 || $year > 10000 )
            $errors[] = 'Invalid year.';
          
          if ( $month < 1 || $month > 12 )
            $errors[] = 'Invalid month.';
          
          if ( $day < 1 || $day > 31 )
            $errors[] = 'Invalid day.';
          
          if ( $hour < 0 || $hour > 23 )
            $errors[] = 'Invalid hour.';
          
          if ( $minute < 0 || $minute > 60 )
            $errors[] = 'Invalid minute.';
          
          if ( $second < 0 || $second > 60 )
            $errors[] = 'Invalid second.';
          
          $name = $_POST['article_name'];
          $name = $db->escape($name);
          
          $author = $_POST['author'];
          $author = $db->escape($author);
          
          if ( count($errors) < 1 )
          {
            $time = mktime($hour, $minute, $second, $month, $day, $year);
          }
          
          if ( isset($paths->pages[ $paths->nslist['NewsBoy'] . $time ]) && $paths->pages[ $paths->nslist['NewsBoy'] . $time ] != $paths->pages[ $paths->nslist['NewsBoy'] . $_POST['page_id'] ] )
            $errors[] = 'You cannot have two news articles with the same publish time.';
          
          if ( count($errors) < 1 )
          {
            $publ = ( isset($_POST['published']) ) ? '1' : '0';
            $sql = 'UPDATE '.table_prefix.'pages SET name=\'' . $name . '\',visible='.$publ.',urlname=\''.$time.'\' WHERE urlname=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\';';
            $q = $db->sql_query($sql);
            
            if ( !$q )
              $db->_die();
            
            // Update author
            $q = $db->sql_query('UPDATE '.table_prefix.'logs SET author=\'' . $author . '\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\' AND action=\'create\';');
            
            if ( !$q )
              $db->_die();
            
            // Update other tables with urlname info
            $q = $db->sql_query('UPDATE '.table_prefix.'logs SET page_id=\'' . $time . '\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\';');
            if ( !$q )
              $db->_die();
            
            $q = $db->sql_query('UPDATE '.table_prefix.'comments SET page_id=\'' . $time . '\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\';');
            if ( !$q )
              $db->_die();
            
            $q = $db->sql_query('UPDATE '.table_prefix.'page_text SET page_id=\'' . $time . '\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\';');
            if ( !$q )
              $db->_die();
            
            $q = $db->sql_query('UPDATE '.table_prefix.'categories SET page_id=\'' . $time . '\' WHERE page_id=\'' . $db->escape($_POST['page_id']) . '\' AND namespace=\'NewsBoy\';');
            if ( !$q )
              $db->_die();
            
            echo '<div class="info-box">Your changes have been saved.</div>';
            
            break;
          }
        }
        
        if ( count($errors) > 0 )
          echo '<div class="warning-box">Errors encountered while saving data:<ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        
        // Obtain page information
        if ( !isset($paths->pages[ $paths->nslist['NewsBoy'] . $_GET['id'] ]) )
        {
          echo 'Invalid ID';
          return false;
        }
        $page_info =& $paths->pages[ $paths->nslist['NewsBoy'] . $_GET['id'] ];
        $time = intval($page_info['urlname_nons']);
        
        // Get author
        $q = $db->sql_query('SELECT author FROM '.table_prefix.'logs WHERE page_id=\'' . $db->escape($page_info['urlname_nons']) . '\' AND namespace=\'NewsBoy\' AND action=\'create\' ORDER BY time_id DESC LIMIT 1;');
        
        if ( !$q )
          $db->_die();
        
        $row = $db->fetchrow();
        $author = ( isset($row['author']) ) ? $row['author'] : '';
        if ( empty($author) )
          $author = 'Anonymous';
        
        // Set date & time
        $month  = date('n', $time);
        $year   = date('Y', $time);
        $day    = date('j', $time);
        $hour   = date('G', $time);
        $minute = date('m', $time);
        $second = date('s', $time);
        
        echo '<form id="nb_edit_form" action="'.makeUrlNS('Special', 'Administration', (( isset($_GET['sqldbg'])) ? 'sqldbg&amp;' : '') .'module='.$paths->cpage['module'] . '&act=edit').'" method="post" onsubmit="if ( !submitAuthorized ) return false;">';
        echo '<div class="tblholder">
                <table border="0" cellspacing="1" cellpadding="4">
                  <tr>
                    <th colspan="2">Editing news article</th>
                  </tr>
                  <tr>
                    <td class="row1">Article name:</td><td class="row2"><input name="article_name" value="' . htmlspecialchars($page_info['name']) . '" /></td>
                  </tr>
                  <tr>
                    <td class="row1">Published date:</td>
                    <td class="row2">
                      <input name="pub_year" type="text" size="5" value="'.$year.'" />-<select name="pub_month">';
       for ( $i = 1; $i <= 12; $i++ )
       {
         $m = "[$i] ";
         switch ( $i )
         {
           case 1:  $m .= 'January'; break;
           case 2:  $m .= 'February'; break;
           case 3:  $m .= 'March'; break;
           case 4:  $m .= 'April'; break;
           case 5:  $m .= 'May'; break;
           case 6:  $m .= 'June'; break;
           case 7:  $m .= 'July'; break;
           case 8:  $m .= 'August'; break;
           case 9:  $m .= 'September'; break;
           case 10: $m .= 'October'; break;
           case 11: $m .= 'November'; break;
           case 12: $m .= 'December'; break;
           default: $m .= 'Fuhrober'; break;
         }
         if ( $month == $i )
           echo '         <option selected="selected" value="' . $i . '">'.$m.'</option>';
         else
           echo '         <option value="' . $i . '">'.$m.'</option>';
       }
       echo '         </select>
                      <input name="pub_day" type="text" size="3" value="' . $day . '" />, time:
                      <input name="pub_hour" type="text" size="3" value="' . $hour . '" />&nbsp;:&nbsp;<input name="pub_minute" type="text" size="3" value="' . $minute . '" />&nbsp;:&nbsp;<input name="pub_second" type="text" size="3" value="' . $second . '" /><br />
                      <small>Note: Hours are in 24-hour format.</small>
                    </td>
                  </tr>
                  <!-- Inline developer blog, episode 1:
                       Right about the time I got here, I started sneezing like crazy. Must have caught it Friday night. Great... now
                       my life is officially stuck on pause for the next 3 days. I\'d swear here but (a) Mommy taught me better, and
                       (b) I wouldn\'t want to offend you hackers. (j/k)
                       
                       Oh crap. And no, I don\'t give towels with my showers.
                       
                       -Dan
                       -->
                  <tr>
                    <td class="row1">Publish article:</td><td class="row2"><label><input name="published" type="checkbox" ' . ( $page_info['visible'] == 1 ? 'checked="checked"' : '' ) . ' /> Article is published (shown to the public)</label></td>
                  </tr>
                  <tr>
                    <td class="row1">Article author:</td><td class="row2">' . $template->username_field('author', $author) . '</td></tr>
                  </tr>
                  <tr>
                    <td class="row3" style="text-align: center;" colspan="2">
                      <a href="#" onclick="var frm = document.getElementById(\'nb_edit_form\'); frm.submit(); return false;">Save changes</a>&nbsp;&nbsp;<a href="#" onclick="ajaxPage(\'' . $paths->cpage['module'] . '\');">Return to main menu</a>
                    </td>
                  </tr>
                </table>
              </div>
              <input type="hidden" name="submitting" value="yes" />
              <input type="hidden" name="page_id" value="' . $_GET['id'] . '" />';
        echo '</form>';
        $done = true;
        break;
      case 'del':
        if ( isset( $_POST['confirmed'] ) )
        {
          $page_id = $_POST['page_id'];
          $namespace = 'NewsBoy';
          
          $e = $db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,page_id,namespace,author) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'delete\', \''.$page_id.'\', \''.$namespace.'\', \''.$session->username.'\')');
          if(!$e) $db->_die('The page log entry could not be inserted.');
          $e = $db->sql_query('DELETE FROM '.table_prefix.'categories WHERE page_id=\''.$page_id.'\' AND namespace=\''.$namespace.'\'');
          if(!$e) $db->_die('The page categorization entries could not be deleted.');
          $e = $db->sql_query('DELETE FROM '.table_prefix.'comments WHERE page_id=\''.$page_id.'\' AND namespace=\''.$namespace.'\'');
          if(!$e) $db->_die('The page comments could not be deleted.');
          $e = $db->sql_query('DELETE FROM '.table_prefix.'page_text WHERE page_id=\''.$page_id.'\' AND namespace=\''.$namespace.'\'');
          if(!$e) $db->_die('The page text entry could not be deleted.');
          $e = $db->sql_query('DELETE FROM '.table_prefix.'pages WHERE urlname=\''.$page_id.'\' AND namespace=\''.$namespace.'\'');
          if(!$e) $db->_die('The page entry could not be deleted.');
          $e = $db->sql_query('DELETE FROM '.table_prefix.'files WHERE page_id=\''.$page_id.'\'');
          if(!$e) $db->_die('The file entry could not be deleted.');
          
          $result = 'This page has been deleted. Note that there is still a log of edits and actions in the database, and anyone with admin rights can raise this page from the dead unless the log is cleared. If the deleted file is an image, there may still be cached thumbnails of it in the cache/ directory, which is inaccessible to users.';
          
          echo $result . '<br />
               <br />
               <a href="#" onclick="ajaxPage(\'' . $paths->cpage['module'] . '\');">Return to Newsboy</a>';
        }
        else
        {
          echo '<form id="nb_delete_form" action="'.makeUrlNS('Special', 'Administration', (( isset($_GET['sqldbg'])) ? 'sqldbg&amp;' : '') .'module='.$paths->cpage['module'] . '&act=del').'" method="post">';
          echo '<div class="tblholder">
                  <table border="0" cellspacing="1" cellpadding="4">
                    <tr>
                      <th>Confirm deletion</th>
                     </tr>
                     <tr>
                       <td class="row1" style="text-align: center;">
                         <p>Are you sure you want to delete this news article?</p>
                       </td>
                     </tr>
                     <tr>
                       <td class="row3" style="text-align: center;">
                         <a href="#" onclick="var frm = document.getElementById(\'nb_delete_form\'); frm.submit(); return false;">Delete</a>&nbsp;&nbsp;<a href="#" onclick="ajaxPage(\'' . $paths->cpage['module'] . '\');">Cancel</a>
                       </td>
                     </tr>
                  </table>
                </div>
                <input type="hidden" name="confirmed" value="yes" />
                <input type="hidden" name="page_id" value="' . intval ( $_GET['id'] ) . '" />';
          echo '</form>';
        }
        $done = true;
        break;
      case 'create':
        
        // Error list
        $errors = Array();
        
        if ( isset ( $_POST['submitting'] ) )
        {
          // Generate timestamp
          $year = intval($_POST['pub_year']);
          $month = intval($_POST['pub_month']);
          $day = intval($_POST['pub_day']);
          $hour = intval($_POST['pub_hour']);
          $minute = intval($_POST['pub_minute']);
          $second = intval($_POST['pub_second']);
          
          // Validation
          if ( $year < 1500 || $year > 10000 )
            $errors[] = 'Invalid year.';
          
          if ( $month < 1 || $month > 12 )
            $errors[] = 'Invalid month.';
          
          if ( $day < 1 || $day > 31 )
            $errors[] = 'Invalid day.';
          
          if ( $hour < 0 || $hour > 23 )
            $errors[] = 'Invalid hour.';
          
          if ( $minute < 0 || $minute > 60 )
            $errors[] = 'Invalid minute.';
          
          if ( $second < 0 || $second > 60 )
            $errors[] = 'Invalid second.';
          
          $name = $_POST['article_name'];
          $name = $db->escape($name);
          
          $author = $_POST['author'];
          $author = $db->escape($author);
          
          if ( count($errors) < 1 )
          {
            $time = mktime($hour, $minute, $second, $month, $day, $year);
          }
          
          if ( isset($paths->pages[ $paths->nslist['NewsBoy'] . $time ]) && $paths->pages[ $paths->nslist['NewsBoy'] . $time ] != $paths->pages[ $paths->nslist['NewsBoy'] . $_POST['page_id'] ] )
            $errors[] = 'You cannot have two news articles with the same publish time.';
          
          if ( count($errors) < 1 )
          {
            $publ = ( isset($_POST['published']) ) ? 1 : 0;
            $result = PageUtils::createpage( (string)$time, 'NewsBoy', $name, $publ );
            
            // Set content
            $content = RenderMan::preprocess_text($_POST['content'], true); // this also SQL-escapes it
            
            $q = $db->sql_query('UPDATE '.table_prefix.'page_text SET page_text=\'' . $content . '\' WHERE page_id=\'' . $time . '\' AND namespace=\'NewsBoy\';');
            if ( !$q )
              $db->_die();
            
            if ( $result )
              echo '<div class="info-box">Your changes have been saved.</div>';
            else
              $errors[] = 'PageUtils::createpage returned an error.';
            
            break;
          }
        }
        
        if ( count($errors) > 0 )
          echo '<div class="warning-box">Errors encountered while preparing data:<ul><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
        
        $time = time();;
        
        // Get author
        $author = $session->username;
        
        if ( empty($author) )
          $author = 'Anonymous';
        
        // Set date & time
        $month  = date('n', $time);
        $year   = date('Y', $time);
        $day    = date('j', $time);
        $hour   = date('G', $time);
        $minute = date('m', $time);
        $second = date('s', $time);
        
        echo '<form id="nb_create_form" action="'.makeUrlNS('Special', 'Administration', (( isset($_GET['sqldbg'])) ? 'sqldbg&amp;' : '') .'module='.$paths->cpage['module'] . '&act=create').'" method="post" onsubmit="if ( !submitAuthorized ) return false;">';
        echo '<div class="tblholder">
                <table border="0" cellspacing="1" cellpadding="4">
                  <tr>
                    <th colspan="2">Creating news article</th>
                  </tr>
                  <tr>
                    <td class="row1">Article name:</td><td class="row2"><input name="article_name" value="" /></td>
                  </tr>
                  <tr>
                    <td class="row1">Published datestamp:</td>
                    <td class="row2">
                      <input name="pub_year" type="text" size="5" value="'.$year.'" />-<select name="pub_month">';
       for ( $i = 1; $i <= 12; $i++ )
       {
         $m = "[$i] ";
         switch ( $i )
         {
           case 1:  $m .= 'January'; break;
           case 2:  $m .= 'February'; break;
           case 3:  $m .= 'March'; break;
           case 4:  $m .= 'April'; break;
           case 5:  $m .= 'May'; break;
           case 6:  $m .= 'June'; break;
           case 7:  $m .= 'July'; break;
           case 8:  $m .= 'August'; break;
           case 9:  $m .= 'September'; break;
           case 10: $m .= 'October'; break;
           case 11: $m .= 'November'; break;
           case 12: $m .= 'December'; break;
           default: $m .= 'Fuhrober'; break;
         }
         if ( $month == $i )
           echo '         <option selected="selected" value="' . $i . '">'.$m.'</option>';
         else
           echo '         <option value="' . $i . '">'.$m.'</option>';
       }
       echo '         </select>
                      <input name="pub_day" type="text" size="3" value="' . $day . '" />, time:
                      <input name="pub_hour" type="text" size="3" value="' . $hour . '" />&nbsp;:&nbsp;<input name="pub_minute" type="text" size="3" value="' . $minute . '" />&nbsp;:&nbsp;<input name="pub_second" type="text" size="3" value="' . $second . '" /><br />
                      <small>Note: Hours are in 24-hour format.</small>
                    </td>
                  </tr>
                  <tr>
                    <td class="row1">Publish article:</td><td class="row2"><label><input name="published" type="checkbox" /> Article is published (shown to the public)</label></td>
                  </tr>
                  <tr>
                    <td class="row1">Article author:</td><td class="row2">' . $template->username_field('author', $author) . '</td></tr>
                  </tr>
                  <tr>
                    <td class="row1">Initial content:<br /><small>You can always edit this later.</small></td><td class="row2"><textarea name="content" rows="15" cols="60" style="width: 100%;"></textarea></td>
                  </tr>
                  <tr>
                    <td class="row3" style="text-align: center;" colspan="2">
                      <a href="#" onclick="var frm = document.getElementById(\'nb_create_form\'); frm.submit(); return false;">Create article</a>&nbsp;&nbsp;<a href="#" onclick="ajaxPage(\'' . $paths->cpage['module'] . '\');">Return to main menu</a>
                    </td>
                  </tr>
                </table>
              </div>
              <input type="hidden" name="submitting" value="yes" />';
        echo '</form>';
        
        $done = true;
        break;
    }
  }
  
  if ( !$done )
  {
  
    // Start output
    echo '<div class="tblholder">
      <table border="0" cellspacing="1" cellpadding="4">
        <tr>
          <th>Name</th>
          <th>Date published</th>
          <th colspan="3">Actions</th>
        </tr>';
        
    $row_class = 'row2';
    
    // List existing news entries
    $q = $db->sql_query('SELECT name,urlname FROM '.table_prefix.'pages WHERE namespace="NewsBoy" AND urlname!="Announce" ORDER BY name ASC;');
    
    if ( !$q )
      $db->_die();
    
    if ( $row = $db->fetchrow($q) )
    {
      do {
        $row_class = ( $row_class == 'row1' ) ? 'row2' : 'row1';
        $ts = intval($row['urlname']);
        $date = date('F d, Y h:i a', $ts);
        $edit_url = makeUrlNS('Special', 'Administration', "module={$paths->cpage['module']}&act=edit&id={$row['urlname']}", true);
        $dele_url = makeUrlNS('Special', 'Administration', "module={$paths->cpage['module']}&act=del&id={$row['urlname']}", true);
        $page_url = makeUrlNS('NewsBoy', $row['urlname']);
        echo "<tr>
                <td class='$row_class' style='width: 50%;'>
                  {$row['name']}
                </td>
                <td class='$row_class' style='width: 40%;'>
                  $date
                </td>
                <td class='$row_class'>
                  <a href='$edit_url'>Settings</a>
                </td>
                <td class='$row_class'>
                  <a href='$page_url' onclick='window.open(this.href); return false;'>Page</a>
                </td>
                <td class='$row_class'>
                  <a href='$dele_url'>Delete</a>
                </td>
              </tr>";
      } while ( $row = $db->fetchrow($q) );
    }
    else
    {
      echo '<tr><td class="row3" colspan="5" style="text-align: center;">No news items yet.</td></tr>';
    }
    echo '<tr><th class="subhead" colspan="5"><a href="' . makeUrlNS('Special', 'Administration', "module={$paths->cpage['module']}&act=create", true) . '" style="color: inherit;">Create new entry</a></th></tr>
          </table></div>';
    $db->free_result();
    
  }
  
}

function page_Admin_NewsboyConfiguration()
{
  global $db, $session, $paths, $template, $plugins; if($session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN) { redirect(makeUrlNS('Special', 'Administration', 'noheaders', true), '', '', 0); die('Hacking attempt'); }
  if ( isset($_POST['submit']) )
  {
    setConfig('nb_portal_title', $_POST['portal_name']);
    if ( isPage($_POST['announce_page']) )
      setConfig('nb_announce_page', $_POST['announce_page']);
    else
      setConfig('nb_announce_page', '');
    // Submit
    echo '<div class="info-box">Your changes have been saved.</div>';
  }
  echo '<form name="main" action="'.htmlspecialchars(makeUrl($paths->nslist['Special'].'Administration', 'module='.$paths->cpage['module'])).'" method="post">';
  echo '<div class="tblholder">
          <table border="0" cellspacing="1" cellpadding="4">
            <tr>
              <th colspan="2">
                Newsboy portal: General configuration
              </th>
            </tr>
            <tr>
              <td class="row2">
                Portal title:<br />
                <small>This is the text that will be shown as the page title on the<br />
                portal. If you don\'t enter anything here, a default will be used.</small>
              </td>
              <td class="row1"><input type="text" size="30" name="portal_name" value="' . htmlspecialchars(getConfig('nb_portal_title')) . '"></td>
            </tr>
            <tr>
              <td class="row2">
                Page to embed as announcement:<br />
                <small>The page you enter here will always be shown at the top of the<br />
                portal. The default is "' . $paths->nslist['NewsBoy'] . 'Announce".</small>
              </td>
              <td class="row1">
                ' . $template->pagename_field('announce_page', htmlspecialchars(getConfig('nb_announce_page'))) . '
              </td>
            </tr>
            <tr>
              <th class="subhead" colspan="2">
                <input type="submit" name="submit" value="Save changes" />
              </th>
            </tr>
          </table>
        </div>';
  echo '</form>';
}

/**
 * Trims a wad of text to the specified length.
 * @todo make HTML friendly (don't break tags)
 * @param string The text to trim
 * @param int The maximum length to trim the text to.
 * @param bool Reference. Set to true if the text was trimmed, otherwise set to false.
 */

function nb_trim_paragraph($text, $len = 500, &$trimmed = false)
{
  $trimmed = false;
  if ( strlen($text) <= $len )
    return $text;
  $trimmed = true;
  $text = substr($text, 0, $len);
  for ( $i = $len; $i > 0; $i-- )
  {
    $chr = $text{$i-1};
    if ( preg_match('/[\s]/', $chr) )
    {
      $text = substr($text, 0, $i - 1);
      $text .= '...';
      return $text;
    }
    $text = substr($text, 0, $i);
  }
  return $text;
}

?>
