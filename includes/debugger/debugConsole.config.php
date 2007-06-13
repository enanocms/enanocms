<?php
/**
 * debugConsole configuration
 *
 * @author Andreas Demmer <info@debugconsole.de>
 * @see <http://www.debugconsole.de>
 * @version 1.2.1
 * @package debugConsole_1.2.1
 */

/**
 * config container for debugConsole
 *
 * @var array
 */
$_debugConsoleConfig = array();

/**
 * use debugConsole
 */
$_debugConsoleConfig['active'] = TRUE;

/**
 * restrict access
 */
$_debugConsoleConfig['restrictions'] = array (
	'restrictAccess' => FALSE,
	'allowedClientAdresses' => array('127.0.0.1')
);

/**
 * set timezone, used for PHP >= 5.1.x
 */
putenv ('TZ=America/New_York');

/**
 * focus debugConsole at end of debug-run
 */
$_debugConsoleConfig['focus'] = TRUE;


/**
 * logfile configuration
 */
$_debugConsoleConfig['logfile'] = array (
	'enable' => FALSE,
	'path' => './',
	'filename' => 'log.txt',
	'disablePopup' => FALSE
);

/**
 * show or hide certain events
 */
$_debugConsoleConfig['filters'] = array (
	'debug' => TRUE,
	'watches' => TRUE,
	'checkpoints' => TRUE,
	'timers' => TRUE,
	'php_notices' => TRUE,
	'php_warnings' => TRUE,
	'php_errors' => TRUE,
	'php_suggestions' => FALSE
);

/**
 * popup dimensions in px
 */
$_debugConsoleConfig['dimensions'] = array (
	'width' => 300,
	'height' => 525
);

/**
 * javascript snippets, do not touch!
 */
$_debugConsoleConfig['javascripts'] = array (
	'openTag' => '<script language="JavaScript" type="text/javascript">',
	'closeTag' => '</script>',
	'openPopup' => 'debugConsole = window.open',
	'closePopup' => 'debugConsole.close()',
	'write' => 'debugConsole.document.write',
	'scroll' => 'debugConsole.scrollBy',
	'focus' => 'debugConsole.focus()'
);

/**
 * html snippets, do not touch!
 */
$_debugConsoleConfig['html'] = array (
	'header' => '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
  <html>
    <head>
      <title>debugConsole</title>
      <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
      <meta name="language" content="en" />
      <style type="text/css">
        * {
        	margin: 0px; 
        	padding: 0px;
        }
        
        body {
        	font-family: arial, sans-serif;
        	font-size: 0.7em;
        	color: black;
        	background-color: white;
        	padding: 5px;
        }
        
        h1 {
        	background-color: #888;
        	color: white;
        	font-size: 100%;
        	padding: 3px;
        	margin: 0px 0px 5px 0px;
        	border: 0px;
        	border-bottom: 4px solid #ccc;
        }
        
        p {
        	border: 1px solid #888;
        	border-left: 5px solid #888;
        	background-color: white;
        	padding: 3px;
        	margin: 5px 3px;
        }
        
        div {
            background-color: #eee;
            border: 1px solid #888;
            margin: 0px 0px 25px 0px;
        }
        
        .dump {
        }
        
        .dump .source {
            font-family: courier, sans-serif;
        }
                
        .backtrace {
        	display: block;
			color: #aaa;
        }
        
        .watch {
        	border-color: #BF1CBF;
        }

        .checkpoint {
        	border-color: #00E600;
        } 
        
        .timer {
        	border-color: blue;
        } 
        
        .notice, .suggestion {
        	border-color: orange;
        }
        
        .warning {
        	border-color: red;
        }

        .notice strong, .warning strong, .suggestion strong {
        	font-weight: bold;
        	display: block;
        }
    
        .notice strong, .suggestion strong {
        	color: orange;
        }
    
        .warning strong {
        	color: red;
        }
        
        .runtime {
            margin: 0px;
            padding: 0px;
            border: 0px;
            width: 100%;
            text-align: center;
            background-color: transparent;
            color: #666;
        }
               
      </style>
    </head>
  <body>',
	'footer' => '</body></html>'
);
?>