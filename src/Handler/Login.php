<?php
function login_dispatch() 
{
	return new Login;
}

function logout_dispatch() 
{
	return new Logout;
}

function login_menu()
{
	menu_add_child('_root','logout','Log Out', array('link' => 'logout', 'weight' => 20));
}

/**
 * Login handler 
 */
class Login extends Handler 
{
	function initialize () 
	{
		$this->_required_perms = array(
			'allow'
		);
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
		global $session;

		$edit = $_POST['edit'];

		if( !($edit['username'] && $edit['password']) ) {
			/* Check if session is already valid */
			if($session->is_valid()) {
				return $this->handle_valid($edit['remember_me']);
			}
			return $this->login_form();
		}
		
		/* Now, if we can, we will create a new user session */
		$rc = $session->create_from_login($edit['username'], $edit['password'], $_SERVER['REMOTE_ADDR']);
		if($rc == false) {
			return $this->login_form("Incorrect username or password");
		}
	
		/* 
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */
		return $this->handle_valid( $edit['remember_me'] );
	}

	function handle_valid( $remember_me = 0 )
	{
		global $session;

		switch($session->attr_get('status')) {
			case 'new':
				return $this->login_form("Login Denied.  Account creation is awaiting approval.");
				break;
			case 'locked':
				return $this->login_form("Login Denied.  Account has been locked by administrator.");
				return true;
				break;
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

				$path = dirname($_SERVER['PHP_SELF']);
				if ($remember_me) {
					setcookie(session_name(), session_id(), time() + 3600 * 24 * 365, $path);
				} else {  
					setcookie(session_name(), session_id(), FALSE, $path);
				}

				local_redirect(url("home"));
				break;
		}
		return true;
	}

	function login_form($error = "")
	{
		
		$output = "<p />";
		if($error) {
			$output .= "<div style='padding-top: 2em; text-align: center'>";
			$output .= theme_error($error);
			$output .= "</div>";
		}
		$rows = array();
		$rows[] = array("Username:", form_textfield("", "edit[username]", "", 25, 25));
		$rows[] = array("Password:", form_password("", "edit[password]", "", 25, 25));

		$rows[] = array(
			array('data' => form_checkbox("Remember Me","edit[remember_me]"),
				  "colspan" => 2, "align" => "center")
		);
		$rows[] = array(
			array('data' => form_submit("Log In","submit")
				  . "<br />" . theme_links(array(
			l("Forgot your password", "person/forgotpassword"),
			l("Create New Account", "person/create"))),
				  "colspan" => 2, "align" => "center")
		);

		$rows[] = array(
			array( 'colspan' => 2,
				   'data' => "<b>Notes:</b> Cookies are required for use of the system.  If you receive an error indicating you 
have an invalid session then cookies may be turned off in your browser.<br />
<br />
<i>
If you cannot login after receiving your Account Activiation notification, try getting a new 
password emailed to you (click on \"Forgot your password?\").</i>"
			)
		);

		$output .= table(null, $rows, array('align'=>'center', 'width' => '300' ));
		$output .=<<<EOF
<script language="JavaScript">
document.lrlogin.elements[0].focus();
</script>
EOF;
		return form($output, 'post', 0, " name='lrlogin'");
	}
}

/**
 * Logout handler. 
 */
class Logout extends Handler 
{
	function initialize ()
	{
		$this->_required_perms = array(
			'allow'
		);
		return true;
	}

	function checkPrereqs( $op ) 
	{
		return false;
	}

	function process ()
	{
		global $session;
		$session->expire();
		local_redirect(url("login"));
		return true;
	}
}

?>
