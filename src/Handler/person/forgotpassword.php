<?php
class person_forgotpassword extends Handler
{

	function checkPrereqs( $next )
	{
		return false;
	}

	function has_permission ()
	{
		// Can always request a password reset
		return true;
	}

	function process()
	{
		global $lr_session;
		$this->title = "Request New Password";
		$edit = $_POST['edit'];
		if ($lr_session->is_admin()) {
			$edit = $_GET['edit'];
		}
		switch($edit['step']) {
			case 'perform':
				$rc = $this->perform( $edit );
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm()
	{
		$admin_addr = variable_get('app_admin_email', '');
		$org = variable_get('app_org_short_name', 'league');
		$output = <<<END_TEXT
<p>
	If you'd like to reset your password, please enter ONLY ONE OF:
</p>
END_TEXT;

		$output .= "<div class='pairtable'>";
		$output .= form(
			form_hidden('edit[step]', 'perform')
			. table( null, array(
			array('Username', form_textfield('', 'edit[username]', '', 25, 100), form_submit("Submit"))
		)));
		$output .= form(
			form_hidden('edit[step]', 'perform')
			. table( null, array(
			array('Email Address', form_textfield('', 'edit[email]', '', 40, 100), form_submit("Submit"))
		)));
		$output .= "</div>";

		$output .=<<<END_TEXT
<p>
	If the information you provide matches an account, an email will be sent
	to the address on file, containing login information and a new password.
	If you don't receive an email within a few hours, you may not have
	remembered your information correctly.
</p>
<p>
  If you really can't remember any of these, you can mail <a
  href="mailto:$admin_addr">$admin_addr</a> for support.  <b>DO NOT CREATE A NEW ACCOUNT!</b>
</p>
END_TEXT;

		return form($output);
	}

	function perform ( $edit = array() )
	{
		$fields = array();
		if(validate_nonblank($edit['username'])) {
			$fields['username'] = $edit['username'];
		}
		if(validate_nonblank($edit['email'])) {
			$fields['email'] = $edit['email'];
		}

		if( count($fields) < 1 ) {
			error_exit("You must supply at least one of username or email address");
		}

		/* Now, try and find the user */
		$user = person_load( $fields );

		/* Now, we either have one or zero users.  Regardless, we'll present
		 * the user with the same output; that prevents them from using this
		 * to guess valid usernames.
		 */
		if( $user ) {
			/* Generate a password */
			$pass = generate_password();
			$cryptpass = md5($pass);

			$user->set('password', $cryptpass);

			if( ! $user->save() ) {
				error_exit("Error setting password");
			}

			/* And fire off an email */
			$rc = send_mail($user->email, "$user->firstname $user->lastname",
				false, false, // from the administrator
				false, false, // no Cc
				_person_mail_text('password_reset_subject', array('%site' => variable_get('app_name','Leaguerunner'))),
				_person_mail_text('password_reset_body', array(
					'%fullname' => "$user->firstname $user->lastname",
					'%username' => $user->username,
					'%password' => $pass,
					'%site' => variable_get('app_name','Leaguerunner')
				)));
			if($rc == false) {
				error_exit("System was unable to send email to that user.  Please contact system administrator.");
			}
		}

		$output = <<<END_TEXT
<p>
	The password for the user matching the criteria you've entered has been
	reset to a randomly generated password.  The new password has been mailed
	to that user's email address.  No, we won't tell you what that email 
	address or user's name are -- if it's you, you'll know soon enough.
</p><p>
	If you don't receive an email within a few hours, you may not have
	remembered your information correctly, or the system may be encountering
	problems.
</p>
END_TEXT;
		return $output;
	}
}

?>
