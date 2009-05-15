<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * log.php - Logs table parsing and displaying
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Front-end for showing page revisions and actions in the logs table.
 * @package Enano
 * @subpackage Frontend
 * @author Dan Fuhry <dan@enanocms.org>
 * @license GNU General Public License
 */

class LogDisplay
{
  /**
   * Criteria for the search.
   * Structure:
   <code>
   array(
       array( 'user', 'Dan' ),
       array( 'within', 86400 ),
       array( 'page', 'Main_Page' )
     )
   </code>
   * @var array
   */
  
  var $criteria = array();
  
  /**
   * Adds a criterion for the log display.
   * @param string Criterion type - user, page, or within
   * @param string Value - username, page ID, or (int) within # seconds or (string) number + suffix (suffix: d = day, w = week, m = month, y = year) ex: "1w"
   */
  
  public function add_criterion($criterion, $value)
  {
    switch ( $criterion )
    {
      case 'user':
      case 'page':
      case 'action':
        $this->criteria[] = array($criterion, $value);
        break;
      case 'minor':
        $this->criteria[] = array($criterion, intval($value));
        break;
      case 'within':
        if ( is_int($value) )
        {
          $this->criteria[] = array($criterion, $value);
        }
        else if ( is_string($value) )
        {
          $lastchar = substr($value, -1);
          $amt = intval($value);
          switch($lastchar)
          {
            case 'd':
              $amt = $amt * 86400;
              break;
            case 'w':
              $amt = $amt * 604800;
              break;
            case 'm':
              $amt = $amt * 2592000;
              break;
            case 'y':
              $amt = $amt * 31536000;
              break;
          }
          $this->criteria[] = array($criterion, $amt);
        }
        else
        {
          throw new Exception('Invalid value type for within');
        }
        break;
      default:
        throw new Exception('Unknown criterion type');
        break;
    }
  }
  
  /**
   * Build the necessary SQL query.
   * @param int Optional: offset, defaults to 0
   * @param int Optional: page size, defaults to 0; 0 = don't limit
   */
  
  public function build_sql($offset = 0, $page_size = 0, $just_page_count = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $where_extra = '';
    $where_bits = array(
        'user' => array(),
        'page' => array(),
        'action' => array()
      );
    foreach ( $this->criteria as $criterion )
    {
      list($type, $value) = $criterion;
      switch($type)
      {
        case 'user':
          $where_bits['user'][] = "author = '" . $db->escape(str_replace('_', ' ', $value)) . "'";
          break;
        case 'action':
          if ( $value === 'protect' )
          {
            $where_bits['action'][] = "action = 'prot'";
            $where_bits['action'][] = "action = 'unprot'";
            $where_bits['action'][] = "action = 'semiprot'";
          }
          else
          {
            $where_bits['action'][] = "action = '" . $db->escape($value) . "'";
          }
          break;
        case 'page':
          list($page_id, $namespace) = RenderMan::strToPageId($value);
          $where_bits['page'][] = "page_id = '" . $db->escape($page_id) . "' AND namespace = '" . $db->escape($namespace) . "'";
          break;
        case 'within':
          $threshold = time() - $value;
          $where_extra .= "\n    AND time_id > $threshold";
          break;
        case 'minor':
          if ( $value == 1 )
            $where_extra .= "\n    AND ( minor_edit = 1 OR action != 'edit' )";
          else
            $where_extra .= "\n    AND minor_edit != 1";
          break;
      }
    }
    if ( !empty($where_bits['user']) )
    {
      $where_extra .= "\n    AND ( " . implode(" OR ", $where_bits['user']) . " )";
    }
    if ( !empty($where_bits['page']) )
    {
      $where_extra .= "\n    AND ( (" . implode(") OR (", $where_bits['page']) . ") )";
    }
    if ( !empty($where_bits['action']) )
    {
      $where_extra .= "\n    AND ( (" . implode(") OR (", $where_bits['action']) . ") )";
    }
    if ( ENANO_DBLAYER == 'PGSQL' )
      $limit = ( $page_size > 0 ) ? "\n  LIMIT $page_size OFFSET $offset" : '';
    else
      $limit = ( $page_size > 0 ) ? "\n  LIMIT $offset, $page_size" : '';
    $columns = ( $just_page_count ) ? 'COUNT(*)' : 'log_id, action, page_id, namespace, CHAR_LENGTH(page_text) AS revision_size, author, time_id, edit_summary, minor_edit';
    $sql = 'SELECT ' . $columns . ' FROM ' . table_prefix . "logs AS l\n"
         . "  WHERE log_type = 'page' AND is_draft != 1$where_extra\n"
         . ( $just_page_count ? '' : "  GROUP BY log_id, action, page_id, namespace, page_text, author, time_id, edit_summary, minor_edit\n" )
         . "  ORDER BY time_id DESC $limit;";
    
    return $sql;
  }
  
  /**
   * Get data!
   * @param int Offset, defaults to 0
   * @param int Page size, if 0 (default) returns entire table (danger Will Robinson!)
   * @return array
   */
  
  public function get_data($offset = 0, $page_size = 0)
  {
    global $db, $session, $paths, $session, $plugins; // Common objects
    $sql = $this->build_sql($offset, $page_size);
    if ( !$db->sql_query($sql) )
      $db->_die();
    
    $return = array();
    $deplist = array();
    $idlist = array();
    while ( $row = $db->fetchrow() )
    {
      $return[ $row['log_id'] ] = $row;
      if ( $row['action'] === 'edit' )
      {
        // This is a page revision; its parent needs to be found
        $pagekey = serialize(array($row['page_id'], $row['namespace']));
        $deplist[$pagekey] = "( page_id = '" . $db->escape($row['page_id']) . "' AND namespace = '" . $db->escape($row['namespace']) . "' AND log_id < {$row['log_id']} )";
        // if we already have a revision from this page in the dataset, we've found its parent
        if ( isset($idlist[$pagekey]) )
        {
          $child =& $return[ $idlist[$pagekey] ];
          $child['parent_size'] = $row['revision_size'];
          $child['parent_revid'] = $row['log_id'];
          $child['parent_time'] = $row['time_id'];
          unset($child);
        }
        $idlist[$pagekey] = $row['log_id'];
      }
    }
    
    // Second query fetches all parent revision data
    // (maybe we have no edits?? check deplist)
    
    if ( !empty($deplist) )
    {
      // FIXME: inefficient. damn GROUP BY for not obeying ORDER BY, it means we can't group and instead have to select
      // all previous revisions of page X and discard all but the first one we find.
      $where_extra = implode("\n    OR ", $deplist);
      $sql = 'SELECT log_id, page_id, namespace, CHAR_LENGTH(page_text) AS revision_size, time_id FROM ' . table_prefix . "logs\n"
           . "  WHERE log_type = 'page' AND action = 'edit'\n  AND ( $where_extra )\n"
           // . "  GROUP BY page_id, namespace\n"
           . "  ORDER BY log_id DESC;";
      if ( !$db->sql_query($sql) )
        $db->_die();
      
      while ( $row = $db->fetchrow() )
      {
        $pagekey = serialize(array($row['page_id'], $row['namespace']));
        if ( isset($idlist[$pagekey]) )
        {
          $child =& $return[ $idlist[$pagekey] ];
          $child['parent_size'] = $row['revision_size'];
          $child['parent_revid'] = $row['log_id'];
          $child['parent_time'] = $row['time_id'];
          unset($child, $idlist[$pagekey]);
        }
      }
    }
    
    // final iteration goes through all edits and if there's not info on the parent, sets to 0. It also calculates size change.
    foreach ( $return as &$row )
    {
      if ( $row['action'] === 'edit' && !isset($row['parent_revid']) )
      {
        $row['parent_revid'] = 0;
        $row['parent_size'] = 0;
      }
      if ( $row['action'] === 'edit' )
      {
        $row['size_delta'] = $row['revision_size'] - $row['parent_size'];
      }
    }
    
    return array_values($return);
  }
  
  /**
   * Get the number of rows that will be in the result set.
   * @return int
   */
  
  public function get_row_count()
  {
    global $db, $session, $paths, $session, $plugins; // Common objects
    
    if ( !$db->sql_query( $this->build_sql(0, 0, true) ) )
      $db->_die();
    
    list($count) = $db->fetchrow_num();
    return $count;
  }
  
  /**
   * Returns the list of criteria
   * @return array
   */
  
  public function get_criteria()
  {
    return $this->criteria;
  }
  
  /**
   * Formats a result row into pretty HTML.
   * @param array dataset from LogDisplay::get_data()
   * @param bool If true (default), shows action buttons.
   * @param bool If true (default), shows page title; good for integrated displays
   * @static
   * @return string
   */
  
  public static function render_row($row, $show_buttons = true, $show_pagetitle = true)
  {
    global $db, $session, $paths, $session, $plugins; // Common objects
    global $lang;
    
    $html = '';
    
    $pagekey = ( isset($paths->nslist[$row['namespace']]) ) ? $paths->nslist[$row['namespace']] . $row['page_id'] : $row['namespace'] . ':' . $row['page_id'];
    $pagekey = sanitize_page_id($pagekey);
    
    // diff button
    if ( $show_buttons )
    {
      if ( $row['action'] == 'edit' && !empty($row['parent_revid']) )
      {
        $html .= '(';
        $ispage = isPage($pagekey);
        
        if ( $ispage )
          $html .= '<a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=diff&diff1={$row['parent_revid']}&diff2={$row['log_id']}", true) . '">';
        
        $html .= $lang->get('pagetools_rc_btn_diff');
        
        if ( $ispage )
          $html .= '</a>';
        
        if ( $ispage )
          $html .= ', <a href="' . makeUrlNS($row['namespace'], $row['page_id'], "oldid={$row['log_id']}", true) . '">';
        
        $html .= $lang->get('pagetools_rc_btn_view');
        
        if ( $ispage )
          $html .= '</a>';
        
        if ( $row['parent_revid'] > 0 && isPage($pagekey) )
        {
          $html .= ', <a href="' . makeUrlNS($row['namespace'], $row['page_id'], false, true) . '#do:edit;rev:' . $row['parent_revid'] . '">' . $lang->get('pagetools_rc_btn_undo') . '</a>';
        }
        $html .= ') ';
      }
      else if ( $row['action'] != 'edit' && ( isPage($pagekey) || $row['action'] == 'delete' ) )
      {
        $html .= '(';
        $html .= '<a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=rollback&id={$row['log_id']}", true). '">' . $lang->get('pagetools_rc_btn_undo') . '</a>';
        $html .= ') ';
      }
      
      // hist button
      $html .= '(';
      if ( isPage($pagekey) )
      {
        $html .= '<a href="' . makeUrlNS($row['namespace'], $row['page_id'], "do=history", true) . '">';
      }
      $html .= $lang->get('pagetools_rc_btn_hist');
      if ( isPage($pagekey) )
      {
        $html .= '</a>';
      }
      $html .= ') . . ';
    }
    
    if ( $show_pagetitle )
    {
      // new page?
      if ( $row['action'] == 'edit' && empty($row['parent_revid']) )
      {
        $html .= '<b>N</b> ';
      }
      // minor edit?
      if ( $row['action'] == 'edit' && $row['minor_edit'] )
      {
        $html .= '<b>m</b> ';
      }
      
      // link to the page
      $cls = ( isPage($pagekey) ) ? '' : ' class="wikilink-nonexistent"';
      $html .= '<a href="' . makeUrlNS($row['namespace'], $row['page_id']) . '"' . $cls . '>' . htmlspecialchars(get_page_title_ns($row['page_id'], $row['namespace'])) . '</a>; ';
    }
    
    // date
    $today = time() - ( time() % 86400 );
    $date = MemberlistFormatter::format_date($row['time_id']) . ' ';
    $date .= date('h:i:s', $row['time_id']);
    $html .= "$date . . ";
    
    // size counter
    if ( $row['action'] == 'edit' )
    {
      $css = self::get_css($row['size_delta']);
      $size_change = number_format($row['size_delta']);
      if ( substr($size_change, 0, 1) != '-' )
        $size_change = "+$size_change";
      
      $html .= "<span style=\"$css\">({$size_change})</span>";
      $html .= ' . . ';
    }
    
    // link to userpage
    $cls = ( isPage($paths->nslist['User'] . $row['author']) ) ? '' : ' class="wikilink-nonexistent"';
    $rank_info = $session->get_user_rank($row['author']);
    $html .= '<a style="' . $rank_info['rank_style'] . '" href="' . makeUrlNS('User', sanitize_page_id($row['author']), false, true) . '"' . $cls . '>' . htmlspecialchars($row['author']) . '</a> ';
    $html .= '(';
    $html .= '<a href="' . makeUrlNS('Special', 'PrivateMessages/Compose/To/' . sanitize_page_id($row['author']), false, true) . '">';
    $html .= $lang->get('pagetools_rc_btn_pm');
    $html .= '</a>, ';
    $html .= '<a href="' . makeUrlNS('User', sanitize_page_id($row['author']), false, true) . '#do:comments">';
    $html .= $lang->get('pagetools_rc_btn_usertalk');
    $html .= '</a>';
    $html .= ') . . ';
    
    // Edit summary
    
    if ( $row['action'] == 'edit' )
    {
      $html .= '<i>(';
      if ( empty($row['edit_summary']) )
      {
        $html .= '<span style="color: #808080;">' . $lang->get('history_summary_none_given') . '</span>';
      }
      else
      {
        $html .= RenderMan::parse_internal_links(htmlspecialchars($row['edit_summary']));
      }
      $html .= ')</i>';
    }
    else
    {
      switch($row['action'])
      {
        default:
          $html .= $row['action'];
          break;
        case 'rename':
          $html .= $lang->get('log_action_rename', array('old_name' => htmlspecialchars($row['edit_summary'])));
          break;
        case 'create':
          $html .= $lang->get('log_action_create');
          break;
        case 'votereset':
          $html .= $lang->get('log_action_votereset', array('num_votes' => $row['edit_summary'], 'plural' => ( intval($row['edit_summary']) == 1 ? '' : $lang->get('meta_plural'))));
          break;
        case 'prot':
        case 'unprot':
        case 'semiprot':
        case 'delete':
        case 'reupload':
          $stringmap = array(
            'prot' => 'log_action_protect_full',
            'unprot' => 'log_action_protect_none',
            'semiprot' => 'log_action_protect_semi',
            'delete' => 'log_action_delete',
            'reupload' => 'log_action_reupload'
          );
        
        if ( $row['edit_summary'] === '__REVERSION__' )
          $reason = '<span style="color: #808080;">' . $lang->get('log_msg_reversion') . '</span>';
        else if ( $row['action'] == 'reupload' && $row['edit_summary'] === '__ROLLBACK__' )
          $reason = '<span style="color: #808080;">' . $lang->get('log_msg_file_restored') . '</span>';
        else
          $reason = ( !empty($row['edit_summary']) ) ? htmlspecialchars($row['edit_summary']) : '<span style="color: #808080;">' . $lang->get('log_msg_no_reason_provided') . '</span>';
        
        $html .= $lang->get($stringmap[$row['action']], array('reason' => $reason));
      }
    }
    
    return $html;
  }
  
  /**
   * Return CSS blurb for size delta
   * @return string
   * @static
   * @access private
   */
  
  private static function get_css($change_size)
  {
    // Hardly changed at all? Return a gray
    if ( $change_size <= 5 && $change_size >= -5 )
      return 'color: #808080;';
    // determine saturation based on size of change (1-500 bytes)
    $change_abs = abs($change_size);
    $index = 0x70 * ( $change_abs / 500 );
    if ( $index > 0x70 )
      $index = 0x70;
    $index = $index + 0x40;
    $index = dechex($index);
    if ( strlen($index) < 2 )
      $index = "0$index";
    $css = ( $change_size > 0 ) ? "color: #00{$index}00;" : "color: #{$index}0000;";
    if ( $change_abs > 500 )
      $css .= ' font-weight: bold;';
    return $css;
  }
}
 
?>
