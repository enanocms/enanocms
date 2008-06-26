/*
 * Enano client-side localization library
 */

var Language = function(lang_id)
{
  var have_lang = false;
  
  if ( typeof(enano_lang) == 'object' )
  {
    if ( typeof(enano_lang[lang_id]) == 'object' )
    {
      have_lang = true;
    }
  }
  if ( !have_lang )
  {
    // load the language file
    load_show_win('strings');
    var ajax = ajaxMakeXHR();
    var uri = makeUrlNS('Special', 'LangExportJSON/' + lang_id);
    ajax.open('GET', uri, false);
    ajax.send(null);
    if ( ajax.readyState == 4 && ajax.status == 200 )
    {
      eval_global(ajax.responseText);
    }
  }
  
  if ( typeof(enano_lang) != 'object' )
    return false;
  if ( typeof(enano_lang[lang_id]) != 'object' )
    return false;
  this.strings = enano_lang[lang_id];
  this.lang_id = lang_id;
  this.placeholder = false;
  
  this.get = function(string_id, subst)
  {
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

var $lang = {
  get: function(t) { return t; },
  placeholder: true
};

var language_onload = function()
{
  $lang = new Language(ENANO_LANG_ID);
}

addOnloadHook(language_onload);
