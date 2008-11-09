// Javascript routines for the page editor

// Idle time required for autosave, in seconds
var AUTOSAVE_TIMEOUT = 15;
var AutosaveTimeoutObj = null;
var editor_img_path = cdnPath + '/images/editor';
var editor_save_lock = false;

window.ajaxEditor = function(revid)
{
  if ( KILL_SWITCH )
    return true;
  if ( editor_open )
    return true;
  load_component('l10n');
  load_component('template-compiler');
  load_component('messagebox');
  selectButtonMinor('edit');
  selectButtonMajor('article');
  setAjaxLoading();
  
  var rev_id_uri = ( revid ) ? '&revid=' + revid : '';
  ajaxGet(stdAjaxPrefix + '&_mode=getsource' + rev_id_uri, function()
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
    if ( readCookie('enano_editor_mode') == 'tinymce' )
    {
      // Current selection is TinyMCE - make span_wiki have the link and span_mce be plaintext
      var a = document.createElement('a');
      a.href = '#';
      a.appendChild(document.createTextNode($lang.get('editor_btn_wikitext')));
      span_wiki.appendChild(a);
      toggler.appendChild(span_wiki);
      toggler.appendChild(document.createTextNode(' | '));
      span_mce.appendChild(document.createTextNode($lang.get('editor_btn_graphical')));
      toggler.appendChild(span_mce);
    }
    else
    {
      // Current selection is wikitext - set span_wiki to plaintext and span_mce to link
      span_wiki.appendChild(document.createTextNode($lang.get('editor_btn_wikitext')));
      toggler.appendChild(span_wiki);
      toggler.appendChild(document.createTextNode(' | '));
      var a = document.createElement('a');
      a.href = '#';
      a.appendChild(document.createTextNode($lang.get('editor_btn_graphical')));
      span_mce.appendChild(a);
      toggler.appendChild(span_mce);
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
  ta_wrapper.appendChild(textarea);
  
  textarea.id = 'ajaxEditArea';
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
        FLAGS: 'href="#" onclick="ajaxEditorSave(); return false;"'
      });
    toolbar += button.run();
    
    // Button: preview
    button.assign_vars({
        TITLE: $lang.get('editor_btn_preview'),
        IMAGE: editor_img_path + '/preview.gif',
        FLAGS: 'href="#" onclick="ajaxEditorGenPreview(); return false;"'
      });
    toolbar += button.run();
    
    // Button: revert
    button.assign_vars({
        TITLE: $lang.get('editor_btn_revert'),
          IMAGE: editor_img_path + '/revert.gif',
        FLAGS: 'href="#" onclick="ajaxEditorRevertToLatest(); return false;"'
      });
    toolbar += button.run();
    
    // Button: diff
    button.assign_vars({
        TITLE: $lang.get('editor_btn_diff'),
        IMAGE: editor_img_path + '/diff.gif',
        FLAGS: 'href="#" onclick="ajaxEditorShowDiffs(); return false;"'
      });
    toolbar += button.run();
    
    // Button: cancel
    button.assign_vars({
        TITLE: $lang.get('editor_btn_cancel'),
        IMAGE: editor_img_path + '/discard.gif',
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
  if ( editNotice ) // This is set globally in {JS_DYNAMIC_VARS}.
  {
    var en_div = document.createElement('div');
    en_div.innerHTML = editNotice;
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
  form.appendChild(tblholder);
  form.innerHTML += '<div style="margin: 10px 0 0 0;">' + toolbar + '</div>';
  edcon.appendChild(form);
  
  if ( editNotice && !readonly )
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
  $dynano('ajaxEditArea').setContent(content);
  
  // If the editor preference is tinymce, switch the editor to TinyMCE now
  if ( readCookie('enano_editor_mode') == 'tinymce' && allow_wysiwyg )
  {
    $dynano('ajaxEditArea').switchToMCE();
  }
  
  if ( allow_wysiwyg )
  {
    if ( readCookie('enano_editor_mode') == 'tinymce' )
    {
      var a = document.getElementById('enano_edit_btn_pt').getElementsByTagName('a')[0];
      a.onclick = function() {
        ajaxSetEditorPlain();
        return false;
      };
    }
    else
    {
      var a = document.getElementById('enano_edit_btn_mce').getElementsByTagName('a')[0];
      a.onclick = function() {
        ajaxSetEditorMCE();
        return false;
      };
    }
  }
  
  // if we're using the modal window, fade it in
  if ( editor_use_modal_window )
  {
    domOpacity(edcon, 0, 100, 500);
  }
  
  // Autosave every 5 minutes           (m  *  s  *  ms)
  setInterval('ajaxPerformAutosave();', ( 5 * 60 * 1000 ));
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
    ajaxSetEditorLoading();
  if ( is_draft && editor_save_lock )
    return false;
  else
    editor_save_lock = true;
  
  var ta_content = ( text_override ) ? text_override : $dynano('ajaxEditArea').getContent();
  
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
    used_draft: used_draft
  };
  
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
  ajaxPost(stdAjaxPrefix + '&_mode=savepage_json', 'r=' + json_packet, function()
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
          new MessageBox(MB_OK | MB_ICONSTOP, $lang.get('editor_err_server'), response.error);
          return false;
        }
        // This will be used if the PageProcessor generated errors (usually security/permissions related)
        if ( response.mode == 'errors' )
        {
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
          }
          else
          {
            // The save was successful; reset flags and make another request for the new page content
            setAjaxLoading();
            editor_open = false;
            editor_save_lock = false;
            enableUnload();
            changeOpac(0, 'ajaxEditContainer');
            ajaxGet(stdAjaxPrefix + '&_mode=getpage&noheaders', function()
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
  var ta_content = $dynano('ajaxEditArea').getContent();
  ta_content = ajaxEscape(ta_content);
  if ( $dynano('enano_editor_preview').object.innerHTML != '' )
  {
    opacity('enano_editor_preview', 100, 0, 500);
  }
  ajaxPost(stdAjaxPrefix + '&_mode=preview', 'text=' + ta_content, function()
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
  var mb = new MessageBox(MB_YESNO | MB_ICONQUESTION, $lang.get('editor_msg_revert_confirm_title'), $lang.get('editor_msg_revert_confirm_body'));
  mb.onclick['Yes'] = function()
  {
    setTimeout('ajaxEditorRevertToLatestReal();', 750);
  }
}

window.ajaxEditorRevertToLatestReal = function()
{
  ajaxSetEditorLoading();
  ajaxGet(stdAjaxPrefix + '&_mode=getsource', function()
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
        
        $dynano('ajaxEditArea').setContent(response.src);
      }
    }, true);
}

window.ajaxEditorShowDiffs = function()
{
  ajaxSetEditorLoading();
  var ta_content = $dynano('ajaxEditArea').getContent();
  ta_content = ajaxEscape(ta_content);
  if ( $dynano('enano_editor_preview').object.innerHTML != '' )
  {
    opacity('enano_editor_preview', 100, 0, 500);
  }
  ajaxPost(stdAjaxPrefix + '&_mode=diff_cur', 'text=' + ta_content, function()
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
  var mb = new MessageBox(MB_YESNO | MB_ICONQUESTION, $lang.get('editor_msg_cancel_confirm_title'), $lang.get('editor_msg_cancel_confirm_body'));
  mb.onclick['Yes'] = function()
  {
    setAjaxLoading();
    ajaxEditorDestroyModalWindow();
    editor_open = false;
    enableUnload();
    setTimeout('ajaxReset();', 750);
  }
}

window.ajaxSetEditorMCE = function()
{
  if ( editor_loading )
    return false;
  
  // Clear out existing buttons
  var span_wiki = $dynano('enano_edit_btn_pt').object;
  var span_mce  = $dynano('enano_edit_btn_mce').object;
  span_wiki.removeChild(span_wiki.firstChild);
  span_mce.removeChild(span_mce.firstChild);
  
  // Rebuild control
  var a = document.createElement('a');
  a.href = '#';
  a.onclick = function() {
    ajaxSetEditorPlain();
    return false;
  };
  a.appendChild(document.createTextNode($lang.get('editor_btn_wikitext')));
  span_wiki.appendChild(a);
  span_mce.appendChild(document.createTextNode($lang.get('editor_btn_graphical')));
  
  // Swap editor
  $dynano('ajaxEditArea').switchToMCE();
  
  // Remember the setting
  createCookie('enano_editor_mode', 'tinymce', 365);
}

window.ajaxSetEditorPlain = function()
{
  if ( editor_loading )
    return false;
  
  // Clear out existing buttons
  var span_wiki = $dynano('enano_edit_btn_pt').object;
  var span_mce  = $dynano('enano_edit_btn_mce').object;
  span_wiki.removeChild(span_wiki.firstChild);
  span_mce.removeChild(span_mce.firstChild);
  
  // Rebuild control
  span_wiki.appendChild(document.createTextNode($lang.get('editor_btn_wikitext')));
  var a = document.createElement('a');
  a.href = '#';
  a.onclick = function() {
    ajaxSetEditorMCE();
    return false;
  };
  a.appendChild(document.createTextNode($lang.get('editor_btn_graphical')));
  span_mce.appendChild(a);
  
  // Swap editor
  $dynano('ajaxEditArea').destroyMCE();
  
  // Remember the setting
  createCookie('enano_editor_mode', 'text', 365);
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
    blackout.style.backgroundImage = 'url(' + scriptPath + '/includes/clientside/tinymce/themes/advanced/skins/default/img/progress.gif)';
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
  
  var ta_content = $dynano('ajaxEditArea').getContent();
  
  if ( ta_content == '' || ta_content == '<p></p>' || ta_content == '<p>&nbsp;</p>' )
  {
    return false;
  }
  
  ajaxEditorSave(true);
}

window.ajaxEditorUseDraft = function()
{
  var aed = document.getElementById('ajaxEditArea');
  if ( !aed )
    return false;
  ajaxSetEditorLoading();
  ajaxGet(stdAjaxPrefix + '&_mode=getsource&get_draft=1', function()
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
        
        $dynano('ajaxEditArea').setContent(response.src);
        $dynano('ajaxEditArea').object.used_draft = true;
        
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

