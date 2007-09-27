<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>{PAGE_NAME} &bull; {SITE_NAME}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="{SCRIPTPATH}/includes/clientside/css/enano-shared.css" />
    <link id="mdgCss" rel="stylesheet" href="{SCRIPTPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css" type="text/css" />
    {JS_DYNAMIC_VARS}
    <!-- This script automatically loads the other 15 JS files -->
    <script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
    {ADDITIONAL_HEADERS}
    
    <script type="text/javascript">
    
      function collapseSidebar(side)
      {
        elem = document.getElementById(side+'-sidebar');
        if(!elem) return;
        counter = document.getElementById(side+'-sidebar-showbutton');
        if(elem.style.display=='none')
        {
          elem.style.display = 'block';
          counter.style.display = 'none';
          elem.parentNode.style.width = '156px';
          if ( !KILL_SWITCH )
          {
            createCookie(side+'_sidebar', 'open', 365);
          }
        } else {
          elem.style.display = 'none';
          counter.style.display = 'block';
          elem.parentNode.style.width = '25px';
          if ( !KILL_SWITCH )
          {
            createCookie(side+'_sidebar', 'collapsed', 365);
          }
        }
      }
      
      /*
      window.onload = function() {
        if(typeof readCookie == 'function')
        {
          if(readCookie('left_sidebar') =='collapsed') collapseSidebar('left');
          if(readCookie('right_sidebar')=='collapsed') collapseSidebar('right');
        }
        if(typeof mdgInnerLoader == 'function')
          mdgInnerLoader();
      }
      */
      
      if ( typeof(KILL_SWITCH) != 'undefined' )
      {
        if ( !KILL_SWITCH )
        {
          var oxygenSidebarSetup = function() {
              if(typeof readCookie == 'function')
              {
                if(readCookie('left_sidebar') =='collapsed') collapseSidebar('left');
                if(readCookie('right_sidebar')=='collapsed') collapseSidebar('right');
              }
            };
          addOnloadHook(oxygenSidebarSetup);
        }
      }
      
      function ajaxRenameInline()
      {
        if ( KILL_SWITCH )
          return false;
        // This trick is _so_ vBulletin...
        elem = document.getElementById('h2PageName');
        if(!elem) return;
        elem.style.display = 'none';
        name = elem.firstChild.nodeValue;
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
        elem1 = document.getElementById('h2PageName');
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
        elem1 = document.getElementById('h2PageName');
        elem2 = document.getElementById('pageheading');
        if(!elem1 || !elem2) return;
        if ( e.target )
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
    </script>
    
  </head>
  <body>
    <table border="0" cellspacing="0" cellpadding="3" id="enano-master" width="100%">
      <tr>
      <!-- BEGIN sidebar_left -->
      <td class="mdgSidebarHolder" valign="top">
        <div id="left-sidebar">
          {SIDEBAR_LEFT}
        </div>
        <div id="left-sidebar-showbutton" style="display: none; position: fixed; top: 3px; left: 3px;">
          <input type="button" onclick="collapseSidebar('left');" value="&gt;&gt;" />
        </div>
      </td>
      <!-- END sidebar_left -->
      <td valign="top">
        <table border="0" width="100%" cellspacing="0" cellpadding="0">
      
        <tr><td id="mdg-tl"></td><td id="mdg-top"></td><td id="mdg-tr"></td></tr>
                                                                                  
        <tr><td id="mdg-l"></td><td>
        <table border="0" width="100%" id="title" cellspacing="0" cellpadding="0">
            <tr>
              <td id="mainhead"><h2><a href="{SCRIPTPATH}/{ADMIN_SID_QUES}">{SITE_NAME}</a></h2><h4>{SITE_DESC}</h4></td>
            </tr>            
          </table>
        </td><td id="mdg-r"></td></tr>
        
        <tr><td id="mdg-brl"></td><td style="background-color: #FFFFFF;"></td><td id="mdg-brr"></td></tr>
        
        <tr>
          <td id="mdg-bl"></td>
          <td class="menu_bg">
          <div class="menu_nojs" id="pagebar_main">
            <div class="label">Page tools</div>
            {TOOLBAR}
            <ul>
              {TOOLBAR_EXTRAS}
            </ul>
            <span class="menuclear"></span>
          </div>
        </td><td id="mdg-br"></td></tr>
        <tr><td id="mdg-ml"></td><td style="background-color: #FFFFFF;">
          <div class="pad"><div class="contentDiv">
          <div style="float: right;">
            <image alt=" " src="{SCRIPTPATH}/images/spacer.gif" id="ajaxloadicon" />
          </div>
          <h2 <!-- BEGIN auth_rename --> ondblclick="ajaxRenameInline();" title="Double-click to rename this page" <!-- END auth_rename --> id="h2PageName">{PAGE_NAME}</h2>
            <div id="ajaxEditContainer">
