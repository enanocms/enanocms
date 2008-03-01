/*
 * Enano client-side localization library
 */

var Language = function(lang_id)
{
  if ( typeof(enano_lang) != 'object' )
    return false;
  if ( typeof(enano_lang[lang_id]) != 'object' )
    return false;
  this.strings = enano_lang[lang_id];
  this.lang_id = lang_id;
  
  this.get = function(string_id, subst)
  {
    if ( window.console )
    {
      try {
        window.console.log('$lang(' + this.lang_id + '): requested string: ' + string_id);
      }
      catch(e)
      {}
    }
    var catname = string_id.substr(0, string_id.indexOf('_'));
    var string_name = string_id.substr(string_id.indexOf('_') + 1);
    if ( typeof(this.strings[catname]) != 'object' )
      return string_id;
    if ( typeof(this.strings[catname][string_name]) != 'string' )
      return string_id;
    return this.perform_subst(this.strings[catname][string_name], subst);
  }
  
  this.perform_subst = function(str, subst)
  {
    var this_regex = /%this\.([a-z0-9_]+)%/;
    var match;
    while ( str.match(this_regex) )
    {
      match = str.match(this_regex);
      str = str.replace(match[0], this.get(match[1]));
    }
    // hackish workaround for %config.*%
    str = str.replace(/%config\.([a-z0-9_]+)%/g, '%$1%');
    if ( typeof(subst) == 'object' )
    {
      for ( var i in subst )
      {
        if ( !i.match(/^([a-z0-9_]+)$/) )
          continue;
        var regex = new RegExp('%' + i + '%', 'g');
        str = str.replace(regex, subst[i]);
      }
    }
    return str;
  }
  
}

var $lang;

var language_onload = function()
{
  if ( typeof(enano_lang) != 'object' )
  {
    language_onload_resched();
    return true;
  }
  if ( !enano_lang[ENANO_LANG_ID] )
  {
    language_onload_resched();
    return true;
  }
  $lang = new Language(ENANO_LANG_ID);
}

// Rescheduler for language onload - allows delaying init if the string list
// isn't ready yet
function language_onload_resched()
{
  setTimeout('language_onload();', 200);
}

addOnloadHook(language_onload);

