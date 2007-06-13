<?php

/**
 * AjIM - the Asynchronous Javascript Instant Messenger
 * A shoutbox/chatbox framework that uses PHP, AJAX, MySQL, and Javascript
 * Version: 1.0 RC 1
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details. 
 */

error_reporting(E_ALL);
class ajim {
  var $table_prefix, $conn, $id, $admin, $iface, $prune, $formatfunc, $config, $bad_words;
  /**
   * Die and be friendly about it.
   * @param string $text - should be the text to show to the user, include mysql_error() value if applicable
   */
  function kill($text) {
    die('AjIM: Database error<br />'.$text);
  }
  /**
   * Make a SQL query. This function contains some error correction that performs automatic database upgrades if needed.
   * @param string $q - The query text to send to MySQL.
   * @return resource - or, kills the connection and bails out if the query failed
   */
  function sql($q) {
    $r = mysql_query($q, $this->conn);
    if(!$r)
    {
      if(strstr(mysql_error(), 'Unknown column \'time_id\''))
      {
        $this->sql('ALTER TABLE '.$this->table_prefix.'ajim ADD COLUMN time_id int(11) NOT NULL DEFAULT 0;');
        $r = mysql_query($q, $this->conn);
      }
      elseif(strstr(mysql_error(), 'Unknown column \'sid\''))
      {
        $this->sql('ALTER TABLE '.$this->table_prefix.'ajim ADD COLUMN sid varchar(40) NOT NULL DEFAULT \'\';');
        $r = mysql_query($q, $this->conn);
      }
      elseif(strstr(mysql_error(), 'Unknown column \'ip_addr\''))
      {
        $this->sql('ALTER TABLE '.$this->table_prefix.'ajim ADD COLUMN ip_addr varchar(15) NOT NULL DEFAULT \'\';');
        $r = mysql_query($q, $this->conn);
      }
      $this->kill('Error during query:<br /><pre>'.htmlspecialchars($q).'</pre><br />MySQL said: '.mysql_error().'<br /><br />Depending on the error, AjIM may be able to automatically repair it. Just hang tight for about ten seconds. Whatever you do, don\'t close this browser window!');
    }
    return $r;
  }
  /**
   * Get the user's SID (unique ID used for editing authorization) or generate a new one.
   * @return string
   */
  function get_sid()
  {
    // Tag the user with a unique ID that can be used to edit posts
    // This is used to essentially track users, but only for the purpose of letting them edit posts
    if(!isset($_COOKIE['ajim_sid']))
    {
      $hash = sha1(microtime());
      setcookie('ajim_sid', $hash, time()+60*60*24*365); // Cookies last for one year
    }
    else
      $hash = $_COOKIE['ajim_sid'];
      
    return $hash;
  }
  /**
   * Set the default value for a configuration field.
   * @param string $key - name of the configuration key
   * @param string $value - the default value
   * @param array $confarray - needs to be the array passed as the first param on the constructor
   */
  function config_default($key, $value, &$confarray)
  {
    if(!isset($confarray[$key]))
      $confarray[$key] = $value;
  }
  /**
   * Set up some basic vars and a database connection
   * @param array $config - a configuration array, with either the key db_connection_handle (a valid MySQL connection resource) or the keys dbhost, dbname, dbuser, and dbpass
   * @param string $table_prefix - the text prepended to the "ajim" table, should match ^([A-z0-9_]+)$
   * @param string $handler - URL to the backend script, for example in Enano this would be the plugin file plugins/ajim.php
   * @param string $admin - string containing the MD5 hash of the user's password, IF AND ONLY IF the user should be allowed to use the moderation function. In all other cases this should be false.
   * @param string $id - used to carry over the randomly generated instance ID between requests. Should be false if the class is being initialized for displaying the inital HTML, in all other cases should be the value of the class variable AjIM::$id
   * @param bool $can_post - true if the user is allowed to post, false otherwise. Defaults to true.
   * @param mixed $formatfunc - a string containing the name of a function that can be called to format text before posts are sent to the user. If you need to call a class method, this should be an array with key 0 being the class name and key 1 being the method name.
   */
  function __construct($config, $table_prefix, $handler, $admin = false, $id = false, $can_post = true, $formatfunc = false) {
    // CONFIGURATION
    // $this->prune: a limit on the number of posts in the chat box. Usually this should be set to 40 or 50. Default is 40.
    // Set to -1 to disable pruning.
    $this->prune = -1;
    
    $this->get_sid();
    
    if(!is_array($config))
      $this->kill('$config passed to the AjIM constructor should be an associative array with either the keys dbhost, dbname, dbuser, and dbpass, or the key db_connection_handle.');
    if(isset($config['db_connection_handle']))
    {
      if(!is_resource($config['db_connection_handle'])) $this->kill('$config[\'db_connection_handle\'] is not a valid resource');
      $this->conn = $config['db_connection_handle'];
      if(!$this->conn) $this->kill('Error verifying database connection: '.mysql_error());
    } elseif(isset($config['dbhost']) && isset($config['dbname']) && isset($config['dbuser']) && isset($config['dbpass'])) {
      $this->conn = mysql_connect($config['dbhost'], $config['dbuser'], $config['dbpass']);
      if(!$this->conn) $this->kill('Error connecting to the database: '.mysql_error());
      $this->sql('USE '.$config['dbname']);
    }
    
    $this->bad_words = Array('viagra', 'phentermine', 'pharma', 'rolex', 'genital', 'penis', 'ranitidine', 'prozac', 'acetaminophen', 'acyclovir', 'ionamin', 'denavir', 'nizoral', 'zoloft', 'estradiol', 'didrex', 'aciphex', 'seasonale', 'allegra', 'lexapro', 'famvir', 'propecia', 'nasacort');
    if(isset($config['bad_words']) && is_array($config['bad_words']))
    {
      $this->bad_words = array_values(array_merge($this->bad_words, $config['bad_words']));
    }
    
    // Don't change these values here - change them by passing values to the config array in this constructor's params!
    $this->config_default('sb_color_background', '#FFFFFF', $config);
    $this->config_default('sb_color_foreground', '#000000', $config);
    $this->config_default('sb_color_editlink',   '#00C000', $config);
    $this->config_default('sb_color_deletelink', '#FF0000', $config);
    $this->config_default('sb_color_userlink',   '#0000FF', $config);
    
    $this->config = $config;
    
    if($id) $this->id = $id;
    else    $this->id = 'ajim_'.time();
    $this->admin = $admin;
    $this->formatfunc = $formatfunc;
    $this->can_post = $can_post;
    $this->table_prefix = $table_prefix;
    $this->sql('CREATE TABLE IF NOT EXISTS '.$this->table_prefix.'ajim(
        post_id mediumint(8) NOT NULL auto_increment,
        name text,
        website text,
        post text,
        time_id int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY ( post_id )
      );');
    $this->iface = $handler;
    if(isset($_GET['ajimmode'])) $this->handler();
  }
  /**
   * A dummy function used for PHP4 compatibility.
   * @see AjIM::__construct()
   */
  function ajim($config, $table_prefix, $handler, $admin = false, $id = false, $can_post = true, $formatfunc = false) {
    $this->__construct($config, $table_prefix, $handler, $admin, $id, $can_post, $formatfunc);
  }
  /**
   * Generates the initial HTML UI to be sent to the user, used internally.
   * @access private
   * @param string $ajimPath - path to the AjIM connector (not this file), relative to document root, with initial slash.
   */
  function html($ajimPath) {
    
    $enstr = $this->can_post ? '' : ' disabled="disabled"';
    $html = '';
    $html .= '<script type="text/javascript" src="'.$ajimPath.'/ajim.php?js&amp;id='.$this->id.'&amp;path='.urlencode($this->iface).'&amp;pfx='.$this->table_prefix.'"></script>';
    if($this->admin) {
    $html.= '<script type="text/javascript" src="'.$ajimPath.'/ajim.php?jsadmin&amp;id='.$this->id.'&amp;path='.urlencode($this->iface).'&amp;pfx='.$this->table_prefix.'"></script>';
    }
    $html .= '<div id="'.$this->id.'_master" style="padding: 5%; width: 90%;">
             <div id="'.$this->id.'_c" style="text-align: center; color: '.$this->config['sb_color_foreground'].';
             font-family: arial, sans-serif; font-size: 7pt; background-color: '.$this->config['sb_color_background'].';
             text-align: left; border: 1px solid #000000; border-bottom: none; margin-bottom: 0; padding: 5%; width: 90%;
             height: 200px; clip: rect(0px,auto,200px,0px); overflow: auto;"><noscript><p>You need to have JavaScript support to use this shoutbox.</p></noscript></div>';
      // This is the post form div
    if($this->can_post)
    {
    $html .= '<div style="font-family: arial; font-size: 7pt; margin-top: 0; border: 1px solid #000000; border-top-width: 0; width: 100%;">
            <form action="#" onsubmit="'.$this->id.'_form(); return false;" method="get">
             <table border="0" style="margin: 0; padding: 0; width: 90%;">
              <tr><td><span style="font-family: arial; font-size: 7pt; ">Name:</span></td>   <td><input style="font-family: arial; font-size: 7pt; border: 1px solid #000; height: 15px; width: 65px; padding: 1px;" id="'.$this->id.'_name" name="name"'.$enstr.' /></td></tr>
              <tr><td><span style="font-family: arial; font-size: 7pt; ">Website:</span></td><td><input style="font-family: arial; font-size: 7pt; border: 1px solid #000; height: 15px; width: 65px; padding: 1px;" id="'.$this->id.'_website" name="website"'.$enstr.' /></td></tr>
              <tr><td colspan="2"><span style="font-family: arial; font-size: 7pt; ">Message:</span></td></tr>
              <tr><td colspan="2"><textarea'.$enstr.' rows="2" cols="16" style="width: auto; margin: 0 auto;" id="'.$this->id.'_post" name="post" onkeyup="'.$this->id.'_keyhandler();"></textarea></td></tr>
              <tr><td colspan="2" align="center"><input'.$enstr.' type="submit" value="Submit post" /><br />
              <span style="font-family: arial; font-size: 6pt; color: #000000;">AjIM powered</span></td></tr>
              ';
    $html .= '</table>
            </form>';
    if($this->admin) {
      $html .= '<table border="0" style="margin: 0; padding: 0; width: 90%;" align="center"><tr><td colspan="2" align="center"><span id="'.$this->id.'_admin"><a href="#" onclick="'.$this->id.'_prompt(); return false;">Administration</a></span></td></tr></table>';
    }
    $html.='</div></div>';
    } else {
      $html .= '<div style="font-family: arial; font-size: 7pt; margin: 5px; margin-top: 0; border: 1px solid #000000; border-top: none;">';
      if(isset($this->config['cant_post_notice'])) {
        $html .= '<div style="margin: 0; padding: 5px;">'.$this->config['cant_post_notice'].'</div>';
      }
      $html .= '</div></div>';
    }
    $html.='<script type="text/javascript">
    document.getElementById(\''.$this->id.'_c\').innerHTML = unescape(\'%3Cdiv align="center" style="width:95%;"%3EInitializing...%3C\/div%3E\');';
    if($this->can_post) $html .= 'if('.$this->id.'readCookie("ajim_password") && ( typeof "'.$this->id.'_login_bin" == "string" || typeof "'.$this->id.'_login_bin" == "function" )) {
      '.$this->id.'_login_bin('.$this->id.'readCookie("ajim_password"));
    }
    if('.$this->id.'readCookie("ajim_name")) document.getElementById("'.$this->id.'_name").value = '.$this->id.'readCookie("ajim_name");
    if('.$this->id.'readCookie("ajim_website")) document.getElementById("'.$this->id.'_website").value = '.$this->id.'readCookie("ajim_website");';
    $html .= ''.$this->id.'_refresh();
    </script>';
    
    return $html;
  }
  /**
   * Kills the database connection
   */
  function destroy() {
    mysql_close($this->conn);
  }
  /**
   * Strips all traces of HTML, XML, and PHP from text, and prepares it for being inserted into a MySQL database.
   * @access private
   * @param string $text - the text to sanitize
   * @return string
   */
  function sanitize($text) {
    $text = rawurldecode($text);
    $text = preg_replace('#<(.*?)>#is', '&lt;\\1&gt;', $text);
    $text = str_replace("\n", '<br />', $text);
    $text = mysql_real_escape_string($text);
    return $text;
  }
  /**
   * Scrutinizes a string $text for any traces of the word $word, returns true if the text is clean.
   * For example, if $word is "viagra" and the text contains "\/|@6r/\" this returns false, else you would get true.
   * @access private
   * @param string $text - the text to check
   * @param string $word - word to look for.
   * @return bool
   */
  function spamcheck($text, $word)
  {
    // build an array, with each key containing one letter (equiv. to str_split() in PHP 5)
    $chars = Array();
    for($i=0;$i<strlen($word);$i++)
    {
      $chars[] = substr($word, $i, 1);
    }
    // This is our rule list - all the known substitutions for a given letter (e.g. "\/" in place of "V", etc.), needs to be escaped for regex use
    $subs = Array(
      'a'=>'a|\/\\\\|@',
      'b'=>'b|\|o',
      'c'=>'c|\(|',
      'd'=>'d|o\|',
      'e'=>'e|3',
      'f'=>'f',
      'g'=>'g|6|9',
      'h'=>'h|\|n',
      'i'=>'i|\!|1|\|',
      'j'=>'j|\!|1|\|',
      'k'=>'k|\|<|\|&lt;',
      'l'=>'l|\!|1|\|',
      'm'=>'m|nn|rn',
      'n'=>'n|h|u\\|\\\\\|',
      'o'=>'o|\(\)|0|@',
      'p'=>'p',
      'q'=>'q',
      'r'=>'r|\|\^',
      's'=>'s',
      't'=>'t|\+',
      'u'=>'u|n',
      'v'=>'v|\\\\\/', // "\/"
      'w'=>'w|vv|\\\\\/\\\\\/', // allows for "\/\/"
      'x'=>'x|><|&gt;<|>&lt;|&gt;&lt;',
      'y'=>'y',
      'z'=>'z|\|\\\\\|' // |\|
      );
    $regex = '#([\s]){0,1}';
    foreach($chars as $c)
    {
      $lc = strtolower($c);
      if(isset($subs[$lc]))
      {
        $regex .= '('.$subs[$lc].')';
      } else {
        die('0 $subs['.$lc.'] is not set');
        $regex .= preg_quote($c);
      }
      $regex .= '(.|)';
    }
    $regex .= '([\s]){0,1}#is';
    //echo($word.': '.$regex.'<br />');
    if(preg_match($regex, $text)) return false;
    return true;
  }
  /**
   * Processes AJAX requests. Usually called if $_GET['ajimmode'] is set.
   * @access private
   */
  function handler() {
    if(isset($_GET['ajimmode'])) {
      switch($_GET['ajimmode']) {
      default:
        die('');
        break;
      case 'getsource':
      case 'getpost':
        if(!preg_match('#^([0-9]+)$#', $_GET['p'])) die('SQL injection attempt');
        $q = $this->sql('SELECT post,sid,ip_addr FROM '.$this->table_prefix.'ajim WHERE post_id='.$_GET['p']);
        $r = mysql_fetch_assoc($q);
        if( ( ( isset($_GET['ajim_auth']) && (!$this->admin || ($this->admin != $_GET['ajim_auth']) ) ) || !isset($_GET['ajim_auth']) ) && ( $this->get_sid() != $r['sid'] || $_SERVER['REMOTE_ADDR'] != $r['ip_addr'] ) ) die('Hacking attempt');
        if($_GET['ajimmode']=='getpost')
          if($this->formatfunc)
          {
            $p = @call_user_func($this->formatfunc, $r['post']);
            if($p) $r['post'] = $p;
            unset($p); // Free some memory
          }
        echo $r['post'];
        break;
      case "savepost":
        if(!preg_match('#^([0-9]+)$#', $_POST['p'])) die('SQL injection attempt');
        $q = $this->sql('SELECT sid,ip_addr FROM '.$this->table_prefix.'ajim WHERE post_id='.$_POST['p']);
        $r = mysql_fetch_assoc($q);
        if( ( ( isset($_POST['ajim_auth']) && (!$this->admin || ($this->admin != $_POST['ajim_auth']) ) ) || !isset($_POST['ajim_auth']) ) && ( $this->get_sid() != $r['sid'] || $_SERVER['REMOTE_ADDR'] != $r['ip_addr'] ) ) die('Hacking attempt');
        $post = $this->sanitize($_POST['post']);
        $post = $this->make_clickable($post);
        $post = preg_replace('#_(.*?)_#is', '<i>\\1</i>', $post);
        $post = preg_replace('#\*(.*?)\*#is', '<b>\\1</b>', $post);
        $bad_words = Array('viagra', 'phentermine', 'pharma');
        foreach($bad_words as $w)
        {
          if(!$this->spamcheck($post, $w)) die('<span style="color: red">The word "'.$w.'" has been detected in your message and as a result your post has been blocked.</span> Don\'t argue, that will only get you banned.');
        }
        if(!$this->can_post) die('Access to posting messages has been denied because the administrator has set that you must be logged into this website in order to post.');
        
        $this->sql('UPDATE '.$this->table_prefix.'ajim SET post=\''.$post.'\' WHERE post_id='.$_POST['p'].';');
        
        if($this->formatfunc)
        {
          $p = @call_user_func($this->formatfunc, $post);
          if($p) $post = $p;
          unset($p); // Free some memory
        }    
        die($post);
        break;
      case 'delete':
        if(!preg_match('#^([0-9]+)$#', $_POST['p'])) die('SQL injection attempt');
        $q = $this->sql('SELECT sid,ip_addr FROM '.$this->table_prefix.'ajim WHERE post_id='.$_POST['p']);
        $r = mysql_fetch_assoc($q);
        if( ( ( isset($_POST['ajim_auth']) && (!$this->admin || ($this->admin != $_POST['ajim_auth']) ) ) || !isset($_POST['ajim_auth']) ) && ( $this->get_sid() != $r['sid'] || $_SERVER['REMOTE_ADDR'] != $r['ip_addr'] ) ) die('Hacking attempt'); 
        $this->sql('DELETE FROM '.$this->table_prefix.'ajim WHERE post_id='.$_POST['p']);
        die('good');
        break;
      case 'post':
        if(!preg_match('#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)$#is', $_POST['website'])) $_POST['website']='';
        // Now for a clever anti-spam trick: blacklist the words "viagra" and "phentermine" using one wicked regex:
        // #([\s]){1}(v|\\\\\/)(.*){1}(i|\||l|1)(.*){1}(a|@|\/\\\\)(.*){1}(g|6)(.*){1}r(.*){1}(a|@|\/\\\\)(\s){1}#is
        $name    = $this->sanitize($_POST['name']);
        $website = $this->sanitize($_POST['website']);
        $post    = $this->sanitize($_POST['post']);
        foreach($this->bad_words as $w)
        {
          if(!$this->spamcheck($post, $w)) die('<span style="color: red">The word "'.$w.'" has been detected in your message and as a result your post has been blocked.</span> Don\'t argue, that will only get you banned.');
        }
        $post = $this->make_clickable($post);
        $post = preg_replace('#_(.*?)_#is', '<i>\\1</i>', $post);
        $post = preg_replace('#\*(.*?)\*#is', '<b>\\1</b>', $post);
        if(!$this->can_post) die('Access to posting messages has been denied because the administrator has set that you must be logged into this website in order to post.');
        $this->sql('INSERT INTO '.$this->table_prefix.'ajim ( name, website, post, time_id, sid, ip_addr ) VALUES(\''.$name.'\', \''.$website.'\', \''.$post.'\', '.time().', \''.mysql_real_escape_string($this->get_sid()).'\', \''.mysql_real_escape_string($_SERVER['REMOTE_ADDR']).'\');');
      case 'view':
        // if(isset($_GET['ajim_auth']))
        //   die('Auth: '.$_GET['ajim_auth']); // .'<br />Pw:   '.$this->admin);
        if(isset($_GET['latest']) && ( isset($this->config['allow_looping']) && $this->config['allow_looping'] == true ))
        {
          // Determine max execution time
          $max_exec = intval(@ini_get('max_execution_time'));
          if(!$max_exec) $max_exec = 30;
          $time_left = $max_exec - 1;
        }
        $q = $this->sql('SELECT name, website, post, post_id, time_id, sid, ip_addr FROM '.$this->table_prefix.'ajim ORDER BY post_id;');
        if(mysql_num_rows($q) < 1) echo '0 <span style="color: #666666">No posts.</span>';
        else {
          // Prune the table
          if($this->prune > 0) {
            $nr = mysql_num_rows($q);
            $nr = $nr - $this->prune;
            if($nr > 0) $this->sql('DELETE FROM '.$this->table_prefix.'ajim LIMIT '.$nr.';');
          }
          // Alright, what we want to do here is grab the entire table, load it into an array, and then display the posts in reverse order.
          for($i = 1; $i<=mysql_num_rows($q); $i++) {
            $t[$i] = mysql_fetch_object($q);
          }
          
          $s = sizeof($t);
          
          if(isset($_GET['latest']) && ( isset($this->config['allow_looping']) && $this->config['allow_looping'] == true ))
          {
            // When I was coding this, I immediately thought "use labels and goto!" Here's hoping, PHP6 :-)
            $latest_from_user = intval($_GET['latest']);
            $latest_from_db   = intval($t[$s]->time_id);
            while(true)
            {
              if($latest_from_user == $latest_from_db && $time_left > 5)
              {
                $time_left = $time_left - 5;
                sleep(5);
                mysql_free_result($q);
                $q = $this->sql('SELECT name, website, post, post_id, time_id, sid, ip_addr FROM '.$this->table_prefix.'ajim ORDER BY post_id;');
                $t = Array();
                for($i = 1; $i<=mysql_num_rows($q); $i++) {
                  $t[$i] = mysql_fetch_object($q);
                }
                $s = sizeof($t);
                $latest_from_user = intval($_GET['latest']);
                $latest_from_db   = intval($t[$s]->time_id);
                //echo (string)$latest_from_db.'<br />';
                //flush();
                //exit;
                if($latest_from_user != $latest_from_db)
                  break;
                continue;
              }
              elseif($latest_from_user == $latest_from_db && $time_left < 5)
              {
                die('[E] No new posts');
              }
              break;
            }
          }
          
          echo $t[$s]->time_id . ' ';
          
          // This is my favorite array trick - it baffles everyone who looks at it :-D
          // What it does is the same as for($i=0;$i<sizeof($t);$i++), but it processes the
          // array in reverse order.
          
          for($i = $s; $i > 0; $i--) {
            if($this->formatfunc)
            {
              $p = @call_user_func($this->formatfunc, $t[$i]->post);
              if($p) $t[$i]->post = $p;
              unset($p); // Free some memory
              $good_tags = Array('b', 'i', 'u', 'br');
              $gt = implode('|', $good_tags);
              
              // Override any modifications that may have been made to the HTML
              $t[$i]->post = preg_replace('#&lt;('.$gt.')&gt;([^.]+)&lt;/\\1&gt;#is', '<\\1>\\2</\\1>', $t[$i]->post);
              $t[$i]->post = preg_replace('#&lt;('.$gt.')([ ]*?)/&gt;#is', '<\\1 />', $t[$i]->post);
              $t[$i]->post = preg_replace('#&lt;('.$gt.')&gt;#is', '<\\1 />', $t[$i]->post);
            }
            echo '<div style="border-bottom: 1px solid #BBB; width: 98%;"><table border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td><span style="font-weight: bold">';
            if($t[$i]->website != '') echo '<a href="'.$t[$i]->website.'" style="color: #0000FF">'.$t[$i]->name.'</a>';
            else echo ''.$t[$i]->name.'';
            echo '</span> ';
            if( $this->can_post && ($t[$i]->sid == $this->get_sid() && $t[$i]->ip_addr == $_SERVER['REMOTE_ADDR'] ) || ( isset($_GET['ajim_auth']) && $_GET['ajim_auth']==$this->admin ) )
            echo '</td><td style="text-align: right"><a href="#" onclick="void('.$this->id.'_delete_post(\''.$t[$i]->post_id.'\')); return false;" style="color: '.$this->config['sb_color_deletelink'].'">Delete</a> <a href="javascript:void('.$this->id.'_edit_post(\''.$t[$i]->post_id.'\'));" id="'.$this->id.'_editbtn_'.$t[$i]->post_id.'" style="color: '.$this->config['sb_color_editlink'].'">Edit</a>';
            echo '</td></tr></table><span style="color: #CCC; font-style: italic;">Posted on '.date('n/j, g:ia', $t[$i]->time_id).'</span></div>';
            echo '<div style="border-bottom: 1px solid #CCC; width: 98%;" id="'.$this->id.'_post_'.$t[$i]->post_id.'">'.$t[$i]->post.'</div>';
            echo '<br />';
          }
        }
        break;
      case 'auth':
        if($_POST['ajim_auth']==$this->admin) echo 'good';
        else echo 'The password you entered is invalid.';
        break;
      }
    }
  }
  
  /**
   * Replace URLs within a block of text with anchors
   * Written by Nathan Codding, copyright (C) phpBB Group
   * @param string $text - the text to process
   * @return string
   */
  function make_clickable($text)
  {
    $text = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1&#058;", $text);
    $ret = ' ' . $text;
    $ret = preg_replace('#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is', '\\1<a href="\\2" target="_blank">\\2</a>', $ret);
    $ret = preg_replace("#(^|[\ n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1<a href="http://\\2" target="_blank">\\2</a>', $ret);
    $ret = preg_replace("#(^|[\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", '\\1<a href="mailto:\\2@\\3">\\2@\\3</a>', $ret);
    $ret = substr($ret, 1);
    return($ret);
  }
}

// The client-side javascript and CSS code

if(isset($_GET['js']) && isset($_GET['id']) && isset($_GET['path']) && isset($_GET['pfx'])) {
  header('Content-type: text/javascript');
  ?>
  // <script>
  var <?php echo $_GET['id']; ?>id='<?php echo $_GET['id']; ?>';
  var path='<?php echo $_GET['path']; ?>';
  var pfx='<?php echo $_GET['pfx']; ?>';
  var authed = false; // Don't even try to hack this var; it contains the MD5 of the password that *you* enter, setting it to true will just botch up all the requests
                      // authed is always set to false unless your password has been verified by the server, and it is sent to the server with every request.
  var shift;
  var <?php echo $_GET['id']; ?>editlist = new Array();
  var <?php echo $_GET['id']; ?>_latestpost = 0;
  var <?php echo $_GET['id']; ?>_allowrequest = true;
  
  var <?php echo $_GET['id']; ?>_refcount = 0;
  var <?php echo $_GET['id']; ?>_refcount_current = 0;
  
  var <?php echo $_GET['id']; ?>interval = setInterval('<?php echo $_GET['id']; ?>_refresh();', 5000);
  var ajim_editlevels = 0;
                      
  // Add the AjIM stylesheet to the HTML header
  var link = document.createElement('link');
  link.href = path+'?title=null&css&id='+<?php echo $_GET['id']; ?>id+'&path='+path+'&pfx='+pfx+'&ajimmode=';
  link.rel  = 'stylesheet';
  link.type = 'text/css';
  var head = document.getElementsByTagName('head');
  head = head[0];
  head.appendChild(link);
  
  if(typeof window.onload == 'function')
    var __ajim_oltemp = window.onload;
  else
    var __ajim_oltemp = function(e) { };
  window.onload = function(e)
  {
    if(document.getElementById('<?php echo $_GET['id']; ?>_post'))
    {
      document.getElementById('<?php echo $_GET['id']; ?>_post').onkeyup = function(e) { <?php echo $_GET['id']; ?>_keyhandler(e); };
    }
    __ajim_oltemp(e);
  }
  
  function <?php echo $_GET['id']; ?>readCookie(name) {var nameEQ = name + "=";var ca = document.cookie.split(';');for(var i=0;i < ca.length;i++){var c = ca[i];while (c.charAt(0)==' ') c = c.substring(1,c.length);if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);}return null;}
  function <?php echo $_GET['id']; ?>setCookie(name,value,days){if (days){var date = new Date();date.setTime(date.getTime()+(days*24*60*60*1000));var expires = "; expires="+date.toGMTString();}else var expires = "";document.cookie = name+"="+value+expires+"; path=/";}
  function <?php echo $_GET['id']; ?>eraseCookie(name) {createCookie(name,"",-1);}
  
  function strpos(haystack, needle)
  {
    if(typeof(haystack) != 'string') return false;
    if(typeof(needle) != 'string')   return false;
    len = needle.length;
    for(i=0;i<haystack.length;i++)
    {
      if ( haystack.substr(i, len) == needle )
        return i;
    }
    return 0;
  }
                      
  function <?php echo $_GET['id']; ?>_newReq(what2call) {
    if (window.XMLHttpRequest) {
      request = new XMLHttpRequest();
    } else {
      if (window.ActiveXObject) {           
        request = new ActiveXObject("Microsoft.XMLHTTP");
      } else {
        alert('Your browser does not support AJAX. Get Firefox 2.0!');
        return false;
      }
    }
    request.onreadystatechange = what2call;
    return request;
  }
  
  function <?php echo $_GET['id']; ?>_refresh(force) {
    <?php echo $_GET['id']; ?>_refcount++;
    <?php echo $_GET['id']; ?>_refcount_current = <?php echo $_GET['id']; ?>_refcount;
    if(!<?php echo $_GET['id']; ?>_allowrequest && !force)
      return false;
    <?php echo $_GET['id']; ?>_allowrequest = false;
    var r = <?php echo $_GET['id']; ?>_newReq(function() {
       if(r.readyState == 4)
       {
         // Prevent an old request from taking over a more recent one
         if(<?php echo $_GET['id']; ?>_refcount > <?php echo $_GET['id']; ?>_refcount_current)
           return;
         if(r.responseText != '[E] No new posts')
         {
           time = r.responseText.substr(0, strpos(r.responseText, ' '));
           <?php echo $_GET['id']; ?>_latestpost = parseInt(time);
           text = r.responseText.substr(strpos(r.responseText, ' ')+1, r.responseText.length);
           document.getElementById('<?php echo $_GET['id']; ?>_c').innerHTML = text;
         }
         <?php echo $_GET['id']; ?>_allowrequest = true;
       }
    });
    if(force)
      latest = '';
    else
      latest = '&latest='+<?php echo $_GET['id']; ?>_latestpost;
    if(authed) r.open('GET', path+'?title=null&ajimmode=view&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+latest+'&ajim_auth='+authed, true);
    else       r.open('GET', path+'?title=null&ajimmode=view&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+latest, true);
    r.send(null);
  }
  
  function <?php echo $_GET['id']; ?>_submit(name, website, post) {
    var r = <?php echo $_GET['id']; ?>_newReq(function() {
       if(r.readyState == 4)
       {
         if(r.responseText != '[E] No new posts')
         {
           if(parseInt(r.responseText.substr(0,1)) != 0)
           {
             time = r.responseText.substr(0, strpos(r.responseText, ' '));
             <?php echo $_GET['id']; ?>_latestpost = parseInt(time);
             text = r.responseText.substr(strpos(r.responseText, ' ')+1, r.responseText.length);
           }
           else
           {
             text = r.responseText;
           }
           document.getElementById('<?php echo $_GET['id']; ?>_c').innerHTML = text;
         }
       }
    })
    if(authed) var parms = 'name='+name+'&website='+website+'&post='+post+'&ajim_auth='+authed;
    else       var parms = 'name='+name+'&website='+website+'&post='+post;
    r.open('POST', path+'?title=null&ajimmode=post&id='+<?php echo $_GET['id']; ?>id+'', true);
    r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    r.setRequestHeader("Content-length", parms.length);
    r.setRequestHeader("Connection", "close");
    r.send(parms);
  }
  
  function <?php echo $_GET['id']; ?>_form() {
    var name = document.getElementById(<?php echo $_GET['id']; ?>id+'_name').value;
    var website = document.getElementById(<?php echo $_GET['id']; ?>id+'_website').value;
    var post = document.getElementById(<?php echo $_GET['id']; ?>id+'_post').value;
    if(name.length < 1) { alert('Please enter your name.'); return; }
    if(post.length < 1) { alert('Please enter a post.'); return; }
    <?php echo $_GET['id']; ?>setCookie('ajim_name', name, 60*60*24*365*10);
    <?php echo $_GET['id']; ?>setCookie('ajim_website', website, 60*60*24*365*10);
    <?php echo $_GET['id']; ?>_submit(name, website, post);
    document.getElementById(<?php echo $_GET['id']; ?>id+'_post').value = '';
  }
  
  
  function <?php echo $_GET['id']; ?>_keyhandler(e)
  {
    if(!e) e = window.event;
    if(e.keyCode == 13)
    {
      val = document.getElementById(<?php echo $_GET['id']; ?>id+'_post').value;
      if(!shift)
      {
        document.getElementById(<?php echo $_GET['id']; ?>id+'_post').value = val.substr(0, val.length - 1);
        <?php echo $_GET['id']; ?>_form();
      }
    }
  }
  
  function <?php echo $_GET['id']; ?>keysensor(event)
  {
    if (event.shiftKey==1)
    {
      shift = true;
    }
    else
    {
      shift = false;
    }
  }
  
  if(window.onkeydown)
  {
    var kttemp = window.onkeydown;
    window.onkeydown = function(e) { kttemp(e); <?php echo $_GET['id']; ?>keysensor(e); }
  } else {
    window.onkeydown = function(e) { <?php echo $_GET['id']; ?>keysensor(e); }
  }
  
  if(window.onkeyup)
  {
    var kttemp = window.onkeyup;
    window.onkeyup = function(e) { kttemp(e); <?php echo $_GET['id']; ?>keysensor(e); }
  } else {
    window.onkeyup = function(e) { <?php echo $_GET['id']; ?>keysensor(e); }
  }
  
  function <?php echo $_GET['id']; ?>_edit_post(pid)
  {
    if(<?php echo $_GET['id']; ?>editlist[pid])
    {
      var r = <?php echo $_GET['id']; ?>_newReq(function() {
        if(r.readyState == 4) {
           document.getElementById('<?php echo $_GET['id']; ?>_post_'+pid).innerHTML = r.responseText;
           document.getElementById('<?php echo $_GET['id']; ?>_editbtn_'+pid).innerHTML = 'Edit';
           ajim_editlevels--;
           <?php echo $_GET['id']; ?>editlist[pid] = false;
           if(ajim_editlevels < 1)
            {
              <?php echo $_GET['id']; ?>interval = setInterval('<?php echo $_GET['id']; ?>_refresh();', 5000);
            }
        }
      });
      if(authed) r.open('GET', path+'?title=null&ajimmode=getpost&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+'&p='+pid+'&ajim_auth='+authed, true);
      else       r.open('GET', path+'?title=null&ajimmode=getpost&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+'&p='+pid, true);
      r.send(null);
    } else {
      clearInterval(<?php echo $_GET['id']; ?>interval);
      var r = <?php echo $_GET['id']; ?>_newReq(function() {
        if(r.readyState == 4) {
           document.getElementById('<?php echo $_GET['id']; ?>_post_'+pid).innerHTML = '<textarea rows="4" cols="17" id="<?php echo $_GET['id']; ?>_editor_'+pid+'">'+r.responseText+'</textarea><br /><a href="#" onclick="<?php echo $_GET['id']; ?>_save_post(\''+pid+'\'); return false;" style="font-size: 7pt; color: #00C000;">save</a>';
           document.getElementById('<?php echo $_GET['id']; ?>_editbtn_'+pid).innerHTML = 'Cancel';
           ajim_editlevels++;
           <?php echo $_GET['id']; ?>editlist[pid] = true;
        }
      });
      if(authed) r.open('GET', path+'?title=null&ajimmode=getsource&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+'&p='+pid+'&ajim_auth='+authed, true);
      else       r.open('GET', path+'?title=null&ajimmode=getsource&id='+<?php echo $_GET['id']; ?>id+'&pfx='+pfx+'&p='+pid, true);
      r.send(null);
    }
  }
  
  var ajim_global_pid;
  function <?php echo $_GET['id']; ?>_save_post(pid) {
    ajim_global_pid = pid;
    if(!document.getElementById('<?php echo $_GET['id']; ?>_editor_'+pid))
    {
      alert('AjIM internal error: bad post ID '+pid+': editor is not open');
      return false;
    }
    var r = <?php echo $_GET['id']; ?>_newReq(function() {
      if(r.readyState == 4)
      {
        ajim_editlevels--;
        <?php echo $_GET['id']; ?>editlist[pid] = false;
        document.getElementById('<?php echo $_GET['id']; ?>_editbtn_'+ajim_global_pid).innerHTML = 'Edit';
        document.getElementById('<?php echo $_GET['id']; ?>_post_'+ajim_global_pid).innerHTML = r.responseText;
        if(ajim_editlevels < 1)
        {
          <?php echo $_GET['id']; ?>_refresh(true);
          <?php echo $_GET['id']; ?>interval = setInterval('<?php echo $_GET['id']; ?>_refresh();', 5000);
        }
      }
    });
    var parms = 'post='+escape(document.getElementById('<?php echo $_GET['id']; ?>_editor_'+pid).value.replace('+', '%2B'))+'&ajim_auth='+authed+'&p='+pid;
    r.open('POST', path+'?title=null&ajimmode=savepost&id='+<?php echo $_GET['id']; ?>id+'', true);
    r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    r.setRequestHeader("Content-length", parms.length);
    r.setRequestHeader("Connection", "close");
    r.send(parms);
    return null;
  }
  
  function <?php echo $_GET['id']; ?>_delete_post(pid) {
    //document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML = '<span style="font-family: arial; font-size: 7pt; ">Loading...</span>';
    var r = <?php echo $_GET['id']; ?>_newReq(function() {
       if(r.readyState == 4)
         if(r.responseText=="good") {
           <?php echo $_GET['id']; ?>_refresh(true);
         } else alert(r.responseText);
    });
    var parms = 'ajim_auth='+authed+'&p='+pid;
    r.open('POST', path+'?title=null&ajimmode=delete&id='+<?php echo $_GET['id']; ?>id+'', true);
    r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    r.setRequestHeader("Content-length", parms.length);
    r.setRequestHeader("Connection", "close");
    r.send(parms);
    return null;
  }
  
  <?php
} elseif(isset($_GET['jsadmin']) && isset($_GET['id']) && isset($_GET['path'])) {
  header('Content-type: text/javascript');
  ?>
  
  var abuffer;
  function <?php echo $_GET['id']; ?>_prompt() {
    abuffer = document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML;
    document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML = '<form action="javascript:void(0)" onsubmit="'+<?php echo $_GET['id']; ?>id+'_login()" method="get"><span style="font-family: arial; font-size: 7pt; ">Password:</span>  <input style="font-family: arial; font-size: 7pt; border: 1px solid #000; height: 15px; width: 65px" id="'+<?php echo $_GET['id']; ?>id+'_passfield" name="pass" type="password" /> <input style="font-family: arial; font-size: 7pt; border: 1px solid #000; height: 15px; width: 65px" type="submit" value="OK" /></form>';
  }
  
  function <?php echo $_GET['id']; ?>_login() {
    pass = document.getElementById(<?php echo $_GET['id']; ?>id+'_passfield').value;
    pass = hex_md5(pass);
    <?php echo $_GET['id']; ?>_login_bin(pass);
  }
  function <?php echo $_GET['id']; ?>_login_bin(pass) {
    document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML = '<span style="font-family: arial; font-size: 7pt; ">Loading...</span>';
    var r = <?php echo $_GET['id']; ?>_newReq(function() {
       if(r.readyState == 4)
       {
         if(r.responseText=="good") {
           authed = pass;
           <?php echo $_GET['id']; ?>setCookie('ajim_password', authed, 60*60*24*365*10);
           <?php echo $_GET['id']; ?>_latestpost = 0;
           <?php echo $_GET['id']; ?>_refresh(true);
           document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML = '';
         }
         else
         {
           alert(r.responseText); 
           document.getElementById(<?php echo $_GET['id']; ?>id+'_admin').innerHTML = '<span style="font-family: arial; font-size: 7pt; color: #ff0000">Invalid password!</span><br />'+abuffer;
         }
       }
    })
    var parms = 'ajim_auth='+pass;
    r.open('POST', path+'?title=null&ajimmode=auth&id='+<?php echo $_GET['id']; ?>id+'', true);
    r.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    r.setRequestHeader("Content-length", parms.length);
    r.setRequestHeader("Connection", "close");
    r.send(parms);
  }
  
  var hexcase = 0; var b64pad  = ""; var chrsz   = 8; function hex_md5(s){ return binl2hex(core_md5(str2binl(s), s.length * chrsz));}; function b64_md5(s){ return binl2b64(core_md5(str2binl(s), s.length * chrsz));}; function str_md5(s){ return binl2str(core_md5(str2binl(s), s.length * chrsz));}; function hex_hmac_md5(key, data) { return binl2hex(core_hmac_md5(key, data)); }; function b64_hmac_md5(key, data) { return binl2b64(core_hmac_md5(key, data)); }; function str_hmac_md5(key, data) { return binl2str(core_hmac_md5(key, data)); }; function md5_vm_test() { return hex_md5("abc") == "900150983cd24fb0d6963f7d28e17f72"; }; function core_md5(x, len) { x[len >> 5] |= 0x80 << ((len) % 32); x[(((len + 64) >>> 9) << 4) + 14] = len; var a =  1732584193; var b = -271733879; var c = -1732584194; var d =  271733878; for(var i = 0; i < x.length; i += 16) { var olda = a; var oldb = b; var oldc = c; var oldd = d; a = md5_ff(a, b, c, d, x[i+ 0], 7 , -680876936);d = md5_ff(d, a, b, c, x[i+ 1], 12, -389564586);c = md5_ff(c, d, a, b, x[i+ 2], 17,  606105819);b = md5_ff(b, c, d, a, x[i+ 3], 22, -1044525330);a = md5_ff(a, b, c, d, x[i+ 4], 7 , -176418897);d = md5_ff(d, a, b, c, x[i+ 5], 12,  1200080426);c = md5_ff(c, d, a, b, x[i+ 6], 17, -1473231341);b = md5_ff(b, c, d, a, x[i+ 7], 22, -45705983);a = md5_ff(a, b, c, d, x[i+ 8], 7 ,  1770035416);d = md5_ff(d, a, b, c, x[i+ 9], 12, -1958414417);c = md5_ff(c, d, a, b, x[i+10], 17, -42063);b = md5_ff(b, c, d, a, x[i+11], 22, -1990404162);a = md5_ff(a, b, c, d, x[i+12], 7 ,  1804603682);d = md5_ff(d, a, b, c, x[i+13], 12, -40341101);c = md5_ff(c, d, a, b, x[i+14], 17, -1502002290);b = md5_ff(b, c, d, a, x[i+15], 22,  1236535329);a = md5_gg(a, b, c, d, x[i+ 1], 5 , -165796510);d = md5_gg(d, a, b, c, x[i+ 6], 9 , -1069501632);c = md5_gg(c, d, a, b, x[i+11], 14,  643717713);b = md5_gg(b, c, d, a, x[i+ 0], 20, -373897302);a = md5_gg(a, b, c, d, x[i+ 5], 5 , -701558691);d = md5_gg(d, a, b, c, x[i+10], 9 ,  38016083);c = md5_gg(c, d, a, b, x[i+15], 14, -660478335);b = md5_gg(b, c, d, a, x[i+ 4], 20, -405537848);a = md5_gg(a, b, c, d, x[i+ 9], 5 ,  568446438);d = md5_gg(d, a, b, c, x[i+14], 9 , -1019803690);c = md5_gg(c, d, a, b, x[i+ 3], 14, -187363961);b = md5_gg(b, c, d, a, x[i+ 8], 20,  1163531501);a = md5_gg(a, b, c, d, x[i+13], 5 , -1444681467);d = md5_gg(d, a, b, c, x[i+ 2], 9 , -51403784);c = md5_gg(c, d, a, b, x[i+ 7], 14,  1735328473);b = md5_gg(b, c, d, a, x[i+12], 20, -1926607734);a = md5_hh(a, b, c, d, x[i+ 5], 4 , -378558);d = md5_hh(d, a, b, c, x[i+ 8], 11, -2022574463);c = md5_hh(c, d, a, b, x[i+11], 16,  1839030562);b = md5_hh(b, c, d, a, x[i+14], 23, -35309556);a = md5_hh(a, b, c, d, x[i+ 1], 4 , -1530992060);d = md5_hh(d, a, b, c, x[i+ 4], 11,  1272893353);c = md5_hh(c, d, a, b, x[i+ 7], 16, -155497632);b = md5_hh(b, c, d, a, x[i+10], 23, -1094730640);a = md5_hh(a, b, c, d, x[i+13], 4 ,  681279174);d = md5_hh(d, a, b, c, x[i+ 0], 11, -358537222);c = md5_hh(c, d, a, b, x[i+ 3], 16, -722521979);b = md5_hh(b, c, d, a, x[i+ 6], 23,  76029189);a = md5_hh(a, b, c, d, x[i+ 9], 4 , -640364487);d = md5_hh(d, a, b, c, x[i+12], 11, -421815835);c = md5_hh(c, d, a, b, x[i+15], 16,  530742520);b = md5_hh(b, c, d, a, x[i+ 2], 23, -995338651);a = md5_ii(a, b, c, d, x[i+ 0], 6 , -198630844);d = md5_ii(d, a, b, c, x[i+ 7], 10,  1126891415);c = md5_ii(c, d, a, b, x[i+14], 15, -1416354905);b = md5_ii(b, c, d, a, x[i+ 5], 21, -57434055);a = md5_ii(a, b, c, d, x[i+12], 6 ,  1700485571);d = md5_ii(d, a, b, c, x[i+ 3], 10, -1894986606);c = md5_ii(c, d, a, b, x[i+10], 15, -1051523);b = md5_ii(b, c, d, a, x[i+ 1], 21, -2054922799);a = md5_ii(a, b, c, d, x[i+ 8], 6 ,  1873313359);d = md5_ii(d, a, b, c, x[i+15], 10, -30611744);c = md5_ii(c, d, a, b, x[i+ 6], 15, -1560198380);b = md5_ii(b, c, d, a, x[i+13], 21,  1309151649);a = md5_ii(a, b, c, d, x[i+ 4], 6 , -145523070);d = md5_ii(d, a, b, c, x[i+11], 10, -1120210379);c = md5_ii(c, d, a, b, x[i+ 2], 15,  718787259);b = md5_ii(b, c, d, a, x[i+ 9], 21, -343485551); a = safe_add(a, olda); b = safe_add(b, oldb); c = safe_add(c, oldc); d = safe_add(d, oldd); } return Array(a, b, c, d); }; function md5_cmn(q, a, b, x, s, t) { return safe_add(bit_rol(safe_add(safe_add(a, q), safe_add(x, t)), s),b); }; function md5_ff(a, b, c, d, x, s, t) { return md5_cmn((b & c) | ((~b) & d), a, b, x, s, t); }; function md5_gg(a, b, c, d, x, s, t) { return md5_cmn((b & d) | (c & (~d)), a, b, x, s, t); }; function md5_hh(a, b, c, d, x, s, t) { return md5_cmn(b ^ c ^ d, a, b, x, s, t); }; function md5_ii(a, b, c, d, x, s, t) { return md5_cmn(c ^ (b | (~d)), a, b, x, s, t); }; function core_hmac_md5(key, data) { var bkey = str2binl(key); if(bkey.length > 16) bkey = core_md5(bkey, key.length * chrsz); var ipad = Array(16), opad = Array(16); for(var i = 0; i < 16; i++) { ipad[i] = bkey[i] ^ 0x36363636; opad[i] = bkey[i] ^ 0x5C5C5C5C; } var hash = core_md5(ipad.concat(str2binl(data)), 512 + data.length * chrsz); return core_md5(opad.concat(hash), 512 + 128); }; function safe_add(x, y) {var lsw = (x & 0xFFFF) + (y & 0xFFFF);var msw = (x >> 16) + (y >> 16) + (lsw >> 16);return (msw << 16) | (lsw & 0xFFFF); }; function bit_rol(num, cnt) { return (num << cnt) | (num >>> (32 - cnt)); }; function str2binl(str) { var bin = Array(); var mask = (1 << chrsz) - 1; for(var i = 0; i < str.length * chrsz; i += chrsz) bin[i>>5] |= (str.charCodeAt(i / chrsz) & mask) << (i%32); return bin; }; function binl2str(bin) { var str = ""; var mask = (1 << chrsz) - 1; for(var i = 0; i < bin.length * 32; i += chrsz) str += String.fromCharCode((bin[i>>5] >>> (i % 32)) & mask); return str; }; function binl2hex(binarray) { var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef"; var str = ""; for(var i = 0; i < binarray.length * 4; i++) { str += hex_tab.charAt((binarray[i>>2] >> ((i%4)*8+4)) & 0xF) + hex_tab.charAt((binarray[i>>2] >> ((i%4)*8  )) & 0xF); } return str; }; function binl2b64(binarray) { var tab = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/"; var str = ""; for(var i = 0; i < binarray.length * 4; i += 3) { var triplet = (((binarray[i >> 2] >> 8 * ( i   %4)) & 0xFF) << 16) | (((binarray[i+1 >> 2] >> 8 * ((i+1)%4)) & 0xFF) << 8 ) |  ((binarray[i+2 >> 2] >> 8 * ((i+2)%4)) & 0xFF); for(var j = 0; j < 4; j++) { if(i * 8 + j * 6 > binarray.length * 32) str += b64pad; else str += tab.charAt((triplet >> 6*(3-j)) & 0x3F); } } return str; };
  
  <?php
} elseif(isset($_GET['css']) && isset($_GET['id']) && isset($_GET['path'])) {
  header('Content-type: text/css');
  ?>
  div#<?php echo $_GET['id']; ?>_master {
    margin: 0;
    padding: 0;
    /* background-color: #DDD; */
  }
  div#<?php echo $_GET['id']; ?>_master a {
    display: inline;
    color: #0000FF;
  }
  div#<?php echo $_GET['id']; ?>_master textarea {
    font-family: arial;
    font-size: 7pt;
    border: 1px solid #000;
    padding: 0;
  }
  <?php
}
?>
