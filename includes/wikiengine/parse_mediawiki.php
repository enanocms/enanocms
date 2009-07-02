<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
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
    'externalnotext' => '#\[((?:https?|irc|ftp)://.+?)\]#'
  );
  
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
    while ( preg_match($template_regex, $text) )
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
        list($itemtoken) = explode(' ', $item);
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
  
  public function paragraph(&$text)
  {
    // This is potentially a hack. It allows the parser to stick in <_paragraph_bypass> tags
    // to prevent the paragraph parser from interfering with pretty HTML generated elsewhere.
    RenderMan::tag_strip('_paragraph_bypass', $text, $_nw);
    
    // The trick with paragraphs is to not turn things into them when a block level element already wraps the block of text.
    // First we need a list of block level elements (http://htmlhelp.com/reference/html40/block.html)
    $blocklevel = 'address|blockquote|center|div|dl|fieldset|form|h1|h2|h3|h4|h5|h6|hr|ol|p|pre|table|ul';
    
    $regex = "/^(
                (?:(?!(?:\\n|[ ]*<(?:{$blocklevel}))))    # condition for starting paragraph: not a newline character or block level element
                .+?                                       # body text
                (?:
                  \\n                                     # additional lines in the para
                  (?:(?!(?:\\n|[ ]*<(?:{$blocklevel}))))  # make sure of only one newline in a row, and no block level elements
                  .*?
                )*
              )$
              /mx";
    
    if ( !preg_match_all($regex, $text, $matches) )
      return array();
    
    // Debugging :)
    // die('<pre>' . htmlspecialchars(print_r($matches, true)) . '</pre>');
    
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

