<?php
register_page_handler('person_create', 'PersonCreate');

/**
 * Player create handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonCreate extends PersonEdit
{
	/** 
	 * Initializer for PersonEdit class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("Create New Account");
		$this->_permissions = array(
			'edit_email'		=> true,
			'edit_phone'		=> true,
			'edit_username'		=> true,
			'edit_password'		=> true,
			'edit_name' 		=> true,
			'edit_birthdate'	=> true,
			'edit_address'		=> true,
			'edit_gender'		=> true,
			'edit_skill' 		=> true,
		);

		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id;

		/* Anyone can create a new account */
		return true;
	}

	function generate_form ()
	{
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$this->tmpl->assign("provinces",
			array_map(
				array($this, "map_callback"), 
				array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'))
		);

/* Here */

		$this->tmpl->assign("gender", "");
		$this->tmpl->assign("gender_list",
			array_map(
				array($this, "map_callback"),
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", "");
		$this->tmpl->assign("skill_list", 
			array_map(
				array($this, "map_callback"),
				array(1,2,3,4,5))
		);

		/* ... and set permissions flags */
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}

	function generate_confirm () 
	{
		$this->tmpl->assign("password_once", var_from_getorpost('password_once'));
		$this->tmpl->assign("password_twice", var_from_getorpost('password_twice'));
		return parent::generate_confirm();
	}

	function perform ()
	{
		global $DB, $id;

		/* TODO: Validate passwords here */
		$password_once = var_from_getorpost("password_once");
		$password_twice = var_from_getorpost("password_twice");
		if($password_once != $password_twice) {
			$this->error_text = gettext("First and second entries of password do not match");
			return false;
		}
		$crypt_pass = md5($password_once);
		
		$st = $DB->prepare("INSERT into person (username,password,class) VALUES (?,?,'new')");
		$res = $DB->execute($st, array(var_from_getorpost('username'), $crypt_pass));
		if($this->is_database_error($res)) {
			return false;
		}
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from person");

		return parent::perform();
	}
}

?>
