<?php
register_page_handler('notfound','NotFound');
/**
 * Default operation handler
 *
 * The default operation handler deals with any operation that isn't foud in
 * the list.  These operations are generally unsupported values for the op=
 * argument to main.php, but may in some cases be typos or illegitimate
 * attempts to access the system (ie: attempts at buffer overflows or other
 * exploits)
 * 
 * @package Leaguerunner
 * @version $Id$
 * @author  Dave O'Neill <dmo@acm.org>
 * @access  public
 * @copyright GPL
 */
class NotFound extends Handler 
{
	/**
	 * Check if the current session has permission for this operation
	 * 
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission () 
	{
		return true;
	}

	/**
	 * Process the "operation not found" output.
	 * 
	 * @access public
	 * @return boolean success/fail
	 */
	function process () 
	{
		$this->error_text = gettext("Sorry, that operation does not exist");
		return false;	
	}
}
?>
