<?php
register_page_handler('login','Login');

/**
 * Login handler 
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class Login extends Handler 
{

	/**
	 * Initializer for Login class
	 * 
	 * We simply initialize the printable name for this operation.
	 *
	 * @access public
	 */
	function initialize () 
	{
		$this->name = "Login";

		return true;
	}

	/**
	 * Check if the current session has permission to log in
	 *
	 * Since there's no other validation to be done, we always return true. 
	 *
	 * @access public
	 * @return boolean Permission success (true) or fail (false).
	 */
	function has_permission()
	{
		return true;
	}

	/**
	 * Process a user login
	 *
	 * Here, we take the given user login and password, and attempt to
	 * validate against the SQL database.
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function process () 
	{
		global $session, $_SERVER;

		$username = var_from_post('username');
		$password = var_from_post('password');

		/* Now, if we can, we will create a new user session */
		if( isset($username) && isset($password) ) {
			$rc = $session->create_from_login($username, $password, $_SERVER['REMOTE_ADDR']);
			if($rc == false) {
				$this->tmpl->assign("error", gettext("Incorrect username or password"));
			}
		} else {
			$rc = false;
		}
		
		return $rc;
	}

	/** 
	 * Display method for Login
	 *
	 * This overrides the parent class display() method to output a
	 * redirection header instead of an HTML page.  This is only ever 
	 * used when process () succeeds.
	 * 
	 * @access public
	 */
	function display ()
	{	
		global $APP_COOKIE_NAME, $session;
		setcookie($APP_COOKIE_NAME, $session->get_session_key());
		return $this->output_redirect("op=menu");
	}

	/**
	 * Display error message.
	 *
	 * This display_error() function overrides the parent class so that we can
	 * display the error message in-context on the login screen.  The
	 * parent's display() function is called to perform the actual display
	 * work, so that any necessary global variables can be set there before
	 * the HTML is output.
	 *
	 * @access public
	 */
	function display_error()
	{
		global $username, $password;
		$this->set_template_file("Login.tmpl");
		parent::display();	
	}
}
?>
