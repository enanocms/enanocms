/**
 * Darkens the browser screen. This will make the entire page un-clickable except for any floating divs created after this is called. Restore with enlighten().
 * @param bool Controls whether the fade should be disabled or not. aclDisableTransitionFX will override this if set to true, and fades are never fired on IE.
 * @param int When specified, represents the numeric opacity value to set the fade layer to. 1-100.
 */

var darkener_index = [];
var FADE_TIME = 500; // in ms

function darken(nofade, opacVal, layerid)
{
  layerid = ( layerid ) ? layerid : 'specialLayer_darkener';
  if(IE)
    nofade = true;
  if ( !opacVal )
    opacVal = 70;
  darkener_index[layerid] = ( typeof(darkener_index[layerid]) == 'number' ) ? darkener_index[layerid] + 1 : 1;
  if(document.getElementById(layerid) && !document.getElementById(layerid).destroying)
  {
    document.getElementById(layerid).style.zIndex = getHighestZ() + 1;
    if(nofade)
    {
      changeOpac(opacVal, layerid);
      document.getElementById(layerid).style.display = 'block';
      document.getElementById(layerid).myOpacVal = opacVal;
    }
    else
    {
      if ( document.getElementById(layerid).style.display != 'none' )
      {
        var currentOpac = document.getElementById(layerid).myOpacVal;
        opacity(layerid, currentOpac, opacVal, FADE_TIME);
        document.getElementById(layerid).myOpacVal = opacVal;
      }
      else
      {
        document.getElementById(layerid).style.display = 'block';
        document.getElementById(layerid).myOpacVal = opacVal;
        opacity(layerid, 0, opacVal, FADE_TIME);
      }
    }
  }
  else if(document.getElementById(layerid) && document.getElementById(layerid).destroying)
  {
    // fade in progress - abort
    console.warn('Aborting fade');
    abortFades();
    changeOpac(opacVal, layerid);
    document.getElementById(layerid).destroying = false;
    return document.getElementById(layerid);
  }
  else
  {
    w = getWidth();
    h = getHeight();
    var thediv = document.createElement('div');
    if(IE)
      thediv.style.position = 'absolute';
    else
      thediv.style.position = 'fixed';
    if ( IE )
    {
      var top = getScrollOffset();
      thediv.style.top = String(top) + 'px';
    }
    else
    {
      thediv.style.top = '0px';
    }
    thediv.style.left = '0px';
    thediv.style.opacity = '0';
    thediv.style.filter = 'alpha(opacity=0)';
    thediv.style.backgroundColor = '#000000';
    thediv.style.width =  '100%';
    thediv.style.height = IE ? h + 'px' : '100%';
    thediv.style.zIndex = getHighestZ() + 1;
    thediv.id = layerid;
    thediv.myOpacVal = opacVal;
    if(nofade)
    {
      thediv.style.opacity = ( parseFloat(opacVal) / 100 );
      thediv.style.filter = 'alpha(opacity=' + opacVal + ')';
      body = document.getElementsByTagName('body');
      body = body[0];
      body.appendChild(thediv);
    } else {
      body = document.getElementsByTagName('body');
      body = body[0];
      body.appendChild(thediv);
      opacity(layerid, 0, opacVal, FADE_TIME);
    }
  }
  return document.getElementById(layerid);
}

/**
 * Un-darkens the screen and re-enables clicking of on-screen controls.
 * @param bool If true, disables the fade effect. Fades are always disabled if aclDisableTransitionFX is true and on IE.
 */

function enlighten(nofade, layerid)
{
  layerid = ( layerid ) ? layerid : 'specialLayer_darkener';
  
  if(IE)
    nofade = true;
  darkener_index[layerid] -= 1;
  if ( darkener_index[layerid] > 0 )
    return false;
  if(document.getElementById(layerid))
  {
    if(nofade)
    {
      document.getElementById(layerid).style.display = 'none';
    }
    else
    {
      document.getElementById(layerid).destroying = true;
      var from = document.getElementById(layerid).myOpacVal;
      opacity(layerid, from, 0, FADE_TIME);
      setTimeout("var l = document.getElementById('" + layerid + "'); var b = document.getElementsByTagName('body')[0]; b.removeChild(l);", 1000);
    }
  }
  return document.getElementById(layerid);
}
