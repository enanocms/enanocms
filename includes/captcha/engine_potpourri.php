<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Copyright (C) 2006-2009 Dan Fuhry
 * captcha.php - visual confirmation system used during registration
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * A more complex captcha engine that relies on GD.
 * @package Enano
 * @subpackage User management
 * @copyright 2007-2008 Dan Fuhry
 * @copyright 2002-2008 phpBB Group
 */
 
class captcha_engine_potpourri extends captcha_base
{
	var $captcha_config;
	
	function __construct($sid, $r = false)
	{
		parent::__construct($sid, $r);
		
		$hex = Array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
		$latticecolor = '#';
		for ( $i = 0; $i < 6; $i++ )
			$latticecolor .= $hex[mt_rand(0, 15)];
		
		$this->captcha_config = array (
			'width' => '350',
			'height' => '90',
			'background_color' => '#E5ECF9',
			'jpeg' => '0',
			'jpeg_quality' => '95',
			'pre_letters' => '1',
			'pre_letters_great' => '0',
			'font' => '1',
			'chess' => '2',
			'ellipses' => '2',
			'arcs' => '2',
			'lines' => '2',
			'image' => '0',
			'gammacorrect' => '0.8',
			'foreground_lattice_x' => (string)mt_rand(25, 30),
			'foreground_lattice_y' => (string)mt_rand(25, 30),
			'lattice_color' => $latticecolor,
		);
	}
	
	function make_image()
	{
		$code = $this->get_code();
		
		// Prefs
		$total_width = $this->captcha_config['width'];
		$total_height = $this->captcha_config['height'];
		
		$hex_bg_color = $this->get_rgb($this->captcha_config['background_color']);
		$bg_color = array();
		$bg_color = explode(",", $hex_bg_color);
		
		$jpeg = $this->captcha_config['jpeg'];
		$img_quality = $this->captcha_config['jpeg_quality'];
		// Max quality is 95
		
		$pre_letters = $this->captcha_config['pre_letters'];
		$pre_letter_great = $this->captcha_config['pre_letters_great'];
		$rnd_font = $this->captcha_config['font'];
		$chess = $this->captcha_config['chess'];
		$ellipses = $this->captcha_config['ellipses'];
		$arcs = $this->captcha_config['arcs'];
		$lines = $this->captcha_config['lines'];
		$image = $this->captcha_config['image'];
		
		$gammacorrect = $this->captcha_config['gammacorrect'];
		
		$foreground_lattice_y = $this->captcha_config['foreground_lattice_y'];
		$foreground_lattice_x = $this->captcha_config['foreground_lattice_x'];
		$hex_lattice_color = $this->get_rgb($this->captcha_config['lattice_color']);
		$rgb_lattice_color = array();
		$rgb_lattice_color = explode(",", $hex_lattice_color);
		
		$font_debug = false;
		
		// Fonts and images init
		if ($image)
		{
			$bg_imgs = array();
			if ($img_dir = opendir(ENANO_ROOT.'/includes/captcha/pics/'))
			{
				while (true == ($file = @readdir($img_dir))) 
				{ 
					if ((substr(strtolower($file), -3) == 'jpg') || (substr(strtolower($file), -3) == 'gif'))    
					{         
						$bg_imgs[] = $file; 
					}     
				}
				closedir($img_dir);
			}
			// Grab a random Background Image or set FALSE if none was found
			$bg_img = ( count($bg_imgs) ) ? rand(0, (count($bg_imgs)-1)) : false;
		}
		
		$fonts = array();
		if ($fonts_dir = opendir(ENANO_ROOT . '/includes/captcha/fonts/'))
		{
			while (true == ($file = @readdir($fonts_dir))) 
			{ 
				if ((substr(strtolower($file), strlen($file)-3, strlen($file)) == 'ttf'))
				{
					$fonts[] = $file; 
				}     
			}
			closedir($fonts_dir);
		} else {
			die('Error reading directory: '.ENANO_ROOT.'/includes/captcha/fonts/');
		}
		$font = mt_rand(0, (count($fonts)-1));
		
		// Generate
		$image = ($this->gdVersion() >= 2) ? imagecreatetruecolor($total_width, $total_height) : imagecreate($total_width, $total_height);
		$background_color = imagecolorallocate($image, $bg_color[0], $bg_color[1], $bg_color[2]);
		imagefill($image, 0, 0, $background_color);
		
		// Generate backgrund
		if ($chess == '1' || $chess == '2' && rand(0,1))
		{
			// Draw rectangles
			for($i = 0; $i <= 8; $i++)
			{
				$rectanglecolor = imagecolorallocate($image, rand(100,200),rand(100,200),rand(100,200));
				imagefilledrectangle($image, 0, 0, round($total_width-($total_width/8*$i)), round($total_height), $rectanglecolor);
				$rectanglecolor = imagecolorallocate($image, rand(100,200),rand(100,200),rand(100,200));
				imagefilledrectangle($image, 0, 0, round($total_width-($total_width/8*$i)), round($total_height/2), $rectanglecolor);
			}
		}
		if ($ellipses == '1' || $ellipses == '2' && rand(0,1))
		{
			// Draw random ellipses
			for ($i = 1; $i <= 60; $i++)
			{
				$ellipsecolor = imagecolorallocate($image, rand(100,250),rand(100,250),rand(100,250));
				imagefilledellipse($image, round(rand(0, $total_width)), round(rand(0, $total_height)), round(rand(0, $total_width/8)), round(rand(0, $total_height/4)), $ellipsecolor);	
			}
		}
		if ($arcs == '1' || $arcs == '2' && rand(0,1))
		{
			// Draw random partial ellipses
			for ($i = 0; $i <= 30; $i++)
			{
				$linecolor = imagecolorallocate($image, rand(120,255),rand(120,255),rand(120,255));
				$cx = round(rand(1, $total_width));
				$cy = round(rand(1, $total_height));
				$int_w = round(rand(1, $total_width/2));
				$int_h = round(rand(1, $total_height));
				imagearc($image, $cx, $cy, $int_w, $int_h, round(rand(0, 190)), round(rand(191, 360)), $linecolor);
				imagearc($image, $cx-1, $cy-1, $int_w, $int_h, round(rand(0, 190)), round(rand(191, 360)), $linecolor);
			}
		}
		if ($lines == '1' || $lines == '2' && rand(0,1))
		{
			// Draw random lines
			for ($i = 0; $i <= 50; $i++)
			{
				$linecolor = imagecolorallocate($image, rand(120,255),rand(120,255),rand(120,255));
				imageline($image, round(rand(1, $total_width*3)), round(rand(1, $total_height*5)), round(rand(1, $total_width/2)), round(rand(1, $total_height*2)), $linecolor);
			}
		}
		
		$text_color_array = array('255,51,0', '51,77,255', '204,51,102', '0,153,0', '255,166,2', '255,0,255', '255,0,0', '0,255,0', '0,0,255', '0,255,255');
		shuffle($text_color_array);
		$pre_text_color_array = array('255,71,20', '71,20,224', '224,71,122', '20,173,20', '255,186,22', '25,25,25');
		shuffle($pre_text_color_array);
		$white = imagecolorallocate($image, 255, 255, 255);
		$gray = imagecolorallocate($image, 100, 100, 100);
		$black = imagecolorallocate($image, 0, 0, 0);
		$lattice_color = imagecolorallocate($image, $rgb_lattice_color[0], $rgb_lattice_color[1], $rgb_lattice_color[2]);
		
		$x_char_position = (round(($total_width - 12) / strlen($code)) + mt_rand(-3, 5));
		
		for ($i = 0; $i < strlen($code); $i++)
		{
			mt_srand((double)microtime()*1000000);
		
			$char = $code{$i};
			$size = mt_rand(floor($total_height / 3.5), ceil($total_height / 2.8));
			$font = ($rnd_font) ? rand(0, (count($fonts)-1)) : $font;
			$angle = mt_rand(-30, 30);
		
			$char_pos = array();
			$char_pos = imagettfbbox($size, $angle, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
			$letter_width = abs($char_pos[0]) + abs($char_pos[4]);
			$letter_height = abs($char_pos[1]) + abs($char_pos[5]);
		
			$x_pos = ($x_char_position / 4) + ($i * $x_char_position);
			($i == strlen($code)-1 && $x_pos >= ($total_width - ($letter_width + 5))) ? $x_pos = ($total_width - ($letter_width + 5)) : '';
			$y_pos = mt_rand(($size * 1.4 ), $total_height - ($size * 0.4));
		
		//	Pre letters
			$size = ($pre_letter_great) ? $size + (2 * $pre_letters) : $size - (2 * $pre_letters);
			for ($count = 1; $count <= $pre_letters; $count++)
			{
				$pre_angle = $angle + mt_rand(-20, 20);
		
				$text_color = $pre_text_color_array[mt_rand(0,count($pre_text_color_array)-1)];
				$text_color = explode(",", $text_color);
				$textcolor = imagecolorallocate($image, $text_color[0], $text_color[1], $text_color[2]);
		
				imagettftext($image, $size, $pre_angle, $x_pos, $y_pos-2, $white, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
				imagettftext($image, $size, $pre_angle, $x_pos+2, $y_pos, $black, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
				imagettftext($image, $size, $pre_angle, $x_pos+1, $y_pos-1, $textcolor, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
		
				$size = ($pre_letter_great) ? $size - 2 : $size + 2;
			}
		
		//	Final letters
			$text_color = $text_color_array[mt_rand(0,count($text_color_array)-1)];
			$text_color = explode(",", $text_color);
			$textcolor = imagecolorallocate($image, $text_color[0], $text_color[1], $text_color[2]);
		
			imagettftext($image, $size, $angle, $x_pos, $y_pos-2, $white, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
			imagettftext($image, $size, $angle, $x_pos+2, $y_pos, $black, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
			imagettftext($image, $size, $angle, $x_pos+1, $y_pos-1, $textcolor, ENANO_ROOT.'/includes/captcha/fonts/'.$fonts[$font], $char);
		}
		
		
		($gammacorrect) ? imagegammacorrect($image, 1.0, $gammacorrect) : '';
		
		// Generate a white lattice in foreground
		if ($foreground_lattice_y)
		{
			// x lines
			$ih = round($total_height / $foreground_lattice_y);
			for ($i = 0; $i <= $ih; $i++)
			{
				imageline($image, 0, $i*$foreground_lattice_y, $total_width, $i*$foreground_lattice_y, $lattice_color);
			}
		}
		if ($foreground_lattice_x)
		{
			// y lines
			$iw = round($total_width / $foreground_lattice_x);
			for ($i = 0; $i <= $iw; $i++)
			{
				imageline($image, $i*$foreground_lattice_x, 0, $i*$foreground_lattice_x, $total_height, $lattice_color);
			}
		}
		
		// Font debug
		if ($font_debug && !$rnd_font)
		{
			imagestring($image, 5, 2, 0, $fonts[$font], $white);
			imagestring($image, 5, 5, 0, $fonts[$font], $white);
			imagestring($image, 5, 4, 2, $fonts[$font], $gray);
			imagestring($image, 5, 3, 1, $fonts[$font], $black);
		}
		
		// Display
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") ." GMT"); 
		header("Pragma: no-cache"); 
		header("Cache-Control: no-store, no-cache, max-age=0, must-revalidate");
		(!$jpeg) ? header("Content-Type: image/png") : header("Content-Type: image/jpeg");
		
		(!$jpeg) ? imagepng($image) : imagejpeg($image, '', $img_quality);
		imagedestroy($image);
	}
	
	// Function get_rgb by Frank Burian
	// http://www.phpfuncs.org/?content=show&id=46
	function get_rgb($hex) { 
		$hex_array = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 
				'A' => 10, 'B' => 11, 'C' => 12, 'D' => 13, 'E' => 14, 
				'F' => 15); 
		$hex = str_replace('#', '', strtoupper($hex)); 
		if (($length = strlen($hex)) == 3) { 
				$hex = $hex{0}.$hex{0}.$hex{1}.$hex{1}.$hex{2}.$hex{2}; 
				$length = 6; 
		} 
		if ($length != 6 or strlen(str_replace(array_keys($hex_array), '', $hex))) 
				return NULL; 
		$rgb['r'] = $hex_array[$hex{0}] * 16 + $hex_array[$hex{1}]; 
		$rgb['g'] = $hex_array[$hex{2}] * 16 + $hex_array[$hex{3}]; 
		$rgb['b']= $hex_array[$hex{4}] * 16 + $hex_array[$hex{5}]; 
		return $rgb['r'].','.$rgb['g'].','.$rgb['b']; 
	}
	
	// Function  gdVersion by Hagan Fox
	// http://de3.php.net/manual/en/function.gd-info.php#52481
	function gdVersion($user_ver = 0)
	{
 		if (! extension_loaded('gd')) { return; }
 		static $gd_ver = 0;
 		// Just accept the specified setting if it's 1.
 		if ($user_ver == 1) { $gd_ver = 1; return 1; }
 		// Use the static variable if function was called previously.
 		if ($user_ver !=2 && $gd_ver > 0 ) { return $gd_ver; }
 		// Use the gd_info() function if possible.
 		if (function_exists('gd_info')) {
 				$ver_info = gd_info();
 				preg_match('/\d/', $ver_info['GD Version'], $match);
 				$gd_ver = $match[0];
 				return $match[0];
 		}
 		// If phpinfo() is disabled use a specified / fail-safe choice...
 		if (preg_match('/phpinfo/', ini_get('disable_functions'))) {
 				if ($user_ver == 2) {
 						$gd_ver = 2;
 						return 2;
 				} else {
 						$gd_ver = 1;
 						return 1;
 				}
 		}
 		// ...otherwise use phpinfo().
 		ob_start();
 		phpinfo(8);
 		$info = ob_get_contents();
 		ob_end_clean();
 		$info = stristr($info, 'gd version');
 		preg_match('/\d/', $info, $match);
 		$gd_ver = $match[0];
 		return $match[0];
	}
}
