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
				member_id,
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
		
		if($this->_permissions['member_id']) {
			$this->tmpl->assign("member_id", $row['member_id']);
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
		$step = var_from_getorpost('step');

		/* Safety check: Don't allow us to delete ourselves */
		$id = var_from_getorpost('id');
		if($session->attr_get('user_id') == $id) {
			$this->error_text = gettext("You cannot delete the currently logged in user");
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
				$this->tmpl->assign("page_instructions", gettext("Confirm that you wish to delete this user from the system."));
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
			$this->error_text = gettext("Account cannot be deleted while player is a team captain.");
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
		global $DB;

		$id = var_from_getorpost('id');

		$person_info = $DB->getRow("SELECT year_started,gender FROM person where user_id = ?", array($id), DB_FETCHMODE_ASSOC);
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
				$this->error_text = gettext("Couldn't get member ID allocation");
				return false;
			}
		} else {
			/* Possible empty, so fill it */
			$lock = $DB->getOne("SELECT GET_LOCK('member_id_${year_started}_lock',10)");
			if($this->is_database_error($lock)) {
				$this->error_text = gettext("Couldn't get lock for member_id allocation");
				return false;
			}
			if($lock == 0) {
				/* Couldn't get lock */
				$this->error_text = gettext("Couldn't get lock for member_id allocation");
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
		
		return true;
	}
}

/**
 * Player edit handler
 */
class PersonEdit extends Handler
{
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
		} 
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
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Person/edit_form.tmpl");
				$this->tmpl->assign("instructions", gettext("Edit any of the following fields and click 'Submit' when done."));
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
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=person_view&id=$id");
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;

		$id = var_from_getorpost('id');
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
				telephone, 
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
		$this->tmpl->assign("id", $id);

		$this->tmpl->assign("username", $row['username']);
		
		$this->tmpl->assign("email", $row['email']);
		
		$ary = explode(" ", $row['telephone']);
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
		
		$this->tmpl->assign("allow_publish_email",  ($row['allow_publish_email'] == 'Y'));
		$this->tmpl->assign("allow_publish_phone",  ($row['allow_publish_phone'] == 'Y'));

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		$id = var_from_getorpost('id');

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
		
		$this->tmpl->assign("email", var_from_getorpost('email'));
		
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
		global $DB;
		
		$id = var_from_getorpost('id');

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
			$fields[] = "telephone = ?";
			$fields_data[] = join(" ",array(
				var_from_getorpost('phone_areacode'),
				var_from_getorpost('phone_prefix'),
				var_from_getorpost('phone_number'),
				var_from_getorpost('phone_extension'))
			);
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
			$fields_data[] = var_from_getorpost('addr_postalcode');
		}
		
		if($this->_permissions['edit_birthdate']) {
			$fields[] = "birthdate = ?";
			$fields_data[] = join("-",array(
				var_from_getorpost('birth_Year'),
				var_from_getorpost('birth_Month'),
				var_from_getorpost('birth_Day')));
				
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

		if(count($fields_data) != count($fields)) {
			$this->error_text = gettext("Internal error: Incorrect number of fields set");
			return false;
		}
		
		if(count($fields) <= 0) {
			$this->error_text = gettext("You have no permission to edit");
			return false;
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(",", $fields);	
		$sql .= "WHERE user_id = ?";

		$sth = $DB->prepare($sql);
		
		$fields_data[] = $id;
		$res = $DB->execute($sth, $fields_data);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		/* TODO: Actually validate some of our data! */
		return true;
		
/*
	## TODO:
	## Check each form field for appropriateness of data. 
	## If something is wrong, set $validity to 0, and append an HTML
	## error to $error_string.

	## Names can only have letters, numbers spaces, and '-. in them.
	if (($q->param('firstname') !~ m/^[\w][\d\w-\. ']+$/) || ($q->param('lastname') !~ m/^[\w][\d\w-\. ']+$/)) {
		$validity = 0;
		$error_string .= "\n<br>You can only use letters, numbers, spaces, and the characters - ' and . in your name.";
	}

	## username
	##	- only allow [\d\w-_. ]
	if ($q->param('username') !~ m/^[\d\w][\d\w-_\. ]+$/) {
		$validity = 0;
		$error_string .= "\n<br>You can only use letters, numbers, spaces, and the characters - _ and . in your username.  Also, it must start with a letter or a number.";
	}

	## email
	##	- in format user@domain, where domain contains at least one . 
	##    character.  We may also want to check for valid toplevel domains.
	if ($q->param('primary_email') !~ m/^[\d\w-_\.]+\@([\d\w-_]+\.)+[\d\w-_]+$/) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a valid email address";
	}

	## phone
	if (!$accept_short && $q->param('primary_areacode') !~ m/^\d{3}$/) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a 3-digit area code.";
			
	}
	if (!$accept_short && ($q->param('primary_prefix') !~ m/^\d{3}$/ ||
	   $q->param('primary_number') !~ m/^\d{4}$/)) {
		$validity = 0;
		$error_string .= "\n<br>You must supply a valid phone number.";
			
	}

	## address (addr_street, addr_city, addr_prov, addr_postalcode)
	if (!defined($q->param('addr_street')) || ($q->param('addr_street') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your street address cannot be blank.";
	}
	if (!defined($q->param('addr_city')) || ($q->param('addr_city') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your city cannot be blank.";
	}
	if (!defined($q->param('addr_prov')) || ($q->param('addr_prov') =~ m/^\s*$/)) {
		$validity = 0;
		$error_string .= "\n<br>Your province cannot be blank.";
	}
	if (!$accept_short && $q->param('addr_postalcode') !~ m/^[a-zA-z]\d[a-zA-z]\d[a-zA-z]\d$/i) {
		$validity = 0;
		$error_string .= "\n<br>Postal code must be in X0X0X0 format (no spaces).";
	}

	## gender
	##	- must be either 'male' or 'female'
	if ($q->param('gender') !~ m/^[MmFf]/) {
		$validity = 0;
		$error_string .= "\n<br>Gender must be either male or female";
	}
	
	## skill
	##  - should be between 0 and 5
#	if (!$accept_short && $q->param('skill_level') !~ m/^[012345]/) {
#		$validity = 0;
#		$error_string .= "\n<br>Skill level must be between 0 and 5";
#	}

	## allow_publish
	
	## birthyear		
	##	- should be four digits
	if (!$accept_short && $q->param('birthyear') !~ m/^\d{4}$/) {
		$validity = 0;
		$error_string .= "\n<br>Birth year must be in YYYY format.";
	}
	if (!$accept_short && $q->param('birthmonth') !~ m/^\d{1,2}$/)  {
		$validity = 0;
		$error_string .= "\n<br>Birth month must be in MM format.";
	}
	if (!$accept_short && $q->param('birthday') !~ m/^\d{1,2}$/) {
		$validity = 0;
		$error_string .= "\n<br>Birth day must be in DD format.";
	}

	return($validity, $error_string);
*/
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
		
		$this->tmpl->assign("instructions", gettext("To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system."));
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
		global $DB, $id;

		/* TODO: Validate passwords here */
		$password_once = var_from_getorpost("password_once");
		$password_twice = var_from_getorpost("password_twice");
		if($password_once != $password_twice) {
			$this->error_text = gettext("First and second entries of password do not match");
			return false;
		}
		$crypt_pass = md5($password_once);
		
		$res = $DB->query("INSERT into person (username,password,class) VALUES (?,?,'new')", array(var_from_getorpost('username'), $crypt_pass));
		if($this->is_database_error($res)) {
			return false;
		}
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from person");

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
				$this->error_text = gettext("You do not have a valid session");
				return false;
			} 
		}

		$id = $session->attr_get('user_id');

		/* Also override the get/post vars for reuse of edit code
		 * TODO This is evil and should be fixed.
		 */
		set_getandpost('id',$id);
		
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
		$id = $session->attr_get('user_id');
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
		global $DB;
		
		$id = $session->attr_get('user_id');
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

function map_callback($item)
{
	return array("output" => $item, "value" => $item);
}

?>
