// Javascript routines for the page editor

var enano_tinymce_options = {
  mode : "exact",
  elements : '',
  plugins : 'table',
  theme_advanced_resize_horizontal : false,
  theme_advanced_resizing : true,
  theme_advanced_toolbar_location : "top",
  theme_advanced_toolbar_align : "left",
  theme_advanced_buttons1_add : "fontselect,fontsizeselect",
  theme_advanced_buttons3_add_before : "tablecontrols,separator",
  theme_advanced_statusbar_location : 'bottom'
};

var initTinyMCE = function(e)
{
  if ( typeof(tinyMCE) == 'object' )
  {
    if ( !KILL_SWITCH )
    {
      tinyMCE.init(enano_tinymce_options);
    }
  }
}
addOnloadHook(initTinyMCE);

