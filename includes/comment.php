<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
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
 * @license GNU General Public License <http://www.gnu.org/licenses/gpl-2.0.html>
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
		if ( $page_id == $paths->page_id && $namespace == $paths->namespace )
			$this->perms =& $GLOBALS['session'];
		else
			$this->perms = $session->fetch_page_acl($page_id, $namespace);
		
		$this->page_id = $db->escape($page_id);
		$this->namespace = $db->escape($namespace);
	}
	
	/**
 	* Processes a command in JSON format.
 	* @param mixed Either the JSON-encoded input string, probably something sent from the Javascript/AJAX frontend, or an equivalent array
 	*/
 	
	function process_json($json)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		global $lang;
		
		$is_json = !is_array($json);
		
		if ( $is_json )
		{
			$data = enano_json_decode($json);
			$data = decode_unicode_array($data);
		}
		else
		{
			$data =& $json;
		}
		if ( !isset($data['mode']) )
		{
			$ret = Array('mode'=>'error','error'=>'No mode defined!');
			echo enano_json_encode($ret);
			return $ret;
		}
		if ( getConfig('enable_comments', '1') == '0' )
		{
			$ret = Array('mode'=>'error','error'=>'Comments are not enabled on this site.');
			echo enano_json_encode($ret);
			return $ret;
		}
		$ret = Array();
		$ret['mode'] = $data['mode'];
		if ( isset($data['passback']) )
			$ret['passback'] = $data['passback'];
		switch ( $data['mode'] )
		{
			case 'fetch':
				if ( !$template->theme_loaded )
					$template->load_theme();
				if ( !isset($data['have_template']) )
				{
					$ret['template'] = file_get_contents(ENANO_ROOT . '/themes/' . $template->theme . '/comment.tpl');
				}
				$approve_clause = $this->perms->get_permissions('mod_comments') ? '' : " AND approved = " . COMMENT_APPROVED;
				// Get totals
				$q = $db->sql_query('SELECT approved FROM ' . table_prefix . "comments WHERE page_id = '$this->page_id' AND namespace = '$this->namespace'{$approve_clause};");
				if ( !$q )
					$db->die_json();
				$counts = array('total' => 0, 'approved' => 0, 'unapproved' => 0, 'spam' => 0);
				while ( $row = $db->fetchrow() )
				{
					$counts['total']++;
					switch($row['approved']):
						case COMMENT_APPROVED:   $counts['approved']++;   break;
						case COMMENT_UNAPPROVED: $counts['unapproved']++; break;
						case COMMENT_SPAM:       $counts['spam']++;       break;
					endswitch;
				}
				$counts['unapproved'] = $counts['total'] - $counts['approved'];
				$data['counts'] = $counts;
				// FIXME, this should be a user preference eventually
				$ret['per_page'] = $per_page = getConfig('comments_per_page', 10);
				$page = ( !empty($data['pagenum']) ) ? intval($data['pagenum']) : 0;
				if ( $page > 0 )
				{
					$ret['mode'] = 'refetch';
				}
				$limit_clause = "LIMIT $per_page OFFSET " . ($page * $per_page);
				$q = $db->sql_query('SELECT c.comment_id,c.name,c.subject,c.comment_data,c.time,c.approved,( c.ip_address IS NOT NULL ) AS have_ip,u.username,u.user_level,u.user_id,u.email,u.signature,u.user_has_avatar,u.avatar_type, b.buddy_id IS NOT NULL AS is_buddy, ( b.is_friend IS NOT NULL AND b.is_friend=1 ) AS is_friend FROM '.table_prefix.'comments AS c
 															LEFT JOIN '.table_prefix.'users AS u
 																ON (u.user_id=c.user_id)
 															LEFT JOIN '.table_prefix.'buddies AS b
 																ON ( ( b.user_id=' . $session->user_id.' AND b.buddy_user_id=c.user_id ) OR b.user_id IS NULL)
 															LEFT JOIN '.table_prefix.'ranks AS r
 																ON ( ( u.user_rank = r.rank_id ) )
 															WHERE page_id=\'' . $this->page_id . '\'
 																AND namespace=\'' . $this->namespace . '\'
 																' . $approve_clause . '
 															GROUP BY c.comment_id,c.name,c.subject,c.comment_data,c.time,c.approved,c.ip_address,u.username,u.user_level,u.user_id,u.email,u.signature,u.user_has_avatar,u.avatar_type,b.buddy_id,b.is_friend
 															ORDER BY c.time ASC
 															' . $limit_clause . ';');
				$ret['comments'] = Array();
				if (!$q)
					$db->die_json();
				if ( $row = $db->fetchrow($q) )
				{
					do {
						
						if ( !$this->perms->get_permissions('mod_comments') && $row['approved'] != COMMENT_APPROVED )
							continue;
						
						// Localize the rank
						$row = array_merge($row, $session->get_user_rank(intval($row['user_id'])));
						
						// Send the source
						$row['comment_source'] = $row['comment_data'];
						
						// Format text
						$row['comment_data'] = RenderMan::render($row['comment_data']);
						
						// Hide it if it's a post from a foe
						// 1.1.8: moved to within the comment templates
						/*
						if ( $row['is_buddy'] == 1 && $row['is_friend'] == 0 )
						{
							$seed = md5(sha1(mt_rand() . microtime()));
							$wrapper = '
								<div id="posthide_'.$seed.'" style="display: none;">
									' . $row['comment_data'] . '
								</div>
								<p><span style="opacity: 0.4; filter: alpha(opacity=40);">' . $lang->get('comment_msg_foe_comment_hidden') . '</span> <span style="text-align: right;"><a href="#showpost" onclick="document.getElementById(\'posthide_'.$seed.'\').style.display=\'block\'; this.parentNode.parentNode.parentNode.removeChild(this.parentNode.parentNode); return false;">' . $lang->get('comment_btn_display_foe_comment') . '</a></span></p>
							';
							$row['comment_data'] = $wrapper;
						}
						*/
						
						// Format date
						$row['time'] = enano_date(ED_DATE | ED_TIME, $row['time']);
						
						// Format signature
						$row['signature'] = ( !empty($row['signature']) ) ? RenderMan::render($row['signature']) : '';
						
						// Do we have the IP?
						$row['have_ip'] = ( $row['have_ip'] == 1 );
						
						// Avatar URL
						$row['avatar_path'] = make_avatar_url($row['user_id'], $row['avatar_type'], $row['email']);
						
						// Name
						//if ( $row['user_id'] > 1 )
						//	$row['name'] = $row['username'];
						
						// Add the comment to the list
						$ret['comments'][] = $row;
						
					} while ( $row = $db->fetchrow($q) );
				}
				$db->free_result();
				$ret['count_appr'] = $counts['approved'];
				$ret['count_total'] = $counts['total'];
				$ret['count_visible'] = $this->perms->get_permissions('mod_comments') ? $counts['total'] : $counts['approved'];
				$ret['count_unappr'] = $counts['unapproved'];
				$ret['auth_mod_comments'] = $this->perms->get_permissions('mod_comments');
				$ret['auth_post_comments'] = $this->perms->get_permissions('post_comments');
				$ret['auth_edit_comments'] = $this->perms->get_permissions('edit_comments');
				$ret['auth_edit_wysiwyg'] = $this->perms->get_permissions('edit_wysiwyg');
				$ret['user_id'] = $session->user_id;
				$ret['username'] = $session->username;
				$ret['logged_in'] = $session->user_logged_in;
				
				$ret['user_level'] = Array();
				$ret['user_level']['guest'] = USER_LEVEL_GUEST;
				$ret['user_level']['member'] = USER_LEVEL_MEMBER;
				$ret['user_level']['mod'] = USER_LEVEL_MOD;
				$ret['user_level']['admin'] = USER_LEVEL_ADMIN;
				
				$ret['approval_needed'] = ( getConfig('approve_comments', '0') == '1' );
				$ret['guest_posting'] = getConfig('comments_need_login');
				
				if ( $ret['guest_posting'] == '1' && !$session->user_logged_in )
				{
					$session->kill_captcha();
					$ret['captcha'] = $session->make_captcha();
				}
				break;
			case 'edit':
				$cid = (string)$data['id'];
				if ( !ctype_digit($cid) || intval($cid) < 1 )
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
				if ( !ctype_digit($cid) || intval($cid) < 1 )
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
					$errors[] = 'The site security policy prevents your user account from posting comments;';
				
				// Guest authorization
				if ( getConfig('comments_need_login') == '2' && !$session->user_logged_in )
					$errors[] = $lang->get('comment_err_need_login');
				
				// CAPTCHA code
				if ( getConfig('comments_need_login') == '1' && !$session->user_logged_in )
				{
					$real_code = $session->get_captcha($data['captcha_id']);
					if ( strtolower($real_code) !== strtolower($data['captcha_code']) )
						$errors[] = $lang->get('comment_err_captcha_wrong');
					$session->kill_captcha();
				}
				
				// Spam check
				$spam_policy = getConfig('comment_spam_policy', 'moderate');
				$sc_name = ( $session->user_logged_in ) ? $session->username : $data['name'];
				$sc_mail = ( $session->user_logged_in ) ? $session->email : false;
				$sc_url  = ( $session->user_logged_in ) ? @$session->user_extra['user_homepage'] : false;
				$spamcheck = $spam_policy === 'accept' ? true : spamalyze($data['text'], $sc_name, $sc_mail, $sc_url);
				if ( !$spamcheck && $spam_policy === 'reject' )
				{
					$errors[] = $lang->get('comment_err_spamcheck_failed_rejected');
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
					$sql_subj = $db->escape($subj);
					$sql_text = $db->escape($text);
					$text = RenderMan::render($text);
					$appr = ( getConfig('approve_comments', '0') == '1' ) ? COMMENT_UNAPPROVED : COMMENT_APPROVED;
					if ( $spam_policy === 'moderate' && !$spamcheck )
						$appr = COMMENT_SPAM;
					$time = time();
					$date = enano_date(ED_DATE | ED_TIME, $time);
					$ip = $_SERVER['REMOTE_ADDR'];
					if ( !is_valid_ip($ip) )
						die('Hacking attempt');
					
					// Send it to the database
					$q = $db->sql_query('INSERT INTO '.table_prefix.'comments(page_id,namespace,name,subject,comment_data,approved, time, user_id, ip_address) VALUES' . "\n  " .
 														"('$this->page_id', '$this->namespace', '$name', '$sql_subj', '$sql_text', $appr, $time, {$session->user_id}, '$ip');");
					if(!$q)
						$db->die_json();
					
					// Re-fetch
					$q = $db->sql_query('SELECT c.comment_id,c.name,c.subject,c.comment_data,c.time,c.approved,u.user_level,u.user_id,u.email,u.signature,u.user_has_avatar,u.avatar_type FROM '.table_prefix.'comments AS c
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
					$ret['rank_data'] = $session->get_user_rank($session->user_id);
					$ret['username'] = $session->username;
					$ret['logged_in'] = $session->user_logged_in;
					$ret['signature'] = RenderMan::render($row['signature']);
					
					$ret['user_level_list'] = Array();
					$ret['user_level_list']['guest'] = USER_LEVEL_GUEST;
					$ret['user_level_list']['member'] = USER_LEVEL_MEMBER;
					$ret['user_level_list']['mod'] = USER_LEVEL_MOD;
					$ret['user_level_list']['admin'] = USER_LEVEL_ADMIN;
					$ret['avatar_path'] = make_avatar_url($row['user_id'], $row['avatar_type'], $row['email']);
				}
				
				break;
			case 'approve':
				if ( !$this->perms->get_permissions('mod_comments') )
				{
					$ret = Array(
					'mode' => 'error', 
					'error' => 'You are not authorized to moderate comments.'
					);
					echo enano_json_encode($ret);
					return $ret;
				}
				
				$cid = (string)$data['id'];
				if ( !ctype_digit($cid) || intval($cid) < 1 )
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
			case 'view_ip':
				if ( !$session->get_permissions('mod_comments') )
				{
					return array(
							'mode' => 'error',
							'error' => 'Unauthorized'
						);
				}
				// fetch comment info
				if ( !is_int($data['id']) )
				{
					return array(
							'mode' => 'error',
							'error' => 'Unauthorized'
						);
				}
				$id =& $data['id'];
				$q = $db->sql_query('SELECT ip_address, name FROM ' . table_prefix . 'comments WHERE comment_id = ' . $id . ';');
				if ( !$q || $db->numrows() < 1 )
				{
					$db->die_json();
				}
				list($ip_addr, $name) = $db->fetchrow_num($q);
				$db->free_result();
				$name = $db->escape($name);
				$username = $db->escape($session->username);
				// log this action
				$q = $db->sql_query('INSERT INTO ' . table_prefix . "logs(time_id, log_type, action, page_text, author, author_uid, edit_summary) VALUES\n  "
														. "( " . time() . ", 'security', 'view_comment_ip', '$name', '$username', $session->user_id, '{$_SERVER['REMOTE_ADDR']}' );");
				if ( !$q )
					$db->die_json();
				
				// send packet
				$ret = array(
						'mode' => 'redraw',
						'ip_addr' => $ip_addr,
						'local_id' => $data['local_id']
					);
				break;
			default:
				$ret = Array(
					'mode' => 'error', 
					'error' => $data['mode'] . ' is not a valid request mode'
					);
				break;
		}
		if ( $is_json )
			echo enano_json_encode($ret);
		
		return $ret;
	}
	
} // class Comments

