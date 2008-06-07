/**
 * Creates a control that can be used to edit a rank.
 */

var RankEditorControl = function(rankdata)
{
  this.rankdata = rankdata;
  
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
      }
    }
    td_color_f.appendChild(f_rank_color);
    
    tr_color.appendChild(td_color_l);
    tr_color.appendChild(td_color_f);
    table.appendChild(tr_color);
    
    // finalize the editor table
    editor.appendChild(table);
    
    // stash rendered editor
    this.editordiv = editor;
    
    // send output
    return editor;
  }
  
  this.getJSONDataset = function()
  {
    
  }
  
  this.getCSS = function()
  {
    
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
}

/**
 * Perform request for editable rank data and draw editor
 */

function ajaxInitRankEdit(rank_id)
{
  var editor = new RankEditorControl({ rank_title: 'Foo', rank_id: rank_id, rank_style: 'color: #ff0000; font-weight: bold;' });
  var ren
  var container = document.getElementById('admin_ranks_container_right');
  container.innerHTML = '';
  container.appendChild(editor.render());
}
