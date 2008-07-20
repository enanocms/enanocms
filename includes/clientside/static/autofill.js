/**
 * Javascript auto-completion for form fields. This supercedes the code in autocomplete.js for MOZILLA ONLY. It doesn't seem to work real
 * well with other browsers yet.
 */

// fill schemas
var autofill_schemas = {};

// default, generic schema
autofill_schemas.generic = {
  template: '<div id="--ID--_region" spry:region="autofill_ds_--CLASS--" class="tblholder">' + "\n" +
            '  <table border="0" cellspacing="1" cellpadding="3" style="font-size: smaller;">' + "\n" +
            '    <tr spry:repeat="autofill_region_--ID--">' + "\n" +
            '      <td class="row1" spry:suggest="{name}">{name}</td>' + "\n" +
            '    </tr>' + "\n" +
            '  </table>' + "\n" +
            '</div>',
  
  init: function(element, fillclass)
  {
    // calculate positions before spry f***s everything up
    var top = $dynano(element).Top() + $dynano(element).Height() - 10; // tblholder has 10px top margin
    var left = $dynano(element).Left();
    
    // dataset name
    var ds_name = 'autofill_ds_' + fillclass;
    
    var allow_anon = ( params.allow_anon ) ? '1' : '0';
    // setup the dataset
    window[ds_name] = new Spry.Data.JSONDataSet(makeUrlNS('Special', 'Autofill', 'type=' + fillclass + '&allow_anon' + allow_anon));
    
    // inject our HTML wrapper
    var template = this.template.replace(new RegExp('--ID--', 'g'), element.id).replace(new RegExp('--CLASS--', 'g', fillclass));
    var wrapper = element.parentNode; // document.createElement('div');
    if ( !wrapper.id )
      wrapper.id = 'autofill_wrap_' + element.id;
    
    // a bunch of hacks to add a spry wrapper
    wrapper.innerHTML = template + wrapper.innerHTML;
    
    var autosuggest = new Spry.Widget.AutoSuggest(wrapper.id, element.id + '_region', window[ds_name], 'name', {loadFromServer: true, urlParam: 'userinput', hoverSuggestClass: 'row2', minCharsType: 3});
    var regiondiv = document.getElementById(element.id + '_region');
    regiondiv.style.position = 'absolute';
    regiondiv.style.top = top + 'px';
    regiondiv.style.left = left + 'px';
  }
};

function autofill_init_element(element, params)
{
  if ( !Spry.Data );
    load_spry_data();
  
  params = params || {};
  // assign an ID if it doesn't have one yet
  if ( !element.id )
  {
    element.id = 'autofill_' + Math.floor(Math.random() * 100000);
  }
  var id = element.id;
  
  // get the fill type
  var fillclass = element.className;
  fillclass = fillclass.split(' ');
  fillclass = fillclass[1];
  
  var schema = ( autofill_schemas[fillclass] ) ? autofill_schemas[fillclass] : autofill_schemas['generic'];
  if ( typeof(schema.init) != 'function' )
  {
    schema.init = autofill_schemas.generic.init;
  }
  schema.init(element, fillclass, params);
  
  element.af_initted = true;
}

var autofill_onload = function()
{
  if ( this.loaded )
  {
    return true;
  }
  
  autofill_schemas.username = {
    template: '<div id="--ID--_region" spry:region="autofill_ds_username" class="tblholder">' + "\n" +
              '  <table border="0" cellspacing="1" cellpadding="3" style="font-size: smaller;">' + "\n" +
              '    <tr>' + "\n" +
              '      <th>' + $lang.get('user_autofill_heading_suggestions') + '</th>' + "\n" +
              '    </tr>' + "\n" +
              '    <tr spry:repeat="autofill_region_--ID--">' + "\n" +
              '      <td class="row1" spry:suggest="{name}">{name_highlight}<br /><small style="{rank_style}">{rank_title}</small></td>' + "\n" +
              '    </tr>' + "\n" +
              '  </table>' + "\n" +
              '</div>',
    
    init: function(element, fillclass, params)
    {
      // calculate positions before spry f***s everything up
      var top = $dynano(element).Top() + $dynano(element).Height() - 10; // tblholder has 10px top margin
      var left = $dynano(element).Left();
      
      var allow_anon = ( params.allow_anon ) ? '1' : '0';
      // setup the dataset
      if ( !window.autofill_ds_username )
      {
        window.autofill_ds_username = new Spry.Data.JSONDataSet(makeUrlNS('Special', 'Autofill', 'type=' + fillclass + '&allow_anon' + allow_anon));
      }
      
      // inject our HTML wrapper
      var template = this.template.replace(new RegExp('--ID--', 'g'), element.id);
      var wrapper = element.parentNode; // document.createElement('div');
      if ( !wrapper.id )
        wrapper.id = 'autofill_wrap_' + element.id;
      
      // a bunch of hacks to add a spry wrapper
      wrapper.innerHTML = template + wrapper.innerHTML;
      
      var autosuggest = new Spry.Widget.AutoSuggest(wrapper.id, element.id + '_region', window.autofill_ds_username, 'name', {loadFromServer: true, urlParam: 'userinput', hoverSuggestClass: 'row2', minCharsType: 3});
      var regiondiv = document.getElementById(element.id + '_region');
      regiondiv.style.position = 'absolute';
      regiondiv.style.top = top + 'px';
      regiondiv.style.left = left + 'px';
    }
  };
  
  autofill_schemas.page = {
    template: '<div id="--ID--_region" spry:region="autofill_region_--ID--" class="tblholder">' + "\n" +
              '  <table border="0" cellspacing="1" cellpadding="3" style="font-size: smaller;">' + "\n" +
              '    <tr>' + "\n" +
              '      <th colspan="2">' + $lang.get('page_autosuggest_heading') + '</th>' + "\n" +
              '    </tr>' + "\n" +
              '    <tr spry:repeat="autofill_region_--ID--">' + "\n" +
              '      <td class="row1" spry:suggest="{page_id}">{pid_highlight}<br /><small>{name_highlight}</small></td>' + "\n" +
              '    </tr>' + "\n" +
              '  </table>' + "\n" +
              '</div>'
  }
  
  var inputs = document.getElementsByClassName('input', 'autofill');
  
  if ( inputs.length > 0 )
  {
    // we have at least one input that needs to be made an autofill element.
    // is spry data loaded?
    if ( !Spry.Data )
    {
      load_spry_data();
      return true;
    }
  }
  
  this.loaded = true;
  
  for ( var i = 0; i < inputs.length; i++ )
  {
    autofill_init_element(inputs[i]);
  }
}

addOnloadHook(autofill_onload);

function autofill_force_region_refresh()
{
  Spry.Data.initRegions();
}

function AutofillUsername(element, event, allowanon)
{
  element.onkeyup = element.onkeydown = element.onkeypress = function(e) {};
  
  element.className = 'autofill username';
  
  allowanon = allowanon ? true : false;
  autofill_init_element(element, {
      allow_anon: allowanon
    });
}

// load spry data components
function load_spry_data()
{
  var scripts = [ 'SpryData.js', 'SpryJSONDataSet.js', 'SpryAutoSuggest.js' ];
  for ( var i = 0; i < scripts.length; i++ )
  {
    load_component(scripts[i]);
  }
  autofill_onload();
}
