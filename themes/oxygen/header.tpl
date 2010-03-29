<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>{PAGE_NAME} &bull; {SITE_NAME}</title>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<link rel="stylesheet" type="text/css" href="{CDNPATH}/includes/clientside/css/enano-shared.css?{ENANO_VERSION}" />
		<!-- BEGIN msie -->
		<link rel="stylesheet" type="text/css" href="{CDNPATH}/includes/clientside/css/enano-shared-ie.css?{ENANO_VERSION}" />
		<!-- END msie -->
		<link id="mdgCss" rel="stylesheet" href="{CDNPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css?{ENANO_VERSION}" type="text/css" />
		{JS_DYNAMIC_VARS}
		{JS_HEADER}
		
		<script type="text/javascript">
			var tinymce_skin = 'o2k7';
		</script>
		
		{ADDITIONAL_HEADERS}
		
		<script type="text/javascript">
		// <![CDATA[
		
			function collapseSidebar(side)
			{
				elem = document.getElementById(side+'-sidebar');
				if(!elem) return;
				counter = document.getElementById(side+'-sidebar-showbutton');
				if(elem.style.display=='none')
				{
					elem.style.display = 'block';
					counter.style.display = 'none';
					elem.parentNode.style.width = '';
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
				if ( KILL_SWITCH || IE )
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
				textbox.oldname = name;
				elem.parentNode.insertBefore(textbox, elem);
				document.onclick = ajaxRenameInlineCancel;
				
				load_component(['l10n', 'fadefilter', 'messagebox']);
				textbox.focus();
				textbox.select();
			}
			function ajaxRenameInlineSave()
			{
				elem1 = document.getElementById('h2PageName');
				elem2 = document.getElementById('pageheading');
				if(!elem1 || !elem2) return;
				value = elem2.value;
				elem2.parentNode.removeChild(elem2); // just destroy the thing
				elem1.removeChild(elem1.firstChild);
				elem1.appendChild(document.createTextNode(value));
				elem1.style.display = 'block';
				if(!value || value=='' || value==elem2.oldname) return;
				setAjaxLoading();
				ajaxPost(stdAjaxPrefix+'&_mode=rename', 'newtitle='+ajaxEscape(value), function() {
					if ( ajax.readyState == 4 )
					{
						unsetAjaxLoading();
						var response = String(ajax.responseText);
						if ( !check_json_response(response) )
						{
							handle_invalid_json(response);
							return false;
						}
						response = parseJSON(response);
						if ( response.success )
						{
							new MessageBox( MB_OK|MB_ICONINFORMATION, $lang.get('ajax_rename_success_title'), $lang.get('ajax_rename_success_body', { page_name_new: value }) );
						}
						else
						{
							alert(response.error);
						}
					}
				});
			}
			function ajaxRenameInlineCancel(e)
			{
				if ( typeof(e) != 'object' && IE )
					e = window.event;
				elem1 = document.getElementById('h2PageName');
				elem2 = document.getElementById('pageheading');
				if(!elem1 || !elem2) return;
				if ( typeof(e) == 'object' && e.target )
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
							<td id="mainhead">
								<h2><a href="{SCRIPTPATH}/{ADMIN_SID_QUES}">{SITE_NAME}</a></h2>
								<h4>{SITE_DESC}</h4>
							</td>
						</tr>            
					</table>
				</td><td id="mdg-r"></td></tr>
				
				<tr><td id="mdg-brl"></td><td style="background-color: #FFFFFF;"></td><td id="mdg-brr"></td></tr>
				
				<tr>
					<td id="mdg-bl"></td>
					<td class="menu_bg">
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
				</td><td id="mdg-br"></td></tr>
				<tr><td id="mdg-ml"></td><td style="background-color: #FFFFFF;">
					<div class="pad"><div class="contentDiv">
					<div style="float: right;">
						<img alt=" " src="{CDNPATH}/images/spacer.gif" id="ajaxloadicon" />
					</div>
					<h1 <!-- BEGIN auth_rename --> ondblclick="ajaxRenameInline();" title="{lang:onpage_btn_rename_inline}" <!-- END auth_rename --> id="h2PageName">{PAGE_NAME}</h1>
						<div id="ajaxEditContainer">
