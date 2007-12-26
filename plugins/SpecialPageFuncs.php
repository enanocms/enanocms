<?php
/*
Plugin Name: Special page-related pages
Plugin URI: http://enanocms.org/
Description: Provides the page Special:CreatePage, which can be used to create new pages. Also adds the About Enano and GNU General Public License pages.
Author: Dan Fuhry
Version: 1.0.3
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.3
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

$plugins->attachHook('session_started', '
  global $paths;
    $paths->add_page(Array(
      \'name\'=>\'specialpage_create_page\',
      \'urlname\'=>\'CreatePage\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_all_pages\',
      \'urlname\'=>\'AllPages\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_special_pages\',
      \'urlname\'=>\'SpecialPages\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_about_enano\',
      \'urlname\'=>\'About_Enano\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_gnu_gpl\',
      \'urlname\'=>\'GNU_General_Public_License\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'specialpage_tag_cloud\',
      \'urlname\'=>\'TagCloud\',
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
    $code = $plugins->setHook('page_create_request');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    if ( substr($urlname, 0, 8) == 'Project:' )
    {
      $template->header();
      
      echo '<h3>The page could not be created.</h3><p>The page title can\'t start with "Project:" because this prefix is reserved for a parser shortcut.</p>';
      
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
    
    $ips = array(
      'ip' => array(),
      'u' => array()
      );
    $ips = $db->escape(serialize($ips));
    
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
    
    $q = $db->sql_query('INSERT INTO '.table_prefix.'pages(name,urlname,namespace,delvote_ips) VALUES(\''.$name.'\', \''.$urlname.'\', \''.$_POST['namespace'].'\',\'' . $ips . '\');');
    if ( !$q )
    {
      $db->_die('The page entry could not be inserted.');
    }
    $q = $db->sql_query('INSERT INTO '.table_prefix.'page_text(page_id,namespace,page_text) VALUES(\''.$urlname.'\', \''.$_POST['namespace'].'\', \''.'\');');
    if ( !$q )
    {
      $db->_die('The page text entry could not be inserted.');
    }
    
    header('Location: '.makeUrlNS($_POST['namespace'], sanitize_page_id($p)) . '#do:edit');
    exit;
  }
  $template->header();
  /*
  if ( !$session->get_permissions('create_page') )
  {
    echo 'Wiki mode is disabled, only admins can create pages.';
    
    $template->footer();
    $db->close();
    
    exit;
  }
  */
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

function PagelistingFormatter($id, $row)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  static $rowtracker = 0;
  static $tdclass = 'row2';
  static $per_row = 2;
  static $first = true;
  $return = '';
  if ( $id === false && $row === false )
  {
    $rowtracker = 0;
    $first = true;
    return false;
  }
  $rowtracker++;
  if ( $rowtracker == $per_row || $first )
  {
    $rowtracker = 0;
    $tdclass = ( $tdclass == 'row2' ) ? 'row1' : 'row2';
  }
  if ( $rowtracker == 0 && !$first )
    $return .= "</tr>\n<tr>";
  
  $first = false;
  
  preg_match('/^ns=(' . implode('|', array_keys($paths->nslist)) . ');pid=(.*?)$/i', $id, $match);
  $namespace =& $match[1];
  $page_id   =& $match[2];
  $page_id   = sanitize_page_id($page_id);
  
  $url = makeUrlNS($namespace, $page_id);
  $url = htmlspecialchars($url);
  
  $link = '<a href="' . $url . '">' . htmlspecialchars($row['name']) . '</a>';
  $td = '<td class="' . $tdclass . '" style="width: 50%;">' . $link . '</td>';
  
  $return .= $td;
  
  return $return;
}

function page_Special_AllPages() 
{
  // This should be an easy one
  global $db, $session, $paths, $template, $plugins; // Common objects
  $template->header();
  $sz = sizeof( $paths->pages ) / 2;
  echo '<p>Below is a list of all of the pages on this website.</p>';
  
  $q = $db->sql_query('SELECT COUNT(urlname) FROM '.table_prefix.'pages WHERE visible!=0;');
  if ( !$q )
    $db->_die();
  $row = $db->fetchrow_num();
  $count = $row[0];
  
  switch($count % 4)
  {
    case 0:
    case 2:
      // even number of results; do nothing
      $last_cell = '';
      break;
    case 1:
      // odd number of results and odd number of rows, use row1
      $last_cell = '<td class="row1"></td>';
      break;
    case 3:
      // odd number of results and even number of rows, use row2
      $last_cell = '<td class="row2"></td>';
      break;
  }
  
  $db->free_result();
  
  $q = $db->sql_unbuffered_query('SELECT CONCAT("ns=",namespace,";pid=",urlname) AS identifier, name FROM '.table_prefix.'pages WHERE visible!=0 ORDER BY name ASC;');
  if ( !$q )
    $db->_die();
  
  $offset = ( isset($_GET['offset']) ) ? intval($_GET['offset']) : 0;
  
  // reset formatter
  PagelistingFormatter(false, false);
  
  $result = paginate(
      $q,                  // result resource
      '{identifier}',      // formatting template
      $count,              // # of results
      makeUrlNS('Special', 'AllPages', 'offset=%s'), // result URL
      $offset,             // start offset
      40,                  // results per page
      array( 'identifier' => 'PagelistingFormatter' ), // hooks
      '<div class="tblholder">
         <table border="0" cellspacing="1" cellpadding="4">
           <tr>',          // print at start
      '    ' . $last_cell . '</tr>
         </table>
       </div>'             // print at end
       );
  
  echo $result;
  
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
  global $lang;
  
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
      <tr><td colspan="2" class="row3">
        <?php
        echo $lang->get('meta_enano_about_poweredby');
        $subst = array(
            'gpl_link' => makeUrlNS('Special', 'GNU_General_Public_License')
          );
        echo $lang->get('meta_enano_about_gpl', $subst);
        if ( $lang->lang_code != 'eng' ):
        // Do not remove this block of code. Doing so is a violation of the GPL. (A copy of the GPL in other languages
        // must be accompanied by a copy of the English GPL.)
        ?>
        <h3>(English)</h3>
        <p>
          This website is powered by <a href="http://enanocms.org/">Enano</a>, the lightweight and open source CMS that everyone can use.
          Enano is copyright &copy; 2006-2007 Dan Fuhry. For legal information, along with a list of libraries that Enano uses, please
          see <a href="http://enanocms.org/Legal_information">Legal Information</a>.
        </p>
        <p>
          The developers and maintainers of Enano strongly believe that software should not only be free to use, but free to be modified,
          distributed, and used to create derivative works. For more information about Free Software, check out the
          <a href="http://en.wikipedia.org/wiki/Free_Software" onclick="window.open(this.href); return false;">Wikipedia page</a> or
          the <a href="http://www.fsf.org/" onclick="window.open(this.href); return false;">Free Software Foundation's</a> homepage.
        </p>
        <p>
          This program is Free Software; you can redistribute it and/or modify it under the terms of the GNU General Public License
          as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
        </p>
        <p>
          This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
          warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
        </p>
        <p>
          You should have received <a href="<?php echo makeUrlNS('Special', 'GNU_General_Public_License'); ?>">a copy of
          the GNU General Public License</a> along with this program; if not, write to:
        </p>
        <p style="margin-left 2em;">
          Free Software Foundation, Inc.,<br />
          51 Franklin Street, Fifth Floor<br />
          Boston, MA 02110-1301, USA
        </p>
        <p>
          Alternatively, you can <a href="http://www.gnu.org/licenses/old-licenses/gpl-2.0.html">read it online</a>.
        </p>
        <?php
        endif;
        ?>
      </td></tr>
      <tr>
        <td class="row2" colspan="2">
          <table border="0" style="margin: 0 auto; background: none; width: 100%;" cellpadding="5">
            <tr>
              <td style="text-align: center;">
                <?php echo $template->fading_button; ?>
              </td>
              <td style="text-align: center;">
                <a href="http://www.php.net/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                  <img alt="Written in PHP" src="<?php echo scriptPath; ?>/images/about-powered-php.png" style="border-width: 0px;" width="88" height="31" />
                </a>
              </td>
              <td style="text-align: center;">
                <?php
                switch(ENANO_DBLAYER)
                {
                  case 'MYSQL':
                    ?>
                    <a href="http://www.mysql.com/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                      <img alt="Database engine powered by MySQL" src="<?php echo scriptPath; ?>/images/about-powered-mysql.png" style="border-width: 0px;" width="88" height="31" />
                    </a>
                    <?php
                    break;
                  case 'PGSQL':
                    ?>
                    <a href="http://www.postgresql.org/" onclick="window.open(this.href); return false;" style="background: none; padding: 0;">
                      <img alt="Database engine powered by PostgreSQL" src="<?php echo scriptPath; ?>/images/about-powered-pgsql.png" style="border-width: 0px;" width="90" height="30" />
                    </a>
                    <?php
                    break;
                }
                ?>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr><td style="width: 100px;" class="row1"><?php echo $lang->get('meta_enano_about_lbl_enanoversion'); ?></td><td class="row1"><?php echo enano_version(true) . ' (' . enano_codename() . ')'; ?></td></tr>
      <tr><td style="width: 100px;" class="row2"><?php echo $lang->get('meta_enano_about_lbl_webserver'); ?></td><td class="row2"><?php if(isset($_SERVER['SERVER_SOFTWARE'])) echo $_SERVER['SERVER_SOFTWARE']; else echo 'Unable to determine web server software.'; ?></td></tr>
      <tr><td style="width: 100px;" class="row1"><?php echo $lang->get('meta_enano_about_lbl_serverplatform'); ?></td><td class="row1"><?php echo $platform; ?></td></tr>
      <tr><td style="width: 100px;" class="row2"><?php echo $lang->get('meta_enano_about_lbl_phpversion'); ?></td><td class="row2"><?php echo PHP_VERSION; ?></td></tr>
      <?php
      switch(ENANO_DBLAYER)
      {
        case 'MYSQL':
          ?>
          <tr><td style="width: 100px;" class="row1"><?php echo $lang->get('meta_enano_about_lbl_mysqlversion'); ?></td><td class="row1"><?php echo mysql_get_server_info($db->_conn); ?></td></tr>
          <?php
          break;
        case 'PGSQL':
          $pg_serverdata = pg_version($db->_conn);
          $pg_version = $pg_serverdata['server'];
          ?>
          <tr><td style="width: 100px;" class="row1"><?php echo $lang->get('meta_enano_about_lbl_pgsqlversion'); ?></td><td class="row1"><?php echo $pg_version; ?></td></tr>
          <?php
          break;
      }
      ?>
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
    echo RenderMan::render( file_get_contents ( ENANO_ROOT . '/GPL' ) );
  }
  else
  {
    echo '<p>It appears that the file "GPL" is missing from your Enano installation. You may find a wiki-formatted copy of the GPL at: <a href="http://enanocms.org/GPL">http://enanocms.org/GPL</a>.</p>';
  }
  $template->footer();
}

function page_Special_TagCloud()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $template->header();
  
  if ( $tag = $paths->getParam(0) )
  {
    $tag = sanitize_tag($tag);
    $q = $db->sql_query('SELECT page_id, namespace FROM '.table_prefix.'tags WHERE tag_name=\'' . $db->escape($tag) . '\';');
    if ( !$q )
      $db->_die();
    if ( $row = $db->fetchrow() )
    {
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4">';
      echo '<tr><th colspan="2">Pages tagged "' . htmlspecialchars($tag) . '"</th></tr>';
      echo '<tr>';
      $i = 0;
      $td_class = 'row1';
      do
      {
        if ( $i % 2 == 0 && $i > 1 )
        {
          $td_class = ( $td_class == 'row2' ) ? 'row1' : 'row2';
          echo '</tr><tr>';
        }
        $i++;
        $title = get_page_title_ns($row['page_id'], $row['namespace']);
        if ( $row['namespace'] != 'Article' && isset($paths->nslist[$row['namespace']]) )
          $title = $paths->nslist[$row['namespace']] . $title;
        $url = makeUrlNS($row['namespace'], $row['page_id']);
        $class = ( isPage( $paths->nslist[$row['namespace']] . $row['page_id'] ) ) ? '' : ' class="wikilink-nonexistent"';
        $link = '<a href="' . htmlspecialchars($url) . '"' . $class . '>' . htmlspecialchars($title) . '</a>';
        echo "<td class=\"$td_class\" style=\"width: 50%;\">$link</td>";
        // " workaround for jEdit highlighting bug
      }
      while ( $row = $db->fetchrow() );
      while ( $i % 2 > 0 )
      {
        $i++;
        echo "<td class=\"$td_class\" style=\"width: 50%;\"></td>";
      }
      // " workaround for jEdit highlighting bug
      echo '<tr>
              <th colspan="2" class="subhead"><a href="' . makeUrlNS('Special', 'TagCloud') . '" style="color: white;">&laquo; Return to tag cloud</a></th>
            </tr>';
      echo '</table>';
      echo '</div>';
    }
  }
  else
  {
    $cloud = new TagCloud();
    
    $q = $db->sql_query('SELECT tag_name FROM '.table_prefix.'tags;');
    if ( !$q )
      $db->_die();
    if ( $db->numrows() < 1 )
    {
      echo '<p>No pages are tagged yet.</p>';
    }
    else
    {
      echo '<h3>Summary of page tagging</h3>';
      while ( $row = $db->fetchrow() )
      {
        $cloud->add_word($row['tag_name']);
      }
      echo $cloud->make_html('normal');
      echo '<p>Hover your mouse over a tag to see how many pages have the tag. Click on a tag to see a list of the pages that have it.</p>';
    }
  }
  
  $template->footer();
}

// tag cloud sidebar block
function sidebar_add_tag_cloud()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  $cloud = new TagCloud();
    
  $q = $db->sql_query('SELECT tag_name FROM '.table_prefix.'tags;');
  if ( !$q )
    $db->_die();
  if ( $db->numrows() < 1 )
  {
    $sb_html = 'No pages are tagged yet.';
  }
  else
  {
    while ( $row = $db->fetchrow() )
    {
      $cloud->add_word($row['tag_name']);
    }
    $sb_html = $cloud->make_html('small', 'justify') . '<br /><a style="text-align: center;" href="' . makeUrlNS('Special', 'TagCloud') . '">Larger version</a>';
  }
  $template->sidebar_widget('Tag cloud', "<div style='padding: 5px;'>$sb_html</div>");
}

$plugins->attachHook('compile_template', 'sidebar_add_tag_cloud();');

?>