<?php

/**
 * Extensions for use inside the Smarty templates.  These can only be accessed
 * via Smarty, and not directly called from PHP.
 *	
 * @package Leaguerunner
 * @module smarty_extension
 * @copyright  GPL
 * @version $Id$
 * @author Dave O'Neill <dmo@acm.org>
 * @access private
 */

/**
 * Generate a pulldown inside a Smarty template.
 * 
 * <pre>
 * This is to be called as
 * {create_pulldown name='this_select' data=$array_var, multiple=true, size=3}
 *
 * $array_var should consist of a regular array of associative arrays.The
 * internal associative arrays should be of the format:
 * array(
 * 	'value' => 'THis is a foo', # The value to be displayed
 * 	'key'   => 'foo',           # The value to be returned when submitted
 * 	'selected' => false         # Whether or not this should be marked as
 * 	                            # selected 
 * )
 * </pre>
 *
 * @param string $params parameter list
 */
function create_pulldown( $params )
{
	extract($params);
	if(empty($name)) {
		echo "ERROR: No name= attribute given to create_pulldown()";
		return;
	}
	if(is_null($data)) {
		echo "ERROR: No data= attribute given to create_pulldown()";
		return;
	}
	if(empty($multiple)) {
		$multiple = false;
	}
	if(empty($size)) {
		$size = 1;
	}

	$output = "<select name='$name' size='$size'";
	if($multiple) {
		$output .= " multiple";
	}
	$output .= ">\n";
	foreach($data as $this_entry) {
		$output .= "<option value='" . $this_entry['key'] . "'";
		if(!empty($this_entry['selected']) && $this_entry['selected']) {
			$output .= " selected";
		}
		$output .= ">";
		if($this_entry['value']) {
			$output .= $this_entry['value'];
		} else {
			$output .= $this_entry['key'];
		}
		$output .= "</option>\n";
	}
	$output .= "</select>";

	echo $output;
}

/**
 * Registers any smarty extensions we've created.
 *
 * @param mixed &$smarty Reference to a Smarty object
 */
function register_smarty_extensions  ( &$smarty )
{
	$smarty->register_function("create_pulldown", create_pulldown);
}

?>
