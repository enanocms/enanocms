<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

class Namespace_File extends Namespace_Default
{
  function send()
  {
    global $output;
    
    $output->add_before_footer($this->show_info());
    $output->add_before_footer($this->display_categories());
    
    if ( $this->exists )
    {
      $this->send_from_db();
    }
    else
    {
      $output->header();
      $this->error_404();
      $output->footer();
    }
  }
  
  function show_info()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $lang;
    
    require_once(ENANO_ROOT . '/includes/log.php');
    
    $local_page_id = $this->page_id;
    $local_namespace = $this->namespace;
    $html = '';
    
    // Prevent unnecessary work
    if ( $local_namespace != 'File' )
      return null;
    
    $selfn = $db->escape($this->page_id);
    $q = $db->sql_query('SELECT f.mimetype,f.time_id,f.size,l.log_id FROM ' . table_prefix . "files AS f\n"
                      . "  LEFT JOIN " . table_prefix . "logs AS l\n"
                      . "    ON ( l.time_id = f.time_id AND ( l.action = 'reupload' OR l.action IS NULL ) )\n"
                      . "  WHERE f.page_id = '$selfn'\n"
                      . "    ORDER BY f.time_id DESC;");
    if ( !$q )
    {
      $db->_die('The file type could not be fetched.');
    }
    
    if ( $db->numrows() < 1 )
    {
      $html .= '<div class="mdg-comment" style="margin-left: 0;">
              <h3>' . $lang->get('onpage_filebox_heading') . '</h3>
              <p>' . $lang->get('onpage_filebox_msg_not_found', array('upload_link' => makeUrlNS('Special', 'UploadFile/'.$local_page_id))) . '</p>
            </div>
            <br />';
      return $html;
    }
    $r = $db->fetchrow();
    $mimetype = $r['mimetype'];
    $datestring = enano_date(ED_DATE | ED_TIME, (int)$r['time_id']);
    $html .= '<div class="mdg-comment" style="margin-left: 0;">
            <h3>' . $lang->get('onpage_filebox_heading') . '</h3>
            <p>' . $lang->get('onpage_filebox_lbl_type') . ' '.$r['mimetype'].'<br />';
    
    $size = $r['size'] . ' ' . $lang->get('etc_unit_bytes');
    if ( $r['size'] >= 1048576 )
    {
      $size .= ' (' . ( round($r['size'] / 1048576, 1) ) . ' ' . $lang->get('etc_unit_megabytes_short') . ')';
    }
    else if ( $r['size'] >= 1024 )
    {
      $size .= ' (' . ( round($r['size'] / 1024, 1) ) . ' ' . $lang->get('etc_unit_kilobytes_short') . ')';
    }
    
    $html .= $lang->get('onpage_filebox_lbl_size', array('size' => $size));
    
    $html .= '<br />' . $lang->get('onpage_filebox_lbl_uploaded') . ' ' . $datestring . '</p>';
    if ( substr($mimetype, 0, 6) != 'image/' && ( substr($mimetype, 0, 5) != 'text/' || $mimetype == 'text/html' || $mimetype == 'text/javascript' ) )
    {
      $html .= '<div class="warning-box">
              ' . $lang->get('onpage_filebox_msg_virus_warning') . '
            </div>';
    }
    if ( substr($mimetype, 0, 6) == 'image/' )
    {
      $html .= '<p>
              <a href="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn).'">
                <img style="border: 0;" alt="'.$paths->page.'" src="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn.htmlspecialchars(urlSeparator).'preview').'" />
              </a>
            </p>';
    }
    $html .= '<p>
            <a href="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn.'/'.$r['time_id'].htmlspecialchars(urlSeparator).'download').'">
              ' . $lang->get('onpage_filebox_btn_download') . '
            </a>';
    if(!$paths->page_protected && ( $paths->wiki_mode || $session->get_permissions('upload_new_version') ))
    {
      $html .= '  |  <a href="'.makeUrlNS('Special', 'UploadFile'.'/'.$selfn).'">
              ' . $lang->get('onpage_filebox_btn_upload_new') . '
            </a>';
    }
    $html .= '</p>';
    if ( $db->numrows() > 1 )
    {
      // requery, sql_result_seek() doesn't work on postgres
      $db->free_result();
      $q = $db->sql_query('SELECT f.mimetype,f.time_id,f.size,l.log_id FROM ' . table_prefix . "files AS f\n"
                      . "  LEFT JOIN " . table_prefix . "logs AS l\n"
                      . "    ON ( l.time_id = f.time_id AND ( l.action = 'reupload' OR l.action IS NULL ) )\n"
                      . "  WHERE f.page_id = '$selfn'\n"
                      . "    ORDER BY f.time_id DESC;");
      if ( !$q )
        $db->_die();
      
      $log = new LogDisplay();
      $log->add_criterion('page', $paths->nslist['File'] . $this->page_id);
      $log->add_criterion('action', 'reupload');
      $data = $log->get_data();
      $i = -1;
      
      $html .= '<h3>' . $lang->get('onpage_filebox_heading_history') . '</h3><p>';
      $last_rollback_id = false;
      while ( $r = $db->fetchrow($q) )
      {
        $html .= '(<a href="'.makeUrlNS('Special', 'DownloadFile'.'/'.$selfn.'/'.$r['time_id'].htmlspecialchars(urlSeparator).'download').'">' . $lang->get('onpage_filebox_btn_this_version') . '</a>) ';
        if ( $session->get_permissions('history_rollback') && $last_rollback_id )
          $html .= ' (<a href="#rollback:' . $last_rollback_id . '" onclick="ajaxRollback(\''.$last_rollback_id.'\'); return false;">' . $lang->get('onpage_filebox_btn_revert') . '</a>) ';
        else if ( $session->get_permissions('history_rollback') && !$last_rollback_id )
          $html .= ' (' . $lang->get('onpage_filebox_btn_current') . ') ';
        $last_rollback_id = $r['log_id'];
        
        $html .= $r['mimetype'].', ';
        
        $fs = $r['size'];
        $fs = (int)$fs;
        
        if($fs >= 1048576)
        {
          $fs = round($fs / 1048576, 1);
          $size = $fs . ' ' . $lang->get('etc_unit_megabytes_short');
        }
        else
        if ( $fs >= 1024 )
        {
          $fs = round($fs / 1024, 1);
          $size = $fs . ' ' . $lang->get('etc_unit_kilobytes_short');
        }
        else
        {
          $size = $fs . ' ' . $lang->get('etc_unit_bytes');
        }
        
        $html .= $size;
        if ( isset($data[++$i]) )
          $html .= ': ' . LogDisplay::render_row($data[$i], false, false);
        
        $html .= '<br />';
      }
      $html .= '</p>';
    }
    $db->free_result();
    $html .= '</div><br />';
    return $html;
  }
}

