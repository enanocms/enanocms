<?php
/*
Plugin Name: Special page-related pages
Plugin URI: http://enanocms.org/
Description: Provides the page Special:CreatePage, which can be used to create new pages. Also adds the About Enano and GNU General Public License pages.
Author: Dan Fuhry
Version: 1.0
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 2
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('base_classes_initted', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'Create page\',
      \'urlname\'=>\'CreatePage\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'All pages\',
      \'urlname\'=>\'AllPages\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'List of special pages\',
      \'urlname\'=>\'SpecialPages\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'About Enano\',
      \'urlname\'=>\'About_Enano\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'GNU General Public License\',
      \'urlname\'=>\'GNU_General_Public_License\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

// function names are IMPORTANT!!! The name pattern is: page_<namespace ID>_<page URLname, without namespace>

function page_Special_CreatePage()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( isset($_POST['do']) )
  {
    $p = $_POST['pagename'];
    $k = array_keys($paths->nslist);
    for ( $i = 0; $i < sizeof( $paths->nslist ); $i++ )
    {
      $ln = strlen( $paths->nslist[$k[$i]] );
      if ( substr($p, 0, $ln) == $paths->nslist[$k[$i]] )
      {
        $namespace = $k[$i];
      }
    }
    if ( $namespace == 'Special' || ( $namespace == 'System' && $session->user_level < USER_LEVEL_ADMIN ) || $namespace == 'Admin')
    {
      $template->header();
      
      echo '<h3>The page could not be created.</h3><p>The name "'.$p.'" is invalid.</p>';
      
      $template->footer();
      $db->close();
      
      exit;
    }
    $name = $db->escape(str_replace('_', ' ', $p));
    $urlname = str_replace(' ', '_', $p);
    $namespace = $_POST['namespace'];
    if ( $namespace == 'Special' || ( $namespace == 'System' && $session->user_level < USER_LEVEL_ADMIN ) || $namespace == 'Admin')
    {
      $template->header();
      
      echo '<h3>The page could not be created.</h3><p>The name "'.$paths->nslist[$namespace].$p.'" is invalid.</p>';
      
      $template->footer();
      $db->close();
      
      exit;
    }
    
    $tn = $paths->nslist[$_POST['namespace']] . $urlname;
    if ( isset($paths->pages[$tn]) )
    {
      die_friendly('Error creating page', '<p>The page already exists.</p>');
    }
    
    if ( $paths->nslist[$namespace] == substr($urlname, 0, strlen($paths->nslist[$namespace]) ) )
    {
      $urlname = substr($urlname, strlen($paths->nslist[$namespace]), strlen($urlname));
    }
    
    $k = array_keys( $paths->nslist );
    if(!in_array($_POST['namespace'], $k))
    {
      $db->_die('An SQL injection attempt was caught at '.dirname(__FILE__).':'.__LINE__.'.');
    }
    
    $urlname = sanitize_page_id($urlname);
    $urlname = $db->escape($urlname);
    
    $perms = $session->fetch_page_acl($urlname, $namespace);
    if ( !$perms->get_permissions('create_page') )
      die_friendly('Error creating page', '<p>An access control rule is preventing you from creating pages.</p>');
    
    $q = $db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace) VALUES('.time().', \''.date('d M Y h:i a').'\', \'page\', \'create\', \''.$session->username.'\', \''.$urlname.'\', \''.$_POST['namespace'].'\');');
    if ( !$q )
    {
      $db->_die('The page log could not be updated.');
    }
    
    $q = $db->sql_query('INSERT INTO '.table_prefix.'pages(name,urlname,namespace) VALUES(\''.$name.'\', \''.$urlname.'\', \''.$_POST['namespace'].'\');');
    if ( !$q )
    {
      $db->_die('The page entry could not be inserted.');
    }
    $q = $db->sql_query('INSERT INTO '.table_prefix.'page_text(page_id,namespace,page_text) VALUES(\''.$urlname.'\', \''.$_POST['namespace'].'\', \''.$db->escape('Please edit this page! <nowiki><script type="text/javascript">ajaxEditor();</script></nowiki>').'\');');
    if ( !$q )
    {
      $db->_die('The page text entry could not be inserted.');
    }
    
    header('Location: '.makeUrlNS($_POST['namespace'], sanitize_page_id($p)));
    exit;
  }
  $template->header();
  if ( !$session->get_permissions('create_page') )
  {
    echo 'Wiki mode is disabled, only admins can create pages.';
    
    $template->footer();
    $db->close();
    
    exit;
  }
  echo RenderMan::render('Using the form below you can create a page.');
  ?>
  <form action="" method="post">
    <p>
      <select name="namespace">
        <?php
        $k = array_keys($paths->nslist);
        for ( $i = 0; $i < sizeof($k); $i++ )
        {
          if ( $paths->nslist[$k[$i]] == '' )
          {
            $s = '[No prefix]';
          }
          else
          {
            $s = $paths->nslist[$k[$i]];
          }
          if ( ( $k[$i] != 'System' || $session->user_level >= USER_LEVEL_ADMIN ) && $k[$i] != 'Admin' && $k[$i] != 'Special')
          {
            echo '<option value="'.$k[$i].'">'.$s.'</option>';
          }
        }
        ?>
      </select> <input type="text" name="pagename" /></p>
      <p><input type="submit" name="do" value="Create Page" /></p>
  </form>
  <?php
  $template->footer();
}

function page_Special_AllPages() 
{
  // This should be an easy one
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  $sz = sizeof( $paths->pages ) / 2;
  echo '<p>Below is a list of all of the pages on this website.</p><div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">';
  $cclass = 'row1';
  for ( $i = 0; $i < $sz; $i = $i )
  {
    if ( $cclass == 'row1')
    {
      $cclass='row3';
    }
    else if ( $cclass == 'row3')
    {
      $cclass='row1';
    }
    echo '<tr>';
    for ( $j = 0; $j < 2; $j = $j )
    {
      if ( $i < $sz && $paths->pages[$i]['namespace'] != 'Special' && $paths->pages[$i]['namespace'] != 'Admin' && $paths->pages[$i]['visible'] == 1)
      {
        echo '<td style="width: 50%" class="'.$cclass.'"><a href="'.makeUrl($paths->pages[$i]['urlname']).'">';
        if ( $paths->pages[$i]['namespace'] != 'Article' )
        {
          echo '('.$paths->pages[$i]['namespace'].') ';
        }
        echo $paths->pages[$i]['name'].'</a></td>';
        $j++;
      }
      else if ( $i >= $sz )
      {
        echo '<td style="width: 50%" class="row2"></td>';
        $j++;
      }
      $i++;
    }
    echo '</tr>';
  }
  echo '</table></div>';
  $template->footer();
}

function page_Special_SpecialPages()
{
  // This should be an easy one
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  $sz = sizeof($paths->pages) / 2;
  echo '<p>Below is a list of all of the special pages on this website.</p><div class="tblholder"><table border="0" width="100%" cellspacing="1" cellpadding="4">';
  $cclass='row1';
  for ( $i = 0; $i < $sz; $i = $i)
  {
    if ( $cclass == 'row1' )
    {
      $cclass = 'row3';
    }
    else if ( $cclass == 'row3')
    {
      $cclass='row1';
    }
    echo '<tr>';
    for ( $j = 0; $j < 2; $j = $j )
    {
      if ( $i < $sz && $paths->pages[$i]['namespace'] == 'Special' && $paths->pages[$i]['visible'] == 1)
      {
        echo '<td style="width: 50%" class="'.$cclass.'"><a href="'.makeUrl($paths->pages[$i]['urlname']).'">';
        echo $paths->pages[$i]['name'].'</a></td>';
        $j++;
      }
      else if ( $i >= $sz )
      {  
        echo '<td style="width: 50%" class="row2"></td>';
        $j++;
      }
      $i++;
    }
    echo '</tr>';
  }
  echo '</table></div>';
  $template->footer();
}

function page_Special_About_Enano()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $platform = 'Unknown';
  $uname = @file_get_contents('/proc/sys/kernel/ostype');
  if($uname == "Linux\n")
    $platform = 'Linux';
  else if(file_exists('/hurd/pfinet')) // I have a little experience with GNU/Hurd :-) http://hurdvm.enanocms.org/
    $platform = 'GNU/Hurd';
  else if(file_exists('C:\Windows\system32\ntoskrnl.exe'))
    $platform = 'Windows NT';
  else if(file_exists('C:\Windows\system\krnl386.exe'))
    $platform = 'Windows 9x/DOS';
  else if(file_exists('/bin/bash'))
    $platform = 'Other GNU/Mac OS X';
  else if(is_dir('/bin'))
    $platform = 'Other POSIX';
  $template->header();
  ?>
  <br />
  <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr><th colspan="2" style="text-align: left;">About the Enano Content Management System</th></tr>
      <tr><td colspan="2" class="row3"><p>This website is powered by <a href="http://enanocms.org/">Enano</a>, the lightweight and open source
      CMS that everyone can use. Enano is copyright &copy; 2006-2007 Dan Fuhry. For legal information, along with a list of libraries that Enano
      uses, please see <a href="http://enanocms.org/Legal_information">Legal Information</a>.</p>
      <p>The developers and maintainers of Enano strongly believe that software should not only be free to use, but free to be modified,
         distributed, and used to create derivative works. For more information about Free Software, check out the
         <a href="http://en.wikipedia.org/wiki/Free_Software" onclick="window.open(this.href); return false;">Wikipedia page</a> or
         the <a href="http://www.fsf.org/" onclick="window.open(this.href); return false;">Free Software Foundation's</a> homepage.</p>
      <p>This program is Free Software; you can redistribute it and/or modify it under the terms of the GNU General Public License
      as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.</p>
      <p>This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
      warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.</p>
      <p>You should have received <a href="<?php echo makeUrlNS('Special', 'GNU_General_Public_License'); ?>">a copy of
         the GNU General Public License</a> along with this program; if not, write to:</p>
      <p style="margin-left 2em;">Free Software Foundation, Inc.,<br />
         51 Franklin Street, Fifth Floor<br />
         Boston, MA 02110-1301, USA</p>
      <p>Alternatively, you can <a href="http://www.gnu.org/copyleft/gpl.html">read it online</a>.</p>
      </td></tr>
      <tr>
        <td class="row2" colspan="2">
        <table border="0" style="margin: 0 auto; background: none; width: 100%;" cellpadding="5">
            <tr>
            <td style="text-align: center;">
                <a href="http://enanocms.org/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                  <img alt="Powered by Enano"
                       src="<?php echo scriptPath; ?>/images/about-powered-enano.png"
                       onmouseover="this.src='<?php echo scriptPath; ?>/images/about-powered-enano-hover.png';"
                       onmouseout="this.src='<?php echo scriptPath; ?>/images/about-powered-enano.png';"
                       style="border-width: 0px;" width="88" height="31" />
                </a>
              </td>
              <td style="text-align: center;">
                <a href="http://www.php.net/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                  <img alt="Written in PHP" src="<?php echo scriptPath; ?>/images/about-powered-php.png" style="border-width: 0px;" width="88" height="31" />
                </a>
              </td>
              <td style="text-align: center;">
                <a href="http://www.mysql.com/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                  <img alt="Database engine powered by MySQL" src="<?php echo scriptPath; ?>/images/about-powered-mysql.png" style="border-width: 0px;" width="88" height="31" />
                </a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr><td style="width: 100px;" class="row1"><a href="http://enanocms.org">Enano</a> version:</td><td class="row1"><?php echo enano_version(true); ?></td></tr>
      <tr><td style="width: 100px;" class="row2">Web server:</td><td class="row2"><?php if(isset($_SERVER['SERVER_SOFTWARE'])) echo $_SERVER['SERVER_SOFTWARE']; else echo 'Unable to determine web server software.'; ?></td></tr>
      <tr><td style="width: 100px;" class="row1">Server platform:</td><td class="row1"><?php echo $platform; ?></td></tr>
      <tr><td style="width: 100px;" class="row2"><a href="http://www.php.net/">PHP</a> version:</td><td class="row2"><?php echo PHP_VERSION; ?></td></tr>
      <tr><td style="width: 100px;" class="row1"><a href="http://www.mysql.com/">MySQL</a> version:</td><td class="row1"><?php echo mysql_get_server_info($db->_conn); ?></td></tr>
    </table>
  </div>
  <?php
  $template->footer();
}

function page_Special_GNU_General_Public_License()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  if(file_exists(ENANO_ROOT.'/GPL'))
  {
    echo '<p>The following text represents the license that the <a href="'.makeUrlNS('Special', 'About_Enano').'">Enano</a> content management system is under. To make it easier to read, the text has been wiki-formatted; in no other way has it been changed.</p>';
    echo RenderMan::render( htmlspecialchars ( file_get_contents ( ENANO_ROOT . '/GPL' ) ) );
  }
  else
  {
    echo '<p>It appears that the file "GPL" is missing from your Enano installation. You may find a wiki-formatted copy of the GPL at: <a href="http://enanocms.org/GPL">http://enanocms.org/GPL</a>.</p>';
  }
  $template->footer();
}

?>