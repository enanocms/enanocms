/**
 * Creates a control that can be used to edit a rank.
 */

var RankEditorControl = function(rankdata)
{
  this.rankdata = ( typeof(rankdata) == 'object' ) ? rankdata : {};
  if ( !this.rankdata.rank_style )
  {
    this.rankdata.rank_style = '';
  }
  
  // have the browser parse CSS for us and use an anchor to be as close
  // as possible in calculating CSS
  
  // this is kind of a hack as it relies on setAttribute/getAttribute in
  // order to obtain stringified versions of CSS data
  var cssobj = document.createElement('a');
  cssobj.setAttribute('style', this.rankdata.rank_style);
  
  this.style_sim_obj = cssobj;
  
  // figure out if we're editing or creating
  this.editing = ( typeof(this.rankdata.rank_id) == 'number' );
  
  this.render = function()
  {
    var editor = document.createElement('div');
    editor.className = 'tblholder';
    // stash this editor instance in the parent div for later function calls
    editor.editor = this;
    this.wrapperdiv = editor;
    editor.style.width = '100%';
    
    // tables suck.
    var table = document.createElement('table');
    table.setAttribute('cellspacing', '1');
    table.setAttribute('cellpadding', '4');
    table.setAttribute('width', '100%');
    
    // heading: "Edit rank: foo" or "Create a new rank"
    var tr_head = document.createElement('tr');
    var th_head = document.createElement('th');
    th_head.setAttribute('colspan', '2');
    if ( this.editing )
    {
      var th_head_string = 'acpur_th_edit_rank';
      var th_head_data = { rank_title: $lang.get(this.rankdata.rank_title) };
    }
    else
    {
      var th_head_string = 'acpur_th_create_rank';
      var th_head_data = { };
    }
    th_head.appendChild(document.createTextNode($lang.get(th_head_string, th_head_data)));
    tr_head.appendChild(th_head);
    this.th_head = th_head;
    table.appendChild(tr_head);
    
    // row: rank title
    var tr_title = document.createElement('tr');
    var td_title_l = document.createElement('td');
    var td_title_f = document.createElement('td');
    
    td_title_l.className = td_title_f.className = 'row1';
    
    td_title_l.appendChild(document.createTextNode($lang.get('acpur_field_rank_title')));
    
    // field: rank title
    var f_rank_title = document.createElement('input');
    f_rank_title.type = 'text';
    f_rank_title.size = '30';
    f_rank_title.value = ( this.editing ) ? this.rankdata.rank_title : '';
    f_rank_title.editor = this;
    f_rank_title.onkeyup = function()
    {
      this.editor.renderPreview();
    }
    this.f_rank_title = f_rank_title;
    td_title_f.appendChild(f_rank_title);
    
    tr_title.appendChild(td_title_l);
    tr_title.appendChild(td_title_f);
    table.appendChild(tr_title);
    
    // row: basic style options
    var tr_basic = document.createElement('tr');
    var td_basic_l = document.createElement('td');
    var td_basic_f = document.createElement('td');
    
    td_basic_l.className = td_basic_f.className = 'row2';
    
    td_basic_l.appendChild(document.createTextNode($lang.get('acpur_field_style_basic')));
    
    // fieldset: basic style options
    // field: bold
    var l_basic_bold = document.createElement('label');
    var f_basic_bold = document.createElement('input');
    f_basic_bold.type = 'checkbox';
    f_basic_bold.checked = ( this.style_sim_obj.style.fontWeight == 'bold' ) ? true : false;
    f_basic_bold.editor = this;
    f_basic_bold.onclick = function()
    {
      this.editor.style_sim_obj.style.fontWeight = ( this.checked ) ? 'bold' : null;
      this.editor.renderPreview();
    }
    l_basic_bold.style.fontWeight = 'bold';
    l_basic_bold.appendChild(f_basic_bold);
    l_basic_bold.appendChild(document.createTextNode(' '));
    l_basic_bold.appendChild(document.createTextNode($lang.get('acpur_field_style_basic_bold')));
    
    // field: italic
    var l_basic_italic = document.createElement('label');
    var f_basic_italic = document.createElement('input');
    f_basic_italic.type = 'checkbox';
    f_basic_italic.checked = ( this.style_sim_obj.style.fontStyle == 'italic' ) ? true : false;
    f_basic_italic.editor = this;
    f_basic_italic.onclick = function()
    {
      this.editor.style_sim_obj.style.fontStyle = ( this.checked ) ? 'italic' : null;
      this.editor.renderPreview();
    }
    l_basic_italic.style.fontStyle = 'italic';
    l_basic_italic.appendChild(f_basic_italic);
    l_basic_italic.appendChild(document.createTextNode(' '));
    l_basic_italic.appendChild(document.createTextNode($lang.get('acpur_field_style_basic_italic')));
    
    // field: underline
    var l_basic_underline = document.createElement('label');
    var f_basic_underline = document.createElement('input');
    f_basic_underline.type = 'checkbox';
    f_basic_underline.checked = ( this.style_sim_obj.style.textDecoration == 'underline' ) ? true : false;
    f_basic_underline.editor = this;
    f_basic_underline.onclick = function()
    {
      this.editor.style_sim_obj.style.textDecoration = ( this.checked ) ? 'underline' : null;
      this.editor.renderPreview();
    }
    l_basic_underline.style.textDecoration = 'underline';
    l_basic_underline.appendChild(f_basic_underline);
    l_basic_underline.appendChild(document.createTextNode(' '));
    l_basic_underline.appendChild(document.createTextNode($lang.get('acpur_field_style_basic_underline')));
    
    // finish up formatting row#1
    td_basic_f.appendChild(l_basic_bold);
    td_basic_f.appendChild(document.createTextNode(' '));
    td_basic_f.appendChild(l_basic_italic);
    td_basic_f.appendChild(document.createTextNode(' '));
    td_basic_f.appendChild(l_basic_underline);
    
    tr_basic.appendChild(td_basic_l);
    tr_basic.appendChild(td_basic_f);
    table.appendChild(tr_basic);
    
    // row: rank color
    var tr_color = document.createElement('tr');
    var td_color_l = document.createElement('td');
    var td_color_f = document.createElement('td');
    
    td_color_l.className = td_color_f.className = 'row1';
    
    td_color_l.appendChild(document.createTextNode($lang.get('acpur_field_style_color')));
    
    // field: rank color
    var f_rank_color = document.createElement('input');
    f_rank_color.type = 'text';
    f_rank_color.size = '7';
    f_rank_color.value = ( this.editing ) ? this.rgb2hex(this.style_sim_obj.style.color) : '';
    f_rank_color.style.backgroundColor = this.style_sim_obj.style.color;
    f_rank_color.editor = this;
    this.f_rank_color = f_rank_color;
    f_rank_color.onkeyup = function(e)
    {
      if ( !e.keyCode )
        e = window.event;
      if ( !e )
        return false;
      var chr = (String.fromCharCode(e.keyCode)).toLowerCase();
      this.value = this.value.replace(/[^a-fA-F0-9]/g, '');
      if ( this.value.length > 6 )
      {
        this.value = this.value.substr(0, 6);
      }
      if ( this.value.length == 6 || this.value.length == 3 )
      {
        this.style.backgroundColor = '#' + this.value;
        this.editor.style_sim_obj.style.color = '#' + this.value;
        this.style.color = '#' + this.editor.determineLightness(this.value);
        this.editor.renderPreview();
      }
      else if ( this.value.length == 0 )
      {
        this.style.backgroundColor = null;
        this.editor.style_sim_obj.style.color = null;
        this.editor.renderPreview();
      }
    }
    td_color_f.appendChild(f_rank_color);
    
    tr_color.appendChild(td_color_l);
    tr_color.appendChild(td_color_f);
    table.appendChild(tr_color);
    
    // field: additional CSS
    var tr_css = document.createElement('tr');
    
    var td_css_l = document.createElement('td');
    td_css_l.className = 'row2';
    td_css_l.appendChild(document.createTextNode($lang.get('acpur_field_style_css')));
    tr_css.appendChild(td_css_l);
    
    var td_css_f = document.createElement('td');
    td_css_f.className = 'row2';
    var f_css = document.createElement('input');
    f_css.type = 'text';
    f_css.value = this.stripBasicCSSAttributes(this.rankdata.rank_style);
    f_css.style.width = '98%';
    f_css.editor = this;
    f_css.onkeyup = function()
    {
      if ( !(trim(this.value)).match(/^((([a-z-]+):(.+?);)+)?$/) )
        return;
      var newcss = this.editor.stripExtendedCSSAttributes(String(this.editor.style_sim_obj.getAttribute('style'))) + ' ' + this.value;
      this.editor.preview_div.setAttribute('style', 'font-size: x-large; ' + newcss);
      this.editor.style_sim_obj.setAttribute('style', newcss);
    }
    this.f_css = f_css;
    td_css_f.appendChild(f_css);
    tr_css.appendChild(td_css_f);
    table.appendChild(tr_css);
    
    // "field": preview
    var tr_preview = document.createElement('tr');
    var td_preview_l = document.createElement('td');
    td_preview_l.className = 'row1';
    td_preview_l.appendChild(document.createTextNode($lang.get('acpur_field_preview')));
    tr_preview.appendChild(td_preview_l);
    
    var td_preview_f = document.createElement('td');
    td_preview_f.className = 'row1';
    var div_preview = document.createElement('a');
    this.preview_div = div_preview;
    div_preview.style.fontSize = 'x-large';
    div_preview.appendChild(document.createTextNode(''));
    div_preview.firstChild.nodeValue = ( this.editing ) ? this.rankdata.rank_title : '';
    td_preview_f.appendChild(div_preview);
    tr_preview.appendChild(td_preview_f);
    
    table.appendChild(tr_preview);
    
    // submit button
    var tr_submit = document.createElement('tr');
    var th_submit = document.createElement('th');
    th_submit.className = 'subhead';
    th_submit.setAttribute('colspan', '2');
    var btn_submit = document.createElement('input');
    btn_submit.type = 'submit';
    btn_submit.value = ( this.editing ) ? $lang.get('acpur_btn_save') : $lang.get('acpur_btn_create_submit');
    btn_submit.editor = this;
    btn_submit.style.fontWeight = 'bold';
    btn_submit.onclick = function(e)
    {
      this.editor.submitEvent(e);
    }
    this.btn_submit = btn_submit;
    th_submit.appendChild(btn_submit);
    
    // delete button
    if ( this.editing )
    {
      var btn_delete = document.createElement('input');
      btn_delete.type = 'button';
      btn_delete.value = $lang.get('acpur_btn_delete');
      btn_delete.editor = this;
      btn_delete.onclick = function(e)
      {
        this.editor.deleteEvent(e);
      }
      th_submit.appendChild(document.createTextNode(' '));
      th_submit.appendChild(btn_delete);
    }
    
    tr_submit.appendChild(th_submit);
    
    table.appendChild(tr_submit);
    
    // render preview
    this.renderPreview();
    
    // finalize the editor table
    editor.appendChild(table);
    
    // stash rendered editor
    this.editordiv = editor;
    
    // send output
    return editor;
  }
  
  /**
   * Takes the existing editor div and transforms the necessary elements so that it goes from "create" mode to "edit" mode
   * @param object Edit data - same format as the rankdata parameter to the constructor, but we should only need rank_id
   */
  
  this.transformToEditor = function(rankdata)
  {
    // we need a rank ID
    if ( typeof(rankdata.rank_id) != 'number' )
      return false;
    
    if ( this.editing )
      return false;
    
    this.editing = true;
    
    this.rankdata = rankdata;
    this.rankdata.rank_title = this.f_rank_title.value;
    this.rankdata.rank_style = this.getCSS();
    
    // transform various controls
    this.th_head.firstChild.nodeValue = $lang.get('acpur_th_edit_rank', {
        rank_title: $lang.get(this.rankdata.rank_title)
      });
    this.btn_submit.value = $lang.get('acpur_btn_save');
    
    // add the delete button
    var th_submit = this.btn_submit.parentNode;
    
    var btn_delete = document.createElement('input');
    btn_delete.type = 'button';
    btn_delete.value = $lang.get('acpur_btn_delete');
    btn_delete.editor = this;
    btn_delete.onclick = function(e)
    {
      this.editor.deleteEvent(e);
    }
    th_submit.appendChild(document.createTextNode(' '));
    th_submit.appendChild(btn_delete);
    
    return true;
  }
  
  /**
   * Takes a hex color, averages the three channels, and returns either 'ffffff' or '000000' depending on the luminosity of the color.
   * @param string
   * @return string
   */
  
  this.determineLightness = function(hexval)
  {
    var rgb = this.hex2rgb(hexval);
    var lumin = ( rgb[0] + rgb[1] + rgb[2] ) / 3;
    return ( lumin > 60 ) ? '000000' : 'ffffff';
  }
  
  /**
   * Strips out basic CSS attributes (color, font-weight, font-style, text-decoration) from a snippet of CSS.
   * @param string
   * @return string
   */
  
  this.stripBasicCSSAttributes = function(css)
  {
    return trim(css.replace(/(color|font-weight|font-style|text-decoration): ?([A-z0-9# ,\(\)]+);/g, ''));
  }
  
  /**
   * Strips out all but basic CSS attributes.
   * @param string
   * @return string
   */
  
  this.stripExtendedCSSAttributes = function(css)
  {
    var match;
    var final_css = '';
    var basics = ['color', 'font-weight', 'font-style', 'text-decoration'];
    while ( match = css.match(/([a-z-]+):(.+?);/) )
    {
      if ( in_array(match[1], basics) )
      {
        final_css += ' ' + match[0] + ' ';
      }
      css = css.replace(match[0], '');
    }
    final_css = trim(final_css);
    return final_css;
  }
  
  this.getCSS = function()
  {
    return this.style_sim_obj.getAttribute('style');
  }
  
  this.renderPreview = function()
  {
    if ( !this.preview_div )
      return false;
    var color = ( this.style_sim_obj.style.color ) ? '#' + this.rgb2hex(this.style_sim_obj.style.color) : null;
    this.preview_div.style.color = color;
    this.preview_div.style.fontWeight = this.style_sim_obj.style.fontWeight;
    this.preview_div.style.fontStyle = this.style_sim_obj.style.fontStyle;
    this.preview_div.style.textDecoration = this.style_sim_obj.style.textDecoration;
    this.preview_div.firstChild.nodeValue = $lang.get(this.f_rank_title.value);
  }
  
  this.submitEvent = function(e)
  {
    if ( this.onsubmit )
    {
      this.onsubmit(e);
    }
    else
    {
      window.console.error('RankEditorControl: no onsubmit event specified');
    }
  }
  
  this.deleteEvent = function(e)
  {
    if ( this.ondelete )
    {
      this.ondelete(e);
    }
    else
    {
      window.console.error('RankEditorControl: no ondelete event specified');
    }
  }
  
  /**
   * Converts a parenthetical color specification (rgb(x, y, z)) to hex form (xxyyzz)
   * @param string
   * @return string
   */
  
  this.rgb2hex = function(rgb)
  {
    var p = rgb.match(/^rgb\(([0-9]+), ([0-9]+), ([0-9]+)\)$/);
    if ( !p )
      return rgb.replace(/^#/, '');
    
    var r = parseInt(p[1]).toString(16), g = parseInt(p[2]).toString(16), b = parseInt(p[3]).toString(16);
    if ( r.length < 2 )
      r = '0' + r;
    if ( g.length < 2 )
      g = '0' + g;
    if ( b.length < 2 )
      b = '0' + b;
    
    return r + g + b;
  }
  
  /**
   * Get red, green, and blue values for the given hex color
   * @param string
   * @return array (numbered, e.g. not an object
   */
  
  this.hex2rgb = function(hex)
  {
    hex = hex.replace(/^#/, '');
    if ( hex.length != 3 && hex.length != 6 )
    {
      return hex;
    }
    if ( hex.length == 3 )
    {
      // is there a better way to do this?
      hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
    }
    hex = [ hex.substr(0, 2), hex.substr(2, 2), hex.substr(4, 2) ];
    var red = parseInt(hex[0], 16);
    var green = parseInt(hex[1], 16);
    var blue = parseInt(hex[2], 16);
    return [red, green, blue];
  }
}

/**
 * Perform request for editable rank data and draw editor
 */

function ajaxInitRankEdit(rank_id)
{
  load_component('messagebox');
  var json_packet = {
    mode: 'get_rank',
    rank_id: rank_id
  };
  json_packet = ajaxEscape(toJSONString(json_packet));
  ajaxPost(makeUrlNS('Admin', 'UserRanks/action.json'), 'r=' + json_packet, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try
        {
          var response = parseJSON(ajax.responseText);
        }
        catch(e)
        {
          handle_invalid_json(ajax.responseText);
        }
        var editor = new RankEditorControl(response);
        editor.onsubmit = ajaxRankEditHandleSaveExisting;
        editor.ondelete = ajaxRankEditHandleDelete;
        var container = document.getElementById('admin_ranks_container_right');
        container.innerHTML = '';
        container.appendChild(editor.render());
      }
    }, true);
}

function ajaxInitRankCreate()
{
  load_component('messagebox');
  var editor = new RankEditorControl();
  editor.onsubmit = ajaxRankEditHandleSaveNew;
  var container = document.getElementById('admin_ranks_container_right');
  container.innerHTML = '';
  container.appendChild(editor.render());
}

function ajaxRankEditHandleSave(editor, switch_new)
{
  var whitey = whiteOutElement(editor.wrapperdiv);
  
  // pack it up, ...
  var json_packet = {
    mode: ( switch_new ) ? 'create_rank' : 'save_rank',
    rank_title: editor.f_rank_title.value,
    rank_style: editor.getCSS()
  }
  if ( !switch_new )
  {
    json_packet.rank_id = editor.rankdata.rank_id;
  }
  /// ... pack it in
  var json_packet = ajaxEscape(toJSONString(json_packet));
  
  ajaxPost(makeUrlNS('Admin', 'UserRanks/action.json'), 'r=' + json_packet, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try
        {
          var response = parseJSON(ajax.responseText);
        }
        catch(e)
        {
          handle_invalid_json(ajax.responseText);
        }
        if ( response.mode == 'success' )
        {
          whiteOutReportSuccess(whitey);
          if ( switch_new )
          {
            //
            // we have a few more things to do with a newly created rank.
            //
            
            // 1. transform editor
            editor.transformToEditor(response);
            editor.onsubmit = ajaxRankEditHandleSaveExisting;
            editor.ondelete = ajaxRankEditHandleDelete;
            
            // 2. append the new rank to the list
            var create_link = document.getElementById('rankadmin_createlink');
            if ( create_link )
            {
              var parent = create_link.parentNode;
              var edit_link = document.createElement('a');
              edit_link.href = '#rank_edit:' + response.rank_id;
              edit_link.className = 'rankadmin-editlink';
              edit_link.setAttribute('style', editor.getCSS());
              edit_link.id = 'rankadmin_editlink_' + response.rank_id;
              edit_link.rank_id = response.rank_id;
              edit_link.appendChild(document.createTextNode($lang.get(editor.f_rank_title.value)));
              parent.insertBefore(edit_link, create_link);
              edit_link.onclick = function()
              {
                ajaxInitRankEdit(this.rank_id);
              }
            }
          }
          else
          {
            // update the rank title on the left
            var edit_link = document.getElementById('rankadmin_editlink_' + editor.rankdata.rank_id);
            if ( edit_link )
            {
              edit_link.firstChild.nodeValue = $lang.get(editor.f_rank_title.value);
              edit_link.setAttribute('style', editor.getCSS());
            }
          }
        }
        else
        {
          whitey.parentNode.removeChild(whitey);
          miniPromptMessage({
              title: $lang.get('acpur_err_save_failed_title'),
              message: response.error,
              buttons: [
                {
                  text: $lang.get('etc_ok'),
                  color: 'red',
                  style: {
                    fontWeight: 'bold'
                  },
                  onclick: function()
                  {
                    miniPromptDestroy(this);
                  }
                }
              ]
          });
        }
      }
    }, true);
}

var ajaxRankEditHandleSaveExisting = function()
{
  ajaxRankEditHandleSave(this, false);
}

var ajaxRankEditHandleSaveNew = function()
{
  ajaxRankEditHandleSave(this, true);
}

var ajaxRankEditHandleDelete = function()
{
  var mp = miniPromptMessage({
      title: $lang.get('acpur_msg_rank_delete_confirm_title'),
      message: $lang.get('acpur_msg_rank_delete_confirm_body'),
      buttons: [
        {
          text: $lang.get('acpur_btn_delete'),
          color: 'red',
          style: {
            fontWeight: 'bold'
          },
          onclick: function()
          {
            var parent = miniPromptGetParent(this);
            var editor = parent.editor;
            setTimeout(function()
              {
                ajaxRankEditDeleteConfirmed(editor);
              }, 1000);
            miniPromptDestroy(parent);
          }
        },
        {
          text: $lang.get('etc_cancel'),
          onclick: function()
          {
            miniPromptDestroy(this);
          }
        }
      ]
    });
  console.debug(mp);
  mp.editor = this;
}

function ajaxRankEditDeleteConfirmed(editor)
{
  var whitey = whiteOutElement(editor.wrapperdiv);
  
  load_component('jquery');
  load_component('jquery-ui');
  
  var json_packet = {
    mode: 'delete_rank',
    rank_id: editor.rankdata.rank_id
  };
  var rank_id = editor.rankdata.rank_id;
  
  json_packet = ajaxEscape(toJSONString(json_packet));
  ajaxPost(makeUrlNS('Admin', 'UserRanks/action.json'), 'r=' + json_packet, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(ajax.responseText);
          return false;
        }
        try
        {
          var response = parseJSON(ajax.responseText);
        }
        catch(e)
        {
          handle_invalid_json(ajax.responseText);
        }
        if ( response.mode == 'success' )
        {
          // the deletion was successful, report success and kill off the editor
          whiteOutReportSuccess(whitey);
          setTimeout(function()
            {
              // nuke the rank title on the left
              var edit_link = document.getElementById('rankadmin_editlink_' + editor.rankdata.rank_id);
              if ( edit_link )
              {
                edit_link.parentNode.removeChild(edit_link);
              }
              // collapse and destroy the editor
              $(editor.wrapperdiv).hide("blind", {}, 500, function()
                  {
                    // when the animation finishes, nuke the whole thing
                    var container = document.getElementById('admin_ranks_container_right');
                    container.innerHTML = $lang.get('acpur_msg_select_rank');
                  }
                );
            }, 1500);
        }
        else
        {
          whitey.parentNode.removeChild(whitey);
          miniPromptMessage({
              title: $lang.get('acpur_err_delete_failed_title'),
              message: response.error,
              buttons: [
                {
                  text: $lang.get('etc_ok'),
                  color: 'red',
                  style: {
                    fontWeight: 'bold'
                  },
                  onclick: function()
                  {
                    miniPromptDestroy(this);
                  }
                }
              ]
          });
        }
      }
    }, true);
}
