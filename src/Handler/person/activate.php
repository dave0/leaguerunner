<?php

require_once('Handler/person/edit.php');
/**
 * Account reactivation
 *
 * Accounts must be periodically reactivated to ensure that they are
 * reasonably up-to-date.
 */
class person_activate extends person_edit
{

	function __construct ( )
	{
		global $lr_session;
		$this->person =& $lr_session->user;
		$this->title = "{$this->person->fullname} &raquo; Activate";
	}

	function checkPrereqs ( $ignored )
	{
		return false;
	}

	/**
	 * Check to see if this user can activate themselves.
	 * This is only possible if the user is in the 'inactive' status. This
	 * also means that the user can't have a valid session.
	 */
	function has_permission ()
	{
		global $lr_session;
		if($lr_session->is_valid()) {
			return false;
		}

		if ($lr_session->attr_get('status') != 'inactive') {
			error_exit("You do not have a valid session");
		}

		return true;
	}

	function process ()
	{
		$edit = $_POST['edit'];

		if( ! $this->person ) {
			error_exit("That account does not exist");
		}

		$this->smarty->assign('instructions', "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
		$this->template_name = 'pages/person/edit.tpl';

		$this->generateForm( $edit );
		$this->smarty->assign('person', $this->person);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$rc = $this->perform( $edit );
			if( ! $rc ) {
				error_exit("Failed attempting to activate account");
			}
			$this->person->set('status', 'active');
			$rc = $this->person->save();
			if( !$rc ) {
				error_exit("Failed attempting to activate account");
			}
			local_redirect(url("home"));
		} else {
			/* Deal with multiple days and start times */
			$this->smarty->assign('edit', (array)$this->person);
		}

		return true;
	}
}
?>
