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

class Namespace_File extends Namespace_Default
{
	function send()
	{
		global $output;
		
		$output->add_before_footer($this->show_info());
		$output->add_before_footer($this->display_categories());
		
		if ( $this->exists )
		{
			$this->send_from_db();
		}
		else
		{
			$output->header();
			$this->error_404();
			$output->footer();
		}
	}
	
	function show_info()
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		global $lang;
		
		require_once(ENANO_ROOT . '/includes/log.php');
		
		$local_page_id = $this->page_id;
		$local_namespace = $this->namespace;
		$html = '';
		
		// Prevent unnecessary work
		if ( $local_namespace != 'File' )
			return null;
		
		$selfn = $db->escape($this->page_id);
		$q = $db->sql_query('SELECT f.mimetype,f.time_id,f.size,l.log_id FROM ' . table_prefix . "files AS f\n"
											. "  LEFT JOIN " . table_prefix . "logs AS l\n"
											. "    ON ( l.time_id = f.time_id AND ( l.action = 'reupload' OR l.action IS NULL ) )\n"
											. "  WHERE f.page_id = '$selfn'\n"
											. "    ORDER BY f.time_id DESC;");
		if ( !$q )
		{
			$db->_die('The file type could not be fetched.');
		}
		
		if ( $db->numrows() < 1 )
		{
			$html .= '<div class="mdg-comment" style="margin-left: 0;">
							<h3>' . $lang->get('onpage_filebox_heading') . '</h3>
							<p>' . $lang->get('onpage_filebox_msg_not_found', array('upload_link' => makeUrlNS('Special', 'UploadFile/'.$local_page_id))) . '</p>
						</div>
						<br />';
			return $html;
		}
		$r = $db->fetchrow();
		$mimetype = $r['mimetype'];
		$datestring = enano_date(ED_DATE | ED_TIME, (int)$r['time_id']);
		$html .= '<div class="mdg-comment" style="margin-left: 0;">
						<h3>' . $lang->get('onpage_filebox_heading') . '</h3>
						<p>' . $lang->get('onpage_filebox_lbl_type') . ' '.$r['mimetype'].'<br />';
		
		$size = $r['size'] . ' ' . $lang->get('etc_unit_bytes');
		if ( $r['size'] >= 1048576 )
		{
			$size .= ' (' . ( round($r['size'] / 1048576, 1) ) . ' ' . $lang->get('etc_unit_megabytes_short') . ')';
		}
		else if ( $r['size'] >= 1024 )
		{
			$size .= ' (' . ( round($r['size'] / 1024, 1) ) . ' ' . $lang->get('etc_unit_kilobytes_short') . ')';
		}
		
		$html .= $lang->get('onpage_filebox_lbl_size', array('size' => $size));
		
		$html .= '<br />' . $lang->get('onpage_filebox_lbl_uploaded') . ' ' . $datestring . '</p>';
		// are we dealing with an image?
		$is_image = substr($mimetype, 0, 6) == 'image/';
		
		// for anything other than plain text and 
		if ( !$is_image && ( substr($mimetype, 0, 5) != 'text/' || $mimetype == 'text/html' || $mimetype == 'text/javascript' ) )
		{
			$html .= '<div class="warning-box">
							' . $lang->get('onpage_filebox_msg_virus_warning') . '
						</div>';
		}
		if ( $is_image )
		{
			// show a thumbnail of the image
			$html .= '<p>
							<a href="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn).'">
								<img style="border: 0;" alt="' . htmlspecialchars($paths->page) . '" src="' . makeUrlNS('Special', "DownloadFile/$selfn/{$r['time_id']}", 'preview', true) . '" />
							</a>
						</p>';
		}
		$html .= '<p>
						<a href="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn.'/'.$r['time_id'].htmlspecialchars(urlSeparator).'download').'">
							' . $lang->get('onpage_filebox_btn_download') . '
						</a>';
		// allow reupload if:
		//   * we are allowed to upload new versions, and
		//      - the file is unprotected, or
		//      - we have permission to override protection
		
		if ( !$this->perms )
			$this->perms = $session->fetch_page_acl($this->page_id, $this->namespace);
		
		if ( $this->perms->get_permissions('upload_new_version') && ( !$this->page_protected || $this->perms->get_permissions('even_when_protected') ) )
		{
			// upload new version link
			$html .= '  |  <a href="'.makeUrlNS('Special', "UploadFile/$selfn", false, true).'">
							' . $lang->get('onpage_filebox_btn_upload_new') . '
						</a>';
		}
		// close off paragraph
		$html .= '</p>';
		// only show this if there's more than one revision
		if ( $db->numrows() > 1 )
		{
			// requery, sql_result_seek() doesn't work on postgres
			$db->free_result();
			$q = $db->sql_query('SELECT f.mimetype,f.time_id,f.size,l.log_id FROM ' . table_prefix . "files AS f\n"
											. "  LEFT JOIN " . table_prefix . "logs AS l\n"
											. "    ON ( l.time_id = f.time_id AND ( l.action = 'reupload' OR l.action IS NULL ) )\n"
											. "  WHERE f.page_id = '$selfn'\n"
											. "    ORDER BY f.time_id DESC;");
			if ( !$q )
				$db->_die();
			
			$log = new LogDisplay();
			$log->add_criterion('page', $paths->nslist['File'] . $this->page_id);
			$log->add_criterion('action', 'reupload');
			$data = $log->get_data();
			$i = -1;
			
			$html .= '<h3>' . $lang->get('onpage_filebox_heading_history') . '</h3><p>';
			$last_rollback_id = false;
			$download_flag = $is_image ? false : 'download';
			while ( $r = $db->fetchrow($q) )
			{
				$html .= '(<a href="'.makeUrlNS('Special', "DownloadFile/$selfn/{$r['time_id']}", $download_flag, true).'">' . $lang->get('onpage_filebox_btn_this_version') . '</a>) ';
				if ( $session->get_permissions('history_rollback') && $last_rollback_id )
					$html .= ' (<a href="#rollback:' . $last_rollback_id . '" onclick="ajaxRollback(\''.$last_rollback_id.'\'); return false;">' . $lang->get('onpage_filebox_btn_revert') . '</a>) ';
				else if ( $session->get_permissions('history_rollback') && !$last_rollback_id )
					$html .= ' (' . $lang->get('onpage_filebox_btn_current') . ') ';
				$last_rollback_id = $r['log_id'];
				
				$html .= $r['mimetype'].', ';
				
				$fs = $r['size'];
				$fs = (int)$fs;
				
				if($fs >= 1048576)
				{
					$fs = round($fs / 1048576, 1);
					$size = $fs . ' ' . $lang->get('etc_unit_megabytes_short');
				}
				else
				if ( $fs >= 1024 )
				{
					$fs = round($fs / 1024, 1);
					$size = $fs . ' ' . $lang->get('etc_unit_kilobytes_short');
				}
				else
				{
					$size = $fs . ' ' . $lang->get('etc_unit_bytes');
				}
				
				$html .= $size;
				if ( isset($data[++$i]) )
					$html .= ': ' . LogDisplay::render_row($data[$i], false, false);
				
				$html .= '<br />';
			}
			$html .= '</p>';
		}
		$db->free_result();
		$html .= '<h3>' . $lang->get('onpage_filebox_lbl_pagesusing') . '</h3>';
		$regexp = ENANO_DBLAYER == 'PGSQL' ? '~ E' : 'REGEXP ';
		$q = $db->sql_query('SELECT t.page_id, t.namespace, p.name FROM ' . table_prefix . "page_text AS t\n"
			              . "  LEFT JOIN " . table_prefix . "pages AS p\n"
			              . "    ON ( t.page_id = p.urlname AND t.namespace = p.namespace )\n"
			              . "  WHERE t.page_text {$regexp}'\\\\[\\\\[:" .
							  addslashes(preg_quote($paths->nslist[$this->namespace])) .
							  addslashes(preg_quote($this->page_id)) .
							  "(\\\\||\\\\])';");
		if ( !$q )
			$db->_die();
		
		if ( $db->numrows() < 1 )
		{
			$html .= '<p>' . $lang->get('onpage_filebox_msg_no_inlinks') . '</p>';
		}
		else
		{
			$html .= '<p>' . $lang->get('onpage_filebox_msg_pagesusing') . '</p>';
			$html .= '<ul>';
			while ( $row = $db->fetchrow() )
			{
				$html .= '<li><a href="' . makeUrlNS($row['namespace'], $row['page_id']) . '">' .
							htmlspecialchars($row['name']) .
							'</a></li>';
			}
			$html .= '</ul>';
		}
		$db->free_result();
		$html .= '</div><br />';
		return $html;
	}
	
	/**
 	* Delete a file from the database and filesystem based on file ID.
 	* @param int File ID
 	* @return null
 	*/
	
	public static function delete_file($file_id)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		
		if ( !is_int($file_id) )
			// seriously?
			return null;
		
		// pull file info
		$q = $db->sql_query('SELECT filename, page_id, time_id, file_extension, file_key FROM ' . table_prefix . "files WHERE file_id = $file_id;");
		if ( !$q )
			$db->_die();
		
		if ( $db->numrows() < 1 )
		{
			$db->free_result();
			return null;
		}
		
		$row = $db->fetchrow();
		$db->free_result();
		
		// make sure the image isn't used by multiple revisions
		$q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "files WHERE file_key = '{$row['file_key']}';");
		if ( !$q )
			$db->_die();
		if ( $db->numrows() < 1 )
		{
			// remove from filesystem
			$file_path = ENANO_ROOT . "/files/{$row['file_key']}{$row['file_extension']}";
			@unlink($file_path);
			// old filename standard
			$file_path = ENANO_ROOT . "/files/{$row['file_key']}-{$row['time_id']}{$row['file_extension']}";
			@unlink($file_path);
		}
		$db->free_result();
		
		// remove from cache
		if ( $dp = @opendir(ENANO_ROOT . '/cache/') )
		{
			$regexp = '#' . preg_quote($row['filename']) . '-' . $row['time_id'] . '-[0-9]+x[0-9]+' . preg_quote($row['file_extension']) . '#';
			while ( $dh = @readdir($dp) )
			{
				if ( preg_match($regexp, $dh) )
				{
					// it's a match, delete the cached thumbnail
					@unlink(ENANO_ROOT . "/cache/$dh");
				}
			}
			closedir($dp);
		}
		
		// remove from database
		$q = $db->sql_query('DELETE FROM ' . table_prefix . "files WHERE file_id = $file_id;");
		if ( !$q )
			$db->_die();
		
		// remove from logs
		$page_id_db = $db->escape($row['page_id']);
		$q = $db->sql_query('DELETE FROM ' . table_prefix . "logs WHERE page_id = '{$page_id_db}' AND namespace = 'File' AND action = 'reupload' AND time_id = {$row['time_id']};");
		if ( !$q )
			$db->_die();
		
		return true;
	}
}

