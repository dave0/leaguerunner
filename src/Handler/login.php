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
				   'data' => "<p><b>Notes:</b> Cookies are required for use of the system.  If you receive an error indicating you
have an invalid session then cookies may be turned off in your browser.<br />
<br />
<i>
If you cannot login after receiving your Account Activation notification, try getting a new
password emailed to you (click on \"Forgot your password?\").</i>
</p>
<p>
Do NOT create a new account if you already have one.  Use the 'Forgot your
password' feature to have your login info emailed to the address on file.
</p>
"
			)
		);

		$output .= table(null, $rows, array('align'=>'center', 'width' => '300' ));
		$output .=<<<EOF
<script language="JavaScript">
document.lrlogin.elements[0].focus();
</script>
EOF;
		$output .= form_hidden('edit[next]', $next);
		return form($output, 'post', 0, " name='lrlogin'");
	}
}

?>