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

class Namespace_Template extends Namespace_Default
{
  function send()
  {
    global $output;
    
    $output->add_before_footer($this->display_categories());
    $output->header();
    
    if ( $this->exists )
    {
      $text = $this->fetch_text();
      $text = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '\\1', $text);
      $text = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '', $text);
      
      $text = RenderMan::render( $text );
      
      eval( '?>' . $text );
    }
    else
    {
      $this->error_404();
    }
    
    $output->footer();
  }
}
