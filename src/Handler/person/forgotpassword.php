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
				$this->template_name = 'pages/person/forgotpassword/result.tpl';
				$this->perform( $edit );
				break;
			default:
				$this->template_name = 'pages/person/forgotpassword/form.tpl';
				$this->smarty->assign('admin_addr', variable_get('app_admin_email', ''));
		}

		return true;
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
			info_exit("You must supply at least one of username or email address");
		}

		/* Now, try and find the user */
		$user = Person::load( $fields );

		/* Now, we either have one or zero users.  Regardless, we'll present
		 * the user with the same output; that prevents them from using this
		 * to guess valid usernames.
		 */
		if( $user ) {
			/* Generate a password */
			$pass = generate_password();
			$user->set_password( $pass );
			if( ! $user->save() ) {
				error_exit("Error setting password");
			}

			/* And fire off an email */
			$rc = send_mail($user,
				false, // from the administrator
				false, // no Cc
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
	}
}

?>
