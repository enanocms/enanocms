var disenable_currentBlock;
function ajaxDisenableBlock(id)
{
  disenable_currentBlock = document.getElementById('disabled_'+id);
  ajaxGet(makeUrlNS('Special', 'EditSidebar', 'action=disenable&ajax=true&noheaders&id='+id), function()
    {
      if(ajax.readyState == 4)
      {
        if(ajax.responseText == 'GOOD')
        {
          if(disenable_currentBlock.style.display == 'none')
          {
            disenable_currentBlock.style.display = 'inline';
          }
          else
          {
            disenable_currentBlock.style.display = 'none';
          }
        } 
        else
        {
          document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
        }
      }
    });
}

var delete_currentBlock;
function ajaxDeleteBlock(id, oElm)
{
  delete_currentBlock = { 0 : id, 1 : oElm };
  ajaxGet(makeUrlNS('Special', 'EditSidebar', 'action=delete&ajax=true&noheaders&id='+id), function()
    {
      if(ajax.readyState == 4)
      {
        if(ajax.responseText == 'GOOD')
        {
          e = delete_currentBlock[1];
          e = e.parentNode.parentNode;
          e.parentNode.removeChild(e);
        } 
        else
        {
          document.getElementById('ajaxEditContainer').innerHTML = ajax.responseText;
        }
      }
    });
}

var blockEdit_current;
function ajaxEditBlock(id, oElm)
{
  blockEdit_current = { 0 : id, 1 : oElm };
  ajaxGet(makeUrlNS('Special', 'EditSidebar', 'action=getsource&noheaders&id='+id), function()
    {
      if(ajax.readyState == 4)
      {
        id = blockEdit_current[0];
        oElm = blockEdit_current[1];
        var thediv = document.createElement('div');
        //if(!oElm.id) oElm.id = 'autoEditButton_'+Math.floor(Math.random() * 100000);
        oElm = oElm.parentNode;
        var magic = $(oElm).Top() + $(oElm).Height();
        var top = String(magic);
        top = top + 'px';
        left = $(oElm).Left() + 'px';
        thediv.style.top = top;
        thediv.style.left = left;
        thediv.style.position = 'absolute';
        thediv.className = 'mdg-comment';
        thediv.style.margin = '0';
        if(ajax.responseText == 'HOUSTON_WE_HAVE_A_PLUGIN')
        {
          thediv.innerHTML = '<h3>This block cannot be edited.</h3><p>This is a plugin block, and cannot be edited.</p><p><a href="#" onclick="this.parentNode.parentNode.parentNode.removeChild(this.parentNode.parentNode); return false;">close</a></p>';
        }
        else
        {
          ta = document.createElement('textarea');
          ta.rows = '15';
          ta.cols = '50';
          ta.innerHTML = ajax.responseText;
          thediv.appendChild(ta);
          b = document.createElement('br');
          thediv.appendChild(b);
          thediv.innerHTML += '<a href="#" onclick="ajaxSaveBlock(this, \''+id+'\'); return false;">save</a>  |  <a href="#" onclick="if(confirm(\'Do you really want to discard your changes?\')) this.parentNode.parentNode.removeChild(this.parentNode); return false;">cancel</a>';
        }
        body = document.getElementsByTagName('body');
        body = body[0];
        body.appendChild(thediv);
      }
    });
}

var blockSave_current;
function ajaxSaveBlock(oElm, id)
{
  taContent = escape(oElm.previousSibling.previousSibling.value);
  taContent = taContent.replace(unescape('%0A'), '%0A');
  taContent = taContent.replace('+', '%2B');
  blockSave_current = { 0 : id, 1 : oElm };
  ajaxPost(makeUrlNS('Special', 'EditSidebar', 'noheaders&action=save&id='+id), 'content='+taContent, function()
    {
      if(ajax.readyState == 4)
      {
        id   = blockSave_current[0];
        oElm = blockSave_current[1];
        eval(ajax.responseText);
        if(status == 'GOOD')
        {
          var _id = 'disabled_' + String(id);
          var parent = document.getElementById(_id).parentNode.parentNode;
          oElm.parentNode.parentNode.removeChild(oElm.parentNode);
          content = content.replace('%a', unescape('%0A'));
          var obj = ( IE ) ? parent.firstChild.nextSibling.nextSibling : parent.firstChild.nextSibling.nextSibling.nextSibling;
          if ( obj )
            obj.innerHTML = content; // $content is set in ajax.responseText
        }
        else
        {
          alert(status);
        }
      }
    });
}

