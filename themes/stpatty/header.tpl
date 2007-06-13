<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    {JS_DYNAMIC_VARS}
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" type="text/css" href="{SCRIPTPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" />
    <!-- This script automatically loads the other 15 JS files -->
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/themes/{THEME_ID}/css-extra/ie-fixes.css" />
    <![endif]-->
    <script type="text/javascript">
    // <![CDATA[
      function ajaxRenameInline()
      {
        // This trick is _so_ vBulletin...
        elem = document.getElementById('pagetitle');
        if(!elem) return;
        elem.style.display = 'none';
        name = elem.innerHTML;
        textbox = document.createElement('input');
        textbox.type = 'text';
        textbox.value = name;
        textbox.id = 'pageheading';
        textbox.size = name.length + 7;
        textbox.onkeyup = function(e) { if(!e) return; if(e.keyCode == 13) ajaxRenameInlineSave(); if(e.keyCode == 27) ajaxRenameInlineCancel(); };
        elem.parentNode.insertBefore(textbox, elem);
        document.onclick = ajaxRenameInlineCancel;
      }
      function ajaxRenameInlineSave()
      {
        elem1 = document.getElementById('pagetitle');
        elem2 = document.getElementById('pageheading');
        if(!elem1 || !elem2) return;
        value = elem2.value;
        elem2.parentNode.removeChild(elem2); // just destroy the thing
        elem1.innerHTML = value;
        elem1.style.display = 'block';
        if(!value || value=='') return;
        ajaxPost(stdAjaxPrefix+'&_mode=rename', 'newtitle='+escape(value), function() {
          if(ajax.readyState == 4) {
            alert(ajax.responseText);
          }
        });
      }
      function ajaxRenameInlineCancel(e)
      {
        if ( !e )
          e = window.event;
        elem1 = document.getElementById('pagetitle');
        elem2 = document.getElementById('pageheading');
        if(!elem1 || !elem2) return;
        if ( e && e.target )
        {
          if(e.target == elem2)
            return;
        }
        //value = elem2.value;
        elem2.parentNode.removeChild(elem2); // just destroy the thing
        //elem1.innerHTML = value;
        elem1.style.display = 'block';
        document.onclick = null;
      }
      // ]]>
    </script>
    {ADDITIONAL_HEADERS}
  </head>
  <body>
    <div id="bg">
      <div id="rap">
        <div id="title">
          <img id="clover" src="{SCRIPTPATH}/themes/{THEME_ID}/images/clover.png" alt=" " />
          <h1>{SITE_NAME}</h1>
          <h2>{SITE_DESC}</h2>
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
          <!-- BEGIN sidebar_left -->
          {SIDEBAR_LEFT}
          <!-- END sidebar_left -->
          <!-- BEGIN sidebar_right -->
          <!-- BEGINNOT in_admin -->
          {SIDEBAR_RIGHT}
          <!-- END in_admin -->
          <!-- END sidebar_right -->
        </div>
        <div id="maincontent">
          <div style="float: right;">
            <img alt=" " src="{SCRIPTPATH}/images/spacer.gif" id="ajaxloadicon" />
          </div>
          <h2 id="pagetitle" <!-- BEGIN auth_rename --> ondblclick="ajaxRenameInline();" title="Double-click to rename this page" <!-- END auth_rename -->>{PAGE_NAME}</h2>
          <div id="ajaxEditContainer">
            
