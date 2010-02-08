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
 * Framework for parsing and rendering various formats. In Enano by default, this is MediaWiki-style wikitext being
 * rendered to XHTML, but this framework allows other formats to be supported as well.
 *
 * @package Enano
 * @subpackage Content
 * @author Dan Fuhry <dan@enanocms.org>
 * @copyright (C) 2009 Enano CMS Project
 * @license GNU General Public License, version 2 or later <http://www.gnu.org/licenses/gpl-2.0.html>
 */

class Carpenter
{
  /**
   * Parser token
   * @const string
   */
  
  const PARSER_TOKEN = "\xFF";
  
  /**
   * Parsing engine
   * @var string
   */
  
  private $parser = 'mediawiki';
  
  /**
   * Rendering engine
   * @var string
   */
  
  private $renderer = 'xhtml';
  
  /**
   * Rendering flags
   */
  
  public $flags = RENDER_WIKI_DEFAULT;
  
  /**
   * List of rendering rules
   * @var array
   */
  
  private $rules = array(
      'lang',
      'templates',
      'blockquote',
      'code',
      'tables',
      'heading',
      'hr',
      // note: can't be named list ("list" is a PHP language construct)
      'multilist',
      'bold',
      'italic',
      'underline',
      'externalwithtext',
      'externalnotext',
      'mailtowithtext',
      'mailtonotext',
      'image',
      'internallink',
      'paragraph',
      'blockquotepost'
    );
  
  /**
   * List of render hooks
   * @var array
   */
  
  private $hooks = array();
  
  /* private $rules = array('prefilter', 'delimiter', 'code', 'function', 'html', 'raw', 'include', 'embed', 'anchor',
           'heading', 'toc', 'horiz', 'break', 'blockquote', 'list', 'deflist', 'table', 'image',
           'phplookup', 'center', 'newline', 'paragraph', 'url', 'freelink', 'interwiki',
           'wikilink', 'colortext', 'strong', 'bold', 'emphasis', 'italic', 'underline', 'tt',
           'superscript', 'subscript', 'revise', 'tighten'); */
  
  /**
   * Render the text!
   * @param string Text to render
   * @return string
   */
  
  public function render($text)
  {
    $parser_class = "Carpenter_Parse_" . ucwords($this->parser);
    $renderer_class = "Carpenter_Render_" . ucwords($this->renderer);
    
    // empty? (don't remove this. the parser will shit bricks later about rules returning empty strings)
    if ( trim($text) === '' )
      return $text;
    
    // include files, if we haven't already
    if ( !class_exists($parser_class) )
    {
      require_once( ENANO_ROOT . "/includes/wikiengine/parse_{$this->parser}.php");
    }
    
    if ( !class_exists($renderer_class) )
    {
      require_once( ENANO_ROOT . "/includes/wikiengine/render_{$this->renderer}.php");
    }
    
    $parser = new $parser_class;
    $renderer = new $renderer_class;
    
    // run prehooks
    foreach ( $this->hooks as $hook )
    {
      if ( $hook['when'] === PO_FIRST )
      {
        $text = call_user_func($hook['callback'], $text);
        if ( !is_string($text) || empty($text) )
        {
          trigger_error("Hook returned empty/invalid text: " . print_r($hook['callback'], true), E_USER_WARNING);
          // *sigh*
          $text = '';
        }
      }
    }
    
    // perform render
    foreach ( $this->rules as $rule )
    {
      // run prehooks
      foreach ( $this->hooks as $hook )
      {
        if ( $hook['when'] === PO_BEFORE && $hook['rule'] === $rule )
        {
          $text = call_user_func($hook['callback'], $text);
          if ( !is_string($text) || empty($text) )
          {
            trigger_error("Hook returned empty/invalid text: " . print_r($hook['callback'], true), E_USER_WARNING);
            // *sigh*
            $text = '';
          }
        }
      }
      
      // execute rule
      $text_before = $text;
      $text = $this->perform_render_step($text, $rule, $parser, $renderer);
      if ( empty($text) )
      {
        trigger_error("Wikitext was completely empty after rule \"$rule\"; restoring backup", E_USER_WARNING);
        $text = $text_before;
      }
      unset($text_before);
      
      // run posthooks
      foreach ( $this->hooks as $hook )
      {
        if ( $hook['when'] === PO_AFTER && $hook['rule'] === $rule )
        {
          $text = call_user_func($hook['callback'], $text);
          if ( !is_string($text) || empty($text) )
          {
            trigger_error("Hook returned empty/invalid text: " . print_r($hook['callback'], true), E_USER_WARNING);
            // *sigh*
            $text = '';
          }
        }
      }
      
      RenderMan::tag_strip_push('final', $text, $final_stripdata);
    }
    
    RenderMan::tag_unstrip('final', $text, $final_stripdata);
    
    // run posthooks
    foreach ( $this->hooks as $hook )
    {
      if ( $hook['when'] === PO_LAST )
      {
        $text = call_user_func($hook['callback'], $text);
        if ( !is_string($text) || empty($text) )
        {
          trigger_error("Hook returned empty/invalid text: " . print_r($hook['callback'], true), E_USER_WARNING);
          // *sigh*
          $text = '';
        }
      }
    }
    
    return (( defined('ENANO_DEBUG') && isset($_GET['parserdebug']) ) ? '<pre>' . htmlspecialchars($text) . '</pre>' : $text) . "\n\n";
  }
  
  /**
   * Performs a step in the rendering process.
   * @param string Text to render
   * @param string Rule to execute
   * @param object Parser instance
   * @param object Renderer instance
   * @return string
   * @access private
   */
  
  private function perform_render_step($text, $rule, $parser, $renderer)
  {
    // First look for a direct function
    if ( function_exists("parser_{$this->parser}_{$this->renderer}_{$rule}") )
    {
      return call_user_func("parser_{$this->parser}_{$this->renderer}_{$rule}", $text, $this->flags);
    }
    
    // We don't have that, so start looking for other ways or means of doing this
    if ( method_exists($parser, $rule) && method_exists($renderer, $rule) )
    {
      // Both the parser and render have callbacks they want to use.
      $pieces = $parser->$rule($text);
      $text = call_user_func(array($renderer, $rule), $text, $pieces);
    }
    else if ( method_exists($parser, $rule) && !method_exists($renderer, $rule) && isset($renderer->rules[$rule]) )
    {
      // The parser has a callback, but the renderer does not
      $pieces = $parser->$rule($text);
      $text = $this->generic_render($text, $pieces, $renderer->rules[$rule]);
    }
    else if ( !method_exists($parser, $rule) && isset($parser->rules[$rule]) && method_exists($renderer, $rule) )
    {
      // The parser has no callback, but the renderer does
      $text = preg_replace_callback($parser->rules[$rule], array($renderer, $rule), $text);
    }
    else if ( isset($parser->rules[$rule]) && isset($renderer->rules[$rule]) )
    {
      // This is a straight-up regex only rule
      $text = preg_replace($parser->rules[$rule], $renderer->rules[$rule], $text);
    }
    else
    {
      // Either the renderer or parser does not support this rule, ignore it
    }
    
    return $text;
  }
  
  /**
   * Generic renderer
   * @access protected
   */
  
  protected function generic_render($text, $pieces, $rule)
  {
    foreach ( $pieces as $i => $piece )
    {
      $replacement = $rule;
      
      // if the piece is an array, replace $1, $2, etc. in the rule with each value in the piece
      if ( is_array($piece) )
      {
        $j = 0;
        foreach ( $piece as $part )
        {
          $j++;
          $replacement = str_replace(array("\\$j", "\${$j}"), $part, $replacement);
        }
      }
      // else, just replace \\1 or $1 in the rule with the piece
      else
      {
        $replacement = str_replace(array("\\1", "\$1"), $piece, $replacement);
      }
      
      $text = str_replace(self::generate_token($i), $replacement, $text);
    }
    
    return $text;
  }
  
  /**
   * Add a hook into the parser.
   * @param callback Function to call
   * @param int PO_* constant
   * @param string If PO_{BEFORE,AFTER} used, rule
   */
  
  public function hook($callback, $when, $rule = false)
  {
    if ( !is_int($when) )
      return null;
    if ( ($when == PO_BEFORE || $when == PO_AFTER) && !is_string($rule) )
      return null;
    if ( ( is_string($callback) && !function_exists($callback) ) || ( is_array($callback) && !method_exists($callback[0], $callback[1]) ) || ( !is_string($callback) && !is_array($callback) ) )
    {
      trigger_error("Attempt to hook with undefined function/method " . print_r($callback, true), E_USER_ERROR);
      return null;
    }
    
    $this->hooks[] = array(
        'callback' => $callback,
        'when'     => $when,
        'rule'     => $rule
      );
  }
  
  /**
   * Disable a render stage
   * @param string stage
   * @return null
   */
  
  public function disable_rule($rule)
  {
    foreach ( $this->rules as $i => $current_rule )
    {
      if ( $current_rule === $rule )
      {
        unset($this->rules[$i]);
        return null;
      }
    }
    return null;
  }
  
  /**
   * Disables all rules.
   * @return null
   */
  
  public function disable_all_rules()
  {
    $this->rules = array();
    return null;
  }
  
  /**
   * Enables a rule
   * @param string rule
   * @return null
   */
   
  public function enable_rule($rule)
  {
    $this->rules[] = $rule;
    return null;
  }
  
  /**
   * Make a rule exclusive (the only one called)
   * @param string stage
   * @return null
   */
  
  public function exclusive_rule($rule)
  {
    if ( is_string($rule) )
      $this->rules = array($rule);
    
    return null;
  }
  
  /**
   * Generate a token
   * @param int Token index
   * @return string
   * @static
   */
  
  public static function generate_token($i)
  {
    return self::PARSER_TOKEN . $i . self::PARSER_TOKEN;
  }
  
  /**
   * Tokenize string
   * @param string
   * @param array Matches
   * @return string
   * @static
   */
  
  public static function tokenize($text, $matches)
  {
    $matches = array_values($matches);
    foreach ( $matches as $i => $match )
    {
      $text = str_replace_once($match, self::generate_token($i), $text);
    }
    
    return $text;
  }
}

