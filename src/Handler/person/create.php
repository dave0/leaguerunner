<?php

require_once('Handler/person/edit.php');

class person_create extends person_edit
{
	function __construct ( )
	{
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','create');
	}

	function checkPrereqs( $next )
	{
		return false;
	}

	function process ()
	{
		$edit = $_POST['edit'];

		$this->title = 'Create Account';

		$id = 'new';
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->person = new Person;
				$this->person->user_id = $id;
				return $this->perform( $this->person, $edit );

			default:
				$edit = array();
				$rc = $this->generateForm( $id, $edit, "To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system.<p><b>NOTE</b> If you already have an account from a previous season, DO NOT CREATE ANOTHER ONE!  Instead, please <a href=\"" . variable_get('password_reset', url('person/forgotpassword')) . "\">follow these instructions</a> to gain access to your account.");
		}
		$this->setLocation(array( $this->title => 0));
		return $rc;
	}

	function perform ( $person, $edit = array())
	{
		global $lr_session;

		if( ! validate_name_input($edit['username']) ) {
			$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
		}
		$existing_user = person_load( array('username' => $edit['username']) );
		if( $existing_user ) {
			error_exit("A user with that username already exists; please go back and try again");
		}

		if($edit['password_once'] != $edit['password_twice']) {
			error_exit("First and second entries of password do not match");
		}
		$crypt_pass = md5($edit['password_once']);

		$person->set('username', $edit['username']);
		$person->set('firstname', $edit['firstname']);
		$person->set('lastname', $edit['lastname']);
		$person->set('password', $crypt_pass);

		$rc = parent::perform( $person, $edit );

		if( $rc === false ) {
			return false;
		} else {
			return para(
				"Thank you for creating an account.  It is now being held for one of the administrators to approve.  "
				. "Once your account is approved, you will receive an email informing you.  "
				. "You will then be able to log in with your username and password."
			);
		}
	}
}

?>
