<?php

class image_pins extends Handler
{
	private $file;

	function __construct ( $file )
	{
		$this->file = $file;
	}

	function has_permission()
	{
		return true;
	}

	function process ()
	{
		global $CONFIG;

		$matches = array();
		if( ! preg_match ("/^([0-9a-zA-Z]+)\.png$/", $this->file, $matches) ) {
			error_exit("Invalid image request for $this->file");
		}

		$code     = $matches[1];
		$basepath = trim($CONFIG['paths']['base_url'], '/')  . '/image/pins';
		$basefile = trim($CONFIG['paths']['file_path'], '/') . '/image/pins';

		$localfile = "$basefile/$code.png";

		if (file_exists ($localfile)) {
			header("Location: http://{$_SERVER['HTTP_HOST']}/$basepath/$code.png");
		} else if (!function_exists ('ImageCreateFromPNG')) {
			header("Location: http://{$_SERVER['HTTP_HOST']}/$basepath/blank-marker.png");
		} else {
			$font = 'ttf-bitstream-vera/Vera';
			$size = 6;

			if( strlen($code) < 3) {
				# Bigger image for number-only pins
				$size = 8;
			}

			$im = ImageCreateFromPNG("$basefile/blank-marker.png");
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
			ImagePNG($im, $localfile);
			ImageDestroy($im);
		}

		exit;
	}
}


?>
