<?php

/*
 * Enano project note: this skeleton class was rewritten due to a licensing issue.
 */

/**
* 
* This class implements a Text_Wiki_Render_Xhtml to "pre-filter" source text so
* that line endings are consistently \n, lines ending in a backslash \
* are concatenated with the next line, and tabs are converted to spaces.
*
* @author Paul M. Jones <pmjones@php.net>
*
* @package Text_Wiki
*
*/

class Text_Wiki_Render_Plain_Prefilter  extends Text_Wiki_Render
{
  function token()
  {
    return '';
  }
}
