<?php
/**!info**
{
  "Plugin Name"  : "plugin_specialupdownload_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_specialupdownload_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.6",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 * SpecialUpdownload.php - handles uploading and downloading of user-uploaded files - possibly the most rigorously security-enforcing script in all of Enano, although sessions.php comes in a close second
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
global $db, $session, $paths, $template, $plugins; // Common objects

// $plugins->attachHook('session_started', 'SpecialUpDownload_paths_init();');

function SpecialUpDownload_paths_init()
{
  register_special_page('UploadFile', 'specialpage_upload_file');
  register_special_page('DownloadFile', 'specialpage_download_file');
}
  
function page_Special_UploadFile()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $cache;
  global $mime_types;
  if(getConfig('enable_uploads')!='1') { die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('upload_err_disabled_site') . '</p>'); }
  if ( !$session->get_permissions('upload_files') )
  {
    die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('upload_err_disabled_acl') . '</p>');
  }
  if(isset($_POST['doit']))
  {
    if(isset($_FILES['data']))
    {
      $file =& $_FILES['data'];
    }
    else
    {
      $file = false;
    }
    if ( !is_array($file) )
    {
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_cant_get_file_meta') . '</p>');
    }
    if ( $file['size'] == 0 || $file['size'] > (int)getConfig('max_file_size', '256000') )
    {
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_too_big_or_small') . '</p>');
    }
    
    $types = fetch_allowed_extensions();
    $ext = strtolower(substr($file['name'], strrpos($file['name'], '.')+1, strlen($file['name'])));
    if ( !isset($types[$ext]) || ( isset($types[$ext]) && !$types[$ext] ) )
    {
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_banned_ext', array('ext' => htmlspecialchars($ext))) . '</p>');
    }
    $type = $mime_types[$ext];
    //$type = explode(';', $type); $type = $type[0];
    //if(!in_array($type, $allowed_mime_types)) die_friendly('Upload failed', '<p>The file type "'.$type.'" is not allowed.</p>');
    if($_POST['rename'] != '')
    {
      $filename = $_POST['rename'];
    }
    else
    {
      $filename = $file['name'];
    }
    $bad_chars = Array(':', '\\', '/', '<', '>', '|', '*', '?', '"', '#', '+');
    foreach($bad_chars as $ch)
    {
      if(strstr($filename, $ch) || preg_match('/^([ ]+)$/is', $filename))
      {
        die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_banned_chars') . '</p>');
      }
    }
    
    $ns = namespace_factory($filename, 'File');
    $cdata = $ns->get_cdata();
    $is_protected = $cdata['really_protected'];
    
    if ( isPage($paths->get_pathskey($filename, 'File')) && !isset ( $_POST['update'] ) )
    {
      $upload_link = makeUrlNS('Special', 'UploadFile/'.$filename);
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_already_exists', array('upload_link' => $upload_link)) . '</p>');
    }
    else if ( isset($_POST['update']) && $is_protected )
    {
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_replace_protected') . '</p>');
    }
    
    $utime = time();
           
    $filename = $db->escape(sanitize_page_id($filename));
    $ext = substr($filename, strrpos($filename, '.'), strlen($filename));
    $flen = filesize($file['tmp_name']);
    
    $comments = ( isset($_POST['update']) ) ? $db->escape($_POST['comments']) : $db->escape(RenderMan::preprocess_text($_POST['comments'], false, false));
    $chartag = sha1(microtime());
    $urln = str_replace(' ', '_', $filename);
    
    $key = md5($filename . '_' . ( function_exists('md5_file') ? md5_file($file['tmp_name']) : file_get_contents($file['tmp_name'])));
    $targetname = ENANO_ROOT . '/files/' . $key . $ext;
    
    if(!@move_uploaded_file($file['tmp_name'], $targetname))
    {
      die_friendly($lang->get('upload_err_title'), '<p>' . $lang->get('upload_err_move_failed') . '</p>');
    }
    
    if(getConfig('file_history') != '1')
    {
      if(!$db->sql_query('DELETE FROM  '.table_prefix.'files WHERE filename=\''.$filename.'\' LIMIT 1;')) $db->_die('The old file data could not be deleted.');
    }
    if(!$db->sql_query('INSERT INTO '.table_prefix.'files(time_id,page_id,filename,size,mimetype,file_extension,file_key) VALUES('.$utime.', \''.$urln.'\', \''.$filename.'\', '.$flen.', \''.$type.'\', \''.$ext.'\', \''.$key.'\')')) $db->_die('The file data entry could not be inserted.');
    if(!isset($_POST['update']))
    {
      if(!$db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace) VALUES('.$utime.', \''.enano_date('d M Y h:i a').'\', \'page\', \'create\', \''.$session->username.'\', \''.$filename.'\', \''.'File'.'\');')) $db->_die('The page log could not be updated.');
      if(!$db->sql_query('INSERT INTO '.table_prefix.'pages(name,urlname,namespace,protected,delvotes,delvote_ips) VALUES(\''.$filename.'\', \''.$urln.'\', \'File\', 0, 0, \'\')')) $db->_die('The page listing entry could not be inserted.');
      if(!$db->sql_query('INSERT INTO '.table_prefix.'page_text(page_id,namespace,page_text,char_tag) VALUES(\''.$urln.'\', \'File\', \''.$comments.'\', \''.$chartag.'\')')) $db->_die('The page text entry could not be inserted.');
    }
    else
    {
      if(!$db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.$utime.', \''.enano_date('d M Y h:i a').'\', \'page\', \'reupload\', \''.$session->username.'\', \''.$filename.'\', \''.'File'.'\', \''.$comments.'\');')) $db->_die('The page log could not be updated.');
    }
    $cache->purge('page_meta');
    die_friendly($lang->get('upload_success_title'), '<p>' . $lang->get('upload_success_body', array('file_link' => makeUrlNS('File', $filename))) . '</p>');
  }
  else
  {
    $template->header();
    $fn = $paths->getParam(0);
    if ( $fn && !$session->get_permissions('upload_new_version') )
    {
      die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('upload_err_replace_denied') . '<p>');
    }
    ?>
    <p><?php echo $lang->get('upload_intro'); ?></p>
    <p><?php 
      // Get the max file size, and format it in a way that is user-friendly
      
      $fs = getConfig('max_file_size', '256000');
      $fs = (int)$fs;
      if($fs >= 1048576)
      {
        $fs = round($fs / 1048576, 1);
        $unitized = $fs . ' ' . $lang->get('etc_unit_megabytes_short');
      }
      elseif($fs >= 1024)
      {
        $fs = round($fs / 1024, 1);
        $unitized = $fs . ' ' . $lang->get('etc_unit_kilobytes_short');
      }
      
      echo $lang->get('upload_max_filesize', array(
          'size' => $unitized
        ));
    ?></p>
    <form action="<?php echo makeUrl($paths->page); ?>" method="post" enctype="multipart/form-data">
      <table border="0" cellspacing="1" cellpadding="4">
        <tr><td><?php echo $lang->get('upload_field_file'); ?></td><td><input name="data" type="file" size="40" /></td></tr>
        <tr><td><?php echo $lang->get('upload_field_renameto'); ?></td><td><input name="rename" type="text" size="40"<?php if($fn) echo ' value="'.$fn.'" readonly="readonly"'; ?> /></td></tr>
        <?php
        if(!$fn) echo '<tr><td>' . $lang->get('upload_field_comments') . '</td><td><textarea name="comments" rows="20" cols="60"></textarea></td></tr>';
        else echo '<tr><td>' . $lang->get('upload_field_reason') . '</td><td><input name="comments" size="50" /></td></tr>';
        ?>
        <tr><td colspan="2" style="text-align: center">
          <?php
          if($fn)
            echo '<input type="hidden" name="update" value="true" />';
          ?>
          <input type="submit" name="doit" value="<?php echo $lang->get('upload_btn_upload'); ?>" />
        </td></tr>
      </table>
    </form>
    <?php
    $template->footer();
  }
}                                                     

function page_Special_DownloadFile()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $do_gzip;
  $filename = rawurldecode($paths->getParam(0));
  $timeid = $paths->getParam(1);
  if ( $timeid && preg_match('#^([0-9]+)$#', (string)$timeid) )
  {
    $tid = ' AND time_id='.$timeid;
  }
  else
  {
    $tid = '';
  }
  $filename = $db->escape(sanitize_page_id($filename));
  
  $q = $db->sql_query('SELECT page_id,size,mimetype,time_id,file_extension,file_key FROM '.table_prefix.'files WHERE filename=\''.$filename.'\''.$tid.' ORDER BY time_id DESC;');
  if ( !$q )
  {
    $db->_die('The file data could not be selected.');
  }
  if ( $db->numrows() < 1 )
  {
    header('HTTP/1.1 404 Not Found');
    die_friendly($lang->get('upload_err_not_found_title'), '<p>' . $lang->get('upload_err_not_found_body', array('filename' => htmlspecialchars($filename))) . '</p>');
  }
  $row = $db->fetchrow();
  $db->free_result();
  
  // Check permissions
  $perms = $session->fetch_page_acl($row['page_id'], 'File');
  if ( !$perms->get_permissions('read') )
  {
    die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
  }
  
  $fname = ENANO_ROOT . '/files/' . $row['file_key'] . $row['file_extension'];
  if ( !file_exists($fname) )
  {
    $fname = ENANO_ROOT . '/files/' . $row['file_key'] . '_' . $row['time_id'] . $row['file_extension'];
  }
  if ( !file_exists($fname) )
  {
    die("Uploaded file $fname not found.");
  }
  
  if ( isset($_GET['preview']) && substr($row['mimetype'], 0, 6) == 'image/' )
  {
    // Determine appropriate width and height
    $width  = ( isset($_GET['width'])  ) ? intval($_GET['width'] ) : 320;
    $height = ( isset($_GET['height']) ) ? intval($_GET['height']) : 320;
    $cache_filename = ENANO_ROOT . "/cache/{$filename}-{$row['time_id']}-{$width}x{$height}{$row['file_extension']}";
    if ( file_exists($cache_filename) )
    {
      $fname = $cache_filename;
    }
    else
    {
      $allow_scale = false;
      $orig_fname = $fname;
      // is caching enabled?
      if ( getConfig('cache_thumbs') == '1' )
      {
        $fname = $cache_filename;
        if ( is_writeable(dirname($fname)) )
        {
          $allow_scale = true;
        }
      }
      else
      {
        // Get a temporary file
        // In this case, the file will not be cached and will be scaled each time it's requested
        $temp_dir = sys_get_temp_dir();
        // if tempnam() cannot use the specified directory name, it will fall back on the system default
        $tempname = tempnam($temp_dir, $filename);
        if ( $tempname && is_writeable($tempname) )
        {
          $allow_scale = true;
        }
      }
      if ( $allow_scale )
      {
        $result = scale_image($orig_fname, $fname, $width, $height);
        if ( !$result )
          $fname = $orig_fname;
      }
      else
      {
        $fname = $orig_fname;
      }
    }
  }
  $handle = @fopen($fname, 'r');
  if ( !$handle )
    die('Can\'t open output file for reading');
  
  $len = filesize($fname);
  header('Content-type: '.$row['mimetype']);
  if ( isset($_GET['download']) )
  {
    header('Content-disposition: attachment, filename="' . $filename . '";');
  }
  if ( !@$GLOBALS['do_gzip'] )
    header('Content-length: ' . $len);
  
  header('Last-Modified: '.enano_date('r', $row['time_id']));
  
  // using this method limits RAM consumption
  while ( !feof($handle) )
  {
    echo fread($handle, 512000);
  }
  fclose($handle);
  
  gzip_output();
  
  exit;
  
}

?>