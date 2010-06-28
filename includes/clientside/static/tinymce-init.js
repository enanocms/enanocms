//
// TinyMCE support
//

// Check tinyMCE to make sure its init is finished
var initTinyMCE = function(e)
{
	if ( typeof(tinyMCE_GZ) == 'object' )
	{
		if ( !KILL_SWITCH && !DISABLE_MCE )
		{
			tinyMCE_GZ.init(enano_tinymce_gz_options, function()
				{
					tinyMCE.init(enano_tinymce_options);
				});
			tinymce_initted = true;
		}
	}
};

// editor options
if ( document.getElementById('mdgCss') )
{
	var css_url = document.getElementById('mdgCss').href;
}
else
{
	var css_url = scriptPath + '/includes/clientside/css/enano_shared.css';
}

var do_popups = ( is_Safari ) ? '' : ',inlinepopups';
var _skin = ( typeof(tinymce_skin) == 'string' ) ? tinymce_skin : 'default';
var tinymce_initted = false;

var html = document.getElementsByTagName('html')[0];
var direction = typeof(html.dir) != 'undefined' ? html.dir : 'ltr';

var enano_tinymce_options = {
	mode : "none",
	plugins : 'table,save,safari,pagebreak,style,layer,advhr,insertdatetime,searchreplace,spellchecker,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,wordcount' + do_popups,
	theme : 'advanced',
	skin : _skin,
	theme_advanced_resize_horizontal : false,
	theme_advanced_resizing : true,
	theme_advanced_toolbar_location : "top",
	theme_advanced_toolbar_align : "left",
	theme_advanced_buttons1 : "save,|,bold,italic,underline,strikethrough,|,spellchecker,|,justifyleft,justifycenter,justifyright,justifyfull,|,forecolor,backcolor,|,formatselect,|,fontselect,fontsizeselect",
	theme_advanced_buttons3_add_before : "tablecontrols,separator",
	theme_advanced_buttons3_add_after : "|,fullscreen",
	theme_advanced_statusbar_location : 'bottom',
	noneditable_noneditable_class : 'mce_readonly',
	content_css : css_url,
	spellchecker_rpc_url : scriptPath + '/includes/clientside/tinymce/plugins/spellchecker/rpc.php',
	directionality : direction
};

var enano_tinymce_gz_options = {
	plugins : 'table,save,safari,pagebreak,style,layer,advhr,insertdatetime,searchreplace,spellchecker,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras' + do_popups,
	themes : 'advanced',
	languages : 'en',
	disk_cache : true,
	debug : false
};

// load the script

if ( !KILL_SWITCH && !DISABLE_MCE )
{
	var uri = scriptPath + '/includes/clientside/tinymce/tiny_mce_gzip.js?327';
	var sc = document.createElement('script');
	sc.src = uri;
	sc.type = 'text/javascript';
	var head = document.getElementsByTagName('head')[0];
	head.appendChild(sc);
}
