<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

class Namespace_API extends Namespace_Default
{
  function send()
  {
    global $output, $session;
    $uri = scriptPath . '/' . $this->page_id;
    if ( $output->naked )
    {
      $sep = ( strstr($uri, '?') ) ? '&' : '?';
      $uri .= "{$sep}noheaders";
    }
    if ( $session->sid_super )
    {
      $sep = ( strstr($uri, '?') ) ? '&' : '?';
      $uri .= "{$sep}auth={$session->sid_super}";
    }
    redirect( $uri, '', '', 0 );
  }
}

