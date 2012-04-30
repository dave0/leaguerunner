<?php
/**
 * Logout handler for Drupal SSO
 */
require_once('Handler/logout.php');
class api_2_logout extends logout
{
	function has_permission ()
	{
		/* Only sessions from same server are permitted */
		if( $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'] ) {
			return true;
		}

		return false;
	}

	function process ()
	{
		global $lr_session;
		$lr_session->expire();
		$this->template_name = 'api/2/logout/success.tpl';
		return true;
	}
}
?>
