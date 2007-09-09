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
      $(obj).rmClass('selected');
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
    if ( !$(obj).hasClass('selected') )
    {
      obj = obj.nextSibling;
      continue;
    }
    if(obj.id != 'mdgToolbar_article' && obj.id != 'mdgToolbar_discussion')
    {
      if ( obj.className )
        $(obj).rmClass('selected');
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
    $('mdgToolbar_'+which).addClass('selected');
  }
}

function selectButtonMinor(which)
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  if(typeof(document.getElementById('mdgToolbar_'+which)) == 'object')
  {
    unselectAllButtonsMinor();
    $('mdgToolbar_'+which).addClass('selected');
  }
}

