<?php
/*
 * Code for dealing with user accounts
 */

function person_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new PersonCreate;
		case 'edit':
			return new PersonEdit;
		case 'view':
			return new PersonView;
		case 'delete':
			return new PersonDelete;
		case 'list':
		case '':
			return new PersonList;
		case 'approve':
			return new PersonApproveNewAccount;
		case 'activate':
			return new PersonActivate;
		case 'survey':
			return new PersonSurvey;
		case 'signwaiver': 
			return new PersonSignWaiver;
		case 'signdogwaiver':
			return new PersonSignDogWaiver;
		case 'listnew':
			return new PersonListNewAccounts;
		case 'changepassword':
			return new PersonChangePassword;  
		case 'forgotpassword':
			return new PersonForgotPassword;
	}
	return null;
}

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
		global $session;

		if(!$session->is_valid()) {
			$this->error_exit("You do not have a valid session");
		}
		
		$id = arg(2);
		
		if(!$id) {
			$this->error_exit("You must provide a user ID");
		}

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;

		/* Also, they can see if you have a dog */
		$this->_permissions['dog'] = true;
		
		/* Administrator can view all and do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			$this->_permissions['user_delete'] = true;
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

		$id = arg(2);

		$result = db_query(
			"SELECT p.*, w.name as ward_name, w.num as ward_number, w.city as ward_city FROM person p LEFT JOIN ward w ON (p.ward_id = w.ward_id) WHERE user_id = %d", $id);

		$person = db_fetch_array($result);
		
		if(!isset($person)) {
			$this->error_exit("That person does not exist");
		}
		
		$links = array();
		
		if($this->_permissions['user_edit']) {
			$links[] = l("edit account", "person/edit/" . $person['user_id'], array('title' => "Edit the currently-displayed account"));
		}
		
		if($this->_permissions['user_change_password']) {
			$links[] = l("change password", "person/changepassword/" . $person['user_id'], array('title' => "Change password for the currently-displayed account"));
		}
		
		if($this->_permissions['user_delete']) {
			$links[] = l("delete account", "person/delete/" . $person['user_id'], array('title' => "Delete the currently-displayed account"));
		}

		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "person/view/$id",
			$this->title => 0));
			
		$links_html =  "";
		if(count($links) > 0) {
			$links_html .= theme_links($links);
		}

		return $links_html . $this->generateView($person);
	}
	
	function generateView (&$person)
	{
		$rows[] = array("Name:", 
			$person['firstname'] . " " . $person['lastname']);

		if($this->_permissions['username']) {
			$rows[] = array("System Username:", $person['username']);
		}
		
		if($this->_permissions['member_id']) {
			$rows[] = array("OCUA Member ID:", $person['member_id']);
		}
		
		if($this->_permissions['email']) {
			if($person['allow_publish_email'] == 'Y') {
				$publish_value = " (published)";
			} else {
				$publish_value = " (private)";
			}
			$rows[] = array("Email Address:", l($person['email'], "mailto:" .  $person['email']) . $publish_value);
		}
		
		foreach(array('home','work','mobile') as $type) {
			if($this->_permissions["${type}_phone"] && $person["${type}_phone"]) {
				if($person["publish_${type}_phone"] == 'Y') {
					$publish_value = " (published)";
				} else {
					$publish_value = " (private)";
				}
				$rows[] = array("Phone ($type):", $person["${type}_phone"] . $publish_value);
			}
		}
		
		if($this->_permissions['address']) {
			$rows[] = array("Address:", 
				format_street_address(
					$person['addr_street'],
					$person['addr_city'],
					$person['addr_prov'],
					$person['addr_postalcode']
				)
			);
			if($person['ward_number']) {
				$rows[] = array('Ward:', 
					l($person['ward_name'] . ' (' . $person['ward_city']. ' Ward ' . $person['ward_number']. ')','op=ward_view&id='.$person['ward_id']));
			}
		}
		
		if($this->_permissions['birthdate']) {
			$rows[] = array('Birthdate:', $person['birthdate']);
		}
		
		if($this->_permissions['height']) {
			$rows[] = array('Height:', $person['height'] ? $person['height'] : 0 . ' inches');
		}
		
		if($this->_permissions['gender']) {
			$rows[] = array("Gender:", $person['gender']);
		}
		
		if($this->_permissions['skill']) {
			$skillAry = getOptionsForSkill();
			$rows[] = array("Skill Level:", $skillAry[$person['skill_level']]);
			$rows[] = array("Year Started:", $person['year_started']);
		}

		if($this->_permissions['class']) {
			$rows[] = array("Account Class:", $person['class']);
		}
		
		$rows[] = array("Account Status:", $person['status']);
		
		if($this->_permissions['dog']) {
			$rows[] = array("Has Dog:",($person['has_dog'] == 'Y') ? "yes" : "no");

			if($person['has_dog'] == 'Y') {
				$rows[] = array("Dog Waiver Signed:",($person['dog_waiver_signed']) ? $person['dog_waiver_signed'] : "Not signed");
			}
		}
		
		if($this->_permissions['last_login']) {
			if($person['last_login']) {
				$rows[] = array("Last Login:", 
					$person['last_login'] . ' from ' . $person['client_ip']);
			} else {
				$rows[] = array("Last Login:", "Never logged in");
			}
		}
		
		$result = db_query(
			"SELECT 
				r.status AS position,
				r.team_id AS id,
				t.name AS name
			FROM 
				teamroster r LEFT JOIN team t USING(team_id)
			WHERE 
				r.player_id = %d", $person['user_id']);
		$rosterPositions = getRosterPositions();
		$teams = array();
		while($team = db_fetch_array($result)) {
			$teams[] = array(
				$rosterPositions[$team['position']],
				"on",
				l($team['name'], "op=team_view&id=" . $team['id'])
			);
		}
		
		$rows[] = array("Teams:", table( null, $teams) );
				
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
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
			'admin_sufficient',
			'deny',
		);
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
		$edit = $_POST['edit'];
		
		$id = arg(2);

		/* Safety check: Don't allow us to delete ourselves */
		if($session->attr_get('user_id') == $id) {
			$this->error_exit("You cannot delete the currently logged in user");
		}

		if($edit['step'] == 'perform') {
			$this->perform( $id );
			local_redirect(url("person/list"));
			return $rc;
		}

		/* Otherwise... */
		$result = db_query(
			"SELECT p.*, w.name as ward_name, w.num as ward_number, w.city as ward_city FROM person p LEFT JOIN ward w ON (p.ward_id = w.ward_id) WHERE user_id = %d", $id);
		if(1 != db_num_rows($result)) {
			$this->error_exit("That person does not exist");
		}
		$person = db_fetch_array($result);
		
		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "person/view/$id",
			$this->title => 0));
		
		return 
			para("Confirm that you wish to delete this user from the system.")
			. $this->generateView($person)
			. form( 
				form_hidden('edit[step]', 'perform')
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
	function perform ( $id )
	{
	
		/* check if user is team captain       */
		$numTeams = db_result(db_query("SELECT COUNT(*) from teamroster where status = 'captain' AND player_id = %d", $id));
		
		if($numTeams > 0) {
			$this->error_exit("Account cannot be deleted while player is a team captain.");
		}
		
		/* check if user is league coordinator */
		$numLeagues = db_result(db_query("SELECT COUNT(*) from league where coordinator_id = %d OR alternate_id = %d", $id, $id));
		if($numLeagues > 0) {
			$this->error_exit("Account cannot be deleted while player is a league coordinator.");
		}
		
		/* remove user from team rosters.  Don't check for affected
		 * rows, as there may not be any
		 */
		db_query("DELETE from teamroster WHERE player_id = %d",$id);
		
		/* remove user account */
		db_query("DELETE from person WHERE user_id = %d", $id);
		
		return (1 != db_affected_rows());
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
			'admin_sufficient',
			'deny',
		);
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
		$edit = $_POST['edit'];
		$id = arg(2);

		if($edit['step'] == 'perform') {
			/* Actually do the approval on the 'perform' step */
			$this->perform( $id );
			local_redirect("person/listnew");
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
				$text .= "[&nbsp;" . l("view", "person/view/$user->user_id") . "&nbsp;]";
			}
			$text .= "</ul></div>";
		}

		$this->setLocation(array(
			$person['firstname'] . " " . $person['lastname'] => "person/view/$id",
			$this->title => 0));
		
		return para($instructions)
			. $this->generateView($person)
			. form( 
				form_hidden('edit[step]', 'perform')
				. form_submit("Approve")
			);
	}

	function perform ( $id )
	{

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
			'admin_sufficient',
			'self_sufficient',
			'deny',
		);

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

		$edit = $_POST['edit'];
		$id = arg(2);
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->perform( $id, $edit );
				local_redirect(url("person/view/$id"));
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
		$output .= form_hidden('edit[step]', 'confirm');

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

		$rows = array();
		$rows[] = array("First Name:",
			form_textfield('', 'edit[firstname]', $formData['firstname'], 25,100, "First (and, if desired, middle) name."));

		$rows[] = array("Last Name:",
			form_textfield('', 'edit[lastname]', $formData['lastname'], 25,100, "Last name"));

		if($this->_permissions['edit_username']) {
			$rows[] = array("System Username:",
				form_textfield('', 'edit[username]', $formData['username'], 25,100, "Desired login name."));
		} else {
			$rows[] = array("System Username:", $formData['username']);
		}

		if($this->_permissions['edit_password']) {
			$rows[] = array("Password:",
				form_password('', 'edit[password_once]', '', 25,100, "Enter your desired password."));
			$rows[] = array("Re-Enter Password:",
				form_password('', 'edit[password_twice]', '', 25,100, "Enter your desired password a second time to confirm it."));
		}

		$rows[] = array("Email Address:",
			form_textfield('', 'edit[email]', $formData['email'], 25, 100, "Enter your preferred email address.  This will be used by OCUA to correspond with you on league matters")
			. form_checkbox("Allow other players to view my email address",'edit[allow_publish_email]','Y',($formData['allow_publish_email'] == 'Y')));

		$addrRows = array();
		$addrRows[] = array("Street Address:",
			form_textfield('','edit[addr_street]',$formData['addr_street'], 25, 100, "Number, street name, and apartment number if necessary"));
		$addrRows[] = array("City:",
			form_textfield('','edit[addr_city]',$formData['addr_city'], 25, 100, "Name of city.  If you are a resident of the amalgamated Ottawa, please enter 'Ottawa' (instead of Kanata, Nepean, etc.)"));
			
		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$addrRows[] = array("Province:",
			form_select('', 'edit[addr_prov]', $formData['addr_prov'], getProvinceNames(), "Select a province from the list"));

		$addrRows[] = array("Postal Code:",
			form_textfield('', 'edit[addr_postalcode]', $formData['addr_postalcode'], 8, 7, "Please enter a correct postal code matching the address above.  OCUA uses this information to help locate new fields near its members."));

		$rows[] = array("Address:", table(null, $addrRows));
	
		$phoneRows = array();
		$phoneRows[] = array("Home:", form_textfield('', 'edit[home_phone]', $formData['home_phone'], 25, 100, form_checkbox("Allow other players to view this number",'edit[publish_home_phone]','Y',($formData['publish_home_phone'] == 'Y'))));
		$phoneRows[] = array("Work:", form_textfield('', 'edit[work_phone]', $formData['work_phone'], 25, 100, form_checkbox("Allow other players to view this number",'edit[publish_work_phone]','Y',($formData['publish_work_phone'] == 'Y'))));
		$phoneRows[] = array("Mobile:", form_textfield('', 'edit[mobile_phone]', $formData['mobile_phone'], 25, 100, form_checkbox("Allow other players to view this number",'edit[publish_mobile_phone]','Y',($formData['publish_mobile_phone'] == 'Y'))));

		$rows[] = array("Telephone:", table(null, $phoneRows));

		$rows[] = array("Gender:",
			form_select('', 'edit[gender]', $formData['gender'], getOptionsFromEnum( 'person', 'gender')));
			
		$rows[] = array("Skill Level:",
			form_radiogroup('', 'edit[skill_level]', $formData['skill_level'],
				getOptionsForSkill()));

		$thisYear = strftime("%Y", time());

		$rows[] = array("Year Started:",
			form_select('', 'edit[year_started]', $formData['year_started'], 
				getOptionsFromRange(1986, $thisYear, 'reverse'), "The year you started playing Ultimate in Ottawa."));

		$rows[] = array("Birthdate:",
			form_select_date('', 'edit[birth]', $formData['birthdate'], ($thisYear - 60), ($thisYear - 10), "Please enter a correct birthdate; having accurate information is important for insurance purposes"));

		$rows[] = array('Height:',
			form_textfield('','edit[height]',$formData['height'], 4, 4, 'Please enter your height in inches.  This is used to help generate even teams in hat leagues and winter indoor.'));
			
		if($this->_permissions['edit_class']) {
			$rows[] = array("Account Class:",
				form_select('','edit[class]', $formData['class'], getOptionsFromEnum('person','class')));
		}
		
		if($this->_permissions['edit_status']) {
			$rows[] = array("Account Status:",
				form_select('','edit[status]', $formData['status'], getOptionsFromEnum('person','status')));
		}

		$rows[] = array("Has dog:",
			form_radiogroup('', 'edit[has_dog]', $formData['has_dog'], array(
				'Y' => 'Yes, I have a dog I will be bringing to games',
				'N' => 'No, I will not be bringing a dog to games')));
		
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$this->setLocation(array(
			$formData['firstname'] . " " . $formData['lastname'] => "person/view/$id",
			$this->title => 0));

		$output .= para(form_submit('submit') . form_reset('reset'));

		
		return form($output);
	}

	function generateConfirm ( $id, $edit = array() )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden('edit[step]', 'perform');

		$rows = array();

		$rows[] = array("First Name:",
			form_hidden('edit[firstname]',$edit['firstname']) . $edit['firstname']);
			
		$rows[] = array("Last Name:",
			form_hidden('edit[lastname]',$edit['lastname']) . $edit['lastname']);
		
		if($this->_permissions['edit_username']) {
			$rows[] = array("System Username:",
				form_hidden('edit[username]',$edit['username']) . $edit['username']);
		}
		
		if($this->_permissions['edit_password']) {
			$rows[] = array("Password:",
				form_hidden('edit[password_once]', $edit['password_once'])
				. form_hidden('edit[password_twice]', $edit['password_twice'])
				. '<i>(entered)</i>');
		}
		
		$rows[] = array("Email Address:",
			form_hidden('edit[email]',$edit['email']) . $edit['email']);
			
		$rows[] = array("Show Email:",
			form_hidden('edit[allow_publish_email]',$edit['allow_publish_email']) . $edit['allow_publish_email']);

		$rows[] = array("Address:",
			form_hidden('edit[addr_street]',$edit['addr_street'])
			. form_hidden('edit[addr_city]',$edit['addr_city'])
			. form_hidden('edit[addr_prov]',$edit['addr_prov'])
			. form_hidden('edit[addr_postalcode]',$edit['addr_postalcode'])
			. $edit['addr_street'] . "<br>" . $edit['addr_city'] . ", " . $edit['addr_prov'] . "<br>" . $edit['addr_postalcode']);

		foreach( array('home','work','mobile') as $location) {
			if($edit["${location}_phone"]) {
				$phoneBlock .= form_hidden("edit[${location}_phone]", $edit["${location}_phone"]);
				$phoneBlock .= ucfirst($location) . ": " . $edit["${location}_phone"];
				if($edit["publish_${location}_phone"] == 'Y') {
					$phoneBlock .= " (published)";
					$phoneBlock .= form_hidden("edit[publish_${location}_phone]", 'Y');
				} else {
					$phoneBlock .= " (private)";
				}
			}
		}
		$rows[] = array("Telephone:", $phoneBlock);
		$rows[] = array("Gender:", form_hidden('edit[gender]',$edit['gender']) . $edit['gender']);
		
		$levels = getOptionsForSkill();
		$rows[] = array("Skill Level:", form_hidden('edit[skill_level]',$edit['skill_level']) . $levels[$edit['skill_level']]);
		
		$rows[] = array("Year Started:", form_hidden('edit[year_started]',$edit['year_started']) . $edit['year_started']);

		$rows[] = array("Birthdate:", 
			form_hidden('edit[birth][year]',$edit['birth']['year']) 
			. form_hidden('edit[birth][month]',$edit['birth']['month']) 
			. form_hidden('edit[birth][day]',$edit['birth']['day']) 
			. $edit['birth']['year'] . " / " . $edit['birth']['month'] . " / " . $edit['birth']['day']);
		
		if($edit['height']) {
			$rows[] = array("Height:", form_hidden('edit[height]',$edit['height']) . $edit['height'] . " inches");
		}
	
		if($this->_permissions['edit_class']) {
			$rows[] = array("Account Class:", form_hidden('edit[class]',$edit['class']) . $edit['class']);
		}
		
		if($this->_permissions['edit_status']) {
			$rows[] = array("Account Status:", form_hidden('edit[status]',$edit['status']) . $edit['status']);
		}
		
		$rows[] = array("Has dog:", form_hidden('edit[has_dog]',$edit['has_dog']) . $edit['has_dog']);
			
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$output .= para(form_submit('submit') . form_reset('reset'));

		$this->setLocation(array(
			$edit['firstname'] . " " . $edit['lastname'] => "person/view/$id",
			$this->title => 0));

		return form($output);
	}

	function perform ( $id, $edit = array() )
	{
	
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$fields      = array();
		$fields_data = array();

		if($this->_permissions['edit_username']) {
			$fields[] = "username = '%s'";
			$fields_data[] = $edit['username'];
		}
		
		if($this->_permissions['edit_class']) {
			$fields[] = "class = '%s'";
			$fields_data[] = $edit['class'];
		}
		if($this->_permissions['edit_status']) {
			$fields[] = "status = '%s'";
			$fields_data[] = $edit['status'];
		}
		
		$fields[] = "email = '%s'";
		$fields_data[] = $edit['email'];
		
		foreach(array('home_phone','work_phone','mobile_phone') as $type) {
			$num = $edit[$type];
			if(strlen($num) > 0) {
				$fields[] = "$type = '%s'";
				$fields_data[] = clean_telephone_number($num);
			} else {
				$fields[] = "$type = '%s'";
				$fields_data[] = null;
			}
		}
		
		$fields[] = "firstname = '%s'";
		$fields_data[] = $edit['firstname'];
		
		$fields[] = "lastname = '%s'";
		$fields_data[] = $edit['lastname'];
		
		$fields[] = "addr_street = '%s'";
		$fields_data[] = $edit['addr_street'];
		
		$fields[] = "addr_city = '%s'";
		$fields_data[] = $edit['addr_city'];
		
		$fields[] = "addr_prov = '%s'";
		$fields_data[] = $edit['addr_prov'];
		
		$postcode = $edit['addr_postalcode'];
		if(strlen($postcode) == 6) {
			$foo = substr($postcode,0,3) . " " . substr($postcode,3);
			$postcode = $foo;
		}
		$fields[] = "addr_postalcode = '%s'";
		$fields_data[] = strtoupper($postcode);
		
		$fields[] = "birthdate = '%s'";
		$fields_data[] = join("-",array(
			$edit['birth']['year'],
			$edit['birth']['month'],
			$edit['birth']['day']));
		
		if($edit['height']) {
			$fields[] = "height = %d";
			$fields_data[] = $height;
		}
		
		$fields[] = "gender = '%s'";
		$fields_data[] = $edit['gender'];
		
		$fields[] = "skill_level = '%s'";
		$fields_data[] = $edit['skill_level'];
		$fields[] = "year_started = '%s'";
		$fields_data[] = $edit['year_started'];

		$fields[] = "allow_publish_email = '%s'";
		$fields_data[] = $edit['allow_publish_email'];
		$fields[] = "publish_home_phone = '%s'";
		$fields_data[] = $edit['publish_home_phone'] ? 'Y' : 'N';
		$fields[] = "publish_work_phone = '%s'";
		$fields_data[] = $edit['publish_work_phone'] ? 'Y' : 'N';
		$fields[] = "publish_mobile_phone = '%s'";
		$fields_data[] = $edit['publish_mobile_phone'] ? 'Y' : 'N';
		
		$fields[] = "has_dog = '%s'";
		$fields_data[] = $edit['has_dog'];

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
		
		return (1 != db_affected_rows());
	}

	function isDataInvalid ( $edit = array() )
	{
		$errors = "";
	
		if( ! validate_name_input($edit['firstname']) || ! validate_name_input($edit['lastname'])) {
			$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
		}

		if($this->_permissions['edit_username']) {
			if( ! validate_name_input($edit['username']) ) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
		}

		if ( ! validate_email_input($edit['email']) ) {
			$errors .= "\n<li>You must supply a valid email address";
		}

		if( !validate_nonblank($edit['home_phone']) &&
			!validate_nonblank($edit['work_phone']) &&
			!validate_nonblank($edit['mobile_phone']) ) {
			$errors .= "\n<li>You must supply at least one valid telephone number.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['home_phone']) && !validate_telephone_input($edit['home_phone'])) {
			$errors .= "\n<li>Home telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['work_phone']) && !validate_telephone_input($edit['work_phone'])) {
			$errors .= "\n<li>Work telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}
		if(validate_nonblank($edit['mobile_phone']) && !validate_telephone_input($edit['mobile_phone'])) {
			$errors .= "\n<li>Mobile telephone number is not valid.  Please supply area code, number and (if any) extension.";
		}

		if( !validate_nonhtml($edit['addr_street']) ) {
			$errors .= "\n<li>You must supply a street address.";
		}
		$addr_city = var_from_getorpost('addr_city');
		if( !validate_nonhtml($edit['addr_city']) ) {
			$errors .= "\n<li>You must supply a city.";
		}
		if( !validate_nonhtml($edit['addr_prov']) ) {
			$errors .= "\n<li>You must supply a province.";
		}
		if( !validate_postalcode($edit['addr_postalcode']) ) {
			$errors .= "\n<li>You must supply a valid Canadian postal code.";
		}
		
		if( !preg_match("/^[mf]/i",$edit['gender'] ) ) {
			$errors .= "\n<li>You must select either male or female for gender.";
		}
		
		if( !validate_date_input($edit['birth']['year'], $edit['birth']['month'], $edit['birth']['day']) ) {
			$errors .= "\n<li>You must provide a valid birthdate";
		}

		if( validate_nonblank($edit['height']) ) {
			if( ($edit['height'] < 36) || ($edit['height'] > 84) ) {
				$errors .= "\n<li>Please enter a reasonable and valid value for your height.";
			}
		}
		
		if( $edit['skill_level'] < 1 || $edit['skill_level'] > 10 ) {
			$errors .= "\n<li>You must select a skill level between 1 and 10";
		}
		
		$current = localtime(time(),1);
		$this_year = $current['tm_year'] + 1900;
		if( $edit['year_started'] > $this_year ) {
			$errors .= "\n<li>Year started must be before current year.";
		}

		if( $edit['year_started'] < 1986 ) {
			$errors .= "\n<li>Year started must be after 1986.  For the number of people who started playing before then, I don't think it matters if you're listed as having played 17 years or 20, you're still old. :)";
		}
		$yearDiff = $edit['year_started'] - $edit['birth']['year'];
		if( $yearDiff < 8) {
			$errors .= "\n<li>You can't have started playing when you were $yearDiff years old!  Please correct your birthdate, or your starting year";
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

		$this->section = 'person';
		return true;
	}

	function checkPrereqs( $next )
	{
		return false;
	}
	
	function process ()
	{
		$edit = $_POST['edit'];

		$id = -1;
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				return $this->perform( &$id, $edit );
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

	function perform ( $id, $edit = array())
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		if($edit['password_once'] != $edit['password_twice']) {
			$this->error_exit("First and second entries of password do not match");
		}
		$crypt_pass = md5($edit['password_once']);

		if(db_num_rows(db_query("SELECT username FROM person WHERE username = '%s'",$edit['username']))) {
			$err = "A user with that username already exists; please go back and try again";
			$this->error_exit($err);
		}
	
		db_query("INSERT into person (username,password,status) VALUES('%s','%s','new')", $edit['username'], $crypt_pass);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$id = db_result(db_query("SELECT LAST_INSERT_ID() from person"));

		$rc = parent::perform( $id, $edit );

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

		$id = $session->attr_get('user_id');
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm': 
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$rc = $this->perform( $id, $edit );
				local_redirect(url("home"));
				break;
			default:
				$formData = $this->getFormData($id);
				$rc = $this->generateForm( $id , $formData, "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
		}

		return $rc;
	}
	
	function perform( $id, $edit = array() )
	{
		$rc = parent::perform( $id, $edit );
		if( ! $rc ) {
			return false;
		}
	
		db_query("UPDATE person SET status = 'active' where user_id = %d", $id);

		return (1 != db_affected_rows());
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
		$this->section = 'person';
		$this->formFile = 'member_survey.html';
		return true;
	}

	function perform()
	{
		global $session;
		
		$dem = $_POST['demographics'];
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
		
		return (1 != db_affected_rows());
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
		$this->section = 'person';
		$this->formFile = 'waiver_form.html';

		$this->querystring = "UPDATE person SET waiver_signed=NOW() where user_id = %d";

		return true;
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$next = $_POST['next'];
		
		if(is_null($next)) {
			$next = queryPickle("menu");
		}
		
		switch($edit['step']) {
			case 'perform':
				$this->perform( $edit );
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
	function perform( $edit = array() )
	{
		global $session;
		
		if('yes' != $edit['signed']) {
			$this->error_exit("Sorry, your account may only be activated by agreeing to the waiver.");
		}

		/* otherwise, it's yes.  Perform the appropriate query to markt he
		 * waiver as signed.
		 */
		db_query($this->querystring, $session->attr_get('user_id'));

		return (1 != db_affected_rows());
	}

	function generateForm( $next )
	{
		$output = form_hidden('next', $next);
		$output .= form_hidden('edit[step]', 'perform');

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
		$this->title = "Consent Form For Dog Owners";
		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);
		$this->section = 'person';
		$this->formFile = 'dog_waiver_form.html';
		$this->querystring = "UPDATE person SET dog_waiver_signed=NOW() where user_id = %d";
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
		$letter = $_GET['letter'];
		
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'person/view/'
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'person/delete/'
			);
		}
		$output = "";
		if($this->_permissions['create']) {
			$output .= l("Create New User", "person/create");
		}


		$query = "SELECT 
			CONCAT(lastname,', ',firstname) AS value, user_id AS id 
			FROM person WHERE lastname LIKE '%s%%' ORDER BY lastname,firstname";
		$output .= $this->generateAlphaList($query, $ops, 'lastname', 'person', 'person/list', $letter);
		
		$this->setLocation(array("List Users" => 'person/list'));

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
		$this->section = 'admin';
		return true;
	}

	function process ()
	{
		$letter = $_GET['letter'];

		$ops = array(
			array(
				'name' => 'view',
				'target' => 'person/view/'
			),
			array(
				'name' => 'approve',
				'target' => 'person/approve/'
			),
			array(
				'name' => 'delete',
				'target' => 'person/delete/'
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

		$this->setLocation(array( $this->title => 'person/listnew' ));
		
		return $this->generateAlphaList($query, $ops, 'lastname', "person WHERE status = 'new'", 'person/listnew', $letter);
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
			'admin_sufficient',
			'self_sufficient',
			'deny',
		);
		$this->section = 'person';
		return true;
	}

	function process()
	{
		global $session;
		$edit = $_POST['edit'];
		
		$id = arg(2);
		if(!$id) {
			$id = $session->attr_get('user_id');
		}
		
		switch($edit['step']) {
			case 'perform':
				$this->perform( $id, $edit );
				local_redirect(url("person/view/$id"));
				break;
			default:
				$rc = $this->generateForm( $id );
		}
		
		return $rc;
	}
	
	function generateForm( $id )
	{

		/* TODO: person_load */
		$result = db_query( "SELECT firstname, lastname, username FROM person WHERE user_id = %d", $id);
		if(1 != db_num_rows($result)) {
			$this->error_exit("That user does not exist");
		}
		$user = db_fetch_object($result);
		
		$this->setLocation(array(
			"$user->firstname $user->lastname" => "person/view/$id",
			'Change Password' => 0
		));

		$output = para("You are changing the password for '$user->firstname $user->lastname' (username '$user->username').");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= "<div class='pairtable'>";
		$output .= table( null, 
			array(
				array("New Password:", form_password('', 'edit[password_one]', '', 25, 100, "Enter your new password")),
				array("New Password (again):", form_password('', 'edit[password_two]', '', 25, 100, "Enter your new password a second time to confirm")),
			)
		);
		$output .= "</div>";
		
		$output .= form_submit("Submit") . form_reset("Reset");

		return form($output);
	}

	function perform ( $id , $edit = array())
	{
		if($edit['password_one'] != $edit['password_two']) {
			$this->error_exit("You must enter the same password twice.");
		}
		
		db_query("UPDATE person set password = '%s' WHERE user_id = %d",
			md5($edit['password_one']), $id);
	
		return (1 != db_affected_rows());
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
		$this->section = 'person';
		return true;
	}

	function process()
	{
		$edit = $_POST['edit'];
		switch($edit['step']) {
			case 'perform':
				$rc = $this->perform( $edit );	
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

		$output .= form_hidden('edit[step]', 'perform');
		$output .= "<div class='pairtable'>";
		$output .= table(null, array(
			array("Username:", form_textfield('', 'edit[username]', '', 25, 100)),
			array("Member ID Number:", form_textfield('', 'edit[member_id]', '', 25, 100)),
			array("Email Address:", form_textfield('', 'edit[email]', '', 40, 100))
		));
		$output .= "</div>";
		$output .= form_submit("Submit") . form_reset("Reset");

		return form($output);
	}

	function perform ( $edit = array() )
	{
		$fields = array();
		$fields_data = array();
		if(validate_nonblank($edit['username'])) {
			$fields[] = "username = '%s'";
			$fields_data[] = $edit['username'];
		}
		if(validate_nonblank($edit['email'])) {
			$fields[] = "email = '%s'";
			$fields_data[] = $edit['email'];
		}
		if(validate_nonblank($edit['member_id'])) {
			$fields[] = "member_id = %d";
			$fields_data[] = $edit['member_id'];
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
Dear $user->firstname $user->lastname,

Someone, probably you, just requested that your password for the account
	$user->username
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
			$rc = mail($user->email, $GLOBALS['APP_NAME'] . " Password Update", $message, "From: " . $GLOBALS['APP_ADMIN_EMAIL'] . "\r\n");
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
