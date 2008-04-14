<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.4 (Caoineag alpha 4)
 * Copyright (C) 2006-2008 Dan Fuhry
 * Installation package
 * license.php - Installer license-agreement stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
  die();

function show_license($fb = false)
{
  global $lang;
  global $installer_version;
  ?>
  <div class="scroller">
  <?php
    if ( !file_exists('./GPL') || !file_exists('./language/english/install/license-deed.html') )
    {
      echo 'Cannot find the license files.';
    }
    echo file_get_contents('./language/english/install/license-deed.html');
    if ( $installer_version['type'] != 'stable' )
    {
      ?>
      <h3><?php echo $lang->get('license_info_unstable_title'); ?></h3>
      <p><?php echo $lang->get('license_info_unstable_body'); ?></p>
      <?php
    }
    ?>
    <h3><?php echo $lang->get('license_section_gpl_heading'); ?></h3>
    <?php if ( $lang->lang_code != 'eng' ): ?>
    <p><i><?php echo $lang->get('license_gpl_blurb_inenglish'); ?></i></p>
    <?php endif; ?>
    <?php echo wikiFormat(file_get_contents(ENANO_ROOT . '/GPL')); ?>
   <?php
   global $template;
   if ( $fb )
   {
     echo '<p style="text-align: center;">Because I could never find the Create a Page button in PHP-Nuke.</p>';
     echo '<p>' . str_replace('http://enanocms.org/', 'http://www.2robots.com/2003/10/15/web-portals-suck/', $template->fading_button) . '</p>';
     echo '<p style="text-align: center;">It\'s not a portal, my friends.</p>';
   }
   ?>
 </div>
 <?php
}

function wikiFormat($message, $filter_links = true)
{
  $wiki = & Text_Wiki::singleton('Mediawiki');
  $wiki->setRenderConf('Xhtml', 'code', 'css_filename', 'codefilename');
  $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', scriptPath . '/index.php?title=');
  $result = $wiki->transform($message, 'Xhtml');
  
  // HTML fixes
  $result = preg_replace('#<tr>([\s]*?)<\/tr>#is', '', $result);
  $result = preg_replace('#<p>([\s]*?)<\/p>#is', '', $result);
  $result = preg_replace('#<br />([\s]*?)<table#is', '<table', $result);
  
  return $result;
}

?>
    <h3><?php echo $lang->get('license_heading'); ?></h3>
     <p><?php echo $lang->get('license_blurb_thankyou'); ?></p>
     <p><?php echo $lang->get('license_blurb_pleaseread'); ?></p>
     <?php show_license(); ?>
     <div class="pagenav">
       <form action="install.php?stage=sysreqs" method="post">
       <?php
       echo '<input type="hidden" name="language" value="' . $lang_id . '" />';
       ?>
         <table border="0">
         <tr>
           <td>
             <input type="submit" value="<?php echo $lang->get('license_btn_i_agree'); ?>" />
           </td>
           <td>
             <p>
               <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
               &bull; <?php echo $lang->get('license_objective_ensure_agree'); ?><br />
               &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
             </p>
           </td>
         </tr>
         </table>
       </form>
     </div>
    <?php

?>
