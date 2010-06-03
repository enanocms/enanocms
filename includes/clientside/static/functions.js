// all utility functions go in here

function makeUrl(page, query, html_friendly)
{
	url = contentPath+page;
	if(url.indexOf('?') > 0) sep = '&';
	else sep = '?';
	if(query)
	{
		url = url + sep + query;
	}
	if(html_friendly)
	{
		url = url.replace('&', '&amp;');
		url = url.replace('<', '&lt;');
		url = url.replace('>', '&gt;');
	}
	return append_sid(url);
}

function makeUrlNS(namespace, page, query, html_friendly)
{
	var url = contentPath+namespace_list[namespace]+(page.replace(/ /g, '_'));
	if(url.indexOf('?') > 0) sep = '&';
	else sep = '?';
	if(query)
	{
		url = url + sep + query;
	}
	if(html_friendly)
	{
		url = url.replace('&', '&amp;');
		url = url.replace('<', '&lt;');
		url = url.replace('>', '&gt;');
	}
	return append_sid(url);
}

function strToPageID(string)
{
	// Convert Special:UploadFile to ['UploadFile', 'Special'], but convert 'Image:Enano.png' to ['Enano.png', 'File']
	for(var i in namespace_list)
	{
		if(namespace_list[i] != '')
		{
			if(namespace_list[i] == string.substr(0, namespace_list[i].length))
			{
				return [string.substr(namespace_list[i].length), i];
			}
		}
	}
	return [string, 'Article'];
}

function append_sid(url)
{
	var match = url.match(/#(.*?)$/);
	url = url.replace(/#(.*?)$/, '');
	sep = ( url.indexOf('?') > 0 ) ? '&' : '?';
	if(ENANO_SID.length > 10)
	{
		url = url + sep + 'auth=' + ENANO_SID;
		sep = '&';
	}
	if ( pagepass.length > 0 )
	{
		url = url + sep + 'pagepass=' + pagepass;
	}
	if ( match )
	{
		url = url + match[0];
	}
	return url;
}

var stdAjaxPrefix = append_sid(scriptPath+'/ajax.php?title='+title);

/**
 * Core AJAX library
 */

function ajaxMakeXHR()
{
	var ajax;
	if (window.XMLHttpRequest) {
		ajax = new XMLHttpRequest();
	} else {
		if (window.ActiveXObject) {           
			ajax = new ActiveXObject("Microsoft.XMLHTTP");
		} else {
			alert('Enano client-side runtime error: No AJAX support, unable to continue');
			return;
		}
	}
	return ajax;
}

function ajaxGet(uri, f, call_editor_safe) {
	// Is the editor open?
	if ( editor_open && !call_editor_safe )
	{
		// Make sure the user is willing to close the editor
		var conf = confirm($lang.get('editor_msg_confirm_ajax'));
		if ( !conf )
		{
			// Kill off any "loading" windows, etc. and cancel the request
			unsetAjaxLoading();
			return false;
		}
		// The user allowed the editor to be closed. Reset flags and knock out the on-close confirmation.
		editor_open = false;
		enableUnload();
		// destroy the MCE instance so it can be recreated later
		$dynano('ajaxEditArea').destroyMCE(false);
	}
	var ajax = ajaxMakeXHR();
	if ( !ajax )
	{
		console.error('ajaxMakeXHR() failed');
		return false;
	}
	ajax.onreadystatechange = function()
	{
		f(ajax);
	};
	ajax.open('GET', uri, true);
	ajax.setRequestHeader( "If-Modified-Since", "Sat, 1 Jan 2000 00:00:00 GMT" );
	ajax.send(null);
	window.ajax = ajax;
}

function ajaxPost(uri, parms, f, call_editor_safe) {
	// Is the editor open?
	if ( editor_open && !call_editor_safe )
	{
		// Make sure the user is willing to close the editor
		var conf = confirm($lang.get('editor_msg_confirm_ajax'));
		if ( !conf )
		{
			// Kill off any "loading" windows, etc. and cancel the request
			unsetAjaxLoading();
			return false;
		}
		// The user allowed the editor to be closed. Reset flags and knock out the on-close confirmation.
		editor_open = false;
		enableUnload();
		// destroy the MCE instance so it can be recreated later
		$dynano('ajaxEditArea').destroyMCE(false);
	}
	var ajax = ajaxMakeXHR();
	if ( !ajax )
	{
		console.error('ajaxMakeXHR() failed');
		return false;
	}
	ajax.onreadystatechange = function()
	{
		f(ajax);
	};
	ajax.open('POST', uri, true);
	ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	// Setting Content-length in Safari triggers a warning
	if ( !is_Safari )
	{
		ajax.setRequestHeader("Content-length", parms.length);
	}
	// fails under chrome 2.0
	// ajax.setRequestHeader("Connection", "close");
	ajax.send(parms);
	window.ajax = ajax;
}

/**
 * Show a friendly error message depicting an AJAX response that is not valid JSON
 * @param string Response text
 * @param string Custom error message. If omitted, the default will be shown.
 */

function handle_invalid_json(response, customerror)
{
	load_component(['messagebox', 'jquery', 'jquery-ui', 'fadefilter', 'flyin', 'l10n']);
	
	darken(aclDisableTransitionFX, 70, 'invalidjsondarkener');
	
	var box = document.createElement('div');
	var mainwin = document.createElement('div');
	var panel = document.createElement('div');
	
	//
	// main window
	//
	
		mainwin.style.padding = '10px';
		mainwin.style.width = '580px';
		mainwin.style.height = '360px';
		mainwin.style.clip = 'rect(0px,auto,auto,0px)';
		mainwin.style.overflow = 'auto';
		mainwin.style.backgroundColor = '#ffffff';
	
		// Title
		var h3 = document.createElement('h3');
		var h3_text = ( $lang.placeholder ) ? 'The site encountered an error while processing your request.' : $lang.get('ajax_badjson_title');
		h3.appendChild(document.createTextNode(h3_text));
		mainwin.appendChild(h3);
		
		if ( typeof(customerror) == 'string' )
		{
			var el = document.createElement('p');
			el.appendChild(document.createTextNode(customerror));
			mainwin.appendChild(el);
		}
		else
		{
			var error = 'We unexpectedly received the following response from the server. The response should have been in the JSON ';
			error += 'serialization format, but the response wasn\'t composed only of the JSON response. There are three possible triggers ';
			error += 'for this problem:';
			customerror = ( $lang.placeholder ) ? error : $lang.get('ajax_badjson_body');
			var el = document.createElement('p');
			el.appendChild(document.createTextNode(customerror));
			mainwin.appendChild(el);
			var ul = document.createElement('ul');
			var li1 = document.createElement('li');
			var li2 = document.createElement('li');
			var li3 = document.createElement('li');
			var li1_text = ( $lang.placeholder ) ? 'The server sent back a bad HTTP response code and thus sent an error page instead of running Enano. This indicates a possible problem with your server, and is not likely to be a bug with Enano.' : $lang.get('ajax_badjson_tip1');
			var li2_text = ( $lang.placeholder ) ? 'The server sent back the expected JSON response, but also injected some code into the response that should not be there. Typically this consists of advertisement code. In this case, the administrator of this site will have to contact their web host to have advertisements disabled.' : $lang.get('ajax_badjson_tip2');
			var li3_text = ( $lang.placeholder ) ? 'It\'s possible that Enano triggered a PHP error or warning. In this case, you may be looking at a bug in Enano.' : $lang.get('ajax_badjson_tip3');
			var osc_ex_data = ( $lang.placeholder ) ? 'This is KNOWN to be the case with the OpenSourceCMS.com demo version of Enano.' : $lang.get('ajax_badjson_osc');
			li1.appendChild(document.createTextNode(li1_text));
			var osc_exception = ( window.location.hostname == 'demo.opensourcecms.com' ) ? ' ' + osc_ex_data : '';
			li2.appendChild(document.createTextNode(li2_text + osc_exception));
			li3.appendChild(document.createTextNode(li3_text));
				
			ul.appendChild(li1);
			ul.appendChild(li2);
			ul.appendChild(li3);
			mainwin.appendChild(ul);
		}
		
		var p2 = document.createElement('p');
		var p2_text = ( $lang.placeholder ) ? 'The response received from the server is as follows:' : $lang.get('ajax_badjson_msg_response');
		p2.appendChild(document.createTextNode(p2_text));
		mainwin.appendChild(p2);
		
		var pre = document.createElement('pre');
		pre.appendChild(document.createTextNode(response));
		mainwin.appendChild(pre);
		
		var p3 = document.createElement('p');
		var p3_text = $lang.placeholder ? 'You may also choose to view the response as HTML.' : $lang.get('ajax_badjson_msg_viewashtml');
		p3.appendChild(document.createTextNode(p3_text + ' '));
		var a = document.createElement('a');
		var a_text = $lang.placeholder ? 'View as HTML' : $lang.get('ajax_badjson_btn_viewashtml');
		a.appendChild(document.createTextNode(a_text + '...'));
		a._resp = response;
		a.onclick = function()
		{
			var vah_title = ( $lang.placeholder ) ? 'View the response as HTML?' : $lang.get('ajax_badjson_html_confirm_title');
			var vah_body = ( $lang.placeholder ) ? 'If the server\'s response was modified by an attacker to include malicious code, viewing the response as HTML might allow that malicious code to run. Only continue if you have inspected the response text and verified that it is safe.' : $lang.get('ajax_badjson_html_confirm_body');
			var btn_confirm = $lang.placeholder ? 'View as HTML' : $lang.get('ajax_badjson_btn_viewashtml');
			var btn_cancel = $lang.placeholder ? 'Cancel' : $lang.get('etc_cancel');
			var mp = miniPromptMessage({
					title: vah_title,
					message: vah_body,
					buttons: [
						{
							text: btn_confirm,
							color: 'blue',
							style: {
								fontWeight: 'bold'
							},
							onclick: function() {
								var mp = miniPromptGetParent(this);
								var win = window.open('about:blank', 'invalidjson_htmlwin', 'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes');
								win.document.write(mp._response);
								win.document.close();
								miniPromptDestroy(this);
							}
						},
						{
							text: btn_cancel,
							onclick: function() {
								miniPromptDestroy(this);
							}
						}
					]
				});
			mp._response = this._resp;
			return false;
		}
		a.href = '#';
		p3.appendChild(a);
		mainwin.appendChild(p3);
	
	//
	// panel
	//
	
		panel.style.backgroundColor = '#D0D0D0';
		panel.style.textAlign = 'right';
		panel.style.padding = '0 10px';
		panel.style.lineHeight = '40px';
		panel.style.width = '580px';
		
		var closer = document.createElement('input');
		var btn_close = $lang.placeholder ? 'Close' : $lang.get('ajax_badjson_btn_close');
		closer.type = 'button';
		closer.value = btn_close;
		closer.onclick = function()
		{
			var parentdiv = this.parentNode.parentNode;
			if ( aclDisableTransitionFX )
			{
				parentdiv.parentNode.removeChild(parentdiv);
				enlighten(aclDisableTransitionFX, 'invalidjsondarkener');
			}
			else
			{
				$(parentdiv).hide("blind", {}, 1000, function()
					{
						parentdiv.parentNode.removeChild(parentdiv);
							enlighten(aclDisableTransitionFX, 'invalidjsondarkener');
					});
			}
		}
		panel.appendChild(closer);
		
	//
	// put it together
	//
	
		box.appendChild(mainwin);
		box.appendChild(panel);
		
		// add it to the body to allow height/width calculation
		
		box.style.display = 'block';
		box.style.position = 'absolute';
		box.style.zIndex = getHighestZ() + 1;
		domObjChangeOpac(0, box);
		
		var body = document.getElementsByTagName('body')[0];
		body.appendChild(box);
		
		
		// calculate position of the box
		// box should be exactly 640px high, 480px wide
		var top = ( getHeight() / 2 ) - ( $dynano(box).Height() / 2 ) + getScrollOffset();
		var left = ( getWidth() / 2 ) - ( $dynano(box).Width() / 2 );
		console.debug('top = %d, left = %d', top, left);
		box.style.top = top + 'px';
		box.style.left = left + 'px';
		
		// we have width and height, set display to none and reset opacity
		if ( aclDisableTransitionFX )
		{
			domObjChangeOpac(100, box);
			box.style.display = 'block';
		}
		else
		{
			box.style.display = 'none';
			domObjChangeOpac(100, box);
			
			setTimeout(function()
				{
					$(box).show("blind", {}, 1000);
				}, 1000);
		}
	return false;
}

/**
 * Verify that a string is roughly a valid JSON object. Warning - this is only a very cheap syntax check.
 * @param string
 * @return bool true if JSON is valid
 */

function check_json_response(response)
{
	response = trim(response);
	if ( response.substr(0, 1) == '{' && response.substr(response.length - 1, 1) == '}' )
	{
		return true;
	}
	return false;
}

function ajaxEscape(text)
{
	/*
	text = escape(text);
	text = text.replace(/\+/g, '%2B', text);
	*/
	text = window.encodeURIComponent(text);
	return text;
}

/**
 * String functions
 */

// Equivalent to PHP trim() function
function trim(text)
{
	text = text.replace(/^([\s]+)/, '');
	text = text.replace(/([\s]+)$/, '');
	return text;
}

// Equivalent to PHP implode() function
function implode(chr, arr)
{
	if ( typeof ( arr.toJSONString ) == 'function' )
		delete(arr.toJSONString);
	
	var ret = '';
	var c = 0;
	for ( var i in arr )
	{
		if(i=='toJSONString')continue;
		if ( c > 0 )
			ret += chr;
		ret += arr[i];
		c++;
	}
	return ret;
}

function form_fetch_field(form, name)
{
	var fields = form.getElementsByTagName('input');
	if ( fields.length < 1 )
		return false;
	for ( var i = 0; i < fields.length; i++ )
	{
		var field = fields[i];
		if ( field.name == name )
			return field;
	}
	return false;
}

function get_parent_form(o)
{
	if ( !o.parentNode )
		return false;
	if ( o.tagName == 'FORM' )
		return o;
	var p = o.parentNode;
	while(true)
	{
		if ( p.tagName == 'FORM' )
			return p;
		else if ( !p )
			return false;
		else
			p = p.parentNode;
	}
}

/**
 * Return a DOMElement that uses a sprite image.
 * @param string Path to sprite image
 * @param int Width of resulting image
 * @param int Height of resulting image
 * @param int X offset
 * @param int Y offset
 * @return object HTMLImageElement
 */

function gen_sprite(path, width, height, xpos, ypos)
{
	var image = document.createElement('img');
	image.src = cdnPath + '/images/spacer.gif';
	image.width = String(width);
	image.height = String(height);
	image.style.backgroundImage = 'url(' + path + ')';
	image.style.backgroundRepeat = 'no-repeat';
	xpos = ( xpos == 0 ) ? '0' : '-' + String(xpos);
	ypos = ( ypos == 0 ) ? '0' : '-' + String(ypos);
	image.style.backgroundPosition = ypos + 'px ' + xpos + 'px';
	
	return image;
}

/**
 * The same as gen_sprite but generates HTML instead of a DOMElement.
 * @param string Path to sprite image
 * @param int Width of resulting image
 * @param int Height of resulting image
 * @param int X offset
 * @param int Y offset
 * @return object HTMLImageElement
 */

function gen_sprite_html(path, width, height, xpos, ypos)
{
	var html = '<img src="' + scriptPath + '/images/spacer.gif" width="' + width + '" height="' + height + '" ';
	xpos = ( xpos == 0 ) ? '0' : '-' + String(xpos);
	ypos = ( ypos == 0 ) ? '0' : '-' + String(ypos);
	html += 'style="background-image: url(' + path + '); background-repeat: no-repeat; background-position: ' + ypos + 'px ' + xpos + 'px;"';
	html += ' />';
	
	return html;
}

function findParentForm(o)
{
	return get_parent_form(o);
}

function domObjChangeOpac(opacity, id)
{
	if ( !id )
		return false;
	
	var object = id.style;
	object.opacity = (opacity / 100);
	object.MozOpacity = (opacity / 100);
	object.KhtmlOpacity = (opacity / 100);
	object.filter = "alpha(opacity=" + opacity + ")";
}

function getScrollOffset(el)
{
	var position;
	var s = el || self;
	el = el || document;
	if ( el.scrollTop )
	{
		position = el.scrollTop;
	}
	else if (s.pageYOffset)
	{
		position = self.pageYOffset;
	}
	else if (document.documentElement && document.documentElement.scrollTop)
	{
		position = document.documentElement.scrollTop;
	}
	else if (document.body)
	{
		position = document.body.scrollTop;
	}
	return position;
}

function setScrollOffset(offset)
{
	window.scroll(0, offset);
}

// Function to fade classes info-box, warning-box, error-box, etc.

function fadeInfoBoxes()
{
	var divs = new Array();
	d = document.getElementsByTagName('div');
	j = 0;
	for(var i in d)
	{
		if ( !d[i] )
			continue;
		if ( !d[i].tagName )
			continue;
		if(d[i].className=='info-box' || d[i].className=='error-box' || d[i].className=='warning-box' || d[i].className=='question-box')
		{
			divs[j] = d[i];
			j++;
		}
	}
	if(divs.length < 1) return;
	load_component('fat');
	for(i in divs)
	{
		if(!divs[i].id) divs[i].id = 'autofade_'+Math.floor(Math.random() * 100000);
		switch(divs[i].className)
		{
			case 'info-box':
			default:
				from = '#3333FF';
				break;
			case 'error-box':
				from = '#FF3333';
				break;
			case 'warning-box':
				from = '#FFFF33';
				break;
			case 'question-box':
				from = '#33FF33';
				break;
		}
		Fat.fade_element(divs[i].id,30,2000,from,Fat.get_bgcolor(divs[i].id));
	}
}

addOnloadHook(fadeInfoBoxes);

// Alpha fades

function opacity(id, opacStart, opacEnd, millisec)
{
		var object = document.getElementById(id);
		domOpacity(object, opacStart, opacEnd, millisec);
}

var opacityDOMCache = {};
function domOpacity(obj, opacStart, opacEnd, millisec) {
		//speed for each frame
		var speed = Math.round(millisec / 100);
		var timer = 0;
		
		// unique ID for this animation
		var uniqid = Math.floor(Math.random() * 1000000);
		opacityDOMCache[uniqid] = obj;

		//determine the direction for the blending, if start and end are the same nothing happens
		if(opacStart > opacEnd) {
				for(i = opacStart; i >= opacEnd; i--) {
						setTimeout("if ( opacityDOMCache["+uniqid+"] ) { var obj = opacityDOMCache["+uniqid+"]; domObjChangeOpac(" + i + ",obj) }",(timer * speed));
						timer++;
				}
		} else if(opacStart < opacEnd) {
				for(i = opacStart; i <= opacEnd; i++)
						{
						setTimeout("if ( opacityDOMCache["+uniqid+"] ) { var obj = opacityDOMCache["+uniqid+"]; domObjChangeOpac(" + i + ",obj); }",(timer * speed));
						timer++;
				}
		}
		setTimeout("delete(opacityDOMCache["+uniqid+"]);",(timer * speed));
}

function abortFades()
{
	opacityDOMCache = {};
}

// change the opacity for different browsers
function changeOpac(opacity, id)
{
	var object = document.getElementById(id);
	return domObjChangeOpac(opacity, object);
}

// draw a white ajax-ey "loading" box over an object
function whiteOutElement(el)
{
	var top = $dynano(el).Top();
	var left = $dynano(el).Left();
	var width = $dynano(el).Width();
	var height = $dynano(el).Height();
	
	var blackout = document.createElement('div');
	// using fixed here allows modal windows to be blacked out
	blackout.style.position = ( el.style.position == 'fixed' ) ? 'fixed' : 'absolute';
	blackout.style.top = top + 'px';
	blackout.style.left = left + 'px';
	blackout.style.width = width + 'px';
	blackout.style.height = height + 'px';
	
	blackout.style.backgroundColor = '#FFFFFF';
	domObjChangeOpac(60, blackout);
	var background = ( $dynano(el).Height() < 48 ) ? 'url(' + scriptPath + '/images/loading.gif)' : 'url(' + scriptPath + '/includes/clientside/tinymce/themes/advanced/skins/default/img/progress.gif)';
	blackout.style.backgroundImage = background;
	blackout.style.backgroundPosition = 'center center';
	blackout.style.backgroundRepeat = 'no-repeat';
	blackout.style.zIndex = getHighestZ() + 2;
	
	var body = document.getElementsByTagName('body')[0];
	body.appendChild(blackout);
	
	return blackout;
}

/**
 * Take a div generated by whiteOutElement() and report success using the glossy "check" graphic. Sets the image, then
 * briefly fades in, then fades out and destroys the box so as to re-allow control over the underlying element
 */

function whiteOutReportSuccess(whitey, nodestroy_mp)
{
	whiteOutDestroyWithImage(whitey, cdnPath + '/images/check.png', nodestroy_mp);
}

function whiteOutReportFailure(whitey, nodestroy_mp)
{
	if ( typeof(nodestroy_mp) == undefined )
		nodestroy_mp = true;
		
	whiteOutDestroyWithImage(whitey, cdnPath + '/images/checkbad.png', nodestroy_mp);
}

function whiteOutDestroyWithImage(whitey, image, nodestroy_mp)
{
	// fade the status indicator in and then out
	whitey.style.backgroundImage = 'url(' + image + ')';
	if ( whitey.isMiniPrompt && !nodestroy_mp )
	{
		setTimeout(function()
			{
				whiteOutDestroyOnMiniPrompt(whitey);
			}, 500);
		return true;
	}
	if ( aclDisableTransitionFX )
	{
		domObjChangeOpac(80, whitey);
	}
	else
	{
		domOpacity(whitey, 60, 80, 500);
		setTimeout(function()
			{
				domOpacity(whitey, 60, 0, 500);
			}, 750);
	}
	setTimeout(function()
		{
			if ( whitey )
				if ( whitey.parentNode )
					whitey.parentNode.removeChild(whitey);
		}, 1250);
}

/**
 * Whites out a form and disables all buttons under it. Useful for onsubmit functions.
 * @example
 <code>
 <form action="foo" onsubmit="whiteOutForm(this);">
 </code>
 * @param object Form object
 * @return object Whiteout div
 */

function whiteOutForm(form)
{
	if ( !form.getElementsByTagName )
		return false;
	
	// disable all buttons
	var buttons = form.getElementsByTagName('input');
	for ( var i = 0; i < buttons.length; i++ )
	{
		if ( buttons[i].type == 'button' || buttons[i].type == 'submit' || buttons[i].type == 'image' )
		{
			buttons[i].disabled = 'disabled';
			// ... but also make a hidden element to preserve any flags
			var clone = buttons[i].cloneNode(true);
			clone.type = 'hidden';
			clone.disabled = false;
			console.debug(clone);
			form.appendChild(clone);
		}
	}
	var buttons = form.getElementsByTagName('button');
	for ( var i = 0; i < buttons.length; i++ )
	{
		buttons[i].disabled = 'disabled';
		// ... but also make a hidden element to preserve any flags
		if ( buttons[i].name )
		{
			var clone = document.createElement('input');
			clone.type = 'hidden';
			clone.name = buttons[i].name;
			clone.value = ( buttons[i].value ) ? buttons[i].value : '';
			form.appendChild(clone);
		}
	}
	
	return whiteOutElement(form);
}

// other DHTML functions

function fetch_offset(obj)
{
	var left_offset = obj.offsetLeft;
	var top_offset = obj.offsetTop;
	while ((obj = obj.offsetParent) != null) {
		left_offset += obj.offsetLeft;
		top_offset += obj.offsetTop;
	}
	return { 'left' : left_offset, 'top' : top_offset };
}

function fetch_dimensions(o) {
	var w = o.offsetWidth;
	var h = o.offsetHeight;
	return { 'w' : w, 'h' : h };
}

function findParentForm(o)
{
	if ( o.tagName == 'FORM' )
		return o;
	while(true)
	{
		o = o.parentNode;
		if ( !o )
			return false;
		if ( o.tagName == 'FORM' )
			return o;
	}
	return false;
}

function bannerOn(text)
{
	darken(true);
	var thediv = document.createElement('div');
	thediv.className = 'mdg-comment';
	thediv.style.padding = '0';
	thediv.style.marginLeft = '0';
	thediv.style.position = 'absolute';
	thediv.style.display = 'none';
	thediv.style.padding = '4px';
	thediv.style.fontSize = '14pt';
	thediv.id = 'mdgDynamic_bannerDiv_'+Math.floor(Math.random() * 1000000);
	thediv.innerHTML = text;
	
	var body = document.getElementsByTagName('body');
	body = body[0];
	body.appendChild(thediv);
	body.style.cursor = 'wait';
	
	thediv.style.display = 'block';
	dim = fetch_dimensions(thediv);
	thediv.style.display = 'none';
	bdim = { 'w' : getWidth(), 'h' : getHeight() };
	so = getScrollOffset();
	
	var left = (bdim['w'] / 2) - ( dim['w'] / 2 );
	
	var top  = (bdim['h'] / 2);
	top  = top - ( dim['h'] / 2 );
	
	top = top + so;
	
	thediv.style.top  = top  + 'px';
	thediv.style.left = left + 'px';
	
	thediv.style.display = 'block';
	
	return thediv.id;
}

function bannerOff(id)
{
	e = document.getElementById(id);
	if(!e) return;
	e.innerHTML = '';
	e.style.display = 'none';
	var body = document.getElementsByTagName('body');
	body = body[0];
	body.style.cursor = 'default';
	enlighten(true);
}

function disableUnload(message)
{
	if(typeof message != 'string') message = 'You may want to save your changes first.';
	window._unloadmsg = message;
	window.onbeforeunload = function(e)
	{
		if ( !e )
			e = window.event;
		e.returnValue = window._unloadmsg;
	}
}

function enableUnload()
{
	window._unloadmsg = null;
	window.onbeforeunload = null;
}

/**
 * Gets the highest z-index of all divs in the document
 * @return integer
 */
function getHighestZ()
{
	z = 0;
	var divs = document.getElementsByTagName('div');
	for(var i = 0; i < divs.length; i++)
	{
		if ( divs[i].style.zIndex > z && divs[i].style.display != 'none' )
			z = divs[i].style.zIndex;
	}
	return parseInt(z);
}

var shift = false;
function isKeyPressed(event)
{
	if (event.shiftKey==1)
	{
		shift = true;
	}
	else
	{
		shift = false;
	}
}

function moveDiv(div, newparent)
{
	var backup = div;
	var oldparent = div.parentNode;
	oldparent.removeChild(div);
	newparent.appendChild(backup);
}

var busyBannerID;
function goBusy(msg)
{
	if(!msg) msg = 'Please wait...';
	body = document.getElementsByTagName('body');
	body = body[0];
	body.style.cursor = 'wait';
	busyBannerID = bannerOn(msg);
}

function unBusy()
{
	body = document.getElementsByTagName('body');
	body = body[0];
	body.style.cursor = 'default';
	bannerOff(busyBannerID);
}

function setAjaxLoading()
{
	if ( document.getElementById('ajaxloadicon') )
	{
		document.getElementById('ajaxloadicon').src=ajax_load_icon;
	}
}

function unsetAjaxLoading()
{
	if ( document.getElementById('ajaxloadicon') )
	{
		document.getElementById('ajaxloadicon').src=cdnPath + '/images/spacer.gif';
	}
}

function readCookie(name) {var nameEQ = name + "=";var ca = document.cookie.split(';');for(var i=0;i < ca.length;i++){var c = ca[i];while (c.charAt(0)==' ') c = c.substring(1,c.length);if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);}return null;}
function createCookie(name,value,days){if (days){var date = new Date();date.setTime(date.getTime()+(days*24*60*60*1000));var expires = "; expires="+date.toGMTString();}else var expires = "";document.cookie = name+"="+value+expires+"; path=/";}
function eraseCookie(name) {createCookie(name,"",-1);}

/*
 * AJAX login box (experimental)
 * Moved / rewritten in login.js
 */

// Included only for API-compatibility
function ajaxPromptAdminAuth(call_on_ok, level)
{
	ajaxLoginInit(call_on_ok, level);
}

/**
 * Insert a DOM object _after_ the specified child.
 * @param object Parent node
 * @param object Node to insert
 * @param object Node to insert after
 */

function insertAfter(parent, baby, bigsister)
{
	try
	{
		if ( parent.childNodes[parent.childNodes.length-1] == bigsister )
			parent.appendChild(baby);
		else
			parent.insertBefore(baby, bigsister.nextSibling);
	}
	catch(e)
	{
		alert(e.toString());
		if ( window.console )
		{
			// Firebug support
			window.console.warn(e);
		}
	}
}

/**
 * Validates an e-mail address.
 * @param string E-mail address
 * @return bool
 */

function validateEmail(email)
{
	return ( email.match(/^(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*|(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*(?:(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[^()<>@,;:".\\\[\]\x80-\xff\000-\010\012-\037]*)*<[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*(?:,[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*)*:[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)?(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|"[^\\\x80-\xff\n\015"]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015"]*)*")[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*@[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:\.[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*(?:[^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff]+(?![^(\040)<>@,;:".\\\[\]\000-\037\x80-\xff])|\[(?:[^\\\x80-\xff\n\015\[\]]|\\[^\x80-\xff])*\])[\040\t]*(?:\([^\\\x80-\xff\n\015()]*(?:(?:\\[^\x80-\xff]|\([^\\\x80-\xff\n\015()]*(?:\\[^\x80-\xff][^\\\x80-\xff\n\015()]*)*\))[^\\\x80-\xff\n\015()]*)*\)[\040\t]*)*)*>)$/) ) ? true : false;
}

/**
 * Validates a username.
 * @param string Username to test
 * @return bool
 */

function validateUsername(username)
{
	var regex = new RegExp('^[^<>&\?\'"%\n\r/]+$', '');
	return ( username.match(regex) ) ? true : false;
}

/*
 * Utility functions, moved from windows.js
 */

function getHeight() {
	var myHeight = 0;
	if( typeof( window.innerWidth ) == 'number' ) {
		myHeight = window.innerHeight;
	} else if( document.documentElement &&
			( document.documentElement.clientWidth || document.documentElement.clientHeight ) ) {
		myHeight = document.documentElement.clientHeight;
	} else if( document.body && ( document.body.clientWidth || document.body.clientHeight ) ) {
		myHeight = document.body.clientHeight;
	}
	return myHeight;
}

function getWidth() {
	var myWidth = 0;
	if( typeof( window.innerWidth ) == 'number' ) {
		myWidth = window.innerWidth;
	} else if( document.documentElement &&
			( document.documentElement.clientWidth || document.documentElement.clientWidth ) ) {
		myWidth = document.documentElement.clientWidth;
	} else if( document.body && ( document.body.clientWidth || document.body.clientWidth ) ) {
		myWidth = document.body.clientWidth;
	}
	return myWidth;
}

/**
 * Sanitizes a page URL string so that it can safely be stored in the database.
 * @param string Page ID to sanitize
 * @return string Cleaned text
 */

function sanitize_page_id(page_id)
{
	// Remove character escapes
	page_id = dirtify_page_id(page_id);

	var regex = new RegExp('[A-Za-z0-9\\[\\]\./:;\(\)@_-]', 'g');
	pid_clean = page_id.replace(regex, 'X');
	var pid_dirty = [];
	for ( var i = 0; i < pid_clean.length; i++ )
		pid_dirty[i] = pid_clean.substr(i, 1);

	for ( var i = 0; i < pid_dirty.length; i++ )
	{
		var chr = pid_dirty[i];
		if ( chr == 'X' )
			continue;
		var cid = chr.charCodeAt(0);
		cid = cid.toString(16).toUpperCase();
		if ( cid.length < 2 )
		{
			cid = '0' + cid;
		}
		pid_dirty[i] = "." + cid;
	}
	
	var pid_chars = [];
	for ( var i = 0; i < page_id.length; i++ )
		pid_chars[i] = page_id.substr(i, 1);
	
	var page_id_cleaned = '';

	for ( var id in pid_chars )
	{
		var chr = pid_chars[id];
		if ( pid_dirty[id] == 'X' )
			page_id_cleaned += chr;
		else
			page_id_cleaned += pid_dirty[id];
	}
	
	return page_id_cleaned;
}

/**
 * Removes character escapes in a page ID string
 * @param string Page ID string to dirty up
 * @return string
 */

function dirtify_page_id(page_id)
{
	// First, replace spaces with underscores
	page_id = page_id.replace(/ /g, '_');

	var matches = page_id.match(/\.[A-Fa-f0-9][A-Fa-f0-9]/g);
	
	if ( matches != null )
	{
		for ( var i = 0; i < matches.length; i++ )
		{
			var match = matches[i];
			var byt = (match.substr(1)).toUpperCase();
			var code = eval("0x" + byt);
			var regex = new RegExp('\\.' + byt, 'g');
			page_id = page_id.replace(regex, String.fromCharCode(code));
		}
	}
	
	return page_id;
}

/*
		the getElementsByClassName function I pilfered from this guy.  It's
		a useful function that'll return any/all tags with a specific css class.

		Written by Jonathan Snook, http://www.snook.ca/jonathan
		Add-ons by Robert Nyman, http://www.robertnyman.com
		
		Modified to match all elements that match the class name plus an integer after the name
		This is used in Enano to allow sliding sidebar widgets that use their own CSS
*/
function getElementsByClassName(oElm, strTagName, strClassName)
{
		// first it gets all of the specified tags
		var arrElements = (strTagName == "*" && document.all) ? document.all : oElm.getElementsByTagName(strTagName);
		
		// then it sets up an array that'll hold the results
		var arrReturnElements = new Array();

		// some regex stuff you don't need to worry about
		strClassName = strClassName.replace(/\-/g, "\\-");

		var oRegExp = new RegExp("(^|\\s)" + strClassName + "([0-9]*)(\\s|$)");
		var oElement;
		
		// now it iterates through the elements it grabbed above
		for(var i=0; i<arrElements.length; i++)
		{
				oElement = arrElements[i];

				// if the class matches what we're looking for it ads to the results array
				if(oElement.className.match(oRegExp))
				{
						arrReturnElements.push(oElement);
				}
		}

		// then it kicks the results back to us
		return (arrReturnElements)
}

/**
 * Equivalent to PHP's in_array function.
 */

function in_array(needle, haystack)
{
	for(var i in haystack)
	{
		if(haystack[i] == needle) return i;
	}
	return false;
}

/**
 * Equivalent of PHP's time()
 * @return int
 */

function unix_time()
{
	return parseInt((new Date()).getTime()/1000);
}
