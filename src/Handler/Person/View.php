<?php
register_page_handler('person_view', 'PersonView');

/**
 * Player viewing handler
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonView extends Handler
{

	/* Permissions bits for various items of interest */
	var $_permissions;
	
	/** 
	 * Initializer for PlayerView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "View Person";
		$this->_permissions = array(
			'email'		=> false,
			'phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
			'last_login'	=> false,
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
		global $DB, $session, $userid;
		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;

		/* Can always view self */
		if($session->attr_get('user_id') == $userid) {
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		/* Administrator can view all */
		if($session->attr_get('class') == 'administrator') {
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		/* 
		 * TODO: 
		 * See if we're looking at a volunteer or team captain
		 */

		/*
		 * See if we're looking at a regular player with possible restrictions
		 */
		$sth = $DB->prepare( "SELECT class, allow_publish_email, allow_publish_phone, FROM person WHERE user_id = ?");
		$res = $DB->execute($sth,$userid);
		if(DB::isError($res)) {
		 	/* TODO: Handle database error */
			return false;
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC, 0);
		$res->free();
		if($row['allow_publish_email'] == 'yes') {
			$this->_permissions['email'] = true;
		}
		if($row['allow_publish_phone'] == 'yes') {
			$this->_permissions['phone'] = true;
		}

		return true;
	}

	function process ()
	{
		global $DB, $userid;

		$this->set_template_file("Person/view.tmpl");
		
		$sth = $DB->prepare("SELECT CONCAT(firstname,' ',lastname) AS fullname, username, primary_email, gender, primary_phone, birthdate, skill_level, year_started, addr_street, addr_city, addr_prov, addr_postalcode, last_login FROM person WHERE user_id = ?");
		$res = $DB->execute($sth,$userid);
		if(DB::isError($res)) {
		 	/* TODO: Handle database error */
			return false;
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC, 0);
		$res->free();

		if(!isset($row)) {
			$this->error_text = gettext("The user [$user_id] does not exist");
		}

		$this->tmpl->assign("full_name", $row['fullname']);
		$this->tmpl->assign("user_id", $userid);

		if($this->_permissions['username']) {
			$this->tmpl->assign("username", $row['username']);
		}
		
		if($this->_permissions['email']) {
			$this->tmpl->assign("email", $row['primary_email']);
		}
		
		if($this->_permissions['phone']) {
			$ary = explode(" ", $row['primary_phone']);
			$new_phone = "($ary[0]) $ary[1]-$ary[2]";
			if(isset($ary[3])) {
				$new_phone .= " x $ary[3]";
			}
			$this->tmpl->assign("phone", $new_phone);
		}
		
		if($this->_permissions['address']) {
			$this->tmpl->assign("address", true);
			$this->tmpl->assign("addr_street", $row['addr_street']);
			$this->tmpl->assign("addr_city", $row['addr_city']);
			$this->tmpl->assign("addr_prov", $row['addr_prov']);
			$this->tmpl->assign("addr_postalcode", $row['addr_postalcode']);
		}

		if($this->_permissions['birthdate']) {
			$this->tmpl->assign("birthdate", $row['birthdate']);
		}
		
		if($this->_permissions['gender']) {
			$this->tmpl->assign("gender", $row['gender']);
		}
		
		if($this->_permissions['skill']) {
			$this->tmpl->assign("skill", true);
			$this->tmpl->assign("skill_level", $row['skill_level']);
			$this->tmpl->assign("year_started", $row['year_started']);
		}
		
		if($this->_permissions['last_login']) {
			if($row['last_login']) {
				$this->tmpl->assign("last_login", $row['last_login']);
			} else {
				$this->tmpl->assign("last_login", gettext("Never logged in"));
			}
		}

		/* Now, fetch teams */
		$this->tmpl->assign("teams",
			get_teams_for_user($userid));

		return true;
	}
}

?>
