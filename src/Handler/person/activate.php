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
		global $lr_session;

		$edit = $_POST['edit'];
		$this->title = "Activate Account";

		$this->person = $lr_session->user;
		if( ! $this->person ) {
			error_exit("That account does not exist");
		}

		switch($edit['step']) {
			case 'confirm': 
				$rc = $this->generateConfirm( $this->person->user_id, $edit );
				break;
			case 'perform':
				$rc = $this->perform( $this->person, $edit );
				if( ! $rc ) {
					error_exit("Failed attempting to activate account");
				}
				$this->person->set('status', 'active');
				$rc = $this->person->save();
				if( !$rc ) {
					error_exit("Failed attempting to activate account");
				}
				local_redirect(url("home"));
				break;
			default:
				$edit = object2array($this->person);
				$rc = $this->generateForm( $this->person->user_id , $edit, "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
		}

		return $rc;
	}
}
?>
