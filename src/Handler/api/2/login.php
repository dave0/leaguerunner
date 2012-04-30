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
				$this->smarty->assign('error', "Login Denied.  Account creation is awaiting approval.");
				return;
			case 'locked':
				return $this->smarty->assign('error', "Login Denied.  Account has been locked by administrator.");
			case 'inactive':
				$this->smarty->assign('reactivate', 1);
				$this->smarty->assign('error', "Login Denied.  Account is inactive.");
				return;
			case 'active':
				if( ! $lr_session->user->is_waiver_current() ) {
					$this->smarty->assign('needwaiver', 1);
					$this->smarty->assign('error', "Login Denied.  You must agree to the player waiver first.");
					return;
				}

				if( ! $lr_session->user->is_dog_waiver_current() ) {
					$this->smarty->assign('needdogwaiver', 1);
					$this->smarty->assign('error', "Login Denied.  You must agree to the dog waiver first.");
				}
				/* These accounts are active and can continue */
				$this->template_name = 'api/2/login/success.tpl';
				$this->smarty->assign('user', $lr_session->user);
				break;
		}
		return true;
	}
}
?>
