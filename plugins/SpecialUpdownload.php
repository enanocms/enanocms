<?php
/*
Plugin Name: Upload/download frontend
Plugin URI: http://enanocms.org/
Description: Provides the pages Special:UploadFile and Special:DownloadFile. UploadFile is used to upload files to the site, and DownloadFile fetches the file from the database, creates thumbnails if necessary, and sends the file to the user.
Author: Dan Fuhry
Version: 1.0.1
Author URI: http://enanocms.org/
*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 2
 * Copyright (C) 2006-2007 Dan Fuhry
 * SpecialUpdownload.php - handles uploading and downloading of user-uploaded files - possibly the most rigorously security-enforcing script in all of Enano, although sessions.php comes in a close second
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
      \'name\'=>\'Upload file\',
      \'urlname\'=>\'UploadFile\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>1,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    
    $paths->add_page(Array(
      \'name\'=>\'Download file\',
      \'urlname\'=>\'DownloadFile\',
      \'namespace\'=>\'Special\',
      \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
      ));
    ');

function page_Special_UploadFile()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $mime_types;
  if(getConfig('enable_uploads')!='1') { die_friendly('Access denied', '<p>File uploads are disabled this website.</p>'); }
  if ( !$session->get_permissions('upload_files') )
  {
    die_friendly('Access denied', '<p>File uploads are disabled for your user account or group.<p>');
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
    if(!is_array($file)) die_friendly('Upload failed', '<p>The server could not retrieve the array $_FILES[\'data\'].</p>');
    if($file['size'] == 0 || $file['size'] > (int)getConfig('max_file_size')) die_friendly('Upload failed', '<p>The file you uploaded is either too large or 0 bytes in length.</p>');
    /*
    $allowed_mime_types = Array(
        'text/plain',
        'image/png',
        'image/jpeg',
        'image/tiff',
        'image/gif',
        'text/html', // Safe because the file is stashed in the database
        'application/x-bzip2',
        'application/x-gzip',
        'text/x-c++'
      );
    if(function_exists('finfo_open') && $fi = finfo_open(FILEINFO_MIME, ENANO_ROOT.'/includes/magic')) // First try to use the fileinfo extension, this is the best way to determine the mimetype
    {
      if(!$fi) die_friendly('Upload failed', '<p>Enano was unable to determine the format of the uploaded file.</p><p>'.@finfo_file($fi, $file['tmp_name']).'</p>');
      $type = @finfo_file($fi, $file['tmp_name']);
      @finfo_close($fi);
    }
    elseif(function_exists('mime_content_type'))
      $type = mime_content_type($file['tmp_name']); // OK, no fileinfo function. Use a (usually) built-in PHP function
    elseif(isset($file['type']))
      $type = $file['type']; // LAST RESORT: use the mimetype the browser sent us, though this is likely to be spoofed
    else // DANG! Not even the browser told us. Bail out.
      die_friendly('Upload failed', '<p>Enano was unable to determine the format of the uploaded file.</p>');
    */
    $types = fetch_allowed_extensions();
    $ext = substr($file['name'], strrpos($file['name'], '.')+1, strlen($file['name']));
    if(!isset($types[$ext]) || ( isset($types[$ext]) && !$types[$ext] ) )
    {
      die_friendly('Upload failed', '<p>The file type ".'.$ext.'" is not allowed.</p>');
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
      if(strstr($filename, $ch) || preg_match('/^([ ]+)$/is', $filename)) die_friendly('Upload failed', '<p>The filename contains invalid characters.</p>');
    }
    
    if ( isset ( $paths->pages[ $paths->nslist['File'] . $filename ] ) && !isset ( $_POST['update'] ) )
    {
      die_friendly('Upload failed', '<p>The file already exists. You can <a href="'.makeUrlNS('Special', 'UploadFile/'.$filename).'">upload a new version of this file</a>.</p>');
    }
    else if ( isset($_POST['update']) && 
            ( !isset($paths->pages[$paths->nslist['File'].$filename]) ||
             (isset($paths->pages[$paths->nslist['File'].$filename]) &&
               $paths->pages[$paths->nslist['File'].$filename]['protected'] == 1 )
             )
           )
    {
      die_friendly('Upload failed', '<p>Either the file does not exist (and therefore cannot be updated) or the file is protected.</p>');
    }
    
    $utime = time();
           
    $filename = $db->escape($filename);
    $ext = substr($filename, strrpos($filename, '.'), strlen($filename));
    $flen = filesize($file['tmp_name']);
    
    $comments = ( isset($_POST['update']) ) ? $db->escape($_POST['comments']) : $db->escape(RenderMan::preprocess_text($_POST['comments'], false, false));
    $chartag = sha1(microtime());
    $urln = str_replace(' ', '_', $filename);
    
    $key = md5($filename . '_' . file_get_contents($file['tmp_name']));
    $targetname = ENANO_ROOT . '/files/' . $key . '_' . $utime . $ext;
    
    if(!@move_uploaded_file($file['tmp_name'], $targetname))
    {
      die_friendly('Upload failed', '<p>Could not move uploaded file to the new location.</p>');
    }
    
    if(getConfig('file_history') != '1')
    {
      if(!$db->sql_query('DELETE FROM  '.table_prefix.'files WHERE filename=\''.$filename.'\' LIMIT 1;')) $db->_die('The old file data could not be deleted.');
    }
    if(!$db->sql_query('INSERT INTO '.table_prefix.'files(time_id,page_id,filename,size,mimetype,file_extension,file_key) VALUES('.$utime.', \''.$urln.'\', \''.$filename.'\', '.$flen.', \''.$type.'\', \''.$ext.'\', \''.$key.'\')')) $db->_die('The file data entry could not be inserted.');
    if(!isset($_POST['update']))
    {
      if(!$db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace) VALUES('.$utime.', \''.date('d M Y h:i a').'\', \'page\', \'create\', \''.$session->username.'\', \''.$filename.'\', \''.'File'.'\');')) $db->_die('The page log could not be updated.');
      if(!$db->sql_query('INSERT INTO '.table_prefix.'pages(name,urlname,namespace,protected,delvotes,delvote_ips) VALUES(\''.$filename.'\', \''.$urln.'\', \'File\', 0, 0, \'\')')) $db->_die('The page listing entry could not be inserted.');
      if(!$db->sql_query('INSERT INTO '.table_prefix.'page_text(page_id,namespace,page_text,char_tag) VALUES(\''.$urln.'\', \'File\', \''.$comments.'\', \''.$chartag.'\')')) $db->_die('The page text entry could not be inserted.');
    }
    else
    {
      if(!$db->sql_query('INSERT INTO '.table_prefix.'logs(time_id,date_string,log_type,action,author,page_id,namespace,edit_summary) VALUES('.$utime.', \''.date('d M Y h:i a').'\', \'page\', \'reupload\', \''.$session->username.'\', \''.$filename.'\', \''.'File'.'\', \''.$comments.'\');')) $db->_die('The page log could not be updated.');
    }
    die_friendly('Upload complete', '<p>Your file has been uploaded successfully. View the <a href="'.makeUrlNS('File', $filename).'">file\'s page</a>.</p>');
  }
  else
  {
    $template->header();
    $fn = $paths->getParam(0);
    if ( $fn && !$session->get_permissions('upload_new_version') )
    {
      die_friendly('Access denied', '<p>Uploading new versions of files has been disabled for your user account or group.<p>');
    }
    ?>
    <p>Using this form you can upload a file to the <?php echo getConfig('site_name'); ?> site.</p>
    <p>The maximum file size is <?php 
      // Get the max file size, and format it in a way that is user-friendly
      $fs = getConfig('max_file_size');
      echo commatize($fs).' bytes';
      $fs = (int)$fs;
      if($fs >= 1048576)
      {
        $fs = round($fs / 1048576, 1);
        echo ' ('.$fs.' MB)';
      }
      elseif($fs >= 1024)
      {
        $fs = round($fs / 1024, 1);
        echo ' ('.$fs.' KB)';
      }
    ?>.</p>
    <form action="<?php echo makeUrl($paths->page); ?>" method="post" enctype="multipart/form-data">
      <table border="0" cellspacing="1" cellpadding="4">
        <tr><td>File:</td><td><input name="data" type="file" size="40" /></td></tr>
        <tr><td>Rename to:</td><td><input name="rename" type="text" size="40"<?php if($fn) echo ' value="'.$fn.'" readonly="readonly"'; ?> /></td></tr>
        <?php
        if(!$fn) echo '<tr><td>Comments:<br />(can be wiki-formatted)</td><td><textarea name="comments" rows="20" cols="60"></textarea></td></tr>';
        else echo '<tr><td>Reason for uploading the new version: </td><td><input name="comments" size="50" /></td></tr>';
        ?>
        <tr><td colspan="2" style="text-align: center">
          <?php
          if($fn)
            echo '<input type="hidden" name="update" value="true" />';
          ?>
          <input type="submit" name="doit" value="Upload file" />
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
  global $do_gzip;
  $filename = rawurldecode($paths->getParam(0));
  $timeid = $paths->getParam(1);
  if($timeid && preg_match('#^([0-9]+)$#', (string)$timeid)) $tid = ' AND time_id='.$timeid;
  else $tid = '';
  $filename = $db->escape($filename);
  $q = $db->sql_query('SELECT page_id,size,mimetype,time_id,file_extension,file_key FROM '.table_prefix.'files WHERE filename=\''.$filename.'\''.$tid.' ORDER BY time_id DESC;');
  if(!$q) $db->_die('The file data could not be selected.');
  if($db->numrows() < 1) { header('HTTP/1.1 404 Not Found'); die_friendly('File not found', '<p>The file "'.$filename.'" cannot be found.</p>'); }
  $row = $db->fetchrow();
  $db->free_result();
  
  // Check permissions
  $perms = $session->fetch_page_acl($row['page_id'], 'File');
  if ( !$perms->get_permissions('read') )
  {
    die_friendly('Access denied', '<p>Access to the specified file is denied.</p>');
  }
  
  $fname = ENANO_ROOT . '/files/' . $row['file_key'] . '_' . $row['time_id'] . $row['file_extension'];
  $data = file_get_contents($fname);
  if(isset($_GET['preview']) && getConfig('enable_imagemagick')=='1' && file_exists(getConfig('imagemagick_path')) && substr($row['mimetype'], 0, 6) == 'image/')
  {
    $nam = tempnam('/tmp', $filename);
    $h = @fopen($nam, 'w');
    if(!$h) die('Error opening '.$nam.' for writing');
    fwrite($h, $data);
    fclose($h);
    /* Make sure the request doesn't contain commandline injection - yow! */
    if(!isset($_GET['width' ]) || (isset($_GET['width'] ) && !preg_match('#^([0-9]+)$#', $_GET['width']  ))) $width  = '320'; else $width  = $_GET['width' ];
    if(!isset($_GET['height']) || (isset($_GET['height']) && !preg_match('#^([0-9]+)$#', $_GET['height'] ))) $height = '240'; else $height = $_GET['height'];
    $cache_filename=ENANO_ROOT.'/cache/'.$filename.'-'.$row['time_id'].'-'.$width.'x'.$height.$row['file_extension'];
    if(getConfig('cache_thumbs')=='1' && file_exists($cache_filename) && is_writable(ENANO_ROOT.'/cache')) {
      $data = file_get_contents($cache_filename);
    } elseif(getConfig('enable_imagemagick')=='1' && file_exists(getConfig('imagemagick_path'))) {
      // Use ImageMagick to convert the image
      //unlink($nam);
      error_reporting(E_ALL);
      $cmd = ''.getConfig('imagemagick_path').' "'.$nam.'" -resize "'.$width.'x'.$height.'>" "'.$nam.'.scaled'.$row['file_extension'].'"';
      system($cmd, $stat);
      if(!file_exists($nam.'.scaled'.$row['file_extension'])) die('Failed to call ImageMagick (return value '.$stat.'), command line was:<br />'.$cmd);
      $data = file_get_contents($nam.'.scaled'.$row['file_extension']);
      // Be stingy about it - better to re-generate the image hundreds of times than to fail completely
      if(getConfig('cache_thumbs')=='1' && !file_exists($cache_filename)) {
        // Write the generated thumbnail to the cache directory
        $h = @fopen($cache_filename, 'w');
        if(!$h) die('Error opening cache file "'.$cache_filename.'" for writing.');
        fwrite($h, $data);
        fclose($h);
      }
    }
    unlink($nam);
  }
  $len = strlen($data);
  header('Content-type: '.$row['mimetype']);
  if(isset($_GET['download'])) header('Content-disposition: attachment, filename="'.$filename.'";');
  header('Content-length: '.$len);
  header('Last-Modified: '.date('r', $row['time_id']));
  echo($data);
  
  gzip_output();
  
  exit;
  
}

?>