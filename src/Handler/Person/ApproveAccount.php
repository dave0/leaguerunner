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

		$person_info = $DB->getRow("SELECT year_started,gender FROM person where user_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($person_info)) {
			return false;
		}

		$year_started = $person_info['year_started'];

		/* TODO Here is where we should generate the member number */
		$result = $DB->query('UPDATE member_id_sequence SET id=LAST_INSERT_ID(id+1) where year = ?', array($year_started));
		if($this->is_database_error($result)) {
			return false;
		}
		$member_id = $DB->getOne("SELECT LAST_INSERT_ID() from member_id_sequence");
		if($this->is_database_error($member_id)) {
			$this->error_text = gettext("Couldn't get member ID allocation");
			return false;
		}
		if($member_id == 0) {
			/* Possible empty, so fill it */
			$result = $DB->getOne("SELECT GET_LOCK('member_id_${year_started}_lock',10)");
			if($this->is_database_error($result)) {
				$this->error_text = gettext("Couldn't get lock for member_id allocation");
				return false;
			}
			if($result == 0) {
				/* Couldn't get lock */
				$this->error_text = gettext("Couldn't get lock for member_id allocation");
				return false;
			}
			$result = $DB->query("REPLACE INTO member_id_sequence values(?,0)", array($year_started));
			if($this->is_database_error($result)) {
				return false;
			}
			$result = $DB->getOne("SELECT RELEASE_LOCK('member_id_${year_started}_lock')");
			if($this->is_database_error($result)) {
				return false;
			}
			$member_id = 1;
		}

		/* Now, that's really not the full member ID.  We need to build that
		 * from other info too.
		 */
		$full_member_id = sprintf("%.4d%.1d%04d", 
			$person_info['year_started'],
			($person_info['gender'] == "Male") ? 0 : 1,
			$member_id);
		
		$sth = $DB->prepare("UPDATE person SET class = 'inactive', member_id = ?  where user_id = ?");
		$res = $DB->execute($sth, array($full_member_id, $id));
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}
}

?>
