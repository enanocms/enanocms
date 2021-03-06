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
 * This script contains code originally found in MediaWiki (http://www.mediawiki.org). MediaWiki is also licensed under
 * the GPLv2 or later; see the file GPL included with this package for details.
 *
 * We're using the MW parser because the Text_Wiki version simply refused to work under PHP 5.2.0. Porting this was
 * _not_ easy. <leaves to get cup of coffee>
 */

global $mStripState, $wgRandomKey;
$mStripState = Array();

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
	
	// parse the text
	$text = doTableStuff($text);

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
	$row_count = 0;
	foreach ( $t AS $k => $line )
	{
		$line = trim ( $line ) ;
		$first_char = substr ( $line , 0 , 1 ) ;
		if ( preg_match( '/^(:*)\{\|(.*)$/', $line, $matches ) ) {
			$indent_level = strlen( $matches[1] );

			$attributes = unstripForHTML( $matches[2] );
			$styled_table = false;
			if ( trim($attributes) == "styled" )
			{
				$attributes='cellspacing="1" cellpadding="4"';
				$styled_table = true;
			}

			$t[$k] = str_repeat( '<dl><dd>', $indent_level ) .
				( $styled_table ? '<div class="tblholder">' : '' ) .
				'<table' . fixTagAttributes( $attributes, 'table' ) . '>' ;
			array_push ( $td , false ) ;
			array_push ( $ltd , '' ) ;
			array_push ( $tr , false ) ;
			array_push ( $ltr , '' ) ;
			array_push ( $has_opened_tr, false );
		}
		else if ( count ( $td ) == 0 ) { } # Don't do any of the following
		else if ( '|}' == substr ( $line , 0 , 2 ) ) {
			$z = "</table>" . ( $styled_table ? '</div>' : '' ) . substr ( $line , 2);
			$l = array_pop ( $ltd ) ;
			if ( !array_pop ( $has_opened_tr ) ) $z = "<tr><td></td></tr>" . $z ;
			if ( array_pop ( $tr ) ) $z = '</tr>' . $z ;
			if ( array_pop ( $td ) ) $z = '</'.$l.'>' . $z ;
			array_pop ( $ltr ) ;
			$t[$k] = $z . str_repeat( '</dd></dl>', $indent_level );
		}
		else if ( '|-' == substr ( $line , 0 , 2 ) ) { # Allows for |---------------
			$row_count++;
			$line = substr ( $line , 1 ) ;
			while ( $line != '' && substr ( $line , 0 , 1 ) == '-' ) $line = substr ( $line , 1 ) ;
			$z = '' ;
			$l = array_pop ( $ltd ) ;
			array_pop ( $has_opened_tr );
			array_push ( $has_opened_tr , true ) ;
			if ( array_pop ( $tr ) ) $z = '</tr>' . $z ;
			if ( array_pop ( $td ) ) $z = '</'.$l.'>' . $z ;
			array_pop ( $ltr ) ;
			$t[$k] = $z ;
			array_push ( $tr , false ) ;
			array_push ( $td , false ) ;
			array_push ( $ltd , '' ) ;
			$attributes = unstripForHTML( $line );
			array_push ( $ltr , fixTagAttributes( $attributes, 'tr' ) ) ;
		}
		else if ( '|' == $first_char || '!' == $first_char || '|+' == substr ( $line , 0 , 2 ) ) { # Caption
			# $line is a table row
			if ( '|+' == substr ( $line , 0 , 2 ) ) {
				$first_char = '+' ;
				$line = substr ( $line , 1 ) ;
			}
			$after = substr ( $line , 1 ) ;
			if ( $first_char == '!' ) $after = str_replace ( '!!' , '||' , $after ) ;

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
				if ( $first_char != '+' )
				{
					$tra = array_pop ( $ltr ) ;
					if ( !array_pop ( $tr ) ) $z = '<tr'.$tra.">\n" ;
					array_push ( $tr , true ) ;
					array_push ( $ltr , '' ) ;
					array_pop ( $has_opened_tr );
					array_push ( $has_opened_tr , true ) ;
				}

				$l = array_pop ( $ltd ) ;
				if ( array_pop ( $td ) ) $z = '</'.$l.'>' . $z ;
				if ( $first_char == '|' ) $l = 'td' ;
				else if ( $first_char == '!' ) $l = 'th' ;
				else if ( $first_char == '+' ) $l = 'caption' ;
				else $l = '' ;
				array_push ( $ltd , $l ) ;

				# Cell parameters
				$y = explode ( '|' , $theline , 2 ) ;
				# Note that a '|' inside an invalid link should not
				# be mistaken as delimiting cell parameters
				if ( strpos( $y[0], '[[' ) !== false ) {
					$y = array ($theline);
				}
				$attr_append = '';
				if ( $styled_table && $l == 'td' )
				{
					$rowclass = 1 + ($row_count % 2);
					$attr_append .= ' class="row' . $rowclass . '"';
				}
				if ( count ( $y ) == 1 )
				{
					$y = "{$z}<{$l}{$attr_append}>{$y[0]}" ;
				}
				else
				{
					$attributes = unstripForHTML( $y[0] );
					if ( !strstr($attributes, "class=") )
						$attributes .= $attr_append;
					$y = "{$z}<{$l}".fixTagAttributes($attributes, $l).">{$y[1]}" ;
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
		if ( array_pop ( $td ) ) $t[] = '</td>' ;
		if ( array_pop ( $tr ) ) $t[] = '</tr>' ;
		if ( !array_pop ( $has_opened_tr ) ) $t[] = "<tr><td></td></tr>" ;
		$t[] = '</table></_paragraph_bypass>' ;
	}

	$t = implode ( "\n" , $t ) ;
	
	# special case: don't return empty table
	if($t == "<table>\n<tr><td></td></tr>\n</table>")
		$t = '';
	return $t ;
}

