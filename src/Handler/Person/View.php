<?php
register_page_handler('person_view', 'PersonView');

/**
 * Player viewing handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonView extends Handler
{
	/** 
	 * Initializer for PlayerView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("View Account:");
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
			'waiver_signed'		=> false,
			'class'		=> false,
			'user_edit'				=> false,
#			'user_delete'			=> false,
#			'user_change_perms'		=> false,
			'user_change_password'	=> false,
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

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;
		
		/* Administrator can view all and do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			if($session->attr_get('user_id') != $id) {
				$this->_permissions['user_delete'] = true;
			}
			$this->_permissions['user_change_perms'] = true;
			return true;
		}

		/* Can always view self */
		if($session->attr_get('user_id') == $id) {
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		/* 
		 * See if we're looking at a team captain.  People who are team
		 * captains can always have their email and phone number viewed for
		 * contact purposes.
		 */
		$count = $DB->getOne("SELECT COUNT(*) FROM team WHERE captain_id = ? OR assistant_id = ?", array($id, $id));
		if($this->is_database_error($count)) {
			return false;
		}
		if($count > 0) {
			/* is captain of at least one team, so we publish email and phone */
			$this->_permissions['email'] = true;
			$this->_permissions['phone'] = true;
			return true; /* since the following checks are now irrelevant */
		}

		/*
		 * See what the player's class is.  Some classes cannot be viewed
		 * unless you are 'administrator'.  Also, 'volunteer' class requires
		 * that email and phone be published.
		 */
		$row = $DB->getRow(
			"SELECT 
				class, 
				allow_publish_email, 
				allow_publish_phone
			FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}
		
		switch($row['class']) {
			case 'new':
			case 'locked':
				/* players of class 'new' and 'locked' can only be viewed by
				 * 'administrator' class, and this case is handled above.
				 */
				return false;
				break;
			case 'administrator':
				/* No point in viewing this user's contact info since it's not
				 * a real person */
				break;
			case 'volunteer':
				/* volunteers have their contact info published */
				$this->_permissions['email'] = true;
				$this->_permissions['phone'] = true;
				break;
			case 'active':
			case 'inactive':
				if($row['allow_publish_email'] == 'yes') {
					$this->_permissions['email'] = true;
				}
				if($row['allow_publish_phone'] == 'yes') {
					$this->_permissions['phone'] = true;
				}
				break;
			default:
				/* do nothing */
				
		}

		return true;
	}

	function process ()
	{	
		$this->set_template_file("Person/view.tmpl");
	
		reset($this->_permissions);
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}
		return $this->generate_view();
	}

	function generate_view ()
	{
		global $DB, $id;
		$row = $DB->getRow(
			"SELECT 
				CONCAT(firstname,' ',lastname) AS fullname, 
				username, 
				email, 
				gender, 
				telephone, 
				birthdate, 
				skill_level, 
				year_started, 
				addr_street, 
				addr_city, 
				addr_prov, 
				addr_postalcode, 
				last_login,
				class,
				waiver_signed,
				client_ip 
			FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
	
		$this->_page_title .= ": ". $row['fullname'];

		$this->tmpl->assign("full_name", $row['fullname']);
		$this->tmpl->assign("user_id", $id);

		if($this->_permissions['username']) {
			$this->tmpl->assign("username", $row['username']);
		}
		
		if($this->_permissions['email']) {
			$this->tmpl->assign("email", $row['email']);
		}
		
		if($this->_permissions['phone']) {
			$ary = explode(" ", $row['telephone']);
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

		if($this->_permissions['class']) {
			$this->tmpl->assign("class", $row['class']);
		}
		
		if($this->_permissions['waiver_signed']) {
			if($row['waiver_signed']) {
				$this->tmpl->assign("waiver_signed", $row['waiver_signed']);
			} else {
				$this->tmpl->assign("waiver_signed", gettext("Not signed"));
			}
		}
		
		if($this->_permissions['last_login']) {
			if($row['last_login']) {
				$this->tmpl->assign("last_login", $row['last_login']);
				$this->tmpl->assign("client_ip", $row['client_ip']);
			} else {
				$this->tmpl->assign("last_login", gettext("Never logged in"));
			}
		}

		/* Now, fetch teams */
		$this->tmpl->assign("teams",
			get_teams_for_user($id));

		return true;
	}
}

?>
