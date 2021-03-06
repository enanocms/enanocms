/*
 * The jBox menu system. Written by Dan Fuhry and licensed under the GPL.
 */

// Cache of DOM and event objects, used in setTimeout() calls due to scope rules
var jBoxObjCache = new Object();

// Cache of "correct" heights for unordered list objects used in submenus. Helps the animation routine know what height it's aiming for.
var jBoxMenuHeights = new Object();

// Blocks animations from running if there's already an animation going on the same object
var jBoxSlideBlocker = new Object();

// Switch to enable or disable jBox slide animation
var jBox_slide_enable = true;

// Speed at which the menus should slide open. 1 to 100 or -1 to disable.
// Setting this higher than 100 will cause an infinite loop in the browser.
// Default is 80 - anything higher than 90 means exponential speed increase
var slide_speed = 80;

// Inertia value to start with
// Default is 0
var inertia_base = 0;

// Value added to inertia_base on each frame - generally 1 to 3 is good, with 1 being slowest animation
// Default is 1
var inertia_inc  = 1;

// Opacity that menus should fade to. 1 to 100 or -1 to disable fades. This only works if the slide effect is also enabled.
// Default is 100
var jBox_opacity = 100;

// Adds the jBox CSS to the HTML header. Called on window onload.
var jBoxInit = function()
{
	setTimeout('jBoxBatchSetup();', 200);
}
addOnloadHook(jBoxInit);

// Initializes each menu.
function jBoxBatchSetup()
{
	if ( KILL_SWITCH )
		return false;
	var menus = document.getElementsByClassName('div', 'menu_nojs');
	if ( menus.length > 0 )
	{
		for ( var i in menus )
		{
			if ( typeof(menus[i]) != 'object')
				continue; // toJSONString() compatibility
			jBoxSetup(menus[i]);
		}
	}
}

// Initializes a div with a jBox menu in it.
function jBoxSetup(obj)
{
	$dynano(obj).addClass('menu');
	removeTextNodes(obj);
	
	var html = document.getElementsByTagName('html')[0];
	var direction = typeof(html.dir) != 'undefined' && html.dir != '' ? html.dir : 'ltr';
	
	for ( var i = 0; i < obj.childNodes.length; i++ )
	{
		/* normally this would be done in about 2 lines of code, but javascript is so picky..... */
		if ( obj.childNodes[i] )
		{
			if ( obj.childNodes[i].tagName )
			{
				if ( obj.childNodes[i].tagName == 'A' )
				{
					// if ( is_Safari ) alert('It\'s an A: '+obj);
					if ( obj.childNodes[i].nextSibling )
					{
						// alert("Next sibling: " + obj.childNodes[i].nextSibling);
						if ( obj.childNodes[i].nextSibling.tagName )
						{
							if ( obj.childNodes[i].nextSibling.tagName == 'UL' || ( obj.childNodes[i].nextSibling.tagName.toLowerCase() == 'div' && obj.childNodes[i].nextSibling.className == 'submenu' ) )
							{
								// Calculate height
								var ul = obj.childNodes[i].nextSibling;
								domObjChangeOpac(0, ul);
								ul.style.display = 'block';
								ul.style.zIndex = getHighestZ() + 2;
								var links = ul.getElementsByTagName('a');
								for ( var j = 0; j < links.length; j++ )
								{
									links[j].onmouseup = function()
									{
										var ul = this;
										while ( ul.tagName != 'UL' && ul.tagName != 'DIV' && ul.tagName != 'BODY' )
											ul = ul.parentNode;
										if ( ul.tagName == 'BODY' )
											return false;
										jBoxHideMenu(ul.previousSibling, ul);
									}
								}
								var dim = fetch_dimensions(ul);
								if ( !ul.id )
									ul.id = 'jBoxmenuobj_' + Math.floor(Math.random() * 10000000);
								jBoxMenuHeights[ul.id] = parseInt(dim['h']) - 2; // subtract 2px for border width
								
								// this is a little bit of a hack
								var should_be_right = ( direction == 'ltr' && $dynano(ul).hasClass('jbox_right') ) || ( direction == 'rtl' && !$dynano(ul).hasClass('jbox_right') );
								
								if ( ( direction == 'ltr' && dim['w'] + $dynano(ul).Left() > getWidth() ) || should_be_right )
								{
									$dynano(ul).addClass('jbox_right');
									ul.jbox_width = $dynano(ul).Width();
								}
								else
								{
									$dynano(ul).rmClass('jbox_right');
								}
								
								ul.style.display = 'none';
								domObjChangeOpac(100, ul);
								
								// Setup events
								obj.childNodes[i].onmouseover = function()  { jBoxOverHandler(this); };
								obj.childNodes[i].onmouseout = function(e)  { jBoxOutHandler(this, e); };
								console.debug(obj.childNodes[i].href);
								if ( obj.childNodes[i].href == window.location.href + '#' )
									obj.childNodes[i].onclick = function()
										{
											jBoxOverHandlerBin(this);
											return false;
										};
								obj.childNodes[i].nextSibling.onmouseout = function(e)  { jBoxOutHandler(this, e); };
								if ( is_iPhone )
								{
									obj.childNodes[i].onclick = function()  { jBoxOverHandler(this); return false; };
								}
							}
						}
					}
				}
			}
		}
	}
}

// Called when user hovers mouse over a submenu
function jBoxOverHandler(obj)
{
	// if ( is_Safari )
	//   alert('Safari and over');
	// Random ID used to track the object to perform on
	var seed = Math.floor(Math.random() * 1000000);
	jBoxObjCache[seed] = obj;
	
	// Sleep for a (little more than a tenth of a) second to see if the user really wants the menu to expand
	setTimeout('if(isOverObj(jBoxObjCache['+seed+'], false, false)) jBoxOverHandlerBin(jBoxObjCache['+seed+']);', 150);
}

// Displays a menu.
function jBoxOverHandlerBin(obj)
{
	var others = obj.parentNode.getElementsByTagName('ul');
	for ( var i in others )
	{
		if(typeof(others[i]) == 'object')
		{
			others[i].style.display = 'none';
			$dynano(others[i].previousSibling).rmClass('liteselected');
		}
	}
	var others = obj.parentNode.getElementsByTagName('div');
	for ( var i in others )
	{
		if(typeof(others[i]) == 'object')
		{
			if ( others[i].className == 'submenu' )
			{
				others[i].style.display = 'none';
				$dynano(others[i].previousSibling).rmClass('liteselected');
			}
		}
	}
	if(obj.nextSibling.tagName.toLowerCase() == 'ul' || ( obj.nextSibling.tagName.toLowerCase() == 'div' && obj.nextSibling.className == 'submenu' ))
	{
		$dynano(obj).addClass('liteselected');
		//obj.className = 'liteselected';
		var ul = obj.nextSibling;
		var dim = fetch_dimensions(obj);
		var off = fetch_offset(obj);
		var dimh = parseInt(dim['h']);
		var offtop = parseInt(off['top']);
		var top = dimh + offtop;
		
		if ( $dynano(ul).hasClass('jbox_right') )
		{
			left = $dynano(obj).Left() + $dynano(obj).Width() - ul.jbox_width; // ( link left + link width ) - ul width
		}
		else
		{
			left = off['left'];
		}
		if ( jBox_slide_enable )
		{
			domObjChangeOpac(0, ul);
		}
		ul.style.left = left + 'px';
		ul.style.top = top + 'px';
		ul.style.clip = 'rect(auto,auto,auto,auto)';
		ul.style.overflow = 'visible';
		ul.style.display = 'block';
		if ( jBox_slide_enable )
		{
			slideOut(ul);
		}
		else
		{
			domObjChangeOpac(100, ul);
		}
	}
}

function jBoxOutHandler(obj, event)
{
	var seed = Math.floor(Math.random() * 1000000);
	var seed2 = Math.floor(Math.random() * 1000000);
	jBoxObjCache[seed] = obj;
	jBoxObjCache[seed2] = event;
	setTimeout('jBoxOutHandlerBin(jBoxObjCache['+seed+'], jBoxObjCache['+seed2+']);', 750);
}

function jBoxOutHandlerBin(obj, event)
{
	var caller = obj.tagName.toLowerCase();
	if(caller == 'a')
	{
		a = obj;
		ul = obj.nextSibling;
	}
	else if(caller == 'ul' || caller == 'div')
	{
		a = obj.previousSibling;
		ul = obj;
	}
	else
	{
		return false;
	}
	
	if (!isOverObj(a, false, event) && !isOverObj(ul, true, event))
	{
		jBoxHideMenu(a, ul);
	}
	
	return true;
}

function jBoxHideMenu(a, ul)
{
	$dynano(a).rmClass('liteselected');
		
	if ( jBox_slide_enable )
	{
		slideIn(ul);
	}
	else
	{
		ul.style.display = 'none';
	}
}

// Slide an element downwards until it is at full height.
// First parameter should be a DOM object with style.display = block and opacity = 0.

var sliderobj = new Object();

function slideOut(obj)
{
	if ( jBoxSlideBlocker[obj.id] )
		return false;
	
	jBoxSlideBlocker[obj.id] = true;
	
	if ( slide_speed == -1 )
	{
		obj.style.display = 'block';
		return false;
	}
	
	var currentheight = 0;
	var targetheight = jBoxMenuHeights[obj.id];
	var inertiabase = inertia_base;
	var inertiainc = inertia_inc;
	slideStep(obj, 0);
	domObjChangeOpac(100, obj);
	obj.style.overflow = 'hidden';
	
	// Don't edit past here
	var timercnt = 0;
	
	var seed = Math.floor(Math.random() * 1000000);
	sliderobj[seed] = obj;
	
	var framecnt = 0;
	
	while(true)
	{
		framecnt++;
		timercnt += ( 100 - slide_speed );
		inertiabase += inertiainc;
		currentheight += inertiabase;
		if ( currentheight > targetheight )
			currentheight = targetheight;
		setTimeout('slideStep(sliderobj['+seed+'], '+currentheight+', '+targetheight+');', timercnt);
		if ( currentheight >= targetheight )
			break;
	}
	timercnt = timercnt + ( 100 - slide_speed );
	setTimeout('jBoxSlideBlocker[sliderobj['+seed+'].id] = false;', timercnt);
	var opacstep = jBox_opacity / framecnt;
	var opac = 0;
	var timerstep = 0;
	domObjChangeOpac(0, obj);
	while(true)
	{
		timerstep += ( 100 - slide_speed );
		opac += opacstep;
		setTimeout('domObjChangeOpac('+opac+', sliderobj['+seed+']);', timerstep);
		if ( opac >= jBox_opacity )
			break;
	}
}

function slideIn(obj)
{
	if ( obj.style.display != 'block' )
		return false;
	
	if ( jBoxSlideBlocker[obj.id] )
		return false;
	
	jBoxSlideBlocker[obj.id] = true;
	
	var targetheight = 0;
	var dim = fetch_dimensions(obj);
	var currentheight = jBoxMenuHeights[obj.id];
	var origheight = currentheight;
	var inertiabase = inertia_base;
	var inertiainc = inertia_inc;
	domObjChangeOpac(100, obj);
	obj.style.overflow = 'hidden';
	
	// Don't edit past here
	var timercnt = 0;
	
	var seed = Math.floor(Math.random() * 1000000);
	sliderobj[seed] = obj;
	
	var framecnt = 0;
	
	for(var j = 0;j<100;j++) // while(true)
	{
		framecnt++;
		timercnt = timercnt + ( 100 - slide_speed );
		inertiabase = inertiabase + inertiainc;
		currentheight = currentheight - inertiabase;
		if ( currentheight < targetheight )
			currentheight = targetheight;
		setTimeout('slideStep(sliderobj['+seed+'], '+currentheight+');', timercnt);
		if ( currentheight <= targetheight )
			break;
	}
	timercnt += ( 100 - slide_speed );
	setTimeout('sliderobj['+seed+'].style.display="none";sliderobj['+seed+'].style.height="'+origheight+'px";jBoxSlideBlocker[sliderobj['+seed+'].id] = false;', timercnt);
	
	var opacstep = jBox_opacity / framecnt;
	var opac = jBox_opacity;
	var timerstep = 0;
	domObjChangeOpac(100, obj);
	while(true)
	{
		timerstep += ( 100 - slide_speed );
		opac -= opacstep;
		setTimeout('domObjChangeOpac('+opac+', sliderobj['+seed+']);', timerstep);
		if ( opac <= 0 )
			break;
	}
	
}

function slideStep(obj, height, maxheight)
{
	obj.style.height = height + 'px';
	//obj.style.clip = 'rect(3px,auto,'+maxheight+'px,auto)';
	obj.style.overflow = 'hidden';
	//obj.style.clip = 'rect('+height+'px,0px,'+maxheight+'px,auto);';
}

function isOverObj(obj, bias, event)
{
	var fieldUL = {};
	var dim = fetch_dimensions(obj);
	var off = fetch_offset(obj);
	fieldUL['top'] = off['top'];
	fieldUL['left'] = off['left'];
	fieldUL['right'] = off['left'] + dim['w'];
	fieldUL['bottom'] = off['top'] + dim['h'];
	
	var mouseX_local = mouseX + getXScrollOffset();
	var mouseY_local = mouseY + getScrollOffset();
	
	// document.getElementById('debug').innerHTML = '<br />Mouse: x: '+mouseX+', y:' + mouseY + '<br />' + document.getElementById('debug').innerHTML;
	
	if(bias)
	{
		if ( ( mouseX_local < fieldUL['left'] + 2 || mouseX_local > fieldUL['right']  - 5 ) ||
 				( mouseY_local < fieldUL['top']  - 2 || mouseY_local > fieldUL['bottom'] - 2 ) )
		{
 			return false;
		}
	}
	else
	{
		if ( ( mouseX_local < fieldUL['left'] || mouseX_local > fieldUL['right']  ) ||
 				( mouseY_local < fieldUL['top']  || mouseY_local > fieldUL['bottom'] ) )
 			return false;
	}
 		
	return true;
}

function jBoxGarbageCollection(e)
{
	setMousePos(e);
	var menus = document.getElementsByClassName('div', 'menu');
	if ( menus.length > 0 )
	{
		for ( var i in menus )
		{
			if ( typeof(menus[i]) != 'object')
				continue; // toJSONString() compatibility
			var uls = menus[i].getElementsByTagName('ul');
			if ( uls.length > 0 )
			{
				for ( var j = 0; j < uls.length; j++ )
				{
					if ( !isOverObj(uls[j], false, e) )
					{
						$dynano(uls[j].previousSibling).rmClass('liteselected');
						//uls[j].style.display = 'none';
						slideIn(uls[j]);
					}
				}
			}
			var uls = getElementsByClassName(menus[i], 'divs', 'submenu');
			if ( uls.length > 0 )
			{
				for ( var j = 0; j < uls.length; j++ )
				{
					if ( !isOverObj(uls[j], false, e) )
					{
						$dynano(uls[j].previousSibling).rmClass('liteselected');
						//uls[j].style.display = 'none';
						slideIn(uls[j]);
					}
				}
			}
		}
	}
}

document.onclick = jBoxGarbageCollection;

var getElementsByClassName = function(parent, type, cls) {
	if(!type)
		type = '*';
	ret = new Array();
	if ( !parent )
		return ret;
	el = parent.getElementsByTagName(type);
	for ( var i = 0; i < el.length; i++ )
	{
		if ( typeof(el[i]) != 'object')
			continue; // toJSONString() compatibility
		if(el[i].className)
		{
			if(el[i].className.indexOf(' ') > 0)
			{
				classes = el[i].className.split(' ');
			}
			else
			{
				classes = new Array();
				classes.push(el[i].className);
			}
			if ( in_array(cls, classes) )
				ret.push(el[i]);
		}
	}
	return ret;
}

document.getElementsByClassName = function(type, cls) {
	return getElementsByClassName(document, type, cls);
}

function setMousePos(event)
{
	if(IE)
	{
		if(!event)
		{
			event = window.event;
		}
		clX = event.clientX;
		if ( document.body )
			sL  = document.body.scrollLeft;
		else
			sL  = 0;
		mouseX = clX + sL;
		mouseY = event.clientY + ( document.body ? document.body.scrollTop : 0 );
		return;
	}
	if( typeof(event.clientX) == 'number' )
	{
		mouseX = event.clientX;
		mouseY = event.clientY;
		return;
	}
	else if( typeof(event.layerX) == 'number' )
	{
		mouseX = event.layerX;
		mouseY = event.layerY;
		return;
	}
	else if( typeof(event.offsetX) == 'number' )
	{
		mouseX = event.offsetX;
		mouseY = event.offsetY;
		return;
	}
	else if( typeof(event.screenX) == 'number' )
	{
		mouseX = event.screenX;
		mouseY = event.screenY;
		return;
	}
	else if( typeof(event.x) == 'number' )
	{
		mouseX = event.x;
		mouseY = event.y;
		return;
	}
}

document.onmousemove = function(e)
{
	setMousePos(e);
};

function removeTextNodes(obj)
{
	if(obj)
	{
		if(typeof(obj.tagName) != 'string' || ( String(obj) == '[object Text]' && is_Safari ) )
		{
			if ( ( obj.nodeType == 3 && obj.data.match(/^([\s]*)$/ig) ) ) //  || ( typeof(obj.innerHTML) == undefined && is_Safari ) ) 
			{
				obj.parentNode.removeChild(obj);
				return;
			}
		}
		if(obj.firstChild)
		{
			for(var i = 0; i < obj.childNodes.length; i++)
			{
				removeTextNodes(obj.childNodes[i]);
			}
		}
	}
}

