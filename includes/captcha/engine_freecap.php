<?php
/************************************************************\
*
*		freeCap v1.4.1 Copyright 2005 Howard Yeend
*		www.puremango.co.uk
*
*    This file is part of freeCap.
*
*    freeCap is free software; you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation; either version 2 of the License, or
*    (at your option) any later version.
*
*    freeCap is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with freeCap; if not, write to the Free Software
*    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*
*
\************************************************************/

/**
 * A port of the freeCap captcha engine to Enano.
 */

class captcha_engine_freecap extends captcha_base
{
	
	var $site_tags = array();
	var $tag_pos = 0;
	var $rand_func = "mt_rand";
	var $seed_func = "mt_srand";
	var $hash_func = "sha1";
	var $output = "png";
	var $use_dict = false;
	var $dict_location = "";
	var $max_word_length = 7;
	var $col_type = 1;
	var $max_attempts = 20;
	var $font_locations = Array();
	var $bg_type = 3;
	var $blur_bg = false;
	var $bg_images = Array();
	var $merge_type = 0;
	var $morph_bg = true;
	var $im, $im2, $im3;
	var $font_size = 36;
	var $debug = false;
	
	function __construct($s, $r = false)
	{
		parent::__construct($s, $r);
		
		// try to avoid the 'free p*rn' method of CAPTCHA circumvention
		// see en.wikipedia.org/wiki/CAPTCHA for more info
		// $this->site_tags[0] = "To avoid spam, please do NOT enter the text if";
		// $this->site_tags[1] = "this site is not puremango.co.uk";
		// or more simply:
		// $site_tags[0] = "for use only on puremango.co.uk";
		// reword or add lines as you please
		// or if you don't want any text:
		$this->site_tags = array();
		
		// where to write the above:
		// 0=top
		// 1=bottom
		// 2=both
		$this->tag_pos = 1;
		
		// functions to call for random number generation
		// mt_rand produces 'better' random numbers
		// but if your server doesn't support it, it's fine to use rand instead
		$this->rand_func = "mt_rand";
		$this->seed_func = "mt_srand";
		
		// which type of hash to use?
		// possible values: "sha1", "md5", "crc32"
		// sha1 supported by PHP4.3.0+
		// md5 supported by PHP3+
		// crc32 supported by PHP4.0.1+
		$this->hash_func = $this->session_fetch('hash_func', 'sha1');
		// store in session so can validate in form processor
		
		// image type:
		// possible values: "jpg", "png", "gif"
		// jpg doesn't support transparency (transparent bg option ends up white)
		// png isn't supported by old browsers (see http://www.libpng.org/pub/png/pngstatus.html)
		// gif may not be supported by your GD Lib.
		$this->output = "png";
		
		// 0=generate pseudo-random string, true=use dictionary
		// dictionary is easier to recognise
		// - both for humans and computers, so use random string if you're paranoid.
		$this->use_dict = false;
		// if your server is NOT set up to deny web access to files beginning ".ht"
		// then you should ensure the dictionary file is kept outside the web directory
		// eg: if www.foo.com/index.html points to c:\website\www\index.html
		// then the dictionary should be placed in c:\website\dict.txt
		// test your server's config by trying to access the dictionary through a web browser
		// you should NOT be able to view the contents.
		// can leave this blank if not using dictionary
		$this->dict_location = ENANO_ROOT . "/includes/captcha/dicts/default.php";
		
		// used to calculate image width, and for non-dictionary word generation
		$this->max_word_length = 7;
		
		// text colour
		// 0=one random colour for all letters
		// 1=different random colour for each letter
		$this->col_type = 1;
		
		// maximum times a user can refresh the image
		// on a 6500 word dictionary, I think 15-50 is enough to not annoy users and make BF unfeasble.
		// further notes re: BF attacks in "avoid brute force attacks" section, below
		// on the other hand, those attempting OCR will find the ability to request new images
		// very useful; if they can't crack one, just grab an easier target...
		// for the ultra-paranoid, setting it to <5 will still work for most users
		$this->max_attempts = 20;
		
		// list of fonts to use
		// font size should be around 35 pixels wide for each character.
		// you can use my GD fontmaker script at www.puremango.co.uk to create your own fonts
		// There are other programs to can create GD fonts, but my script allows a greater
		// degree of control over exactly how wide each character is, and is therefore
		// recommended for 'special' uses. For normal use of GD fonts,
		// the GDFontGenerator @ http://www.philiplb.de is excellent for convering ttf to GD
		
		// the fonts included with freeCap *only* include lowercase alphabetic characters
		// so are not suitable for most other uses
		// to increase security, you really should add other fonts
		$this->font_locations = Array(
				//ENANO_ROOT . "/includes/captcha/fonts/assimila.ttf",
				//ENANO_ROOT . "/includes/captcha/fonts/elephant.ttf",
				//ENANO_ROOT . "/includes/captcha/fonts/swash_normal.ttf",
				//ENANO_ROOT . "/includes/captcha/fonts/.ttf",
				//ENANO_ROOT . "/includes/captcha/fonts/trekker_regular.ttf"
				ENANO_ROOT . "/includes/captcha/fonts/FreeMonoBold.ttf",
				ENANO_ROOT . "/includes/captcha/fonts/FreeSerifBold.ttf",
				ENANO_ROOT . "/includes/captcha/fonts/LiberationSans-Bold.ttf",
			);
		
		// background:
		// 0=transparent (if jpg, white)
		// 1=white bg with grid
		// 2=white bg with squiggles
		// 3=morphed image blocks
		// 'random' background from v1.3 didn't provide any extra security (according to 2 independent experts)
		// many thanks to http://ocr-research.org.ua and http://sam.zoy.org/pwntcha/ for testing
		// for jpgs, 'transparent' is white
		$this->bg_type = 3;
		// should we blur the background? (looks nicer, makes text easier to read, takes longer)
		$this->blur_bg = false;
		
		// for bg_type 3, which images should we use?
		// if you add your own, make sure they're fairly 'busy' images (ie a lot of shapes in them)
		$this->bg_images = Array(
				ENANO_ROOT . "/includes/captcha/pics/freecap_im1.jpg",
				ENANO_ROOT . "/includes/captcha/pics/freecap_im2.jpg",
				ENANO_ROOT . "/includes/captcha/pics/freecap_im3.jpg",
				ENANO_ROOT . "/includes/captcha/pics/freecap_im4.jpg",
				ENANO_ROOT . "/includes/captcha/pics/allyourbase.jpg"
			);
		
		// for non-transparent backgrounds only:
		// if 0, merges CAPTCHA with bg
		// if 1, write CAPTCHA over bg
		$this->merge_type = 0;
		// should we morph the bg? (recommend yes, but takes a little longer to compute)
		$this->morph_bg = true;
		
		// you shouldn't need to edit anything below this, but it's extensively commented if you do want to play
		// have fun, and email me with ideas, or improvements to the code (very interested in speed improvements)
		// hope this script saves some spam :-)
	}
	
	//////////////////////////////////////////////////////
	////// Functions:
	//////////////////////////////////////////////////////
	function make_seed() {
	// from http://php.net/srand
			list($usec, $sec) = explode(' ', microtime());
			return (float) $sec + ((float) $usec * 100000);
	}
	
	function rand_color() {
		$rf =& $this->rand_func;
		if($this->bg_type==3)
		{
			// needs darker colour..
			return $rf(10,100);
		} else {
			return $rf(60,170);
		}
	}
	
	function myImageBlur($im)
	{
		// w00t. my very own blur function
		// in GD2, there's a gaussian blur function. bunch of bloody show-offs... :-)
	
		$width = imagesx($im);
		$height = imagesy($im);
	
		$temp_im = ImageCreateTrueColor($width,$height);
		$bg = ImageColorAllocate($temp_im,150,150,150);
	
		// preserves transparency if in orig image
		ImageColorTransparent($temp_im,$bg);
	
		// fill bg
		ImageFill($temp_im,0,0,$bg);
	
		// anything higher than 3 makes it totally unreadable
		// might be useful in a 'real' blur function, though (ie blurring pictures not text)
		$distance = 1;
		// use $distance=30 to have multiple copies of the word. not sure if this is useful.
	
		// blur by merging with itself at different x/y offsets:
		ImageCopyMerge($temp_im, $im, 0, 0, 0, $distance, $width, $height-$distance, 70);
		ImageCopyMerge($im, $temp_im, 0, 0, $distance, 0, $width-$distance, $height, 70);
		ImageCopyMerge($temp_im, $im, 0, $distance, 0, 0, $width, $height, 70);
		ImageCopyMerge($im, $temp_im, $distance, 0, 0, 0, $width, $height, 70);
		// remove temp image
		ImageDestroy($temp_im);
	
		return $im;
	}
	
	function sendImage($pic)
	{
		// output image with appropriate headers
		global $output,$im,$im2,$im3;
		// ENANO - obfuscation technique disabled
		// (this is for ethical reasons - ask dan at enanocms.org for information on why)
		// Basically it outputs an X-Captcha header showing freeCap version, etc. Unnecessary
		// header(base64_decode("WC1DYXB0Y2hhOiBmcmVlQ2FwIDEuNCAtIHd3dy5wdXJlbWFuZ28uY28udWs="));
		
		if ( $this->debug )
		{
			$x = imagesx($pic) - 70;
			$y = imagesy($pic) - 20;
			
			$code = $this->get_code();
			$red = ImageColorAllocateAlpha($pic, 0xAA, 0, 0, 72);
			ImageString($pic, 5, $x, $y, $code, $red);
			ImageString($pic, 5, 5, $y, "[debug mode]", $red);
		}
		
		switch($this->output)
		{
			// add other cases as desired
			case "jpg":
				header("Content-Type: image/jpeg");
				ImageJPEG($pic);
				break;
			case "gif":
				header("Content-Type: image/gif");
				ImageGIF($pic);
				break;
			case "png":
			default:
				header("Content-Type: image/png");
				ImagePNG($pic);
				break;
		}
	
		// kill GD images (removes from memory)
		ImageDestroy($this->im);
		ImageDestroy($this->im2);
		ImageDestroy($pic);
		if(!empty($this->im3))
		{
			ImageDestroy($this->im3);
		}
		exit();
	}
	
	function make_image()
	{
		//////////////////////////////////////////////////////
		////// Create Images + initialise a few things
		//////////////////////////////////////////////////////
		
		// seed random number generator
		// PHP 4.2.0+ doesn't need this, but lower versions will
		$this->seed_func($this->make_seed());
		
		// how faded should the bg be? (100=totally gone, 0=bright as the day)
		// to test how much protection the bg noise gives, take a screenshot of the freeCap image
		// and take it into a photo editor. play with contrast and brightness.
		// If you can remove most of the bg, then it's not a good enough percentage
		switch($this->bg_type)
		{
			case 0:
				break;
			case 1:
			case 2:
				$bg_fade_pct = 65;
				break;
			case 3:
				$bg_fade_pct = 50;
				break;
		}
		// slightly randomise the bg fade
		$bg_fade_pct += $this->rand_func(-2,2);
		
		// read each font and get font character widths
		// $font_widths = Array();
		// for($i=0 ; $i<sizeof($this->font_locations) ; $i++)
		// {
		//   $handle = fopen($this->font_locations[$i],"r");
		//   // read header of GD font, up to char width
		//   $c_wid = fread($handle,15);
		//   $font_widths[$i] = ord($c_wid{8})+ord($c_wid{9})+ord($c_wid{10})+ord($c_wid{11});
		//   fclose($handle);
		// }
		
		// modify image width depending on maximum possible length of word
		// you shouldn't need to use words > 6 chars in length really.
		$width = ($this->max_word_length*($this->font_size+10)+75);
		$height = 90;
		
		$this->im = ImageCreateTrueColor($width, $height);
		$this->im2 = ImageCreateTrueColor($width, $height);
		
		////////////////////////////////////////////////////////
		// GENERATE IMAGE                                     //
		////////////////////////////////////////////////////////
		
		$word = $this->get_code();
		
		// save hash of word for comparison
		// using hash so that if there's an insecurity elsewhere (eg on the form processor),
		// an attacker could only get the hash
		// also, shared servers usually give all users access to the session files
		// echo `ls /tmp`; and echo `more /tmp/someone_elses_session_file`; usually work
		// so even if your site is 100% secure, someone else's site on your server might not be
		// hence, even if attackers can read the session file, they can't get the freeCap word
		// (though most hashes are easy to brute force for simple strings)
		
		//////////////////////////////////////////////////////
		////// Fill BGs and Allocate Colours:
		//////////////////////////////////////////////////////
		
		// set tag colour
		// have to do this before any distortion
		// (otherwise colour allocation fails when bg type is 1)
		$tag_col = ImageColorAllocate($this->im,10,10,10);
		$site_tag_col2 = ImageColorAllocate($this->im2,0,0,0);
		
		// set debug colours (text colours are set later)
		$debug = ImageColorAllocate($this->im, 255, 0, 0);
		$debug2 = ImageColorAllocate($this->im2, 255, 0, 0);
		
		// set background colour (can change to any colour not in possible $text_col range)
		// it doesn't matter as it'll be transparent or coloured over.
		// if you're using bg_type 3, you might want to try to ensure that the color chosen
		// below doesn't appear too much in any of your background images.
		$bg = ImageColorAllocate($this->im, 254, 254, 254);
		$bg2 = ImageColorAllocate($this->im2, 254, 254, 254);
		
		// set transparencies
		ImageColorTransparent($this->im,$bg);
		// im2 transparent to allow characters to overlap slightly while morphing
		ImageColorTransparent($this->im2,$bg2);
		
		// fill backgrounds
		ImageFill($this->im,0,0,$bg);
		ImageFill($this->im2,0,0,$bg2);
		
		if($this->bg_type!=0)
		{
			// generate noisy background, to be merged with CAPTCHA later
			// any suggestions on how best to do this much appreciated
			// sample code would be even better!
			// I'm not an OCR expert (hell, I'm not even an image expert; puremango.co.uk was designed in MsPaint)
			// so the noise models are based around my -guesswork- as to what would make it hard for an OCR prog
			// ideally, the character obfuscation would be strong enough not to need additional background noise
			// in any case, I hope at least one of the options given here provide some extra security!
		
			$this->im3 = ImageCreateTrueColor($width,$height);
			$temp_bg = ImageCreateTrueColor($width*1.5,$height*1.5);
			$bg3 = ImageColorAllocate($this->im3,255,255,255);
			ImageFill($this->im3,0,0,$bg3);
			$temp_bg_col = ImageColorAllocate($temp_bg,255,255,255);
			ImageFill($temp_bg,0,0,$temp_bg_col);
			
			// we draw all noise onto temp_bg
			// then if we're morphing, merge from temp_bg to im3
			// or if not, just copy a $widthx$height portion of $temp_bg to $this->im3
			// temp_bg is much larger so that when morphing, the edges retain the noise.
		
			if($this->bg_type==1)
			{
				// grid bg:
		
				// draw grid on x
				for($i=$this->rand_func(6,20) ; $i<$width*2 ; $i+=$this->rand_func(10,25))
				{
					ImageSetThickness($temp_bg,$this->rand_func(2,6));
					$text_r = $this->rand_func(100,150);
					$text_g = $this->rand_func(100,150);
					$text_b = $this->rand_func(100,150);
					$text_colour3 = ImageColorAllocate($temp_bg, $text_r, $text_g, $text_b);
		
					ImageLine($temp_bg,$i,0,$i,$height*2,$text_colour3);
				}
				// draw grid on y
				for($i=$this->rand_func(6,20) ; $i<$height*2 ; $i+=$this->rand_func(10,25))
				{
					ImageSetThickness($temp_bg,$this->rand_func(2,6));
					$text_r = $this->rand_func(100,150);
					$text_g = $this->rand_func(100,150);
					$text_b = $this->rand_func(100,150);
					$text_colour3 = ImageColorAllocate($temp_bg, $text_r, $text_g, $text_b);
		
					ImageLine($temp_bg,0,$i,$width*2, $i ,$text_colour3);
				}
			} else if($this->bg_type==2) {
				// draw squiggles!
		
				$bg3 = ImageColorAllocate($this->im3,255,255,255);
				ImageFill($this->im3,0,0,$bg3);
				ImageSetThickness($temp_bg,4);
		
				for($i=0 ; $i<strlen($word)+1 ; $i++)
				{
					$text_r = $this->rand_func(100,150);
					$text_g = $this->rand_func(100,150);
					$text_b = $this->rand_func(100,150);
					$text_colour3 = ImageColorAllocate($temp_bg, $text_r, $text_g, $text_b);
		
					$points = Array();
					// draw random squiggle for each character
					// the longer the loop, the more complex the squiggle
					// keep random so OCR can't say "if found shape has 10 points, ignore it"
					// each squiggle will, however, be a closed shape, so OCR could try to find
					// line terminations and start from there. (I don't think they're that advanced yet..)
					for($j=1 ; $j<$this->rand_func(5,10) ; $j++)
					{
						$points[] = $this->rand_func(1*(20*($i+1)),1*(50*($i+1)));
						$points[] = $this->rand_func(30,$height+30);
					}
		
					ImagePolygon($temp_bg,$points,intval(sizeof($points)/2),$text_colour3);
				}
		
			} else if($this->bg_type==3) {
				// take random chunks of $this->bg_images and paste them onto the background
		
				for($i=0 ; $i<sizeof($this->bg_images) ; $i++)
				{
					// read each image and its size
					$temp_im[$i] = ImageCreateFromJPEG($this->bg_images[$i]);
					$temp_width[$i] = imagesx($temp_im[$i]);
					$temp_height[$i] = imagesy($temp_im[$i]);
				}
				
				$blocksize = $this->rand_func(20,60);
				for($i=0 ; $i<$width*2 ; $i+=$blocksize)
				{
					// could randomise blocksize here... hardly matters
					for($j=0 ; $j<$height*2 ; $j+=$blocksize)
					{
						$this->image_index = $this->rand_func(0,sizeof($temp_im)-1);
						$cut_x = $this->rand_func(0,$temp_width[$this->image_index]-$blocksize);
						$cut_y = $this->rand_func(0,$temp_height[$this->image_index]-$blocksize);
						ImageCopy($temp_bg, $temp_im[$this->image_index], $i, $j, $cut_x, $cut_y, $blocksize, $blocksize);
					}
				}
				for($i=0 ; $i<sizeof($temp_im) ; $i++)
				{
					// remove bgs from memory
					ImageDestroy($temp_im[$i]);
				}
		
				// for debug:
				//sendImage($temp_bg);
			}
		
			// for debug:
			//sendImage($this->im3);
		
			if($this->morph_bg)
			{
				// morph background
				// we do this separately to the main text morph because:
				// a) the main text morph is done char-by-char, this is done across whole image
				// b) if an attacker could un-morph the bg, it would un-morph the CAPTCHA
				// hence bg is morphed differently to text
				// why do we morph it at all? it might make it harder for an attacker to remove the background
				// morph_chunk 1 looks better but takes longer
		
				// this is a different and less perfect morph than the one we do on the CAPTCHA
				// occasonally you get some dark background showing through around the edges
				// it doesn't need to be perfect as it's only the bg.
				$morph_chunk = $this->rand_func(1,5);
				$morph_y = 0;
				for($x=0 ; $x<$width ; $x+=$morph_chunk)
				{
					$morph_chunk = $this->rand_func(1,5);
					$morph_y += $this->rand_func(-1,1);
					ImageCopy($this->im3, $temp_bg, $x, 0, $x+30, 30+$morph_y, $morph_chunk, $height*2);
				}
		
				ImageCopy($temp_bg, $this->im3, 0, 0, 0, 0, $width, $height);
		
				$morph_x = 0;
				for($y=0 ; $y<=$height; $y+=$morph_chunk)
				{
					$morph_chunk = $this->rand_func(1,5);
					$morph_x += $this->rand_func(-1,1);
					ImageCopy($this->im3, $temp_bg, $morph_x, $y, 0, $y, $width, $morph_chunk);
		
				}
			} else {
				// just copy temp_bg onto im3
				ImageCopy($this->im3,$temp_bg,0,0,30,30,$width,$height);
			}
		
			ImageDestroy($temp_bg);
		
			if($this->blur_bg)
			{
				$this->myImageBlur($this->im3);
			}
		}
		// for debug:
		//sendImage($this->im3);
		
		//////////////////////////////////////////////////////
		////// Write Word
		//////////////////////////////////////////////////////
		
		// write word in random starting X position
		$word_start_x = $this->rand_func(5,32);
		// y positions jiggled about later
		$word_start_y = 50;
		
		// use last pixelwidth
		$font_pixelwidth = $this->font_size + 10;
		
		if($this->col_type==0)
		{
			$text_r = $this->rand_color();
			$text_g = $this->rand_color();
			$text_b = $this->rand_color();
			$text_colour2 = ImageColorAllocate($this->im2, $text_r, $text_g, $text_b);
		}
		
		// write each char in different font
		for($i=0 ; $i<strlen($word) ; $i++)
		{
			if($this->col_type==1)
			{
				$text_r = $this->rand_color();
				$text_g = $this->rand_color();
				$text_b = $this->rand_color();
				$text_colour2 = ImageColorAllocate($this->im2, $text_r, $text_g, $text_b);
			}
		
			$j = $this->rand_func(0,sizeof($this->font_locations)-1);
			// $font = ImageLoadFont($this->font_locations[$j]);
			// ImageString($this->im2, $font, $word_start_x+($font_widths[$j]*$i), $word_start_y, $word{$i}, $text_colour2);
			ImageTTFText($this->im2, $this->font_size, 0, $word_start_x+(($font_pixelwidth)*$i), $word_start_y, $text_colour2, $this->font_locations[$j], $word{$i});
		}
		
		// for debug:
		// $this->sendImage($this->im2);
		
		//////////////////////////////////////////////////////
		////// Morph Image:
		//////////////////////////////////////////////////////
		
		// calculate how big the text is in pixels
		// (so we only morph what we need to)
		$word_pix_size = $word_start_x+(strlen($word)*$font_pixelwidth);
		
		// firstly move each character up or down a bit:
		$y_pos = 0;
		for($i=$word_start_x ; $i<$word_pix_size ; $i+=$font_pixelwidth)
		{
			// move on Y axis
			// deviates at least 4 pixels between each letter
			$prev_y = $y_pos;
			do{
				$y_pos = $this->rand_func(-5,5);
			} while($y_pos<$prev_y+2 && $y_pos>$prev_y-2);
			ImageCopy($this->im, $this->im2, $i, $y_pos, $i, 0, $font_pixelwidth, $height);
		
			// for debug:
			// ImageRectangle($this->im,$i,$y_pos+10,$i+$font_pixelwidth,$y_pos+70,$debug);
		}
		
		// for debug:
		// $this->sendImage($this->im);
		
		ImageFilledRectangle($this->im2,0,0,$width,$height,$bg2);
		
		// randomly morph each character individually on x-axis
		// this is where the main distortion happens
		// massively improved since v1.2
		$y_chunk = 1;
		$morph_factor = 1;
		$morph_x = 0;
		for($j=0 ; $j<strlen($word) ; $j++)
		{
			$y_pos = 0;
			for($i=0 ; $i<=$height; $i+=$y_chunk)
			{
				$orig_x = $word_start_x+($j*$font_pixelwidth);
				// morph x += so that instead of deviating from orig x each time, we deviate from where we last deviated to
				// get it? instead of a zig zag, we get more of a sine wave.
				// I wish we could deviate more but it looks crap if we do.
				$morph_x += $this->rand_func(-$morph_factor,$morph_factor);
				// had to change this to ImageCopyMerge when starting using ImageCreateTrueColor
				// according to the manual; "when (pct is) 100 this function behaves identically to imagecopy()"
				// but this is NOT true when dealing with transparencies...
				ImageCopyMerge($this->im2, $this->im, $orig_x+$morph_x, $i+$y_pos, $orig_x, $i, $font_pixelwidth, $y_chunk, 100);
		
				// for debug:
				//ImageLine($this->im2, $orig_x+$morph_x, $i, $orig_x+$morph_x+1, $i+$y_chunk, $debug2);
				//ImageLine($this->im2, $orig_x+$morph_x+$font_pixelwidth, $i, $orig_x+$morph_x+$font_pixelwidth+1, $i+$y_chunk, $debug2);
			}
		}
		
		// for debug:
		//sendImage($this->im2);
		
		ImageFilledRectangle($this->im,0,0,$width,$height,$bg);
		// now do the same on the y-axis
		// (much easier because we can just do it across the whole image, don't have to do it char-by-char)
		$y_pos = 0;
		$x_chunk = 1;
		for($i=0 ; $i<=$width ; $i+=$x_chunk)
		{
			// can result in image going too far off on Y-axis;
			// not much I can do about that, apart from make image bigger
			// again, I wish I could do 1.5 pixels
			$y_pos += $this->rand_func(-1,1);
			ImageCopy($this->im, $this->im2, $i, $y_pos, $i, 0, $x_chunk, $height);
		
			// for debug:
			//ImageLine($this->im,$i+$x_chunk,0,$i+$x_chunk,100,$debug);
			//ImageLine($this->im,$i,$y_pos+25,$i+$x_chunk,$y_pos+25,$debug);
		}
		
		// for debug:
		//sendImage($this->im);
		
		// blur edges:
		// doesn't really add any security, but looks a lot nicer, and renders text a little easier to read
		// for humans (hopefully not for OCRs, but if you know better, feel free to disable this function)
		// (and if you do, let me know why)
		$this->myImageBlur($this->im);
		
		// for debug:
		//sendImage($this->im);
		
		if($this->output!="jpg" && $this->bg_type==0)
		{
			// make background transparent
			ImageColorTransparent($this->im,$bg);
		}
		
		
		
		
		
		//////////////////////////////////////////////////////
		////// Try to avoid 'free p*rn' style CAPTCHA re-use
		//////////////////////////////////////////////////////
		// ('*'ed to stop my site coming up for certain keyword searches on google)
		
		// can obscure CAPTCHA word in some cases..
		
		// write site tags 'shining through' the morphed image
		ImageFilledRectangle($this->im2,0,0,$width,$height,$bg2);
		if(is_array($this->site_tags))
		{
			for($i=0 ; $i<sizeof($this->site_tags) ; $i++)
			{
				// ensure tags are centered
				$tag_width = strlen($this->site_tags[$i])*6;
				// write tag is chosen position
				if($this->tag_pos==0 || $this->tag_pos==2)
				{
					// write at top
					ImageString($this->im2, 2, intval($width/2)-intval($tag_width/2), (10*$i), $this->site_tags[$i], $site_tag_col2);
				}
				if($this->tag_pos==1 || $this->tag_pos==2)
				{
					// write at bottom
					ImageString($this->im2, 2, intval($width/2)-intval($tag_width/2), ($height-34+($i*10)), $this->site_tags[$i], $site_tag_col2);
				}
			}
		}
		ImageCopyMerge($this->im2,$this->im,0,0,0,0,$width,$height,80);
		ImageCopy($this->im,$this->im2,0,0,0,0,$width,$height);
		// for debug:
		//sendImage($this->im);
		
		
		
		
		//////////////////////////////////////////////////////
		////// Merge with obfuscated background
		//////////////////////////////////////////////////////
		
		if($this->bg_type!=0)
		{
			// merge bg image with CAPTCHA image to create smooth background
		
			// fade bg:
			if($this->bg_type!=3)
			{
				$temp_im = ImageCreateTrueColor($width,$height);
				$white = ImageColorAllocate($temp_im,255,255,255);
				ImageFill($temp_im,0,0,$white);
				ImageCopyMerge($this->im3,$temp_im,0,0,0,0,$width,$height,$bg_fade_pct);
				// for debug:
				//sendImage($this->im3);
				ImageDestroy($temp_im);
				$c_fade_pct = 50;
			} else {
				$c_fade_pct = $bg_fade_pct;
			}
		
			// captcha over bg:
			// might want to not blur if using this method
			// otherwise leaves white-ish border around each letter
			if($this->merge_type==1)
			{
				ImageCopyMerge($this->im3,$this->im,0,0,0,0,$width,$height,100);
				ImageCopy($this->im,$this->im3,0,0,0,0,$width,$height);
			} else {
				// bg over captcha:
				ImageCopyMerge($this->im,$this->im3,0,0,0,0,$width,$height,$c_fade_pct);
			}
		}
		// for debug:
		//sendImage($this->im);
		
		
		//////////////////////////////////////////////////////
		////// Write tags, remove variables and output!
		//////////////////////////////////////////////////////
		
		// tag it
		// feel free to remove/change
		// but if it's not essential I'd appreciate you leaving it
		// after all, I've put a lot of work into this and am giving it away for free
		// the least you could do is give me credit (or buy me stuff from amazon!)
		// but I understand that in professional environments, your boss might not like this tag
		// so that's cool.
		$tag_str = "";
		// for debug:
		//$tag_str = "[".$word."]";
		
		// ensure tag is right-aligned
		$tag_width = strlen($tag_str)*6;
		// write tag
		ImageString($this->im, 2, $width-$tag_width, $height-13, $tag_str, $tag_col);
		
		// unset all sensetive vars
		// in case someone include()s this file on a shared server
		// you might think this unneccessary, as it exit()s
		// but by using register_shutdown_function
		// on a -very- insecure shared server, they -might- be able to get the word
		unset($word);
		// the below aren't really essential, but might aid an OCR attack if discovered.
		// so we unset them
		
		// output final image :-)
		$this->sendImage($this->im);
		// (sendImage also destroys all used images)
	}
	
	function rand_func($s, $m)
	{
		global $_starttime;
		$tn = microtime_float() - $_starttime;
		if ( $tn > 5 )
		{
			echo '<pre>';
			enano_debug_print_backtrace();
			echo '</pre>';
			exit;
		}
		$rf =& $this->rand_func;
		return $rf($s, $m);
	}
	
	function seed_func($s)
	{
		$rf =& $this->seed_func;
		return $rf($s);
	}
	
	function hash_func($s)
	{
		$rf =& $this->hash_func;
		return $rf($s);
	}
	
}
