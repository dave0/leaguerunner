<?php
/**
 * Logout handler. 
 */
class logout extends Handler 
{
	function has_permission ()
	{
		return true;
	}

	function checkPrereqs( $op ) 
	{
		return false;
	}

	function process ()
	{
		global $lr_session;
		$lr_session->expire();
		local_redirect(url("login"));
		return true;
	}
}
?>
