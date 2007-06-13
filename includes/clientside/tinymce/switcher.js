function readCookie(name) {var nameEQ = name + "=";var ca = document.cookie.split(';');for(var i=0;i < ca.length;i++){var c = ca[i];while (c.charAt(0)==' ') c = c.substring(1,c.length);if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);}return null;}
function createCookie(name,value,days){if (days){var date = new Date();date.setTime(date.getTime()+(days*24*60*60*1000));var expires = "; expires="+date.toGMTString();}else var expires = "";document.cookie = name+"="+value+expires+"; path=/";}
function eraseCookie(name) {createCookie(name,"",-1);}

function initSwitcher()
{
  if(readCookie('tmce_demo_mode') == 'tinymce')
  {
    switchToMCE();
  }
}

function switchToMCE()
{
  elem = document.getElementById('tMceEditor');
  tinyMCE.addMCEControl(elem, 'content', document);
  createCookie('tmce_demo_mode', 'tinymce', 365);
}

function switchToText()
{
  elem = document.getElementById('tMceEditor');
  tinyMCE.removeMCEControl('content');
  createCookie('tmce_demo_mode', 'text', 365);
}

function switchEditor()
{
  if(readCookie('tmce_demo_mode') == 'tinymce')
  {
    switchToText();
  }
  else
  {
    switchToMCE();
  }
}

window.onload = initSwitcher;

tinyMCE.init({
      mode : "exact",
      elements : '',
      theme_advanced_resize_horizontal : false,
      theme_advanced_resizing : true,
      theme_advanced_toolbar_location : "top",
      theme_advanced_toolbar_align : "left",
      theme_advanced_buttons1_add : "fontselect,fontsizeselect",
      theme_advanced_statusbar_location : 'bottom'
  });

