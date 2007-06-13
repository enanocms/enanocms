<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link rel="stylesheet" href="{STYLE_LINK}" type="text/css" id="mdgCss" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/themes/{THEME_ID}/css-simple/{STYLE_ID}.css" />
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
  
    <table border="0" style="width: 100%; height: 100%;">
    <tr>
    <td style="width: 10%;"></td>
    <td valign="middle">
  
    <table border="0" cellspacing="0" cellpadding="0" id="enano-master" width="100%" style="height: 200px;">
      <tr>
        <td colspan="2" style="height: 96px; padding: 10px;" id="header-banner">
          <h1>{PAGE_NAME}</h1>
        </td>
      </tr>
      <tr>
        <td valign="top" id="enanomain">
          <div class="contentDiv">
            <div id="ajaxEditContainer">
