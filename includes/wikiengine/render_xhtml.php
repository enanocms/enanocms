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

class Carpenter_Render_Xhtml
{
  public $rules = array(
    'lang'   => '',
    'templates' => '',
    'bold'   => '<strong>\\1</strong>',
    'italic' => '<em>\\1</em>',
    'underline' => '<span style="text-decoration: underline;">\\1</span>',
    'externalwithtext' => '<a href="\\1" onclick="window.open(this.href); return false;">\\2</a>',
    'externalnotext' => '<a href="\\1" onclick="window.open(this.href); return false;">\\1</a>',
    'hr' => '<hr />'
  );
  
  public function heading($text, $pieces)
  {
    foreach ( $pieces as $i => $piece )
    {
      $tocid = sanitize_page_id(trim($piece['text']));
      // (bad) workaround for links in headings
      $tocid = str_replace(array('[', ']'), '', $tocid);
      $tag = '<h' . $piece['level'] . ' id="head:' . $tocid . '">';
      $tag .= trim($piece['text']);
      $tag .= '</h' . $piece['level'] . '>';
      
      $text = str_replace(Carpenter::generate_token($i), $tag, $text);
    }
    
    return $text;
  }
  
  public function multilist($text, $pieces)
  {
    foreach ( $pieces as $i => $piece )
    {
      switch($piece['type'])
      {
        case 'unordered':
        default:
          $btag = 'ul';
          $itag = 'li';
          break;
        case 'ordered':
          $btag = 'ol';
          $itag = 'li';
          break;
        case 'indent':
          $btag = 'dl';
          $itag = 'dd';
          break;
      }
      $list = "<_paragraph_bypass><$btag>\n";
      $spacing = '';
      $depth = 1;
      foreach ( $piece['items'] as $j => $item )
      {
        // most of this just goes into pretty formatting.
        // everything else goes into meeting the PITA requirement that if you're going
        // another level deep, HTML requires the next level to be INSIDE of the <dd>/<li> tag.
        $itemdepth = $item['depth'];
        if ( $itemdepth > $depth )
        {
          while ( $depth < $itemdepth )
          {
            $spacing .= '    ';
            $list .= "{$spacing}<$btag>\n";
            $depth++;
          }
        }
        else if ( $itemdepth < $depth )
        {
          while ( $depth > $itemdepth )
          {
            $list .= "{$spacing}</$btag>\n";
            $spacing = substr($spacing, 4);
            $list .= "{$spacing}</$itag>\n";
            $spacing = substr($spacing, 4);
            $depth--;
          }
        }
        $list .= "{$spacing}    <$itag>" . nl2br($item['text']);
        if ( ( isset($piece['items'][ ++$j ]) && $piece['items'][ $j ]['depth'] <= $itemdepth ) || !isset($piece['items'][ $j ]) ) 
        {
          $list .= "</$itag>\n";
        }
        else
        {
          $list .= "\n";
          $spacing .= "    ";
        }
      }
      while ( $depth > 1 )
      {
        $list .= "{$spacing}</$btag>\n";
        $spacing = substr($spacing, 4);
        $list .= "{$spacing}</$itag>\n";
        $spacing = substr($spacing, 4);
        $depth--;
      }
      $list .= "</$btag></_paragraph_bypass>\n";
      $text = str_replace(Carpenter::generate_token($i), $list, $text);
    }
    return $text;
  }
  
  public function blockquote($text)
  {
    return $text;
  }
  
  public function blockquotepost($text, $rand_id)
  {
    $text = strtr($text, array(
        "<p>{blockquote:$rand_id}<br />"  => '<blockquote>',
        "<br />\n{/blockquote:$rand_id}</p>" => '</blockquote>',
        "{blockquote:$rand_id}"  => '<blockquote>',
        "{/blockquote:$rand_id}" => '</blockquote>'
      ));
    $text = strtr($text, array(
        "<blockquote><br />" => '<blockquote>',
        "</blockquote><br />" => '</blockquote>'
      ));
    return $text;
  }
  
  public function paragraph($text, $pieces)
  {
    foreach ( $pieces as $i => $piece )
    {
      $text = str_replace(Carpenter::generate_token($i), '<p>' . nl2br($piece) . '</p>', $text);
    }
    
    return $text;
  }
  
  public function mailtonotext($pieces)
  {
    $pieces[2] = $pieces[1];
    return $this->mailtowithtext($pieces);
  }
  
  public function mailtowithtext($pieces)
  {
    global $email;
    return $email->encryptEmail($pieces[1], '', '', $pieces[2]);
  }
  
  public function code($match)
  {
    return '<pre>' . htmlspecialchars($match[0]) . '</pre>';
  }
}

// Alias internal link parsing to RenderMan's method
function parser_mediawiki_xhtml_internallink($text)
{
  return RenderMan::parse_internal_links($text);
}

