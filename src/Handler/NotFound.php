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

	function initialize ()
	{
		$this->name = "Operation Not Found";
	}

	/**
	 * Check if the current session has permission for this operation
	 *
	 * If there is a current session, the user is allowed to receive the
	 * "operation not found" error.  Otherwise, they get a "Not Logged In"
	 * error.
	 * 
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission () 
	{
		global $session;
		/* Check that there is a session first */
		if($session->is_valid()) {
			return true;
		}
		/* If no session, it's error time. */
		$this->name = "Not Logged In";
		$this->error_text = gettext("Sorry, you aren't logged in");
		return false;
	}

	/**
	 * Process the "operation not found" output.
	 * 
	 * @access public
	 * @return boolean success/fail
	 */
	function process () 
	{
		global $op;
		$this->set_template_file("ErrorMessage.tmpl");
		$this->tmpl->assign("message",
			gettext("Sorry, you cannot perform the operation") . " <b>" . $op . "</b><br>"
			);
		return true;	
	}
}
?>
