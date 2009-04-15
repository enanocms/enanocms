<?php
/**!info**
{
  "Plugin Name"  : "plugin_speciallog_title",
  "Plugin URI"   : "http://enanocms.org/",
  "Description"  : "plugin_speciallog_desc",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.1.6",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.6 (Caoineag beta 1)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function SpecialLog_paths_init()
{
  global $paths;
  $paths->add_page(Array(
    'name'=>'specialpage_log',
    'urlname'=>'Log',
    'namespace'=>'Special',
    'special'=>0,'visible'=>1,'comments_on'=>0,'protected'=>1,'delvotes'=>0,'delvote_ips'=>'',
    ));
}

function page_Special_Log()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $output;
  
  // FIXME: This doesn't currently prohibit viewing of aggregate logs that might include a page for which
  // 
  
  // FIXME: This is a real hack. We're trying to get permissions on a random non-existent article, which
  // effectively forces calculation to occur based on site-wide permissions.
  $pid = '';
  for ( $i = 0; $i < 32; $i++ )
  {
    $pid .= chr(mt_rand(32, 126));
  }
  $perms = $session->fetch_page_acl($pid, 'Article');
  $perms_changed = false;
  
  require_once(ENANO_ROOT . '/includes/log.php');
  $log = new LogDisplay();
  $page = 1;
  $pagesize = 50;
  $fmt = 'full';
  
  if ( $params = $paths->getAllParams() )
  {
    if ( $params === 'AddFilter' && !empty($_POST['type']) && !empty($_POST['value']) )
    {
      $type = $_POST['type'];
      if ( $type == 'within' )
        $value = strval(intval($_POST['value']['within'])) . $_POST['value']['withinunits'];
      else
        $value = $_POST['value'][$type];
        
      $value = str_replace('/', '.2f', sanitize_page_id($value));
        
      if ( empty($value) || ( $type == 'within' && intval($value) == 0 ) )
      {
        $adderror = $lang->get('log_err_addfilter_field_empty');
      }
      
      $append = ( !empty($_POST['existing_filters']) ) ? "{$_POST['existing_filters']}/" : '';
      $url = makeUrlNS('Special', "Log/{$append}{$type}={$value}");
      
      redirect($url, '', '', 0);
    }
    $params = explode('/', $params);
    foreach ( $params as $param )
    {
      $param = str_replace('.2f', '/', dirtify_page_id($param));
      if ( preg_match('/^([a-z!]+)=(.+?)$/', $param, $match) )
      {
        $name =& $match[1];
        $value =& $match[2];
        switch($name)
        {
          case 'resultpage':
            $page = intval($value);
            break;
          case 'size':
            $pagesize = intval($value);
            break;
          case 'fmt':
            switch($value)
            {
              case 'barenaked':
              case 'ajax':
                $fmt = 'naked';
                $output = new Output_Naked();
                break;
            }
            break;
          case 'page':
            if ( get_class($perms) == 'sessionManager' )
            {
              unset($perms);
              list($pid, $ns) = RenderMan::strToPageID($value);
              $perms = $session->fetch_page_acl($pid, $ns);
              if ( !$perms->get_permissions('history_view') )
              {
                die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('log_err_access_denied') . '</p>');
              }
            }
            // no break here on purpose
          default:
            try
            {
              $log->add_criterion($name, $value);
            }
            catch ( Exception $e )
            {
            }
            break;
        }
      }
    }
  }
  if ( !$perms->get_permissions('history_view') )
  {
    die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('log_err_access_denied') . '</p>');
  }
  
  $page--;
  $rowcount = $log->get_row_count();  
  $result_url = makeUrlNS('Special', 'Log/' . rtrim(preg_replace('|/?resultpage=([0-9]+)/?|', '/', $paths->getAllParams()), '/') . '/resultpage=%s', false, true);
  $paginator = generate_paginator($page, ceil($rowcount / $pagesize), $result_url);
  
  $dataset = $log->get_data($page * $pagesize, $pagesize);
  
  $output->header();
  
  // breadcrumbs
  if ( $fmt != 'naked' )
  {
    echo '<div class="breadcrumbs" style="font-weight: normal;" id="log-breadcrumbs">';
    echo speciallog_generate_breadcrumbs($log->get_criteria());
    echo '</div>';
  
  // form
  ?>
  
  <!-- Begin filter add form -->
  
  <form action="<?php echo makeUrlNS('Special', 'Log/AddFilter', false, true); ?>" method="post" enctype="multipart/form-data">
    <?php
    // serialize parameters
    $params_pre = rtrim(preg_replace('#/?resultpage=[0-9]+/?#', '/', $paths->getAllParams()), '/');
    echo '<input type="hidden" name="existing_filters" value="' . htmlspecialchars($params_pre) . '" />';
    ?>
    <script type="text/javascript">//<![CDATA[
      addOnloadHook(function()
        {
          load_component('jquery');
          $('#log_addfilter_select').change(function()
            {
              var value = $(this).val();
              $('.log_addfilter').hide();
              $('#log_addform_' + value).show();
            });
          $('#log_addform_' + $('#log_addfilter_select').val()).show();
        });
    // ]]>
    </script>
    <?php
    if ( isset($adderror) )
    {
      echo '<div class="error-box">' . $adderror . '</div>';
    }
    ?>
    <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
      <tr>
        <th colspan="2">
          <?php echo $lang->get('log_heading_addfilter'); ?>
        </th>
      </tr>
      <tr>
      <td class="row1" style="width: 50%; text-align: right;">
          <select name="type" id="log_addfilter_select">
            <option value="user"><?php echo $lang->get('log_form_filtertype_user'); ?></option>
            <option value="page"><?php echo $lang->get('log_form_filtertype_page'); ?></option>
            <option value="within"><?php echo $lang->get('log_form_filtertype_within'); ?></option>
            <option value="action"><?php echo $lang->get('log_form_filtertype_action'); ?></option>
          </select>
        </td>
        <td class="row1" style="width: 50%; text-align: left;">
          <div class="log_addfilter" id="log_addform_user">
            <input type="text" class="autofill username" name="value[user]" size="40" />
          </div>
          <div class="log_addfilter" id="log_addform_page">
            <input type="text" class="autofill page" name="value[page]" size="40" />
          </div>
          <div class="log_addfilter" id="log_addform_within">
            <input type="text" name="value[within]" size="7" />
            <select name="value[withinunits]">
              <option value="d"><?php echo $lang->get('etc_unit_days'); ?></option>
              <option value="w"><?php echo $lang->get('etc_unit_weeks'); ?></option>
              <option value="m"><?php echo $lang->get('etc_unit_months'); ?></option>
              <option value="y"><?php echo $lang->get('etc_unit_years'); ?></option>
            </select>
          </div>
          <div class="log_addfilter" id="log_addform_action">
            <select name="value[action]">
              <option value="rename"><?php echo $lang->get('log_formaction_rename'); ?></option>
              <option value="create"><?php echo $lang->get('log_formaction_create'); ?></option>
              <option value="delete"><?php echo $lang->get('log_formaction_delete'); ?></option>
              <option value="protect"><?php echo $lang->get('log_action_protect'); ?></option>
              <option value="edit"><?php echo $lang->get('log_action_edit'); ?></option>
            </select>
          </div>
        </td>
      </tr>
      <tr>
        <th colspan="2" class="subhead">
          <input type="submit" value="<?php echo $lang->get('log_btn_add_filter'); ?>" />
        </th>
      </tr>
    </table>
    </div>
  
  </form>
  
  <!-- End filter add form -->
  
  <?php
  
  }
  
  // start of actual log output area
  if ( $fmt != 'naked' )
  {
    echo '<div id="log-body">';
  }
  
  if ( $rowcount > 0 )
  {
    // we have some results, show pagination + result list
    echo '<h3 style="float: left;">' . $lang->get('log_heading_logdisplay') . '</h3>';
    
    echo $paginator;
    // padding
    echo '<div style="height: 10px;"></div>';
    foreach ( $dataset as $row )
    {
      echo LogDisplay::render_row($row) . '<br />';
    }
    echo $paginator;
  }
  else
  {
    // no results
    echo '<h2 class="emptymessage">' . $lang->get('log_msg_no_results') . '</h2>';
  }
  
  if ( $fmt != 'naked' )
    echo '</div> <!-- div#log-body -->';
  
  $output->footer();
}

function speciallog_generate_breadcrumbs($criteria)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  if ( count($criteria) == 0 )
  {
    return $lang->get('log_msg_no_filters');
  }
  
  $html = array();
  foreach ( $criteria as $criterion )
  {
    list($type, $value) = $criterion;
    switch($type)
    {
      case 'user':
        $rank_info = $session->get_user_rank($value);
        $user_link = '<a href="' . makeUrlNS('User', $value, false, true) . '" style="' . $rank_info['rank_style'] . '" title="' . htmlspecialchars($lang->get($rank_info['rank_title'])) . '">';
        $user_link .= htmlspecialchars(str_replace('_', ' ', $value)) . '</a>';
        
        $crumb = $lang->get('log_breadcrumb_author', array('user' => $user_link));
        break;
      case 'page':
        $crumb = $lang->get('log_breadcrumb_page', array('page' => '<a href="' . makeUrl($value, false, true) . '">' . htmlspecialchars(get_page_title($value)) . '</a>'));
        break;
      case 'action':
        $crumb = $lang->get('log_breadcrumb_action', array('action' => htmlspecialchars($lang->get("log_action_{$value}"))));
        break;
      case 'within':
        $value = intval($value);
        if ( $value % 31536000 == 0 )
        {
          $n = $value / 31536000;
          $value = "$n " . $lang->get( $n > 1 ? 'etc_unit_years' : 'etc_unit_year' );
        }
        else if ( $value % 2592000 == 0 )
        {
          $n = $value / 2592000;
          $value = "$n " . $lang->get( $n > 1 ? 'etc_unit_months' : 'etc_unit_month' );
        }
        else if ( $value % 604800 == 0 )
        {
          $n = $value / 604800;
          $value = "$n " . $lang->get( $n > 1 ? 'etc_unit_weeks' : 'etc_unit_week' );
        }
        else if ( $value % 86400 == 0 )
        {
          $n = $value / 86400;
          $value = "$n " . $lang->get( $n > 1 ? 'etc_unit_days' : 'etc_unit_day' );
        }
        else
        {
          $value = "$value " . $lang->get( $value > 1 ? 'etc_unit_seconds' : 'etc_unit_second' );
        }
        $crumb = $lang->get('log_breadcrumb_within', array('time' => $value));
        break;
    }
    $html[] = $crumb . ' ' . speciallog_crumb_remove_link($criterion);
  }
  return implode(' &raquo; ', $html);
}

function speciallog_crumb_remove_link($criterion)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  list($type, $value) = $criterion;
  
  $params = explode('/', dirtify_page_id($paths->getAllParams()));
  foreach ( $params as $i => $param )
  {
    if ( $param === "$type=$value" )
    {
      unset($params[$i]);
      break;
    }
    else if ( $type === 'within' )
    {
      list($ptype, $pvalue) = explode('=', $param);
      if ( $ptype !== 'within' )
        continue;
      
      $lastchar = substr($pvalue, -1);
      $amt = intval($pvalue);
      switch($lastchar)
      {
        case 'd':
          $amt = $amt * 86400;
          break;
        case 'w':
          $amt = $amt * 604800;
          break;
        case 'm':
          $amt = $amt * 2592000;
          break;
        case 'y':
          $amt = $amt * 31536000;
          break;
      }
      if ( $amt === $value )
      {
        unset($params[$i]);
        break;
      }
    }
  }
  if ( count($params) > 0 )
  {
    $params = implode('/', $params);
    $url = makeUrlNS('Special', "Log/$params", false, true);
  }
  else
  {
    $url = makeUrlNS('Special', "Log", false, true);
  }
  
  return '<sup><a href="' . $url . '">(x)</a></sup>';
}
