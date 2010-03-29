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

class Carpenter_Parse_MediaWiki
{
	public $rules = array(
		'bold'   => "/'''(.+?)'''/",
		'italic' => "/''(.+?)''/",
		'underline' => '/__(.+?)__/',
		'externalwithtext' => '#\[((?:https?|irc|ftp)://.+?) (.+?)\]#',
		'externalnotext' => '#\[((?:https?|irc|ftp)://.+?)\]#',
		'mailtonotext' => '#\[mailto:([^ \]]+?)\]#',
		'mailtowithtext' => '#\[mailto:([^ \]]+?) (.+?)\]#',
		'hr' => '/^[-]{4,} *$/m',
		'code' => '/^(?:<code>(?:\r?\n)?|<pre>)(.+?)(?:<\/pre>|(?:\r?\n)?<\/code>)$/mis'
	);
	
	private $blockquote_rand_id;
	
	public function lang(&$text)
	{
		global $lang;
		
		preg_match_all('/<lang (?:code|id)="([a-z0-9_-]+)">([\w\W]+?)<\/lang>/', $text, $langmatch);
		foreach ( $langmatch[0] as $i => $match )
		{
			if ( $langmatch[1][$i] == $lang->lang_code )
			{
				$text = str_replace_once($match, $langmatch[2][$i], $text);
			}
			else
			{
				$text = str_replace_once($match, '', $text);
			}
		}
		
		return array();
	}
	
	public function templates(&$text)
	{
		$template_regex = "/\{\{(.+)((\n|\|[ ]*([A-z0-9]+)[ ]*=[ ]*(.+))*)\}\}/isU";
		$i = 0;
		while ( preg_match($template_regex, $text, $match) )
		{
			$i++;
			if ( $i == 5 )
				break;
			$text = RenderMan::include_templates($text);
		}
		
		return array();
	}
	
	public function heading(&$text)
	{
		if ( !preg_match_all('/^(={1,6}) *(.+?) *\\1 *$/m', $text, $results) )
			return array();
		
		$headings = array();
		foreach ( $results[0] as $i => $match )
		{
			$headings[] = array(
					'level' => strlen($results[1][$i]),
					'text' => $results[2][$i]
				);
		}
		
		$text = Carpenter::tokenize($text, $results[0]);
		
		return $headings;
	}
	
	public function multilist(&$text)
	{
		// Match entire lists
		$regex = '/^
								([:#\*])+     # Initial list delimiter
								[ ]*
								.+?
								(?:
									\r?\n
									(?:\\1|[ ]{2,})
									[ ]*
									.+?)*
								$/mx';
		
		if ( !preg_match_all($regex, $text, $lists) )
			return array();
		
		$types = array(
				'*' => 'unordered',
				'#' => 'ordered',
				':' => 'indent'
			);
		
		$pieces = array();
		foreach ( $lists[0] as $i => $list )
		{
			$token = $lists[1][$i];
			$piece = array(
					'type' => $types[$token],
					'items' => array()
				);
			
			// convert windows newlines to unix
			$list = str_replace("\r\n", "\n", $list);
			$items_pre = explode("\n", $list);
			$items = array();
			// first pass, go through and combine items that are newlined
			foreach ( $items_pre as $item )
			{
				if ( substr($item, 0, 1) == $token )
				{
					$items[] = $item;
				}
				else
				{
					// it's a continuation of the previous LI. Don't need to worry about
					// undefined indices here since the regex should filter out all invalid
					// markup. Just append this line to the previous.
					$items[ count($items) - 1 ] .= "\n" . trim($item);
				}
			}
			
			// second pass, separate items and tokens
			unset($items_pre);
			foreach ( $items as $item )
			{
				// get the depth
				$itemtoken = preg_replace('/^([#:\*]+).*$/s', '$1', $item);
				// get the text
				$itemtext = trim(substr($item, strlen($itemtoken)));
				$piece['items'][] = array(
						// depth starts at 1
						'depth' => strlen($itemtoken),
						'text' => $itemtext
					);
			}
			$pieces[] = $piece;
		}
		
		$text = Carpenter::tokenize($text, $lists[0]);
		
		return $pieces;
	}
	
	public function blockquote(&$text)
	{
		$rand_id = hexencode(AESCrypt::randkey(16), '', '');
		
		while ( preg_match_all('/^(?:(>+) *.+(?:\r?\n|$))+/m', $text, $quotes) )
		{
			foreach ( $quotes[0] as $quote )
			{
				$piece = trim(preg_replace('/^> */m', '', $quote));
				$text = str_replace_once($quote, "{blockquote:$rand_id}\n$piece\n{/blockquote:$rand_id}\n", $text);
			}
		}
		//die('<pre>' . htmlspecialchars($text) . '</pre>');
		
		$this->blockquote_rand_id = $rand_id;
	}
	
	public function blockquotepost(&$text)
	{
		return $this->blockquote_rand_id;
	}
	
	public function paragraph(&$text)
	{
		// The trick with paragraphs is to not turn things into them when a block level element already wraps the block of text.
		// First we need a list of block level elements (http://htmlhelp.com/reference/html40/block.html + some Enano extensions)
		$blocklevel = 'address|blockquote|center|code|div|dl|fieldset|form|h1|h2|h3|h4|h5|h6|hr|li|ol|p|pre|table|ul|tr|td|th|tbody|thead|tfoot';
		
		// Wrap all block level tags
		RenderMan::tag_strip('_paragraph_bypass', $text, $_nw);
		
		// Find all opening and closing tags
		
		$regex = ";(<(?:/(?:$blocklevel)|(?:$blocklevel)(?: [^>]*?)?)>);s";
								
		// oh. and we're using this tokens thing because for identical matches, the first match will
		// get wrapped X number of times instead of all matches getting wrapped once; replacing each
		// with a unique token id remedies this
		
		$tokens = array();
		$rand_id = sha1(microtime() . mt_rand());
		$tag_stack = array();
		
		if ( $text_split = preg_split($regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE) )
		{
			$text = '';
			// go through the text, extract tag names, and push them to a stack.
			foreach ( $text_split as $splitpart )
			{
				if ( preg_match(";^<(/)?($blocklevel)( |>);i", $splitpart, $match) )
				{
					$tagname = $match[2];
					if ( $match[1] == '/' )
					{
						// closing tag
						if ( $tagname != ($top = array_pop($tag_stack)) )
						{
							// invalid - push back
							array_push($tag_stack, $top);
						}
						else
						{
							// valid - if stack's at zero, add a </_paragraph_bypass>
							if ( count($tag_stack) == 0 )
								$splitpart .= '</_paragraph_bypass>';
						}
					}
					else
					{
						// push
						array_push($tag_stack, $tagname);
						if ( count($tag_stack) == 1 )
							$splitpart = '<_paragraph_bypass>' . $splitpart;
					}
				}
				$text .= $splitpart;
			}
			//echo '<pre>' . htmlspecialchars(print_r($text, true)) . '</pre>';
		}
		
		// All things that should be para-bypassed now are surrounded by _paragraph_bypass tags.
		
		// die('<pre>' . htmlspecialchars($text) . '</pre>');
	
		RenderMan::tag_unstrip('_paragraph_bypass', $text, $_nw, true);
		
		// This is potentially a hack. It allows the parser to stick in <_paragraph_bypass> tags
		// to prevent the paragraph parser from interfering with pretty HTML generated elsewhere.
		RenderMan::tag_strip('_paragraph_bypass', $text, $_nw);
		
		$startcond = "(?!(?:[\\r\\n]|\{_paragraph_bypass:[a-f0-9]{32}:[0-9]+\}|[ ]*<\/?(?:$blocklevel)(?: .+>|>)))";
		$regex = "/^
								$startcond        # line start condition - do not match if the line starts with the condition above
								.+?               # body text
								(?:
									\\n             # additional lines
									$startcond      # make sure of only one newline in a row, and end the paragraph if a new line fails the start condition
									.*?
								)*                # keep going until it fails
							$
							/mx";
		
		if ( !preg_match_all($regex, $text, $matches) )
		{
			RenderMan::tag_unstrip('_paragraph_bypass', $text, $_nw);
			return array();
		}
		
		// Debugging :)
		// die('<pre>' . htmlspecialchars($text) . "\n-----------------------------------------------------------\n" . htmlspecialchars(print_r($matches, true)) . '</pre>');
		
		// restore stripped
		RenderMan::tag_unstrip('_paragraph_bypass', $text, $_nw);
		
		// tokenize
		$text = Carpenter::tokenize($text, $matches[0]);
		
		return $matches[0];
	}
}

function parser_mediawiki_xhtml_image($text)
{
	$text = RenderMan::process_image_tags($text, $taglist);
	$text = RenderMan::process_imgtags_stage2($text, $taglist);
	return $text;
}

function parser_mediawiki_xhtml_tables($text)
{
	return process_tables($text);
}

