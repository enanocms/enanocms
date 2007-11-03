/**
 * Javascript auto-completion for form fields.
 */
 
var af_current = false;
 
function AutofillUsername(parent, event, allowanon)
{
  // if this is IE, use the old code
  if ( IE )
  {
    ajaxUserNameComplete(parent);
    return false;
  }
  if ( parent.afobj )
  {
    parent.afobj.go();
    return true;
  }
  
  parent.autocomplete = 'off';
  parent.setAttribute('autocomplete', 'off');
  
  this.repeat = false;
  this.event = event;
  this.box_id = false;
  this.boxes = new Array();
  this.state = false;
  this.allowanon = ( allowanon ) ? true : false;
  
  if ( !parent.id )
    parent.id = 'afuser_' + Math.floor(Math.random() * 1000000);
  
  this.field_id = parent.id;
  
  // constants
  this.KEY_UP    = 38;
  this.KEY_DOWN  = 40;
  this.KEY_ESC   = 27;
  this.KEY_TAB   = 9;
  this.KEY_ENTER = 13;
  
  // response cache
  this.responses = new Object();
  
  // ajax placeholder
  this.process_dataset = function(resp_json)
  {
    // window.console.info('Processing the following dataset.');
    // window.console.debug(resp_json);
    var autofill = this;
    
    if ( typeof(autofill.event) == 'object' )
    {
      if ( autofill.event.keyCode )
      {
        if ( autofill.event.keyCode == autofill.KEY_ENTER && autofill.boxes.length < 1 && !autofill.box_id )
        {
          // user hit enter after accepting a suggestion - submit the form
          var frm = findParentForm($(autofill.field_id).object);
          frm._af_acting = false;
          frm.submit();
          // window.console.info('Submitting form');
          return false;
        }
        if ( autofill.event.keyCode == autofill.KEY_UP || autofill.event.keyCode == autofill.KEY_DOWN || autofill.event.keyCode == autofill.KEY_ESC || autofill.event.keyCode == autofill.KEY_TAB || autofill.event.keyCode == autofill.KEY_ENTER )
        {
          autofill.keyhandler();
          // window.console.info('Control key detected, called keyhandler and exiting');
          return true;
        }
      }
    }
    
    if ( this.box_id )
    {
      this.destroy();
      // window.console.info('already have a box open - destroying and exiting');
      //return false;
    }
    
    var users = new Array();
    for ( var i = 0; i < resp_json.users_real.length; i++ )
    {
      try
      {
        var user = resp_json.users_real[i].toLowerCase();
        var inp  = $(autofill.field_id).object.value;
        inp = inp.toLowerCase();
        if ( user.indexOf(inp) > -1 )
        {
          users.push(resp_json.users_real[i]);
        }
      }
      catch(e)
      {
        users.push(resp_json.users_real[i]);
      }
    }

    // This was used ONLY for debugging the DOM and list logic    
    // resp_json.users = resp_json.users_real;
    
    // construct table
    var div = document.createElement('div');
    div.className = 'tblholder';
    div.style.clip = 'rect(0px,auto,auto,0px)';
    div.style.maxHeight = '200px';
    div.style.overflow = 'auto';
    div.style.zIndex = '9999';
    var table = document.createElement('table');
    table.border = '0';
    table.cellSpacing = '1';
    table.cellPadding = '3';
    
    var tr = document.createElement('tr');
    var th = document.createElement('th');
    th.appendChild(document.createTextNode('Username suggestions'));
    tr.appendChild(th);
    table.appendChild(tr);
    
    if ( users.length < 1 )
    {
      var tr = document.createElement('tr');
      var td = document.createElement('td');
      td.className = 'row1';
      td.appendChild(document.createTextNode('No suggestions'));
      td.afobj = autofill;
      tr.appendChild(td);
      table.appendChild(tr);
    }
    else
      
      for ( var i = 0; i < users.length; i++ )
      {
        var user = users[i];
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        td.className = ( i == 0 ) ? 'row2' : 'row1';
        td.appendChild(document.createTextNode(user));
        td.afobj = autofill;
        td.style.cursor = 'pointer';
        td.onclick = function()
        {
          this.afobj.set(this.firstChild.nodeValue);
        }
        tr.appendChild(td);
        table.appendChild(tr);
      }
      
    // Finalize div
    var tb_top    = $(autofill.field_id).Top();
    var tb_height = $(autofill.field_id).Height();
    var af_top    = tb_top + tb_height - 9;
    var tb_left   = $(autofill.field_id).Left();
    var af_left   = tb_left;
    
    div.style.position = 'absolute';
    div.style.left = af_left + 'px';
    div.style.top  = af_top  + 'px';
    div.style.width = '200px';
    div.style.fontSize = '7pt';
    div.style.fontFamily = 'Trebuchet MS, arial, helvetica, sans-serif';
    div.id = 'afuserdrop_' + Math.floor(Math.random() * 1000000);
    div.appendChild(table);
    
    autofill.boxes.push(div.id);
    autofill.box_id = div.id;
    if ( users.length > 0 )
      autofill.state = users[0];
    
    var body = document.getElementsByTagName('body')[0];
    body.appendChild(div);
    
    autofill.repeat = true;
  }
  
  // perform ajax call
  this.fetch_and_process = function()
  {
    af_current = this;
    var processResponse = function()
    {
      if ( ajax.readyState == 4 )
      {
        var afobj = af_current;
        af_current = false;
        // parse the JSON response
        var response = String(ajax.responseText) + ' ';
        if ( response.substr(0,1) != '{' )
        {
          new messagebox(MB_OK|MB_ICONSTOP, 'Invalid response', 'Invalid or unexpected JSON response from server:<pre>' + ajax.responseText + '</pre>');
          return false;
        }
        if ( $(afobj.field_id).object.value.length < 3 )
          return false;
        var resp_json = parseJSON(response);
        var resp_code = $(afobj.field_id).object.value.toLowerCase().substr(0, 3);
        afobj.responses[resp_code] = resp_json;
        afobj.process_dataset(resp_json);
      }
    }
    var usernamefragment = ajaxEscape($(this.field_id).object.value);
    ajaxGet(stdAjaxPrefix + '&_mode=fillusername&name=' + usernamefragment + '&allowanon=' + ( this.allowanon ? '1' : '0' ), processResponse);
  }
  
  this.go = function()
  {
    if ( document.getElementById(this.field_id).value.length < 3 )
    {
      this.destroy();
      return false;
    }
    
    if ( af_current )
      return false;
    
    var resp_code = $(this.field_id).object.value.toLowerCase().substr(0, 3);
    if ( this.responses.length < 1 || ! this.responses[ resp_code ] )
    {
      // window.console.info('Cannot find dataset ' + resp_code + ' in cache, sending AJAX request');
      this.fetch_and_process();
    }
    else
    {
      // window.console.info('Using cached dataset: ' + resp_code);
      var resp_json = this.responses[ resp_code ];
      this.process_dataset(resp_json);
    }
    document.getElementById(this.field_id).onkeyup = function(event)
    {
      this.afobj.event = event;
      this.afobj.go();
    }
    document.getElementById(this.field_id).onkeydown = function(event)
    {
      var form = findParentForm(this);
      if ( typeof(event) != 'object' )
        var event = window.event;
      if ( typeof(event) == 'object' )
      {
        if ( event.keyCode == this.afobj.KEY_ENTER && this.afobj.boxes.length < 1 && !this.afobj.box_id )
        {
          // user hit enter after accepting a suggestion - submit the form
          form._af_acting = false;
          return true;
        }
      }
      form._af_acting = true;
    }
  }
  
  this.keyhandler = function()
  {
    var key = this.event.keyCode;
    if ( key == this.KEY_ENTER && !this.repeat )
    {
      var form = findParentForm($(this.field_id).object);
        form._af_acting = false;
      return true;
    }
    switch(key)
    {
      case this.KEY_UP:
        this.focus_up();
        break;
      case this.KEY_DOWN:
        this.focus_down();
        break;
      case this.KEY_ESC:
        this.destroy();
        break;
      case this.KEY_TAB:
        this.destroy();
        break;
      case this.KEY_ENTER:
        this.set();
        break;
    }
    
    var form = findParentForm($(this.field_id).object);
      form._af_acting = false;
  }
  
  this.get_state_td = function()
  {
    var div = document.getElementById(this.box_id);
    if ( !div )
      return false;
    if ( !this.state )
      return false;
    var table = div.firstChild;
    for ( var i = 1; i < table.childNodes.length; i++ )
    {
      // the table is DOM-constructed so no cruddy HTML hacks :-)
      var child = table.childNodes[i];
      var tn = child.firstChild.firstChild;
      if ( tn.nodeValue == this.state )
        return child.firstChild;
    }
    return false;
  }
  
  this.focus_down = function()
  {
    var state_td = this.get_state_td();
    if ( !state_td )
      return false;
    if ( state_td.parentNode.nextSibling )
    {
      // Ooh boy, DOM stuff can be so complicated...
      // <tr>  -->  <tr>
      // <td>       <td>
      // user       user
      
      var newstate = state_td.parentNode.nextSibling.firstChild.firstChild.nodeValue;
      if ( !newstate )
        return false;
      this.state = newstate;
      state_td.className = 'row1';
      state_td.parentNode.nextSibling.firstChild.className = 'row2';
      
      // Exception - automatically scroll around if the item is off-screen
      var height = $(this.box_id).Height();
      var top = $(this.box_id).object.scrollTop;
      var scroll_bottom = height + top;
      
      var td_top = $(state_td.parentNode.nextSibling.firstChild).Top() - $(this.box_id).Top();
      var td_height = $(state_td.parentNode.nextSibling.firstChild).Height();
      var td_bottom = td_top + td_height;
      
      if ( td_bottom > scroll_bottom )
      {
        var scrollY = td_top - height + 2*td_height - 7;
        // window.console.debug(scrollY);
        $(this.box_id).object.scrollTop = scrollY;
        /*
        var newtd = state_td.parentNode.nextSibling.firstChild;
        var a = document.createElement('a');
        var id = 'autofill' + Math.floor(Math.random() * 100000);
        a.name = id;
        a.id = id;
        newtd.appendChild(a);
        window.location.hash = '#' + id;
        */
        
        // In firefox, scrolling like that makes the field get unfocused
        $(this.field_id).object.focus();
      }
    }
    else
    {
      return false;
    }
  }
  
  this.focus_up = function()
  {
    var state_td = this.get_state_td();
    if ( !state_td )
      return false;
    if ( state_td.parentNode.previousSibling && state_td.parentNode.previousSibling.firstChild.tagName != 'TH' )
    {
      // Ooh boy, DOM stuff can be so complicated...
      // <tr>  <--  <tr>
      // <td>       <td>
      // user       user
      
      var newstate = state_td.parentNode.previousSibling.firstChild.firstChild.nodeValue;
      if ( !newstate )
      {
        return false;
      }
      this.state = newstate;
      state_td.className = 'row1';
      state_td.parentNode.previousSibling.firstChild.className = 'row2';
      
      // Exception - automatically scroll around if the item is off-screen
      var top = $(this.box_id).object.scrollTop;
      
      var td_top = $(state_td.parentNode.previousSibling.firstChild).Top() - $(this.box_id).Top();
      
      if ( td_top < top )
      {
        $(this.box_id).object.scrollTop = td_top - 10;
        /*
        var newtd = state_td.parentNode.previousSibling.firstChild;
        var a = document.createElement('a');
        var id = 'autofill' + Math.floor(Math.random() * 100000);
        a.name = id;
        a.id = id;
        newtd.appendChild(a);
        window.location.hash = '#' + id;
        */
        
        // In firefox, scrolling like that makes the field get unfocused
        $(this.field_id).object.focus();
      }
    }
    else
    {
      $(this.box_id).object.scrollTop = 0;
      return false;
    }
  }
  
  this.destroy = function()
  {
    this.repeat = false;
    var body = document.getElementsByTagName('body')[0];
    var div = document.getElementById(this.box_id);
    if ( !div )
      return false;
    setTimeout('var body = document.getElementsByTagName("body")[0]; body.removeChild(document.getElementById("'+div.id+'"));', 20);
    // hackish workaround for divs that stick around past their welcoming period
    for ( var i = 0; i < this.boxes.length; i++ )
    {
      var div = document.getElementById(this.boxes[i]);
      if ( div )
        setTimeout('var body = document.getElementsByTagName("body")[0]; var div = document.getElementById("'+div.id+'"); if ( div ) body.removeChild(div);', 20);
      delete(this.boxes[i]);
    }
    this.box_id = false;
    this.state = false;
  }
  
  this.set = function(val)
  {
    var ta = document.getElementById(this.field_id);
    if ( val )
      ta.value = val;
    else if ( this.state )
      ta.value = this.state;
    this.destroy();
  }
  
  this.sleep = function()
  {
    if ( this.box_id )
    {
      var div = document.getElementById(this.box_id);
      div.style.display = 'none';
    }
    var el = $(this.field_id).object;
    var fr = findParentForm(el);
    el._af_acting = false;
  }
  
  this.wake = function()
  {
    if ( this.box_id )
    {
      var div = document.getElementById(this.box_id);
      div.style.display = 'block';
    }
  }
  
  parent.onblur = function()
  {
    af_current = this.afobj;
    window.setTimeout('if ( af_current ) af_current.sleep(); af_current = false;', 50);
  }
  
  parent.onfocus = function()
  {
    af_current = this.afobj;
    window.setTimeout('if ( af_current ) af_current.wake(); af_current = false;', 50);
  }
  
  parent.afobj = this;
  var frm = findParentForm(parent);
  if ( frm.onsubmit )
  {
    frm.orig_onsubmit = frm.onsubmit;
    frm.onsubmit = function(e)
    {
      if ( this._af_acting )
        return false;
      this.orig_onsubmit(e);
    }
  }
  else
  {
    frm.onsubmit = function()
    {
      if ( this._af_acting )
        return false;
    }
  }
  
  if ( parent.value.length < 3 )
  {
    this.destroy();
    return false;
  }
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

