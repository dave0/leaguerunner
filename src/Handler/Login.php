<?php
register_page_handler('login','Login');
register_page_handler('logout','Logout');

/**
 * Login handler 
 */
class Login extends Handler 
{
	function initialize () 
	{
		$this->set_title("Login");
		$this->_required_perms = array(
			'allow'
		);

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
		if( !(isset($username) || isset($password)) ) {
			return false;
		}
		$rc = $session->create_from_login($username, $password, $_SERVER['REMOTE_ADDR']);
		if($rc == false) {
			$this->tmpl->assign("error", gettext("Incorrect username or password"));
			return false;
		}
	
		/* 
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */
		switch($session->attr_get('class')) {
			case 'new':
				$this->tmpl->assign("error",gettext("Login Denied.  Account creation is awaiting approval."));
				return false;
				break;
			case 'locked':
				$this->tmpl->assign("error", gettext("Login Denied.  Account has been locked by administrator."));
				return false;
				break;
			default:
				break;
		}
		return true;
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

		/* Now that we know the user was reasonably valid, see where we should
		 * send them
		 */
		switch($session->attr_get('class')) {
			case 'inactive':
				/* Inactive.  Send this person to the revalidation page(s) */
				setcookie($APP_COOKIE_NAME, $session->get_session_key());
				return $this->output_redirect("op=person_activate");
				break;
			case 'active':
			case 'volunteer':
			case 'administrator':
				/* These accounts are active and can continue */
				setcookie($APP_COOKIE_NAME, $session->get_session_key());
				return $this->output_redirect("op=menu");
				break;
			case 'new':
			case 'locked':
			default:
				$this->error_text = "Internal Error: Unreachable code reached in Login.php";	
				return $this->display_error();
				break;
		}
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

/**
 * Logout handler. 
 */
class Logout extends Handler 
{
	function initialize ()
	{
		$this->set_title("Logout");
		$this->_required_perms = array(
			'allow'
		);
		return true;
	}

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
		return $this->output_redirect("op=login");
	}
}

?>
