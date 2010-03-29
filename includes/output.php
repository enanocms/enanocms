<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * output.php - Controls output format, messages of death, that kind of stuff
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Abstract class to define how output handlers should act.
 * @package Enano
 * @subpackage UI
 */

abstract class Output_Base
{
	/**
 	* Page title
 	* @var string
 	*/
	
	public $title = 'Untitled';
	
	/**
 	* To allow scripts to determine whether we are outputting headers or not.
 	* @var bool
 	*/
	
	public $naked = false;
	
	/**
 	* Added content
 	* @var string
 	* @var string
 	* @var string
 	* @var string
 	*/
	
	public $before_header = '', $after_header = '', $before_footer = '', $after_footer = '';
	
	/**
 	* Call this to send content headers (e.g. the first third of the document if HTML) in place of $template->header().
 	* @access public
 	*/
	
	abstract public function header();
	
	/**
 	* Call this to send extra stuff after the content (equivalent of $template->footer()).
 	* @access public
 	*/
	
	abstract public function footer();
	
	/**
 	* Add some code just before the header.
 	* @access public
 	*/
	
	public function add_before_header($code)
	{
		$this->before_header .= $code;
	}
	
	/**
 	* Add some code just after the header.
 	* @access public
 	*/
	
	public function add_after_header($code)
	{
		$this->after_header .= $code;
	}
	
	/**
 	* Add some code just before the footer.
 	* @access public
 	*/
	
	public function add_before_footer($code)
	{
		$this->before_footer .= $code;
	}
	
	/**
 	* Add some code just after the footer.
 	* @access public
 	*/
	
	public function add_after_footer($code)
	{
		$this->after_footer .= $code;
	}
	
	/**
 	* Send any required HTML headers through, e.g. Content-type.
 	* @access public
 	*/
	
	public function http_headers()
	{
		header('Content-type: text/html');
	}
	
	/**
 	* Set the title of the page being output.
 	* @param string Page name
 	*/
	
	public function set_title($title)
	{
		$this->title = $title;
	}
	
	/**
 	* Avoid sending things out of order.
 	* @var bool
 	* @var bool
 	*/
	
	public $headers_sent = false, $footers_sent = false;
}

/**
 * HTML outputter.
 */

class Output_HTML extends Output_Base
{
	public function header()
	{
		if ( $this->headers_sent )
			return;
		
		$this->headers_sent = true;
		
		ob_start();
	}
	
	public function footer()
	{
		global $template;
		if ( !$this->headers_sent )
			return;
		
		$this->headers_sent = false;
		$content = ob_get_contents();
		ob_end_clean();
		
		ob_start();
		echo $this->before_header;
		echo $template->getHeader();
		echo $this->after_header;
		echo $content;
		echo $this->before_footer;
		echo $template->getFooter();
		echo $this->after_footer;
		
		global $aggressive_optimize_html;
		if ( $aggressive_optimize_html )
		{
			$content = ob_get_contents();
			ob_end_clean();
			
			ob_start();
			echo aggressive_optimize_html($content);
		}
		else
		{
			$content = ob_get_contents();
			ob_end_clean();
			
			ob_start();
			echo preg_replace('~</?enano:no-opt>~', '', $content);
		}
		
	}
	
	public function set_title($title)
	{
		global $template;
		$template->assign_vars(array(
				'PAGE_NAME' => htmlspecialchars($title)
			));
	}
}

/**
 * Same as HTML, except uses simple-header and simple-footer.
 */

class Output_HTML_Simple extends Output_HTML
{
	public function footer()
	{
		global $template;
		if ( !$this->headers_sent )
			return;
		
		$this->headers_sent = false;
		$content = ob_get_contents();
		ob_end_clean();
		
		ob_start();
		echo $this->before_header;
		echo $template->getHeader(true);
		echo $this->after_header;
		echo $content;
		echo $this->before_footer;
		echo $template->getFooter(true);
		echo $this->after_footer;
		
		global $aggressive_optimize_html;
		if ( $aggressive_optimize_html )
		{
			$content = ob_get_contents();
			ob_end_clean();
			
			ob_start();
			echo aggressive_optimize_html($content);
		}
		else
		{
			$content = ob_get_contents();
			ob_end_clean();
			
			ob_start();
			echo preg_replace('~</?enano:no-opt>~', '', $content);
		}
	}
}

/**
 * Outputter that bypasses $template->header() and $template->footer(), but still shows HTML added via {before,after}_{header,footer}.
 */

class Output_Striptease extends Output_HTML
{
	public function header()
	{
		echo $this->before_header;
		echo $this->after_header;
	}
	
	public function footer()
	{
		echo $this->before_footer;
		echo $this->after_footer;
	}
}

/**
 * Outputter that bypasses $template->header() and $template->footer().
 */

class Output_Naked extends Output_HTML
{
	public $naked = true;
	
	public function header()
	{
	}
	
	public function footer()
	{
	}
}

/**
 * Safe template outputter
 */

class Output_Safe
{
	protected $template;
	protected $headers_sent = false;
	public function __construct()
	{
		$this->template = new template_nodb();
		$theme = ( defined('ENANO_CONFIG_FETCHED') ) ? getConfig('theme_default') : 'oxygen';
		$style = ( defined('ENANO_CONFIG_FETCHED') ) ? '__foo__' : 'bleu';
		
		$this->template->load_theme($theme, $style);
		$this->template->tpl_strings['SITE_NAME'] = getConfig('site_name');
		$this->template->tpl_strings['SITE_DESC'] = getConfig('site_desc');
		$this->template->tpl_strings['COPYRIGHT'] = getConfig('copyright_notice');
		$this->template->tpl_strings['PAGE_NAME'] = 'Untitled';
	}
	public function header()
	{
		if ( $this->headers_sent )
			return;
		
		$this->headers_sent = true;
		
		$this->template->header();
	}
	
	public function footer()
	{
		global $template;
		if ( !$this->headers_sent )
		{
			$this->template->header();
		}
		
		$this->headers_sent = false;
		$this->template->footer();
		
	}
	
	public function set_title($title)
	{
		$this->template->tpl_strings['PAGE_NAME'] = $title;
	}
}

?>
