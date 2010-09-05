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
 *
 * @package Enano
 * @subpackage Frontend
 */

define('ENANO_INTERFACE_INDEX', '');

// start up Enano
require('includes/common.php');

// decide on HTML compacting
$aggressive_optimize_html = !defined('ENANO_DEBUG') && !isset($_GET['nocompress']);

// Set up gzip encoding before any output is sent
global $do_gzip;
$do_gzip = true;

error_reporting(E_ALL);

if($aggressive_optimize_html || $do_gzip)
{
	ob_start();
}

global $db, $session, $paths, $template, $plugins; // Common objects
$page_timestamp = time();

if ( !isset($_GET['do']) )
{
	$_GET['do'] = 'view';
}
switch($_GET['do'])
{
	default:
		$code = $plugins->setHook('page_action');
		ob_start();
		foreach ( $code as $cmd )
		{
			eval($cmd);
		}
		if ( $contents = ob_get_contents() )
		{
			ob_end_clean();
			echo $contents;
		}
		else
		{
			die_friendly('Invalid action', '<p>The action "'.htmlspecialchars($_GET['do']).'" is not defined. Return to <a href="'.makeUrl($paths->page).'">viewing this page\'s text</a>.</p>');
		}
		break;
	case 'view':
		// echo PageUtils::getpage($paths->page, true, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
		$rev_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
		$page = new PageProcessor( $paths->page_id, $paths->namespace, $rev_id );
		// Feed this PageProcessor to the template processor. This prevents $template from starting another
		// PageProcessor when we already have one going.
		$template->set_page($page);
		$page->send_headers = true;
		$page->allow_redir = ( !isset($_GET['redirect']) || (isset($_GET['redirect']) && $_GET['redirect'] !== 'no') );
		$pagepass = ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '';
		$page->password = $pagepass;
		$page->send(true);
		$page_timestamp = $page->revision_time;
		break;
	case 'comments':
		$output->header();
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		$sub = ( isset ($_GET['sub']) ) ? $_GET['sub'] : false;
		switch($sub)
		{
			case 'admin':
			default:
				$act = ( isset ($_GET['action']) ) ? $_GET['action'] : false;
				$id = ( isset ($_GET['id']) ) ? intval($_GET['id']) : -1;
				echo PageUtils::comments_html($paths->page_id, $paths->namespace, $act, Array('id'=>$id));
				break;
			case 'postcomment':
				if(empty($_POST['name']) ||
 					empty($_POST['subj']) ||
 					empty($_POST['text'])
 					) { echo 'Invalid request'; break; }
				$cid = ( isset($_POST['captcha_id']) ) ? $_POST['captcha_id'] : false;
				$cin = ( isset($_POST['captcha_input']) ) ? $_POST['captcha_input'] : false;
				
				require_once('includes/comment.php');
				$comments = new Comments($paths->page_id, $paths->namespace);
				
				$submission = array(
						'mode' => 'submit',
						'captcha_id' => $cid,
						'captcha_code' => $cin,
						'name' => $_POST['name'],
						'subj' => $_POST['subj'],
						'text' => $_POST['text'],
					);
				
				$result = $comments->process_json($submission);
				if ( $result['mode'] == 'error' )
				{
					echo '<div class="error-box">' . htmlspecialchars($result['error']) . '</div>';
				}
				else
				{
					echo '<div class="info-box">' . $lang->get('comment_msg_comment_posted') . '</div>';
				}
				
				echo PageUtils::comments_html($paths->page_id, $paths->namespace);
				break;
			case 'editcomment':
				if(!isset($_GET['id']) || ( isset($_GET['id']) && !preg_match('#^([0-9]+)$#', $_GET['id']) )) { echo '<p>Invalid comment ID</p>'; break; }
				$q = $db->sql_query('SELECT subject,comment_data,comment_id FROM '.table_prefix.'comments WHERE comment_id='.$_GET['id']);
				if(!$q) $db->_die('The comment data could not be selected.');
				$row = $db->fetchrow();
				$db->free_result();
				$row['subject'] = str_replace('\'', '&#039;', $row['subject']);
				echo '<form action="'.makeUrl($paths->page, 'do=comments&amp;sub=savecomment').'" method="post">';
				echo "<br /><div class='tblholder'><table border='0' width='100%' cellspacing='1' cellpadding='4'>
								<tr><td class='row1'>" . $lang->get('comment_postform_field_subject') . "</td><td class='row1'><input type='text' name='subj' value='{$row['subject']}' /></td></tr>
								<tr><td class='row2'>" . $lang->get('comment_postform_field_comment') . "</td><td class='row2'><textarea rows='10' cols='40' style='width: 98%;' name='text'>{$row['comment_data']}</textarea></td></tr>
								<tr><td class='row1' colspan='2' class='row1' style='text-align: center;'><input type='hidden' name='id' value='{$row['comment_id']}' /><input type='submit' value='" . $lang->get('etc_save_changes') . "' /></td></tr>
							</table></div>";
				echo '</form>';
				break;
			case 'savecomment':
				if(empty($_POST['subj']) || empty($_POST['text'])) { echo '<p>Invalid request</p>'; break; }
				$r = PageUtils::savecomment_neater($paths->page_id, $paths->namespace, $_POST['subj'], $_POST['text'], (int)$_POST['id']);
				if($r != 'good') { echo "<pre>$r</pre>"; break; }
				echo PageUtils::comments_html($paths->page_id, $paths->namespace);
				break;
			case 'deletecomment':
				if(!empty($_GET['id']))
				{
					PageUtils::deletecomment_neater($paths->page_id, $paths->namespace, (int)$_GET['id']);
				}
				echo PageUtils::comments_html($paths->page_id, $paths->namespace);
				break;
		}
		$output->footer();
		break;
	case 'edit':
		if(isset($_POST['_cancel']))
		{
			redirect(makeUrl($paths->page), '', '', 0);
			break;
		}
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(isset($_POST['_save']))
		{
			$captcha_valid = true;
			if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
			{
				$captcha_valid = false;
				if ( isset($_POST['captcha_id']) && isset($_POST['captcha_code']) )
				{
					$hash_correct = strtolower($session->get_captcha($_POST['captcha_id']));
					$hash_input   = strtolower($_POST['captcha_code']);
					if ( $hash_input === $hash_correct )
						$captcha_valid = true;
				}
			}
			if ( $captcha_valid )
			{
				$e = PageUtils::savepage($paths->page_id, $paths->namespace, $_POST['page_text'], $_POST['edit_summary'], isset($_POST['minor']));
				if ( $e == 'good' )
				{
					redirect(makeUrl($paths->page), $lang->get('editor_msg_save_success_title'), $lang->get('editor_msg_save_success_body'), 3);
				}
			}
		}
		$template->header();
		if ( isset($captcha_valid) )
		{
			echo '<div class="usermessage">' . $lang->get('editor_err_captcha_wrong') . '</div>';
		}
		if(isset($_POST['_preview']))
		{
			$text = $_POST['page_text'];
			$edsumm = $_POST['edit_summary'];
			echo PageUtils::genPreview($_POST['page_text']);
			$text = htmlspecialchars($text);
			$revid = 0;
		}
		else
		{
			$revid = ( isset($_GET['revid']) ) ? intval($_GET['revid']) : 0;
			$page = new PageProcessor($paths->page_id, $paths->namespace, $revid);
			$text = $page->fetch_source();
			$edsumm = '';
			// $text = RenderMan::getPage($paths->cpage['urlname_nons'], $paths->namespace, 0, false, false, false, false);
		}
		if ( $revid > 0 )
		{
			$time = $page->revision_time;
			// Retrieve information about this revision and the current one
			$q = $db->sql_query('SELECT l1.author AS currentrev_author, l2.author AS oldrev_author FROM ' . table_prefix . 'logs AS l1
LEFT JOIN ' . table_prefix . 'logs AS l2
	ON ( l2.log_id = ' . $revid . '
 			AND l2.log_type  = \'page\'
 			AND l2.action    = \'edit\'
 			AND l2.page_id   = \'' . $db->escape($paths->page_id) . '\'
 			AND l2.namespace = \'' . $db->escape($paths->namespace) . '\'
 			AND l1.is_draft != 1
			)
WHERE l1.log_type  = \'page\'
	AND l1.action    = \'edit\'
	AND l1.page_id   = \'' . $db->escape($paths->page_id) . '\'
	AND l1.namespace = \'' . $db->escape($paths->namespace) . '\'
	AND l1.time_id > ' . $time . '
	AND l1.is_draft != 1
ORDER BY l1.time_id DESC;');
			if ( !$q )
				$db->die_json();
			
			if ( $db->numrows() > 0 )
			{
				echo '<div class="usermessage">' . $lang->get('editor_msg_editing_old_revision') . '</div>';
				
				$rev_count = $db->numrows() - 2;
				$row = $db->fetchrow();
				$undo_info = array(
					'old_author'     => $row['oldrev_author'],
					'current_author' => $row['currentrev_author'],
					'undo_count'     => max($rev_count, 1),
					'last_rev_id'    => $revid
				);
			}
			else
			{
				$revid = 0;
			}
			$db->free_result();
		}
		echo '
			<form action="'.makeUrl($paths->page, 'do=edit').'" method="post" enctype="multipart/form-data">
			<br />
			<textarea name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea><br />
			<br />
			';
		$edsumm = ( $revid > 0 ) ? $lang->get('editor_reversion_edit_summary', $undo_info) : $edsumm;
		echo $lang->get('editor_lbl_edit_summary') . ' <input name="edit_summary" type="text" size="40" value="' . htmlspecialchars($edsumm) . '" /><br /><label><input type="checkbox" name="minor" /> ' . $lang->get('editor_lbl_minor_edit_field') . '</label><br />';
		if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
		{
			echo '<br /><table border="0"><tr><td>';
			echo '<b>' . $lang->get('editor_lbl_field_captcha') . '</b><br />'
 					. '<br />'
 					. $lang->get('editor_msg_captcha_pleaseenter') . '<br /><br />'
 					. $lang->get('editor_msg_captcha_blind');
			echo '</td><td>';
			$hash = $session->make_captcha();
			echo '<img src="' . makeUrlNS('Special', "Captcha/$hash") . '" onclick="this.src+=\'/a\'" style="cursor: pointer;" /><br />';
			echo '<input type="hidden" name="captcha_id" value="' . $hash . '" />';
			echo $lang->get('editor_lbl_field_captcha_code') . ' <input type="text" name="captcha_code" value="" size="9" />';
			echo '</td></tr></table>';
		}
		echo '<br />
				<input type="submit" name="_save"    value="' . $lang->get('editor_btn_save') . '" style="font-weight: bold;" />
				<input type="submit" name="_preview" value="' . $lang->get('editor_btn_preview') . '" />
				<input type="submit" name="_revert"  value="' . $lang->get('editor_btn_revert') . '" />
				<input type="submit" name="_cancel"  value="' . $lang->get('editor_btn_cancel') . '" />
			</form>
		';
		if ( getConfig('wiki_edit_notice', '0') == '1' )
		{
			$notice = getConfig('wiki_edit_notice_text');
			echo RenderMan::render($notice);
		}
		$template->footer();
		break;
	case 'viewsource':
		$template->header();
		$text = RenderMan::getPage($paths->page_id, $paths->namespace, 0, false, false, false, false);
		$text = htmlspecialchars($text);
		echo '
			<form action="'.makeUrl($paths->page, 'do=edit').'" method="post">
			<br />
			<textarea readonly="readonly" name="page_text" rows="20" cols="60" style="width: 97%;">'.$text.'</textarea>';
		echo '<br />
				<input type="submit" name="_cancel" value="' . $lang->get('editor_btn_closeviewer') . '" />
			</form>
		';
		$template->footer();
		break;
	case 'history':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		$hist = PageUtils::histlist($paths->page_id, $paths->namespace);
		$template->header();
		echo $hist;
		$template->footer();
		break;
	case 'rollback':
		$id = (isset($_GET['id'])) ? $_GET['id'] : false;
		if(!$id || !ctype_digit($id)) die_friendly('Invalid action ID', '<p>The URL parameter "id" is not an integer. Exiting to prevent nasties like SQL injection, etc.</p>');
		
		$id = intval($id);
		
		$page = new PageProcessor($paths->page_id, $paths->namespace);
		$result = $page->rollback_log_entry($id);
		
		if ( $result['success'] )
		{
			$result = $lang->get("page_msg_rb_success_{$result['action']}", array('dateline' => $result['dateline']));
		}
		else
		{
			$result = $lang->get("page_err_{$result['error']}", array('action' => @$result['action']));
		}
		
		$template->header();
		echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a></p>';
		$template->footer();
		break;
	case 'catedit':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(isset($_POST['save']))
		{
			unset($_POST['save']);
			$val = PageUtils::catsave($paths->page_id, $paths->namespace, $_POST['categories']);
			if($val == 'GOOD')
			{
				header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break;
			} else {
				die_friendly('Error saving category information', '<p>'.$val.'</p>');
			}
		}
		elseif(isset($_POST['__enanoCatCancel']))
		{
			header('Location: '.makeUrl($paths->page)); echo '<html><head><title>Redirecting...</title></head><body>If you haven\'t been redirected yet, <a href="'.makeUrl($paths->page).'">click here</a>.'; break;
		}
		$template->header();
		$c = PageUtils::catedit_raw($paths->page_id, $paths->namespace);
		echo $c[1];
		$template->footer();
		break;
	case 'moreoptions':
		$template->header();
		echo '<div class="menu_nojs" style="width: 150px; padding: 0;"><ul style="display: block;"><li><div class="label">' . $lang->get('ajax_lbl_moreoptions_nojs') . '</div><div style="clear: both;"></div></li>'.$template->toolbar_menu.'</ul></div>';
		$template->footer();
		break;
	case 'protect':
		if ( !$session->sid_super )
		{
			redirect(makeUrlNS('Special', "Login/{$paths->page}", 'target_do=protect&level=' . $session->user_level, false), $lang->get('etc_access_denied_short'), $lang->get('etc_access_denied_need_reauth'), 0);
		}
		
		if ( isset($_POST['level']) && isset($_POST['reason']) )
		{
			$level = intval($_POST['level']);
			if ( !in_array($level, array(PROTECT_FULL, PROTECT_SEMI, PROTECT_NONE)) )
			{
				$errors[] = 'bad level';
			}
			$reason = trim($_POST['reason']);
			if ( empty($reason) )
			{
				$errors[] = $lang->get('onpage_protect_err_need_reason');
			}
			
			$page = new PageProcessor($paths->page_id, $paths->namespace);
			$result = $page->protect_page($level, $reason);
			if ( $result['success'] )
			{
				redirect(makeUrl($paths->page), $lang->get('page_protect_lbl_success_title'), $lang->get('page_protect_lbl_success_body', array('page_link' => makeUrl($paths->page, false, true))), 3);
			}
			else
			{
				$errors[] = $lang->get('page_err_' . $result['error']);
			}
		}
		$template->header();
		?>
		<form action="<?php echo makeUrl($paths->page, 'do=protect'); ?>" method="post">
			<h3><?php echo $lang->get('onpage_protect_heading'); ?></h3>
			<p><?php echo $lang->get('onpage_protect_msg_select_level'); ?></p>
			
			<?php
			if ( !empty($errors) )
			{
				echo '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
			}
			?>
			
			<div class="protectlevel" style="line-height: 22px; margin-left: 17px;">
				<label>
					<input type="radio" name="level" value="<?php echo PROTECT_FULL; ?>" />
					<?php echo gen_sprite(cdnPath . '/images/protect-icons.png', 22, 22, 0, 0); ?>
					<?php echo $lang->get('onpage_protect_btn_full'); ?>
				</label>
			</div>
			<div class="protectlevel_hint" style="font-size: smaller; margin-left: 68px;">
				<?php echo $lang->get('onpage_protect_btn_full_hint'); ?>
			</div>
			
			<div class="protectlevel" style="line-height: 22px; margin-left: 17px;">
				<label>
					<input type="radio" name="level" value="<?php echo PROTECT_SEMI; ?>" />
					<?php echo gen_sprite(cdnPath . '/images/protect-icons.png', 22, 22, 22, 0); ?>
					<?php echo $lang->get('onpage_protect_btn_semi'); ?>
				</label>
			</div>
			<div class="protectlevel_hint" style="font-size: smaller; margin-left: 68px;">
				<?php echo $lang->get('onpage_protect_btn_semi_hint'); ?>
			</div>
			
			<div class="protectlevel" style="line-height: 22px; margin-left: 17px;">
				<label>
					<input type="radio" name="level" value="<?php echo PROTECT_NONE; ?>" />
					<?php echo gen_sprite(cdnPath . '/images/protect-icons.png', 22, 22, 44, 0); ?>
					<?php echo $lang->get('onpage_protect_btn_none'); ?>
				</label>
			</div>
			<div class="protectlevel_hint" style="font-size: smaller; margin-left: 68px;">
				<?php echo $lang->get('onpage_protect_btn_none_hint'); ?>
			</div>
			
			<table style="margin-left: 1em;" cellspacing="10">
				<tr>
					<td valign="top">
						<?php echo $lang->get('onpage_protect_lbl_reason'); ?>
					</td>
					<td>
						<input type="text" name="reason" size="40" /><br />
						<small><?php echo $lang->get('onpage_protect_lbl_reason_hint'); ?></small>
					</td>
				</tr>
			</table>
														
			<p>
				<input type="submit" value="<?php echo htmlspecialchars($lang->get('page_protect_btn_submit')) ?>" style="font-weight: bold;" />
				<a class="abutton" href="<?php echo makeUrl($paths->page, false, true); ?>"><?php echo $lang->get('etc_cancel'); ?></a>
			</p> 
		</form>
		<?php
		$template->footer();
		break;
	case 'rename':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(!empty($_POST['newname']))
		{
			$r = PageUtils::rename($paths->page_id, $paths->namespace, $_POST['newname']);
			die_friendly($lang->get('page_rename_success_title'), '<p>'.nl2br($r).' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>');
		}
		$template->header();
		?>
		<form action="<?php echo makeUrl($paths->page, 'do=rename'); ?>" method="post">
			<?php if(isset($_POST['newname'])) echo '<p style="color: red;">' . $lang->get('page_rename_err_need_name') . '</p>'; ?>
			<p><?php echo $lang->get('page_rename_lbl'); ?></p>
			<p><input type="text" name="newname" size="40" /></p>
			<p><input type="submit" value="<?php echo htmlspecialchars($lang->get('page_rename_btn_submit')); ?>" style="font-weight: bold;" /></p> 
		</form>
		<?php
		$template->footer();    
		break;
	case 'flushlogs':
		if(!$session->get_permissions('clear_logs'))
		{
			die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
		}
		if ( !$session->sid_super )
		{
			redirect(makeUrlNS('Special', "Login/{$paths->page}", 'target_do=flushlogs&level=' . $session->user_level, false), $lang->get('etc_access_denied_short'), $lang->get('etc_access_denied_need_reauth'), 0);
		}
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(isset($_POST['_downthejohn']))
		{
			$template->header();
				$result = PageUtils::flushlogs($paths->page_id, $paths->namespace);
				echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
			$template->footer();
			break;
		}
		$template->header();
			?>
			<form action="<?php echo makeUrl($paths->page, 'do=flushlogs'); ?>" method="post">
 				<?php echo $lang->get('page_flushlogs_warning_stern'); ?>
 				<p><input type="submit" name="_downthejohn" value="<?php echo htmlspecialchars($lang->get('page_flushlogs_btn_submit')); ?>" style="color: red; font-weight: bold;" /></p>
			</form>
			<?php
		$template->footer();
		break;
	case 'delvote':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(isset($_POST['_ballotbox']))
		{
			$template->header();
			$result = PageUtils::delvote($paths->page_id, $paths->namespace);
			echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
			$template->footer();
			break;
		}
		$template->header();
			?>
			<form action="<?php echo makeUrl($paths->page, 'do=delvote'); ?>" method="post">
 				<?php
 					echo $lang->get('page_delvote_warning_stern');
 					echo '<p>';
 					switch($paths->cpage['delvotes'])
 					{
 						case 0:  echo $lang->get('page_delvote_count_zero'); break;
 						case 1:  echo $lang->get('page_delvote_count_one'); break;
 						default: echo $lang->get('page_delvote_count_plural', array('delvotes' => $paths->cpage['delvotes'])); break;
 					}
 					echo '</p>';
 				?>
 				<p><input type="submit" name="_ballotbox" value="<?php echo htmlspecialchars($lang->get('page_delvote_btn_submit')); ?>" /></p>
			</form>
			<?php
		$template->footer();
		break;
	case 'resetvotes':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		if(!$session->get_permissions('vote_reset'))
		{
			die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
		}
		if(isset($_POST['_youmaylivealittlelonger']))
		{
			$template->header();
				$result = PageUtils::resetdelvotes($paths->page_id, $paths->namespace);
				echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
			$template->footer();
			break;
		}
		$template->header();
			?>
			<form action="<?php echo makeUrl($paths->page, 'do=resetvotes'); ?>" method="post">
				<p><?php echo $lang->get('ajax_delvote_reset_confirm'); ?></p>
				<p><input type="submit" name="_youmaylivealittlelonger" value="<?php echo htmlspecialchars($lang->get('page_delvote_reset_btn_submit')); ?>" /></p>
			</form>
			<?php
		$template->footer();
		break;
	case 'deletepage':
		if ( !$session->get_permissions('delete_page') )
		{
			die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
		}
		if ( !$session->sid_super )
		{
			redirect(makeUrlNS('Special', "Login/{$paths->page}", 'target_do=deletepage&level=' . $session->user_level, false), $lang->get('etc_access_denied_short'), $lang->get('etc_access_denied_need_reauth'), 0);
		}
		
		require_once(ENANO_ROOT . '/includes/pageutils.php');
		if ( isset($_POST['_adiossucker']) )
		{
			$reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
			if ( empty($reason) )
				$error = $lang->get('ajax_delete_prompt_reason');
			else
			{
				$template->header();
					$result = PageUtils::deletepage($paths->page_id, $paths->namespace, $reason);
					echo '<p>'.$result.' <a href="'.makeUrl($paths->page).'">' . $lang->get('etc_return_to_page') . '</a>.</p>';
				$template->footer();
				break;
			}
		}
		$template->header();
			?>
			<form action="<?php echo makeUrl($paths->page, 'do=deletepage'); ?>" method="post">
 				<?php echo $lang->get('page_delete_warning_stern'); ?>
 				<?php if ( isset($error) ) echo "<p>$error</p>"; ?>
 				<p><?php echo $lang->get('page_delete_lbl_reason'); ?> <input type="text" name="reason" size="50" /></p>
 				<p><input type="submit" name="_adiossucker" value="<?php echo htmlspecialchars($lang->get('page_delete_btn_submit')); ?>" style="font-weight: bold;" /></p>
			</form>
			<?php
		$template->footer();
		break;
	case 'setwikimode':
		if(!$session->get_permissions('set_wiki_mode'))
		{
			die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
		}
		if ( isset($_POST['finish']) )
		{
			$level = intval($_POST['level']);
			if ( !in_array($level, array(0, 1, 2) ) )
			{
				die_friendly('Invalid request', '<p>Level not specified</p>');
			}
			$q = $db->sql_query('UPDATE '.table_prefix.'pages SET wiki_mode=' . $level . ' WHERE urlname=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $paths->namespace . '\';');
			if ( !$q )
				$db->_die();
			redirect(makeUrl($paths->page), htmlspecialchars($paths->cpage['name']), $lang->get('page_wikimode_success_redirect'), 2);
		}
		else
		{
			$template->header();
			if(!isset($_GET['level']) || ( isset($_GET['level']) && !preg_match('#^([0-9])$#', $_GET['level']))) die_friendly('Invalid request', '<p>Level not specified</p>');
				$level = intval($_GET['level']);
				if ( !in_array($level, array(0, 1, 2) ) )
				{
					die_friendly('Invalid request', '<p>Level not specified</p>');
				}
			echo '<form action="' . makeUrl($paths->page, 'do=setwikimode', true) . '" method="post">';
			echo '<input type="hidden" name="finish" value="foo" />';
			echo '<input type="hidden" name="level" value="' . $level . '" />';
			$level_txt = ( $level == 0 ) ? 'page_wikimode_level_off' : ( ( $level == 1 ) ? 'page_wikimode_level_on' : 'page_wikimode_level_global' );
			$blurb = ( $level == 0 || ( $level == 2 && getConfig('wiki_mode') != '1' ) ) ? 'page_wikimode_blurb_disable' : 'page_wikimode_blurb_enable';
			?>
			<h3><?php echo $lang->get('page_wikimode_heading'); ?></h3>
			<p><?php echo $lang->get($level_txt) . ' ' . $lang->get($blurb); ?></p>
			<p><?php echo $lang->get('page_wikimode_warning'); ?></p>
			<p><input type="submit" value="<?php echo htmlspecialchars($lang->get('page_wikimode_btn_submit')); ?>" /></p>
			<?php
			echo '</form>';
			$template->footer();
		}
		break;
	case 'diff':
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		require_once(ENANO_ROOT.'/includes/diff.php');
		$template->header();
		$id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
		$id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
		if ( !$id1 || !$id2 )
		{
			echo '<p>Invalid request.</p>';
			$template->footer();
			break;
		}
		if ( !ctype_digit($_GET['diff1']) || !ctype_digit($_GET['diff1']) )
		{
			echo '<p>SQL injection attempt</p>';
			$template->footer();
			break;
		}
		echo PageUtils::pagediff($paths->page_id, $paths->namespace, $id1, $id2);
		$template->footer();
		break;
	case 'detag':
		if ( $session->user_level < USER_LEVEL_ADMIN )
		{
			die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
		}
		if ( $paths->page_exists )
		{
			die_friendly($lang->get('etc_invalid_request_short'), '<p>' . $lang->get('page_detag_err_page_exists') . '</p>');
		}
		$q = $db->sql_query('DELETE FROM '.table_prefix.'tags WHERE page_id=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $paths->namespace . '\';');
		if ( !$q )
			$db->_die('Detag query, index.php:'.__LINE__);
		die_friendly($lang->get('page_detag_success_title'), '<p>' . $lang->get('page_detag_success_body') . '</p>');
		break;
	case 'aclmanager':
		if ( !$session->sid_super )
		{
			redirect(makeUrlNS('Special', "Login/{$paths->page}", 'target_do=aclmanager&level=' . $session->user_level, false), $lang->get('etc_access_denied_short'), $lang->get('etc_access_denied_need_reauth'), 0);
		}
		
		require_once(ENANO_ROOT.'/includes/pageutils.php');
		$data = ( isset($_POST['data']) ) ? $_POST['data'] : Array('mode' => 'listgroups');
		PageUtils::aclmanager($data);
		break;
	case 'sql_report':
		$rev_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
		$page = new PageProcessor( $paths->page_id, $paths->namespace, $rev_id );
		$page->send_headers = true;
		$pagepass = ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '';
		$page->password = $pagepass;
		$page->send(true);
		ob_end_clean();
		ob_start();
		$db->sql_report();
		break;
}

// Generate an ETag
/*
// format: first 10 digits of SHA1 of page name, user id in hex, user and auth levels, page timestamp in hex
$etag = substr(sha1($paths->namespace . ':' . $paths->page_id), 0, 10) . '-' .
				"u{$session->user_id}l{$session->user_level}a{$session->auth_level}-" .
				dechex($page_timestamp);
				
if ( isset($_SERVER['HTTP_IF_NONE_MATCH']) )
{
	if ( "\"$etag\"" == $_SERVER['HTTP_IF_NONE_MATCH'] )
	{
		header('HTTP/1.1 304 Not Modified');
		exit();
	}
}
					
header("ETag: \"$etag\"");
*/

$db->close();  
gzip_output();

@ob_end_flush();
	
?>
