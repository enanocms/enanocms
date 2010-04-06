<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * Installation package
 * login.php - Installer login information stage
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

if ( !defined('IN_ENANO_INSTALL') )
	die();

// AES functions required
require_once( ENANO_ROOT . '/includes/rijndael.php' );
require_once( ENANO_ROOT . '/includes/constants.php' );
require_once( ENANO_ROOT . '/includes/dbal.php' );
require_once( ENANO_ROOT . '/includes/sessions.php' );

$ui->add_header('<script type="text/javascript" src="includes/js/formutils.js"></script>');
$ui->show_header();

// generate the HTML for the form, and store the public and private key in the temporary config
$aes_form = sessionManager::generate_aes_form($dh_keys);
$fp = @fopen(ENANO_ROOT . '/config.new.php', 'a+');
if ( !$fp )
	die('Couldn\'t open the config for writing');
fwrite($fp, "
// DiffieHellman parameters
\$dh_public = '{$dh_keys['public']}';
\$dh_private = '{$dh_keys['private']}';
\$aes_fallback = '{$dh_keys['aes']}';
");
fclose($fp);

// FIXME: l10n
?>
<h3><?php echo $lang->get('login_welcome_title'); ?></h3>
<?php echo $lang->get('login_welcome_body'); ?>

<script type="text/javascript">

	// <![CDATA[
	
	function verify(target)
	{
		var frm = document.forms [ 'install_login' ];
		var undefined;
		var passed = true;
		
		var data = {
			username: frm.username.value,
			password: frm.password.value,
			password_confirm: frm.password_confirm.value,
			email: frm.email.value
		};
		
		if ( !target )
			target = { name: undefined };
		
		if ( target.name == undefined || target.name == 'username' )
		{
			var matches = validateUsername(data.username);
			document.getElementById('s_username').src = ( matches ) ? img_good : img_bad;
			if ( !matches )
				passed = false;
		}
		
		if ( target.name == undefined || target.name == 'password' || target.name == 'password_confirm' )
		{
			var matches = ( data.password.length >= 6 && data.password == data.password_confirm ) ;
			document.getElementById('s_password').src = ( matches ) ? img_good : img_bad;
			if ( !matches )
				passed = false;
		}
		
		if ( target.name == undefined || target.name == 'email' )
		{
			var matches = validateEmail(data.email);
			document.getElementById('s_email').src = ( matches ) ? img_good : img_bad;
			if ( !matches )
				passed = false;
		}
		
		return passed;
	}
	
	function verify_submit()
	{
		if ( verify() )
			return true;
		alert($lang.get('login_err_verify_failure'));
		return false;
	}
	
	function submit_encrypt()
	{
		return runEncryption();
	}
	
	addOnloadHook(function()
		{
			load_component('crypto');
			load_component('l10n');
		});
	
	// ]]>

</script>

<form action="install.php?stage=confirm" method="post" name="install_login" onsubmit="return ( verify_submit() && submit_encrypt() );"><?php
	foreach ( $_POST as $key => &$value )
	{
		if ( !preg_match('/^[a-z0-9_]+$/', $key) )
			die('...really?');
		if ( $key == '_cont' )
			continue;
		$value_clean = str_replace(array('\\', '"', '<', '>'), array('\\\\', '\\"', '&lt;', '&gt;'), $value);
		echo "\n  <input type=\"hidden\" name=\"$key\" value=\"$value_clean\" />";
	}
	
	$https = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' );
	$scriptpath_full = 'http' . ( $https ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . scriptPath . '/';
	?>
	
	<table border="0" cellspacing="0" cellpadding="10" style="width: 100%;">
	
		<tr>
			<td style="width: 50%;">
				<b><?php echo $lang->get('login_field_username'); ?></b>
			</td>
			<td style="width: 50%;">
				<input type="text" tabindex="1" name="username" size="15" onkeyup="verify(this);" />
			</td>
			<td>
				<img id="s_username" alt="Good/bad icon" src="../images/checkbad.png" />
			</td>
		</tr>
		
		<tr>
			<td>
				<b><?php echo $lang->get('login_field_password'); ?></b><br />
				<?php echo $lang->get('login_aes_blurb'); ?>
			</td>
			<td>
				<input type="password" tabindex="2" name="password" size="15" onkeyup="password_score_field(this); verify(this);" /><br />
				<br />
				<div id="pwmeter"></div>
				<br />
				<input type="password" tabindex="3" name="password_confirm" size="15" onkeyup="verify(this);" /> <small><?php echo $lang->get('login_field_password_confirm'); ?></small>
			</td>
			<td>
				<img id="s_password" alt="Good/bad icon" src="../images/checkbad.png" />
			</td>
		</tr>
		
		<tr>
			<td style="width: 50%;">
				<b><?php echo $lang->get('login_field_email'); ?></b>
			</td>
			<td style="width: 50%;">
				<input type="text" tabindex="4" name="email" size="30" onkeyup="verify(this);" />
			</td>
			<td>
				<img id="s_email" alt="Good/bad icon" src="../images/checkbad.png" />
			</td>
		</tr>
	
	</table>
	
	<?php
	// hidden form fields/DH keygen
	echo $aes_form;
	?>
	
	<div style="text-align: center;">
		<input type="submit" name="_cont" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
	</div>
</form>

<?php echo sessionManager::aes_javascript('install_login', 'password'); ?>

