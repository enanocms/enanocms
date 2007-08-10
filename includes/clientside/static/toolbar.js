// Page toolbar - selecting buttons

function unselectAllButtonsMajor()
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  obj = document.getElementById('pagebar_main').firstChild;
  while(obj)
  {
    if(obj.id == 'mdgToolbar_article' || obj.id == 'mdgToolbar_discussion')
    {
      obj.className = '';
    }
    obj = obj.nextSibling;
  }
}

function unselectAllButtonsMinor()
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  obj = document.getElementById('pagebar_main').firstChild.nextSibling;
  while(obj)
  {
    if ( obj.className != 'selected' )
    {
      obj = obj.nextSibling;
      continue;
    }
    if(obj.id != 'mdgToolbar_article' && obj.id != 'mdgToolbar_discussion')
    {
      if ( obj.className )
        obj.className = '';
    }
    obj = obj.nextSibling;
  }
}

function selectButtonMajor(which)
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  var dom = document.getElementById('mdgToolbar_'+which);
  if ( !dom )
    return false;
  if(typeof(dom) == 'object')
  {
    unselectAllButtonsMajor();
    document.getElementById('mdgToolbar_'+which).className = 'selected';
  }
}

function selectButtonMinor(which)
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  if(typeof(document.getElementById('mdgToolbar_'+which)) == 'object')
  {
    unselectAllButtonsMinor();
    document.getElementById('mdgToolbar_'+which).className = 'selected';
  }
}

