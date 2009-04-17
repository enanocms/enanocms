<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * The default handler for namespaces. Basically fetches the page text from the database. Other namespaces should extend this class.
 * @package Enano
 * @subpackage PageHandler
 * @author Dan Fuhry <dan@enanocms.org>
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl-2.0.html>
 */

class Namespace_Default
{
  /**
   * Page ID
   * @var string
   */
  
  public $page_id;
  
  /**
   * Namespace
   * @var string
   */
  
  public $namespace;
  
  /**
   * Local copy of the page text
   */
  
  public $text_cache;
  
  /**
   * Revision ID to send. If 0, the latest revision.
   * @var int
   */
  
  public $revision_id = 0;
  
  /**
   * Tracks whether the page exists
   * @var bool
   */
  
  public $exists = false;
  
  /**
   * Page title
   * @var string
   */
  
  public $title = '';
  
  /**
   * Constructor.
   */
  
  public function __construct($page_id, $namespace, $revision_id = 0)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->page_id = sanitize_page_id($page_id);
    $this->namespace = $namespace;
    $this->revision_id = intval($revision_id);
    
    // only do this if calling from the (very heavily feature filled) abstract
    // this will still be called if you're using your own handler but not replacing the constructor
    if ( __CLASS__ == 'Namespace_Default' )
    {
      $this->exists = false;
      // NOTE! These should already be WELL sanitized before we reach this stage.
      $q = $db->sql_query('SELECT name FROM ' . table_prefix . "pages WHERE urlname = '$this->page_id' AND namespace = '$this->namespace';");
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
      {
        // we still have a chance... some older databases don't do dots in the page title right
        if ( strstr(dirtify_page_id($this->page_id), '.') )
        {
          $page_id = str_replace('.', '.2e', $page_id);
          
          $q = $db->sql_query('SELECT name FROM ' . table_prefix . "pages WHERE urlname = '$page_id' AND namespace = '$this->namespace';");
          if ( !$q )
            $db->_die();
          
          if ( $db->numrows() < 1 )
          {
            $this->title = $paths->nslist[$namespace] . dirtify_page_id($page_id);
          }
          else
          {
            list($this->title) = $db->fetchrow_num();
            $this->exists = true;
            $this->page_id = $page_id;
          }
        }
        else
        {
          $this->title = $paths->nslist[$namespace] . dirtify_page_id($page_id);
        }
      }
      else
      {
        list($this->title) = $db->fetchrow_num();
        $this->exists = true;
      }
      $db->free_result();
    }
  }
  
  /**
   * Pulls the page's actual text from the database.
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
   * Send the page.
   */
  
  public function send()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $output;
    
    $output->add_before_footer($this->display_categories());
    
    if ( $this->exists )
      $this->send_from_db();
    else
    {
      // This is the DEPRECATED way to extend namespaces. It's left in only for compatibility with older plugins.
      ob_start();
      $code = $plugins->setHook('page_not_found');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
      $c = ob_get_contents();
      if ( !empty($c) )
      {
        ob_end_clean();
        echo $c;
      }
      else
      {
        $output->header();
        $this->error_404();
        $output->footer();
      }
    }
  }
   
  /**
   * The "real" send-the-page function. The reason for this is so other namespaces can re-use the code
   * to fetch the page from the DB while being able to install their own wrappers.
   */
  
  public function send_from_db($incl_inner_headers = true, $send_headers = true)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    global $output;
    
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
    
    if ( $send_headers )
    {
      $template->init_vars($this);
      $output->set_title($this->title);
      $output->header();
    }
    $this->do_breadcrumbs();
    
    if ( $incl_inner_headers )
    {
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
      $q = $db->sql_query('SELECT page_format FROM ' . table_prefix . "logs WHERE log_id = {$this->revision_id};");
      if ( !$q )
        $db->_die();
      
      list($page_format) = $db->fetchrow_num();
      $db->free_result();
    }
    else
    {
      $pathskey = $paths->nslist[ $this->namespace ] . $this->page_id;
      $page_format = $paths->pages[$pathskey]['page_format'];
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
      if ( $page_format === 'wikitext' )
      {
        $text = '?>' . RenderMan::render($text);
      }
      else
      {
        // Page format is XHTML. This means we want to disable functionality that MCE takes care of, while still retaining
        // the ability to wikilink, the ability to use images, etc. Basically, RENDER_INLINEONLY disables all behavior in
        // the rendering engine/Text_Wiki that conflicts with MCE.
        $text = '?>' . RenderMan::render($text, RENDER_WIKI_DEFAULT | RENDER_INLINEONLY);
      }
    }
    else
    {
      $text = '?>' . $text;
      $text = preg_replace('/<nowiki>(.*?)<\/nowiki>/s', '\\1', $text);
    }
    
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
    
    if ( $send_headers )
      $output->footer();
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
        if ( $pathskey !== get_main_page() )
          echo '<a href="' . makeUrl(get_main_page(), false, true) . '">';
        echo $lang->get('onpage_btn_breadcrumbs_home');
        if ( $pathskey !== get_main_page() )
          echo '</a>';
      }
      foreach ( $breadcrumb_data as $i => $crumb )
      {
        $cumulative = implode('/', array_slice($breadcrumb_data, 0, ( $i + 1 )));
        if ( $show_home && $cumulative === get_main_page() )
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
  
  public function error_404($userpage = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang, $output;
    
    @header('HTTP/1.1 404 Not Found');
    
    $msg = ( $pp = $paths->sysmsg('Page_not_found') ) ? $pp : '{STANDARD404}';
    
    $standard_404 = '';
    
    if ( $userpage )
    {
      $standard_404 .= '<h3>' . $lang->get('page_msg_404_title_userpage') . '</h3>
             <p>' . $lang->get('page_msg_404_body_userpage');
    }
    else
    {
      $standard_404 .= '<h3>' . $lang->get('page_msg_404_title') . '</h3>
             <p>' . $lang->get('page_msg_404_body');
    }
    if ( $session->get_permissions('create_page') )
    {
      $standard_404 .= ' ' . $lang->get('page_msg_404_create', array(
          'create_flags' => 'href="'.makeUrlNS($this->namespace, $this->page_id, 'do=edit', true).'" onclick="ajaxEditor(); return false;"',
          'mainpage_link' => makeUrl(get_main_page(), false, true)
        ));
    }
    else
    {
      $standard_404 .= ' ' . $lang->get('page_msg_404_gohome', array(
          'mainpage_link' => makeUrl(get_main_page(), false, true)
        ));
    }
    $standard_404 .= '</p>';
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
        $standard_404 .= '<p>' . $lang->get('page_msg_404_was_deleted', array(
                  'delete_time' => enano_date('d M Y h:i a', $r['time_id']),
                  'delete_reason' => htmlspecialchars($r['edit_summary']),
                  'rollback_flags' => 'href="'.makeUrl($paths->page, 'do=rollback&amp;id='.$r['log_id']).'" onclick="ajaxRollback(\''.$r['log_id'].'\'); return false;"'
                ))
              . '</p>';
        if ( $session->user_level >= USER_LEVEL_ADMIN )
        {
          $standard_404 .= '<p>' . $lang->get('page_msg_404_admin_opts', array(
                    'detag_link' => makeUrl($paths->page, 'do=detag', true)
                  ))
                . '</p>';
        }
      }
      $db->free_result();
    }
    $standard_404 .= '<p>
            ' . $lang->get('page_msg_404_http_response') . '
          </p>';
          
    $parser = $template->makeParserText($msg);
    $parser->assign_vars(array(
        'STANDARD404' => $standard_404
      ));
    
    $msg = RenderMan::render($parser->run());
    eval( '?>' . $msg );
  }
  
  /**
   * Display the categories a page is in. If the current page is a category, its contents will also be printed.
   */
  
  function display_categories()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    $html = '';
    
    if ( $this->namespace == 'Category' )
    {
      // Show member pages and subcategories
      $q = $db->sql_query('SELECT p.urlname, p.namespace, p.name, p.namespace=\'Category\' AS is_category FROM '.table_prefix.'categories AS c
                             LEFT JOIN '.table_prefix.'pages AS p
                               ON ( p.urlname = c.page_id AND p.namespace = c.namespace )
                             WHERE c.category_id=\'' . $db->escape($this->page_id) . '\'
                             ORDER BY is_category DESC, p.name ASC;');
      if ( !$q )
      {
        $db->_die();
      }
      $html .= '<h3>' . $lang->get('onpage_cat_heading_subcategories') . '</h3>';
      $html .= '<div class="tblholder">';
      $html .= '<table border="0" cellspacing="1" cellpadding="4">';
      $html .= '<tr>';
      $ticker = 0;
      $counter = 0;
      $switched = false;
      $class  = 'row1';
      while ( $row = $db->fetchrow() )
      {
        if ( $row['is_category'] == 0 && !$switched )
        {
          if ( $counter > 0 )
          {
            // Fill-in
            while ( $ticker < 3 )
            {
              $ticker++;
              $html .= '<td class="' . $class . '" style="width: 33.3%;"></td>';
            }
          }
          else
          {
            $html .= '<td class="' . $class . '">' . $lang->get('onpage_cat_msg_no_subcategories') . '</td>';
          }
          $html .= '</tr></table></div>' . "\n\n";
          $html .= '<h3>' . $lang->get('onpage_cat_heading_pages') . '</h3>';
          $html .= '<div class="tblholder">';
          $html .= '<table border="0" cellspacing="1" cellpadding="4">';
          $html .= '<tr>';
          $counter = 0;
          $ticker = -1;
          $switched = true;
        }
        $counter++;
        $ticker++;
        if ( $ticker == 3 )
        {
          $html .= '</tr><tr>';
          $ticker = 0;
          $class = ( $class == 'row3' ) ? 'row1' : 'row3';
        }
        $html .= "<td class=\"{$class}\" style=\"width: 33.3%;\">"; // " to workaround stupid jEdit bug
        
        $link = makeUrlNS($row['namespace'], sanitize_page_id($row['urlname']));
        $html .= '<a href="' . $link . '"';
        $key = $paths->nslist[$row['namespace']] . sanitize_page_id($row['urlname']);
        if ( !isPage( $key ) )
        {
          $html .= ' class="wikilink-nonexistent"';
        }
        $html .= '>';
        $title = get_page_title_ns($row['urlname'], $row['namespace']);
        $html .= htmlspecialchars($title);
        $html .= '</a>';
        
        $html .= "</td>";
      }
      if ( !$switched )
      {
        if ( $counter > 0 )
        {
          // Fill-in
          while ( $ticker < 2 )
          {
            $ticker++;
            $html .= '<td class="' . $class . '" style="width: 33.3%;"></td>';
          }
        }
        else
        {
          $html .= '<td class="' . $class . '">' . $lang->get('onpage_cat_msg_no_subcategories') . '</td>';
        }
        $html .= '</tr></table></div>' . "\n\n";
        $html .= '<h3>' . $lang->get('onpage_cat_heading_pages') . '</h3>';
        $html .= '<div class="tblholder">';
        $html .= '<table border="0" cellspacing="1" cellpadding="4">';
        $html .= '<tr>';
        $counter = 0;
        $ticker = 0;
        $switched = true;
      }
      if ( $counter > 0 )
      {
        // Fill-in
        while ( $ticker < 2 )
        {
          $ticker++;
          $html .= '<td class="' . $class . '" style="width: 33.3%;"></td>';
        }
      }
      else
      {
        $html .= '<td class="' . $class . '">' . $lang->get('onpage_cat_msg_no_pages') . '</td>';
      }
      $html .= '</tr></table></div>' . "\n\n";
    }
    
    if ( $this->namespace != 'Special' && $this->namespace != 'Admin' )
    {
      $html .= '<div class="mdg-comment" style="margin: 10px 0 0 0;" id="category_box_wrapper">';
      $html .= '<div style="float: right;">';
      $html .= '(<a href="#" onclick="ajaxCatToTag(); return false;">' . $lang->get('tags_catbox_link') . '</a>)';
      $html .= '</div>';
      $html .= '<div id="mdgCatBox">' . $lang->get('catedit_catbox_lbl_categories') . ' ';
      
      $where = '( c.page_id=\'' . $db->escape($this->page_id) . '\' AND c.namespace=\'' . $db->escape($this->namespace) . '\' )';
      $prefix = table_prefix;
      $sql = <<<EOF
SELECT c.category_id FROM {$prefix}categories AS c
  LEFT JOIN {$prefix}pages AS p
    ON ( ( p.urlname = c.page_id AND p.namespace = c.namespace ) OR ( p.urlname IS NULL AND p.namespace IS NULL ) )
  WHERE $where
  ORDER BY p.name ASC, c.page_id ASC;
EOF;
      $q = $db->sql_query($sql);
      if ( !$q )
        $db->_die();
      
      if ( $row = $db->fetchrow() )
      {
        $list = array();
        do
        {
          $cid = sanitize_page_id($row['category_id']);
          $title = get_page_title_ns($cid, 'Category');
          $link = makeUrlNS('Category', $cid);
          $list[] = '<a href="' . $link . '">' . htmlspecialchars($title) . '</a>';
        }
        while ( $row = $db->fetchrow() );
        $html .= implode(', ', $list);
      }
      else
      {
        $html .= $lang->get('catedit_catbox_lbl_uncategorized');
      }
      
      $can_edit = ( $session->get_permissions('edit_cat') && ( !$paths->page_protected || $session->get_permissions('even_when_protected') ) );
      if ( $can_edit )
      {
        $edit_link = '<a href="' . makeUrl($paths->page, 'do=catedit', true) . '" onclick="ajaxCatEdit(); return false;">' . $lang->get('catedit_catbox_link_edit') . '</a>';
        $html .= ' [ ' . $edit_link . ' ]';
      }
      
      $html .= '</div></div>';
    }
    return $html;
  }
  /**
   * Just tell us if the current page exists or not.
   * @return bool
   */
   
  function exists()
  {
    return $this->exists;
  }
}

/**
 * The namespaces that use the default handler.
 */

class Namespace_Article extends Namespace_Default
{
}

class Namespace_Project extends Namespace_Default
{
}

class Namespace_Help extends Namespace_Default
{
}

