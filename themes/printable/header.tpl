<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" href="{SCRIPTPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" type="text/css" />
    <link rel="stylesheet" media="print" href="{SCRIPTPATH}/themes/{THEME_ID}/css-simple/printbits.css" type="text/css" />
    {JS_DYNAMIC_VARS}
    <!-- This script automatically loads the other 15 JS files -->
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    {ADDITIONAL_HEADERS}
    
  </head>
  <body>
    <div class="pad"><div class="contentDiv">
      <div style="float: right;">
        <span class="normallink"><a href="#" onclick="window.print(); return false;">print page</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="{CONTENTPATH}{PAGE_URLNAME}{ADMIN_SID_AUTO}">view normal version</a></span>&nbsp;<image alt=" " src="{SCRIPTPATH}/images/spacer.gif" id="ajaxloadicon" />
      </div>
      <h2>{PAGE_NAME}</h2>
        <div id="ajaxEditContainer">
