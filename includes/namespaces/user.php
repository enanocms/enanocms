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

class Namespace_User extends Namespace_Default
{
  public function __construct($page_id, $namespace, $revision_id = 0)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    parent::__construct($page_id, $namespace, $revision_id);
    
    if ( ( $this->title == str_replace('_', ' ', $this->page_id) || $this->title == $paths->nslist['User'] . str_replace('_', ' ', $this->page_id) ) || !$this->exists )
    {
      $this->title = $lang->get('userpage_page_title', array('username' => str_replace('_', ' ', dirtify_page_id($this->page_id))));
      $this->cdata['name'] = $this->title;
    }
    
  }
  
  public function send()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $email;
    global $lang, $output;
    
    /**
     * PLUGGING INTO USER PAGES
     * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
     * Userpages are highly programmable and extendable using a number of
     * hooks. These hooks are:
     *
     *   - userpage_sidebar_left
     *   - userpage_sidebar_right
     *   - userpage_tabs_links
     *   - userpage_tabs_body
     *
     * You can add a variety of sections to user pages, including new tabs
     * and new sections on the tables. To add a tab, attach to
     * userpage_tabs_links and echo out:
     *
     *   <li><a href="#tab:YOURTABID">YOUR TAB TEXT</a></li>
     *
     * Then hook into userpage_tabs_body and echo out:
     *
     *   <div id="tab:YOURTABID">YOUR TAB CONTENT</div>
     *
     * The userpage javascript runtime will take care of everything else,
     * meaning transitions, click events, etc. Currently it's not possible
     * to add custom click events to tabs, but any DOM-related JS that needs
     * to run in your tab can be run onload and the effects will be seen when
     * your tab is clicked. YOURTABID should be lowercase alphanumeric and
     * have a short prefix so as to assure that it remains specific to your
     * plugin.
     *
     * To hook into the "profile" tab, use userpage_sidebar_{left,right}. Just
     * echo out table cells as normal. The table on the left (the wide one) has
     * four columns, and the one on the right has one column.
     * 
     * See plugins.php for a guide on creating and attaching to hooks.
     */
     
    $page_urlname = dirtify_page_id($this->page_id);
    
    $target_username = strtr($page_urlname, 
      Array(
        '_' => ' ',
        '<' => '&lt;',
        '>' => '&gt;'
        ));
    
    $target_username = preg_replace('/^' . str_replace('/', '\\/', preg_quote($paths->nslist['User'])) . '/', '', $target_username);
    list($target_username) = explode('/', $target_username);
    
    $output->set_title($this->title);
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
    if ( $user_exists )
    {
      $rank_data = $session->get_user_rank(intval($userdata['authoritative_uid']));
    }
    else
    {
      // get the rank data for the anonymous user (placeholder basically)
      $rank_data = $session->get_user_rank(1);
    }
    
    // add the userpage script to the header
    $template->add_header('<script type="text/javascript" src="' . cdnPath . '/includes/clientside/static/userpage.js"></script>');
    
    $output->header();
    
    // if ( $send_headers )
    // {
    //  display_page_headers();
    // }
   
    //
    // BASIC INFORMATION
    // Presentation of username/rank/avatar/basic info
    //
    
    if ( $user_exists )
    {
    
      ?>
      <div id="userpage_wrap">
        <ul id="userpage_links">
          <li><a href="#tab:profile"><?php echo $lang->get('userpage_tab_profile'); ?></a></li>
          <li><a href="#tab:content"><?php echo $lang->get('userpage_tab_content'); ?></a></li>
          <?php
          $code = $plugins->setHook('userpage_tabs_links');
          foreach ( $code as $cmd )
          {
            eval($cmd);
          }
          ?>
        </ul>
        
        <div id="tab:profile">
      
      <?php
      
      echo '<table border="0" cellspacing="0" cellpadding="0">
              <tr>';
                
      echo '    <td valign="top">';
      
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4">';
              
      // heading
      echo '    <tr>
                  <th colspan="' . ( $session->user_level >= USER_LEVEL_ADMIN ? '3' : '4' ) . '">
                    ' . $lang->get('userpage_heading_basics', array('username' => htmlspecialchars($target_username))) . '
                  </th>
                  ' . (
                    $session->user_level >= USER_LEVEL_ADMIN ?
                    '<th class="subhead" style="width: 25%;"><a href="' . makeUrlNS('Special', 'Administration', 'module=' . $paths->nslist['Admin'] . 'UserManager&src=get&user=' . urlencode($target_username), true) . '" onclick="ajaxAdminUser(\'' . addslashes($target_username) . '\'); return false;">&raquo; ' . $lang->get('userpage_btn_administer_user') . '</a></th>'
                      : ''
                  ) . '
                </tr>';
                
      // avi/rank/username
      echo '    <tr>
                  <td class="row3" colspan="4">
                    ' . (
                        $userdata['user_has_avatar'] == 1 ?
                        '<div style="float: left; margin-right: 10px;">
                          <img alt="' . $lang->get('usercp_avatar_image_alt', array('username' => $userdata['username'])) . '" src="' . make_avatar_url(intval($userdata['authoritative_uid']), $userdata['avatar_type'], $userdata['email']) . '" />
                         </div>'
                        : ''
                      ) . '
                      <span style="font-size: x-large; ' . $rank_data['rank_style'] . '">' . htmlspecialchars($userdata['username']) . '</span>
                      ' . ( !empty($rank_data['user_title']) ? '<br />' . htmlspecialchars($rank_data['user_title']) : '' ) . '
                      ' . ( !empty($rank_data['rank_title']) ? '<br />' . htmlspecialchars($lang->get($rank_data['rank_title'])) : '' ) . '
                  </td>
                </tr>';
                
      // join date & total comments
      echo '<tr>';
      echo '  <td class="row2" style="text-align: right; width: 25%;">
                ' . $lang->get('userpage_lbl_joined') . '
              </td>
              <td class="row1" style="text-align: left; width: 25%;">
                ' . enano_date('F d, Y h:i a', $userdata['reg_time']) . '
              </td>';
      echo '  <td class="row2" style="text-align: right; width: 25%;">
                ' . $lang->get('userpage_lbl_num_comments') . '
              </td>
              <td class="row1" style="text-align: left; width: 25%;">
                ' . $userdata['n_comments'] . '
              </td>';
      echo '</tr>';
      
      // real name
      if ( !empty($userdata['real_name']) )
      {
        echo '<tr>
                <td class="row2" style="text-align: right;">
                  ' . $lang->get('userpage_lbl_real_name') . '
                </td>
                <td class="row1" colspan="3" style="text-align: left;">
                  ' . htmlspecialchars($userdata['real_name']) . '
                </td>
              </tr>';
      }
                
      // latest comments
      
      echo '<tr><th class="subhead" colspan="4">' . $lang->get('userpage_heading_comments', array('username' => htmlspecialchars($target_username))) . '</th></tr>';
      $q = $db->sql_query('SELECT page_id, namespace, subject, time FROM '.table_prefix.'comments WHERE name=\'' . $db->escape($target_username) . '\' AND user_id=' . $userdata['authoritative_uid'] . ' AND approved=1 ORDER BY time DESC LIMIT 7;');
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
      
      echo '<tr><td class="row3" colspan="4">';
      echo '<div style="border: 1px solid #000000; padding: 0px; width: 100%; clip: rect(0px,auto,auto,0px); overflow: auto; background-color: transparent;" class="tblholder">';
      
      echo '<table border="0" cellspacing="1" cellpadding="4" style="width: 200%;"><tr>';
      $class = 'row1';
      
      $tpl = '  <td class="{CLASS}">
                  <a href="{PAGE_LINK}" <!-- BEGINNOT page_exists -->class="wikilink-nonexistent"<!-- END page_exists -->>{PAGE}</a><br />
                  <small>{lang:userpage_comments_lbl_posted} {DATE}<br /></small>
                  <b><a href="{COMMENT_LINK}">{SUBJECT}</a></b>
                </td>';
      $parser = $template->makeParserText($tpl);
      
      if ( count($comments) > 0 )
      {
        foreach ( $comments as $comment )
        {
          $c_page_id = $paths->nslist[ $comment['namespace'] ] . sanitize_page_id($comment['page_id']);
          if ( isPage($c_page_id) )
          {
            $parser->assign_bool(array(
              'page_exists' => true
              ));
            $page_title = get_page_title($c_page_id);
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
        echo '<td class="' . $class . '">' . $lang->get('userpage_msg_no_comments') . '</td>';
      }
      echo '</tr></table>';
      
      echo '</div>';
      echo '</td></tr>';
      
      $code = $plugins->setHook('userpage_sidebar_left');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
              
      echo '  </table>
            </div>';
            
      echo '</td>';
      
      //
      // CONTACT INFORMATION
      //
      
      echo '    <td valign="top" style="width: 150px; padding-left: 10px;">';
      
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
      
      if ( !empty($userdata['user_homepage']) )
      {
        $class = ( $class == 'row1' ) ? 'row3' : 'row1';
        echo '<tr><td class="' . $class . '">' . $lang->get('userpage_lbl_homepage') . '<br /><a href="' . $userdata['user_homepage'] . '">' . $userdata['user_homepage'] . '</a></td></tr>';
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
      echo '</td>';
      
      //
      // End of profile
      //
      
      echo '</tr></table>';
      
      echo '</div>'; // tab:profile
    
    }
    
    // User's own content
    
    echo '<span class="menuclear"></span>';
    
    echo '<div id="tab:content">';
    
    if ( $this->exists )
    {
      $this->send_from_db(true, false);
    }
    else
    {
      $this->error_404(true);
    }
    
    echo '</div>'; // tab:content
    
    $code = $plugins->setHook('userpage_tabs_body');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if ( $user_exists )
    {
      echo '</div>'; // userpage_wrap
    }
    else
    {
      if ( !is_valid_ip($target_username) )
      {
        echo '<p>' . $lang->get('userpage_msg_user_not_exist', array('username' => htmlspecialchars($target_username))) . '</p>';
      }
    }
    
    // if ( $send_headers )
    // {
    //  display_page_footers();
    // }
    
    $output->footer();
  }
}

