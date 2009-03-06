// Sliding drawers on the sidebar

// our global vars
// the delay between the slide in/out, and a little inertia

/*
pseudocode:
  oninit():
    i = 0
    for every div with class "slideblock", do
      if ( cookie['mdgSliderState_' || i] == 'closed' )
        div.hide()
        
      div.trigger.addEvent onclick():
        if ( div.hidden )
          div.show()
          cookie['mdgSliderState_' || i] = 'open'
        else
          div.hide()
          cookie['mdgSliderState_' || i] = 'closed
          
      i++
    
*/


var sliders_initted = false;
      
var initSliders = function()
{
  if ( KILL_SWITCH || IE )
    return false;
  
  var divs = getElementsByClassName(document, "div", "slideblock");
  var divs2 = getElementsByClassName(document, "div", "slideblock2");
  for ( var i = 0; i < divs2.length; i++ )
  {
    divs.push(divs2[i]);
  }
  delete divs2;
  
  if ( divs.length < 1 )
    return false;
  
  for ( var i = 0; i < divs.length; i++ )
  {
    var div = divs[i];
    // set a unique id for this slider
    div.metaid = i;
    
    var cookiename = 'mdgSliderState_' + i;
    if ( readCookie(cookiename) == 'closed' )
    {
      div.style.display = 'none';
    }
    
    var el = div.previousSibling;
    if ( !el )
      continue;
    while ( el.tagName == undefined )
    {
      el = el.previousSibling;
      if ( !el )
        break;
    }
    if ( !el )
      continue;
    var toggler = el.getElementsByTagName('a')[0];
    if ( !toggler )
      continue;
    toggler.onclick = function()
    {
      load_component(['jquery', 'jquery-ui']);
      
      var mydiv = this.parentNode.nextSibling;
      while ( mydiv.tagName != 'DIV' )
        mydiv = mydiv.nextSibling;
      if ( mydiv.style.display == 'none' )
      {
        $(mydiv).show('blind');
        var cookiename = 'mdgSliderState_' + mydiv.metaid;
        createCookie(cookiename, 'open', 365);
      }
      else
      {
        $(mydiv).hide('blind');
        var cookiename = 'mdgSliderState_' + mydiv.metaid;
        createCookie(cookiename, 'closed', 365);
      }
      
      return false;
    }
  }
}

addOnloadHook(initSliders);

