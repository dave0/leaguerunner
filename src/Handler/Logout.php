<?php
register_page_handler('logout','Logout');

/**
 * Logout handler. 
 *
 * @package Leaguerunner
 * @version $Id$
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class Logout extends Handler 
{
	/**
	 * Constructor
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "Logout";
	}

	/**
	 * Check authorization to log out
	 *
	 * All active sessions can be logged out.  Otherwise, return an 
	 * error.
	 */
	function has_permission()
	{	
		global $session;
		
		/* Anyone with a valid session id has permission */
		if($session->is_valid()) {
			return true;
		}
		/* If no session, it's error time. */
		$this->error_text = gettext("You can't log out if you're not logged in");
		return false;
	}

	/**
	 * Process a logout attempt
	 *
	 * @access public
	 */
	function process ()
	{
		global $session;
		$rc =  $session->expire();
		if(! $rc) {
			$this->error_text = gettext("Couldn't log out!");
		}
		return $rc;
	}

	/**
	 * Display handler for Logout
	 *
	 * When logging out a user, after invalidating that user's session, we
	 * redirect them to the login page.
	 *
	 * @access public
	 */
	function display ()
	{	
		/* TODO: Should we ever display an error page? */
		Header("Location: " . $GLOBALS['APP_CGI_LOCATION'] . "?op=login");
	}
}

?>
