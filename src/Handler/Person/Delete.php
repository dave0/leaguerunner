<?php
register_page_handler('person_delete', 'PersonDelete');

/**
 * Approve new account creation
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonDelete extends PersonView
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("Delete Account");
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
		global $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}

		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a user ID");
			return false;
		}

		if($session->attr_get('user_id') == $id) {
			$this->error_text = gettext("You cannot delete the currently logged in user");
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
				$this->tmpl->assign("page_instructions", gettext("Confirm that you wish to delete this user from the system."));
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
			return $this->output_redirect("op=person_list");
		}
		return parent::display();
	}

	/**
	 * Delete a user account from the system.
	 *
	 * Here, we need to not only remove the user account, but
	 * 	- ensure user is not a team captain or assistant
	 * 	- ensure user is not a league coordinator
	 * 	- remove user from all team rosters
	 */
	function perform ()
	{
		global $DB, $id;

		/* check if user is team captain       */
		$res = $DB->getOne("SELECT COUNT(*) from team where captain_id = ? OR assistant_id = ?", array($id, $id));
		if($this->is_database_error($res)) {
			return false;
		}
		if($res > 0) {
			$this->error_text = gettext("Account cannot be deleted while player is team captain or assistant.");
			return false;
		}
		
		/* check if user is league coordinator */
		$res = $DB->getOne("SELECT COUNT(*) from league where coordinator_id = ? OR alternate_id = ?", array($id, $id));
		if($this->is_database_error($res)) {
			return false;
		}
		if($res > 0) {
			$this->error_text = gettext("Account cannot be deleted while player is a league coordinator.");
			return false;
		}
		
		/* remove user from team rosters  */
		$res = $DB->query("DELETE from teamroster WHERE player_id = ?", array($id));
		if($this->is_database_error($res)) {
			return false;
		}
		
		/* remove user account */
		$res = $DB->query("DELETE from person WHERE user_id = ?", array($id));
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}
}

?>
