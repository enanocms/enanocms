/*
 * Enano JWS - Javascript Windowing System
 * Sorry if I stole the name ;)
 * Copyright (C) 2006-2007 Dan Fuhry
 * Yes, it's part of Enano, so it's GPL
 */

  var position;
  function getScrollOffset()
  {
    var position;
    if (self.pageYOffset)
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
  position = getScrollOffset();
  
  var jws = {
    position : position,
    obj : null,
    startup : function() {
      jws.debug('jws.startup()');
      var divs = document.getElementsByTagName('div');
      if(IE) { position = document.body.scrollTop; }
      else   { position = window.pageYOffset; }
      for(i=0;i<divs.length;i++) {
        if(divs[i].id && divs[i].id.substr(0, 4) == 'root') {
          divs[i].onClick = 'jws.focus(\''+divs[i].id+'\')';
          var tb = i + 1
          tb = divs[tb];
          tb.innerHTML = '<table border="0" width="100%"><tr><td>' + tb.innerHTML + '</td><td align="right"><div align="center" class="closebtn" onclick="jws.closeWin(\''+divs[i].id+'\');">X</div></td></tr></table>';
          divs[i].style.width = '640px';
          divs[i].style.height = '480px';
          Drag.init(tb, divs[i]);
        }
      }
    },
    initWindow : function(o) {
      jws.debug('jws.initWindow('+o+' ['+o.id+'])');
      var divs = document.getElementsByTagName('div');
      for(i=0;i<divs.length;i++) {
        if(divs[i].id && divs[i].id == o.id) {
          var tb = i + 1
          tb = divs[tb];
          tb.innerHTML = '<table border="0" width="100%"><tr><td>' + tb.innerHTML + '</td><td align="right"><div class="closebtn" onclick="jws.closeWin(\''+divs[i].id+'\');"></div></td></tr></table>';
          divs[i].style.width = '640px';
          divs[i].style.height = '480px';
          Drag.init(tb, divs[i]);
        }
      }
    },
    closeWin : function(id) {
      jws.debug('jws.closeWin(\''+id+'\')');
      document.getElementById(id).style.display = 'none';
      enlighten();
    },
    openWin : function(id, x, y) {
      darken();
      var e = document.getElementById(id);
      if(!x) x = 640;
      if(!y) y = 480;
      jws.debug('jws.openWin(\''+id+'\', '+x+', '+y+')');
      e.style.display = 'block';
      e.style.width   = x+'px';
      e.style.height  = y+'px';
      
      var divs = document.getElementsByTagName('div');
      for(i=0;i<divs.length;i++) {
        if(divs[i].id && divs[i].id == e.id) {
          var cn = i + 3;
          cn = divs[cn];
          
          var h = getElementHeight(e.id) - 53;
          var w = getElementWidth(cn.id) - 20;
          cn.style.width   =  w + 'px';
          cn.style.height  =  h + 'px';
          cn.style.clip.top = 0 + 'px';
          cn.style.clip.left = 0 + 'px';
          cn.style.clip.right =  w + 'px';
          cn.style.clip.bottom = h + 'px';
          cn.style.overflow = 'auto';
        }
      }
      jws.setpos(id);
      jws.focus(id);
    },
    setpos : function(el) {
      jws.debug('jws.setpos(\''+el+'\')');
      el = document.getElementById(el);
      var w = getWidth();
      var h = getHeight();
      var ew = getElementWidth(el.id);
      var eh = getElementHeight(el.id);
      px = (w/2) - (ew/2);
      py = (h/2) - (eh/2);
      if (IE) { position = document.body.scrollTop; }
      else    { position = window.pageYOffset; }
      py=py+0;
      if ( IE )
        el.style.position = "absolute";
      else
        el.style.position = "fixed";
      el.style.left=px+'px';
      el.style.top =py+'px';
    },
    scrollHandler : function() {
      var divs = document.getElementsByTagName('div');
      for(i=0;i<divs.length;i++) {
        if(divs[i].id && divs[i].id.substr(0, 4) == 'root' && divs[i].style.display == 'block') {
          c = divs[i];
          jws.debug('jws.scrollHandler(): moving element: '+c.id);
          var t = c.style.top;
          var py = t.substr(0, t.length - 2);
          py = parseInt(py);
          if(jws.position) { py = py - jws.position; }
          position = getScrollOffset();
          py=py+position;                                                           
          c.style.position = "absolute";
          if(!isNaN(py)) c.style.top =py+'px';
          jws.debug('jws.scrollHandler(): value of py: '+py);
        }
      }
      jws.position = position;
    },
    focus : function(e) {
      e = document.getElementById(e);
      if(e.style.zindex) z = e.style.zindex;
      else z = 1;
      z=z+5;
      e.style.zIndex = z;
    },
    debug : function(t) {
      if(document.getElementById('jsw-debug-console')) {
        dbg = document.getElementById('jsw-debug-console');
        debugdata = dbg.innerHTML;
        dbg.innerHTML = debugdata+"<br />"+t;
      }
    }
  } // class jws

//window.onscroll=jws['scrollHandler'];

/*
 * Utility functions
 */
 
// getElementWidth() and getElementHeight()
// Source: http://www.aspandjavascript.co.uk/javascript/javascript_api/get_element_width_height.asp

function getElementHeight(Elem) {
  if (ns4) 
  {
    var elem = getObjNN4(document, Elem);
    return elem.clip.height;
  } 
  else
  {
    if(document.getElementById) 
    {
      var elem = document.getElementById(Elem);
    }
    else if (document.all)
    {
      var elem = document.all[Elem];
    }
    if (op5) 
    { 
      xPos = elem.style.pixelHeight;
    }
    else
    {
      xPos = elem.offsetHeight;
    }
    return xPos;
  } 
}

function getElementWidth(Elem) {
  if (ns4) {
    var elem = getObjNN4(document, Elem);
    return elem.clip.width;
  } else {
    if(document.getElementById) {
      var elem = document.getElementById(Elem);
    } else if (document.all){
      var elem = document.all[Elem];
    }
    if (op5) {
      xPos = elem.style.pixelWidth;
    } else {
      xPos = elem.offsetWidth;
    }
    return xPos;
  }
}

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

/**************************************************
 * dom-drag.js
 * 09.25.2001
 * www.youngpup.net
 * The original version of this code is in the
 * public domain. We have relicensed this modified
 * version under the GPL version 2 or later for
 * Enano.
 **************************************************/

var Drag = {

  obj : null,

  init : function(o, oRoot, minX, maxX, minY, maxY, bSwapHorzRef, bSwapVertRef, fXMapper, fYMapper)
  {
    o.onmousedown	= Drag.start;

    o.hmode			= bSwapHorzRef ? false : true ;
    o.vmode			= bSwapVertRef ? false : true ;

    o.root = oRoot && oRoot != null ? oRoot : o ;

    if (o.hmode  && isNaN(parseInt(o.root.style.left  ))) o.root.style.left   = "0px";
    if (o.vmode  && isNaN(parseInt(o.root.style.top   ))) o.root.style.top    = "0px";
    if (!o.hmode && isNaN(parseInt(o.root.style.right ))) o.root.style.right  = "0px";
    if (!o.vmode && isNaN(parseInt(o.root.style.bottom))) o.root.style.bottom = "0px";

    o.minX	= typeof minX != 'undefined' ? minX : null;
    o.minY	= typeof minY != 'undefined' ? minY : null;
    o.maxX	= typeof maxX != 'undefined' ? maxX : null;
    o.maxY	= typeof maxY != 'undefined' ? maxY : null;

    o.xMapper = fXMapper ? fXMapper : null;
    o.yMapper = fYMapper ? fYMapper : null;

    o.root.onDragStart	= new Function();
    o.root.onDragEnd	= new Function();
    o.root.onDrag		= new Function();
  },

  start : function(e)
  {
    var o = Drag.obj = this;
    e = Drag.fixE(e);
    var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
    var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right );
    o.root.onDragStart(x, y);

    o.lastMouseX	= e.clientX;
    o.lastMouseY	= e.clientY;

    if (o.hmode) {
      if (o.minX != null)	o.minMouseX	= e.clientX - x + o.minX;
      if (o.maxX != null)	o.maxMouseX	= o.minMouseX + o.maxX - o.minX;
    } else {
      if (o.minX != null) o.maxMouseX = -o.minX + e.clientX + x;
      if (o.maxX != null) o.minMouseX = -o.maxX + e.clientX + x;
    }

    if (o.vmode) {
      if (o.minY != null)	o.minMouseY	= e.clientY - y + o.minY;
      if (o.maxY != null)	o.maxMouseY	= o.minMouseY + o.maxY - o.minY;
    } else {
      if (o.minY != null) o.maxMouseY = -o.minY + e.clientY + y;
      if (o.maxY != null) o.minMouseY = -o.maxY + e.clientY + y;
    }

    document.onmousemove	= Drag.drag;
    document.onmouseup		= Drag.end;

    return false;
  },

  drag : function(e)
  {
    e = Drag.fixE(e);
    var o = Drag.obj;

    var ey	= e.clientY;
    var ex	= e.clientX;
    var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
    var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right );
    var nx, ny;

    if (o.minX != null) ex = o.hmode ? Math.max(ex, o.minMouseX) : Math.min(ex, o.maxMouseX);
    if (o.maxX != null) ex = o.hmode ? Math.min(ex, o.maxMouseX) : Math.max(ex, o.minMouseX);
    if (o.minY != null) ey = o.vmode ? Math.max(ey, o.minMouseY) : Math.min(ey, o.maxMouseY);
    if (o.maxY != null) ey = o.vmode ? Math.min(ey, o.maxMouseY) : Math.max(ey, o.minMouseY);

    nx = x + ((ex - o.lastMouseX) * (o.hmode ? 1 : -1));
    ny = y + ((ey - o.lastMouseY) * (o.vmode ? 1 : -1));

    if (o.xMapper)		nx = o.xMapper(y)
    else if (o.yMapper)	ny = o.yMapper(x)

    Drag.obj.root.style[o.hmode ? "left" : "right"] = nx + "px";
    Drag.obj.root.style[o.vmode ? "top" : "bottom"] = ny + "px";
    Drag.obj.lastMouseX	= ex;
    Drag.obj.lastMouseY	= ey;

    Drag.obj.root.onDrag(nx, ny);
    return false;
  },

  end : function()
  {
    document.onmousemove = getMouseXY;
    document.onmouseup   = null;
    Drag.obj.root.onDragEnd(	parseInt(Drag.obj.root.style[Drag.obj.hmode ? "left" : "right"]), 
                  parseInt(Drag.obj.root.style[Drag.obj.vmode ? "top" : "bottom"]));
    Drag.obj = null;
  },

  fixE : function(e)
  {
    if (typeof e == 'undefined') e = window.event;
    if (typeof e.layerX == 'undefined') e.layerX = e.offsetX;
    if (typeof e.layerY == 'undefined') e.layerY = e.offsetY;
    return e;
  }
};

