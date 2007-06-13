<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link rel="stylesheet" href="{STYLE_LINK}" type="text/css" id="mdgCss" />
    {JS_DYNAMIC_VARS}
    <!-- This script automatically loads the other 15 JS files -->
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    <script type="text/javascript">
    
      function collapseSidebar(side)
      {
        elem = document.getElementById(side+'-sidebar');
        counter = document.getElementById(side+'-sidebar-showbutton');
        if(elem.style.display=='none')
        {
          elem.style.display = 'block';
          counter.style.display = 'none';
          elem.parentNode.style.width = '156px';
          createCookie(side+'_sidebar', 'open', 365);
        } else {
          elem.style.display = 'none';
          counter.style.display = 'block';
          elem.parentNode.style.width = '25px';
          createCookie(side+'_sidebar', 'collapsed', 365);
        }
      }
      
      window.onload = function() {
        if(readCookie('left_sidebar') =='collapsed') collapseSidebar('left');
        if(readCookie('right_sidebar')=='collapsed') collapseSidebar('right');
        mdgInnerLoader();
      }
    </script>
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
    <div id="root3" class="jswindow">
      <div id="tb3" class="titlebar">Wiki formatting help</div>
      <div class="content" id="cn3">
        Loading...
      </div>
    </div>
    <table border="0" cellspacing="0" cellpadding="0" id="enano-master" width="100%">
      <tr><td colspan="2" style="height: 96px;"><a href="{SCRIPTPATH}/{ADMIN_SID_QUES}"><img alt="{SITE_NAME}" src="{SCRIPTPATH}/themes/boxart/images/logo-{STYLE_ID}.png" style="border: 0px;" /></a></td></tr>
      <tr>
      <td class="mdgSidebarHolder" valign="top">
        <div id="left-sidebar">
          
          <div class="sidebar">
            {SIDEBAR_LEFT}
            {SIDEBAR_RIGHT}
          </div>
          
        </div>
        <div id="left-sidebar-showbutton" style="display: none; position: fixed; top: 3px; left: 3px;">
          <input type="button" onclick="collapseSidebar('left');" value="&gt;&gt;" />
        </div>
      </td>
      <td valign="top" id="enanomain">
        <table border="0" width="100%" cellspacing="0" cellpadding="0">
      
        <td>
          <div class="menu_nojs" id="pagebar_main">
            <div class="label">Page tools</div>
            {TOOLBAR}
            <ul>
              {TOOLBAR_EXTRAS}
            </ul>
            <span class="menuclear">&nbsp;</span>
          </div>
        </td>
        <tr>
          <td>
          <div class="contentDiv">
          <h2>{PAGE_NAME}</h2>
            <div id="ajaxEditContainer">
