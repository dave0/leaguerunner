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
		$this->title = "Login";
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
	 */
	function process () 
	{
		global $session, $_SERVER;

		$username = var_from_post('username');
		$password = var_from_post('password');

		/* Now, if we can, we will create a new user session */
		if( !(isset($username) || isset($password)) ) {
			print theme_header($this->title);
			print $this->login_form();
			print theme_footer();
			return false;  // display_error
		}
		
		$rc = $session->create_from_login($username, $password, $_SERVER['REMOTE_ADDR']);
		if($rc == false) {
			print theme_header($this->title);
			print $this->login_form("Incorrect username or password");
			print theme_footer();
			return false; // display_error
		}
	
		/* 
		 * Now that we know their username/password is valid, check to see if
		 * there are restrictions on their account.
		 */

		switch($session->attr_get('class')) {
			case 'new':
				print theme_header($this->title);
				print $this->login_form("Login Denied.  Account creation is awaiting approval.");
				print theme_footer();
				return false;
				break;
			case 'locked':
				print theme_header($this->title);
				print $this->login_form("Login Denied.  Account has been locked by administrator.");
				print theme_footer();
				return false;
				break;
			case 'inactive':
				/* Inactive.  Send this person to the revalidation page(s) */
				local_redirect(url("op=person_activate"));
				break;
			case 'active':
			case 'volunteer':
			case 'administrator':
				/* These accounts are active and can continue */
				local_redirect(url("op=menu"));
				break;
		}
		return true;
	}

	function display ()
	{	
		// DELETEME: Remove this once Smarty is gone.
		return true;
	}

	function display_error()
	{
		// DELETEME: Remove this once Smarty is gone.
		return true;
	}

	function login_form($error = "")
	{
		$output =<<<EOF
<table align='center' border='0' cellpadding='5' width='300'>
<tr>
	<td colspan='2' align='center'>
EOF;
		if(isset($error)) {
			$output .= "<font color='red'><b>$error</b></font>";
		}
		$output .=<<<EOF
	</td>
</tr>
<tr>
	<td>Username:</td>
	<td><input type='text' name='username' size='25'></td>
</tr>
<tr>
	<td>Password:</td>
	<td><input type='password' name='password' size='25'></td>
</tr>
<tr>
	<td colspan='2' align='center' valign='middle'>
		<input type='submit' name='submit' value='Log In'>
    <br>
EOF;
		$output .= l("Forgot your password", "op=person_forgotpassword");
		$output .= " | ";
		$output .= l("Create New Account", "op=person_create");
		$output .=<<<EOF
  </td>
</tr>
<tr>
<td colspan='2'><font size='-2'>
<b>Note 1:</b> Cookies are required for use of the system.  If you receive an error indicating you 
have an invalid session then cookies may be turned off in your browser.<br />
<br />
<b>Note 2:</b> Your account from last year (2002 Summer or Fall seasons, or
2003 Indoor) will not work.  If you have not created a new account this year,
you will need to do so.
<br />
<i>We are having intermittent problems where user's initial passwords are not being stored properly. 
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
		return form($output);
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

	function process ()
	{
		global $session;
		$session->expire();
		local_redirect(url("op=login"));
		return true;
	}

	function display ()
	{	
		// DELETEME: Remove this once Smarty is gone.
		return true;
	}
}

?>
