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
 * <select name=foo>
 * {create_options  data=$array_var selected=$item multiple=true size=3}
 * </select>
 *
 * $array_var should consist of a regular array of associative arrays.The
 * internal associative arrays should be of the format:
 * array(
 * 	'output' => 'THis is a foo', # The value to be displayed
 * 	'value'   => 'foo',           # The value to be returned when submitted
 * )
 * </pre>
 *
 * @param string $params parameter list
 */
function create_options( $params )
{
	extract($params);
	if(is_null($data)) {
		echo "ERROR: No data= attribute given to create_pulldown()";
		return;
	}

	if(empty($selected)) {
		$selected = array();
	} else {
		if(!is_array($selected)) {
			$selected = array($selected);
		}
	}

	foreach($data as $this_entry) {
		$output .= "<option value='" . $this_entry['value'] . "'";
		if(in_array($this_entry['value'], $selected)) {
			$output .= " selected";
		}
		$output .= ">";
		if($this_entry['output']) {
			$output .= $this_entry['output'];
		} else {
			$output .= $this_entry['value'];
		}
		$output .= "</option>\n";
	}

	echo $output;
}

/**
 * Registers any smarty extensions we've created.
 *
 * @param mixed &$smarty Reference to a Smarty object
 */
function register_smarty_extensions  ( &$smarty )
{
	$smarty->register_function("create_options","create_options");
}

?>
