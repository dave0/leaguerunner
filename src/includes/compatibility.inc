<?php
/*
 * $Id$
 *
 * Contains functions for backwards compatibility with older PHP 
 * releases.
 */

/*
 * PHP older than 4.3.0 doesn't have file_get_contents
 */
if (!function_exists("file_get_contents")) {
    function file_get_contents($filename, $use_include_path = 0) {
        $file = @fopen($filename, "rb", $use_include_path);
        if ($file) {
            while (!feof($file)) $data .= fread($file, 1024);
            fclose($file);
        }
        return $data;
    }
}

/*
 * PHP 4.x doesn't have array_combine
 */
if(!function_exists('array_combine'))
{
	function array_combine($arr1,$arr2) {
		$out = array();
		foreach($arr1 as $key1 => $value1) {
			$out[$value1] = $arr2[$key1];
		}
		return $out;
	}
}

?>
