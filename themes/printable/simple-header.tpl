<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
	<head>
		<title>{PAGE_NAME} &bull; {SITE_NAME}</title>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<link rel="stylesheet" href="{SCRIPTPATH}/themes/{THEME_ID}/css-simple/{STYLE_ID}.css" type="text/css" id="mdgCss" />
		{JS_DYNAMIC_VARS}
		<!-- This script automatically loads the other 15 JS files -->
		<script type="text/javascript" src="{SCRIPTPATH}/includes/clientside/static/enano-lib-basic.js"></script>
		{ADDITIONAL_HEADERS}
	</head>
	<body>
		<table border="0" style="width: 100%; height: 100%;">
		<tr>
		<td style="width: 10%;"></td>
		<td valign="middle">
			<table id="enano-main" border="0" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
				<tr>
					<td id="head-up-left"></td>
					<td id="head-up"></td>
					<td id="head-up-right"></td>
				</tr>
				<tr>
					<td id="head-left"></td>
					<td id="head-main">
						<h1>{PAGE_NAME}</h1>
					</td>
					<td id="head-right"></td>
				</tr>
				<tr>
					<td id="main-left"></td>
					<td id="main-main">
						<div id="ajaxEditContainer">
