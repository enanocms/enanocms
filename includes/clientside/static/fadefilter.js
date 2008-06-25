/**
 * Darkens the browser screen. This will make the entire page un-clickable except for any floating divs created after this is called. Restore with enlighten().
 * @param bool Controls whether the fade should be disabled or not. aclDisableTransitionFX will override this if set to true, and fades are never fired on IE.
 * @param int When specified, represents the numeric opacity value to set the fade layer to. 1-100.
 */

var darkener_index = 0;

function darken(nofade, opacVal)
{
  if(IE)
    nofade = true;
  if ( !opacVal )
    opacVal = 70;
  darkener_index++;
  if(document.getElementById('specialLayer_darkener'))
  {
    if(nofade)
    {
      changeOpac(opacVal, 'specialLayer_darkener');
      document.getElementById('specialLayer_darkener').style.display = 'block';
      document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
    }
    else
    {
      if ( document.getElementById('specialLayer_darkener').style.display != 'none' )
      {
        var currentOpac = document.getElementById('specialLayer_darkener').myOpacVal;
        opacity('specialLayer_darkener', currentOpac, opacVal, 1000);
        document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
      }
      else
      {
        document.getElementById('specialLayer_darkener').style.display = 'block';
        document.getElementById('specialLayer_darkener').myOpacVal = opacVal;
        opacity('specialLayer_darkener', 0, opacVal, 1000);
      }
    }
  } else {
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
    thediv.style.height = '100%';
    thediv.zIndex = getHighestZ() + 5;
    thediv.id = 'specialLayer_darkener';
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
      opacity('specialLayer_darkener', 0, opacVal, 1000);
    }
  }
}

/**
 * Un-darkens the screen and re-enables clicking of on-screen controls.
 * @param bool If true, disables the fade effect. Fades are always disabled if aclDisableTransitionFX is true and on IE.
 */

function enlighten(nofade)
{
  if(IE)
    nofade = true;
  darkener_index -= 1;
  if ( darkener_index > 0 )
    return false;
  if(document.getElementById('specialLayer_darkener'))
  {
    if(nofade)
    {
      document.getElementById('specialLayer_darkener').style.display = 'none';
    }
    else
    {
      var from = document.getElementById('specialLayer_darkener').myOpacVal;
      // console.info('Fading from ' + from);
      opacity('specialLayer_darkener', from, 0, 1000);
      setTimeout("document.getElementById('specialLayer_darkener').style.display = 'none';", 1000);
    }
  }
}
