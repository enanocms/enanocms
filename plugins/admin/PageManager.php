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

// Page management smart form

function page_Admin_PageManager()
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	global $lang;
	global $cache;
	
	if ( $session->auth_level < USER_LEVEL_ADMIN || $session->user_level < USER_LEVEL_ADMIN )
	{
		$login_link = makeUrlNS('Special', 'Login/' . $paths->nslist['Special'] . 'Administration', 'level=' . USER_LEVEL_ADMIN, true);
		echo '<h3>' . $lang->get('adm_err_not_auth_title') . '</h3>';
		echo '<p>' . $lang->get('adm_err_not_auth_body', array( 'login_link' => $login_link )) . '</p>';
		return;
	}
	
	require_once(ENANO_ROOT . '/includes/pageutils.php');
	
	echo '<h3>' . $lang->get('acppm_heading_main') . '</h3>';
	$show_select = true;
	
	if ( isset($_REQUEST['action']) || isset($_REQUEST['source']) )
	{
		if ( isset($_REQUEST['action']) )
		{
			$act =& $_REQUEST['action'];
			$act = strtolower($act);
		}
		else if ( isset($_REQUEST['source']) && $_REQUEST['source'] == 'ajax' )
		{
			$act = 'select';
		}
		switch ( $act )
		{
			case 'save':
			case 'select':
				// First step is to determine the page ID and namespace
				
				if ( isset($_REQUEST['pid_search']) )
				{
					list($page_id, $namespace) = RenderMan::strToPageID($_REQUEST['page_id']);
					$name = $db->escape(dirtify_page_id($page_id));
					$page_id = $db->escape(sanitize_page_id($page_id));
					$namespace = $db->escape($namespace);
					$name = strtolower($name);
					$page_id = strtolower($page_id);
					$sql = "SELECT * FROM " . table_prefix . "pages WHERE ( " . ENANO_SQLFUNC_LOWERCASE . "(urlname) LIKE '%$page_id%' OR " . ENANO_SQLFUNC_LOWERCASE . "(name) LIKE '%$name%' ) ORDER BY name ASC;";
				}
				else
				{
					// pid_search was not set, assume absolute page ID
					list($page_id, $namespace) = RenderMan::strToPageID($_REQUEST['page_id']);
					$page_id = $db->escape(sanitize_page_id($page_id));
					$namespace = $db->escape($namespace);
					
					$sql = "SELECT * FROM " . table_prefix . "pages WHERE urlname = '$page_id' AND namespace = '$namespace';";
				}
				
				if ( !($q = $db->sql_query($sql)) )
				{
					$db->_die('PageManager selecting dataset for page');
				}
				
				if ( $db->numrows() < 1 )
				{
					echo '<div class="error-box">
									' . $lang->get('acppm_err_page_not_found') . '
								</div>';
					break;
				}
				
				if ( $db->numrows() > 1 )
				{
					// Ambiguous results
					if ( isset($_REQUEST['pid_search']) )
					{
						echo '<h3>' . $lang->get('acppm_msg_results_ambiguous_title') . '</h3>';
						echo '<p>' . $lang->get('acppm_msg_results_ambiguous_body') . '</p>';
						echo '<ul>';
						while ( $row = $db->fetchrow($q) )
						{
							echo '<li>';
							$pathskey = $paths->nslist[$row['namespace']] . $row['urlname'];
							$edit_url = makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageManager&action=select&page_id=$pathskey", true);
							$view_url = makeUrlNS($row['namespace'], $row['urlname']);
							$page_name = htmlspecialchars(get_page_title_ns( $row['urlname'], $row['namespace'] ));
							$view_link = $lang->get('acppm_ambig_btn_viewpage');
							echo "<a href=\"$edit_url\">$page_name</a> (<a onclick=\"window.open(this.href); return false;\" href=\"$view_url\">$view_link</a>)";
							echo '</li>';
						}
						echo '</ul>';
						$show_select = false;
						break;
					}
					else
					{
						echo '<p>' . $lang->get('acppm_err_ambig_absolute') . '</p>';
						break;
					}
				}
				
				// From this point on we can assume that exactly one matching page was found.
				$dataset = $db->fetchrow();
				$page_id = $dataset['urlname'];
				$namespace = $dataset['namespace'];
				
				// This is used to re-determine the page ID after submit.
				$pathskey = $paths->nslist[$namespace] . sanitize_page_id($page_id);
				
				// The extra switch allows us to break out of the save routine if needed
				switch ( $act )
				{
					case 'save':
						
						$errors = array();
						$page_id_changed = false;
						$namespace_changed = false;
						
						// Backup the dataset to avoid redundantly updating values
						$dataset_backup = $dataset;
						
						// We've elected to save the page. The angle of attack here is to validate each form field,
						// and if the field validates successfully, change the value in $dataset accordingly.
						
						// Field: page name
						$page_name = $_POST['page_name'];
						$page_name = trim($page_name);
						if ( empty($page_name) )
						{
							$errors[] = $lang->get('acppm_err_invalid_page_name');
						}
						else
						{
							$dataset['name'] = $page_name;
						}
						
						// Field: page URL string
						$page_urlname = $_POST['page_urlname'];
						$page_urlname = trim($_POST['page_urlname']);
						if ( empty($page_urlname) && !have_blank_urlname_page() )
						{
							$errors[] = $lang->get('acppm_err_invalid_url_string');
						}
						else
						{
							$page_id_changed = ( $_POST['page_urlname'] !== $dataset['urlname'] );
							$dataset['urlname'] = sanitize_page_id($page_urlname);
						}
						
						// Field: namespace
						$namespace_new = $_POST['page_namespace'];
						if ( !isset($paths->nslist[ $namespace ]) )
						{
							$errors[] = $lang->get('acppm_err_invalid_namespace');
						}
						else
						{
							$namespace_changed = ( $_POST['page_namespace'] !== $dataset['namespace'] );
							$dataset['namespace'] = $namespace_new;
						}
						
						// Field: comments enabled
						$dataset['comments_on'] = ( isset($_POST['comments_on']) ) ? 1 : 0;
						
						// Field: page visible
						$dataset['visible'] = ( isset($_POST['visible']) ) ? 1 : 0;
						
						// Field: standalone page
						$dataset['special'] = ( isset($_POST['special']) ) ? 1 : 0;
						
						// Field: page protection
						$protect_level = $_POST['protected'];
						if ( !in_array($protect_level, array('0', '1', '2')) )
						{
							$errors[] = $lang->get('acppm_err_invalid_protection');
						}
						else
						{
							$dataset['protected'] = intval($protect_level);
						}
						
						// Field: wiki mode
						$wiki_mode = $_POST['wikimode'];
						if ( !in_array($wiki_mode, array('0', '1', '2')) )
						{
							$errors[] = $lang->get('acppm_err_invalid_wiki_mode');
						}
						else
						{
							$dataset['wiki_mode'] = intval($wiki_mode);
						}
						
						if ( count($errors) < 1 )
						{
							// We're free of errors. Build a SQL query to update the page table.
							$particles = array();
							
							foreach ( $dataset as $key => $value )
							{
								if ( $value === $dataset_backup[$key] || ( is_int($value) && $value === intval($dataset_backup[$key]) ) )
									continue;
								if ( is_int($value) )
								{
									$particle = "$key = $value";
								}
								else
								{
									$value = $db->escape($value);
									$particle = "$key = '$value'";
								}
								$particles[] = $particle;
								unset($particle);
							}
							
							$page_id_new = $db->escape($dataset['urlname']);
							$namespace_new = $db->escape($dataset['namespace']);
							
							// Only run the update query if at least one field was changed.
							if ( count($particles) > 0 )
							{
								$particles = implode(', ', $particles);
								$page_id_db = $db->escape($page_id);
								$namespace_db = $db->escape($namespace);
								$sql = 'UPDATE ' . table_prefix . "pages SET $particles WHERE urlname = '$page_id_db' AND namespace = '$namespace_db';";
								
								if ( !$db->sql_query($sql) )
									$db->_die('PageManager running primary update query');
								
								// Did we change the page ID or namespace? If so we need to also change logs, comments, tags, etc.
								if ( $page_id_changed || $namespace_changed )
								{
									$sql = array(
											'UPDATE ' . table_prefix . "logs SET page_id = '$page_id_new', namespace = '$namespace_new' WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';",
											'UPDATE ' . table_prefix . "tags SET page_id = '$page_id_new', namespace = '$namespace_new' WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';",
											'UPDATE ' . table_prefix . "comments SET page_id = '$page_id_new', namespace = '$namespace_new' WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';",
											'UPDATE ' . table_prefix . "page_text SET page_id = '$page_id_new', namespace = '$namespace_new' WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';",
											'UPDATE ' . table_prefix . "categories SET page_id = '$page_id_new', namespace = '$namespace_new' WHERE page_id = '$page_id_db' AND namespace = '$namespace_db';"
											'UPDATE ' . table_prefix . "files SET page_id = '$page_id_new', filename = '$page_id_new' WHERE page_id = '$page_id_db';"
										);
									foreach ( $sql as $q )
									{
										if ( !$db->sql_query($q) )
											$db->_die('PageManager running slave update query after page ID/namespace change');
									}
									
									// If we're going File -> other, remove files
									if ( $namespace_db === 'File' && $namespace_new !== 'File' )
									{
										PageUtils::delete_page_files($page_id);
									}
								}
								
								// Did we change the name of the page? If so, make PageProcessor log it
								if ( $dataset_backup['name'] != $dataset['name'] )
								{
									$page = new PageProcessor($page_id_new, $namespace_new);
									$page->rename_page($dataset['name']);
								}
								
								// Finally, clear the metadata cache
								$cache->purge('page_meta');
							}
							
							// Did the user ask to delete the page?
							// I know it's a bit pointless to delete the page only after validating and processing the whole form, but what the heck :)
							if ( isset($_POST['delete']) )
							{
								PageUtils::deletepage($page_id_new, $namespace_new, $lang->get('acppm_delete_reason'));
							}
							
							echo '<div class="info-box">' . $lang->get('acppm_msg_save_success', array( 'viewpage_url' => makeUrlNS($dataset['namespace'], $dataset['urlname']) )) . '</div>';
							break 2;
						}
						
						break;
				}
				$tpl_code = <<<TPLCODE
				<div class="tblholder">
					<table border="0" cellspacing="1" cellpadding="4">
						<tr>
							<th colspan="2">
								{lang:acppm_heading_editing} "{PAGE_NAME}"
							</th>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_page_name}
							</td>
							<td class="row1">
								<input type="text" name="page_name" value="{PAGE_NAME}" size="40" />
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_page_urlname}<br />
								<small>{lang:acppm_lbl_page_urlname_hint}</small>
							</td>
							<td class="row1">
								<input type="text" name="page_urlname" value="{PAGE_URLNAME}" size="40" />
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_namespace}
							</td>
							<td class="row1">
								<select name="page_namespace">
								{NAMESPACE_LIST}</select>
								<!-- BEGIN is_file -->
								<br />
								{lang:acppm_msg_file_ns_warning}
								<!-- END is_file -->
							</td>
						</tr>
						
						<tr>
							<th colspan="2" class="subhead">
								{lang:acppm_heading_advanced}
							</th>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_enable_comments_title}
							</td>
							<td class="row1">
								<label>
									<input type="checkbox" name="comments_on" <!-- BEGIN comments_enabled -->checked="checked" <!-- END comments_enabled -->/>
									{lang:acppm_lbl_enable_comments}
								</label>
								<br />
								<small>{lang:acppm_lbl_enable_comments_hint}</small>
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_special_title}
							</td>
							<td class="row1">
								<label>
									<input type="checkbox" name="special" <!-- BEGIN special -->checked="checked" <!-- END special -->/>
									{lang:acppm_lbl_special}
								</label>
								<br />
								<small>{lang:acppm_lbl_special_hint}</small>
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_visible_title}
							</td>
							<td class="row1">
								<label>
									<input type="checkbox" name="visible" <!-- BEGIN visible -->checked="checked" <!-- END visible -->/>
									{lang:acppm_lbl_visible}
								</label>
								<br />
								<small>{lang:acppm_lbl_visible_hint}</small>
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_protected_title}
							</td>
							<td class="row1">
								<label>
									<input type="radio" name="protected" value="0" <!-- BEGIN protected_off -->checked="checked" <!-- END protected_off -->/>
									{lang:acppm_lbl_protected_off}
								</label>
								<br />
								<label>
									<input type="radio" name="protected" value="1" <!-- BEGIN protected_on -->checked="checked" <!-- END protected_on -->/>
									{lang:acppm_lbl_protected_on}
								</label>
								<br />
								<label>
									<input type="radio" name="protected" value="2" <!-- BEGIN protected_semi -->checked="checked" <!-- END protected_semi -->/>
									{lang:acppm_lbl_protected_semi}
								</label>
								<br />
								<small>{lang:acppm_lbl_protected_hint}</small>
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_wikimode_title}
							</td>
							<td class="row1">
								<label>
									<input type="radio" name="wikimode" value="0" <!-- BEGIN wikimode_off -->checked="checked" <!-- END wikimode_off -->/>
									{lang:acppm_lbl_wikimode_off}
								</label>
								<br />
								<label>
									<input type="radio" name="wikimode" value="1" <!-- BEGIN wikimode_on -->checked="checked" <!-- END wikimode_on -->/>
									{lang:acppm_lbl_wikimode_on}
								</label>
								<br />
								<label>
									<input type="radio" name="wikimode" value="2" <!-- BEGIN wikimode_global -->checked="checked" <!-- END wikimode_global -->/>
									{lang:acppm_lbl_wikimode_global}
								</label>
								<br />
								<small>{lang:acppm_lbl_wikimode_hint}</small>
							</td>
						</tr>
						
						<tr>
							<td class="row2">
								{lang:acppm_lbl_delete_title}
							</td>
							<td class="row1">
								<label>
									<input type="checkbox" name="delete" />
									{lang:acppm_lbl_delete}
								</label>
								<br />
								<small>{lang:acppm_lbl_delete_hint}</small>
							</td>
						</tr>
						
						<tr>
							<th colspan="2" class="subhead">
								<button name="action" value="save">
									<b>{lang:etc_save_changes}</b>
								</button>
								<button name="action" value="nil">
									<b>{lang:etc_cancel}</b>
								</button>
							</th>
						</tr>
						
					</table>
				</div>
				
				<input type="hidden" name="page_id" value="{PATHS_KEY}" />
TPLCODE;
				$parser = $template->makeParserText($tpl_code);
				
				$ns_list = '';
				foreach ( $paths->nslist as $ns => $prefix ) 
				{
					// FIXME: Plugins need to specify whether they want Enano's regular PageProcessor
					// to handle these pages, and whether such pages from namespaces created by plugins
					// can be stored in the database or not.
					if ( $ns == 'Special' || $ns == 'Admin' || $ns == 'Anonymous' )
						continue;
					$ns = htmlspecialchars($ns);
					$prefix = htmlspecialchars($prefix);
					if ( empty($prefix) )
						$prefix = $lang->get('acppm_ns_article');
					$sel = ( $dataset['namespace'] == $ns ) ? ' selected="selected"' : '';
					$ns_list .= "  <option value=\"$ns\"$sel>$prefix</option>\n                ";
				}
				
				$parser->assign_vars(array(
						'PAGE_NAME' => htmlspecialchars($dataset['name']),
						'PAGE_URLNAME' => htmlspecialchars($dataset['urlname']),
						'NAMESPACE_LIST' => $ns_list,
						'PATHS_KEY' => $pathskey
					));
				
				$parser->assign_bool(array(
						'comments_enabled' => ( $dataset['comments_on'] == 1 ),
						'special' => ( $dataset['special'] == 1 ),
						'visible' => ( $dataset['visible'] == 1 ),
						'protected_off'   => ( $dataset['protected'] == 0 ),
						'protected_on'    => ( $dataset['protected'] == 1 ),
						'protected_semi'  => ( $dataset['protected'] == 2 ),
						'wikimode_off'    => ( $dataset['wiki_mode'] == 0 ),
						'wikimode_on'     => ( $dataset['wiki_mode'] == 1 ),
						'wikimode_global' => ( $dataset['wiki_mode'] == 2 ),
						'is_file'         => ( $dataset['namespace'] == 'File' )
					));
				
				if ( isset($errors) )
				{
					echo '<div class="error-box">';
					echo $lang->get('acppm_err_header');
					echo '<ul>';
					echo '<li>' . implode('</li><li>', $errors) . '</li>';
					echo '</ul>';
					echo '</div>';
				}
				
				$form_action = makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageManager", true);
				
				echo "<form action=\"$form_action\" method=\"post\">";
				echo $parser->run();
				echo "</form>";
				
				$show_select = false;
				break;
		}
	}
	
	if ( $show_select )
	{
		echo '<p>' . $lang->get('acppm_hint') . '</p>';
		
		// Show the search form
		
		$form_action = makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageManager", true);
		echo "<form action=\"$form_action\" method=\"post\">";
		echo $lang->get('acppm_lbl_field_search') . ' ';
		echo $template->pagename_field('page_id') . ' ';
		echo '<input type="hidden" name="action" value="select" />';
		echo '<input type="submit" name="pid_search" value="' . $lang->get('search_btn_search') . '" />';
		echo "</form>";
		
		// Grab all pages from the database and show a list of pages on the site
		
		echo '<h3>' . $lang->get('acppm_heading_select_page_from_list') . '</h3>';
		echo '<p>' . $lang->get('acppm_hint_select_page_from_list') . '</p>';
		
		$q = $db->sql_query('SELECT COUNT(name) AS num_pages FROM ' . table_prefix . 'pages;');
		if ( !$q )
			$db->_die('PageManager doing initial page count');
		list($num_pages) = $db->fetchrow_num();
		$db->free_result();
		
		$pg_start = ( isset($_GET['offset']) ) ? intval($_GET['offset']) : 0;
		
		$q = $db->sql_query('SELECT urlname, name, namespace, ' . $num_pages . ' AS num_pages, ' . $pg_start . ' AS offset FROM ' . table_prefix . 'pages ORDER BY name ASC;');
		if ( !$q )
			$db->_die('PageManager doing main select query for page list');
		
		// Paginate results
		$html = paginate(
				$q,
				'{urlname}',
				$num_pages,
				makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageManager&offset=%s", false),
				$pg_start,
				99,
				array('urlname' => 'admin_pagemanager_format_listing'),
				'<div class="tblholder" style="height: 300px; clip: rect(0px, auto, auto, 0px); overflow: auto;">
				<table border="0" cellspacing="1" cellpadding="4">',
				'  </table>
 				</div>'
			);
		echo $html;
	}
	
}

function admin_pagemanager_format_listing($_, $row)
{
	global $db, $session, $paths, $template, $plugins; // Common objects
	
	static $cell_count = 0;
	static $td_class = 'row1';
	static $run_count = 0;
	static $num_pages_floor = false;
	if ( !$num_pages_floor )
	{
		$num_pages_floor = $row['num_pages'];
		while ( $num_pages_floor % 99 > 0 )
			$num_pages_floor--;
	}
	$return = '';
	$run_count++;
	
	$last_page = ( $row['offset'] == $num_pages_floor );
	$last_run = ( ( $last_page && $run_count == $row['num_pages'] % 99 ) || $run_count == 99 );
	if ( $cell_count == 0 )
	{
		$return .= "<tr>\n";
	}
	$title = get_page_title_ns($row['urlname'], $row['namespace']);
	$pathskey = $paths->nslist[$row['namespace']] . $row['urlname'];
	if ( isset($row['mode']) && $row['mode'] == 'edit' )
	{
		$url = makeUrlNS($row['namespace'], $row['urlname'], false, true) . '#do:edit';
	}
	else
	{
		$url = makeUrlNS('Special', 'Administration', "module={$paths->nslist['Admin']}PageManager&action=select&page_id=$pathskey", true);
	}
	$url = '<a href="' . $url . '">' . htmlspecialchars($title) . '</a>';
	$return .= '  <td class="' . $td_class . '" style="width: 33%;">' . $url . '</td>' . "\n";
	$cell_count++;
	if ( $cell_count == 3 && !$last_run )
	{
		$cell_count = 0;
		$td_class = ( $td_class == 'row2' ) ? 'row1' : 'row2';
		$return .= "</tr>\n";
	}
	else if ( $last_run )
	{
		while ( $cell_count < 3 )
		{
			$return .= "  <td class=\"{$td_class}\"></td>\n";
			$cell_count++;
		}
		$return .= "</tr>\n";
	}
	return $return;
}

?>
