<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    {JS_DYNAMIC_VARS}
    <link rel="stylesheet" type="text/css" href="{CDNPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" type="text/css" href="{CDNPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" />
    <!--[if lte IE 6]>
    <link rel="stylesheet" type="text/css" href="{CDNPATH}/themes/{THEME_ID}/css-extra/ie6.css" />
    <![endif]-->
    {JS_HEADER}
    {ADDITIONAL_HEADERS}
    <script type="text/javascript">//<![CDATA[
    var auth_rename = <!-- BEGIN auth_rename -->true<!-- BEGINELSE auth_rename -->false<!-- END auth_rename -->;
    // ]]>
    </script>
    <script type="text/javascript" src="{CDNPATH}/themes/{THEME_ID}/js/inlinerename.js"></script>
  </head>
  <body>
    <div id="header">
      <?php
      global $session;
      
      if ( is_object($paths) && $head = $paths->sysMsg('SiteHeader') )
      {
        echo '<a class="header-placeholder" href="' . makeUrl(get_main_page()) . '">&nbsp;</a>';
        echo $head;
      }
      else
      {
      ?>
        <div class="logo"></div>
        <h1><a href="<?php echo is_object($session) ? makeUrl(get_main_page(), false, true) : scriptPath . '/'; ?>">{SITE_NAME}</a></h1>
      <?php
        }
      ?>
    </div>
    <!-- HOOK enanium_main_header -->
    <!-- BEGINNOT stupid_mode -->
    <form class="searchform" action="{SCRIPTPATH}/index.php" method="get">
      <div>
        <input type="hidden" name="title" value="{NS_SPECIAL}Search" />
        <input type="hidden" name="auth" value="{ADMIN_SID_RAW}" />
        <input type="text" name="q" value="" />
        <input type="submit" value="search" />
      </div>
      <!-- HOOK enanium_search_form -->
    </form>
    <ul class="useropts">
      <!-- BEGIN user_logged_in -->
      <li class="em"><a href="{url:User:{USERNAME}}">{USERNAME}</a></li>
      <li><a href="{url:Special:Preferences}">{lang:sidebar_btn_preferences_short}</a></li>
      <li class="logout"><a href="{url:Special:Logout/{CSRF_TOKEN}/{PAGE_URLNAME}}" onclick="mb_logout(); return false;">{lang:sidebar_btn_logout}</a></li>
      <!-- BEGINELSE user_logged_in -->
      <li class="em"><a href="{url:Special:Login}" onclick="ajaxStartLogin(); return false;">{lang:sidebar_btn_login}</a></li>
      <li><a href="{url:Special:Register}">{lang:sidebar_btn_register}</a></li>
      <!-- END user_logged_in -->
    </ul>
    <!-- END stupid_mode -->
    <!-- Yes this is table based. For reliability reasons. -->
    <table border="0" cellspacing="0" cellpadding="0" id="body-wrapper">
    <tr>
      <td valign="top" id="cell-sbleft">
        <div class="left sidebar" id="enanium_sidebar_left">
          <a class="closebtn" onclick="enanium_toggle_sidebar_left(); return false;">&laquo;</a>
          {SIDEBAR_LEFT}
        </div>
        <div class="left-sidebar-hidden" id="enanium_sidebar_left_hidden">
          <a class="openbtn" onclick="enanium_toggle_sidebar_left(); return false;">&raquo;</a>
        </div>
        <!-- HOOK sidebar_left_post -->
      </td>
      <td valign="top" id="cell-content">
        <!-- BEGINNOT stupid_mode -->
        <div class="menu_nojs" style="float: right; margin-right: 10px;">
          <a href="#" onclick="return false;">{lang:onpage_lbl_changes}</a>
          <ul class="jbox_right">
            <li><a href="{url:Special:Log/user={USERNAME}|escape}">{lang:onpage_btn_changes_mine}</a></li>
            <li><a href="{url:Special:Log/within=1w|escape}">{lang:onpage_btn_changes_recent}</a></li>
            <li><a href="{url:Special:Log/page={PAGE_URLNAME}|escape}">{lang:onpage_btn_changes_history}</a></li>
          </ul>
          <a href="#" onclick="return false;">{lang:onpage_lbl_sitetools}</a>
          <ul class="jbox_right">
            <li><a href="{url:Special:CreatePage|escape}">{lang:sidebar_btn_createpage}</a></li>
            <li><a href="{url:Special:UploadFile|escape}">{lang:sidebar_btn_uploadfile}</a></li>
            <li><a href="{url:Special:SpecialPages|escape}">{lang:sidebar_btn_specialpages}</a></li>
            <!-- BEGIN user_logged_in -->
            <li><a href="{url:Special:Memberlist|escape}">{lang:specialpage_member_list}</a></li>
            <!-- END user_logged_in -->
            <!-- BEGIN auth_admin -->
            {SIDEBAR_LINK}
            {ADMIN_LINK}
            <!-- END auth_admin -->
          </ul>
          <span class="menuclear"></span>
        </div>
        <!-- END stupid_mode -->
        <div class="menu_nojs" id="pagebar_main">
          <div class="label">
            <!-- BEGIN stupid_mode -->
            Page tools
            <!-- BEGINELSE stupid_mode -->
            {lang:onpage_lbl_pagetools}
            <!-- END stupid_mode -->
          </div>
          {TOOLBAR}
          <ul>
            {TOOLBAR_EXTRAS}
          </ul>
          <span class="menuclear"></span>
        </div>
        <div id="content-wrapper" class="content">
          <table border="0" cellspacing="0" cellpadding="0" style="width: 100%;">
          <tr>
          <td valign="top" style="width: 100%;">
          <div style="float: right;">
            <img alt=" " src="{CDNPATH}/images/spacer.gif" id="ajaxloadicon" />
          </div>
          <h1 <!-- BEGIN auth_rename -->title="{lang:onpage_btn_rename_inline}" <!-- END auth_rename -->id="h2PageName">{PAGE_NAME}</h1>
          <div id="ajaxEditContainer">
