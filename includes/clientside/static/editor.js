// Javascript routines for the page editor

// Idle time required for autosave, in seconds
var AUTOSAVE_TIMEOUT = 15;
var AutosaveTimeoutObj = null;
var editor_img_path = cdnPath + '/images/editor';
var editor_save_lock = false;
var editor_wikitext_transform_enable = true;
var editor_orig_text = '';
var editor_last_draft = '';
var page_format = 'wikitext';

window.ajaxEditor = function(revid)
{
	if ( KILL_SWITCH )
		return true;
	if ( editor_open )
		return true;
	load_component(['l10n', 'template-compiler', 'messagebox', 'fadefilter', 'flyin', 'toolbar']);
	selectButtonMinor('edit');
	selectButtonMajor('article');
	setAjaxLoading();
	
	var rev_id_uri = ( revid ) ? '&revid=' + revid : '';
	ajaxGet(stdAjaxPrefix + '&_mode=getsource' + rev_id_uri, function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				unsetAjaxLoading();
				
				var response = String(ajax.responseText + '');
				if ( !check_json_response(response) )
				{
					handle_invalid_json(response);
					return false;
				}
				
				response = parseJSON(response);
				if ( response.mode == 'error' )
				{
					unselectAllButtonsMinor();
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_server'), response.error);
					return false;
				}
				
				if ( !response.auth_view_source )
				{
					unselectAllButtonsMinor();
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_access_denied_title'), $lang.get('editor_err_access_denied_body'));
					return false;
				}
				
				// do we need to enter a captcha before saving the page?
				var captcha_hash = ( response.require_captcha ) ? response.captcha_id : false;
				
				ajaxBuildEditor((!response.auth_edit), response.time, response.allow_wysiwyg, captcha_hash, response.revid, response.undo_info, response);
			}
		});
}

window.ajaxBuildEditor = function(readonly, timestamp, allow_wysiwyg, captcha_hash, revid, undo_info, response)
{
	try {
	// Set flags
	// We don't want the fancy confirmation framework to trigger if the user is only viewing the page source
	if ( !readonly )
	{
		editor_open = true;
		disableUnload();
	}
	
	// Destroy existing contents of page container
	if ( editor_use_modal_window )
	{
		darken(true, 70, 'enano_editor_darkener');
		// Build a div with 80% width, centered, and 10px from the top of the window
		var edcon = document.createElement('div');
		edcon.style.position = 'absolute';
		edcon.style.backgroundColor = '#FFFFFF';
		edcon.style.padding = '10px';
		edcon.style.width = '80%';
		edcon.style.zIndex = getHighestZ() + 1;
		edcon.id = 'ajaxEditContainerModal';
		
		// Positioning
		var top = getScrollOffset() + 10;
		var left = ( getWidth() / 10 ) - 10; // 10% of window width on either side - 10px for padding = perfect centering effect
		edcon.style.top = String(top) + 'px';
		edcon.style.left = String(left) + 'px';
		var body = document.getElementsByTagName('body')[0];
		
		// Set opacity to 0
		domObjChangeOpac(0, edcon);
		body.appendChild(edcon);
	}
	else
	{
		var edcon = document.getElementById('ajaxEditContainer');
		for ( var i = edcon.childNodes.length - 1; i >= 0; i-- )
		{
			edcon.removeChild(edcon.childNodes[i]);
		}
	}
	
	var content = response.src;
	
	//
	// BUILD EDITOR
	//
	
	var heading = document.createElement('h3');
	heading.style.cssFloat = 'left';
	heading.style.styleFloat = 'left';
	heading.style.marginTop = '0px';
	heading.style.marginBottom = '0px';
	heading.appendChild(document.createTextNode($lang.get('editor_msg_editor_heading')));
	
	// Plaintext/wikitext toggler
	// Only build the editor if using TinyMCE is allowed. THIS IS WEAK
	// AND CANNOT BE MADE ANY STRONGER.
	
	if ( allow_wysiwyg )
	{
		var toggler = document.createElement('p');
		toggler.style.marginLeft = '0';
		toggler.style.textAlign = 'right';
		
		var span_wiki = document.createElement('span');
		var span_mce  = document.createElement('span');
		span_wiki.id  = 'enano_edit_btn_pt';
		span_mce.id   = 'enano_edit_btn_mce';
		
		// to-wikitext button
		var a = document.createElement('a');
		a.href = '#';
		a.className = 'abutton image abutton_green';
		a.appendChild(gen_sprite(scriptPath + '/images/editor/sprite.png', 16, 16, 0, 96));
		a.appendChild(document.createTextNode(' ' + $lang.get('editor_btn_wikitext')));
		span_wiki.appendChild(a);
		toggler.appendChild(span_wiki);
		
		// to-HTML button
		var a = document.createElement('a');
		a.href = '#';
		a.className = 'abutton image abutton_blue';
		a.appendChild(gen_sprite(scriptPath + '/images/editor/sprite.png', 16, 16, 0, 112));
		a.appendChild(document.createTextNode(' ' + $lang.get('editor_btn_graphical')));
		span_mce.appendChild(a);
		toggler.appendChild(span_mce);
		
		if ( response.page_format == 'wikitext' )
		{
			// Current selection is a custom editor plugin - make span_wiki have the link and span_mce be plaintext
			span_wiki.style.display = 'none';
		}
		else
		{
			// Current selection is wikitext - set span_wiki to plaintext and span_mce to link
			span_mce.style.display = 'none';
		}
	}
	
	// Form (to allow submits from MCE to trigger a real save)
	var form = document.createElement('form');
	form.action = 'javascript:void(0);';
	form.onsubmit = function()
	{
		ajaxEditorSave();
		return false;
	}
	
	// Draft notice
	if ( response.have_draft && !readonly )
	{
		var dn = document.createElement('div');
		dn.className = 'warning-box';
		dn.id = 'ajax_edit_draft_notice';
		dn.innerHTML = '<b>' + $lang.get('editor_msg_have_draft_title') + '</b><br />';
		dn.innerHTML += $lang.get('editor_msg_have_draft_body', { author: response.draft_author, time: response.draft_time });
	}
	
	// Old-revision notice
	if ( revid > 0 )
	{
		var oldrev_box = document.createElement('div');
		oldrev_box.className = 'usermessage';
		oldrev_box.appendChild(document.createTextNode($lang.get('editor_msg_editing_old_revision')));
	}
	
	// Preview holder
	var preview_anchor = document.createElement('a');
	preview_anchor.name = 'ajax_preview';
	preview_anchor.id = 'ajax_preview';
	var preview_container = document.createElement('div');
	preview_container.id = 'enano_editor_preview';
	preview_container.style.clear = 'left';
	
	// Textarea containing the content
	var ta_wrapper = document.createElement('div');
	ta_wrapper.style.margin = '10px 0';
	
	// ta_wrapper.style.clear = 'both';
	var textarea = document.createElement('textarea');
	textarea.id = 'ajaxEditArea';
	
	// A hook allowing plugins to create a toolbar on top of the textarea
	eval(setHook('editor_gui_toolbar'));
	
	ta_wrapper.appendChild(textarea);
	
	textarea.rows = '20';
	textarea.cols = '60';
	textarea.style.width = '98.7%';
	
	// Revision metadata controls
	var tblholder = document.createElement('div');
	tblholder.className = 'tblholder';
	var metatable = document.createElement('table');
	metatable.setAttribute('border', '0');
	metatable.setAttribute('cellspacing', '1');
	metatable.setAttribute('cellpadding', '4');
	
	if ( readonly )
	{
		// Close Viewer button
		var toolbar = '';
		var head = new templateParser(response.toolbar_templates.toolbar_start);
		var button = new templateParser(response.toolbar_templates.toolbar_button);
		var tail = new templateParser(response.toolbar_templates.toolbar_end);
		
		toolbar += head.run();
		
		button.assign_bool({
				show_title: true
			});
		
		// Button: close
		button.assign_vars({
				TITLE: $lang.get('editor_btn_closeviewer'),
				IMAGE: editor_img_path + '/discard.gif',
				SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 16),
				FLAGS: 'href="#" onclick="ajaxReset(true); return false;"'
			});
		toolbar += button.run();
		toolbar += tail.run();
	}
	else
	{
		// First row: edit summary
		var tr1 = document.createElement('tr');
		var td1_1 = document.createElement('td');
		var td1_2 = document.createElement('td');
		td1_1.className = 'row2';
		td1_2.className = 'row1';
		td1_2.style.width = '70%';
		td1_1.appendChild(document.createTextNode($lang.get('editor_lbl_edit_summary')));
		td1_1.appendChild(document.createElement('br'));
		var small = document.createElement('small');
		small.appendChild(document.createTextNode($lang.get('editor_lbl_edit_summary_explain')));
		td1_1.appendChild(small);
		
		var field_es = document.createElement('input');
		field_es.id = 'enano_editor_field_summary';
		field_es.type = 'text';
		field_es.size = '40';
		field_es.style.width = '96%';
		
		if ( revid > 0 )
		{
			undo_info.last_rev_id = revid;
			field_es.value = $lang.get('editor_reversion_edit_summary', undo_info);
		}
		
		td1_2.appendChild(field_es);
		
		tr1.appendChild(td1_1);
		tr1.appendChild(td1_2);
		
		// Second row: minor edit
		var tr2 = document.createElement('tr');
		var td2_1 = document.createElement('td');
		var td2_2 = document.createElement('td');
		td2_1.className = 'row2';
		td2_2.className = 'row1';
		td2_1.appendChild(document.createTextNode($lang.get('editor_lbl_minor_edit')));
		td2_1.appendChild(document.createElement('br'));
		var small = document.createElement('small');
		small.appendChild(document.createTextNode($lang.get('editor_lbl_minor_edit_explain')));
		td2_1.appendChild(small);
		
		var label = document.createElement('label');
		var field_mi = document.createElement('input');
		field_mi.id = 'enano_editor_field_minor';
		field_mi.type = 'checkbox';
		label.appendChild(field_mi);
		label.appendChild(document.createTextNode(' '));
		label.appendChild(document.createTextNode($lang.get('editor_lbl_minor_edit_field')));
		td2_2.appendChild(label);
		
		tr2.appendChild(td2_1);
		tr2.appendChild(td2_2);
		
		if ( captcha_hash )
		{
			// generate captcha field (effectively third row)
			var tr4 = document.createElement('tr');
			var td4_1 = document.createElement('td');
			var td4_2 = document.createElement('td');
			td4_1.className = 'row2';
			td4_2.className = 'row1';
			
			td4_1.appendChild(document.createTextNode($lang.get('editor_lbl_field_captcha')));
			td4_1.appendChild(document.createElement('br'));
			var small2 = document.createElement('small');
			small2.appendChild(document.createTextNode($lang.get('editor_msg_captcha_pleaseenter')));
			small2.appendChild(document.createElement('br'));
			small2.appendChild(document.createElement('br'));
			small2.appendChild(document.createTextNode($lang.get('editor_msg_captcha_blind')));
			td4_1.appendChild(small2);
			
			var img = document.createElement('img');
			img.src = makeUrlNS('Special', 'Captcha/' + captcha_hash);
			img.setAttribute('enano:captcha_hash', captcha_hash);
			img.id = 'enano_editor_captcha_img';
			img.onclick = function()
			{
				this.src = makeUrlNS('Special', 'Captcha/' + this.getAttribute('enano:captcha_hash') + '/' + Math.floor(Math.random() * 100000));
			}
			img.style.cursor = 'pointer';
			td4_2.appendChild(img);
			td4_2.appendChild(document.createElement('br'));
			td4_2.appendChild(document.createTextNode($lang.get('editor_lbl_field_captcha_code') + ' '));
			var input = document.createElement('input');
			input.type = 'text';
			input.id = 'enano_editor_field_captcha';
			input.setAttribute('enano:captcha_hash', captcha_hash);
			input.size = '9';
			td4_2.appendChild(input);
			
			tr4.appendChild(td4_1);
			tr4.appendChild(td4_2);
		}
		
		// Third row: controls
		
		var toolbar = '';
		var head = new templateParser(response.toolbar_templates.toolbar_start);
		var button = new templateParser(response.toolbar_templates.toolbar_button);
		var label = new templateParser(response.toolbar_templates.toolbar_label);
		var tail = new templateParser(response.toolbar_templates.toolbar_end);
		
		button.assign_bool({
				show_title: true
			});
		
		toolbar += head.run();
		
		// Button: Save
		button.assign_vars({
				TITLE: $lang.get('editor_btn_save'),
				IMAGE: editor_img_path + '/save.gif',
				SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 64),
				FLAGS: 'href="#" onclick="ajaxEditorSave(); return false;"'
			});
		toolbar += button.run();
		
		// Button: preview
		button.assign_vars({
				TITLE: $lang.get('editor_btn_preview'),
				IMAGE: editor_img_path + '/preview.gif',
				SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 32),
				FLAGS: 'href="#" onclick="ajaxEditorGenPreview(); return false;"'
			});
		toolbar += button.run();
		
		// Button: revert
		button.assign_vars({
				TITLE: $lang.get('editor_btn_revert'),
					IMAGE: editor_img_path + '/revert.gif',
					SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 48),
				FLAGS: 'href="#" onclick="ajaxEditorRevertToLatest(); return false;"'
			});
		toolbar += button.run();
		
		// Button: diff
		button.assign_vars({
				TITLE: $lang.get('editor_btn_diff'),
				IMAGE: editor_img_path + '/diff.gif',
				SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 0),
				FLAGS: 'href="#" onclick="ajaxEditorShowDiffs(); return false;"'
			});
		toolbar += button.run();
		
		// Button: cancel
		button.assign_vars({
				TITLE: $lang.get('editor_btn_cancel'),
				IMAGE: editor_img_path + '/discard.gif',
				SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 16),
				FLAGS: 'href="#" onclick="ajaxEditorCancel(); return false;"'
			});
		toolbar += button.run();
		
		// Separator
		label.assign_vars({
				TITLE: ' '
			});
		toolbar += label.run();
		
		// Button: Save draft
		button.assign_vars({
				TITLE: $lang.get('editor_btn_savedraft'),
				IMAGE: editor_img_path + '/savedraft.gif',
				// SPRITE: gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 80),
				SPRITE: false,
				FLAGS: 'href="#" onclick="ajaxPerformAutosave(); return false;" id="ajax_edit_savedraft_btn"'
			});
		toolbar += button.run();
		
		toolbar += tail.run();
		
		metatable.appendChild(tr1);
		metatable.appendChild(tr2);
		if ( captcha_hash )
		{
			metatable.appendChild(tr4);
		}
		// metatable.appendChild(tr3);
	}
	tblholder.appendChild(metatable);
	
	// Edit disclaimer/notice
	if ( response.edit_notice )
	{
		var en_div = document.createElement('div');
		en_div.innerHTML = response.edit_notice;
		en_div.className = 'usermessage';
		en_div.style.margin = '10px 0 0 0';
	}
	
	// Put it all together...
	form.appendChild(heading);
	if ( allow_wysiwyg )
		form.appendChild(toggler);
	
	if ( dn )
		form.appendChild(dn);
	
	if ( oldrev_box )
		form.appendChild(oldrev_box);
	
	form.appendChild(preview_anchor);
	form.appendChild(preview_container);
	form.appendChild(ta_wrapper);
	if ( !readonly )
		form.appendChild(tblholder);
	var tbdiv = document.createElement('div');
	tbdiv.innerHTML = toolbar;
	tbdiv.style.margin = '10px 0 0 0';
	form.appendChild(tbdiv);
	edcon.appendChild(form);
	
	if ( response.edit_notice && !readonly )
	{
		edcon.appendChild(en_div);
	}
	
	// more textarea attribs/init
	var textarea = document.getElementById('ajaxEditArea');
	textarea.as_last_save = 0;
	textarea.content_orig = content;
	textarea.used_draft = false;
	textarea.onkeyup = function()
	{
		if ( this.needReset )
		{
			var img = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('img')[0];
			var lbl = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('span')[0];
			img.src = editor_img_path + '/savedraft.gif';
			lbl.innerHTML = $lang.get('editor_btn_savedraft');
		}
		if ( window.AutosaveTimeoutObj )
			clearTimeout(window.AutosaveTimeoutObj);
		window.AutosaveTimeoutObj = setTimeout('ajaxAutosaveDraft();', ( AUTOSAVE_TIMEOUT * 1000 ));
	}
	
	if ( readonly )
	{
		textarea.className = 'mce_readonly';
		textarea.setAttribute('readonly', 'readonly');
	}
	
	$dynano('ajaxEditArea').object.focus();
	$dynano('ajaxEditArea').object._edTimestamp = timestamp;
	ajaxEditorSetContent(content);
	editor_orig_text = content;
	
	// If the editor preference is tinymce, switch the editor to TinyMCE now
	if ( response.page_format != 'wikitext' && allow_wysiwyg )
	{
		if ( typeof(editor_formats[response.page_format]) == 'object' )
		{
			// instruct the editor plugin to go ahead and build its UI
			editor_formats[response.page_format].ui_construct();
			window.page_format = response.page_format;
		}
		else
		{
			// Page was formatted with a plugin that no longer exists
			miniPromptMessage({
				title: $lang.get('editor_msg_convert_missing_plugin_title'),
				message: $lang.get('editor_msg_convert_missing_plugin_body', { plugin: response.page_format }),
				buttons: [
					{
						text: $lang.get('etc_ok'),
						onclick: function()
						{
							miniPromptDestroy(this);
							return false;
						}
					}
				]
			});
		}
	}
	
	if ( allow_wysiwyg )
	{
		var a = document.getElementById('enano_edit_btn_pt').getElementsByTagName('a')[0];
		a.onclick = function() {
			ajaxSetEditorPlain();
			return false;
		};
		var a = document.getElementById('enano_edit_btn_mce').getElementsByTagName('a')[0];
		a.onclick = function() {
			try
			{
				ajaxSetEditorMCE();
			}
			catch(e)
			{
				console.debug(e);
			}
			return false;
		};
	}
	else
	{
		$('#enano_edit_btn_pt').hide();
		$('#enano_edit_btn_mce').hide();
	}
	
	// if we're using the modal window, fade it in
	if ( editor_use_modal_window )
	{
		domOpacity(edcon, 0, 100, 500);
	}
	
	eval(setHook('editor_post_init'));
	
	// Autosave every 5 minutes           (m  *  s  *  ms)
	setInterval('ajaxPerformAutosave();', ( 5 * 60 * 1000 ));
	
	}
	catch(e)
	{
		console.debug(e);
	}
}

window.ajaxEditorDestroyModalWindow = function()
{
	if ( editor_use_modal_window )
	{
		var edcon = document.getElementById('ajaxEditContainerModal');
		var body = document.getElementsByTagName('body')[0];
		if ( edcon )
		{
			body.removeChild(edcon);
			enlighten(true, 'enano_editor_darkener');
		}
	}
}

window.ajaxEditorSave = function(is_draft, text_override)
{
	if ( !is_draft )
	{
		ajaxSetEditorLoading();
	}
	if ( is_draft && editor_save_lock )
		return false;
	else
		editor_save_lock = true;
	
	var ta_content = ( text_override ) ? text_override : ajaxEditorGetContent();
	
	if ( !is_draft && ( ta_content == '' || ta_content == '<p></p>' || ta_content == '<p>&nbsp;</p>' ) )
	{
		new MessageBox(MB_OK|MB_ICONSTOP, $lang.get('editor_err_no_text_title'), $lang.get('editor_err_no_text_body'));
		ajaxUnSetEditorLoading();
		return false;
	}
	
	if ( is_draft )
	{
		// ajaxSetEditorLoading();
		var img = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('img')[0];
		var lbl = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('span')[0];
		img.src = cdnPath + '/images/loading.gif';
		var d = new Date();
		var m = String(d.getMinutes());
		if ( m.length < 2 )
			m = '0' + m;
		var time = d.getHours() + ':' + m;
		lbl.innerHTML = $lang.get('editor_msg_draft_saving');
	}
	
	var edit_summ = $dynano('enano_editor_field_summary').object.value;
	if ( !edit_summ )
		edit_summ = '';
	var is_minor = ( $dynano('enano_editor_field_minor').object.checked ) ? 1 : 0;
	var timestamp = $dynano('ajaxEditArea').object._edTimestamp;
	var used_draft = $dynano('ajaxEditArea').object.used_draft;
	
	var json_packet = {
		src: ta_content,
		summary: edit_summ,
		minor_edit: is_minor,
		time: timestamp,
		draft: ( is_draft == true ),
		format: window.page_format,
		used_draft: used_draft
	};
	
	eval(setHook('editor_save_presend'));
	
	// Do we need to add captcha info?
	if ( document.getElementById('enano_editor_field_captcha') && !is_draft )
	{
		var captcha_field = document.getElementById('enano_editor_field_captcha');
		if ( captcha_field.value == '' )
		{
			new MessageBox(MB_OK|MB_ICONSTOP, $lang.get('editor_err_need_captcha_title'), $lang.get('editor_err_need_captcha_body'));
			ajaxUnSetEditorLoading();
			return false;
		}
		json_packet.captcha_code = captcha_field.value;
		json_packet.captcha_id = captcha_field.getAttribute('enano:captcha_hash');
	}
	
	json_packet = ajaxEscape(toJSONString(json_packet));
	ajaxPost(stdAjaxPrefix + '&_mode=savepage_json', 'r=' + json_packet, function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				ajaxUnSetEditorLoading();
				var response = String(ajax.responseText + '');
				if ( !check_json_response(response) )
				{
					handle_invalid_json(response);
					return false;
				}
				
				response = parseJSON(response);
				// This will only be used if there was a lower-level error.
				if ( response.mode == 'error' )
				{
					editor_save_lock = false;
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_server'), response.error);
					return false;
				}
				// This will be used if the PageProcessor generated errors (usually security/permissions related)
				if ( response.mode == 'errors' )
				{
					editor_save_lock = false;
					// This will be true if the user entered a captcha code incorrectly, thus
					// invalidating the code and requiring a new image to be generated.
					if ( response.new_captcha )
					{
						// Generate the new captcha field
						var img = document.getElementById('enano_editor_captcha_img');
						var input = document.getElementById('enano_editor_field_captcha');
						if ( img && input )
						{
							img._captchaHash = response.new_captcha;
							input._captchaHash = response.new_captcha;
							img.src = makeUrlNS('Special', 'Captcha/' + response.new_captcha);
							input.value = '';
						}
					}
					var errors = '<ul><li>' + implode('</li><li>', response.errors) + '</li></ul>';
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_save_title'), $lang.get('editor_err_save_body') + errors);
					return false;
				}
				// If someone else got to the page first, warn the user
				if ( response.mode == 'obsolete' )
				{
					editor_save_lock = false;
					// Update the local timestamp to allow override
					$dynano('ajaxEditArea').object._edTimestamp = response.time;
					new MessageBox(MB_OK | MB_ICONEXCLAMATION, $lang.get('editor_err_obsolete_title'), $lang.get('editor_err_obsolete_body', { author: response.author, timestamp: response.date_string, page_url: makeUrl(title, false, true) }));
					return false;
				}
				if ( response.mode == 'success' )
				{
					if ( response.is_draft )
					{
						document.getElementById('ajaxEditArea').used_draft = true;
						document.getElementById('ajaxEditArea').needReset = true;
						var img = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('img')[0];
						var lbl = $dynano('ajax_edit_savedraft_btn').object.getElementsByTagName('span')[0];
						if ( response.is_draft == 'delete' )
						{
							img.src = scriptPath + '/images/editor/savedraft.gif';
							lbl.innerHTML = $lang.get('editor_btn_savedraft');
							
							var dn = $dynano('ajax_edit_draft_notice').object;
							if ( dn )
							{
								dn.parentNode.removeChild(dn);
							}
						}
						else
						{
							img.src = scriptPath + '/images/mini-info.png';
							var d = new Date();
							var m = String(d.getMinutes());
							if ( m.length < 2 )
								m = '0' + m;
							var time = d.getHours() + ':' + m;
							lbl.innerHTML = $lang.get('editor_msg_draft_saved', { time: time });
						}
						editor_save_lock = false;
					}
					else
					{
						// The save was successful; reset flags and make another request for the new page content
						setAjaxLoading();
						editor_open = false;
						editor_save_lock = false;
						enableUnload();
						if ( window.page_format != 'wikitext' )
						{
							if ( typeof(editor_formats[window.page_format].ui_destroy) == 'function' )
							{
								editor_formats[window.page_format].ui_destroy();
							}
						}
						changeOpac(0, 'ajaxEditContainer');
						ajaxGet(stdAjaxPrefix + '&_mode=getpage&noheaders', function(ajax)
							{
								if ( ajax.readyState == 4 && ajax.status == 200 )
								{
									unsetAjaxLoading();
									selectButtonMajor('article');
									unselectAllButtonsMinor();
									
									ajaxEditorDestroyModalWindow();
									document.getElementById('ajaxEditContainer').innerHTML = '<div class="usermessage">' + $lang.get('editor_msg_saved') + '</div>' + ajax.responseText;
									// if we're on a userpage, call the onload function to rebuild the tabs
									if ( typeof(userpage_onload) == 'function' )
									{
										window.userpage_blocks = [];
										userpage_onload();
									}
									opacity('ajaxEditContainer', 0, 100, 1000);
								}
							});
					}
				}
			}
		}, true);
}

// Delete the draft (this is a massive server-side hack)
window.ajaxEditorDeleteDraft = function()
{
	miniPromptMessage({
			title: $lang.get('editor_msg_confirm_delete_draft_title'),
			message: $lang.get('editor_msg_confirm_delete_draft_body'),
			buttons: [
					{
						text: $lang.get('editor_btn_delete_draft'),
						color: 'red',
						style: {
							fontWeight: 'bold'
						},
						onclick: function() {
							ajaxEditorDeleteDraftReal();
							miniPromptDestroy(this);
						}
					},
					{
						text: $lang.get('etc_cancel'),
						onclick: function() {
							miniPromptDestroy(this);
						}
					}
				]
		});
}

window.ajaxEditorDeleteDraftReal = function()
{
	return ajaxEditorSave(true, -1);
}

window.ajaxEditorGenPreview = function()
{
	ajaxSetEditorLoading();
	var ta_content = ajaxEditorGetContent();
	ta_content = ajaxEscape(ta_content);
	if ( $dynano('enano_editor_preview').object.innerHTML != '' )
	{
		opacity('enano_editor_preview', 100, 0, 500);
	}
	ajaxPost(stdAjaxPrefix + '&_mode=preview', 'text=' + ta_content, function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				ajaxUnSetEditorLoading();
				changeOpac(0, 'enano_editor_preview');
				$dynano('enano_editor_preview').object.innerHTML = ajax.responseText;
				window.location.hash = '#ajax_preview';
				opacity('enano_editor_preview', 0, 100, 500);
			}
		}, true);
}

window.ajaxEditorRevertToLatest = function()
{
	miniPromptMessage({
			title: $lang.get('editor_msg_revert_confirm_title'),
			message: $lang.get('editor_msg_revert_confirm_body'),
			buttons: [
				{
					text: $lang.get('editor_btn_revert_confirm'),
					color: 'red',
					sprite: [ editor_img_path + '/sprite.png', 16, 16, 0, 48 ],
					style: {
						fontWeight: 'bold'
					},
					onclick: function()
					{
						ajaxEditorRevertToLatestReal();
						miniPromptDestroy(this);
						return false;
					}
				},
				{
					text: $lang.get('etc_cancel'),
					onclick: function()
					{
						miniPromptDestroy(this);
						return false;
					}
				}
			]
		});
}

window.ajaxEditorRevertToLatestReal = function()
{
	ajaxSetEditorLoading();
	ajaxGet(stdAjaxPrefix + '&_mode=getsource', function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				ajaxUnSetEditorLoading();
				
				var response = String(ajax.responseText + '');
				if ( !check_json_response(response) )
				{
					handle_invalid_json(response);
					return false;
				}
				
				response = parseJSON(response);
				if ( response.mode == 'error' )
				{
					unselectAllButtonsMinor();
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_server'), response.error);
					return false;
				}
				
				if ( !response.auth_view_source )
				{
					unselectAllButtonsMinor();
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_access_denied_title'), $lang.get('editor_err_access_denied_body'));
					return false;
				}
				
				setTimeout(function()
					{
						editor_convert_if_needed(response.page_format);
						ajaxEditorSetContent(response.src);
					}, aclDisableTransitionFX ? 10 : 750);
			}
		}, true);
}

window.ajaxEditorShowDiffs = function()
{
	ajaxSetEditorLoading();
	var ta_content = ajaxEditorGetContent();
	ta_content = ajaxEscape(ta_content);
	if ( $dynano('enano_editor_preview').object.innerHTML != '' )
	{
		opacity('enano_editor_preview', 100, 0, 500);
	}
	ajaxPost(stdAjaxPrefix + '&_mode=diff_cur', 'text=' + ta_content, function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				ajaxUnSetEditorLoading();
				changeOpac(0, 'enano_editor_preview');
				$dynano('enano_editor_preview').object.innerHTML = ajax.responseText;
				window.location.hash = '#ajax_preview';
				opacity('enano_editor_preview', 0, 100, 500);
			}
		}, true);
}

window.ajaxEditorCancel = function()
{
	miniPromptMessage({
			title: $lang.get('editor_msg_cancel_confirm_title'),
			message: $lang.get('editor_msg_cancel_confirm_body'),
			buttons: [
				{
					text: $lang.get('editor_btn_cancel_confirm'),
					color: 'red',
					sprite: [ editor_img_path + '/sprite.png', 16, 16, 0, 16 ],
					style: {
						fontWeight: 'bold'
					},
					onclick: function()
					{
						setAjaxLoading();
						editor_open = false;
						enableUnload();
						if ( typeof(editor_formats[window.page_format]) == 'object' && typeof(editor_formats[window.page_format].ui_destroy) == 'function' )
						{
							editor_formats[window.page_format].ui_destroy();
						}
						ajaxEditorDestroyModalWindow();
						ajaxReset();
						miniPromptDestroy(this);
						return false;
					}
				},
				{
					text: $lang.get('editor_btn_cancel_cancel'),
					onclick: function()
					{
						miniPromptDestroy(this);
						return false;
					}
				}
			]
		});
}

window.ajaxSetEditorMCE = function()
{
	if ( editor_loading )
		return false;
	
	var len = 0;
	for ( var i in editor_formats )
	{
		len++;
	}
	
	if ( len == 0 )
	{
		miniPromptMessage({
				title: $lang.get('editor_msg_convert_no_plugins_title'),
				message: $lang.get('editor_msg_convert_no_plugins_body'),
				buttons: [
					{
						text: $lang.get('etc_cancel'),
						onclick: function()
						{
							miniPromptDestroy(this);
							return false;
						}
					}
				]
			});
		return false;
	}
	
	var mp = miniPrompt(function(div)
		{
			$(div).css('text-align', 'center');
			$(div).append('<h3>' + $lang.get('editor_msg_convert_confirm_title') + '</h3>');
			$(div).append('<p>' + $lang.get('editor_msg_convert_confirm_body') + '</p>');
			var select = '<select class="format">';
			for ( var i in editor_formats )
			{
				var obj = editor_formats[i];
				select += '<option value="' + i + '">' + $lang.get(obj.name) + '</option>';
			}
			select += '</select>';
			
			$(div).append('<p class="format_drop">' + $lang.get('editor_msg_convert_lbl_plugin') + select + '</p>');
			$(div).append('<p><a href="#" class="abutton abutton_green go_action" style="font-weight: bold;">' + gen_sprite_html(editor_img_path + '/sprite.png', 16, 16, 0, 112) + $lang.get('editor_btn_graphical_convert') + '</a>'
					+ '<a href="#" class="abutton cancel_action">' + $lang.get('etc_cancel') + '</a></p>');
			
			$('a.go_action', div).click(function()
				{
					// go ahead with converting to this format
					
					var parent = miniPromptGetParent(this);
					var whitey = whiteOutMiniPrompt(parent);
					var plugin = $('select.format', parent).val();
					ajaxEditorSetFormat(plugin, function()
						{
							if ( typeof(whitey) == 'object' )
								whiteOutReportSuccess(whitey);
						});
					return false;
				});
			
			$('a.cancel_action', div).click(function()
				{
					miniPromptDestroy(this);
					return false;
				});
		});
	
	return false;
}

window.ajaxSetEditorPlain = function(confirmed)
{
	if ( editor_loading )
		return false;
	
	if ( !confirmed )
	{
		miniPromptMessage({
				title: $lang.get('editor_msg_convert_confirm_title'),
				message: $lang.get('editor_msg_convert_confirm_body'),
				buttons: [
					{
						color: 'green',
						text: $lang.get('editor_btn_wikitext'),
						style: {
							fontWeight: 'bold'
						},
						sprite: [ editor_img_path + '/sprite.png', 16, 16, 0, 96 ],
						onclick: function()
						{
							ajaxSetEditorPlain(true);
							miniPromptDestroy(this);
							return false;
						}
					},
					{
						text: $lang.get('etc_cancel'),
						onclick: function()
						{
							miniPromptDestroy(this);
							return false;
						}
					}
				]
			});
		return false;
	}
	
	// Clear out existing buttons
	var span_wiki = $dynano('enano_edit_btn_pt').object;
	var span_mce  = $dynano('enano_edit_btn_mce').object;
	span_wiki.style.display = 'none';
	span_mce.style.display = 'inline';
	
	// Swap editor
	if ( typeof(editor_formats[window.page_format]) == 'object' && typeof(editor_formats[window.page_format].ui_destroy) == 'function' )
	{
		if ( typeof(editor_formats[window.page_format].convert_from) == 'function' )
		{
			var text = ajaxEditorGetContent();
			var newtext = editor_formats[window.page_format].convert_from(text);
			if ( typeof(newtext) != 'string' )
				newtext = text;
		}
		editor_formats[window.page_format].ui_destroy();
		$('#ajaxEditArea').val(newtext);
	}
	
	window.page_format = 'wikitext';
}

var editor_loading = false;

window.ajaxSetEditorLoading = function()
{
	var ed = false;
	if ( window.tinyMCE )
	{
		ed = tinyMCE.get('ajaxEditArea');
	}
	editor_loading = true;
	if ( ed )
	{
		ed.setProgressState(1);
	}
	else
	{
		ed = document.getElementById('ajaxEditArea');
		var blackout = document.createElement('div');
		blackout.style.position = 'absolute';
		blackout.style.top = $dynano('ajaxEditArea').Top() + 'px';
		blackout.style.left = $dynano('ajaxEditArea').Left() + 'px';
		blackout.style.width = $dynano('ajaxEditArea').Width() + 'px';
		blackout.style.height = $dynano('ajaxEditArea').Height() + 'px';
		blackout.style.backgroundColor = '#FFFFFF';
		domObjChangeOpac(60, blackout);
		blackout.style.backgroundImage = 'url(' + cdnPath + '/images/loading-big.gif)';
		blackout.style.backgroundPosition = 'center center';
		blackout.style.backgroundRepeat = 'no-repeat';
		blackout.id = 'enano_editor_blackout';
		blackout.style.zIndex = getHighestZ() + 2;
		
		var body = document.getElementsByTagName('body')[0];
		body.appendChild(blackout);
	}
}

window.ajaxUnSetEditorLoading = function()
{
	editor_loading = false;
	var ed = false;
	if ( window.tinyMCE )
	{
		ed = tinyMCE.get('ajaxEditArea');
	}
	if ( ed )
	{
		ed.setProgressState(0);
	}
	else
	{
		var blackout = document.getElementById('enano_editor_blackout');
		var body = document.getElementsByTagName('body')[0];
		if ( !blackout )
			return false;
		body.removeChild(blackout);
	}
}

window.ajaxAutosaveDraft = function()
{
	var aed = document.getElementById('ajaxEditArea');
	if ( !aed )
		return false;
	var last_save = aed.as_last_save;
	var now = unix_time();
	if ( ( last_save + 120 ) < now && aed.value != aed.content_orig )
	{
		ajaxPerformAutosave();
	}
}

window.ajaxPerformAutosave = function()
{
	var aed = document.getElementById('ajaxEditArea');
	if ( !aed )
		return false;
	var now = unix_time();
	aed.as_last_save = now;
	
	var ta_content = ajaxEditorGetContent();
	
	if ( ta_content == '' || ta_content == '<p></p>' || ta_content == '<p>&nbsp;</p>' || ta_content == editor_orig_text || ta_content == editor_last_draft )
	{
		return false;
	}
	
	editor_last_draft = ta_content;
	
	ajaxEditorSave(true);
}

window.ajaxEditorUseDraft = function()
{
	var aed = document.getElementById('ajaxEditArea');
	if ( !aed )
		return false;
	ajaxSetEditorLoading();
	ajaxGet(stdAjaxPrefix + '&_mode=getsource&get_draft=1', function(ajax)
		{
			if ( ajax.readyState == 4 && ajax.status == 200 )
			{
				ajaxUnSetEditorLoading();
				
				var response = String(ajax.responseText + '');
				if ( !check_json_response(response) )
				{
					handle_invalid_json(response);
					return false;
				}
				
				response = parseJSON(response);
				if ( response.mode == 'error' )
				{
					unselectAllButtonsMinor();
					new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_server'), response.error);
					return false;
				}
				
				editor_convert_if_needed(response.page_format);
				
				if ( response.page_format != 'wikitext' && typeof(editor_formats[response.page_format]) == 'object' )
				{
					if ( typeof(editor_formats[response.page_format].set_text) == 'function' )
					{
						editor_formats[response.page_format].set_text(response.src);
					}
					else
					{
						$('#ajaxEditArea').val(response.src);
					}
				}
				else
				{
					$('#ajaxEditArea').val(response.src);
				}
				
				$dynano('ajaxEditArea').object.used_draft = true;
				editor_orig_text = editor_last_draft = response.src;
				
				var es = document.getElementById('enano_editor_field_summary');
				if ( es.value == '' )
				{
					es.value = response.edit_summary;
				}
				
				var dn = $dynano('ajax_edit_draft_notice').object;
				dn.parentNode.removeChild(dn);
			}
		}, true);
}

window.editor_convert_if_needed = function(targetformat, noticetitle, noticebody)
{
	// Do we need to change the format?
	var need_to_mce = ( targetformat != 'wikitext' && page_format == 'wikitext' );
	var need_to_wkt = ( targetformat == 'wikitext' && page_format != 'wikitext' );
	if ( need_to_mce )
	{
		editor_formats[targetformat].ui_construct();
		window.page_format = targetformat;
		
		// Clear out existing buttons
		var span_wiki = $dynano('enano_edit_btn_pt').object;
		var span_mce  = $dynano('enano_edit_btn_mce').object;
		span_wiki.style.display = 'inline';
		span_mce.style.display = 'none';
	}
	else if ( need_to_wkt )
	{
		editor_formats[window.page_format].ui_construct();
		window.page_format = 'wikitext';
		
		// Clear out existing buttons
		var span_wiki = $dynano('enano_edit_btn_pt').object;
		var span_mce  = $dynano('enano_edit_btn_mce').object;
		span_wiki.style.display = 'none';
		span_mce.style.display = 'inline';
	}
	if ( need_to_mce || need_to_wkt )
	{
		// explain the conversion
		if ( !noticetitle )
			noticetitle = 'editor_msg_convert_draft_load_title';
		if ( !noticebody )
			noticebody = 'editor_msg_convert_draft_load_body';
		
		miniPromptMessage({
				title: $lang.get(noticetitle),
				message: $lang.get(noticebody),
				buttons: [
					{
						text: $lang.get('etc_ok'),
						onclick: function()
						{
							miniPromptDestroy(this);
							return false;
						}
					}
				]
			});
	}
}

window.ajaxEditorSetFormat = function(plugin, success_func)
	{
		// perform conversion
		if ( typeof(editor_formats[plugin]) != 'object' )
			return false;
		
		if ( typeof(editor_formats[plugin].convert_to) == 'function' )
		{
			var result = editor_formats[plugin].convert_to($('#ajaxEditArea').val());
		}
		else
		{
			var result = $('#ajaxEditArea').val();
		}
		if ( typeof(result) != 'string' )
		{
			result = $('#ajaxEditArea').val();
		}
		$('#ajaxEditArea').val(result);
		if ( typeof(editor_formats[plugin].ui_construct) == 'function' )
		{
			editor_formats[plugin].ui_construct();
		}
		success_func();
		window.page_format = plugin;
		
		// change the buttons over
		$('#enano_edit_btn_pt').css('display', 'inline');
		$('#enano_edit_btn_mce').css('display', 'none');
	};
	
window.ajaxEditorGetContent = function()
	{
		if ( window.page_format == 'wikitext' )
		{
			return $('#ajaxEditArea').val();
		}
		else
		{
			if ( typeof(editor_formats[window.page_format].get_text) == 'function' )
			{
				return editor_formats[window.page_format].get_text();
			}
			else
			{
				return $('#ajaxEditArea').val();
			}
		}
	};

window.ajaxEditorSetContent = function(text)
	{
		if ( window.page_format == 'wikitext' )
		{
			$('#ajaxEditArea').val(text);
		}
		else
		{
			if ( typeof(editor_formats[window.page_format].set_text) == 'function' )
			{
				editor_formats[window.page_format].set_text(text);
			}
			else
			{
				$('#ajaxEditArea').val(text);
			}
		}
	};
