// The "Dynano" Javascript framework. Similar in syntax to JQuery but highly Enano-specific (TinyMCE, etc).

var $dynano = function(id)
{
  return new DNobj(id);
}
function DNobj(id)
{
  if ( id == undefined )
  {
    return {};
  }
  this.object = ( typeof(id) == 'object' ) ? id : document.getElementById(id);
  if ( !this.object )
  {
    console.warn('Dynano: requested object is bad. id parameter follows.');
    console.debug(id);
    this.object = false;
    return this;
  }
  if ( this.object.Dynano )
  {
    return this.object.Dynano;
  }
  this.object.Dynano = this;
  
  this.height = __DNObjGetHeight(this.object);
  this.width = __DNObjGetWidth(this.object);
  
  if ( this.object.tagName == 'TEXTAREA' && ( typeof(tinyMCE) == 'object' || typeof(tinyMCE_GZ) == 'object' ) )
  {
    this.object.dnIsMCE = 'no';
    this.switchToMCE = DN_switchToMCE;
    this.destroyMCE = DN_destroyMCE;
    this.getContent = DN_mceFetchContent;
    this.setContent = DN_mceSetContent;
    this.makeSwitchable = DN_makeSwitchableTA;
    this.isMCE = DN_isMCE;
  }
}
function __DNObjGetHeight(o) {
  return o.offsetHeight;
}

function __DNObjGetWidth(o) {
  return o.offsetWidth;
}

function addClass(obj, clsname)
{
  var cnt = obj.className;
  var space = ( (cnt + '').length > 0 ) ? ' ' : '';
  var cls = cnt + space + clsname;
  obj.className = cls;
}

function rmClass(obj, clsname)
{
  var cnt = obj.className;
  if ( cnt == clsname )
  {
    obj.className = '';
  }
  else
  {
    cnt = cnt.replace(clsname, '');
    cnt = trim(cnt);
    obj.className = cnt;
  }
}

function hasClass(obj, clsname)
{
  var cnt = obj.className;
  if ( !cnt )
    return false;
  if ( cnt == clsname )
    return true;
  cnt = cnt.split(' ');
  
  for ( var i in cnt )
    if ( cnt[i] == clsname )
      return true;
    
  return false;
}
function __DNObjGetLeft(obj) {
  var left_offset = obj.offsetLeft;
  while ((obj = obj.offsetParent) != null) {
    left_offset += obj.offsetLeft;
  }
  return left_offset;
}

function __DNObjGetTop(obj) {
  var left_offset = obj.offsetTop;
  while ((obj = obj.offsetParent) != null) {
    left_offset += obj.offsetTop;
  }
  return left_offset;
}

function DN_switchToMCE(performWikiTransform)
{
  if ( !this.object.id )
    this.object.id = 'textarea_' + Math.floor(Math.random() * 1000000);
  if ( !this.object.name )
    this.object.name = 'textarea_' + Math.floor(Math.random() * 1000000);
  // Updated for TinyMCE 3.x
  if ( performWikiTransform )
  {
    this.object.value = DN_WikitextToXHTML(this.object.value);
  }
  // If tinyMCE init hasn't been called yet, do it now.
  if ( !tinymce_initted )
  {
    console.info('$dynano().switchToMCE(): doing "exact"-type MCE init');
    enano_tinymce_options.mode = 'exact';
    enano_tinymce_options.elements = this.object.id;
    initTinyMCE();
    this.object.dnIsMCE = 'yes';
    return true;
  }
  else
  {
    console.info('$dynano().switchToMCE(): tinyMCE already loaded, calling mceAddControl');
    tinymce.EditorManager.execCommand("mceAddControl", true, this.object.id);
    this.object.dnIsMCE = 'yes';
  }
  return this;
}

function DN_destroyMCE(performWikiTransform)
{
  //if ( !this.object.dn_is_mce )
  //  return this;
  if ( this.object.id && window.tinymce )
  {
    // TinyMCE 2.x
    // tinymce.EditorManager.removeMCEControl(this.object.name);
    // TinyMCE 3.x
    var ed = tinymce.EditorManager.getInstanceById(this.object.id);
    if ( ed )
    {
      if ( !tinymce.EditorManager.execCommand("mceRemoveEditor", false, this.object.id) )
        alert('could not destroy editor');
      if ( performWikiTransform )
      {
        this.object.value = DN_XHTMLToWikitext(this.object.value);
      }
    }
  }
  this.object.dnIsMCE = 'no';
  return this;
}

function DN_isMCE()
{
  return ( this.object.dnIsMCE == 'yes' );
}

function DN_mceFetchContent()
{
  if ( this.object.name )
  {
    var text = this.object.value;
    if ( tinymce.EditorManager.get(this.object.id) )
    {
      var editor = tinymce.EditorManager.get(this.object.id);
      text = editor.getContent();
    }
    return text;
  }
  else
  {
    return this.object.value;
  }
}

function DN_mceSetContent(text)
{
  if ( this.object.name )
  {
    this.object.value = text;
    if ( tinymce.EditorManager.get(this.object.id) )
    {
      var editor = tinymce.EditorManager.get(this.object.id);
      editor.setContent(text);
    }
  }
  else
  {
    this.object.value = text;
  }
}

var P_BOTTOM = 1;
var P_TOP = 2;

function DN_makeSwitchableTA(pos)
{
  if ( this.toggler )
    return false;
  
  if ( !pos )
    pos = P_BOTTOM;
  
  load_component('l10n');
  var cookiename = 'enano_editor_mode';
  
  var toggler = document.createElement('div');
  toggler.dynano = this;
  this.toggler = toggler;
  
  if ( !this.object.id )
    this.object.id = 'dynano_auto_' + Math.floor(Math.random() * 1000000);
  
  toggler.s_mode_text = $lang.get('editor_btn_wikitext');
  toggler.s_mode_graphical = $lang.get('editor_btn_graphical');
  
  toggler.set_text = function()
  {
    if ( this.dynano.object.dnIsMCE == 'yes' )
      this.dynano.destroyMCE();
    
    this.innerHTML = '';
    this.appendChild(document.createTextNode(this.s_mode_text + ' | '));
    
    var link = document.createElement('a');
    link.href = '#';
    link.onclick = function()
    {
      this.parentNode.set_graphical();
      return false;
    }
    link.appendChild(document.createTextNode(this.s_mode_graphical));
    this.appendChild(link);
    
    createCookie('enano_editor_mode', 'text', 365);
  }
  
  toggler.set_graphical = function()
  {
    this.dynano.switchToMCE();
    this.innerHTML = '';
    
    var link = document.createElement('a');
    link.href = '#';
    link.onclick = function()
    {
      this.parentNode.set_text();
      return false;
    }
    link.appendChild(document.createTextNode(this.s_mode_text));
    this.appendChild(link);
    
    this.appendChild(document.createTextNode(' | ' + this.s_mode_graphical));
    createCookie('enano_editor_mode', 'tinymce', 365);
  }
  
  toggler.style.styleFloat = 'right';
  toggler.style.cssFloat = 'right';
  if ( pos == P_BOTTOM )
  {
    insertAfter(this.object.parentNode, toggler, this.object);
  }
  else
  {
    this.object.parentNode.insertBefore(toggler, this.object);
  }
  
  if ( readCookie(cookiename) == 'tinymce' )
  {
    toggler.set_graphical();
  }
  else
  {
    toggler.set_text();
  }
}

function DN_WikitextToXHTML(text)
{
  return DN_AjaxGetTransformedText(text, 'xhtml');
}

function DN_XHTMLToWikitext(text)
{
  return DN_AjaxGetTransformedText(text, 'wikitext');
}

// AJAX to the server to transform text
function DN_AjaxGetTransformedText(text, to)
{
  // get an XHR instance
  var ajax = ajaxMakeXHR();
  
  var uri = stdAjaxPrefix + '&_mode=transform&to=' + to;
  var parms = 'text=' + ajaxEscape(text);
  try
  {
    ajax.open('POST', uri, false);
    ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    // Setting Content-length in Safari triggers a warning
    if ( !is_Safari )
    {
      ajax.setRequestHeader("Content-length", parms.length);
    }
    ajax.send(parms);
    // async request, so if status != 200 at this point then we're screwed
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      var response = String(ajax.responseText + '');
      if ( !check_json_response(response) )
      {
        handle_invalid_json(response);
        return text;
      }
      response = parseJSON(response);
      if ( response.mode == 'error' )
      {
        alert(response.error);
        return text;
      }
      return response.text;
    }
  }
  catch(e)
  {
    console.warn('DN_AjaxGetTransformedText: XHR failed');
  }
  return text;
}

DNobj.prototype.addClass = function(clsname) { addClass(this.object, clsname); return this; };
DNobj.prototype.rmClass  = function(clsname) { rmClass( this.object, clsname); return this; };
DNobj.prototype.hasClass = function(clsname) { return hasClass(this.object, clsname); };
DNobj.prototype.Height   = function()        { return __DNObjGetHeight(this.object); }
DNobj.prototype.Width    = function()        { return __DNObjGetWidth( this.object); }
DNobj.prototype.Left     = function()        { /* return this.object.offsetLeft; */ return __DNObjGetLeft(this.object); }
DNobj.prototype.Top      = function()        { /* return this.object.offsetTop;  */ return __DNObjGetTop( this.object); }

