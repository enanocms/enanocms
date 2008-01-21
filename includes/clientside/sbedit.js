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
          thediv.innerHTML = '<h3>' + $lang.get('sbedit_msg_cant_edit_plugin_title') + '</h3><p>' + $lang.get('sbedit_msg_cant_edit_plugin_body', { close_link: 'a href="#" onclick="this.parentNode.parentNode.parentNode.removeChild(this.parentNode.parentNode); return false;"' }) + '</p>';
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
          thediv.innerHTML += '<a href="#" onclick="ajaxSaveBlock(this, \''+id+'\'); return false;">' + $lang.get('sbedit_btn_edit_save') + '</a>  |  <a href="#" onclick="if(confirm(\'' + $lang.get('sbedit_msg_discard_confirm') + '\')) this.parentNode.parentNode.removeChild(this.parentNode); return false;">' + $lang.get('sbedit_btn_edit_cancel') + '</a>';
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

function ajaxRenameSidebarStage1(parent, id)
{
  var oldname = parent.firstChild.nodeValue;
  parent.removeChild(parent.firstChild);
  parent.ondblclick = function() {};
  parent._idcache = id;
  var input = document.createElement('input');
  input.type = 'text';
  input.sbedit_id = id;
  input.oldvalue = oldname;
  input.onkeyup = function(e)
  {
    if ( typeof(e) != 'object' )
      return false;
    if ( !e.keyCode )
      return false;
    if ( e.keyCode == 13 )
    {
      ajaxRenameSidebarStage2(this);
    }
    if ( e.keyCode == 27 )
    {
      ajaxRenameSidebarCancel(this);
    }
  };
  input.onblur = function()
  {
    ajaxRenameSidebarCancel(this);
  };
  input.value = oldname;
  input.style.fontSize = '7pt';
  parent.appendChild(input);
  input.focus();
}

function ajaxRenameSidebarStage2(input)
{
  var newname = input.value;
  var id = input.sbedit_id;
  var parent = input.parentNode;
  parent.removeChild(input);
  parent.appendChild(document.createTextNode(( newname == '' ? '<Unnamed>' : newname )));
  parent.ondblclick = function() { ajaxRenameSidebarStage1(this, this._idcache); return false; };
  var img = document.createElement('img');
  img.src = scriptPath + '/images/loading.gif';
  parent.appendChild(img);
  newname = ajaxEscape(newname);
  ajaxPost(makeUrlNS('Special', 'EditSidebar', 'ajax&noheaders&action=rename&id='+id), 'newname=' +newname, function()
    {
      if ( ajax.readyState == 4 )
      {
        parent.removeChild(img);
        if ( ajax.responseText != 'GOOD' )
          new messagebox(MB_OK|MB_ICONSTOP, 'Error renaming block', ajax.responseText);
      }
    });
}

function ajaxRenameSidebarCancel(input)
{
  var newname = input.oldvalue;
  var id = input.sbedit_id;
  var parent = input.parentNode;
  parent.removeChild(input);
  parent.appendChild(document.createTextNode(newname));
  parent.ondblclick = function() { ajaxRenameSidebarStage1(this, this._idcache); return false; };
}

