<?php
register_page_handler('person_changepassword', 'PersonChangePassword');

/**
 * Player password change
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonChangePassword extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "Change Password";
		return true;
	}

	function has_permission ()
	{
		global $session, $_GET;
		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			/* assume self */
			$_GET['id'] = $session->attr_get('user_id');
			return true;
		}

		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			return true;
		}
		
		/* user can change own password */
		if($session->attr_get('user_id') == $id) {
			return true;
		}

		return false;
	}

	function process()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				$rc = $this->perform();	
				break;
			default:
				$this->set_template_file("Person/change_password.tmpl");
				$rc = $this->generate_form();
		}

		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}
	
	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		if($step == 'perform') {
			return $this->output_redirect("op=person_view;id=$id");
		}
		return parent::display();
	}

	function generate_form()
	{
		global $DB;

		$id = var_from_getorpost('id');
		
		$row = $DB->getRow(
			"SELECT 
				firstname,
				lastname,
				username 
			 FROM 
			 	person WHERE user_id = ?",
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("firstname", $row['firstname']);
		$this->tmpl->assign("lastname", $row['lastname']);
		$this->tmpl->assign("username", $row['username']);
		$this->tmpl->assign("id", $id);

		return true;
	}

	function perform ()
	{
		global $DB;

		$id = var_from_getorpost('id');
		$pass_one = var_from_getorpost('password_one');
		$pass_two = var_from_getorpost('password_two');

		if($pass_one != $pass_two) {
			$this->error_text = gettext("You must enter the same password twice.");
			return false;
		}

		
		$sth = $DB->prepare("UPDATE person set password = ? WHERE user_id = ?");
		if($this->is_database_error($sth)) {
			return false;
		}
		
		$res = $DB->execute($sth, array(md5($pass_one), $id));
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}
}
