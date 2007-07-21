<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Class that handles comments. Has HTML/Javascript frontend support.
 * @package Enano CMS
 * @subpackage Comment manager
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl.html>
 */

class Comments
{
  #
  # VARIABLES
  #
  
  /**
   * Current list of comments.
   * @var array
   */
  
  var $comments = Array();
  
  /**
   * Object to track permissions.
   * @var object
   */
  
  var $perms;
  
  #
  # METHODS
  #
  
  /**
   * Constructor.
   * @param string Page ID of the page to load comments for
   * @param string Namespace of the page to load comments for
   */
  
  function __construct($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    // Initialize permissions
    if ( $page_id == $paths->cpage['urlname_nons'] && $namespace == $paths->namespace )
      $this->perms =& $GLOBALS['session'];
    else
      $this->perms = $session->fetch_page_acl($page_id, $namespace);
    
    $this->page_id = $db->escape($page_id);
    $this->namespace = $db->escape($namespace);
  }
  
  /**
   * PHP 4 constructor.
   * @see Comments::__construct
   */
  function Comments($page_id, $namespace)
  {
    $this->__construct($page_id, $namespace);
  }
  
  /**
   * Processes a command in JSON format.
   * @param string The JSON-encoded input, probably something sent from the Javascript/AJAX frontend
   */
   
  function process_json($json)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $parser = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $data = $parser->decode($json);
    if ( !isset($data['mode']) )
    {
      return $parser->encode(Array('mode'=>'error','error'=>'No mode defined!'));
    }
    $ret = Array();
    $ret['mode'] = $data['mode'];
    switch ( $data['mode'] )
    {
      case 'fetch':
        if ( !$template->theme_loaded )
          $template->load_theme();
        if ( !isset($data['have_template']) )
        {
          $ret['template'] = file_get_contents(ENANO_ROOT . '/themes/' . $template->theme . '/comment.tpl');
        }
        $q = $db->sql_query('SELECT c.comment_id,c.name,c.subject,c.comment_data,c.time,c.approved,u.user_level,u.user_id,u.signature FROM '.table_prefix.'comments AS c
                               LEFT JOIN '.table_prefix.'users AS u
                                 ON (u.user_id=c.user_id)
                               WHERE page_id=\'' . $this->page_id . '\'
                                 AND namespace=\'' . $this->namespace . '\';');
        $count_appr = 0;
        $count_total = 0;
        $count_unappr = 0;
        $ret['comments'] = Array();
        if (!$q)
          $db->die_json();
        if ( $row = $db->fetchrow() )
        {
          do {
            
            // Increment counters
            $count_total++;
            ( $row['approved'] == 1 ) ? $count_appr++ : $count_unappr++;
            
            if ( !$this->perms->get_permissions('mod_comments') && $row['approved'] == 0 )
              continue;
            
            // Send the source
            $row['comment_source'] = $row['comment_data'];
            
            // Format text
            $row['comment_data'] = RenderMan::render($row['comment_data']);
            
            // Format date
            $row['time'] = date('F d, Y h:i a', $row['time']);
            
            // Format signature
            $row['signature'] = ( !empty($row['signature']) ) ? RenderMan::render($row['signature']) : '';
            
            // Add the comment to the list
            $ret['comments'][] = $row;
            
          } while ( $row = $db->fetchrow() );
        }
        $db->free_result();
        $ret['count_appr'] = $count_appr;
        $ret['count_total'] = $count_total;
        $ret['count_unappr'] = $count_unappr;
        $ret['auth_mod_comments'] = $this->perms->get_permissions('mod_comments');
        $ret['auth_post_comments'] = $this->perms->get_permissions('post_comments');
        $ret['auth_edit_comments'] = $this->perms->get_permissions('edit_comments');
        $ret['user_id'] = $session->user_id;
        $ret['username'] = $session->username;
        $ret['logged_in'] = $session->user_logged_in;
        
        $ret['user_level'] = Array();
        $ret['user_level']['guest'] = USER_LEVEL_GUEST;
        $ret['user_level']['member'] = USER_LEVEL_MEMBER;
        $ret['user_level']['mod'] = USER_LEVEL_MOD;
        $ret['user_level']['admin'] = USER_LEVEL_ADMIN;
        
        $ret['approval_needed'] = ( getConfig('approve_comments') == '1' );
        $ret['guest_posting'] = getConfig('comments_need_login');
        
        if ( $ret['guest_posting'] == '1' && !$session->user_logged_in )
        {
          $session->kill_captcha();
          $ret['captcha'] = $session->make_captcha();
        }
        break;
      case 'edit':
        $cid = (string)$data['id'];
        if ( !preg_match('#^([0-9]+)$#i', $cid) || intval($cid) < 1 )
        {
          echo '{"mode":"error","error":"HACKING ATTEMPT"}';
          return false;
        }
        $cid = intval($cid);
        $q = $db->sql_query('SELECT c.user_id,c.approved FROM '.table_prefix.'comments c LEFT JOIN '.table_prefix.'users u ON (u.user_id=c.user_id) WHERE comment_id='.$cid.';');
        if(!$q)
          $db->die_json();
        $row = $db->fetchrow();
        $uid = intval($row['user_id']);
        $can_edit = ( ( $uid == $session->user_id && $uid != 1 && $this->perms->get_permissions('edit_comments') ) || ( $this->perms->get_permissions('mod_comments') ) );
        if(!$can_edit)
        {
          echo '{"mode":"error","error":"HACKING ATTEMPT"}';
          return false;
        }
        $data['data'] = str_replace("\r", '', $data['data']); // Windows compatibility
        $text = RenderMan::preprocess_text($data['data'], true, false);
        $text2 = $db->escape($text);
        $subj = $db->escape(htmlspecialchars($data['subj']));
        $q = $db->sql_query('UPDATE '.table_prefix.'comments SET subject=\'' . $subj . '\',comment_data=\'' . $text2 . '\' WHERE comment_id=' . $cid . ';');
        if(!$q)
          $db->die_json();
        $ret = Array(
            'mode' => 'redraw',
            'id'   => $data['local_id'],
            'subj' => htmlspecialchars($data['subj']),
            'text' => RenderMan::render($text),
            'src'  => $text,
            'approved' => $row['approved']
          );
        break;
      case 'delete':
        $cid = (string)$data['id'];
        if ( !preg_match('#^([0-9]+)$#i', $cid) || intval($cid) < 1 )
        {
          echo '{"mode":"error","error":"HACKING ATTEMPT"}';
          return false;
        }
        $cid = intval($cid);
        $q = $db->sql_query('SELECT c.user_id FROM '.table_prefix.'comments c LEFT JOIN '.table_prefix.'users u ON (u.user_id=c.user_id) WHERE comment_id='.$cid.';');
        if(!$q)
          $db->die_json();
        $row = $db->fetchrow();
        $uid = intval($row['user_id']);
        $can_edit = ( ( $uid == $session->user_id && $uid != 1 && $this->perms->get_permissions('edit_comments') ) || ( $this->perms->get_permissions('mod_comments') ) );
        if(!$can_edit)
        {
          echo '{"mode":"error","error":"HACKING ATTEMPT"}';
          return false;
        }
        $q = $db->sql_query('DELETE FROM '.table_prefix.'comments WHERE comment_id='.$cid.';');
        if(!$q)
          $db->die_json();
        $ret = Array(
            'mode' => 'annihilate',
            'id'   => $data['local_id']
          );
        break;
      case 'submit':
        
        // Now for a huge round of security checks...
        
        $errors = Array();
        
        // Authorization
        // Like the rest of the ACL system, this call is a one-stop check for ALL ACL entries.
        if ( !$this->perms->get_permissions('post_comments') )
          $errors[] = 'An ACL entry is preventing the comment from being posted.';
        
        // Guest authorization
        if ( getConfig('comments_need_login') == '2' && !$session->user_logged_in )
          $errors[] = 'You need to log in before posting comments.';
        
        // CAPTCHA code
        if ( getConfig('comments_need_login') == '1' && !$session->user_logged_in )
        {
          $real_code = $session->get_captcha($data['captcha_id']);
          if ( $real_code != $data['captcha_code'] )
            $errors[] = 'The confirmation code you entered was incorrect.';
        }
        
        if ( count($errors) > 0 )
        {
          $ret = Array(
            'mode' => 'error',
            'error' => implode("\n", $errors)
            );
        }
        else
        {
          // We're authorized!
          
          // Preprocess
          $name = ( $session->user_logged_in ) ? htmlspecialchars($session->username) : htmlspecialchars($data['name']);
          $subj = htmlspecialchars($data['subj']);
          $text = RenderMan::preprocess_text($data['text'], true, false);
          $src = $text;
          $sql_text = $db->escape($text);
          $text = RenderMan::render($text);
          $appr = ( getConfig('approve_comments') == '1' ) ? '0' : '1';
          $time = time();
          $date = date('F d, Y h:i a', $time);
          
          // Send it to the database
          $q = $db->sql_query('INSERT INTO '.table_prefix.'comments(page_id,namespace,name,subject,comment_data,approved, time, user_id) VALUES' .
                              "('$this->page_id', '$this->namespace', '$name', '$subj', '$sql_text', $appr, $time, $session->user_id);");
          if(!$q)
            $db->die_json();
          
          // Re-fetch
          $q = $db->sql_query('SELECT c.comment_id,c.name,c.subject,c.comment_data,c.time,c.approved,u.user_level,u.user_id,u.signature FROM '.table_prefix.'comments AS c
                               LEFT JOIN '.table_prefix.'users AS u
                                 ON (u.user_id=c.user_id)
                               WHERE page_id=\'' . $this->page_id . '\'
                                 AND namespace=\'' . $this->namespace . '\'
                                 AND time='.$time.' ORDER BY comment_id DESC LIMIT 1;');
          if(!$q)
            $db->die_json();
          
          $row = $db->fetchrow();
          $db->free_result();
          $row['time'] = $date;
          $row['comment_data'] = $text;
          $row['comment_source'] = $src;
          $ret = Array(
              'mode' => 'materialize'
            );
          $ret = enano_safe_array_merge($ret, $row);
          
          $ret['auth_mod_comments'] = $this->perms->get_permissions('mod_comments');
          $ret['auth_post_comments'] = $this->perms->get_permissions('post_comments');
          $ret['auth_edit_comments'] = $this->perms->get_permissions('edit_comments');
          $ret['user_id'] = $session->user_id;
          $ret['username'] = $session->username;
          $ret['logged_in'] = $session->user_logged_in;
          $ret['signature'] = RenderMan::render($row['signature']);
          
          $ret['user_level_list'] = Array();
          $ret['user_level_list']['guest'] = USER_LEVEL_GUEST;
          $ret['user_level_list']['member'] = USER_LEVEL_MEMBER;
          $ret['user_level_list']['mod'] = USER_LEVEL_MOD;
          $ret['user_level_list']['admin'] = USER_LEVEL_ADMIN;
          
        }
        
        break;
      case 'approve':
        if ( !$this->perms->get_permissions('mod_comments') )
        {
          $ret = Array(
          'mode' => 'error', 
          'error' => 'You are not authorized to moderate comments.'
          );
          echo $parser->encode($ret);
          return $ret;
        }
        
        $cid = (string)$data['id'];
        if ( !preg_match('#^([0-9]+)$#i', $cid) || intval($cid) < 1 )
        {
          echo '{"mode":"error","error":"HACKING ATTEMPT"}';
          return false;
        }
        $cid = intval($cid);
        $q = $db->sql_query('SELECT subject,approved FROM '.table_prefix.'comments WHERE comment_id='.$cid.';');
        if(!$q || $db->numrows() < 1)
          $db->die_json();
        $row = $db->fetchrow();
        $db->free_result();
        $appr = ( $row['approved'] == '1' ) ? '0' : '1';
        $q = $db->sql_query('UPDATE '.table_prefix."comments SET approved=$appr WHERE comment_id=$cid;");
        if (!$q)
          $db->die_json();
        
        $ret = Array(
            'mode' => 'redraw',
            'approved' => $appr,
            'subj' => $row['subject'],
            'id'   => $data['local_id'],
            'approve_updated' => 'yes'
          );
        
        break;
      default:
        $ret = Array(
          'mode' => 'error', 
          'error' => $data['mode'] . ' is not a valid request mode'
          );
        break;
    }
    echo $parser->encode($ret);
    return $ret;
  }
  
} // class Comments

