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
 	* PathManager info array ("cdata") for this page. (The one with urlname, name, namespace, delvotes, delvote_ips, protected, visible, etc.)
 	* @var array
 	*/
	
	public $cdata = array();
	
	/**
 	* ACL calculation instance for this page.
 	* @var object(Session_ACLPageInfo)
 	*/
	
	public $perms = false;
	
	/**
 	* Protection calculation
 	* @var bool
 	*/
	
	public $page_protected = false;
	
	/**
 	* Wiki mode calculation
 	* @var bool
 	*/
	
	public $wiki_mode = false;
	
	/**
 	* Page conditions. These represent the final decision as to whether an action is allowed or not. They are set to true if ACLs permit AND if
 	* the action "makes sense." (e.g., you can't vote to delete a non-wikimode page.)
 	* @var array
 	*/
	
	public $conds = array();
	
	/**
 	* Constructor.
 	*/
	
	public function __construct($page_id, $namespace, $revision_id = 0)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		
		$this->page_id = sanitize_page_id($page_id);
		$this->namespace = $namespace;
		$this->revision_id = intval($revision_id);
		
		// grab the cdata
		$this->build_cdata();
		
		$this->page_protected = $this->cdata['really_protected'] ? true : false;
		switch($this->cdata['wiki_mode'])
		{
			case 0: $this->wiki_mode = false; break;
			case 1: $this->wiki_mode = true; break;
			default: case 2: $this->wiki_mode = getConfig('wiki_mode') == 1; break;
		}
	}
	
	/**
 	* Build the page's cdata.
 	*/
	
	public function build_cdata()
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		static $cdata_cache = array();
		$pathskey = $paths->get_pathskey($this->page_id, $this->namespace);
		if ( isset($cdata_cache[$pathskey]) )
		{
			$this->cdata = $cdata_cache[$pathskey];
			$this->exists = $cdata_cache[$pathskey]['page_exists'];
			$this->title = $cdata_cache[$pathskey]['name'];
			return null;
		}
		
		$this->exists = false;
		$ns_char = substr($paths->nslist['Special'], -1);
		$page_name = $this->namespace == 'Article' ? dirtify_page_id($this->page_id) : "{$this->namespace}{$ns_char}" . dirtify_page_id($this->page_id);
		$page_name = str_replace('_', ' ', $page_name);
		$this->title = $page_name;
		
		$this->cdata = array(
			'name' => $page_name,
			'urlname' => $this->page_id,
			'namespace' => $this->namespace,
			'special' => 0,
			'visible' => 0,
			'comments_on' => 1,
			'protected' => 0,
			'delvotes' => 0,
			'delvote_ips' => '',
			'wiki_mode' => 2,
			'page_exists' => false,
			'page_format' => getConfig('default_page_format', 'wikitext')
		);
		
		if ( $data_from_db = Namespace_Default::get_cdata_from_db($this->page_id, $this->namespace) )
		{
			$this->exists = true;
			$this->cdata = $data_from_db;
			$this->cdata['page_exists'] = true;
			$this->title = $this->cdata['name'];
		}
				
		$this->cdata = Namespace_Default::bake_cdata($this->cdata);
		
		$cdata_cache[$pathskey] = $this->cdata;
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
 	* Get a redirect, if there is one.
 	* @return mixed Array: Page ID and namespace, associative; bool: false (no redirect)
 	*/
	
	public function get_redirect()
	{
		$text = $this->fetch_text();
		if ( preg_match('/^#redirect \[\[([^\]]+?)\]\]/i', $text, $match ) )
		{
			list($page_id, $namespace) = RenderMan::strToPageID($match[1]);
			return array(
					'page_id' => $page_id,
					'namespace' => $namespace
				);
		}
		return false;
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
		
		profiler_log("Namespace [$this->namespace, $this->page_id]: pulled text from DB");
		
		$text = preg_replace('/([\s]*)__NOBREADCRUMBS__([\s]*)/', '', $text);
		$text = preg_replace('/([\s]*)__NOTOC__([\s]*)/', '', $text);
		$text = preg_replace('/^#redirect \[\[.+?\]\]\s*/i', '', $text);
		
		if ( $send_headers )
		{
			$output->set_title($this->title);
			$output->header();
		}
		$this->do_breadcrumbs();
		
		if ( $incl_inner_headers )
		{
			if ( !$this->perms )
				$this->perms = $session->fetch_page_acl($this->page_id, $this->namespace);
			
			if ( $this->perms->get_permissions('vote_reset') && $this->cdata['delvotes'] > 0)
			{
				$delvote_ips = unserialize($this->cdata['delvote_ips']);
				$hr = htmlspecialchars(implode(', ', $delvote_ips['u']));
				
				$string_id = ( $this->cdata['delvotes'] == 1 ) ? 'delvote_lbl_votes_one' : 'delvote_lbl_votes_plural';
				$string = $lang->get($string_id, array('num_users' => $this->cdata['delvotes']));
				
				echo '<div class="info-box" style="margin-left: 0; margin-top: 5px;" id="mdgDeleteVoteNoticeBox">
								<b>' . $lang->get('etc_lbl_notice') . '</b> ' . $string . '<br />
								<b>' . $lang->get('delvote_lbl_users_that_voted') . '</b> ' . $hr . '<br />
								<a href="'.makeUrl($paths->page, 'do=deletepage').'" onclick="ajaxDeletePage(); return false;">' . $lang->get('delvote_btn_deletepage') . '</a>  |  <a href="'.makeUrl($paths->page, 'do=resetvotes').'" onclick="ajaxResetDelVotes(); return false;">' . $lang->get('delvote_btn_resetvotes') . '</a>
							</div>';
			}
		}
		
		if ( $this->revision_id )
		{
			echo '<div class="info-box" style="margin-left: 0; margin-top: 5px;">
							<b>' . $lang->get('page_msg_archived_title') . '</b><br />
							' . $lang->get('page_msg_archived_body', array(
									'archive_date' => enano_date(ED_DATE, $this->revision_time),
									'archive_time' => enano_date(ED_TIME, $this->revision_time),
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
			$page_format = $this->cdata['page_format'];
		}
		
		$code = $plugins->setHook('pageprocess_render_head');
		foreach ( $code as $cmd )
		{
			eval($cmd);
		}
		
		$prof_contentevent = profiler_log("Namespace [$this->namespace, $this->page_id]: headers and preprocessing done - about to send content");
		
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
				$text = '?>' . RenderMan::render($text, RENDER_INLINE);
			}
		}
		else
		{
			$text = '?>' . $text;
			$text = preg_replace('/<nowiki>(.*?)<\/nowiki>/s', '\\1', $text);
		}
		
		eval ( $text );
		
		profiler_log("Namespace [$this->namespace, $this->page_id]: content sent", true, $prof_contentevent);
		
		$code = $plugins->setHook('pageprocess_render_tail');
		foreach ( $code as $cmd )
		{
			eval($cmd);
		}
		
		if ( $incl_inner_headers )
		{
			display_page_footers();
		}
		
		profiler_log("Namespace [$this->namespace, $this->page_id]: sent footers");
		
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
	
	public function error_404()
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		global $lang, $output;
		
		$userpage = $this->namespace == 'User';
		
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
									'delete_time' => enano_date(ED_DATE | ED_TIME, $r['time_id']),
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
			while ( $row = $db->fetchrow($q) )
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
			
			$q = $db->sql_query('SELECT category_id FROM ' . table_prefix . "categories WHERE page_id = '$this->page_id' AND namespace = '$this->namespace';");
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
				while ( $row = $db->fetchrow($q) );
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
 	* Pull in switches as to whether a specific toolbar button should be used or not. This sets things up according to the current page being displayed.
 	* @return array Associative
 	*/
	
	function set_conds()
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		
		if ( !$this->perms )
			$this->perms = $session->fetch_page_acl($this->page_id, $this->namespace);
		
		if ( !$this->perms )
		{
			// We're trying to send a page WAY too early (session hasn't been started yet), such as for a redirect. Send a default set of conds because
			// there's NO way to get permissions to determine anything otherwise. Yes, starting $session here might be dangerous.
			$this->conds = array(
					'article' => true,
					'comments' => false,
					'edit' => false,
					'viewsource' => false,
					'history' => false,
					'rename' => false,
					'delvote' => false,
					'resetvotes' => false,
					'delete' => false,
					'printable' => false,
					'protect' => false,
					'setwikimode' => false,
					'clearlogs' => false,
					'password' => false,
					'acledit' => false,
					'adminpage' => false
				);
			return $this->conds;
		}
		
		// die('have perms: <pre>' . print_r($this->perms, true) . "\n---------------------------------\nBacktrace:\n" . enano_debug_print_backtrace(true));
		
		$enforce_protection = ( $this->page_protected && ( ( $session->check_acl_scope('even_when_protected', $this->namespace) && !$this->perms->get_permissions('even_when_protected') ) || !$session->check_acl_scope('even_when_protected', $this->namespace) ) );
		
		$conds = array();
		
		// Article: always show
		$conds['article'] = true;
		
		// Discussion: Show if comments are enabled on the site, and if comments are on for this page.
		$conds['comments'] = $this->perms->get_permissions('read') && getConfig('enable_comments', '1')=='1' && $this->cdata['comments_on'] == 1;
		
		// Edit: Show if we have permission to edit the page, and if we don't have protection in effect
		$conds['edit'] = $this->perms->get_permissions('read') && $session->check_acl_scope('edit_page', $this->namespace) && $this->perms->get_permissions('edit_page') && !$enforce_protection;
		
		// View source: Show if we have permission to view source and either ACLs prohibit editing or protection is in effect
		$conds['viewsource'] = $session->check_acl_scope('view_source', $this->namespace) && $this->perms->get_permissions('view_source') && ( !$this->perms->get_permissions('edit_page') || $enforce_protection ) && $this->namespace != 'API';
		
		// History: Show if we have permission to see history and if the page exists
		$conds['history'] = $session->check_acl_scope('history_view', $this->namespace) && $this->exists && $this->perms->get_permissions('history_view');
		
		// Rename: Show if the page exists, if we have permission to rename, and if protection isn't in effect
		$conds['rename'] = $session->check_acl_scope('rename', $this->namespace) && $this->exists && $this->perms->get_permissions('rename') && !$enforce_protection;
		
		// Vote-to-delete: Show if we have Wiki Mode on, if we have permission to vote for deletion, and if the page exists (can't vote to delete a nonexistent page)
		$conds['delvote'] = $this->wiki_mode && $session->check_acl_scope('vote_delete', $this->namespace) && $this->perms->get_permissions('vote_delete') && $this->exists;
		
		// Reset votes: Show if we have Wiki Mode on, if we have permission to reset votes, if the page exists, and if there's at least one vote
		$conds['resetvotes'] = $session->check_acl_scope('vote_reset', $this->namespace) && $this->wiki_mode && $this->exists && $this->perms->get_permissions('vote_reset') && $this->cdata['delvotes'] > 0;
		
		// Delete page: Show if the page exists and if we have permission to delete it
		$conds['delete'] = $session->check_acl_scope('delete_page', $this->namespace) && $this->exists && $this->perms->get_permissions('delete_page');
		
		// Printable view: Show if the page exists
		$conds['printable'] = $this->exists;
		
		// Protect: Show if we have Wiki Mode on, if the page exists, and if we have permission to protect the page.
		$conds['protect'] = $session->check_acl_scope('protect', $this->namespace) && $this->wiki_mode && $this->exists && $this->perms->get_permissions('protect');
		
		// Set Wiki Mode: Show if the page exists and if we have permission to set wiki mode
		$conds['setwikimode'] = $session->check_acl_scope('set_wiki_mode', $this->namespace) && $this->exists && $this->perms->get_permissions('set_wiki_mode');
		
		// Clear logs: Show if we have permission to clear logs
		$conds['clearlogs'] = $session->check_acl_scope('clear_logs', $this->namespace) && $this->perms->get_permissions('clear_logs');
		
		// Set password: a little bit complicated. If there's a password, check for password_reset; else, check for password_set.
		$conds['password'] = empty($this->cdata['password']) ?
 													$session->check_acl_scope('password_set', $this->namespace) && $this->perms->get_permissions('password_set') :
 													$session->check_acl_scope('password_reset', $this->namespace) && $this->perms->get_permissions('password_reset');
		
		// Edit ACLs: Show if this is a non-Enano page that's calling the Enano API and (a) if we have permissions to edit ACLs or (b) we're an admin AND ACL_ALWAYS_ALLOW_ADMIN_EDIT_ACL is on
		$conds['acledit'] = $this->namespace != 'API' && $session->check_acl_scope('edit_acl', $this->namespace) && ( $this->perms->get_permissions('edit_acl') || ( defined('ACL_ALWAYS_ALLOW_ADMIN_EDIT_ACL') &&  $session->user_level >= USER_LEVEL_ADMIN ) );
		
		// Admin page: Show if the page exists and if we're an admin
		$conds['adminpage'] = $session->user_level >= USER_LEVEL_ADMIN && $this->exists;
		
		// Allow plugins to change stuff
		$code = $plugins->setHook('page_conds_set');
		foreach ( $code as $cmd )
		{
			eval($cmd);
		}
		
		$this->conds = $conds;
	}
	
	/**
 	* Return page conditions
 	* @return array
 	*/
	
	public function get_conds()
	{
		if ( empty($this->conds) )
			$this->set_conds();
		
		return $this->conds;
	}
	
	/**
 	* Just tell us if the current page exists or not.
 	* @return bool
 	*/
 	
	public function exists()
	{
		return $this->exists;
	}
	
	/**
 	* Return cdata
 	* @return array
 	*/
	
	public function get_cdata()
	{
		return $this->cdata;
	}
	
	/**
 	* Bake, or finalize the processing of, a cdata array.
 	* @static
 	* @access public
 	*/
	
	public static function bake_cdata($cdata)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		
		// urlname_nons is the actual page_id.
		$cdata['urlname_nons'] = $cdata['urlname'];
		if ( isset($paths->nslist[ $cdata['namespace'] ]) )
		{
			$cdata['urlname'] = $paths->nslist[ $cdata['namespace'] ] . $cdata['urlname'];
		}
		else
		{
			$ns_char = substr($paths->nslist['Special'], -1);
			$cdata['urlname'] = $cdata['namespace'] . $ns_char . $cdata['urlname'];
		}
		
		// add missing keys
		$defaults = array(
			'special' => 0,
			'visible' => 0,
			'comments_on' => 1,
			'protected' => 0,
			'delvotes' => 0,
			'delvote_ips' => serialize(array()),
			'wiki_mode' => 2,
			'page_format' => getConfig('default_page_format', 'wikitext')
		);
		foreach ( $defaults as $key => $value )
		{
			if ( !isset($cdata[$key]) )
				$cdata[$key] = $value;
		}
		
		// fix up deletion votes
		if ( empty($cdata['delvotes']) )
			$cdata['delvotes'] = 0;
		
		// fix up deletion vote IP list
		if ( empty($cdata['delvote_ips']) )
			$cdata['delvote_ips'] = serialize(array());
		
		// calculate wiki mode
		$cdata['really_wiki_mode'] = ( $cdata['wiki_mode'] == 1 || ( $cdata['wiki_mode'] == 2 && getConfig('wiki_mode', 0) == 1 ) );
		
		// calculate protection
		$cdata['really_protected'] = ( $cdata['protected'] > 0 );
		if ( $cdata['protected'] == 2 )
		{
			$cdata['really_protected'] = !$session->user_logged_in || ( $session->user_logged_in && $session->reg_time + 86400*4 > time() );
		}
		
		return $cdata;
	}
	
	/**
 	* Grabs raw (unbaked) cdata from the database, caching if possible.
 	* @param string Page ID
 	* @param string Namespace.
 	* @static
 	*/
	
	public static function get_cdata_from_db($page_id, $namespace)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		static $cache = array();
		
		$pathskey = $paths->get_pathskey($page_id, $namespace);
		if ( isset($cache[$pathskey]) )
			return $cache[$pathskey];
		
		$page_id_db = $db->escape($page_id);
		$namespace_db = $db->escape($namespace);
		
		$q = $db->sql_query('SELECT p.*'
											. '    FROM ' . table_prefix . "pages AS p\n"
											. "  WHERE p.urlname = '$page_id_db' AND p.namespace = '$namespace_db'\n"
											. "    GROUP BY p.urlname, p.name, p.namespace, p.page_order, p.special, p.visible, p.protected, p.wiki_mode, p.comments_on, p.delvotes, p.delvote_ips, p.page_format, p.password;");
		
		if ( !$q )
			$db->_die();
		
		if ( $db->numrows() < 1 )
		{
			$db->free_result();
			$cache[$pathskey] = false;
			return false;
		}
		
		$row = $db->fetchrow();
		
		// Get comment counts
		// FIXME: Apparently there's a bit of recursion in here. Fetching permissions depends on this cdata function.
		// Perhaps we should eliminate session's dependency on cdata? (What is it used for?)
		$q = $db->sql_query('SELECT approved FROM ' . table_prefix . "comments WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';");
		// yay parallel assignment
		$row['comments_approved'] = $row['comments_unapproved'] = $row['comments_spam'] = 0;
		while ( $commentrow = $db->fetchrow() )
			switch($commentrow['approved'])
			{
				case COMMENT_APPROVED:
				default:
					$row['comments_approved']++;
					break;
				case COMMENT_UNAPPROVED:
					$row['comments_unapproved']++;
					break;
				case COMMENT_SPAM:
					$row['comments_spam']++;
					break;
			}
		
		$cache[$pathskey] = $row;
		return $row;
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

class Namespace_Category extends Namespace_Default
{
}

