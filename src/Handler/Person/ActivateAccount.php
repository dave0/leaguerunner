<?php
register_page_handler('person_activate', 'PersonActivate');

/**
 * Account reactivation
 *
 * Accounts must be periodically reactivated to ensure that they are
 * reasonably up-to-date.
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonActivate extends PersonEdit
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		parent::initialize();
		$this->set_title("Activate Account");

		return true;
	}

	/**
	 * Check to see if this user can activate themselves.
	 * This is only possible if the user is in the 'inactive' class.
	 */
	function has_permission ()
	{
		global $session, $id;
	
		/* 
		 * This looks weird, but really isn't.  'inactive' users can't really
		 * have a valid session, thus we need to jump through some hoops here
		 * to consider any inactive user as OK for this handler.
		 */
		if(!$session->is_valid()) {
			if ($session->attr_get('class') != 'inactive') {
				$this->error_text = gettext("You do not have a valid session");
				return false;
			} 
		}


		$id = $session->attr_get('user_id');
		
		$this->_permissions['edit_email'] 		= true;
		$this->_permissions['edit_phone']		= true;
		$this->_permissions['edit_name'] 		= true;
		$this->_permissions['edit_birthdate']	= true;
		$this->_permissions['edit_address']		= true;
		$this->_permissions['edit_gender']		= true;
		$this->_permissions['edit_skill'] 		= true;

		return true;
	}

	/*
	 * Unfortunately, we need to override process() from Edit.php in order to
	 * insert the step where a user must click through the waiver/agreement --
	 * even though it's nearly all the same code, we need to stick stuff in
	 * the middle.  =(
	 */
	function process ()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm': 
				$this->set_template_file("Person/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'update');
				$rc = $this->generate_confirm();
				break;
			case 'update':  /* Make any updates specified by the user */
				$this->set_template_file("Person/waiver_form.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->perform();
				break;
			case 'perform':  /* Waiver was clicked */
				$rc = $this->process_waiver();
				break;
			default:
				$this->set_template_file("Person/edit_form.tmpl");
				$rc = $this->generate_form();
				$this->tmpl->assign("instructions", gettext("In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary."));
				$this->tmpl->assign("page_step", 'confirm');
		}
		
		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return $rc;
	}

	/**
	 * Override parent display to redirect to 'menu' on success
	 */
	function display ()
	{
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=menu");
		}
		return parent::display();
	}

	/**
	 * Process input from the waiver form.
	 *
	 * We will only activate the user if they agreed to the waiver.
	 */
	function process_waiver()
	{
		global $DB, $id;
		
		$signed = var_from_getorpost('signed');
		
		if('yes' != $signed) {
			$this->error_text = gettext("Sorry, your account may only be activated by agreeing to the waiver.");
			return false;
		}

		/* otherwise, it's yes.  Set the user to 'active' and marked the
		 * signed_waiver field to the current date */
		$res = $DB->query("UPDATE person SET class = 'active', waiver_signed=NOW() where user_id = ?", array($id));

		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
		
	}
	

}

?>
