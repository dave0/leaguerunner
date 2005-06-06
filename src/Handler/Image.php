<?php

function image_dispatch()
{
	$op = arg(1);

	switch($op) {
		case 'pins':
			$obj = new ImageGeneratePin;
			break;
		default:
			error_exit('Invalid operation');
	}
	
	return $obj;
}

function image_permissions()
{
	return true;
}

class ImageGeneratePin extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		$file = arg(2);
		if( ! preg_match ("/^[0-9a-zA-Z]+\.png$/", $file) ) {
			error_exit("Invalid image request");
		}

		$code = substr($file, 0, 3);

		$font = 'ttf-bitstream-vera/Vera';
		$size = 6;
		$basepath = '/opt/websites/testing.ocua.ca/leaguerunner/src/image/pins';

		$im = ImageCreateFromPNG("$basepath/blank-marker.png");
		imageSaveAlpha($im, true);

		$tsize = ImageTTFBBox($size, 0, $font, $code);

		$textbg = ImageColorAllocate($im, 255, 119, 207);
		$black = ImageColorAllocate($im, 0,0,0);

		$dx = abs($tsize[2]-$tsize[0]);
		$dy = abs($tsize[5]-$tsize[3]);
		$x = ( ImageSx($im) - $dx) / 2 + 1;
		$y = ( ImageSy($im) - $dy) / 2;
		
		ImageTTFText($im, $size, 0, $x, $y, $black, $font, $code);
		
		header('Content-Type: image/png');
		ImagePNG($im);
		ImagePNG($im, "$basepath/$code.png");
		ImageDestroy($im);
		exit;
	}
}


?>
