<?php
register_page_handler('person_approvenew', 'PersonApproveNewAccount');

/**
 * Approve new account creation
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonApproveNewAccount extends PersonView
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("Approve Account");
		$this->_permissions = array(
			'email'		=> false,
			'phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
			'last_login'		=> false,
			'user_edit'				=> false,
			'user_change_password'	=> false,
		);
		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}

		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a user ID");
			return false;
		}
		
		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		$this->error_text = gettext("You do not have permission to perform that operation");
		return false;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				$rc = $this->perform();
				break;
			case 'confirm':
			default:
				$this->set_template_file("Person/admin_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$this->tmpl->assign("page_instructions", gettext("Confirm that you wish to approve this user.  The account will be moved to 'inactive' status."));
				$rc = $this->generate_view();
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
		if($step == 'perform') {
			return $this->output_redirect("op=person_listnew");
		}
		return parent::display();
	}

	function perform ()
	{
		global $DB, $id;

		/* TODO Here is where we should generate the member number */

		
		$sth = $DB->prepare("UPDATE person SET class = 'inactive' where user_id = ?");
		$res = $DB->execute($sth, array($id));
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}
}

?>
