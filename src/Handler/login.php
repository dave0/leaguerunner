<?php

class login extends Handler
{
	function has_permission ()
	{
		return true;
	}

	function checkPrereqs( $op )
	{
		return false;
	}

	/**
	 * Process a user login
	 *
	 * Here, we take the given user login and password, and attempt to
	 * validate against the SQL database.
	 *
	 */
	function process ()
	{
		global $lr_session;

		$edit = $_POST['edit'];

		if( !($edit['username'] && $edit['password']) ) {
			/* Check if session is already valid */
			if($lr_session->is_valid()) {
				return $this->handle_valid($edit['remember_me'], $edit['next']);
			}
			return $this->login_form( null, $_GET['next'] );
		}

		/* Now, if we can, we will create a new user session */
		$rc = $lr_session->create_from_login($edit['username'], $edit['password'], $_SERVER['REMOTE_ADDR']);
		if($rc == false) {
			return $this->login_form("Incorrect username or password");
		}

		/*
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */
		return $this->handle_valid( $edit['remember_me'], $edit['next'] );
	}

	function handle_valid( $remember_me = 0, $next = null )
	{
		global $lr_session;

		$status = $lr_session->attr_get('status');
		// New users may be treated as active, if the right setting is on
		if( $lr_session->user->is_active () ) {
			$status = 'active';
		}

		switch($status) {
			case 'new':
				return $this->login_form("Login Denied.  Account creation is awaiting approval.");
			case 'locked':
				return $this->login_form("Login Denied.  Account has been locked by administrator.");
			case 'inactive':
				/* Inactive.  Send this person to the revalidation page(s) */
				local_redirect(url("person/activate"));
				break;
			case 'active':
				/* These accounts are active and can continue */

				/*
				 * If the user wants to be remembered, set the proper cookie
				 * such that the session won't expire.
				 */

				$path = ini_get('session.cookie_path');
				if( ! $path ) {
					$path = '/';
				}

				$domain = ini_get('session.cookie_domain');

				if ($remember_me) {
					setcookie(session_name(), session_id(), time() + 3600 * 24 * 365, $path, $domain);
				} else {
					setcookie(session_name(), session_id(), FALSE, $path, $domain);
				}

				if( $next ) {
					local_redirect(queryUnpickle($next));
				} else {
					local_redirect(url("home"));
				}
				break;
		}
		return true;
	}

	function login_form($error = "", $next = null)
	{
		if( $next && !$error ) {
			$error = 'You must log in to perform that operation';
		}

		$this->smarty->assign('error', $error);
		$this->smarty->assign('hide_sidebar', 1);
		$this->template_name = 'pages/login.tpl';
		return true;
	}
}

?>
