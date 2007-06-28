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

function DN_switchToMCE()
{
  //if ( this.object.dn_is_mce )
  //  return this;
  if ( !this.object.id )
    this.object.id = 'textarea_' + Math.floor(Math.random() * 1000000);
  if ( !this.object.name )
    this.object.name = 'textarea_' + Math.floor(Math.random() * 1000000);
  tinyMCE.addMCEControl(this.object, this.object.name, document);
  this.object.dnIsMCE = 'yes';
  return this;
}

function DN_destroyMCE()
{
  //if ( !this.object.dn_is_mce )
  //  return this;
  if ( this.object.name )
    tinyMCE.removeMCEControl(this.object.name);
  this.object.dnIsMCE = 'no';
  return this;
}

function DN_mceFetchContent()
{
  if ( this.object.name )
  {
    var text = this.object.value;
    if ( tinyMCE.getInstanceById(this.object.name) )
      text = tinyMCE.getContent(this.object.name);
    return text;
  }
  else
  {
    return this.object.value;
  }
}

DNobj.prototype.addClass = function(clsname) { addClass(this.object, clsname); return this; };
DNobj.prototype.rmClass  = function(clsname) { rmClass( this.object, clsname); return this; };
DNobj.prototype.hasClass = function(clsname) { return hasClass(this.object, clsname); };
DNobj.prototype.Height   = function()        { return __DNObjGetHeight(this.object); }
DNobj.prototype.Width    = function()        { return __DNObjGetWidth( this.object); }
DNobj.prototype.Left     = function()        { /* return this.object.offsetLeft; */ return __DNObjGetLeft(this.object); }
DNobj.prototype.Top      = function()        { /* return this.object.offsetTop;  */ return __DNObjGetTop( this.object); }

