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
register_page_handler('person_survey', 'PersonSurvey');
register_page_handler('person_signwaiver', 'PersonSignWaiver');
register_page_handler('person_signdogwaiver', 'PersonSignDogWaiver');
register_page_handler('person_list', 'PersonList');
register_page_handler('person', 'PersonList');
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
		$this->title = 'View';
		$this->_permissions = array(
			'email'		=> false,
			'home_phone'		=> false,
			'work_phone'		=> false,
			'mobile_phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'height'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
			'last_login'		=> false,
			'waiver_signed'		=> false,
			'member_id'		=> false,
			'dog'		=> false,
			'class'		=> false,
			'status'		=> false,
			'publish'			=> false,
			'user_edit'				=> false,
#			'user_delete'			=> false,
			'user_change_password'	=> false,
		);
		$this->op = 'person_view';
		$this->section = 'person';
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
		global $session, $id;

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
		$result = db_query("SELECT COUNT(*) FROM teamroster a, teamroster b WHERE (a.status = 'captain' OR a.status = 'assistant') AND a.player_id = %d AND (b.status = 'captain' OR b.status = 'assistant') AND b.player_id = %d",$id, $session->attr_get('user_id'));

		$count = db_result($result);
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
		$result = db_query("SELECT COUNT(*) FROM teamroster a, teamroster b WHERE a.team_id = b.team_id AND a.player_id = %d AND a.status <> 'captain_request' AND b.player_id = %d AND (b.status = 'captain' OR b.status='assistant')",$id, $session->attr_get('user_id'));
		$is_on_team = db_result($result);
		if($is_on_team > 0) {
			$this->_permissions['email'] = true;
			$this->_permissions['home_phone'] = true;
			$this->_permissions['work_phone'] = true;
			$this->_permissions['mobile_phone'] = true;
			/* we must continue, since this player could be 'locked' */
		}

		/*
		 * See what the player's status is.  Some cannot be viewed unless you
		 * are 'administrator'.  
		 */
		$result = db_query(
			"SELECT 
				status, 
				allow_publish_email, 
				publish_home_phone,
				publish_work_phone,
				publish_mobile_phone
			FROM person WHERE user_id = %d", $id);

		$row = db_fetch_array($result);
		
		switch($row['status']) {
			case 'new':
			case 'locked':
				/* players of status 'new' and 'locked' can only be viewed by
				 * 'administrator' class, and this case is handled above.
				 */
				return false;
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
		global $id;
		
		$result = db_query(
			"SELECT p.*, w.name as ward_name, w.num as ward_number, w.city as ward_city FROM person p LEFT JOIN ward w ON (p.ward_id = w.ward_id) WHERE user_id = %d", $id);

		$person = db_fetch_array($result);
		
		if(!isset($person)) {
			$this->error_exit("That person does not exist");
		}
		
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

		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "op=person_view&id=$id",
			$this->title => 0));
			
		$links_html =  "";
		if(count($links) > 0) {
			$links_html .= theme_links($links);
		}

		return $links_html . $this->generateView($person);
	}
	
	function generateView (&$person)
	{
		$fullname = $person['firstname'] . " " . $person['lastname'];
		
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
			if($person['ward_number']) {
				$output .= simple_row('Ward:', 
					l($person['ward_name'] . ' (' . $person['ward_city']. ' Ward ' . $person['ward_number']. ')','op=ward_view&id='.$person['ward_id']));
			}
		}
		
		if($this->_permissions['birthdate']) {
			$output .= simple_row('Birthdate:', $person['birthdate']);
		}
		
		if($this->_permissions['height']) {
			$output .= simple_row('Height:', $person['height'] ? $person['height'] : 0 . ' inches');
		}
		
		if($this->_permissions['gender']) {
			$output .= simple_row("Gender:", $person['gender']);
		}
		
		if($this->_permissions['skill']) {
			$skillAry = getOptionsForSkill();
			$output .= simple_row("Skill Level:", $skillAry[$person['skill_level']]);
			$output .= simple_row("Year Started:", $person['year_started']);
		}

		if($this->_permissions['class']) {
			$output .= simple_row("Account Class:", $person['class']);
		}
		
		$output .= simple_row("Account Status:", $person['status']);
		
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
 * Delete an account
 */
class PersonDelete extends PersonView
{
	function initialize ()
	{
		$this->title = 'Delete';
		$this->_permissions = array(
			'email'		=> false,
			'phone'		=> false,
			'username'	=> false,
			'birthdate'	=> false,
			'height'	=> false,
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
		$this->op = 'person_delete';
		$this->section = 'person';
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
		$id = var_from_getorpost('id');

		/* Safety check: Don't allow us to delete ourselves */
		if($session->attr_get('user_id') == $id) {
			$this->error_exit("You cannot delete the currently logged in user");
		}

		if($step == 'perform') {
			$this->perform();
			local_redirect("op=person_list");
			exit; // redundant, local_redirect will exit.
		}

		/* Otherwise... */
		$result = db_query(
			"SELECT p.*, w.name as ward_name, w.num as ward_number, w.city as ward_city FROM person p LEFT JOIN ward w ON (p.ward_id = w.ward_id) WHERE user_id = %d", $id);
		$person = db_fetch_array($result);

		if(!isset($person)) {
			$this->error_exit("That person does not exist");
		}
		
		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "op=person_view&id=$id",
			$this->title => 0));
		
		return 
			para("Confirm that you wish to delete this user from the system.")
			. $this->generateView($person)
			. form( 
				form_hidden('op', $this->op)
				. form_hidden('step', 'perform')
				. form_hidden('id', $id)
				. form_submit("Delete")
			);
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
		$id = var_from_getorpost('id');

		/* check if user is team captain       */
		$result = db_query("SELECT COUNT(*) from teamroster where status = 'captain' AND player_id = %d", $id);

		$numTeams = db_result($result);
		
		if($numTeams > 0) {
			$this->error_exit("Account cannot be deleted while player is a team captain.");
		}
		
		/* check if user is league coordinator */
		$result = db_query("SELECT COUNT(*) from league where coordinator_id = %d OR alternate_id = %d", $id, $id);
		$numLeagues = db_result($result);	
		if($numLeagues > 0) {
			$this->error_exit("Account cannot be deleted while player is a league coordinator.");
		}
		
		/* remove user from team rosters  */
		db_query("DELETE from teamroster WHERE player_id = %d",$id);
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		/* remove user account */
		db_query("DELETE from person WHERE user_id = %d", $id);
		if( 1 != db_affected_rows() ) {
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
		$this->title = 'Approve Account';
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'deny',
		);
		$this->op = 'person_approvenew';
		$this->section = 'person';
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
		$id = var_from_getorpost('id');

		if($step == 'perform') {
			/* Actually do the approval on the 'perform' step */
			$this->perform();
			local_redirect("op=person_listnew");
			exit; // redundant, local_redirect will exit.
		} 

		/* Otherwise... */
		$result = db_query(
			"SELECT p.*, w.name as ward_name, w.num as ward_number, w.city as ward_city FROM person p LEFT JOIN ward w ON (p.ward_id = w.ward_id) WHERE user_id = %d",  $id);
			
		$person = db_fetch_array($result);

		if(!isset($person)) {
			$this->error_exit("That person does not exist");
		}
		
		if($person['status'] != 'new') {
			$this->error_exit("That account has already been approved");
		}
		
		$text = "Confirm that you wish to approve this user.  The account will be moved to 'inactive' status.";
		
		/* Check to see if there are any duplicate users */
		$result = db_query("SELECT
			p.user_id,
			p.firstname,
			p.lastname
			FROM person p, person q 
			WHERE q.user_id = %d
				AND p.gender = q.gender
				AND p.user_id <> q.user_id
				AND (
					p.email = q.email
					OR p.home_phone = q.home_phone
					OR p.work_phone = q.work_phone
					OR p.mobile_phone = q.mobile_phone
					OR p.addr_street = q.addr_street
					OR (p.firstname = q.firstname AND p.lastname = q.lastname)
				)", $id);
				
		if(db_num_rows($result) > 0) {
			$text .= "<div class='warning'><br>The following users may be duplicates of this account:<ul>\n";
			while($user = db_fetch_object($result)) {
				$text .= "<li>$user->firstname $user->lastname";
				$text .= "[&nbsp;" . l("view", "op=person_view&id=$user->user_id") . "&nbsp;]";
			}
			$text .= "</ul></div>";
		}

		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "op=person_view&id=$id",
			$this->title => 0));
		
		return para($instructions)
			. $this->generateView($person)
			. form( 
			form_hidden('op', $this->op)
			. form_hidden('step', 'perform')
			. form_hidden('id', $id)
			. form_submit("Approve")
		);
	}

	function perform ()
	{

		$id = var_from_getorpost('id');

		$result = db_query("SELECT * FROM person where user_id = %d", $id);
		$person = db_fetch_object($result);

		$result = db_query("UPDATE member_id_sequence SET id=LAST_INSERT_ID(id+1) where year = %d AND gender = '%s'", 
			$person->year_started, $person->gender);
		$rows = db_affected_rows();
		if($rows == 1) {
		
			$result = db_query("SELECT LAST_INSERT_ID() from member_id_sequence");
			$member_id = db_result($result);
			if( !isset($member_id)) {
				$this->error_exit("Couldn't get member ID allocation");
			}
		} else if($rows == 0) {
			/* Possible empty, so fill it */
			$lockname = "member_id_" 
				. $person->year_started
				. "_" 
				. $person->gender 
				. "_lock";
			$result = db_query("SELECT GET_LOCK('$lockname',10)");
			$lock = db_result($result);
			
			if(!isset($lock) || $lock == 0) {
				/* Couldn't get lock */
				$this->error_exit("Couldn't get lock for member_id allocation");
			}
			db_query( "REPLACE INTO member_id_sequence values(%d,'%s',1)", 
				$person->year_started, $person->gender);

			db_query("SELECT RELEASE_LOCK('${lockname}')");
			
			$member_id = 1;
		} else {
			/* Something bad happened */
			return false;
		}

		/* Now, that's really not the full member ID.  We need to build that
		 * from other info too.
		 */
		$full_member_id = sprintf("%.4d%.1d%03d", 
			$person->year_started,
			($person->gender == "Male") ? 0 : 1,
			$member_id);
	
		db_query("UPDATE person SET status = 'inactive', member_id = %d  where user_id = %d", $full_member_id, $id);
	
		if( 1 != db_affected_rows() ) {
			return false;
		}

		/* Ok, it's done.  Now send a mail to the user and tell them. */
		$message = <<<EOM
Dear $person->firstname $person->lastname,

Your {$GLOBALS['APP_NAME']} account has been approved. Your new permanent
member number is
	$full_member_id
This number will be used in the future to identify you for member services
discounts, etc, so please do not lose it.
You may now log in to the system at
	http://{$_SERVER['SERVER_NAME']}{$_SERVER["PHP_SELF"]}
with the username
	$person->username
and the password you specified when you created your account.  You will be
asked to confirm your account information and sign a waiver form before
your account will be activated.
Thanks,
{$GLOBALS['APP_ADMIN_NAME']}
EOM;

		$rc = mail($person->email, $GLOBALS['APP_NAME'] . " Account Activation", $message, "From: " . $GLOBALS['APP_ADMIN_EMAIL'] . "\r\n");
		if($rc == false) {
			$this->error_exit("Error sending email to " . $person->email);
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
		$this->title = 'Edit';
		$this->_permissions = array(
			'edit_username'		=> false,
			'edit_class' 		=> false,
			'edit_status' 		=> false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'self_sufficient',
			'deny',
		);

		$this->op = "person_edit";
		$this->section = 'person';
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

		$id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( $id );
				local_redirect("op=person_view&id=$id");
				break;
			default:
				$formData = $this->getFormData($id);
				$rc = $this->generateForm($id, $formData, "Edit any of the following fields and click 'Submit' when done.");
		}
		
		return $rc;
	}

	function getFormData( $id ) 
	{
		return db_fetch_array(db_query("SELECT * FROM person WHERE user_id = %d", $id));
	}

	function generateForm ( $id, &$formData, $instructions = "")
	{
		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);

		$output .= para($instructions);
		$output .= para(
			"Note that email and phone publish settings below only apply to regular players.  "
			. "Captains will always have access to view the phone numbers and email addresses of their confirmed players.  "
			. "All Team Captains will also have their email address viewable by other players"
		);
		$output .= para(
			"If you have concerns about the data OCUA collects, please see our "
			. "<b><font color=red><a href='http://www.ocua.ca/ocua/policy/privacy_policy.html' target='_new'>Privacy Policy</a></font></b>"
		);
		
		$output .= "<table border='0' cellpadding='3' cellspacing='0'>";
		$output .= simple_row("First Name:",
			form_textfield('', 'firstname', $formData['firstname'], 25,100, "First (and, if desired, middle) name."));
		$output .= simple_row("Last Name:",
			form_textfield('', 'lastname', $formData['lastname'], 25,100, "Last name"));

		if($this->_permissions['edit_username']) {
			$output .= simple_row("System Username:",
				form_textfield('', 'username', $formData['username'], 25,100, "Desired login name."));
		} else {
			$output .= simple_row("System Username:", $formData['username']);
		}

		if($this->_permissions['edit_password']) {
			$output .= simple_row("Password:",
				form_password('', 'password_once', '', 25,100, "Enter your desired password."));
			$output .= simple_row("Re-Enter Password:",
				form_password('', 'password_twice', '', 25,100, "Enter your desired password a second time to confirm it."));
		}

		$output .= simple_row("Email Address:",
			form_textfield('', 'email', $formData['email'], 25, 100, "Enter your preferred email address.  This will be used by OCUA to correspond with you on league matters")
			. form_checkbox("Allow other players to view my email address",'allow_publish_email','Y',($formData['allow_publish_email'] == 'Y')));

		$addrBlock = "<table border='0' cellspacing='3'>";
		$addrBlock .= simple_row("Street Address:",
			form_textfield('','addr_street',$formData['addr_street'], 25, 100, "Number, street name, and apartment number if necessary"));
		$addrBlock .= simple_row("City:",
			form_textfield('','addr_city',$formData['addr_city'], 25, 100, "Name of city.  If you are a resident of the amalgamated Ottawa, please enter 'Ottawa' (instead of Kanata, Nepean, etc.)"));
			
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$addrBlock .= simple_row("Province:",
			form_select('', 'addr_prov', $formData['addr_prov'], getProvinceNames(), "Select a province from the list"));

		$addrBlock .= simple_row("Postal Code:",
			form_textfield('', 'addr_postalcode', $formData['addr_postalcode'], 8, 7, "Please enter a correct postal code matching the address above.  OCUA uses this information to help locate new fields near its members."));

		$addrBlock .= "</table>";

		$output .= simple_row("Address:", $addrBlock);

		$output .= simple_row("Telephone:", 
			"<table border='0'>"
			. simple_row("Home:", form_textfield('', 'home_phone', $formData['home_phone'], 25, 100, form_checkbox("Allow other players to view this number",'publish_home_phone','Y',($formData['publish_home_phone'] == 'Y'))))
			. simple_row("Work:", form_textfield('', 'work_phone', $formData['work_phone'], 25, 100, form_checkbox("Allow other players to view this number",'publish_work_phone','Y',($formData['publish_work_phone'] == 'Y'))))
			. simple_row("Mobile:", form_textfield('', 'mobile_phone', $formData['mobile_phone'], 25, 100, form_checkbox("Allow other players to view this number",'publish_mobile_phone','Y',($formData['publish_mobile_phone'] == 'Y'))))
			. "</table>"
		);

		$output .= simple_row("Gender:",
			form_select('', 'gender', $formData['gender'], getOptionsFromEnum( 'person', 'gender')));
			
		$output .= simple_row("Skill Level:",
			form_radiogroup('', 'skill_level', $formData['skill_level'],
				getOptionsForSkill()));

		$thisYear = strftime("%Y", time());

		$output .= simple_row("Year Started:",
			form_select('', 'year_started', $formData['year_started'], 
				getOptionsFromRange(1986, $thisYear, 'reverse'), "The year you started playing Ultimate in Ottawa."));

		$output .= simple_row("Birthdate:",
			form_select_date('', 'birth', $formData['birthdate'], ($thisYear - 60), ($thisYear - 10), "Please enter a correct birthdate; having accurate information is important for insurance purposes"));

		$output .= simple_row('Height:',
			form_textfield('','height',$formData['height'], 4, 4, 'Please enter your height in inches.  This is used to help generate even teams in hat leagues and winter indoor.'));
			
		if($this->_permissions['edit_class']) {
			$output .= simple_row("Account Class:",
				form_select('','class', $formData['class'], getOptionsFromEnum('person','class')));
		}
		
		if($this->_permissions['edit_status']) {
			$output .= simple_row("Account Status:",
				form_select('','status', $formData['status'], getOptionsFromEnum('person','status')));
		}

		$output .= simple_row("Has dog:",
			form_radiogroup('', 'has_dog', $formData['has_dog'], array(
				'Y' => 'Yes, I have a dog I will be bringing to games',
				'N' => 'No, I will not be bringing a dog to games')));
		
		$output .= "</table>";

		$this->setLocation(array(
			$formData['firstname'] . " " . $formData['lastname'] => "op=person_view&id=$id",
			$this->title => 0));

		$output .= para(form_submit('submit') . form_reset('reset'));

		
		return form($output);
	}

	function generateConfirm ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);

		$output .= "<table border='0'>";

		$firstname = var_from_post('firstname');
		$output .= simple_row("First Name:",
			form_hidden('firstname',$firstname) . $firstname);
			
		$lastname = var_from_post('lastname');
		$output .= simple_row("Last Name:",
			form_hidden('lastname',$lastname) . $lastname);
		
		if($this->_permissions['edit_username']) {
			$username = var_from_post('username');
			$output .= simple_row("System Username:",
				form_hidden('username',$username) . $username);
		}
		
		if($this->_permissions['edit_password']) {
			$password_once = var_from_post('username');
			$output .= simple_row("Password:",
				form_hidden('password_once', var_from_post('password_once'))
				. form_hidden('password_twice', var_from_post('password_twice'))
				. '<i>(entered)</i>');
		}
		
		$email = var_from_post('email');
		$output .= simple_row("Email Address:",
			form_hidden('email',$email) . $email);
			
		$allow_publish_email = var_from_post('allow_publish_email');
		$output .= simple_row("Show Email:",
			form_hidden('allow_publish_email',$allow_publish_email) . $allow_publish_email);

		$addr_street = var_from_post('addr_street');
		$addr_city = var_from_post('addr_city');
		$addr_prov = var_from_post('addr_prov');
		$addr_postalcode = var_from_post('addr_postalcode');
		$output .= simple_row("Address:",
			form_hidden('addr_street',$addr_street)
			. form_hidden('addr_city',$addr_city)
			. form_hidden('addr_prov',$addr_prov)
			. form_hidden('addr_postalcode',$addr_postalcode)
			. "$addr_street<br>$addr_city, $addr_prov<br> $addr_postalcode");

		$phone['home'] = var_from_post('home_phone');
		$phone['work'] = var_from_post('work_phone');
		$phone['mobile'] = var_from_post('mobile_phone');
		$phone['publish_home'] =  var_from_post('publish_home_phone');
		$phone['publish_work'] =  var_from_post('publish_work_phone');
		$phone['publish_mobile'] =  var_from_post('publish_mobile_phone');

		foreach( array('home','work','mobile') as $location) {
			if($phone[$location]) {
				$phoneBlock .= form_hidden($location . '_phone', $phone[$location]);
				$phoneBlock .= ucfirst($location) . ": " . $phone[$location];
				if($phone["publish_$location"] == 'Y') {
					$phoneBlock .= " (published)";
					$phoneBlock .= form_hidden('publish_' . $location . '_phone', 'Y');
				} else {
					$phoneBlock .= " (private)";
				}
			}
		}
		$output .= simple_row("Telephone:", $phoneBlock);

		$gender = var_from_post('gender');
		$output .= simple_row("Gender:", form_hidden('gender',$gender) . $gender);
		
		$skill_level = var_from_post('skill_level');
		$levels = getOptionsForSkill();
		$output .= simple_row("Skill Level:", form_hidden('skill_level',$skill_level) . $levels[$skill_level]);
		
		$year_started = var_from_post('year_started');
		$output .= simple_row("Year Started:", form_hidden('year_started',$year_started) . $year_started);

		$birth_year = var_from_post('birth_year');
		$birth_month = var_from_post('birth_month');
		$birth_day = var_from_post('birth_day');
		$output .= simple_row("Birthdate:", 
			form_hidden('birth_year',$birth_year) 
			. form_hidden('birth_month',$birth_month) 
			. form_hidden('birth_day',$birth_day) 
			. "$birth_year / $birth_month / $birth_day");
		
		$height = var_from_post('height');
		if($height) {
			$output .= simple_row("Height:", form_hidden('height',$height) . $height . " inches");
		}
	
		if($this->_permissions['edit_class']) {
			$class = var_from_post('class');
			$output .= simple_row("Account Class:", form_hidden('class',$class) . $class);
		}
		
		if($this->_permissions['edit_status']) {
			$status = var_from_post('status');
			$output .= simple_row("Account Status:", form_hidden('status',$status) . $status);
		}
		
		$has_dog = var_from_post('has_dog');
		$output .= simple_row("Has dog:", form_hidden('has_dog',$has_dog) . $has_dog);
			
		$output .= "</table>";

		$output .= para(form_submit('submit') . form_reset('reset'));

		$this->setLocation(array(
			"$firstname $lastname" => "op=person_view&id=$id",
			$this->title => 0));

		return form($output);
	}

	function perform ( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_username']) {
			$fields[] = "username = '%s'";
			$fields_data[] = var_from_getorpost('username');
		}
		
		if($this->_permissions['edit_class']) {
			$fields[] = "class = '%s'";
			$fields_data[] = var_from_getorpost('class');
		}
		if($this->_permissions['edit_status']) {
			$fields[] = "status = '%s'";
			$fields_data[] = var_from_getorpost('status');
		}
		
		$fields[] = "email = '%s'";
		$fields_data[] = var_from_getorpost('email');
		
		foreach(array('home_phone','work_phone','mobile_phone') as $type) {
			$num = var_from_getorpost($type);
			if(strlen($num) > 0) {
				$fields[] = "$type = '%s'";
				$fields_data[] = clean_telephone_number($num);
			} else {
				$fields[] = "$type = '%s'";
				$fields_data[] = null;
			}
		}
		
		$fields[] = "firstname = '%s'";
		$fields_data[] = var_from_getorpost('firstname');
		
		$fields[] = "lastname = '%s'";
		$fields_data[] = var_from_getorpost('lastname');
		
		$fields[] = "addr_street = '%s'";
		$fields_data[] = var_from_getorpost('addr_street');
		
		$fields[] = "addr_city = '%s'";
		$fields_data[] = var_from_getorpost('addr_city');
		
		$fields[] = "addr_prov = '%s'";
		$fields_data[] = var_from_getorpost('addr_prov');
		
		$postcode = var_from_getorpost('addr_postalcode');
		if(strlen($postcode) == 6) {
			$foo = substr($postcode,0,3) . " " . substr($postcode,3);
			$postcode = $foo;
		}
		$fields[] = "addr_postalcode = '%s'";
		$fields_data[] = strtoupper($postcode);
		
		$fields[] = "birthdate = '%s'";
		$fields_data[] = join("-",array(
			var_from_getorpost('birth_year'),
			var_from_getorpost('birth_month'),
			var_from_getorpost('birth_day')));
		
		$height = var_from_getorpost('height');
		if($height) {
			$fields[] = "height = %d";
			$fields_data[] = $height;
		}
		
		$fields[] = "gender = '%s'";
		$fields_data[] = var_from_getorpost('gender');
		
		$fields[] = "skill_level = '%s'";
		$fields_data[] = var_from_getorpost('skill_level');
		$fields[] = "year_started = '%s'";
		$fields_data[] = var_from_getorpost('year_started');

		$fields[] = "allow_publish_email = '%s'";
		$fields_data[] = var_from_getorpost('allow_publish_email');
		$fields[] = "publish_home_phone = '%s'";
		$fields_data[] = var_from_getorpost('publish_home_phone') ? 'Y' : 'N';
		$fields[] = "publish_work_phone = '%s'";
		$fields_data[] = var_from_getorpost('publish_work_phone') ? 'Y' : 'N';
		$fields[] = "publish_mobile_phone = '%s'";
		$fields_data[] = var_from_getorpost('publish_mobile_phone') ? 'Y' : 'N';
		
		$fields[] = "has_dog = '%s'";
		$fields_data[] = var_from_getorpost('has_dog');

		if(count($fields_data) != count($fields)) {
			$this->error_exit("Internal error: Incorrect number of fields set");
		}
		
		if(count($fields) <= 0) {
			$this->error_exit("You have no permission to edit");
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(", ", $fields);	
		$sql .= "WHERE user_id = %d";
		
		$fields_data[] = $id;

		db_query( $sql, $fields_data);
		
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		$errors = "";
	
		$firstname = var_from_getorpost('firstname');
		$lastname = var_from_getorpost('lastname');
		if( ! validate_name_input($firstname) || ! validate_name_input($lastname)) {
			$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
		}

		if($this->_permissions['edit_username']) {
			$username = var_from_getorpost('username');
			if( ! validate_name_input($username) ) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
		}

		$email = var_from_getorpost('email');
		if ( ! validate_email_input($email) ) {
			$errors .= "\n<li>You must supply a valid email address";
		}

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
		
		$gender = var_from_getorpost('gender');
		if( !preg_match("/^[mf]/i",$gender ) ) {
			$errors .= "\n<li>You must select either male or female for gender.";
		}
		
		$birthyear = var_from_getorpost('birth_year');
		$birthmonth = var_from_getorpost('birth_month');
		$birthday = var_from_getorpost('birth_day');
		if( !validate_date_input($birthyear, $birthmonth, $birthday) ) {
			$errors .= "\n<li>You must provide a valid birthdate";
		}

		$height = var_from_getorpost('height');
		if( validate_nonblank($height) ) {
			if( ($height < 36) || ($height > 84) ) {
				$errors .= "\n<li>Please enter a reasonable and valid value for your height.";
			}
		}
		
		$skill = var_from_getorpost('skill_level');
		if( $skill < 1 || $skill > 10 ) {
			$errors .= "\n<li>You must select a skill level between 1 and 5";
		}
		
		$year_started = var_from_getorpost('year_started');
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
		$this->title = 'Create Account';
		$this->_permissions = array(
			'edit_username'		=> true,
			'edit_password'		=> true,
		);

		$this->_required_perms = array( 'allow' );

		$this->op = 'person_create';
		$this->section = 'person';
		return true;
	}

	function checkPrereqs( $next )
	{
		return false;
	}
	
	function process ()
	{
		$step = var_from_getorpost('step');

		$id = -1;
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				return $this->perform( &$id );
				break;
			default:
				$formData = $this->getFormData($id);
				$rc = $this->generateForm( $id, $formData, "To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system.");
		}
		$this->setLocation(array( $this->title => 0));
		return $rc;
	}

	function getFormData ($id)
	{
		return array();
	}

	function perform ( $id )
	{
		$password_once = var_from_getorpost("password_once");
		$password_twice = var_from_getorpost("password_twice");
		if($password_once != $password_twice) {
			$this->error_exit("First and second entries of password do not match");
		}
		$crypt_pass = md5($password_once);

		$username = var_from_getorpost('username');

		if(db_num_rows(db_query("SELECT username FROM person WHERE username = '%s'",$username))) {
			$err = "A user with that username already exists; please go back and try again";
			$this->error_exit($err);
		}
	
		db_query("INSERT into person (username,password,status) VALUES '%s','%s','new')", $username, $crypt_pass);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$id = db_result(db_query("SELECT LAST_INSERT_ID() from person"));

		$rc = parent::perform( $id );

		if( $rc === false ) {
			return false;
		} else {
			return para(
				"Thank you for creating an account.  It is now being held for one of the administrators to approve.  "
				. "Once your account is approved, you will receive an email informing you.  "
				. "You will then be able to log in with your username and password."
			);
		}
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
		$this->title = "Activate Account";

		$this->op = 'person_activate';
		$this->section = 'person';
		return true;
	}

	function checkPrereqs ( $ignored )
	{
		return false;
	}

	/**
	 * Check to see if this user can activate themselves.
	 * This is only possible if the user is in the 'inactive' status. This
	 * also means that the user can't have a valid session.
	 */
	function has_permission ()
	{
		global $session;
		if(!$session->is_valid()) {
			if ($session->attr_get('status') != 'inactive') {
				$this->error_exit("You do not have a valid session");
			} 
		} else {
			return false;
		}
		
		return true;
	}

	function process ()
	{
		global $session;
		$step = var_from_getorpost('step');
		$id = $session->attr_get('user_id');
		
		switch($step) {
			case 'confirm': 
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$rc = $this->perform( $id );
				local_redirect("op=menu");
				break;
			default:
				$formData = $this->getFormData($id);
				$rc = $this->generateForm( $id , $formData, "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
		}

		return $rc;
	}
	
	function perform( $id )
	{
		$rc = parent::perform( $id );
		if( ! $rc ) {
			return false;
		}
	
		db_query("UPDATE person SET status = 'active' where user_id = %d", $id);

		if(1 != db_affected_rows()) {
			return false;
		}
		
		return true;
	}


}

class PersonSurvey extends PersonSignWaiver
{
	function initialize ()
	{
		global $session;
		$this->title = "Member Survey";

		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);
		$this->op = 'person_survey';
		$this->section = 'person';

		$this->formFile = 'member_survey.html';

		return true;
	}

	function perform()
	{
		global $session;
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

		if(count($fields) > 0) {
			$sql = "INSERT INTO demographics (";
			$sql .= join(",", $fields);	
			$sql .= ") VALUES(";
			for($i=0; $i< (count($fields) - 1); $i++) {
				$sql .= "'%s',";
			}
			$sql .= "'%s')";
			
			db_query($sql, $fields_data);
			if( 1 != db_affected_rows() ) {
				return false;
			}
		}
		
		db_query("UPDATE person SET survey_completed = 'Y' where user_id = %d", $session->attr_get('user_id'));
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}
}

class PersonSignWaiver extends Handler
{

	function checkPrereqs ( $op ) 
	{
		return false;
	}
	
	function initialize ()
	{
		global $session;
		$this->title = "Consent Form for League Play";

		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);
		$this->op = 'person_signwaiver';
		$this->section = 'person';

		$this->formFile = 'waiver_form.html';

		return true;
	}

	function process ()
	{
		$step   = var_from_getorpost('step');
		$next = var_from_getorpost('next');
		if(is_null($next)) {
			$next = queryPickle("menu");
		}
		
		switch($step) {

			case 'perform':
				$this->perform();
				local_redirect( queryUnpickle($next));
			default:
				$rc = $this->generateForm( $next );
		}	

		$this->setLocation( array($this->title => 0 ));
		
		return $rc;
	}

	/**
	 * Process input from the waiver form.
	 *
	 * User will not be permitted to log in if they have not signed the
	 * waiver.
	 */
	function perform()
	{
		global $session;
		
		$id = $session->attr_get('user_id');
		$signed = var_from_getorpost('signed');
		
		if('yes' != $signed) {
			$this->error_exit("Sorry, your account may only be activated by agreeing to the waiver.");
		}

		/* otherwise, it's yes.  Mark the signed_waiver field to the current
		 * date */
		db_query("UPDATE person SET waiver_signed=NOW() where user_id = %d", $id);

		if( 1 != db_affected_rows()) {
			return false;
		}
		
		return true;
	}

	function generateForm( $next )
	{
		$output = form_hidden('op', $this->op);
		$output .= form_hidden('next', $next);
		$output .= form_hidden('step', 'perform');

		ob_start();
		$retval = @readfile("data/" . $this->formFile);
		if (false !== $retval) {
			$output .= ob_get_contents();
		}
		ob_end_clean();

		$output .= para(form_submit('submit') . form_reset('reset'));
		
		return form($output);
	}
}

class PersonSignDogWaiver extends PersonSignWaiver
{
	function initialize ()
	{
		global $session;
		$this->title = "Consent Form For Dog Owners";

		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);
		$this->op = 'person_signdogwaiver';
		$this->section = 'person';

		$this->formFile = 'dog_waiver_form.html';

		return true;
	}

	/**
	 * Process input from the waiver form.
	 *
	 * User will not be permitted to log in if they have not signed the
	 * waiver.
	 */
	function perform()
	{
		global $$session;
		
		$id = $session->attr_get('user_id');
		$signed = var_from_getorpost('signed');
		
		if('yes' != $signed) {
			$this->error_exit("Sorry, if you wish to bring a dog to the fields, you must sign this waiver.");
		}

		/* otherwise, it's yes.  Set the user to 'active' and marked the
		 * signed_waiver field to the current date */
		db_query("UPDATE person SET dog_waiver_signed=NOW() where user_id = %d",$id);

		if( 1 != db_affected_rows() ) {
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
		$this->_permissions = array(
			'delete' => false,
			'create' => false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'volunteer_sufficient',
			'deny',
		);
		$this->op = 'person_list';
		$this->section = 'person';
		
		$this->setLocation(array("List Users" => 'op=' . $this->op));

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
		$letter = var_from_getorpost("letter");
		
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=person_view&id='
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'op=person_delete&id='
			);
		}
		$output = "";
		if($this->_permissions['create']) {
			$output .= l("Create New User", "op=person_create");
		}


		$query = "SELECT 
			CONCAT(lastname,', ',firstname) AS value, user_id AS id 
			FROM person WHERE lastname LIKE '%s%%' ORDER BY lastname,firstname";
		$output .= $this->generateAlphaList($query, $ops, 'lastname', 'person', $this->op, $letter);

		return $output;
	}
}

/**
 * Player list handler
 */
class PersonListNewAccounts extends Handler
{
	function initialize ()
	{
		$this->title = "New Accounts";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		$this->op = 'person_listnew';
		$this->section = 'admin';
		return true;
	}

	function process ()
	{
		$letter = var_from_getorpost("letter");

		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=person_view&id='
			),
			array(
				'name' => 'approve',
				'target' => 'op=person_approvenew&id='
			),
			array(
				'name' => 'delete',
				'target' => 'op=person_delete&id='
			),
		);

        $query = "SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id 
			 FROM person 
			 WHERE
			 	status = 'new'
			 AND
			 	lastname LIKE '%s%%'
			 ORDER BY lastname, firstname";

		$this->setLocation(array( $this->title => 'op=person_listnew' ));
		
		return $this->generateAlphaList($query, $ops, 'lastname', "person WHERE status = 'new'", $this->op, $letter);
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
		$this->op = 'person_changepassword';
		$this->section = 'person';
		return true;
	}

	function process()
	{
		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		switch($step) {
			case 'perform':
				$this->perform( $id );
				local_redirect("op=person_view&id=$id");
				break;
			default:
				$rc = $this->generateForm( $id );
		}
		
		return $rc;
	}
	
	function generateForm( $id )
	{

		$result = db_query( "SELECT firstname, lastname, username FROM person WHERE user_id = %d", $id);

		$user = db_fetch_array($result);
			
		if(!isset($user)) {
			$this->error_exit("That user does not exist");
		}
		
		$this->setLocation(array(
			$user['firstname'] . " " .$user['lastname'] => "op=person_view&id=$id",
			'Change Password' => 0
		));

		$output = para("You are changing the password for '"
			. $user['firstname'] . " " . $user['lastname']
			. "' (username '" . $user['username'] . "').");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= "<table border='0'>";
		$output .= simple_row("New Password:", form_password('', 'password_one', '', 25, 100, "Enter your new password"));
		$output .= simple_row("New Password (again):", form_password('', 'password_two', '', 25, 100, "Enter your new password a second time to confirm"));
		$output .= "</table>";
		$output .= form_submit("Submit") . form_reset("Reset");

		return form($output);
	}

	function perform ()
	{
		$id = var_from_getorpost('id');
		$pass_one = var_from_getorpost('password_one');
		$pass_two = var_from_getorpost('password_two');

		if($pass_one != $pass_two) {
			$this->error_exit("You must enter the same password twice.");
		}
		
		db_query("UPDATE person set password = '%s' WHERE user_id = %d",
			md5($pass_one), $id);
	
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}
}

class PersonForgotPassword extends Handler
{

	function checkPrereqs( $next )
	{
		return false;
	}

	function initialize ()
	{
		$this->_required_perms = array(
			'allow',
		);
		$this->title = "Request New Password";
		$this->op = 'person_forgotpassword';
		$this->section = 'person';
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
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm()
	{
		$output = <<<END_TEXT
<p>
	If you've forgotten your password, please enter as much information
	as you can in the following fields.   If you can only remember one
	or two things, that's OK... we'll try and figure it out.  Member ID 
	or username are required if you are sharing an email address with
	another registered player.
</p><p>
	If you don't receive an email within a few hours, you may not have
	remembered correctly, or the system may be encountering problems.
</p>
END_TEXT;

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= "<table border='0'>";
		$output .= simple_row("Username:", form_textfield('', 'username', '', 25, 100));
		$output .= simple_row("Member ID Number:", form_textfield('', 'member_id', '', 25, 100));
		$output .= simple_row("Email Address:", form_textfield('', 'email', '', 40, 100));
		$output .= "</table>";
		$output .= form_submit("Submit") . form_reset("Reset");

		return form($output);
	}

	function perform ()
	{
		$username = var_from_getorpost('username');
		$member_id = var_from_getorpost('member_id');
		$email = var_from_getorpost('email');
		
		$fields = array();
		$fields_data = array();
		if(validate_nonblank($username)) {
			$fields[] = "username = '%s'";
			$fields_data[] = $username;
		}
		if(validate_nonblank($email)) {
			$fields[] = "email = '%s'";
			$fields_data[] = $email;
		}
		if(validate_nonblank($member_id)) {
			$fields[] = "member_id = %d";
			$fields_data[] = $member_id;
		}
		
		if( count($fields) < 1 || (count($fields) != count($fields_data))) {
			$this->error_exit("You must supply at least one of username, member ID, or email address");
		}

		/* Now, try and find the user */
		$sql = "SELECT user_id,firstname,lastname,username,email FROM person WHERE ";
		$sql .= join(" AND ",$fields);

		$result = db_query($sql, $fields_data);
		
		if(db_num_rows($result) > 1) {
			$this->error_exit("You did not supply enough identifying information.  Try filling in more data.");
		}

		/* Now, we either have one or zero users.  Regardless, we'll present
		 * the user with the same output; that prevents them from using this
		 * to guess valid usernames.
		 */
		if(db_num_rows($result) == 1) {
			$user = db_fetch_object($result);
	
			/* Generate a password */
			$pass = generate_password();
			$cryptpass = md5($pass);

			db_query("UPDATE person SET password = '%s' WHERE user_id = %d", $cryptpass, $user->user_id);

			if( 1 != db_affected_rows() ) {
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
		}

		$output = <<<END_TEXT
<p>
	The password for the user matching the criteria you've entered has been
	reset to a randomly generated password.  The new password has been mailed
	to that user's email address.  No, we won't tell you what that email 
	address or user's name are -- if it's you, you'll know soon enough.
</p><p>
	If you don't receive an email within a few hours, you may not have
	remembered your information correctly, or the system may be encountering
	problems.
</p>
END_TEXT;
		return $output;
	}
}
?>
