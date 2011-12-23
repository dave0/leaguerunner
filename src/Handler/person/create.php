<?php

require_once('Handler/person/edit.php');

class person_create extends person_edit
{
	function __construct ( )
	{
		$this->title = 'Create Account';
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

		$this->smarty->assign('instructions', "To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system.<p><b>NOTE</b> If you already have an account from a previous season, DO NOT CREATE ANOTHER ONE!  Instead, please <a href=\"forgotpassword\">follow these instructions</a> to gain access to your account.");
		$this->template_name = 'pages/person/edit.tpl';

		$this->generateForm( $edit );

		$this->person = new Person;
		$this->person->user_id = 'new';

		$this->smarty->assign('person', $this->person);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->person->set('username', $edit['username']);
			$this->person->set_password($edit['password_once']);
			$this->perform($edit);
			$this->template_name = 'pages/person/create_complete.tpl';
			return true;
		} else {
			$this->smarty->assign('edit', (array)$this->person);
		}

		return true;
	}

	function check_input_errors ( $edit = array() )
	{
		$errors = parent::check_input_errors( $edit );

		if( ! validate_name_input($edit['username']) ) {
			$errors[] = "You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
		}
		$existing_user = Person::load( array('username' => $edit['username']) );
		if( $existing_user ) {
			$errors[] = "A user with that username already exists; please choose another";
		}

		if($edit['password_once'] != $edit['password_twice']) {
			$errors[] = error_exit("First and second entries of password do not match");
		}

		return $errors;
	}
}

?>
