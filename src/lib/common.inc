<?php
/* 
 * Common functions for use throughout Leagueruner
 */

/*
 * Validation functions
 */
function validate_nonhtml ( $string )
{
	if( !validate_nonblank($string) ) {
		return false;
	}
	if ( preg_match("/<>/", $string) ) {
		return false;
	}
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

/*
 * Helper functions
 */


/*
 * Clean up a telephone number so that it's in a common format
 * Assumption: phone number has passed validate_telephone_input()
 */
function clean_telephone_number( $string ) 
{
	$matches = array();
	preg_match("/^\(?(\d{3})\)?\s*-?\s*(\d{3})\s*-?\s*(\d{4})\s*(?:[ext]*\s*(\d+))?$/", $string, $matches);

	$clean = "(" . $matches[1] . ") " . $matches[2] . "-" . $matches[3];
	if(count($matches) == 5) {
		$clean .= " x" . $matches[4];
	}
	
	return $clean;
}


/*
 * PHP doesn't have the Perlish comparisons of cmp and <=>
 * so we fake a numeric cmp() here.
 */
function cmp ($a, $b) 
{
	if($a > $b) {
		return 1;
	}
	if($a < $b) {
		return -1;
	}
	return 0;
}


?>
