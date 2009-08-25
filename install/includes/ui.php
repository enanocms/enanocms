<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * ui.php - User interface for installations and upgrades
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * The class for drawing and managing UI components.
 * @package Enano
 * @subpackage Installer
 * @author Dan Fuhry
 */

class Enano_Installer_UI
{
  /**
   * The list of installer stages.
   * @var array
   */
  
  var $stages = array();
  
  /**
   * The GUID of the active stage
   * @var string
   */
  
  var $current_stage = '';
  
  /**
   * The application name, or the name displayed after the stage name in the title bar. Should be localized.
   * @var string
   */
  
  var $app_name = '';
  
  /**
   * If the header should be simplified (stripped of the Enano logo and top heading), this will be true.
   * @var bool
   */
  
  var $simple = false;
  
  /**
   * Text inserted into the header on the right.
   * @var string
   */
  
  var $step = '';
  
  /**
   * Extra text to add to the HTML <head> section
   * @var array Will be implode()'ed
   */
  
  var $additional_headers = array();
  
  /**
   * Constructor.
   * @param string The name displayed in the <title> tag
   * @param bool If true, the simplified header format is displayed.
   */
  
  function __construct($app_name, $simple_header)
  {
    $this->stages = array(
        'main' => array(),
        'hide' => array()
      );
    $this->app_name = $app_name;
    $this->simple = ( $simple_header ) ? true : false;
  }
  
  /**
   * Adds more text to the HTML header.
   * @param string
   */
  
  function add_header($html)
  {
    $this->additional_headers[] = $html;
  }
  
  /**
   * Adds a stage to the installer.
   * @param string Title of the stage, should be already put through $lang->get()
   * @param bool If true, the stage is shown among possible stages at the top of the window. If false, acts as a hidden stage
   * @return string Unique identifier for stage, used later on set_visible_stage()
   */
  
  function add_stage($stage, $visible = true)
  {
    $key = ( $visible ) ? 'main' : 'hide';
    $guid = md5(microtime() . mt_rand());
    $this->stages[$key][$guid] = $stage;
    if ( empty($this->current_stage) )
      $this->current_stage = $guid;
    return $guid;
  }
  
  /**
   * Resets the active stage of installation. This is for the UI only; it doesn't actually change how the backend works.
   * @param string GUID of stage, returned from add_stage()
   * @return bool true on success, false if stage GUID not found
   */
  
  function set_visible_stage($guid)
  {
    foreach ( $this->stages['main'] as $key => $stage_name )
    {
      if ( $key == $guid )
      {
        $this->current_stage = $guid;
        return true;
      }
    }
    foreach ( $this->stages['hide'] as $key => $stage_name )
    {
      if ( $key == $guid )
      {
        $this->current_stage = $guid;
        return true;
      }
    }
    return false;
  }
  
  /**
   * Outputs the HTML headers and start of the <body>, including stage indicator
   */
  
  function show_header()
  {
    // Determine the name of the current stage
    $stage_name = false;
    
    if ( isset($this->stages['main'][$this->current_stage]) )
      $stage_name = $this->stages['main'][$this->current_stage];
    else if ( isset($this->stages['hide'][$this->current_stage]) )
      $stage_name = $this->stages['hide'][$this->current_stage];
    else
      // Can't determine name of stage
      return false;
      
    $this->app_name = htmlspecialchars($this->app_name);
    $stage_name = htmlspecialchars($stage_name);
    
    global $lang;
    if ( is_object($lang) && isset($GLOBALS['lang_uri']) )
    {
      $lang_uri = sprintf($GLOBALS['lang_uri'], $lang->lang_code);
      $this->add_header('<script type="text/javascript" src="' . $lang_uri . '"></script>');
    }
    
    $additional_headers = implode("\n    ", $this->additional_headers);
    $title = addslashes(str_replace(' ', '_', $stage_name));
    $js_dynamic = '<script type="text/javascript">
        var title="' . $title . '";
        var scriptPath="'.scriptPath.'";
        var cdnPath="'.scriptPath.'";
        var ENANO_SID="";
        var AES_BITS='.AES_BITS.';
        var AES_BLOCKSIZE=' . AES_BLOCKSIZE . ';
        var pagepass=\'\';
        var ENANO_LANG_ID = 1;
        var DISABLE_MCE = true;
        var msg_loading_component = \'Loading %component%...\';
      </script>';
    
    echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{$stage_name} &bull; {$this->app_name}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="../includes/clientside/css/enano-shared.css" />
    <link rel="stylesheet" type="text/css" href="images/css/installer.css" id="mdgCss" />
    $js_dynamic
    <script type="text/javascript" src="../includes/clientside/static/enano-lib-basic.js"></script>
    $additional_headers
  </head>
  <body>
    <div id="enano">

EOF;
    if ( !$this->simple )
    {
      $step = ( !empty($this->step) ) ? '<div id="step">' . htmlspecialchars($this->step) . '</div>' : '';
      echo <<<EOF
      <div id="header">
        $step
        <img alt="Enano logo" src="images/enano-artwork/installer-header-blue.png" />
      </div>

EOF;
    }
    $stages_class = ( $this->simple ) ? 'stages' : 'stages stages-fixed';
    echo <<<EOF
      <div class="stages-holder">
        <ul class="$stages_class">
    
EOF;
    foreach ( $this->stages['main'] as $guid => $stage )
    {
      $class = ( $guid == $this->current_stage ) ? 'stage stage-active' : 'stage';
      $stage = htmlspecialchars($stage);
      echo "      <li class=\"$class\">$stage</li>\n    ";
    }
    echo "    </ul>\n      <div style=\"clear: both;\"></div>\n      </div>\n";
    echo "      <div id=\"enano-fill\">\n      ";
    echo "  <div id=\"enano-body\">\n            ";
  }
  
  /**
   * Displays the page footer.
   */
  
  function show_footer()
  {
    $scriptpath = scriptPath;
    $year = date('Y');
    echo <<<EOF
          <div id="copyright">
            Enano and its various components, related documentation, and artwork are copyright &copy; 2006-$year Dan Fuhry.<br />
            Copyrights for <a href="{$scriptpath}/licenses/">third-party components</a> are held by their respective authors.<br />
            This program is Free Software; see the file "GPL" included with this package for details.
          </div>
        </div> <!-- div#enano-body -->
      </div> <!-- div#enano-fill -->
    </div> <!-- div#enano -->
  </body>
</html>
EOF;
  }
  
}
 
?>
