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
		global $session, $_SERVER;

		$username = var_from_post('username');
		$password = var_from_post('password');

		if( !(isset($username) || isset($password)) ) {
			/* Check if session is already valid */
			if($session->is_valid()) {
				return $this->handle_valid();
			}
			return $this->login_form();
		}
		
		/* Now, if we can, we will create a new user session */
		$rc = $session->create_from_login($username, $password, $_SERVER['REMOTE_ADDR']);
		if($rc == false) {
			return $this->login_form("Incorrect username or password");
		}
	
		/* 
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */
		return $this->handle_valid();
	}

	function handle_valid()
	{
		global $session, $_SERVER;
		
		$remember_me = var_from_post('remember_me');

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
				local_redirect("op=person_activate");
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

				local_redirect("op=menu");
				break;
		}
		return true;
	}

	function login_form($error = "")
	{

		if($error) {
			$output .= "<div style='padding-top: 2em; text-align: center'>";
			$output .= theme_error($error);
			$output .= "</div>";
		}
		$output .= "<table align='center' border='0' cellpadding='5' width='300'>";
		$output .= "<tr><td>Username:</td><td>";
		$output .= form_textfield("", "username", "", 25, 25);
		$output .= "</td></tr>";
		$output .= "<tr><td>Password:</td><td>";
		$output .= form_password("", "password", "", 25, 25);
		$output .= "</td></tr>";

		$output .= tr(
			td(form_checkbox("Remember Me","remember_me"), array( "colspan" => 2, "align" => "center"))
		);

		$login_td = form_submit("Log In","submit");
		$login_td .= "<br />" . theme_links(array(
			l("Forgot your password", "op=person_forgotpassword"),
			l("Create New Account", "op=person_create")));
		
		$output .= tr(
			td( $login_td, array( "colspan" => 2, "align" => "center", "valign" => "middle"))
		);
		
		$output .=<<<EOF
<tr>
<td colspan='2'><font size='-2'>
<b>Notes:</b> Cookies are required for use of the system.  If you receive an error indicating you 
have an invalid session then cookies may be turned off in your browser.<br />
<br />
<i>
If you cannot login after receiving your Account Activiation notification, try getting a new 
password emailed to you (click on "Forgot your password?").</i>
</font></td>
</tr>
</table>
<input type='hidden' name='op' value='login'>
<script language="JavaScript">
document.lrlogin.username.focus();
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
		local_redirect("op=login");
		return true;
	}
}

?>
