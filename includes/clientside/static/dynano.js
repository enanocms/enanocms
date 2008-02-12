// The "Dynano" Javascript framework. Similar in syntax to JQuery but only has what Enano needs.

var $ = function(id)
{
  return new DNobj(id);
}
var $dynano = $;
function DNobj(id)
{
  this.object = ( typeof(id) == 'object' ) ? id : document.getElementById(id);
  if ( !this.object )
  {
    this.object = false;
    return this;
  }
  this.height = __DNObjGetHeight(this.object);
  this.width = __DNObjGetWidth(this.object);
  
  if ( this.object.tagName == 'TEXTAREA' && typeof(tinyMCE) == 'object' )
  {
    this.object.dnIsMCE = 'no';
    this.switchToMCE = DN_switchToMCE;
    this.destroyMCE = DN_destroyMCE;
    this.getContent = DN_mceFetchContent;
    this.setContent = DN_mceSetContent;
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
  //if ( this.object.dn_is_mce )
  //  return this;
  if ( !this.object.id )
    this.object.id = 'textarea_' + Math.floor(Math.random() * 1000000);
  if ( !this.object.name )
    this.object.name = 'textarea_' + Math.floor(Math.random() * 1000000);
  // TinyMCE 2.x
  // tinyMCE.addMCEControl(this.object, this.object.name, document);
  // TinyMCE 3.x
  if ( performWikiTransform )
  {
    this.object.value = DN_WikitextToXHTML(this.object.value);
  }
  tinyMCE.execCommand("mceAddControl", true, this.object.id);
  this.object.dnIsMCE = 'yes';
  return this;
}

function DN_destroyMCE(performWikiTransform)
{
  //if ( !this.object.dn_is_mce )
  //  return this;
  if ( this.object.id )
  {
    // TinyMCE 2.x
    // tinyMCE.removeMCEControl(this.object.name);
    // TinyMCE 3.x
    var ed = tinyMCE.getInstanceById(this.object.id);
    if ( ed )
    {
      if ( !tinyMCE.execCommand("mceRemoveEditor", false, this.object.id) )
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

function DN_mceFetchContent()
{
  if ( this.object.name )
  {
    var text = this.object.value;
    if ( tinyMCE.get(this.object.id) )
    {
      var editor = tinyMCE.get(this.object.id);
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
    if ( tinyMCE.get(this.object.id) )
    {
      var editor = tinyMCE.get(this.object.id);
      editor.setContent(text);
    }
  }
  else
  {
    this.object.value = text;
  }
}

// A basic Wikitext to XHTML converter
function DN_WikitextToXHTML(text)
{
  text = text.replace(/^===[\s]*(.+?)[\s]*===$/g, '<h3>$1</h3>');
  text = text.replace(/'''(.+?)'''/g, '<b>$1</b>');
  text = text.replace(/''(.+?)''/g, '<i>$1</i>');
  text = text.replace(/\[(http|ftp|irc|mailto):([^ \]])+ ([^\]]+?)\]/g, '<a href="$1:$2">$4</a>');
  return text;
}

// Inverse of the previous function
function DN_XHTMLToWikitext(text)
{
  text = text.replace(/<h3>(.+?)<\/h3>/g, '=== $1 ===');
  text = text.replace(/<(b|strong)>(.+?)<\/(b|strong)>/g, "'''$2'''");
  text = text.replace(/<(i|em)>(.+?)<\/(i|em)>/g, "''$2''");
  text = text.replace(/<a href="([^" ]+)">(.+?)<\/a>/g, '[$1 $2]');
  text = text.replace(/<\/?p>/g, '');
  return text;
}

DNobj.prototype.addClass = function(clsname) { addClass(this.object, clsname); return this; };
DNobj.prototype.rmClass  = function(clsname) { rmClass( this.object, clsname); return this; };
DNobj.prototype.hasClass = function(clsname) { return hasClass(this.object, clsname); };
DNobj.prototype.Height   = function()        { return __DNObjGetHeight(this.object); }
DNobj.prototype.Width    = function()        { return __DNObjGetWidth( this.object); }
DNobj.prototype.Left     = function()        { /* return this.object.offsetLeft; */ return __DNObjGetLeft(this.object); }
DNobj.prototype.Top      = function()        { /* return this.object.offsetTop;  */ return __DNObjGetTop( this.object); }

