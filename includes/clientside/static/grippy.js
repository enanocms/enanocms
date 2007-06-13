// Resizable textareas (fun!)

function taStartDrag()
{
  obj = this;
  current_ta = obj.previousSibling;
  startmouseX = mouseX;
  startmouseY = mouseY;
  startScroll = getScrollOffset();
  is_dragging = true;
  startwidth  = getElementWidth(current_ta.id);
  startheight = getElementHeight(current_ta.id);
  var body = document.getElementsByTagName('body');
  body = body[0];
  body.style.cursor = 's-resize';
}
function taInDrag()
{
  if(!is_dragging) return;
  cw = startwidth;
  ch = startheight;
  mx = mouseX;
  my = mouseY + getScrollOffset() - startScroll;
  ch = -6 + ch + ( my - startmouseY );
  current_ta.style.height = ch+'px';
  if(do_width)
  {
    current_ta.style.width  = mx+'px';
    current_ta.nextSibling.style.width  = mx+'px';
  }
}
function taCloseDrag()
{
  is_dragging = false;
  current_ta = false;
  body = document.getElementsByTagName('body');
  body = body[0];
  body.style.cursor = 'default';
}

var grippied_textareas = new Array();

function initTextareas()
{
  var textareas = document.getElementsByTagName('textarea');
  for (i = 0;i < textareas.length;i++)
  {
    if(!textareas[i].id)
      textareas[i].id = 'autoTextArea_'+Math.floor(Math.random()*100000);
    cta = textareas[i];
    var divchk = ( in_array(cta.id, grippied_textareas) ) ? false : true;
    if(divchk)
    {
      grippied_textareas.push(cta.id);
      makeGrippy(cta);
    }
  }
}

function makeGrippy(cta)
{
  var thediv = document.createElement('div');
  thediv.style.backgroundColor = '#ceceed';
  thediv.style.backgroundImage = 'url('+scriptPath+'/images/grippy.gif)';
  thediv.style.backgroundPosition = 'bottom right';
  thediv.style.backgroundRepeat = 'no-repeat';
  thediv.style.width = getElementWidth(cta.id)+'px';
  thediv.style.cursor = 's-resize';
  thediv.style.className = 'ThisIsATextareaGrippy';
  thediv.id = 'autoGrippy_'+Math.floor(Math.random()*100000);
  thediv.style.height = '12px';
  thediv.onmousedown = taStartDrag;
  thediv.style.border = '1px solid #0000A0';
  if(cta.style.marginBottom)
  {
    thediv.style.marginBottom = cta.style.marginBottom;
    cta.style.marginBottom = '0';
  }
  if(cta.style.marginLeft)
  {
    thediv.style.marginLeft = cta.style.marginLeft;
  }
  if(cta.style.marginRight)
  {
    thediv.style.marginRight = cta.style.marginRight;
  }
  document.onmouseup = taCloseDrag;
  if(cta.nextSibling) cta.parentNode.insertBefore(thediv, cta.nextSibling);
  else cta.parentNode.appendChild(thediv);
}

