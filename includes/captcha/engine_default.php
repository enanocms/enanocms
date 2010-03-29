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
 *
 * This file contains code written by Paul Sohier (www.paulscripts.nl). The CAPTCHA code was ported from the phpBB Better
 * Captcha mod, and has been released under the GPLv2 by the original author.
 */

/**
 * The default CAPTCHA engine. Generates medium-strength captchas with very good performance.
 * @package Enano
 * @subpackage User management
 * @copyright 2007-2008 Dan Fuhry
 * @copyright Paul Sohier
 */
 
class captcha_engine_default extends captcha_base
{
	function make_image()
	{
		$code =& strtoupper($this->get_code());
		
		/**
			* The next part is orginnaly written by ted from mastercode.nl and modified for use in Enano.
			**/
		header("content-type:image/png");
		header('Cache-control: no-cache, no-store');
		$breedte = 320;
		$hoogte = 60;
		$img = imagecreatetruecolor($breedte,$hoogte);
		$achtergrond = imagecolorallocate($img, $this->color("bg"), $this->color("bg"), $this->color("bg"));
		
		imagefilledrectangle($img, 0, 0, $breedte-1, $hoogte-1, $achtergrond);
		for($g = 0;$g < 30; $g++)
		{
			$t = $this->dss_rand();
			$t = $t[0];
					
			$ypos = rand(0,$hoogte);
			$xpos = rand(0,$breedte);
					
			$kleur = imagecolorallocate($img, $this->color("bgtekst"), $this->color("bgtekst"), $this->color("bgtekst"));
					
			imagettftext($img, $this->size(), $this->move(), $xpos, $ypos, $kleur, $this->font(), $t);
		} 			
		$stukje = $breedte / (strlen($code) + 3);
		
		for($j = 0;$j < strlen($code); $j++)
		{
			
			
			$tek = $code[$j];
			$ypos = rand(33,43);
			$xpos = $stukje * ($j+1);
					
			$kleur2 = imagecolorallocate($img, $this->color("tekst"), $this->color("tekst"), $this->color("tekst"));
			
			imagettftext($img, $this->size(), $this->move(), $xpos, $ypos, $kleur2, $this->font() , $tek);
		}
			
		imagepng($img);
	}
	
	/**
		* Some functions :)
		* Also orginally written by mastercode.nl
		**/
	/**
		* Function to create a random color
		* @param $type string Mode for the color
		* @return int
		**/
	function color($type)
	{
		switch($type)
		{
			case "bg": 
				$kleur = rand(224,255); 
			break;
			case "tekst": 
				$kleur = rand(0,127); 
			break;
			case "bgtekst": 
				$kleur = rand(200,224); 
			break;
			default: 
				$kleur = rand(0,255); 
			break;
		}
		return $kleur;
	}
	/**
		* Function to ranom the size
		* @return int
		**/
	function size()
	{
		$grootte = rand(14,30);
		return $grootte;
	}
	/**
		* Function to random the posistion
		* @return int
		**/
	function move()
	{
		$draai = rand(-25,25);
		return $draai;
	}
	
	/**
		* Function to return a ttf file from fonts map
		* @return string
		**/
	function font()
	{
		$f = @opendir(ENANO_ROOT . '/includes/captcha/fonts/');
		if(!$f) die('Can\'t open includes/captcha/fonts/ for reading');
		$ar = array();
		while(($file = @readdir($f)) !== false)
		{
			if(!in_array($file, array('.','..')) && strstr($file, '.ttf'))
			{
				$ar[] = $file;
			}
		}
		if(count($ar))
		{
			shuffle($ar);
			$i = rand(0,(count($ar) - 1));
			return ENANO_ROOT . '/includes/captcha/fonts/' . $ar[$i];
		}
	}
	function dss_rand()
	{
		$val = microtime() .  mt_rand();
		$val = md5($val . 'a');
		return substr($val, 4, 16);
	}
}
