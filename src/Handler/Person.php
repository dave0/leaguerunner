<?php
/*
 * Code for dealing with user accounts
 */
register_page_handler('person_view', 'NEWPersonView');
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
class NEWPersonView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'email'		=> false,
			'home_phone'		=> false,
			'work_phone'		=> false,
			'mobile_phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
			'last_login'		=> false,
			'waiver_signed'		=> false,
			'member_id'		=> false,
			'dog'		=> false,
			'class'		=> false,
			'publish'			=> false,
			'user_edit'				=> false,
#			'user_delete'			=> false,
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
			$this->error_exit("You do not have a valid session");
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_exit("You must provide a user ID");
		}

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;

		/* Also, they can see if you have a dog */
		$this->_permissions['dog'] = true;
		
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
		 * See if we're a captain looking at another team captain.  
		 * Captains are always allowed to view each other for 
		 * contact purposes.
		 */
		$count = $DB->getOne("SELECT COUNT(*) FROM teamroster a, teamroster b WHERE (a.status = 'captain' OR a.status = 'assistant') AND a.player_id = ? AND (b.status = 'captain' OR b.status = 'assistant') AND b.player_id = ?", array($id, $session->attr_get('user_id')));
		if($this->is_database_error($count)) {
			return false;
		}
		if($count > 0) {
			/* is captain of at least one team, so we publish email and phone */
			$this->_permissions['email'] = true;
			$this->_permissions['home_phone'] = true;
			$this->_permissions['work_phone'] = true;
			$this->_permissions['mobile_phone'] = true;
			return true; /* since the following checks are now irrelevant */
		}

		/* If the current user is a team captain, and the requested user is on
		 * their team, they are allowed to view email/phone
		 */
		$is_on_team = $DB->getOne("SELECT COUNT(*) FROM teamroster a, teamroster b WHERE a.team_id = b.team_id AND a.player_id = ? AND a.status <> 'captain_request' AND b.player_id = ? AND (b.status = 'captain' OR b.status='assistant')",array($id, $session->attr_get('user_id')));
		if($is_on_team > 0) {
			$this->_permissions['email'] = true;
			$this->_permissions['home_phone'] = true;
			$this->_permissions['work_phone'] = true;
			$this->_permissions['mobile_phone'] = true;
			/* we must continue, since this player could be 'locked' */
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
				publish_home_phone,
				publish_work_phone,
				publish_mobile_phone
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
				$this->_permissions['home_phone'] = true;
				$this->_permissions['work_phone'] = true;
				$this->_permissions['mobile_phone'] = true;
				break;
			case 'volunteer':
				/* volunteers have their contact info published */
				$this->_permissions['email'] = true;
				$this->_permissions['home_phone'] = true;
				$this->_permissions['work_phone'] = true;
				$this->_permissions['mobile_phone'] = true;
				break;
			case 'active':
			case 'inactive':
				if($row['allow_publish_email'] == 'Y') {
					$this->_permissions['email'] = true;
				}
				if($row['publish_home_phone'] == 'Y') {
					$this->_permissions['home_phone'] = true;
				}
				if($row['publish_work_phone'] == 'Y') {
					$this->_permissions['work_phone'] = true;
				}
				if($row['publish_mobile_phone'] == 'Y') {
					$this->_permissions['mobile_phone'] = true;
				}
				break;
			default:
				/* do nothing */
				
		}

		return true;
	}

	function process ()
	{	
		global $DB, $id;
		$row = $DB->getRow(
			"SELECT * FROM person WHERE user_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		if(!isset($row)) {
			$this->error_exit("That person does not exist");
		}
	
		$fullname = $row['firstname'] . " " . $row['lastname'];
		$this->set_title("View Account &raquo; $fullname");
		print $this->get_header();
		print $this->generate_view($row);
		print $this->get_footer();
		return true;
	}
	
	function display() 
	{
		return true;  // TODO Remove me after smarty is removed
	}

	function generate_view (&$person)
	{
		$fullname = $person['firstname'] . " " . $person['lastname'];
		$output =  h1($fullname);
		
		$links = array();
		
		if($this->_permissions['user_edit']) {
			$links[] = l("edit account", "op=person_edit&id=" . $person['user_id'], array('title' => "Edit the currently-displayed account"));
		}
		
		if($this->_permissions['user_change_password']) {
			$links[] = l("change password", "op=person_changepassword&id=" . $person['user_id'], array('title' => "Change password for the currently-displayed account"));
		}
		
		if($this->_permissions['user_delete']) {
			$links[] = l("delete account", "op=person_delete&id=" . $person['user_id'], array('title' => "Delete the currently-displayed account"));
		}
		if(count($links) > 0) {
			$output .= simple_tag("blockquote", theme_links($links));
		}
		
		$output .= "<table border='0'>";
		$output .= simple_row("Name:", $fullname);

		if($this->_permissions['username']) {
			$output .= simple_row("System Username:", $person['username']);
		}
		
		if($this->_permissions['member_id']) {
			$output .= simple_row("OCUA Member ID:", $person['member_id']);
		}
		
		if($this->_permissions['email']) {
			if($person['allow_publish_email'] == 'Y') {
				$publish_value = " (published)";
			} else {
				$publish_value = " (private)";
			}
			$output .= simple_row("Email Address:", l($person['email'], "mailto:" .  $person['email']) . $publish_value);
		}
		
		foreach(array('home','work','mobile') as $type) {
			if($this->_permissions["${type}_phone"] && $person["${type}_phone"]) {
				if($person["publish_${type}_phone"] == 'Y') {
					$publish_value = " (published)";
				} else {
					$publish_value = " (private)";
				}
				$output .= simple_row("Phone ($type):", $person["${type}_phone"] . $publish_value);
			}
		}
		
		if($this->_permissions['address']) {
			$output .= simple_row("Address:", 
				format_street_address(
					$person['addr_street'],
					$person['addr_city'],
					$person['addr_prov'],
					$person['addr_postalcode']
				)
			);
		}
		
		if($this->_permissions['birthdate']) {
			$output .= simple_row("Birthdate:", $person['birthdate']);
		}
		
		if($this->_permissions['gender']) {
			$output .= simple_row("Gender:", $person['gender']);
		}
		
		if($this->_permissions['skill']) {
			$output .= simple_row("Skill Level:", $person['skill_level']);
			$output .= simple_row("Year Started:", $person['year_started']);
		}

		if($this->_permissions['class']) {
			$output .= simple_row("Account Class:", $person['class']);
		}
		
		if($this->_permissions['dog']) {
			$output .= simple_row("Has Dog:",($person['has_dog'] == 'Y') ? "yes" : "no");

			if($person['has_dog'] == 'Y') {
				$output .= simple_row("Dog Waiver Signed:",($person['dog_waiver_signed']) ? $person['dog_waiver_signed'] : "Not signed");
			}
		}
		
		if($this->_permissions['last_login']) {
			if($person['last_login']) {
				$output .= simple_row("Last Login:", 
					$person['last_login'] . ' from ' . $person['client_ip']);
			} else {
				$output .= simple_row("Last Login:", "Never logged in");
			}
		}
		
		$team_html = "<table border='0'>";
		$teams = get_teams_for_user($person['user_id']);
		$tcount = count($teams);
		for($i=0; $i < $tcount; $i++) {
			$team = $teams[$i];
			$team_html .= tr(
				td($team['position'])
				. td("on")
				. td( l($team['name'], "op=team_view&id=" . $team['id']))
			);
		}
		$team_html .= "</table>";
		
		$output .= simple_row("Teams:", $team_html);
				
		$output .= "</table>";
		
		return $output;
	}
}

/**
 * Player viewing handler
 */
class PersonView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'email'		=> false,
			'home_phone'		=> false,
			'work_phone'		=> false,
			'mobile_phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
			'last_login'		=> false,
			'waiver_signed'		=> false,
			'member_id'		=> false,
			'dog'		=> false,
			'class'		=> false,
			'publish'			=> false,
			'user_edit'				=> false,
#			'user_delete'			=> false,
			'user_change_password'	=> false,
		);

		return true;
	}

	function process ()
	{	
		$this->error_exit("This code should never be run");
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
		
		if(!isset($row)) {
			$this->error_exit("That person does not exist");
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
		
		if($this->_permissions['home_phone']) {
			$this->tmpl->assign("home_phone", $row['home_phone']);
		}
		if($this->_permissions['work_phone']) {
			$this->tmpl->assign("work_phone", $row['work_phone']);
		}
		if($this->_permissions['mobile_phone']) {
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
		
		if($this->_permissions['dog']) {

			$this->tmpl->assign("has_dog", $row['has_dog']);
			if($row['has_dog'] == 'Y' && $row['dog_waiver_signed']) {
				$this->tmpl->assign("dog_waiver_signed", $row['dog_waiver_signed']);
			} else {
				$this->tmpl->assign("dog_waiver_signed", "Not signed");
			}
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
			$this->tmpl->assign("publish_home_phone", $row['publish_home_phone']);
			$this->tmpl->assign("publish_work_phone", $row['publish_work_phone']);
			$this->tmpl->assign("publish_mobile_phone", $row['publish_mobile_phone']);
		}

		$this->tmpl->assign("has_dog", $row['has_dog']);

		/* Now, fetch teams */
		$this->tmpl->assign("teams",
			get_teams_for_user($id));

		return true;
	}
}

/**
 * Delete an account
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

	function has_permission()
	{
		return Handler::has_permission();
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
			$this->error_exit("You cannot delete the currently logged in user");
		}
		
		switch($step) {
			case 'perform':
				$this->perform();
				local_redirect("op=person_list");
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
			$this->error_exit("Account cannot be deleted while player is a team captain.");
		}
		
		/* check if user is league coordinator */
		$res = $DB->getOne("SELECT COUNT(*) from league where coordinator_id = ? OR alternate_id = ?", array($id, $id));
		if($this->is_database_error($res)) {
			return false;
		}
		if($res > 0) {
			$this->error_exit("Account cannot be deleted while player is a league coordinator.");
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
		parent::initialize();
		$this->set_title("Approve Account");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny',
		);
		return true;
	}

	function has_permission()
	{
		return Handler::has_permission();
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
				$this->perform();
				local_redirect("op=person_listnew");
				break;
			case 'confirm':
			default:
				$this->set_template_file("Person/admin_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_view();
		}
		
		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
	}

	function generate_view () 
	{
		global $DB;
		$id = var_from_getorpost('id');

		/* Check to see if there are any duplicate users */
		$duplicate_info = $DB->getAll("SELECT
			p.user_id,
			p.firstname,
			p.lastname
			FROM person p, person q 
			WHERE q.user_id = ?
				AND p.gender = q.gender
				AND p.user_id <> q.user_id
				AND (
					p.email = q.email
					OR p.home_phone = q.home_phone
					OR p.work_phone = q.work_phone
					OR p.mobile_phone = q.mobile_phone
					OR p.addr_street = q.addr_street
					OR (p.firstname = q.firstname AND p.lastname = q.lastname)
				)", array($id), DB_FETCHMODE_ASSOC);
				
		if($this->is_database_error($person_info)) {
			return false;
		}
		
		$instructions = "Confirm that you wish to approve this user.  The account will be moved to 'inactive' status.";
		if(count($duplicate_info) > 0) {
			$instructions .= "<div class='warning'><br>The following users may be duplicates of this account:<ul>\n";
			foreach($duplicate_info as $row) {
				$instructions .= "<li>{$row['firstname']} {$row['lastname']} [ <a href='{$_SERVER['PHP_SELF']}?op=person_view&id={$row['user_id']}'>view</a> ]\n";
			
			}
			$instructions .= "</ul></div>";
		}
		$this->tmpl->assign("page_instructions", $instructions);
		
		return parent::generate_view();
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

		$result = $DB->query('UPDATE member_id_sequence SET id=LAST_INSERT_ID(id+1) where year = ? AND gender = ?', 
			array($person_info['year_started'], $person_info['gender']));
		if($this->is_database_error($result)) {
			return false;
		}

		if($DB->affectedRows() == 1) {
			$member_id = $DB->getOne("SELECT LAST_INSERT_ID() from member_id_sequence");
			if($this->is_database_error($member_id)) {
				$this->error_exit("Couldn't get member ID allocation");
			}
		} else {
			/* Possible empty, so fill it */
			$lockname = "member_id_" 
				. $person_info['year_started'] 
				. "_" 
				. $person_info['gender'] 
				. "_lock";
			$lock = $DB->getOne("SELECT GET_LOCK('${lockname}',10)");
			if($this->is_database_error($lock)) {
				$this->error_exit("Couldn't get lock for member_id allocation");
			}
			if($lock == 0) {
				/* Couldn't get lock */
				$this->error_exit("Couldn't get lock for member_id allocation");
			}
			$result = $DB->query(
				"REPLACE INTO member_id_sequence values(?,?,1)", 
				array($person_info['year_started'], $person_info['gender']));
			$lock = $DB->getOne("SELECT RELEASE_LOCK('${lockname}')");
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
		$full_member_id = sprintf("%.4d%.1d%03d", 
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
			$this->error_exit("Error sending email to " . $person_info['email']);
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
			'edit_dog'	 		=> false,
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
				$this->perform();
				local_redirect("op=person_view&id=".$this->_id);
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

	function generate_form ()
	{
		global $DB;

		$row = $DB->getRow( 
			"SELECT * FROM person WHERE user_id = ?",
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

		$this->tmpl->assign("gender", $row['gender']);
		$this->tmpl->assign("gender_list",
			array_map(
				"map_callback",
				array('Male','Female'))
		);
		
		$this->tmpl->assign("skill_level", $row['skill_level']);

		$this->tmpl->assign("started_year", $row['year_started'] . "-00-00");
		
		$this->tmpl->assign("birthdate",  $row['birthdate']);
		
		$this->tmpl->assign("allow_publish_email", $row['allow_publish_email']);
		$this->tmpl->assign("publish_home_phone", $row['publish_home_phone']);
		$this->tmpl->assign("publish_work_phone", $row['publish_work_phone']);
		$this->tmpl->assign("publish_mobile_phone", $row['publish_mobile_phone']);
		$this->tmpl->assign("has_dog", $row['has_dog']);

		$this->tmpl->assign("class", $row['class']);
		$this->tmpl->assign("classes", get_enum_options('person','class'));

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
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
		$this->tmpl->assign("publish_home_phone", var_from_getorpost('publish_home_phone'));
		$this->tmpl->assign("publish_work_phone", var_from_getorpost('publish_work_phone'));
		$this->tmpl->assign("publish_mobile_phone", var_from_getorpost('publish_mobile_phone'));
		$this->tmpl->assign("has_dog", var_from_getorpost('has_dog'));

		return true;
	}

	function perform ()
	{
		global $DB;
		
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
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
			foreach(array('home_phone','work_phone','mobile_phone') as $type) {
				$num = var_from_getorpost($type);
				if(strlen($num) > 0) {
					$fields[] = "$type = ?";
					$fields_data[] = clean_telephone_number($num);
				} else {
					$fields[] = "$type = ?";
					$fields_data[] = null;
				}
			}
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
			
			$postcode = var_from_getorpost('addr_postalcode');
			if(strlen($postcode) == 6) {
				$foo = substr($postcode,0,3) . " " . substr($postcode,3);
				$postcode = $foo;
			}
			$fields[] = "addr_postalcode = ?";
			$fields_data[] = strtoupper($postcode);
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
			$fields[] = "publish_home_phone = ?";
			$fields_data[] = var_from_getorpost('publish_home_phone') ? 'Y' : 'N';
			$fields[] = "publish_work_phone = ?";
			$fields_data[] = var_from_getorpost('publish_work_phone') ? 'Y' : 'N';
			$fields[] = "publish_mobile_phone = ?";
			$fields_data[] = var_from_getorpost('publish_mobile_phone') ? 'Y' : 'N';
		}
		
		if($this->_permissions['edit_dog']) {
			$fields[] = "has_dog = ?";
			$fields_data[] = var_from_getorpost('has_dog');
		}

		if(count($fields_data) != count($fields)) {
			$this->error_exit("Internal error: Incorrect number of fields set");
		}
		
		if(count($fields) <= 0) {
			$this->error_exit("You have no permission to edit");
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(", ", $fields);	
		$sql .= "WHERE user_id = ?";
		
		$fields_data[] = $this->_id;
		
		$handle = $DB->prepare($sql);
		$res = $DB->execute($handle, $fields_data);
		
		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		global $DB;
		$errors = "";
	
		if($this->_permissions['edit_name']) {
			$firstname = var_from_getorpost('firstname');
			$lastname = var_from_getorpost('lastname');
			if( ! validate_name_input($firstname) || ! validate_name_input($lastname)) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
			}
		}

		if($this->_permissions['edit_username']) {
			$username = var_from_getorpost('username');
			if( ! validate_name_input($username) ) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
		}

		if($this->_permissions['edit_email']) {
			$email = var_from_getorpost('email');
			if ( ! validate_email_input($email) ) {
				$errors .= "\n<li>You must supply a valid email address";
			}
		}

		if($this->_permissions['edit_phone']) {
			$home_phone = var_from_getorpost('home_phone');
			$work_phone = var_from_getorpost('work_phone');
			$mobile_phone = var_from_getorpost('mobile_phone');
			if( !validate_nonblank($home_phone) &&
				!validate_nonblank($work_phone) &&
				!validate_nonblank($mobile_phone) ) {
				$errors .= "\n<li>You must supply at least one valid telephone number.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($home_phone) && !validate_telephone_input($home_phone)) {
				$errors .= "\n<li>Home telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($work_phone) && !validate_telephone_input($work_phone)) {
				$errors .= "\n<li>Work telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
			if(validate_nonblank($mobile_phone) && !validate_telephone_input($mobile_phone)) {
				$errors .= "\n<li>Mobile telephone number is not valid.  Please supply area code, number and (if any) extension.";
			}
		}

		if($this->_permissions['edit_address']) {
			$addr_street = var_from_getorpost('addr_street');
			if( !validate_nonhtml($addr_street) ) {
				$errors .= "\n<li>You must supply a street address.";
			}
			$addr_city = var_from_getorpost('addr_city');
			if( !validate_nonhtml($addr_city) ) {
				$errors .= "\n<li>You must supply a city.";
			}
			$addr_prov = var_from_getorpost('addr_prov');
			if( !validate_nonhtml($addr_prov) ) {
				$errors .= "\n<li>You must supply a province.";
			}
			$addr_postalcode = var_from_getorpost('addr_postalcode');
			if( !validate_postalcode($addr_postalcode) ) {
				$errors .= "\n<li>You must supply a valid Canadian postal code.";
			}
		}
		
		if($this->_permissions['edit_gender']) {
			$gender = var_from_getorpost('gender');
			if( !preg_match("/^[mf]/i",$gender ) ) {
				$errors .= "\n<li>You must select either male or female for gender.";
			}
		}
		
		if($this->_permissions['edit_birthdate']) {
			$birthyear = var_from_getorpost('birth_Year');
			$birthmonth = var_from_getorpost('birth_Month');
			$birthday = var_from_getorpost('birth_Day');
			if( !validate_date_input($birthyear, $birthmonth, $birthday) ) {
				$errors .= "\n<li>You must provide a valid birthdate";
			}
		}
		
		if($this->_permissions['edit_skill']) {
			$skill = var_from_getorpost('skill_level');
			if( $skill < 1 || $skill > 10 ) {
				$errors .= "\n<li>You must select a skill level between 1 and 5";
			}
			
			$year_started = var_from_getorpost('started_Year');
			$current = localtime(time(),1);
			$this_year = $current['tm_year'] + 1900;
			if( $year_started > $this_year ) {
				$errors .= "\n<li>Year started must be before current year.";
			}

			if( $year_started < 1986 ) {
				$errors .= "\n<li>Year started must be after 1986.  For the number of people who started playing before then, I don't think it matters if you're listed as having played 17 years or 20, you're still old. :)";
			}
			if( $year_started < $birthyear + 8) {
				$errors .= "\n<li>You can't have started playing when you were " . ($year_started - $birthyear) . " years old!  Please correct your birthdate, or your starting year";
			}
		}
	
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
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
			'edit_dog' 			=> true,
			'edit_publish'		=> true,
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
			$this->error_exit("First and second entries of password do not match");
		}
		$crypt_pass = md5($password_once);
		
		$res = $DB->query("INSERT into person (username,password,class) VALUES (?,?,'new')", array(var_from_getorpost('username'), $crypt_pass));
		$err = isDatabaseError($res);
		if($err != false) {
			if(strstr($err,"already exists: INSERT into person (username,password,class) VALUES")) {
				$err = "A user with that username already exists; please go back and try again";
			}
			$this->error_exit($err);
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
				$this->error_exit("You do not have a valid session");
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
		$this->_permissions['edit_dog'] 		= true;
		$this->_permissions['edit_publish']		= true;

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
		global $DB;
		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm': 
				$this->set_template_file("Person/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'update');
				$rc = $this->generate_confirm();
				break;
			case 'update':  /* Make any updates specified by the user */
				$dog = var_from_getorpost("has_dog");
				if($dog == "Y") {
					$this->set_template_file("Person/dog_waiver_form.tmpl");
					$this->tmpl->assign("page_step", 'dog_waiver');
				} else {
					$this->set_template_file("Person/waiver_form.tmpl");
					$this->tmpl->assign("page_step", 'survey');
				}
				$rc = $this->perform();
				break;
			case 'dog_waiver':
				$this->set_template_file("Person/waiver_form.tmpl");
				$this->tmpl->assign("page_step", 'survey');
				$rc = $this->process_dog_waiver();
				break;
			case 'survey':  /* Waiver was clicked */
				$rc = $this->process_waiver();
				$this->set_template_file("Person/survey_form.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				break;
			case 'perform':
				$this->process_survey();
				local_redirect("op=menu");
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
			$this->set_title("Informed Consent Form For League Play");
			$this->error_exit("Sorry, your account may only be activated by agreeing to the waiver.");
		}

		/* otherwise, it's yes.  Set the user to 'active' and marked the
		 * signed_waiver field to the current date */
		$res = $DB->query("UPDATE person SET class = 'active', waiver_signed=NOW() where user_id = ?", array($id));

		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
		
	}

	function process_dog_waiver()
	{
		global $DB, $session;
		
		$id = $session->attr_get('user_id');
		$signed = var_from_getorpost('signed');
		
		if('yes' != $signed) {
			$this->set_title("Informed Consent Form For Dog Owners");
			$this->error_exit("Sorry, if you wish to bring a dog to the fields, you must sign this waiver.");
		}

		/* otherwise, it's yes.  Set the user to 'active' and marked the
		 * signed_waiver field to the current date */
		$res = $DB->query("UPDATE person SET dog_waiver_signed=NOW() where user_id = ?", array($id));

		if($this->is_database_error($res)) {
			return false;
		}
		
		return true;
		
	}
	
	function process_survey()
	{
		global $DB, $session;
		$dem = var_from_getorpost('demographics');
		$items = array( 'income','num_children','education','field','language','other_sports');

		$fields = array();
		$fields_data = array();

		foreach($items as $item) {
			if( ! array_key_exists($item, $dem) ) {
				continue;
			}
			if($dem[$item] == '---') {
				continue;
			}
			
			$fields[] = $item;

			// Cheat for array-type items
			if(is_array($dem[$item])) {
				$fields_data[] = join(",",$dem[$item]);
			} else {
				$fields_data[] = $dem[$item];
			}
		}

		if(count($fields) <= 0) {
			return true;
		}
		
		
		$sql = "INSERT INTO demographics (";
		$sql .= join(",", $fields);	
		$sql .= ") VALUES(";
		for($i=0; $i< (count($fields) - 1); $i++) {
			$sql .= "?,";
		}
		$sql .= "?)";
		
		$res = $DB->query($sql, $fields_data);
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
			'volunteer_sufficient',
			'deny',
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
		
		$letter = var_from_getorpost("letter");
		
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=person_view'
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'op=person_delete'
			);
		}

        $query = $DB->prepare("SELECT 
			CONCAT(lastname,', ',firstname) AS value, user_id AS id 
			FROM person WHERE lastname LIKE ? ORDER BY lastname");
		
		$output =  $this->generateAlphaList($query, $ops, 'lastname', 'person', 'person_list', $letter);
		
		print $this->get_header();
		print h1($this->title);
		print $output;
		print $this->get_footer();
		
		return true;
	}

	function display () 
	{
		return true;  // TODO Remove me after smarty is removed
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

		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=person_view'
			),
			array(
				'name' => 'approve',
				'target' => 'op=person_approvenew'
			),
			array(
				'name' => 'delete',
				'target' => 'op=person_delete'
			),
		);

        $query = $DB->prepare("SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id 
			 FROM person 
			 WHERE
			 	class = 'new'
			 AND
			 	lastname LIKE ? 
			 ORDER BY lastname");
		
		$output =  $this->generateAlphaList($query, $ops, 'lastname', "person WHERE class = 'new'", 'person_list', $letter);
		
		print $this->get_header();
		print h1($this->title);
		print $output;
		print $this->get_footer();
		
		return true;
	}

	function display () 
	{
		return true;  // TODO Remove me after smarty is removed
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
		$id = var_from_getorpost('id');
		switch($step) {
			case 'perform':
				$this->perform();	
				local_redirect("op=person_view&id=$id");
				break;
			default:
				$this->set_template_file("Person/change_password.tmpl");
				$rc = $this->generate_form();
		}

		$this->tmpl->assign("page_op", var_from_getorpost('op'));

		return $rc;
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
			$this->error_exit("That user does not exist");
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
			$this->error_exit("You must enter the same password twice.");
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
			$this->error_exit("You must supply at least one of username, member ID, or email address");
		}

		/* Now, try and find the user */
		$sql = "SELECT user_id,firstname,lastname,username,email FROM person WHERE ";
		$sql .= join(" AND ",$fields);

		$users = $DB->getAll($sql, $fields_data, DB_FETCHMODE_ASSOC);
		if($this->is_database_error($users)) {
			return false;
		}
		
		if(count($users) > 1) {
			$this->error_exit("You did not supply enough identifying information.  Try filling in more data.");
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
			$this->error_exit("System was unable to send email to that user.  Please contact system administrator.");
		}
		
		return true;
	}
}
?>
