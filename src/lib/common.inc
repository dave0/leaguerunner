<?php
/* 
 * Common functions for use throughout Leagueruner
 */

function validate_url_input ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	/* TODO: Ensure that it starts with http:// and does not contain 
	 * any invalid characters (such as HTML blink tags, etc)
	 */
	return true;
}

function validate_date_input ( $year, $month, $day )
{
	if( !(validate_nonblank($year) && validate_nonblank($month) && validate_nonblank($day)) ) {
		return false;
	}

	$current = localtime(time(),1);
	$this_year = $current['tm_year'] + 1900;

	/* Checkdate doesn't check that the year is sane, so we have to 
	 * do it ourselves.  Our sanity window is that anything earlier 
	 * than 80 years ago, and anything 5 years in the future must be 
	 * bogus.
	 */
	if( ($year < $this_year - 80) || ($year > $this_year + 5) ) {
		return false;
	}
	
	if(!checkdate($month, $day, $year) ) {
		return false;
	}
	return true;
}

function validate_name_input ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^[\w-\. ']+$/", $string) ) {
		return false;
	}
	return true;
}

function validate_telephone_input( $string ) 
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^\(?\d{3}\)?\s*-?\s*\d{3}\s*-?\s*\d{4}\s*([ext]*\s*\d+)?$/", $string) ) {
		return false;
	}
	return true;
}
	
function validate_email_input ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( ! preg_match("/^[\w-\.\+]+\@([\w-]+\.)+[\w-]+$/", $string) ) {
		return false;
	}
	return true;
}
	
function validate_postalcode ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	
	$string = strtoupper(trim($string));

	/* Catch well-known "garbage" postalcodes.
	 * If necessary, we can restrict postalcodes to only ottawa-area
	 * postalcodes, but that would be a lot of work for potentially little
	 * gain.
	 */
	if( preg_match("/^([:alpha:]\d)$1$1$/", $string) ) {
		return false;
	}
	
	if ( ! preg_match("/^[A-Z]\d[A-Z]\s*\d[A-Z]\d$/", $string) ) {
		return false;
	}
	return true;
}

function validate_nonblank( $string )
{
	if( strlen(trim($string)) <= 0 ) {
		return false;
	}
	return true;
}
?>
