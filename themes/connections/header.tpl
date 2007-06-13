<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" type="text/css" href="{SCRIPTPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" />
    {JS_DYNAMIC_VARS}
    <!-- This script automatically loads the other 15 JS files -->
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    {ADDITIONAL_HEADERS}
  </head>
  <body>
    
    <div id="root1" class="jswindow">
      <div id="tb1" class="titlebar">Confirm Logout</div>
      <div class="content" id="cn1">
        <form action="{CONTENTPATH}Special:Logout" method="get">
          <div style="text-align: center">
            <h3>Are you sure you want to log out?</h3>
            <input type="submit" value="Log out" style="font-weight: bold;" />  <input type="button" onclick="jws.closeWin('root1');" value="Cancel" />
          </div>
        </form>
      </div>  
    </div>
    <div id="root2" class="jswindow">
      <div id="tb2" class="titlebar">Change style</div>
      <div class="content" id="cn2">
        If you can see this text, it means that your browser does not support Cascading Style Sheets (CSS). CSS is a fundemental aspect of XHTML, and as a result it is becoming very widely adopted by websites, including this one. You should consider switching to a more modern web browser, such as Mozilla Firefox or Opera 9.
      </div>  
    </div>
    
    <div id="rap">
      <div id="header">
        <ul id="topnav">
          <li>&nbsp;</li>
        </ul>
        <h1><a href="{CONTENTPATH}" title="{SITE_NAME}">{SITE_NAME}</a></h1>
        <div id="desc">{PAGE_NAME}</div>
      </div>
      <div class="menu_nojs" id="pagebar_main">
        <div class="label">Page tools</div>
        {TOOLBAR}
        <ul>
          {TOOLBAR_EXTRAS}
        </ul>
        <span class="menuclear">&nbsp;</span>
      </div>
      <div id="main">
        <div id="content">
          <div class="post">
            <div class="post-info">
              <div style="float: right;">
                <image alt=" " src="{SCRIPTPATH}/images/spacer.gif" id="ajaxloadicon" />
              </div>
              <h2 class="post-title">{PAGE_NAME}</h2>
            </div>
            <div class="post-content">
            
            <div id="ajaxEditContainer">
            <!-- START CONTENT -->
