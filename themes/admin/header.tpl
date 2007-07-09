<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" type="text/css" href="{SCRIPTPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" />
    {JS_DYNAMIC_VARS}
    <script type="text/javascript" src="{SCRIPTPATH}/themes/admin/js/menu.js"></script>
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    {ADDITIONAL_HEADERS}
    </head>
  <body>
    <div id="header">
      <div class="sitename">{SITE_NAME}</div>
      <div class="menulink"><a href="#" onclick="adminOpenMenu('sidebar', this); return false;">expand menu</a></div>
      [&nbsp;<a href="{SCRIPTPATH}/{ADMIN_SID_QUES}">Main page &#0187;</a>&nbsp;]
    </div>
    <div class="menu_nojs" id="pagebar_main">
      <div class="label">Page tools</div>
      {TOOLBAR}
      <ul>
        {TOOLBAR_EXTRAS}
      </ul>
      <span class="menuclear">&nbsp;</span>
    </div>
    <div id="sidebar">
      {SIDEBAR_LEFT}
      {SIDEBAR_RIGHT}
    </div>
    <table border="0" cellspacing="0" cellpadding="0" id="wrapper">
      <tr>
        <td class="top-left"></td><td class="top">&nbsp;</td><td class="top-right"></td>
      </tr>
      <tr>
        <td class="left"></td>
        <td class="main">
          <div style="float: right;">
            <image alt=" " src="{SCRIPTPATH}/images/spacer.gif" id="ajaxloadicon" />
          </div>
          <h2 class="pagename">{PAGE_NAME}</h2>
          <div id="ajaxEditContainer">
