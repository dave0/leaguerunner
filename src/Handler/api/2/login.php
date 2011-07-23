<?php

/**
 * Login method for Drupal plugin to use for single-sign-on
 */
class api_2_login extends Handler
{
	function has_permission ()
	{
		/* Only sessions from same server are permitted */
		if( $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'] ) {
			return true;
		}

		return false;
	}

	function checkPrereqs( $op )
	{
		// Override so we don't get redirected by waiver-date checking
		return false;
	}

	function process ()
	{
		global $lr_session;

		$this->template_name = 'api/2/login/error.tpl';

		if( !($_POST['username'] || $_POST['password']) ) {
			$this->smarty->assign('error', 'No login credentials provided');
			return true;
		}

		/* Now, if we can, we will create a new user session */
		$rc = $lr_session->create_from_login($_POST['username'], $_POST['password'], $_POST['remote_addr']);
		if($rc == false) {
			$this->smarty->assign('error', 'Incorrect username or password');
			return true;
		}

		/*
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */
		switch( $lr_session->attr_get('status') ) {
			case 'new':
				return $this->smarty->assign('error', "Login Denied.  Account creation is awaiting approval.");
			case 'locked':
				return $this->smarty->assign('error', "Login Denied.  Account has been locked by administrator.");
			case 'inactive':
				/// TODO: Need a way for Drupal to redirect to the activation page
				// local_redirect(url("person/activate"));
				return $this->smarty->assign('error', "Login Denied.  Account is inactive.");
				break;
			case 'active':
				/* These accounts are active and can continue */
				$this->template_name = 'api/2/login/success.tpl';
				$this->smarty->assign('user', $lr_session->user);
				break;
		}
		return true;
	}
}
?>
