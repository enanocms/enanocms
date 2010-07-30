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

class Namespace_Special extends Namespace_Default
{
	public function __construct($page_id, $namespace, $revision_id = 0)
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		
		$this->page_id = sanitize_page_id($page_id);
		$this->namespace = $namespace;
		$this->build_cdata();
		$this->revision_id = intval($revision_id);
		
		$this->page_protected = true;
		$this->wiki_mode = 0;
	}
	
	public function build_cdata()
	{
		global $db, $session, $paths, $template, $plugins; // Common objects
		global $lang;
		
		if ( strstr($this->page_id, '/') )
			list($base_page_id) = explode('/', $this->page_id);
		else
			$base_page_id = $this->page_id;
		$this->exists = function_exists("page_{$this->namespace}_{$base_page_id}");
		
		if ( isset($paths->pages[ $paths->get_pathskey($this->page_id, $this->namespace) ]) )
		{
			$page_name = $paths->pages[ $paths->get_pathskey($this->page_id, $this->namespace) ]['name'];
		}
		else
		{
			$page_name = "{$paths->nslist[ $this->namespace ]}{$this->page_id}";
			if ( ($_ = $lang->get('specialpage_' . strtolower($this->page_id))) !== 'specialpage_' . strtolower($this->page_id) )
			{
				$page_name = $_;
			}
		}
		
		$this->cdata = array(
				'name' => $lang->get($page_name),
				'urlname' => $this->page_id,
				'namespace' => $this->namespace,
				'special' => 0,
				'visible' => 0,
				'comments_on' => 0,
				'protected' => 0,
				'delvotes' => 0,
				'delvote_ips' => '',
				'wiki_mode' => 2,
				'page_exists' => $this->exists,
				'page_format' => getConfig('default_page_format', 'wikitext')
			);
		$this->cdata = Namespace_Default::bake_cdata($this->cdata);
		
		$this->title =& $this->cdata['name'];
	}
	
	function send()
	{
		global $output;
		
		if ( $this->exists )
		{
			call_user_func("page_{$this->namespace}_{$this->page_id}");
		}
		else
		{
			$output->header();
			$this->error_404();
			$output->footer();
		}
	}
	
	// We add the unused variable $userpage here to silence "declaration should be compatible" errors
	function error_404()
	{
		global $lang, $output;
		$func_name = "page_{$this->namespace}_{$this->page_id}";
		
		if ( $this->namespace == 'Admin' )
			die_semicritical($lang->get('page_msg_admin_404_title'), $lang->get('page_msg_admin_404_body', array('func_name' => $func_name)), true);
		
		$title = $lang->get('page_err_custompage_function_missing_title');
		$message = $lang->get('page_err_custompage_function_missing_body', array( 'function_name' => $func_name ));
		
		$output->set_title($title);
		$output->header();
		echo "<p>$message</p>";
		$output->footer();
	}
	
	function set_conds()
	{
		parent::set_conds();
		
		$this->conds['printable'] = false;
		$this->conds['adminpage'] = false;
	}
}
