<?php
register_page_handler('person_edit', 'PersonEdit');

/**
 * Player edit handler
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonEdit extends Handler
{
	/** 
	 * Initializer for PersonEdit class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "Edit Person";
		$this->_permissions = array(
			'edit_email'		=> false,
			'edit_phone'		=> false,
#			'edit_username'		=> false,
			'edit_name' 		=> false,
			'edit_birthdate'	=> false,
			'edit_address'		=> false,
			'edit_gender'		=> false,
			'edit_skill' 		=> false,
		);

		return true;
	}

	/**
	 * Check if the current session has permission to view this player.
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the target player (return true)
	 * check if the session user is the system admin  (return true)
	 * Now, check permissions of session to view this user
	 *
	 * @access public
	 * @return boolean success/fail
	 */
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
			/* Also allow editing of usernames */
			$this->_permissions['edit_username'] = true;
			return true;
		}

		/* Can always edit most self things */
		if($session->attr_get('user_id') == $id) {
			$this->enable_all_perms();
			return true;
		}

		/* 
		 * TODO: 
		 * See if we're a volunteer with user edit permission
		 */

		return true;
	}

	function process ()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Person/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$this->set_template_file("Person/edit_result.tmpl");
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Person/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
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

	function generate_form ()
	{
		global $DB, $id;

		$row = $DB->getRow(
			"SELECT 
				firstname,
				lastname, 
				class,
				allow_publish_email, 
				allow_publish_phone,
				username, 
				primary_email, 
				gender, 
				primary_phone, 
				birthdate, 
				skill_level, 
				year_started, 
				addr_street, 
				addr_city, 
				addr_prov, 
				addr_postalcode, 
				last_login 
			FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("firstname", $row['firstname']);
		$this->tmpl->assign("lastname", $row['lastname']);
		$this->tmpl->assign("user_id", $id);

		$this->tmpl->assign("username", $row['username']);
		
		$this->tmpl->assign("primary_email", $row['primary_email']);
		
		$ary = explode(" ", $row['primary_phone']);
		$this->tmpl->assign("phone_areacode", $ary[0]);
		$this->tmpl->assign("phone_prefix", $ary[1]);
		$this->tmpl->assign("phone_number", $ary[2]);
		if(isset($ary[3])) {
			$this->tmpl->assign("phone_extension", $ary[3]);
		}
		
		$this->tmpl->assign("addr_street", $row['addr_street']);
		$this->tmpl->assign("addr_city", $row['addr_city']);
		$this->tmpl->assign("addr_prov", $row['addr_prov']);
		$this->tmpl->assign("addr_postalcode", $row['addr_postalcode']);

		/* And fill provinces array */
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$this->tmpl->assign("provinces",
			array_map(
				array($this, "map_callback"), 
				array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'))
		);

/* Here */

		$this->tmpl->assign("gender", $row['gender']);
		$this->tmpl->assign("gender_list",
			array_map(
				array($this, "map_callback"),
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", $row['skill_level']);
		$this->tmpl->assign("skill_list", 
			array_map(
				array($this, "map_callback"),
				array(1,2,3,4,5))
		);

		$this->tmpl->assign("started_year", $row['year_started'] . "-00-00");
		
		$this->tmpl->assign("birthdate",  $row['birthdate']);
		
		$this->tmpl->assign("allow_publish_email",  $row['allow_publish_email']);
		$this->tmpl->assign("allow_publish_phone",  $row['allow_publish_phone']);

		return true;
	}

	function generate_confirm ()
	{
		global $DB, $id;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("firstname", var_from_getorpost('firstname'));
		$this->tmpl->assign("lastname", var_from_getorpost('lastname'));
		
		$this->tmpl->assign("username", var_from_getorpost('username'));
		
		$this->tmpl->assign("primary_email", var_from_getorpost('primary_email'));
		
		$this->tmpl->assign("addr_street", var_from_getorpost('addr_street'));
		$this->tmpl->assign("addr_city", var_from_getorpost('addr_city'));
		$this->tmpl->assign("addr_prov", var_from_getorpost('addr_prov'));
		$this->tmpl->assign("addr_postalcode", var_from_getorpost('addr_postalcode'));

		$this->tmpl->assign("phone_areacode", var_from_getorpost('phone_areacode'));
		$this->tmpl->assign("phone_prefix", var_from_getorpost('phone_prefix'));
		$this->tmpl->assign("phone_number", var_from_getorpost('phone_number'));
		$this->tmpl->assign("phone_extension", var_from_getorpost('phone_extension'));

		$this->tmpl->assign("gender", var_from_getorpost('gender'));
		$this->tmpl->assign("skill_level", var_from_getorpost('skill_level'));
		
		$this->tmpl->assign("started_Year", var_from_getorpost('started_Year'));
		
		$this->tmpl->assign("birth_Year", var_from_getorpost('birth_Year'));
		$this->tmpl->assign("birth_Month", var_from_getorpost('birth_Month'));
		$this->tmpl->assign("birth_Day", var_from_getorpost('birth_Day'));
		
		$this->tmpl->assign("allow_publish_email", var_from_getorpost('allow_publish_email'));
		$this->tmpl->assign("allow_publish_phone", var_from_getorpost('allow_publish_phone'));

		return true;
	}

	function perform ()
	{
		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}
	
		$this->error_text = gettext("Argh, that part isn't implemented yet.");
		return false;	
	}

	function validate_data ()
	{
		/* TODO: Actually validate some of our data! */
		return true;
	}

	function map_callback($item)
	{
		return array("output" => $item, "value" => $item);
	}
}

?>
