<?php
/*
 * Code for dealing with user accounts
 */
register_page_handler('person_view', 'PersonView');
register_page_handler('person_delete', 'PersonDelete');
register_page_handler('person_approvenew', 'PersonApproveNewAccount');
register_page_handler('person_edit', 'PersonEdit');
register_page_handler('person_create', 'PersonCreate');
register_page_handler('person_activate', 'PersonActivate');
register_page_handler('person_list', 'PersonList');
register_page_handler('person_listnew', 'PersonListNewAccounts');
register_page_handler('person_changepassword', 'PersonChangePassword');
register_page_handler('person_forgotpassword', 'PersonForgotPassword');

/**
 * Player viewing handler
 */
class PersonView extends Handler
{
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
			'member_id'		=> false,
			'class'		=> false,
			'publish'			=> false,
			'user_edit'				=> false,
#			'user_delete'			=> false,
#			'user_change_perms'		=> false,
			'user_change_password'	=> false,
		);

		return true;
	}

	/**
	 * Permissions check
	 *
	 * This permissions check is much more complex than others, so we
	 * will override the parent and perform all checks here.
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = "You do not have a valid session";
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = "You must provide a user ID";
			return false;
		}

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;
		
		/* Administrator can view all and do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			$this->_permissions['user_change_perms'] = true;
			return true;
		}

		/* Can always view self */
		if($session->attr_get('user_id') == $id) {
			reset($this->_permissions);
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
		$count = $DB->getOne("SELECT COUNT(*) FROM teamroster WHERE status = 'captain' AND player_id = ?", array($id, $id));
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
				 * a real person, but... */
				$this->_permissions['email'] = true;
				$this->_permissions['phone'] = true;
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
			"SELECT * FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$fullname = $row['firstname'] . " " . $row['lastname'];
		
		$this->_page_title .= ": $fullname". 

		$this->tmpl->assign("full_name", $fullname);
		$this->tmpl->assign("user_id", $id);

		if($this->_permissions['username']) {
			$this->tmpl->assign("username", $row['username']);
		}
		
		if($this->_permissions['member_id']) {
			$this->tmpl->assign("member_id", $row['member_id']);
		}
		
		if($this->_permissions['email']) {
			$this->tmpl->assign("email", $row['email']);
		}
		
		if($this->_permissions['phone']) {
			$this->tmpl->assign("home_phone", $row['home_phone']);
			$this->tmpl->assign("work_phone", $row['work_phone']);
			$this->tmpl->assign("mobile_phone", $row['mobile_phone']);
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
			if(array_key_exists('waiver_signed', $row)) {
				$this->tmpl->assign("waiver_signed", $row['waiver_signed']);
			} else {
				$this->tmpl->assign("waiver_signed", "Not signed");
			}
		}
		
		if($this->_permissions['last_login']) {
			if($row['last_login']) {
				$this->tmpl->assign("last_login", $row['last_login']);
				$this->tmpl->assign("client_ip", $row['client_ip']);
			} else {
				$this->tmpl->assign("last_login", "Never logged in");
			}
		}

		if($this->_permissions['publish']) {
			$this->tmpl->assign("allow_publish_email", $row['allow_publish_email']);
			$this->tmpl->assign("allow_publish_phone", $row['allow_publish_phone']);
		}

		/* Now, fetch teams */
		$this->tmpl->assign("teams",
			get_teams_for_user($id));

		return true;
	}
}

/**
 * Approve new account creation
 */
class PersonDelete extends PersonView
{
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
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny',
		);
		return true;
	}

	function set_permission_flags($type) 
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		}
	}

	function process ()
	{
		global $session;
		$step = var_from_getorpost('step');

		/* Safety check: Don't allow us to delete ourselves */
		$id = var_from_getorpost('id');
		if($session->attr_get('user_id') == $id) {
			$this->error_text = "You cannot delete the currently logged in user";
			return false;
		}
		
		switch($step) {
			case 'perform':
				$rc = $this->perform();
				break;
			case 'confirm':
			default:
				$this->set_template_file("Person/admin_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$this->tmpl->assign("page_instructions", "Confirm that you wish to delete this user from the system.");
				$rc = $this->generate_view();
		}
		
		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}

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
		global $DB;
		$id = var_from_getorpost('id');

		/* check if user is team captain       */
		$res = $DB->getOne("SELECT COUNT(*) from teamroster where status = 'captain' AND player_id = ?", array($id, $id));
		if($this->is_database_error($res)) {
			return false;
		}
		if($res > 0) {
			$this->error_text = "Account cannot be deleted while player is a team captain.";
			return false;
		}
		
		/* check if user is league coordinator */
		$res = $DB->getOne("SELECT COUNT(*) from league where coordinator_id = ? OR alternate_id = ?", array($id, $id));
		if($this->is_database_error($res)) {
			return false;
		}
		if($res > 0) {
			$this->error_text = "Account cannot be deleted while player is a league coordinator.";
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

/**
 * Approve new account creation
 */
class PersonApproveNewAccount extends PersonView
{
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
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny',
		);
		return true;
	}

	function set_permission_flags($type) 
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		}
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
				$this->tmpl->assign("page_instructions", "Confirm that you wish to approve this user.  The account will be moved to 'inactive' status.");
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
		global $DB;

		$id = var_from_getorpost('id');

		$person_info = $DB->getRow("SELECT 
			firstname,
			lastname,
			username,
			email,
			year_started,
			gender 
		    FROM person where user_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($person_info)) {
			return false;
		}

		$year_started = $person_info['year_started'];

		$result = $DB->query('UPDATE member_id_sequence SET id=LAST_INSERT_ID(id+1) where year = ?', array($year_started));
		if($this->is_database_error($result)) {
			return false;
		}

		if($DB->affectedRows() == 1) {
			$member_id = $DB->getOne("SELECT LAST_INSERT_ID() from member_id_sequence");
			if($this->is_database_error($member_id)) {
				$this->error_text = "Couldn't get member ID allocation";
				return false;
			}
		} else {
			/* Possible empty, so fill it */
			$lock = $DB->getOne("SELECT GET_LOCK('member_id_${year_started}_lock',10)");
			if($this->is_database_error($lock)) {
				$this->error_text = "Couldn't get lock for member_id allocation";
				return false;
			}
			if($lock == 0) {
				/* Couldn't get lock */
				$this->error_text = "Couldn't get lock for member_id allocation";
				return false;
			}
			$result = $DB->query("REPLACE INTO member_id_sequence values(?,1)", array($year_started));
			$lock = $DB->getOne("SELECT RELEASE_LOCK('member_id_${year_started}_lock')");
			if($this->is_database_error($lock)) {
				return false;
			}
			/* Check the result error after releasing the lock */
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
		
		$res = $DB->query("UPDATE person SET class = 'inactive', member_id = ?  where user_id = ?", array($full_member_id, $id));
		
		if($this->is_database_error($res)) {
			return false;
		}

		/* Ok, it's done.  Now send a mail to the user and tell them. */
		$message = <<<EOM
Dear {$person_info['firstname']} {$person_info['lastname']},

Your {$GLOBALS['APP_NAME']} account has been approved. Your new permanent
member number is
	$full_member_id
This number will be used in the future to identify you for member services
discounts, etc, so please do not lose it.
You may now log in to the system at
	http://{$GLOBALS['APP_SERVER']}{$_SERVER["PHP_SELF"]}
with the username
	{$person_info['username']}
and the password you specified when you created your account.  You will be
asked to confirm your account information and sign a waiver form before
your account will be activated.
Thanks,
{$GLOBALS['APP_ADMIN_NAME']}
EOM;

		$rc = mail($person_info['email'], $GLOBALS['APP_NAME'] . " Account Activation", $message, "From: " . $GLOBALS['APP_ADMIN_EMAIL'] . "\r\n");
		if($rc == false) {
			$this->error_text = "Error sending email to " . $person_info['email'];
			return false;
		}
		
		return true;
	}
}

/**
 * Player edit handler
 */
class PersonEdit extends Handler
{
	var $_id;
	
	function initialize ()
	{
		$this->set_title("Edit Account");
		$this->_permissions = array(
			'edit_email'		=> false,
			'edit_phone'		=> false,
			'edit_username'		=> false,
			'edit_name' 		=> false,
			'edit_birthdate'	=> false,
			'edit_address'		=> false,
			'edit_gender'		=> false,
			'edit_skill' 		=> false,
			'edit_class' 		=> false,
			'edit_publish'		=> false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'self_sufficient',
			'deny',
		);

		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if($type == 'self') {
			$this->_permissions['edit_email'] 		= true;
			$this->_permissions['edit_phone']		= true;
			$this->_permissions['edit_name'] 		= true;
			$this->_permissions['edit_birthdate']	= true;
			$this->_permissions['edit_address']		= true;
			$this->_permissions['edit_gender']		= true;
			$this->_permissions['edit_skill'] 		= true;
			$this->_permissions['edit_publish']		= true;
		} 
	}

	function process ()
	{
		$step = var_from_getorpost('step');

		$this->_id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$this->set_template_file("Person/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Person/edit_form.tmpl");
				$this->tmpl->assign("instructions", "Edit any of the following fields and click 'Submit' when done.");
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

	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=person_view&id=".$this->_id);
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;

		$row = $DB->getRow(
			"SELECT 
				firstname,
				lastname, 
				class,
				allow_publish_email, 
				allow_publish_phone,
				username, 
				email, 
				gender, 
				home_phone, 
				work_phone, 
				mobile_phone, 
				birthdate, 
				skill_level, 
				year_started, 
				addr_street, 
				addr_city, 
				addr_prov, 
				addr_postalcode, 
				class,
				last_login 
			FROM person WHERE user_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("firstname", $row['firstname']);
		$this->tmpl->assign("lastname", $row['lastname']);
		$this->tmpl->assign("id", $this->_id);

		$this->tmpl->assign("username", $row['username']);
		
		$this->tmpl->assign("email", $row['email']);
		
		$this->tmpl->assign("home_phone", $row['home_phone']);
		$this->tmpl->assign("work_phone", $row['work_phone']);
		$this->tmpl->assign("mobile_phone", $row['mobile_phone']);
	
		$this->tmpl->assign("addr_street", $row['addr_street']);
		$this->tmpl->assign("addr_city", $row['addr_city']);
		$this->tmpl->assign("addr_prov", $row['addr_prov']);
		$this->tmpl->assign("addr_postalcode", $row['addr_postalcode']);

		/* And fill provinces array */
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$this->tmpl->assign("provinces",
			array_map(
				"map_callback", 
				array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'))
		);

/* Here */

		$this->tmpl->assign("gender", $row['gender']);
		$this->tmpl->assign("gender_list",
			array_map(
				"map_callback",
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", $row['skill_level']);
		$this->tmpl->assign("skill_list", 
			array_map(
				"map_callback",
				array(1,2,3,4,5))
		);

		$this->tmpl->assign("started_year", $row['year_started'] . "-00-00");
		
		$this->tmpl->assign("birthdate",  $row['birthdate']);
		
		$this->tmpl->assign("allow_publish_email", $row['allow_publish_email']);
		$this->tmpl->assign("allow_publish_phone", $row['allow_publish_phone']);

		$this->tmpl->assign("class", $row['class']);
		$this->tmpl->assign("classes", $this->get_enum_options('person','class'));

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("page_step", 'confirm');
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("instructions", "Edit any of the following fields and click 'Submit' when done.");
			return $this->generate_form();
		}

		$this->tmpl->assign("id", $this->_id);

		$this->tmpl->assign("firstname", var_from_getorpost('firstname'));
		$this->tmpl->assign("lastname", var_from_getorpost('lastname'));
		
		$this->tmpl->assign("username", var_from_getorpost('username'));
		
		$this->tmpl->assign("email", var_from_getorpost('email'));
		
		$this->tmpl->assign("addr_street", var_from_getorpost('addr_street'));
		$this->tmpl->assign("addr_city", var_from_getorpost('addr_city'));
		$this->tmpl->assign("addr_prov", var_from_getorpost('addr_prov'));
		$this->tmpl->assign("addr_postalcode", var_from_getorpost('addr_postalcode'));

		$this->tmpl->assign("home_phone", var_from_getorpost('home_phone'));
		$this->tmpl->assign("work_phone", var_from_getorpost('work_phone'));
		$this->tmpl->assign("mobile_phone", var_from_getorpost('mobile_phone'));

		$this->tmpl->assign("gender", var_from_getorpost('gender'));
		$this->tmpl->assign("skill_level", var_from_getorpost('skill_level'));
		
		$this->tmpl->assign("started_Year", var_from_getorpost('started_Year'));
		
		$this->tmpl->assign("birth_Year", var_from_getorpost('birth_Year'));
		$this->tmpl->assign("birth_Month", var_from_getorpost('birth_Month'));
		$this->tmpl->assign("birth_Day", var_from_getorpost('birth_Day'));
		$this->tmpl->assign("class", var_from_getorpost('class'));
		
		$this->tmpl->assign("allow_publish_email", var_from_getorpost('allow_publish_email'));
		$this->tmpl->assign("allow_publish_phone", var_from_getorpost('allow_publish_phone'));

		return true;
	}

	function perform ()
	{
		global $DB;
		
		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Person/edit_form.tmpl");
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_username']) {
			$fields[] = "username = ?";
			$fields_data[] = var_from_getorpost('username');
		}
		
		if($this->_permissions['edit_email']) {
			$fields[] = "email = ?";
			$fields_data[] = var_from_getorpost('email');
		}
		
		if($this->_permissions['edit_phone']) {
			$fields[] = "home_phone = ?";
			$fields_data[] = var_from_getorpost('home_phone');
			$fields[] = "work_phone = ?";
			$fields_data[] = var_from_getorpost('work_phone');
			$fields[] = "mobile_phone = ?";
			$fields_data[] = var_from_getorpost('mobile_phone');
		}
		
		if($this->_permissions['edit_name']) {
			$fields[] = "firstname = ?";
			$fields_data[] = var_from_getorpost('firstname');
			
			$fields[] = "lastname = ?";
			$fields_data[] = var_from_getorpost('lastname');
		}
		
		if($this->_permissions['edit_address']) {
			$fields[] = "addr_street = ?";
			$fields_data[] = var_from_getorpost('addr_street');
			
			$fields[] = "addr_city = ?";
			$fields_data[] = var_from_getorpost('addr_city');
			
			$fields[] = "addr_prov = ?";
			$fields_data[] = var_from_getorpost('addr_prov');
			
			$fields[] = "addr_postalcode = ?";
			$fields_data[] = str_replace(" ","",var_from_getorpost('addr_postalcode'));
		}
		
		if($this->_permissions['edit_birthdate']) {
			$fields[] = "birthdate = ?";
			$fields_data[] = join("-",array(
				var_from_getorpost('birth_Year'),
				var_from_getorpost('birth_Month'),
				var_from_getorpost('birth_Day')));
				
		}
		
		if($this->_permissions['edit_class']) {
			$fields[] = "class = ?";
			$fields_data[] = var_from_getorpost('class');
				
		}
		
		if($this->_permissions['edit_gender']) {
			$fields[] = "gender = ?";
			$fields_data[] = var_from_getorpost('gender');
		}
		
		if($this->_permissions['edit_skill']) {
			$fields[] = "skill_level = ?";
			$fields_data[] = var_from_getorpost('skill_level');
			$fields[] = "year_started = ?";
			$fields_data[] = var_from_getorpost('started_Year');
		}

		if($this->_permissions['edit_publish']) {
			$fields[] = "allow_publish_email = ?";
			$fields_data[] = var_from_getorpost('allow_publish_email');
			$fields[] = "allow_publish_phone = ?";
			$fields_data[] = var_from_getorpost('allow_publish_phone');
		}

		if(count($fields_data) != count($fields)) {
			$this->error_text = "Internal error: Incorrect number of fields set";
			return false;
		}
		
		if(count($fields) <= 0) {
			$this->error_text = "You have no permission to edit";
			return false;
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE user_id = ?";
		
		$fields_data[] = $this->_id;
		
		$res = $DB->query($sql, $fields_data);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		$rc = true;
		$this->error_text = "";
	
		if($this->_permissions['edit_name']) {
			$firstname = var_from_getorpost('firstname');
			$lastname = var_from_getorpost('lastname');
			if( ! validate_name_input($firstname) || ! validate_name_input($lastname)) {
				$rc = false;
				$this->error_text .= "\n<br>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
			}
		}

		if($this->_permissions['edit_username']) {
			$username = var_from_getorpost('username');
			if( ! validate_name_input($username) ) {
				$rc = false;
				$this->error_text .= "\n<br>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
		}

		if($this->_permissions['edit_email']) {
			$email = var_from_getorpost('email');
			if ( ! validate_email_input($email) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply a valid email address";
			}
		}

		if($this->_permissions['edit_email']) {
			$home_phone = var_from_getorpost('home_phone');
			$work_phone = var_from_getorpost('work_phone');
			$mobile_phone = var_from_getorpost('mobile_phone');
			if( !validate_nonblank($home_phone) &&
				!validate_nonblank($work_phone) &&
				!validate_nonblank($mobile_phone) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply at least one valid telephone number.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($home_phone) && !validate_telephone_input($home_phone)) {
				$rc = false;
				$this->error_text .= "\n<br>Home telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($work_phone) && !validate_telephone_input($work_phone)) {
				$rc = false;
				$this->error_text .= "\n<br>Work telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($mobile_phone) && !validate_telephone_input($mobile_phone)) {
				$rc = false;
				$this->error_text .= "\n<br>Mobile telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
		}

		if($this->_permissions['edit_address']) {
			$addr_street = var_from_getorpost('addr_street');
			if( !validate_nonhtml($addr_street) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply a street address.";
			}
			$addr_city = var_from_getorpost('addr_city');
			if( !validate_nonhtml($addr_city) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply a city.";
			}
			$addr_prov = var_from_getorpost('addr_prov');
			if( !validate_nonhtml($addr_prov) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply a province.";
			}
			$addr_postalcode = var_from_getorpost('addr_postalcode');
			if( !validate_postalcode($addr_postalcode) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must supply a valid Canadian postal code.";
			}
		}
		
		if($this->_permissions['edit_gender']) {
			$gender = var_from_getorpost('gender');
			if( !preg_match("/^[mf]/i",$gender ) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must select either male or female for gender.";
			}
		}
		
		if($this->_permissions['edit_skill']) {
			$skill = var_from_getorpost('skill');
			if( $skill < 0 || $skill > 5 ) {
				$rc = false;
				$this->error_text .= "\n<br>You must select a skill level between 0 and 5";
			}
			
			$year_started = var_from_getorpost('started_Year');
			$current = localtime(time(),1);
			$this_year = $current['tm_year'] + 1900;
			if( $year_started < ($this_year - 30)  || $year_started > $this_year ) {
				$rc = false;
				$this->error_text .= "\n<br>You must select the year you started playing the sport";
			}
		}
		
		if($this->_permissions['edit_birthdate']) {
			$birthyear = var_from_getorpost('birth_Year');
			$birthmonth = var_from_getorpost('birth_Month');
			$birthday = var_from_getorpost('birth_Day');
			if( !validate_date_input($birthyear, $birthmonth, $birthday) ) {
				$rc = false;
				$this->error_text .= "\n<br>You must provide a valid birthdate";
			}
		}
		
		return $rc;
	}
}

/**
 * Player create handler
 */
class PersonCreate extends PersonEdit
{
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

		$this->_required_perms = array( 'allow' );

		return true;
	}

	function generate_form ()
	{
		
		$this->tmpl->assign("instructions", "To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system.");
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$this->tmpl->assign("provinces",
			array_map(
				"map_callback", 
				array('Ontario','Quebec','Alberta','British Columbia','Manitoba','New Brunswick','Newfoundland','Northwest Territories','Nunavut','Nova Scotia','Prince Edward Island','Saskatchewan','Yukon'))
		);

		$this->tmpl->assign("gender", "");
		$this->tmpl->assign("gender_list",
			array_map(
				"map_callback",
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", "");
		$this->tmpl->assign("skill_list", 
			array_map(
				"map_callback",
				array(1,2,3,4,5))
		);

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
		global $DB;

		$password_once = var_from_getorpost("password_once");
		$password_twice = var_from_getorpost("password_twice");
		if($password_once != $password_twice) {
			$this->error_text = "First and second entries of password do not match";
			return false;
		}
		$crypt_pass = md5($password_once);
		
		$res = $DB->query("INSERT into person (username,password,class) VALUES (?,?,'new')", array(var_from_getorpost('username'), $crypt_pass));
		if($this->is_database_error($res)) {
			if(strstr($this->error_text,"already exists: INSERT into person (username,password,class) VALUES")) {
				$this->error_text = "A user with that username already exists; please go back and try again";
			}
			return false;
		}
		$this->_id = $DB->getOne("SELECT LAST_INSERT_ID() from person");

		$this->set_template_file("Person/create_result.tmpl");
		
		return parent::perform();
	}
	
	/**
	 * Override display to avoid redirects.
	 */
	function display ()
	{
		return Handler::display();
	}


}

/**
 * Account reactivation
 *
 * Accounts must be periodically reactivated to ensure that they are
 * reasonably up-to-date.
 */
class PersonActivate extends PersonEdit
{
	function initialize ()
	{
		parent::initialize();
		$this->set_title("Activate Account");

		return true;
	}

	/**
	 * Check to see if this user can activate themselves.
	 * This is only possible if the user is in the 'inactive' class. This
	 * also means that the user can't have a valid session.
	 */
	function has_permission ()
	{
		global $session;
		if(!$session->is_valid()) {
			if ($session->attr_get('class') != 'inactive') {
				$this->error_text = "You do not have a valid session";
				return false;
			} 
		}

		$this->_id = $session->attr_get('user_id');
		
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
				$this->tmpl->assign("instructions", "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
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
		global $DB, $session;
		
		$id = $session->attr_get('user_id');
		$signed = var_from_getorpost('signed');
		
		if('yes' != $signed) {
			$this->error_text = "Sorry, your account may only be activated by agreeing to the waiver.";
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

/**
 * Player list handler
 */
class PersonList extends Handler
{
	function initialize ()
	{
		global $session;
		$this->set_title("List Users");
		$this->_permissions = array(
			'delete' => false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow',
		);

		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->_permissions['delete'] = true;
		} 
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("common/generic_list.tmpl");

		$letter = var_from_getorpost("letter");
		$letters = $DB->getCol("select distinct UPPER(SUBSTRING(lastname,1,1)) as letter from person ORDER BY letter asc");
		if($this->is_database_error($letters)) {
			return false;
		}
		
		if(!isset($letter)) {
			$letter = $letters[0];
		}

		$found = $DB->getAll(
			"SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id_val 
			 FROM person 
			 WHERE lastname LIKE ? ORDER BY lastname",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$ops = array(
			array(
				'description' => 'view',
				'action' => 'person_view'
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'description' => 'delete',
				'action' => 'person_delete'
			);
		}
		$this->tmpl->assign("available_ops", $ops);
		
		$this->tmpl->assign("page_op", "person_list");
		$this->tmpl->assign("letter", $letter);
		$this->tmpl->assign("letters", $letters);
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}
}

/**
 * Player list handler
 */
class PersonListNewAccounts extends Handler
{
	function initialize ()
	{
		$this->set_title("List New Accounts");
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("common/generic_list.tmpl");

		$found = $DB->getAll(
			"SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id_val 
			 FROM person 
			 WHERE
			 	class = 'new'
			 ORDER BY lastname", DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'person_view'
			),
			array(
				'description' => 'approve',
				'action' => 'person_approvenew'
			),
			array(
				'description' => 'delete',
				'action' => 'person_delete'
			),
		));
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

/**
 * Player password change
 */
class PersonChangePassword extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'self_sufficient',
			'deny',
		);
		return true;
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
			return $this->output_redirect("op=person_view&id=$id");
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
		if(!isset($row)) {
			$this->error_text = "That user does not exist";
			return false;
		}
		
		$this->set_title("Changing Password for " . $row['firstname'] . " " .$row['lastname'] );

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
			$this->error_text = "You must enter the same password twice.";
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

class PersonForgotPassword extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'allow',
		);
		return true;
	}

	function process()
	{
		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				$this->set_template_file("Person/forgot_password_result.tmpl");
				$rc = $this->perform();	
				break;
			default:
				$this->set_template_file("Person/forgot_password_form.tmpl");
				$rc = true;
		}

		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}

	function perform ()
	{
		global $DB;

		$username = var_from_getorpost('username');
		$member_id = var_from_getorpost('member_id');
		$email = var_from_getorpost('email');
		
		$fields = array();
		$fields_data = array();
		if(validate_nonblank($username)) {
			$fields[] = "username = ?";
			$fields_data[] = $username;
		}
		if(validate_nonblank($email)) {
			$fields[] = "email = ?";
			$fields_data[] = $email;
		}
		if(validate_nonblank($member_id)) {
			$fields[] = "member_id = ?";
			$fields_data[] = $member_id;
		}
		
		if( count($fields) < 1 || (count($fields) != count($fields_data))) {
			$this->error_text = "You must supply at least one of username, member ID, or email address";
			return false;
		}

		/* Now, try and find the user */
		$sql = "SELECT user_id,firstname,lastname,username,email FROM person WHERE ";
		$sql .= join(" AND ",$fields);

		$users = $DB->getAll($sql, $fields_data, DB_FETCHMODE_ASSOC);
		if($this->is_database_error($users)) {
			return false;
		}
		
		if(count($users) > 1) {
			$this->error_text = "You did not supply enough identifying information.  Try filling in more data.";
			return false;
		}

		/* Now, we either have one or zero users.  Regardless, we'll present
		 * the user with the same output; that prevents them from using this
		 * to guess valid usernames.
		 */
		if(count($users) != 1) {
			/* Just return true, even though we did nothing */
			return true;
		}
	
		/* Generate a password */
		$pass = generate_password();
		$cryptpass = md5($pass);
		$res = $DB->query("UPDATE person SET password = ? WHERE user_id = ?", array($cryptpass, $users[0]['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}

		$message = <<<EOM
Dear {$users[0]['firstname']} {$users[0]['lastname']},

Someone, probably you, just requested that your password for the account
	{$users[0]['username']}
be reset.  Your new password is
	$pass
Since this password has been sent via unencrypted email, you should change
it as soon as possible.

If you didn't request this change, don't worry.  Your account password
can only ever be mailed to the email address specified in your 
{$GLOBALS['APP_NAME']} system account.  However, if you think someone may
be attempting to gain unauthorized access to your account, please contact
the system administrator.
EOM;

		/* And fire off an email */
		$rc = mail($users[0]['email'], $GLOBALS['APP_NAME'] . " Password Update", $message, "From: " . $GLOBALS['APP_ADMIN_EMAIL'] . "\r\n");
		if($rc == false) {
			$this->error_text = "System was unable to send email to that user.  Please contact system administrator.";
			return false;
		}
		
		return true;
	}
}

/**
 * Return array of team information for the given userid
 * 
 * @param integer $userid  User ID
 * @return array Array of all teams with this player, with id, name, and position of player for each team.
 */
function get_teams_for_user($userid) 
{
	global $DB;
	$rows = $DB->getAll(
		"SELECT 
			r.status AS position,
            r.team_id AS id,
            t.name AS name
        FROM 
            teamroster r LEFT JOIN team t USING(team_id)
        WHERE 
            r.player_id = ?",
	array($userid), DB_FETCHMODE_ASSOC);
	for($i=0; $i < count($rows); $i++) {
		$rows[$i]['position'] = display_roster_status($rows[$i]['position']);
	}
	return $rows;
}

function generate_password()
{
	$chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
	$pass = '';
	for($i=0;$i<8;$i++) {
		$pass .= $chars{mt_rand(0,strlen($chars)-1)};
	}
	return $pass;
}

function map_callback($item)
{
	return array("output" => $item, "value" => $item);
}
?>
