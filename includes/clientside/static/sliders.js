// Sliding drawers on the sidebar

// our global vars
// the delay between the slide in/out, and a little inertia

var sliders_initted = false;
      
function initSliders()
{
  sliders_initted = true;
  if ( KILL_SWITCH )
    return false;
    // detect whether the user has ie or not, how we get the height is different 
    var useragent = navigator.userAgent.toLowerCase();
    var ie = ((useragent.indexOf('msie') != -1) && (useragent.indexOf('opera') == -1) && (useragent.indexOf('webtv') == -1));
    
    if(ie)
      return;
    
    var divs = getElementsByClassName(document, "div", "slideblock");
    
    for(var i=0; i<divs.length; i++)
    {
        // set a unique id for this slider
        divs[i].metaid = i;
        
        // get the original height
        var baseheight = (ie) ? divs[i].offsetHeight + "px" : document.defaultView.getComputedStyle(divs[i], null).getPropertyValue('height', null);

        // use cookies to toggle whether to display it or not
        var id = ( divs[i].parentNode.firstChild.nextSibling ) ? divs[i].parentNode.firstChild.nextSibling.firstChild : divs[i].parentNode.parentNode.firstChild.nextSibling.firstChild;
        
        if ( !id.nextSibling )
          return;
        
        if(id.innerHTML || id.nextSibling.length < 1) id = id.innerHTML;
        else id = id.nextSibling.innerHTML; // Gecko fix
        
        var cookieName = 'mdgSliderState_'+i; // id.replace(' ', '_');
        //alert(cookieName + ': ' + readCookie(cookieName));
        if(readCookie(cookieName)=='closed')
        {
          divs[i].style.display = "none";
        }
        else
        {
          divs[i].style.display = "block";
        }

        // "save" our div height, because once it's display is set to none we can't get the original height again
        var d = new div();
        d.el = divs[i];
        d.ht = baseheight.substring(0, baseheight.indexOf("p"));

        // store our saved version
        divheights[i] = d;        
    }
}

// this is one of our divs, it just has a DOM reference to the element and the original height
function div(_el, _ht)
{
    this.el = _el;
    this.ht = _ht;
}

function toggle(t)
{
  if(IE)
    return false;
  if ( KILL_SWITCH )
    return false;
  if ( !sliders_initted )
    initSliders();
    // reset our inertia base and interval
    inertiabase = inertiabaseoriginal;
    clearInterval(slideinterval);

    // get our block
    block = t.parentNode.nextSibling;

    // for mozilla, it doesn't like whitespace between elements
    if(block.className == undefined)
        block = t.parentNode.nextSibling.nextSibling;
      
    if(block.style.display == "none")
    {
        block.style.display = "block";
        block.style.height = "1px";

        // our goal and current height
        targetheight = divheight(block);
        heightnow = 1;
        
        // remember toggled state
        cookieName = 'mdgSliderState_'+block.metaid; // t.innerHTML.replace(' ', '_');
        createCookie(cookieName, 'open', 3650);

        // our interval
        slideinterval = setInterval(slideout, slideintervalinc);
    }
    else
    {
        // our goal and current height
        targetheight = 1;
        heightnow = divheight(block);
        
        // remember toggled state
        cookieName = 'mdgSliderState_'+block.metaid; // t.innerHTML.replace(' ', '_');
        createCookie(cookieName, 'closed', 3650);

        // our interval
        slideinterval = setInterval(slidein, slideintervalinc);
    }
}

// this is our slidein function the interval uses, it keeps subtracting
// from the height till it's 1px then it hides it
function slidein()
{
    if(heightnow > targetheight)
    {
        // reduce the height by intertiabase * inertiainc
        heightnow -= inertiabase;

        // increase the intertiabase by the amount to keep it changing
        inertiabase += inertiainc;

        // it's possible to exceed the height we want so we use a ternary - (condition) ? when true : when false;
        block.style.height = (heightnow > 1) ? heightnow + "px" : targetheight + "px";
    }
    else
    {
        // finished, so hide the div properly and kill the interval
        clearInterval(slideinterval);
        block.style.display = "none";
    }
}

// this is the function our slideout interval uses, it keeps adding
// to the height till it's fully displayed
function slideout()
{
    block.style.display = 'block';
    if(heightnow < targetheight)
    {
        // increases the height by the inertia stuff
        heightnow += inertiabase;

        // increase the inertia stuff
        inertiabase += inertiainc;

        // it's possible to exceed the height we want so we use a ternary - (condition) ? when true : when false;
        block.style.height = (heightnow < targetheight) ? heightnow + "px" : targetheight + "px";
        
    }
    else
    {
        // finished, so make sure the height is what it's meant to be (inertia can make it off a little)
        // then kill the interval
        clearInterval(slideinterval);
        block.style.height = targetheight + "px";
    }
}

// returns the height of the div from our array of such things
function divheight(d)
{
    for(var i=0; i<divheights.length; i++)
    {
        if(divheights[i].el == d)
        {
            return divheights[i].ht;
        }
    }
}

/*
    the getElementsByClassName function I pilfered from this guy.  It's
    a useful function that'll return any/all tags with a specific css class.

    Written by Jonathan Snook, http://www.snook.ca/jonathan
    Add-ons by Robert Nyman, http://www.robertnyman.com
    
    Modified to match all elements that match the class name plus an integer after the name
    This is used in Enano to allow sliding sidebar widgets that use their own CSS
*/
function getElementsByClassName(oElm, strTagName, strClassName)
{
    // first it gets all of the specified tags
    var arrElements = (strTagName == "*" && document.all) ? document.all : oElm.getElementsByTagName(strTagName);
    
    // then it sets up an array that'll hold the results
    var arrReturnElements = new Array();

    // some regex stuff you don't need to worry about
    strClassName = strClassName.replace(/\-/g, "\\-");

    var oRegExp = new RegExp("(^|\\s)" + strClassName + "([0-9]*)(\\s|$)");
    var oElement;
    
    // now it iterates through the elements it grabbed above
    for(var i=0; i<arrElements.length; i++)
    {
        oElement = arrElements[i];

        // if the class matches what we're looking for it ads to the results array
        if(oElement.className.match(oRegExp))
        {
            arrReturnElements.push(oElement);
        }
    }

    // then it kicks the results back to us
    return (arrReturnElements)
}

