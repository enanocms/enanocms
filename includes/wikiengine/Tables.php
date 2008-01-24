<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3 (Dyrad)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * This script contains code originally found in MediaWiki (http://www.mediawiki.org). MediaWiki is also licensed under
 * the GPLv2; see the file GPL included with this package for details.
 *
 * We're using the MW parser because the Text_Wiki version simply refused to work under PHP 5.2.0. Porting this was
 * _not_ easy. <leaves to get cup of coffee>
 */

  global $mStripState, $wgRandomKey;
  $mStripState = Array();
  
  $attrib = '[a-zA-Z0-9]';
  $space = '[\x09\x0a\x0d\x20]';
  
  define( 'MW_CHAR_REFS_REGEX',
	'/&([A-Za-z0-9]+);
	 |&\#([0-9]+);
	 |&\#x([0-9A-Za-z]+);
	 |&\#X([0-9A-Za-z]+);
	 |(&)/x' );
  
  define( 'MW_ATTRIBS_REGEX',
    "/(?:^|$space)($attrib+)
      ($space*=$space*
      (?:
       # The attribute value: quoted or alone
        ".'"'."([^<".'"'."]*)".'"'."
       | '([^<']*)'
       |  ([a-zA-Z0-9!#$%&()*,\\-.\\/:;<>?@[\\]^_`{|}~]+)
       |  (\#[0-9a-fA-F]+) # Technically wrong, but lots of
                 # colors are specified like this.
                 # We'll be normalizing it.
      )
       )?(?=$space|\$)/sx" );
  
  /**
   * emulate mediawiki parser, including stripping, etc.
   *
   * @param string $text the text to parse
   * @return string
   * @access public
   */
   
  function process_tables( $text )
  {
    // include some globals, do some parser stuff that would normally be done in the parent parser function
    global $mStripState;
    $x =& $mStripState;
		//$text = mwStrip( $text, $x );
    
    // parse the text
    $text = doTableStuff($text);
    
    // Unstrip it
    // $text = unstrip( $text, $mStripState );
    // $text = unstripNoWiki( $text, $mStripState );
    //die('<pre>'.print_r($mStripState, true).'</pre>');
    return $text;
  }

  /**
	 * parse the wiki syntax used to render tables
	 *
   * @param string $t the text to parse
   * @return string
	 * @access private
	 */
	function doTableStuff( $t ) {
    
		$t = explode ( "\n" , $t ) ;
		$td = array () ; # Is currently a td tag open?
		$ltd = array () ; # Was it TD or TH?
		$tr = array () ; # Is currently a tr tag open?
		$ltr = array () ; # tr attributes
		$has_opened_tr = array(); # Did this table open a <tr> element?
		$indent_level = 0; # indent level of the table
		foreach ( $t AS $k => $x )
		{
			$x = trim ( $x ) ;
			$fc = substr ( $x , 0 , 1 ) ;
			if ( preg_match( '/^(:*)\{\|(.*)$/', $x, $matches ) ) {
				$indent_level = strlen( $matches[1] );

				$attributes = unstripForHTML( $matches[2] );

				$t[$k] = str_repeat( '<dl><dd>', $indent_level ) .
					'<nowiki><table' . fixTagAttributes( $attributes, 'table' ) . '></nowiki>' ;
				array_push ( $td , false ) ;
				array_push ( $ltd , '' ) ;
				array_push ( $tr , false ) ;
				array_push ( $ltr , '' ) ;
				array_push ( $has_opened_tr, false );
			}
			else if ( count ( $td ) == 0 ) { } # Don't do any of the following
			else if ( '|}' == substr ( $x , 0 , 2 ) ) {
				$z = "<nowiki></table></nowiki>" . substr ( $x , 2);
				$l = array_pop ( $ltd ) ;
				if ( !array_pop ( $has_opened_tr ) ) $z = "<nowiki><tr><td></td></tr></nowiki>" . $z ;
				if ( array_pop ( $tr ) ) $z = '<nowiki></tr></nowiki>' . $z ;
				if ( array_pop ( $td ) ) $z = '<nowiki></'.$l.'></nowiki>' . $z ;
				array_pop ( $ltr ) ;
				$t[$k] = $z . str_repeat( '<nowiki></dd></dl></nowiki>', $indent_level );
			}
			else if ( '|-' == substr ( $x , 0 , 2 ) ) { # Allows for |---------------
				$x = substr ( $x , 1 ) ;
				while ( $x != '' && substr ( $x , 0 , 1 ) == '-' ) $x = substr ( $x , 1 ) ;
				$z = '' ;
				$l = array_pop ( $ltd ) ;
				array_pop ( $has_opened_tr );
				array_push ( $has_opened_tr , true ) ;
				if ( array_pop ( $tr ) ) $z = '<nowiki></tr></nowiki>' . $z ;
				if ( array_pop ( $td ) ) $z = '<nowiki></'.$l.'></nowiki>' . $z ;
				array_pop ( $ltr ) ;
				$t[$k] = $z ;
				array_push ( $tr , false ) ;
				array_push ( $td , false ) ;
				array_push ( $ltd , '' ) ;
				$attributes = unstripForHTML( $x );
				array_push ( $ltr , fixTagAttributes( $attributes, 'tr' ) ) ;
			}
			else if ( '|' == $fc || '!' == $fc || '|+' == substr ( $x , 0 , 2 ) ) { # Caption
				# $x is a table row
				if ( '|+' == substr ( $x , 0 , 2 ) ) {
					$fc = '+' ;
					$x = substr ( $x , 1 ) ;
				}
				$after = substr ( $x , 1 ) ;
				if ( $fc == '!' ) $after = str_replace ( '!!' , '||' , $after ) ;

				// Split up multiple cells on the same line.
				// FIXME: This can result in improper nesting of tags processed
				// by earlier parser steps, but should avoid splitting up eg
				// attribute values containing literal "||".
				$after = wfExplodeMarkup( '||', $after );

				$t[$k] = '' ;

				# Loop through each table cell
				foreach ( $after AS $theline )
				{
					$z = '' ;
					if ( $fc != '+' )
					{
						$tra = array_pop ( $ltr ) ;
						if ( !array_pop ( $tr ) ) $z = '<nowiki><tr'.$tra."></nowiki>\n" ;
						array_push ( $tr , true ) ;
						array_push ( $ltr , '' ) ;
						array_pop ( $has_opened_tr );
						array_push ( $has_opened_tr , true ) ;
					}

					$l = array_pop ( $ltd ) ;
					if ( array_pop ( $td ) ) $z = '<nowiki></'.$l.'></nowiki>' . $z ;
					if ( $fc == '|' ) $l = 'td' ;
					else if ( $fc == '!' ) $l = 'th' ;
					else if ( $fc == '+' ) $l = 'caption' ;
					else $l = '' ;
					array_push ( $ltd , $l ) ;

					# Cell parameters
					$y = explode ( '|' , $theline , 2 ) ;
					# Note that a '|' inside an invalid link should not
					# be mistaken as delimiting cell parameters
					if ( strpos( $y[0], '[[' ) !== false ) {
						$y = array ($theline);
					}
					if ( count ( $y ) == 1 )
						$y = "{$z}<nowiki><{$l}></nowiki>{$y[0]}" ;
					else {
						$attributes = unstripForHTML( $y[0] );
						$y = "{$z}<nowiki><{$l}".fixTagAttributes($attributes, $l)."></nowiki>{$y[1]}" ;
					}
					$t[$k] .= $y ;
					array_push ( $td , true ) ;
				}
			}
		}

		# Closing open td, tr && table
		while ( count ( $td ) > 0 )
		{
			$l = array_pop ( $ltd ) ;
			if ( array_pop ( $td ) ) $t[] = '<nowiki></td></nowiki>' ;
			if ( array_pop ( $tr ) ) $t[] = '<nowiki></tr></nowiki>' ;
			if ( !array_pop ( $has_opened_tr ) ) $t[] = "<nowiki><tr><td></td></tr></nowiki>" ;
			$t[] = '<nowiki></table></nowiki>' ;
		}

		$t = implode ( "\n" , $t ) ;
    
		# special case: don't return empty table
		if($t == "<nowiki><table></nowiki>\n<nowiki><tr><td></td></tr></nowiki>\n<nowiki></table></nowiki>")
			$t = '';
		return $t ;
	}
  
  /**
	 * Take a tag soup fragment listing an HTML element's attributes
	 * and normalize it to well-formed XML, discarding unwanted attributes.
	 * Output is safe for further wikitext processing, with escaping of
	 * values that could trigger problems.
	 *
	 * - Normalizes attribute names to lowercase
	 * - Discards attributes not on a whitelist for the given element
	 * - Turns broken or invalid entities into plaintext
	 * - Double-quotes all attribute values
	 * - Attributes without values are given the name as attribute
	 * - Double attributes are discarded
	 * - Unsafe style attributes are discarded
	 * - Prepends space if there are attributes.
	 *
	 * @param string $text
	 * @param string $element
	 * @return string
	 */
	function fixTagAttributes( $text, $element ) {
		if( trim( $text ) == '' ) {
			return '';
		}
		
		$stripped = validateTagAttributes(
			decodeTagAttributes( $text ), $element );
		
		$attribs = array();
		foreach( $stripped as $attribute => $value ) {
			$encAttribute = htmlspecialchars( $attribute );
			$encValue = safeEncodeAttribute( $value );
			
			$attribs[] = "$encAttribute=".'"'."$encValue".'"'.""; // "
		}
		return count( $attribs ) ? ' ' . implode( ' ', $attribs ) : '';
	}
  
  /**
	 * Encode an attribute value for HTML tags, with extra armoring
	 * against further wiki processing.
	 * @param $text
	 * @return HTML-encoded text fragment
	 */
	function safeEncodeAttribute( $text ) {
		$encValue= encodeAttribute( $text );
		
		# Templates and links may be expanded in later parsing,
		# creating invalid or dangerous output. Suppress this.
		$encValue = strtr( $encValue, array(
			'<'    => '&lt;',   // This should never happen,
			'>'    => '&gt;',   // we've received invalid input
			'"'    => '&quot;', // which should have been escaped.
			'{'    => '&#123;',
			'['    => '&#91;',
			"''"   => '&#39;&#39;',
			'ISBN' => '&#73;SBN',
			'RFC'  => '&#82;FC',
			'PMID' => '&#80;MID',
			'|'    => '&#124;',
			'__'   => '&#95;_',
		) );

		return $encValue;
	}
  
  /**
	 * Encode an attribute value for HTML output.
	 * @param $text
	 * @return HTML-encoded text fragment
	 */
	function encodeAttribute( $text ) {
    
    // In Enano 1.0.3, added this cheapo hack to keep ampersands
    // from being double-sanitized. Thanks to markybob from #deluge.
    
    // htmlspecialchars() the "manual" way
    $encValue = strtr( $text, array(
      '&amp;'  => '&',
      '&quot;' => '"',
      '&lt;'   => '<',
      '&gt;'   => '>',
      '&#039;' => "'"
    ) );
    
    $encValue = strtr( $text, array(
      '&' => '&amp;',
      '"' => '&quot;',
      '<' => '&lt;',
      '>' => '&gt;',
      "'" => '&#039;'
    ) );
    
		
		// Whitespace is normalized during attribute decoding,
		// so if we've been passed non-spaces we must encode them
		// ahead of time or they won't be preserved.
		$encValue = strtr( $encValue, array(
			"\n" => '&#10;',
			"\r" => '&#13;',
			"\t" => '&#9;',
		) );
		
		return $encValue;
	}
  
  function unstripForHTML( $text ) {
    global $mStripState;
		$text = unstrip( $text, $mStripState );
		$text = unstripNoWiki( $text, $mStripState );
		return $text;
	}
  
  /**
	 * Always call this after unstrip() to preserve the order
	 *
	 * @private
	 */
	function unstripNoWiki( $text, &$state ) {
		if ( !isset( $state['nowiki'] ) ) {
			return $text;
		}

		# TODO: good candidate for FSS
		$text = strtr( $text, $state['nowiki'] );
		
		return $text;
	}
  
  /**
	 * Take an array of attribute names and values and normalize or discard
	 * illegal values for the given element type.
	 *
	 * - Discards attributes not on a whitelist for the given element
	 * - Unsafe style attributes are discarded
	 *
	 * @param array $attribs
	 * @param string $element
	 * @return array
	 *
	 * @todo Check for legal values where the DTD limits things.
	 * @todo Check for unique id attribute :P
	 */
	function validateTagAttributes( $attribs, $element ) {
		$whitelist = array_flip( attributeWhitelist( $element ) );
		$out = array();
		foreach( $attribs as $attribute => $value ) {
			if( !isset( $whitelist[$attribute] ) ) {
				continue;
			}
			# Strip javascript "expression" from stylesheets.
			# http://msdn.microsoft.com/workshop/author/dhtml/overview/recalc.asp
			if( $attribute == 'style' ) {
				$value = checkCss( $value );
				if( $value === false ) {
					# haxx0r
					continue;
				}
			}

			if ( $attribute === 'id' )
				$value = escapeId( $value );

			// If this attribute was previously set, override it.
			// Output should only have one attribute of each name.
			$out[$attribute] = $value;
		}
		return $out;
	}
  
  /**
	 * Pick apart some CSS and check it for forbidden or unsafe structures.
	 * Returns a sanitized string, or false if it was just too evil.
	 *
	 * Currently URL references, 'expression', 'tps' are forbidden.
	 *
	 * @param string $value
	 * @return mixed
	 */
	function checkCss( $value ) {
		$stripped = decodeCharReferences( $value );

		// Remove any comments; IE gets token splitting wrong
		$stripped = preg_replace( '!/\\*.*?\\*/!S', '', $stripped );
		$value = $stripped;

		// ... and continue checks
		$stripped = preg_replace( '!\\\\([0-9A-Fa-f]{1,6})[ \\n\\r\\t\\f]?!e',
			'codepointToUtf8(hexdec("$1"))', $stripped );
		$stripped = str_replace( '\\', '', $stripped );
		if( preg_match( '/(expression|tps*:\/\/|url\\s*\().*/is',
				$stripped ) ) {
			# haxx0r
			return false;
		}
		
		return $value;
	}
  
  /**
	 * Decode any character references, numeric or named entities,
	 * in the text and return a UTF-8 string.
	 *
	 * @param string $text
	 * @return string
	 * @access public
	 * @static
	 */
	function decodeCharReferences( $text ) {
		return preg_replace_callback(
			MW_CHAR_REFS_REGEX,
			'decodeCharReferencesCallback',
			$text );
	}
  
  /**
	 * Fetch the whitelist of acceptable attributes for a given
	 * element name.
	 *
	 * @param string $element
	 * @return array
	 */
	function attributeWhitelist( $element ) {
		static $list;
		if( !isset( $list ) ) {
			$list = setupAttributeWhitelist();
		}
		return isset( $list[$element] )
			? $list[$element]
			: array();
	}
  
  /**
	 * @todo Document it a bit
	 * @return array
	 */
	function setupAttributeWhitelist() {
    global $db, $session, $paths, $template, $plugins;
		$common = array( 'id', 'class', 'lang', 'dir', 'title', 'style' );
		$block = array_merge( $common, array( 'align' ) );
		$tablealign = array( 'align', 'char', 'charoff', 'valign' );
		$tablecell = array( 'abbr',
		                    'axis',
		                    'headers',
		                    'scope',
		                    'rowspan',
		                    'colspan',
		                    'nowrap', # deprecated
		                    'width',  # deprecated
		                    'height', # deprecated
		                    'bgcolor' # deprecated
		                    );

		# Numbers refer to sections in HTML 4.01 standard describing the element.
		# See: http://www.w3.org/TR/html4/
		$whitelist = array (
			# 7.5.4
			'div'        => $block,
			'center'     => $common, # deprecated
			'span'       => $block, # ??

			# 7.5.5
			'h1'         => $block,
			'h2'         => $block,
			'h3'         => $block,
			'h4'         => $block,
			'h5'         => $block,
			'h6'         => $block,

			# 7.5.6
			# address

			# 8.2.4
			# bdo

			# 9.2.1
			'em'         => $common,
			'strong'     => $common,
			'cite'       => $common,
			# dfn
			'code'       => $common,
			# samp
			# kbd
			'var'        => $common,
			# abbr
			# acronym

			# 9.2.2
			'blockquote' => array_merge( $common, array( 'cite' ) ),
			# q

			# 9.2.3
			'sub'        => $common,
			'sup'        => $common,

			# 9.3.1
			'p'          => $block,

			# 9.3.2
			'br'         => array( 'id', 'class', 'title', 'style', 'clear' ),

			# 9.3.4
			'pre'        => array_merge( $common, array( 'width' ) ),

			# 9.4
			'ins'        => array_merge( $common, array( 'cite', 'datetime' ) ),
			'del'        => array_merge( $common, array( 'cite', 'datetime' ) ),

			# 10.2
			'ul'         => array_merge( $common, array( 'type' ) ),
			'ol'         => array_merge( $common, array( 'type', 'start' ) ),
			'li'         => array_merge( $common, array( 'type', 'value' ) ),

			# 10.3
			'dl'         => $common,
			'dd'         => $common,
			'dt'         => $common,

			# 11.2.1
			'table'      => array_merge( $common,
								array( 'summary', 'width', 'border', 'frame',
										'rules', 'cellspacing', 'cellpadding',
										'align', 'bgcolor',
								) ),

			# 11.2.2
			'caption'    => array_merge( $common, array( 'align' ) ),

			# 11.2.3
			'thead'      => array_merge( $common, $tablealign ),
			'tfoot'      => array_merge( $common, $tablealign ),
			'tbody'      => array_merge( $common, $tablealign ),

			# 11.2.4
			'colgroup'   => array_merge( $common, array( 'span', 'width' ), $tablealign ),
			'col'        => array_merge( $common, array( 'span', 'width' ), $tablealign ),

			# 11.2.5
			'tr'         => array_merge( $common, array( 'bgcolor' ), $tablealign ),

			# 11.2.6
			'td'         => array_merge( $common, $tablecell, $tablealign ),
			'th'         => array_merge( $common, $tablecell, $tablealign ),
      
      # 12.2
      # added by dan
      'a'          => array_merge( $common, array( 'href', 'name' ) ),
      
      # 13.2
      # added by dan
      'img'        => array_merge( $common, array( 'src', 'width', 'height', 'alt' ) ),

			# 15.2.1
			'tt'         => $common,
			'b'          => $common,
			'i'          => $common,
			'big'        => $common,
			'small'      => $common,
			'strike'     => $common,
			's'          => $common,
			'u'          => $common,

			# 15.2.2
			'font'       => array_merge( $common, array( 'size', 'color', 'face' ) ),
			# basefont

			# 15.3
			'hr'         => array_merge( $common, array( 'noshade', 'size', 'width' ) ),

			# XHTML Ruby annotation text module, simple ruby only.
			# http://www.w3c.org/TR/ruby/
			'ruby'       => $common,
			# rbc
			# rtc
			'rb'         => $common,
			'rt'         => $common, #array_merge( $common, array( 'rbspan' ) ),
			'rp'         => $common,
      
      # For compatibility with the XHTML parser.
      'nowiki'     => array(),
      'noinclude'  => array(),
      'nodisplay'  => array(),
      
      # XHTML stuff
      'acronym'    => $common
			);
    
    // custom tags can be added by plugins
    $code = $plugins->setHook('html_attribute_whitelist');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
		return $whitelist;
	}
  
  /**
	 * Given a value escape it so that it can be used in an id attribute and
	 * return it, this does not validate the value however (see first link)
	 *
	 * @link http://www.w3.org/TR/html401/types.html#type-name Valid characters
	 *                                                          in the id and
	 *                                                          name attributes
	 * @link http://www.w3.org/TR/html401/struct/links.html#h-12.2.3 Anchors with the id attribute
	 *
	 * @bug 4461
	 *
	 * @static
	 *
	 * @param string $id
	 * @return string
	 */
	function escapeId( $id ) {
		static $replace = array(
			'%3A' => ':',
			'%' => '.'
		);

		$id = urlencode( decodeCharReferences( strtr( $id, ' ', '_' ) ) );

		return str_replace( array_keys( $replace ), array_values( $replace ), $id );
	}
  
  /**
   * More or less "markup-safe" explode()
   * Ignores any instances of the separator inside <...>
   * @param string $separator
   * @param string $text
   * @return array
   */
  function wfExplodeMarkup( $separator, $text ) {
    $placeholder = "\x00";
    
    // Just in case...
    $text = str_replace( $placeholder, '', $text );
    
    // Trim stuff
    $replacer = new ReplacerCallback( $separator, $placeholder );
    $cleaned = preg_replace_callback( '/(<.*?>)/', array( $replacer, 'go' ), $text );
    
    $items = explode( $separator, $cleaned );
    foreach( $items as $i => $str ) {
      $items[$i] = str_replace( $placeholder, $separator, $str );
    }
    
    return $items;
  }
  
  class ReplacerCallback {
    function ReplacerCallback( $from, $to ) {
      $this->from = $from;
      $this->to = $to;
    }
    
    function go( $matches ) {
      return str_replace( $this->from, $this->to, $matches[1] );
    }
  }
  
  /**
	 * Return an associative array of attribute names and values from
	 * a partial tag string. Attribute names are forces to lowercase,
	 * character references are decoded to UTF-8 text.
	 *
	 * @param string
	 * @return array
	 */
	function decodeTagAttributes( $text ) {
		$attribs = array();

		if( trim( $text ) == '' ) {
			return $attribs;
		}

		$pairs = array();
		if( !preg_match_all(
			MW_ATTRIBS_REGEX,
			$text,
			$pairs,
			PREG_SET_ORDER ) ) {
			return $attribs;
		}

		foreach( $pairs as $set ) {
			$attribute = strtolower( $set[1] );
			$value = getTagAttributeCallback( $set );
			
			// Normalize whitespace
			$value = preg_replace( '/[\t\r\n ]+/', ' ', $value );
			$value = trim( $value );
			
			// Decode character references
			$attribs[$attribute] = decodeCharReferences( $value );
		}
		return $attribs;
	}
  
  /**
	 * Pick the appropriate attribute value from a match set from the
	 * MW_ATTRIBS_REGEX matches.
	 *
	 * @param array $set
	 * @return string
	 * @access private
	 */
	function getTagAttributeCallback( $set ) {
		if( isset( $set[6] ) ) {
			# Illegal #XXXXXX color with no quotes.
			return $set[6];
		} elseif( isset( $set[5] ) ) {
			# No quotes.
			return $set[5];
		} elseif( isset( $set[4] ) ) {
			# Single-quoted
			return $set[4];
		} elseif( isset( $set[3] ) ) {
			# Double-quoted
			return $set[3];
		} elseif( !isset( $set[2] ) ) {
			# In XHTML, attributes must have a value.
			# For 'reduced' form, return explicitly the attribute name here.
			return $set[1];
		} else {
			die_friendly('Parser error', "<p>Tag conditions not met. This should never happen and is a bug.</p>" );
		}
	}
  
  /**
	 * Strips and renders nowiki, pre, math, hiero
	 * If $render is set, performs necessary rendering operations on plugins
	 * Returns the text, and fills an array with data needed in unstrip()
	 * If the $state is already a valid strip state, it adds to the state
	 *
	 * @param bool $stripcomments when set, HTML comments <!-- like this -->
	 *  will be stripped in addition to other tags. This is important
	 *  for section editing, where these comments cause confusion when
	 *  counting the sections in the wikisource
	 * 
	 * @param array dontstrip contains tags which should not be stripped;
	 *  used to prevent stipping of <gallery> when saving (fixes bug 2700)
	 *
	 * @access private
	 */
	function mwStrip( $text, &$state, $stripcomments = false , $dontstrip = array () ) {
    global $wgRandomKey;
		$render = true;

		$wgRandomKey = "\x07UNIQ" . dechex(mt_rand(0, 0x7fffffff)) . dechex(mt_rand(0, 0x7fffffff));
    $uniq_prefix =& $wgRandomKey;
		$commentState = array();
		
		$elements = array( 'nowiki', 'gallery' );
		
    # Removing $dontstrip tags from $elements list (currently only 'gallery', fixing bug 2700)
		foreach ( $elements AS $k => $v ) {
			if ( !in_array ( $v , $dontstrip ) ) continue;
			unset ( $elements[$k] );
		}
		
		$matches = array();
		$text = extractTagsAndParams( $elements, $text, $matches, $uniq_prefix );

		foreach( $matches as $marker => $data ) {
			list( $element, $content, $params, $tag ) = $data;
			if( $render ) {
				$tagName = strtolower( $element );
				switch( $tagName ) {
				case '!--':
					// Comment
					if( substr( $tag, -3 ) == '-->' ) {
						$output = $tag;
					} else {
						// Unclosed comment in input.
						// Close it so later stripping can remove it
						$output = "$tag-->";
					}
					break;
				case 'html':
					if( $wgRawHtml ) {
						$output = $content;
						break;
					}
					// Shouldn't happen otherwise. :)
				case 'nowiki':
					$output = wfEscapeHTMLTagsOnly( $content );
					break;
				default:
				}
			} else {
				// Just stripping tags; keep the source
				$output = $tag;
			}

			// Unstrip the output, because unstrip() is no longer recursive so 
			// it won't do it itself
			$output = unstrip( $output, $state );

			if( !$stripcomments && $element == '!--' ) {
				$commentState[$marker] = $output;
			} elseif ( $element == 'html' || $element == 'nowiki' ) {
				$state['nowiki'][$marker] = $output;
			} else {
				$state['general'][$marker] = $output;
			}
		}

		# Unstrip comments unless explicitly told otherwise.
		# (The comments are always stripped prior to this point, so as to
		# not invoke any extension tags / parser hooks contained within
		# a comment.)
		if ( !$stripcomments ) {
			// Put them all back and forget them
			$text = strtr( $text, $commentState );
		}

		return $text;
	}
  
  /**
	 * Replaces all occurrences of HTML-style comments and the given tags
	 * in the text with a random marker and returns teh next text. The output
	 * parameter $matches will be an associative array filled with data in
	 * the form:
	 *   'UNIQ-xxxxx' => array(
	 *     'element',
	 *     'tag content',
	 *     array( 'param' => 'x' ),
	 *     '<element param="x">tag content</element>' ) )
	 *
	 * @param $elements list of element names. Comments are always extracted.
	 * @param $text Source text string.
	 * @param $uniq_prefix
	 *
	 * @access private
	 * @static
	 */
	function extractTagsAndParams($elements, $text, &$matches, $uniq_prefix = ''){
		static $n = 1;
		$stripped = '';
		$matches = array();

		$taglist = implode( '|', $elements );
		$start = "/<($taglist)(\\s+[^>]*?|\\s*?)(\/?>)|<(!--)/i";

		while ( '' != $text ) {
			$p = preg_split( $start, $text, 2, PREG_SPLIT_DELIM_CAPTURE );
			$stripped .= $p[0];
			if( count( $p ) < 5 ) {
				break;
			}
			if( count( $p ) > 5 ) {
				// comment
				$element    = $p[4];
				$attributes = '';
				$close      = '';
				$inside     = $p[5];
			} else {
				// tag
				$element    = $p[1];
				$attributes = $p[2];
				$close      = $p[3];
				$inside     = $p[4];
			}

			$marker = "$uniq_prefix-$element-" . sprintf('%08X', $n++) . '-QINU';
			$stripped .= $marker;

			if ( $close === '/>' ) {
				// Empty element tag, <tag />
				$content = null;
				$text = $inside;
				$tail = null;
			} else {
				if( $element == '!--' ) {
					$end = '/(-->)/';
				} else {
					$end = "/(<\\/$element\\s*>)/i";
				}
				$q = preg_split( $end, $inside, 2, PREG_SPLIT_DELIM_CAPTURE );
				$content = $q[0];
				if( count( $q ) < 3 ) {
					# No end tag -- let it run out to the end of the text.
					$tail = '';
					$text = '';
				} else {
					$tail = $q[1];
					$text = $q[2];
				}
			}
			
			$matches[$marker] = array( $element,
				$content,
				decodeTagAttributes( $attributes ),
				"<$element$attributes$close$content$tail" );
		}
		return $stripped;
	}
  
  /**
   * Escape html tags
   * Basically replacing " > and < with HTML entities ( &quot;, &gt;, &lt;)
   *
   * @param $in String: text that might contain HTML tags.
   * @return string Escaped string
   */
  function wfEscapeHTMLTagsOnly( $in ) {
    return str_replace(
      array( '"', '>', '<' ),
      array( '&quot;', '&gt;', '&lt;' ),
      $in );
  }
  
  /**
	 * Restores pre, math, and other extensions removed by strip()
	 *
	 * always call unstripNoWiki() after this one
	 * @private
	 */
	function unstrip( $text, &$state ) {
		if ( !isset( $state['general'] ) ) {
			return $text;
		}

		# TODO: good candidate for FSS
		$text = strtr( $text, $state['general'] );
    
		return $text;
	}
  
  /**
	 * Return UTF-8 string for a codepoint if that is a valid
	 * character reference, otherwise U+FFFD REPLACEMENT CHARACTER.
	 * @param int $codepoint
	 * @return string
	 * @private
	 */
	function decodeChar( $codepoint ) {
		if( validateCodepoint( $codepoint ) ) {
			return codepointToUtf8( $codepoint );
		} else {
			return UTF8_REPLACEMENT;
		}
	}

	/**
	 * If the named entity is defined in the HTML 4.0/XHTML 1.0 DTD,
	 * return the UTF-8 encoding of that character. Otherwise, returns
	 * pseudo-entity source (eg &foo;)
	 *
	 * @param string $name
	 * @return string
	 */
	function decodeEntity( $name ) {
		global $wgHtmlEntities;
		if( isset( $wgHtmlEntities[$name] ) ) {
			return codepointToUtf8( $wgHtmlEntities[$name] );
		} else {
			return "&$name;";
		}
	}
  
  /**
	 * Returns true if a given Unicode codepoint is a valid character in XML.
	 * @param int $codepoint
	 * @return bool
	 */
	function validateCodepoint( $codepoint ) {
		return ($codepoint ==    0x09)
			|| ($codepoint ==    0x0a)
			|| ($codepoint ==    0x0d)
			|| ($codepoint >=    0x20 && $codepoint <=   0xd7ff)
			|| ($codepoint >=  0xe000 && $codepoint <=   0xfffd)
			|| ($codepoint >= 0x10000 && $codepoint <= 0x10ffff);
	}
  
/**
 * Return UTF-8 sequence for a given Unicode code point.
 * May die if fed out of range data.
 *
 * @param $codepoint Integer:
 * @return String
 * @public
 */
function codepointToUtf8( $codepoint ) {
	if($codepoint <		0x80) return chr($codepoint);
	if($codepoint <    0x800) return chr($codepoint >>	6 & 0x3f | 0xc0) .
									 chr($codepoint		  & 0x3f | 0x80);
	if($codepoint <  0x10000) return chr($codepoint >> 12 & 0x0f | 0xe0) .
									 chr($codepoint >>	6 & 0x3f | 0x80) .
									 chr($codepoint		  & 0x3f | 0x80);
	if($codepoint < 0x110000) return chr($codepoint >> 18 & 0x07 | 0xf0) .
									 chr($codepoint >> 12 & 0x3f | 0x80) .
									 chr($codepoint >>	6 & 0x3f | 0x80) .
									 chr($codepoint		  & 0x3f | 0x80);

	echo "Asked for code outside of range ($codepoint)\n";
	die( -1 );
}

  /**
	 * @param string $matches
	 * @return string
	 */
	function decodeCharReferencesCallback( $matches ) {
		if( $matches[1] != '' ) {
			return decodeEntity( $matches[1] );
		} elseif( $matches[2] != '' ) {
			return  decodeChar( intval( $matches[2] ) );
		} elseif( $matches[3] != ''  ) {
			return  decodeChar( hexdec( $matches[3] ) );
		} elseif( $matches[4] != '' ) {
			return  decodeChar( hexdec( $matches[4] ) );
		}
		# Last case should be an ampersand by itself
		return $matches[0];
	}
  
?>
