<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class pluginLoader {
  var $hook_list;
  var $load_list;
  var $loaded_plugins;
  var $system_plugins = Array('SpecialUserFuncs.php','SpecialUserPrefs.php','SpecialPageFuncs.php','SpecialAdmin.php','SpecialCSS.php','SpecialUpdownload.php','SpecialSearch.php','PrivateMessages.php','SpecialGroups.php');
  function loadAll() 
  {
    dc_here('plugins: building file list');
    
    $dir = ENANO_ROOT.'/plugins/';
    
    $this->load_list = Array();
    
    $plugins = Array();
    
    // Open a known directory, and proceed to read its contents
    
    if (is_dir($dir))
    {
      if ($dh = opendir($dir))
      {
        while (($file = readdir($dh)) !== false)
        {
          if(preg_match('#^(.*?)\.php$#is', $file))
          {
            if(getConfig('plugin_'.$file) == '1' || in_array($file, $this->system_plugins))
            {
              $this->load_list[] = $dir . $file;
              $plugid = substr($file, 0, strlen($file)-4);
              $f = file_get_contents($dir . $file);
              $f = explode("\n", $f);
              $f = array_slice($f, 2, 7);
              $f[0] = substr($f[0], 13);
              $f[1] = substr($f[1], 12);
              $f[2] = substr($f[2], 13);
              $f[3] = substr($f[3], 8 );
              $f[4] = substr($f[4], 9 );
              $f[5] = substr($f[5], 12);
              $plugins[$plugid] = Array();
              $plugins[$plugid]['name'] = $f[0];
              $plugins[$plugid]['uri']  = $f[1];
              $plugins[$plugid]['desc'] = $f[2];
              $plugins[$plugid]['auth'] = $f[3];
              $plugins[$plugid]['vers'] = $f[4];
              $plugins[$plugid]['aweb'] = $f[5];
            }
          }
        }
        closedir($dh);
      }
    }
    $this->loaded_plugins = $plugins;
    //die('<pre>'.htmlspecialchars(print_r($plugins, true)).'</pre>');
  }
  function setHook($name, $opts = Array()) {
    dc_dump($name, 'plugins: hook added: ');
    /*
    $r = Array();
    if(isset($this->hook_list[$name])) {
      for($i=0;$i<sizeof($this->hook_list[$name]);$i++) {
        $ret = eval($this->hook_list[$name][$i]);
        if($ret !== null) $r[] = $ret;
      }
    }
    if(sizeof($r) > 0) return $r;
    else return false;
    */
    if(isset($this->hook_list[$name]) && is_array($this->hook_list[$name]))
    {
      return $this->hook_list[$name];
    }
    else
    {
      return Array();
    }
  }
  function attachHook($name, $code) {
    dc_dump($code, 'plugins: hook attached: '.$name.'<br />code:');
    if(!isset($this->hook_list[$name]))
    {
      $this->hook_list[$name] = Array();
    }
    $this->hook_list[$name][] = $code;
  }
  function loaded($plugid)
  {
    return isset( $this->loaded_plugins[$plugid] );
  }
}

?>
