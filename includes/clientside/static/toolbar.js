// Page toolbar - selecting buttons

window.unselectAllButtonsMajor = function()
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  obj = document.getElementById('pagebar_main').firstChild;
  while(obj)
  {
    if(obj.id == 'mdgToolbar_article' || obj.id == 'mdgToolbar_discussion')
    {
      $dynano(obj).rmClass('selected');
    }
    obj = obj.nextSibling;
  }
}

window.unselectAllButtonsMinor = function()
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  obj = document.getElementById('pagebar_main').firstChild.nextSibling;
  while(obj)
  {
    if ( !$dynano(obj).hasClass('selected') )
    {
      obj = obj.nextSibling;
      continue;
    }
    if(obj.id != 'mdgToolbar_article' && obj.id != 'mdgToolbar_discussion')
    {
      if ( obj.className )
        $dynano(obj).rmClass('selected');
    }
    obj = obj.nextSibling;
  }
}

window.selectButtonMajor = function(which)
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  var dom = document.getElementById('mdgToolbar_'+which);
  if ( !dom )
    return false;
  if(typeof(dom) == 'object')
  {
    unselectAllButtonsMajor();
    $dynano('mdgToolbar_'+which).addClass('selected');
  }
}

window.selectButtonMinor = function(which)
{
  if ( !document.getElementById('pagebar_main') )
    return false;
  if(typeof(document.getElementById('mdgToolbar_'+which)) == 'object')
  {
    unselectAllButtonsMinor();
    $dynano('mdgToolbar_'+which).addClass('selected');
  }
}

