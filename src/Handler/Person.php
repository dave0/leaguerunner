<?php
/*
 * Code for dealing with user accounts
 */

function person_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			$obj = new PersonCreate;
			break;
		case 'edit':
			$obj = new PersonEdit;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'view':
			$obj = new PersonView;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'delete':
			$obj = new PersonDelete;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'search':
			$obj = new PersonSearch;
			break;
		case 'approve':
			$obj = new PersonApproveNewAccount;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'activate':
			$obj = new PersonActivate;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'signwaiver': 
			$obj = new PersonSignWaiver;
			break;
		case 'signdogwaiver':
			$obj = new PersonSignDogWaiver;
			break;
		case 'listnew':
			$obj = new PersonListNewAccounts;
			break;
		case 'changepassword':
			$obj = new PersonChangePassword;  
			$obj->person = person_load( array('user_id' => $id) );
			break;
		case 'forgotpassword':
			$obj = new PersonForgotPassword;
			break;
		case 'historical':
			$obj = new PersonHistorical;
			$obj->person = person_load( array('user_id' => $id) );
			break;
		default:
			$obj = null;
	}
	if( $obj->person ) {
		person_add_to_menu( $obj->person );
	}
	return $obj;
}

/**
 * The permissions check for all Person actions.
 */
function person_permissions ( &$user, $action, $arg1 = NULL, $arg2 = NULL )
{
	$self_edit_fields = array();  # TODO
	$create_fields = array( 'name', 'username', 'password');
	$create_fields = array_merge($self_edit_fields, $create_fields);

	$all_view_fields = array( 'name', 'gender', 'skill', 'willing_to_volunteer' );
	if (variable_get('dog_questions', 1)) {
		$all_view_fields[] = 'dog';
	}
	$restricted_contact_fields = array( 'email', 'home_phone', 'work_phone', 'mobile_phone' );
	$captain_view_fields = array( 'height', 'shirtsize' );
	
	$self_view_fields = array('username','birthdate','address','last_login', 'member_id','height','shirtsize');
	$self_view_fields = array_merge($all_view_fields, $restricted_contact_fields, $self_view_fields);

	switch( $action ) {
		case 'create':
			return true;
			break;
		case 'edit':
			if( 'new' == $arg1) {
				// Almost all fields can be edited for new players
				if( $arg2 ) {
					return( in_array( $arg2, $create_fields ) );
				} else {
					return true;
				}
			}
			if( ! $user->is_active() ) {
				return false;
			}
			if( $user->user_id == $arg1 ) {
				if( $arg2 ) {
					return( in_array( $arg2, $self_edit_fields ) );
				} else {
					return true;
				}
			}
			break;
		case 'password_change':
			// User can change own password
			if( is_numeric( $arg1 ))  {
				if( $user->user_id == $arg1 ) {
					return true;
				}
			}
			break;
		case 'view':
			if( ! $user->is_active() ) {
				return false;
			}
			if( is_numeric( $arg1 )) {
				if( $user->user_id == $arg1 ) {
					// Viewing yourself allowed, most fields
					if( $arg2 ) {
						return( in_array( $arg2, $self_view_fields ) );
					} else {
						return true;
					}
				} else {
					// Other user.  Now comes the hard part
					$player = person_load( array('user_id' => $arg1) );

					// New or locked players cannot be viewed.
					if( $player->status == 'new' || $player->status == 'locked' ) {
						return false;
					}

					$sess_user_teams = implode(",",array_keys($user->teams));
					$viewable_fields = $all_view_fields;

					/* If player is a captain, their email is viewable */
					if( $player->is_a_captain ) {
						// Plus, can view email
						$viewable_fields[] = 'email';
					}

					if($user->is_a_captain) {
						/* If the current user is a team captain, and the requested user is on
						 * their team, they are allowed to view email/phone
						 */
						foreach( $player->teams as $team ) {
							if( $user->is_captain_of( $team->team_id ) &&
								$team->position != 'captain_request' ) {
								/* They are, so publish email and phone */
								$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields, $captain_view_fields);
								break;
							}
						}

						/* If the current user is a team captain, and the requested user is
						 * captain for a "nearby" team, they are allowed to view email/phone
						 */
						if($player->is_a_captain) {
							foreach( $player->teams as $player_team ) {
								if( $player->is_captain_of( $player_team->team_id ) ) {
									foreach( $user->teams as $user_team ) {
										if( $user->is_captain_of( $user_team->team_id ) &&
											$player_team->league_id == $user_team->league_id )
										{
											$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
										}
									}
								}
							}
						}
					}

					/* Coordinator info is viewable */
					if($player->is_a_coordinator) {
						$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
					}

					/* Coordinators get to see phone numbers of the captains they handle */
					if($user->is_a_coordinator && $player->is_a_captain) {
						foreach( $player->teams as $team ) {
							if( $player->is_captain_of( $team->team_id ) &&
								$user->coordinates_league_containing( $team->team_id ) )
							{
								$viewable_fields = array_merge($all_view_fields, $restricted_contact_fields);
							}
						}
					}

					// Finally, perform the check and return
					if( $arg2 ) {
						return( in_array( $arg2, $viewable_fields ) );
					} else {
						return true;
					}
				}
			}
			break;
		case 'list':
		case 'search':
			if( ! $user->is_active() ) {
				return false;
			}
			return($user->class != 'visitor');
		case 'approve':
			// administrator-only
		case 'delete':
			// administrator-only
		case 'listnew':
			// administrator-only
		default:
			return false;
	}

	return false;
}


/**
 * Generate the menu items for the "Players" and "My Account" sections.
 */
function person_menu() 
{
	global $lr_session;

	$id = $lr_session->attr_get('user_id');

	menu_add_child('_root', 'myaccount','My Account', array('weight' => -10, 'link' => "person/view/$id"));
	menu_add_child('myaccount', 'myaccount/edit','edit account', array('weight' => -10, 'link' => "person/edit/$id"));
	menu_add_child('myaccount', 'myaccount/pass', 'change password', array( 'link' => "person/changepassword/$id"));
	menu_add_child('myaccount', 'myaccount/signwaiver', 'view/sign player waiver', array( 'link' => "person/signwaiver", 'weight' => 3));

	if (variable_get('dog_questions', 1) && $lr_session->attr_get('has_dog') == 'Y') {
		menu_add_child('myaccount', 'myaccount/signdogwaiver', 'view/sign dog waiver', array( 'link' => "person/signdogwaiver", 'weight' => 4));
	}

    # Don't show "Players" menu for non-players.
	if( ! $lr_session->is_player() ) {
	    return;
	}

	menu_add_child('_root','person',"Players", array('weight' => -9));
	if($lr_session->has_permission('person','list') ) {
		menu_add_child('person','person/search',"search players", array('link' => 'person/search'));
	}

	if($lr_session->is_admin()) {
	
		$newUsers = person_count(array( 'status' => 'new' ));
		if($newUsers) {
			menu_add_child('person','person/listnew',"approve new accounts ($newUsers pending)", array('link' => "person/listnew"));
		}

		menu_add_child('person', 'person/create', "create account", array('link' => "person/create", 'weight' => 1));

		# Admin menu
		menu_add_child('settings', 'settings/person', 'user settings', array('link' => 'settings/person'));
		menu_add_child('statistics', 'statistics/person', 'player statistics', array('link' => 'statistics/person'));
	}
}

/**
 * Player viewing handler
 */
class PersonView extends Handler
{
	var $person;

	function has_permission ()
	{
		global $lr_session;

		if(!$this->person) {
			error_exit("That user does not exist");
		}

		return $lr_session->has_permission('person','view', $this->person->user_id);
	}

	function process ()
	{
		$this->title = 'View';
		$this->setLocation(array(
			$this->person->fullname => 'person/view/' . $this->person->user_id,
			$this->title => 0));

		return $this->generateView($this->person);
	}

	function generateView (&$person)
	{
		global $lr_session;

		$rows[] = array("Name:", $person->fullname);

		if( ! ($lr_session->is_player() || ($lr_session->attr_get('user_id') == $person->user_id)) ) {
			return "<div class='pairtable'>" . table(null, $rows) . "</div>";
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'username') ) {
			$rows[] = array("System Username:", $person->username);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'member_id') ) {
			if($person->member_id) {
				$rows[] = array(variable_get('app_org_short_name', 'League') . ' Member ID:', $person->member_id);
			} else {
				$rows[] = array(variable_get('app_org_short_name', 'League') . ' Member ID:', 'Not a member of ' . variable_get('app_org_short_name', 'the league'));
			}
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'user_id') ) {
			$rows[] = array('Website User ID:', $person->user_id);
		}

		if($person->allow_publish_email == 'Y') {
			$rows[] = array("Email Address:", l($person->email, "mailto:$person->email") . " (published)");
		} else {
			if($lr_session->has_permission('person','view',$person->user_id, 'email') ) {
				$rows[] = array("Email Address:", l($person->email, "mailto:$person->email") . " (private)");
			}
		}

		foreach(array('home','work','mobile') as $type) {
			$item = "${type}_phone";
			$publish = "publish_$item";
			if($person->$publish == 'Y') {
				$rows[] = array("Phone ($type):", $person->$item . " (published)");
			} else {
				if($lr_session->has_permission('person','view',$person->user_id, $item)  && isset($person->$item) ) {
					$rows[] = array("Phone ($type):", $person->$item . " (private)");
				}
			}
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'address')) {
			$rows[] = array("Address:", 
				format_street_address(
					$person->addr_street,
					$person->addr_city,
					$person->addr_prov,
					$person->addr_country,
					$person->addr_postalcode
				)
			);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'birthdate')) {
			$rows[] = array('Birthdate:', $person->birthdate);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'height')) {
			$rows[] = array('Height:', $person->height ? "$person->height inches" : "Please edit your account to enter your height");
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'gender')) {
			$rows[] = array("Gender:", $person->gender);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'shirtsize')) {
			$rows[] = array('Shirt Size:', $person->shirtsize);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'skill')) {
			$skillAry = getOptionsForSkill();
			$rows[] = array("Skill Level:", $skillAry[$person->skill_level]);
			$rows[] = array("Year Started:", $person->year_started);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'class')) {
			$rows[] = array("Account Class:", $person->class);
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'status')) {
			$rows[] = array("Account Status:", $person->status);
		}

		if(variable_get('dog_questions', 1)) {
			if($lr_session->has_permission('person','view',$person->user_id, 'dog')) {
				$rows[] = array("Has Dog:",($person->has_dog == 'Y') ? "yes" : "no");

				if($person->has_dog == 'Y') {
					$rows[] = array("Dog Waiver Signed:",($person->dog_waiver_signed) ? $person->dog_waiver_signed : "Not signed");
				}
			}
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'willing_to_volunteer')) {
			$rows[] = array('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?',($person->willing_to_volunteer == 'Y') ? 'yes' : 'no');
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'contact_for_feedback')) {
			$rows[] = array('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					($person->contact_for_feedback == 'Y') ? 'yes' : 'no');
		}

		if($lr_session->has_permission('person','view',$person->user_id, 'last_login')) {
			if($person->last_login) {
				$rows[] = array('Last Login:', 
					$person->last_login . ' from ' . $person->client_ip);
			} else {
				$rows[] = array('Last Login:', 'Never logged in');
			}
		}

		$rosterPositions = getRosterPositions();
		$teams = array();
		while(list(,$team) = each($person->teams)) {
			$teams[] = array(
				$rosterPositions[$team->position],
				'on',
				l($team->name, "team/view/$team->id"),
				"(" . l($team->league_name, "league/view/$team->league_id") . ")"
			);
		}
		reset($person->teams);
		$rows[] = array("Teams:", table( null, $teams) );
		if( count ($person->historical_teams) ) {
			$rows[] = array( '', 'There is also ' . l('historical team data', "person/historical/$person->user_id") . ' saved');
		}

		if( $person->is_a_coordinator ) {
			$leagues = array();
			foreach( $person->leagues as $league ) {
				$leagues[] = array(
					"Coordinator of",
					l($league->fullname, "league/view/$league->league_id")
				);
			}
			reset($person->leagues);

			$rows[] = array("Leagues:", table( null, $leagues) );
		}

		if( variable_get('registration', 0) ) {
			if($lr_session->has_permission('registration','history',$person->user_id)) {
				$rows[] = array("Registration:", l('View registration history', "registration/history/$person->user_id"));
			}
		}

		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

/**
 * Delete an account
 */
class PersonDelete extends PersonView
{
	var $person;

	function has_permission()
	{
		global $lr_session;

		if(!$this->person) {
			error_exit("That user does not exist");
		}

		return $lr_session->has_permission('person','delete', $this->person->user_id);
	}


	function process ()
	{
		global $lr_session;
		$this->title = 'Delete';
		$edit = $_POST['edit'];

		/* Safety check: Don't allow us to delete ourselves */
		if($lr_session->attr_get('user_id') == $this->person->user_id) {
			error_exit("You cannot delete your own account!");
		}

		if($edit['step'] == 'perform') {
			$this->person->delete();
			local_redirect(url("person/search"));
			return $rc;
		}

		$this->setLocation(array(
			$this->person->fullname => "person/view/" . $this->person->user_id,
			$this->title => 0));

		return 
			para("Confirm that you wish to delete this user from the system.")
			. $this->generateView($this->person)
			. form( 
				form_hidden('edit[step]', 'perform')
				. form_submit("Delete")
			);
	}
}

/**
 * Approve new account creation
 */
class PersonApproveNewAccount extends PersonView
{
	var $person;

	function has_permission()
	{
		global $lr_session;
		if(!$this->person) {
			error_exit("That user does not exist");
		}

		return $lr_session->has_permission('person','approve', $this->person->user_id);
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = 'Approve Account';

		if($edit['step'] == 'perform') {
			/* Actually do the approval on the 'perform' step */
			$this->perform( $edit );
			local_redirect("person/listnew");
		} 

		if($this->person->status != 'new') {
			error_exit("That account has already been approved");
		}

		$dispositions = array(
			'---'	          => '- Select One -',
			'approve_player'  => 'Approved as Player',
			'approve_visitor' => 'Approved as visitor account',
			'delete' 		  => 'Deleted silently',
		);

		$sth = $this->person->find_duplicates();

		$duplicates = '';
		while( $user = $sth->fetchObject('Person', array(LOAD_OBJECT_ONLY)) ) {
			$duplicates .= "<li>$user->firstname $user->lastname";
			$duplicates .= "[&nbsp;" . l("view", "person/view/$user->user_id") . "&nbsp;]";

			$dispositions["delete_duplicate:$user->user_id"] = "Deleted as duplicate of $user->firstname $user->lastname ($user->user_id)";
			$dispositions["merge_duplicate:$user->user_id"] = "Merged backwards into $user->firstname $user->lastname ($user->user_id)";
		}

		$approval_form = 
			form_hidden('edit[step]', 'perform')
			. form_select('This user should be', 'edit[disposition]', '---', $dispositions)
			. form_submit("Submit");


		$this->setLocation(array(
			$this->person->fullname => "person/view/" . $this->person->user_id,
			$this->title => 0));

		if( strlen($duplicates) > 0 ) {
			$duplicates = para("<div class='warning'><br>The following users may be duplicates of this account:<ul>\n"
				. $duplicates
				. "</ul></div>");
		}

		return 
			$duplicates
			. form( para($approval_form) )
			. $this->generateView($this->person);
	}

	function perform ( $edit )
	{
		global $lr_session; 

		$disposition = $edit['disposition'];

		if($disposition == '---') {
			error_exit("You must select a disposition for this account");
		}

		list($disposition,$dup_id) = split(':',$disposition);

		switch($disposition) {
			case 'approve_player':
				$this->person->set('class','player');
				$this->person->set('status','inactive');
				if(! $this->person->generate_member_id() ) {
					error_exit("Couldn't get member ID allocation");
				}

				if( ! $this->person->save() ) {
					error_exit("Couldn't save new member activation");
				}

				$message = _person_mail_text('approved_body_player', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%memberid' => $this->person->member_id,
					'%url' => url(""),
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));

				$rc = send_mail($this->person->email, $this->person->fullname,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('approved_subject', array( '%username' => $this->person->username, '%site' => variable_get('app_name','Leaguerunner') )), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			case 'approve_visitor':
				$this->person->set('class','visitor');
				$this->person->set('status','inactive');
				if( ! $this->person->save() ) {
					error_exit("Couldn't save new member activation");
				}

				$message = _person_mail_text('approved_body_visitor', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%url' => url(""),
					'%adminname' => variable_get('app_admin_name','Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));
				$rc = send_mail($this->person->email, $this->person->fullname,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('approved_subject', array( '%username' => $this->person->username, '%site' => variable_get('app_name','Leaguerunner' ))), 
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			case 'delete':
				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}
				return true;

			case 'delete_duplicate':
				$existing = person_load( array('user_id' => $dup_id) );
				$message = _person_mail_text('dup_delete_body', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%existingusername' => $existing->username,
					'%existingemail' => $existing->email,
					'%passwordurl' => url("person/forgotpassword"),
					'%adminname' => $lr_session->user->fullname,
					'%site' => variable_get('app_name','Leaguerunner')));

				$addresses = $names = array();
				$addresses[] = $this->person->email;
				$names[] = $this->person->fullname;
				if($this->person->email != $existing->email) {
					$addresses[] = $existing->email;
					$names[] = $this->person->fullname;
				}

				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}

				$rc = send_mail($addresses, $names,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('dup_delete_subject', array( '%site' => variable_get('app_name', 'Leaguerunner') )), 
					$message);

				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			// This is basically the same as the delete duplicate, except
			// that some old information (e.g. user ID) is preserved
			case 'merge_duplicate':
				$existing = person_load( array('user_id' => $dup_id) );
				$message = _person_mail_text('dup_merge_body', array( 
					'%fullname' => $this->person->fullname,
					'%username' => $this->person->username,
					'%existingusername' => $existing->username,
					'%existingemail' => $existing->email,
					'%passwordurl' => 'http://www.tuc.org/user.php?op=lostpassscreen&module=LostPassword',
					'%adminname' => variable_get('app_admin_name','Leaguerunner Admin'),
					'%site' => variable_get('app_name','Leaguerunner')));

				$addresses = $names = array();
				$addresses[] = $this->person->email;
				$names[] = $this->person->fullname;
				if($this->person->email != $existing->email) {
					$addresses[] = $existing->email;
					$names[] = $this->person->fullname;
				}

				// Copy over almost all of the new data; there must be a better way
				$existing->set('status', 'active');
				$existing->set('username', $this->person->username);
				$existing->set('password', $this->person->password);
				$existing->set('firstname', $this->person->firstname);
				$existing->set('lastname', $this->person->lastname);
				$existing->set('email', $this->person->email);
				$existing->set('allow_publish_email', $this->person->allow_publish_email);
				$existing->set('home_phone', $this->person->home_phone);
				$existing->set('publish_home_phone', $this->person->publish_home_phone);
				$existing->set('work_phone', $this->person->work_phone);
				$existing->set('publish_work_phone', $this->person->publish_work_phone);
				$existing->set('mobile_phone', $this->person->mobile_phone);
				$existing->set('publish_mobile_phone', $this->person->publish_mobile_phone);
				$existing->set('addr_street', $this->person->addr_street);
				$existing->set('addr_city', $this->person->addr_city);
				$existing->set('addr_prov', $this->person->addr_prov);
				$existing->set('addr_country', $this->person->addr_country);
				$existing->set('addr_postalcode', $this->person->addr_postalcode);
				$existing->set('gender', $this->person->gender);
				$existing->set('birthdate', $this->person->birthdate);
				$existing->set('height', $this->person->height);
				$existing->set('skill_level', $this->person->skill_level);
				$existing->set('year_started', $this->person->year_started);
				$existing->set('shirtsize', $this->person->shirtsize);
				$existing->set('session_cookie', $this->person->session_cookie);
				$existing->set('has_dog', $this->person->has_dog);
				$existing->set('survey_completed', $this->person->survey_completed);
				$existing->set('willing_to_volunteer', $this->person->willing_to_volunteer);
				$existing->set('contact_for_feedback', $this->person->contact_for_feedback);
				$existing->set('last_login', $this->person->last_login);
				$existing->set('client_ip', $this->person->client_ip);
				$existing->set('complete', $this->person->complete);

				if( !$existing->member_id) {
					$existing->generate_member_id();
				}

				if( ! $this->person->delete() ) {
					error_exit("Delete of user " . $this->person->fullname . " failed.");
				}

				if( ! $existing->save() ) {
					error_exit("Couldn't save new member information");
				}

				$rc = send_mail($addresses, $names,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('dup_merge_subject', array( '%site' => variable_get('app_name', 'Leaguerunner') )), 
					$message);

				if($rc == false) {
					error_exit("Error sending email to " . $this->person->email);
				}
				return true;

			default:
				error_exit("You must select a disposition for this account");

		}
	}
}


/**
 * Player edit handler
 */
class PersonEdit extends Handler
{
	var $person;

	function has_permission ()
	{
		global $lr_session;
		if(!$this->person) {
			error_exit("That user does not exist");
		}
		return $lr_session->has_permission('person','edit', $this->person->user_id);
	}

	function process ()
	{
		global $lr_session;
		$edit = $_POST['edit'];
		$this->title = 'Edit';

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $this->person->user_id, $edit );
				break;
			case 'perform':
				$this->perform( $this->person, $edit );
				if($this->person->is_active()) {
					local_redirect('person/view/' . $this->person->user_id);
				}
				else {
					local_redirect('');
				}
				break;
			default:
				$edit = object2array($this->person);
				$rc = $this->generateForm($this->person->user_id, $edit, "Edit any of the following fields and click 'Submit' when done.");
		}

		return $rc;
	}

	function generateForm ( $id, &$formData, $instructions = "")
	{
		global $lr_session, $BASE_URL;
		$output = <<<END_TEXT
<script language="JavaScript" type="text/javascript">
<!--
function popup(url)
{
	newwindow=window.open(url,'Leaguerunner Skill Rating Form','height=350,width=400,resizable=yes,scrollbars=yes')
	if (window.focus) {newwindow.focus()}
	return false;
}

function doNothing() {}

// -->
// </script>
END_TEXT;
		$output .= form_hidden('edit[step]', 'confirm');

		$output .= para($instructions);
		$output .= para(
			"Note that email and phone publish settings below only apply to regular players.  "
			. "Captains will always have access to view the phone numbers and email addresses of their confirmed players.  "
			. "All Team Captains will also have their email address viewable by other players"
		);
		$privacy = variable_get('privacy_policy', 'http://www.ocua.ca/node/17');
		if($privacy) {
			$output .= para(
					'If you have concerns about the data ' . variable_get('app_org_short_name', 'the league') . ' collects, please see our '
					. "<b><a href=\"$privacy\" target=\"_new\">Privacy Policy</a>.</b>"
			);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'name') ) {
			$group .= form_textfield('First Name', 'edit[firstname]', $formData['firstname'], 25,100, 'First (and, if desired, middle) name.');

			$group .= form_textfield('Last Name', 'edit[lastname]', $formData['lastname'], 25,100);
		} else {
			$group .= form_item('Full Name', $formData['firstname'] . ' ' . $formData['lastname']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'username') ) {
			$group .= form_textfield('System Username', 'edit[username]', $formData['username'], 25,100, 'Desired login name.');
		} else {
			$group .= form_item('System Username', $formData['username'], 'Desired login name.');
		}
		
		if($lr_session->has_permission('person', 'edit', $id, 'password') ) {
			$group .= form_password('Password', 'edit[password_once]', '', 25,100, 'Enter your desired password.');
			$group .= form_password('Re-enter Password', 'edit[password_twice]', '', 25,100, 'Enter your desired password a second time to confirm it.');
		}

		$group .= form_select('Gender', 'edit[gender]', $formData['gender'], getOptionsFromEnum( 'person', 'gender'), 'Select your gender');

		$output .= form_group('Identity', $group);

		$group = form_textfield('Email Address', 'edit[email]', $formData['email'], 25, 100, 'Enter your preferred email address.  This will be used by ' . variable_get('app_org_short_name', 'the league') . ' to correspond with you on league matters.');
		$group .= form_checkbox('Allow other players to view my email address','edit[allow_publish_email]','Y',($formData['allow_publish_email'] == 'Y'));

		$output .= form_group('Online Contact', $group);

		$group = form_textfield('Street and Number','edit[addr_street]',$formData['addr_street'], 25, 100, 'Number, street name, and apartment number if necessary');
		$group .= form_textfield('City','edit[addr_city]',$formData['addr_city'], 25, 100, 'Name of city');

		/* TODO: evil.  Need to allow Americans to use this at some point in
		 * time... */
		$group .= form_select('Province', 'edit[addr_prov]', $formData['addr_prov'], getProvinceNames(), 'Select a province/state from the list');

		$group .= form_select('Country', 'edit[addr_country]', $formData['addr_country'], getCountryNames(), 'Select a country from the list');

		$group .= form_textfield('Postal Code', 'edit[addr_postalcode]', $formData['addr_postalcode'], 8, 7, 'Please enter a correct postal code matching the address above. ' . variable_get('app_org_short_name', 'the league') . ' uses this information to help locate new fields near its members.');

		$output .= form_group('Street Address', $group);


		$group = form_textfield('Home', 'edit[home_phone]', $formData['home_phone'], 25, 100, 'Enter your home telephone number');
		$group .= form_checkbox('Allow other players to view home number','edit[publish_home_phone]','Y',($formData['publish_home_phone'] == 'Y'));
		$group .= form_textfield('Work', 'edit[work_phone]', $formData['work_phone'], 25, 100, 'Enter your work telephone number (optional)');
		$group .= form_checkbox('Allow other players to view work number','edit[publish_work_phone]','Y',($formData['publish_work_phone'] == 'Y'));
		$group .= form_textfield('Mobile', 'edit[mobile_phone]', $formData['mobile_phone'], 25, 100, 'Enter your cell or pager number (optional)');
		$group .= form_checkbox('Allow other players to view mobile number','edit[publish_mobile_phone]','Y',($formData['publish_mobile_phone'] == 'Y'));
		$output .= form_group('Telephone Numbers', $group);

		$player_classes = array(
			'player' => 'Player',
			'visitor' => 'Non-player account');

		if(! $formData['class'] ) {
			$formData['class'] = 'visitor';
		}

		if($lr_session->has_permission('person', 'edit', $id, 'class') ) {
			$player_classes['administrator'] = 'Leaguerunner administrator';
			$player_classes['volunteer'] = 'Volunteer';
		}

		# Volunteers can unset themselves as volunteer if they wish.
		if( $formData['class'] == 'volunteer' ) {
			$player_classes['volunteer'] = 'Volunteer';
		}

		$group = form_radiogroup('Account Type', 'edit[class]', $formData['class'], $player_classes );
		if($lr_session->has_permission('person', 'edit', $id, 'status') ) {
			$group .= form_select('Account Status','edit[status]', $formData['status'], getOptionsFromEnum('person','status'));
		}

		$output .= form_group('Account Information', $group);

		$group = form_select('Skill Level', 'edit[skill_level]', $formData['skill_level'], 
				getOptionsFromRange(1, 10), 
#				"Please use the questionnaire to <a href=\"javascript:doNothing()\" onClick=\"popup('$BASE_URL/data/rating.html')\">calculate your rating</a>"
				"Please use the questionnaire to <a href=\"$BASE_URL/data/rating.html\" target='_new'>calculate your rating</a>"
		);

		$thisYear = strftime('%Y', time());
		$group .= form_select('Year Started', 'edit[year_started]', $formData['year_started'], 
				getOptionsFromRange(1986, $thisYear, 'reverse'), 'The year you started playing Ultimate in this league.');

		$group .= form_select_date('Birthdate', 'edit[birth]', $formData['birthdate'], ($thisYear - 75), ($thisYear - 5), 'Please enter a correct birthdate; having accurate information is important for insurance purposes');

		$group .= form_textfield('Height','edit[height]',$formData['height'], 4, 4, 'Please enter your height in inches (5 feet is 60 inches; 6 feet is 72 inches).  This is used to help generate even teams for hat leagues.');

		$group .= form_select('Shirt Size','edit[shirtsize]', $formData['shirtsize'], getShirtSizes());

		if (variable_get('dog_questions', 1)) {
			$group .= form_radiogroup('Has Dog', 'edit[has_dog]', $formData['has_dog'], array(
				'Y' => 'Yes, I have a dog I will be bringing to games',
				'N' => 'No, I will not be bringing a dog to games'));
		}

		$group .= form_radiogroup('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?', 'edit[willing_to_volunteer]', $formData['willing_to_volunteer'], array(
			'Y' => 'Yes',
			'N' => 'No'));

		$group .= form_radiogroup('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					'edit[contact_for_feedback]', $formData['contact_for_feedback'],
					array('Y' => 'Yes',
							'N' => 'No'));

		$output .= form_group('Player and Skill Information', $group);

		$this->setLocation(array(
			$formData['fullname'] => "person/view/$id",
			$this->title => 0));

		$output .= para(form_submit('submit') . form_reset('reset'));

		return form($output);
	}

	function generateConfirm ( $id, $edit = array() )
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $id, $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes.");
		$output .= form_hidden('edit[step]', 'perform');

		$group = '';
		if($lr_session->has_permission('person', 'edit', $id, 'name') ) {
			$group .= form_item('First Name',
				form_hidden('edit[firstname]',$edit['firstname']) . $edit['firstname']);
			$group .= form_item('Last Name',
				form_hidden('edit[lastname]',$edit['lastname']) . $edit['lastname']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'username') ) {
			$group .= form_item('System Username',
				form_hidden('edit[username]',$edit['username']) . $edit['username']);
		}

		if($lr_session->has_permission('person', 'edit', $id, 'password') ) {
			$group .= form_item('Password',
				form_hidden('edit[password_once]', $edit['password_once'])
				. form_hidden('edit[password_twice]', $edit['password_twice'])
				. '<i>(entered)</i>');
		}
		$group .=  form_item('Gender', form_hidden('edit[gender]',$edit['gender']) . $edit['gender']);

		$output .= form_group('Identity', $group);

		$group = form_item('Email Address',
			form_hidden('edit[email]',$edit['email']) . $edit['email']);

		$group .= form_item('Show email to other players',
			form_hidden('edit[allow_publish_email]',$edit['allow_publish_email']) . $edit['allow_publish_email']);

		$output .= form_group('Online Contact', $group);

		$group = form_item('',
			form_hidden('edit[addr_street]',$edit['addr_street'])
			. form_hidden('edit[addr_city]',$edit['addr_city'])
			. form_hidden('edit[addr_prov]',$edit['addr_prov'])
			. form_hidden('edit[addr_country]',$edit['addr_country'])
			. form_hidden('edit[addr_postalcode]',$edit['addr_postalcode'])
			. "{$edit['addr_street']}<br>{$edit['addr_city']}, {$edit['addr_prov']}, {$edit['addr_country']}<br>{$edit['addr_postalcode']}");

		$output .= form_group('Street Address', $group);

		$group = '';
		foreach( array('home','work','mobile') as $location) {
			if($edit["${location}_phone"]) {
				$group .= form_item(ucfirst($location),
					form_hidden("edit[${location}_phone]", $edit["${location}_phone"])
					. $edit["${location}_phone"]);

				if($edit["publish_${location}_phone"] == 'Y') {
					$publish_info = "yes";
					$publish_info .= form_hidden("edit[publish_${location}_phone]", 'Y');
				} else {
					$publish_info = "no";
				}
				$group .= form_item("Allow other players to view $location number", $publish_info);
			}
		}
		$output .= form_group('Telephone Numbers', $group);


		$group = form_item("Account Class", form_hidden('edit[class]',$edit['class']) . $edit['class']);

		if($lr_session->has_permission('person', 'edit', $id, 'status') ) {
			$group .= form_item("Account Status", form_hidden('edit[status]',$edit['status']) . $edit['status']);
		}

		$output .= form_group('Account Information', $group);

		$levels = getOptionsForSkill();
		$group = form_item("Skill Level", form_hidden('edit[skill_level]',$edit['skill_level']) . $levels[$edit['skill_level']]);

		$group .= form_item("Year Started", form_hidden('edit[year_started]',$edit['year_started']) . $edit['year_started']);

		$group .= form_item("Birthdate", 
			form_hidden('edit[birth][year]',$edit['birth']['year']) 
			. form_hidden('edit[birth][month]',$edit['birth']['month']) 
			. form_hidden('edit[birth][day]',$edit['birth']['day']) 
			. $edit['birth']['year'] . " / " . $edit['birth']['month'] . " / " . $edit['birth']['day']);

		if($edit['height']) {
			$group .= form_item("Height", form_hidden('edit[height]',$edit['height']) . $edit['height'] . " inches");
		}
		$group .= form_item("Shirt Size", form_hidden('edit[shirtsize]',$edit['shirtsize']) . $edit['shirtsize']);

		if (variable_get('dog_questions', 1)) {
			$group .= form_item("Has dog", form_hidden('edit[has_dog]',$edit['has_dog']) . $edit['has_dog']);
		}

		$group .= form_item('Can ' . variable_get('app_org_short_name', 'the league') . ' contact you with a survey about volunteering?', form_hidden('edit[willing_to_volunteer]',$edit['willing_to_volunteer']) . $edit['willing_to_volunteer']);

		$group .= form_item('From time to time, ' . variable_get('app_org_short_name', 'the league') .
					' would like to contact members with information on our programs and to solicit feedback. ' .
					'Can ' . variable_get('app_org_short_name', 'the league') . ' contact you in this regard? ',
					form_hidden('edit[contact_for_feedback]',$edit['contact_for_feedback']) . $edit['contact_for_feedback']);

		$output .= form_group('Player and Skill Information', $group);

		$output .= para(form_submit('submit') . form_reset('reset'));

		$this->setLocation(array(
			$edit['firstname'] . " " . $edit['lastname'] => "person/view/$id",
			$this->title => 0));

		return form($output);
	}

	function perform ( &$person, $edit = array() )
	{
		global $lr_session;

		$dataInvalid = $this->isDataInvalid( $person->user_id, $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if($edit['username'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'username') ) {
			$person->set('username', $edit['username']);
		}

		/* EVIL HACK
		 * If this person is currently a 'visitor', it does not have a
		 * member number, so if we move it to another class, it needs
		 * to be given one.  We do this by forcing its status to 'new' and
		 * requiring it be reapproved.  Ugly hack, but since
		 * we're likely to scrutinize non-player accounts less than player
		 * accounts, it's necessary.
		 */
		if( ($person->class == 'visitor') && ($edit['class'] == 'player') ) {
			$person->set('status','new');
			$person->set('class','player');
			$status_changed = true;
		}

		if($edit['class'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'class') ) {
			$person->set('class', $edit['class']);
		}

		if($edit['status'] && $lr_session->has_permission('person', 'edit', $this->person->user_id, 'status') ) {
			$person->set('status',$edit['status']);
		}

		$person->set('email', $edit['email']);
		$person->set('allow_publish_email', $edit['allow_publish_email']);

		foreach(array('home_phone','work_phone','mobile_phone') as $type) {
			$num = $edit[$type];
			if(strlen($num)) {
				$person->set($type, clean_telephone_number($num));
			} else {
				$person->set($type, null);
			}

			$person->set('publish_' . $type, $edit['publish_' . $type] ? 'Y' : 'N');
		}

		if($lr_session->has_permission('person', 'edit', $this->person->user_id, 'name') ) {
			$person->set('firstname', $edit['firstname']);
			$person->set('lastname', $edit['lastname']);
		}

		$person->set('addr_street', $edit['addr_street']);
		$person->set('addr_city', $edit['addr_city']);
		$person->set('addr_prov', $edit['addr_prov']);
		$person->set('addr_country', $edit['addr_country']);

		$postcode = $edit['addr_postalcode'];
		if(strlen($postcode) == 6) {
			$foo = substr($postcode,0,3) . " " . substr($postcode,3);
			$postcode = $foo;
		}
		$person->set('addr_postalcode', $edit['addr_postalcode']);

		$person->set('birthdate', join("-",array(
			$edit['birth']['year'],
			$edit['birth']['month'],
			$edit['birth']['day'])));

		if($edit['height']) {
			$person->set('height', $edit['height']);
		}
		$person->set('shirtsize', $edit['shirtsize']);

		$person->set('gender', $edit['gender']);

		$person->set('skill_level', $edit['skill_level']);
		$person->set('year_started', $edit['year_started']);

		if (variable_get('dog_questions', 1)) {
			$person->set('has_dog', $edit['has_dog']);
		}

		$person->set('willing_to_volunteer', $edit['willing_to_volunteer']);
		$person->set('contact_for_feedback', $edit['contact_for_feedback']);

		if( ! $person->save() ) {
			error_exit("Internal error: couldn't save changes");
		} else {
			/* EVIL HACK
			 * If a user changes their own status from visitor to player, they
			 * will get logged out, so we need to warn them of this fact.
			 */
			if($status_changed) {
			   print theme_header("Edit Account", $this->breadcrumbs);
		       print "<h1>Edit Account</h1>";
			   print para(
				"You have requested to change your account status to 'Player'.  As such, your account is now being held for one of the administrators to approve.  "
				. 'Once your account is approved, you will receive an email informing you of your new ' . variable_get('app_org_short_name', 'League') . ' member number. '
				. 'You will then be able to log in once again with your username and password.');
		       print theme_footer();
			   exit;
			}
		}
		return true;
	}

	function isDataInvalid ( $id, $edit = array() )
	{
		global $lr_session;
		$errors = "";

		if($lr_session->has_permission('person','edit',$id, 'name')) {
			if( ! validate_name_input($edit['firstname']) || ! validate_name_input($edit['lastname'])) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in first and last names";
			}
		}

		if($lr_session->has_permission('person','edit',$id, 'username')) {
			if( ! validate_name_input($edit['username']) ) {
				$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
			}
			$user = person_load( array('username' => $edit['username']) );
			# TODO: BUG: need to check that $user->user_id != current id
			if( $user && !$lr_session->is_admin()) {
				error_exit("A user with that username already exists; please go back and try again");
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

		$address_errors = validate_address( 
			$edit['addr_street'],
			$edit['addr_city'],
			$edit['addr_prov'],
			$edit['addr_postalcode'],
			$edit['addr_country']);

		if( count($address_errors) > 0) {
			$errors .= "\n<li>" . join("\n<li>", $address_errors);
		}

		if( !preg_match("/^[mf]/i",$edit['gender'] ) ) {
			$errors .= "\n<li>You must select either male or female for gender.";
		}

		if( !validate_date_input($edit['birth']['year'], $edit['birth']['month'], $edit['birth']['day']) ) {
			$errors .= "\n<li>You must provide a valid birthdate";
		}

		if( validate_nonblank($edit['height']) ) {
			if( !$lr_session->is_admin() && ( ($edit['height'] < 36) || ($edit['height'] > 84) )) {
				$errors .= "\n<li>Please enter a reasonable and valid value for your height.";
			}
		}

		if( $edit['skill_level'] < 1 || $edit['skill_level'] > 10 ) {
			$errors .= "\n<li>You must select a skill level between 1 and 10. You entered " .  $edit['skill_level'];
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
	var $person;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','create');
	}

	function checkPrereqs( $next )
	{
		return false;
	}

	function process ()
	{
		$edit = $_POST['edit'];

		$this->title = 'Create Account';

		$id = 'new';
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->person = new Person;
				return $this->perform( $this->person, $edit );

			default:
				$edit = array();
				$rc = $this->generateForm( $id, $edit, "To create a new account, fill in all the fields below and click 'Submit' when done.  Your account will be placed on hold until approved by an administrator.  Once approved, you will be allocated a membership number, and have full access to the system.<p><b>NOTE</b> If you already have an account from a previous season, DO NOT CREATE ANOTHER ONE!  Instead, please <a href=\"" . url('person/forgotpassword') . "\">follow these instructions</a> to gain access to your account.");
		}
		$this->setLocation(array( $this->title => 0));
		return $rc;
	}

	function perform ( $person, $edit = array())
	{
		global $lr_session;

		if( ! validate_name_input($edit['username']) ) {
			$errors .= "\n<li>You can only use letters, numbers, spaces, and the characters - ' and . in usernames";
		}
		$existing_user = person_load( array('username' => $edit['username']) );
		if( $existing_user ) {
			error_exit("A user with that username already exists; please go back and try again");
		}

		if($edit['password_once'] != $edit['password_twice']) {
			error_exit("First and second entries of password do not match");
		}
		$crypt_pass = md5($edit['password_once']);

		$person->set('username', $edit['username']);
		$person->set('firstname', $edit['firstname']);
		$person->set('lastname', $edit['lastname']);
		$person->set('password', $crypt_pass);

		$rc = parent::perform( $person, $edit );

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
	var $person;

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
		global $lr_session;
		if($lr_session->is_valid()) {
			return false;
		}

		if ($lr_session->attr_get('status') != 'inactive') {
			error_exit("You do not have a valid session");
		} 

		return true;
	}

	function process ()
	{
		global $lr_session;

		$edit = $_POST['edit'];
		$this->title = "Activate Account";

		$this->person = $lr_session->user;
		if( ! $this->person ) {
			error_exit("That account does not exist");
		}

		switch($edit['step']) {
			case 'confirm': 
				$rc = $this->generateConfirm( $this->person->user_id, $edit );
				break;
			case 'perform':
				$rc = $this->perform( $this->person, $edit );
				if( ! $rc ) {
					error_exit("Failed attempting to activate account");
				}
				$this->person->set('status', 'active');
				$rc = $this->person->save();
				if( !$rc ) {
					error_exit("Failed attempting to activate account");
				}
				local_redirect(url("home"));
				break;
			default:
				$edit = object2array($this->person);
				$rc = $this->generateForm( $this->person->user_id , $edit, "In order to keep our records up-to-date, please confirm that the information below is correct, and make any changes necessary.");
		}

		return $rc;
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
		$this->title = 'Consent Form for League Play';
		$this->formFile = 'waiver_form.html';
		$this->querystring = 'UPDATE person SET waiver_signed=NOW() where user_id = ?';

		return true;
	}

	function has_permission()
	{
		global $lr_session;
		if (variable_get('registration', 0) && variable_get('allow_tentative', 0)) {
			return ($lr_session->is_loaded());
		} else {
			return ($lr_session->is_valid());
		}
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
		global $lr_session, $dbh;

		if('yes' != $edit['signed']) {
			error_exit("Sorry, your account may only be activated by agreeing to the waiver.");
		}

		/* otherwise, it's yes.  Perform the appropriate query to mark the
		 * waiver as signed.
		 */
		$sth = $dbh->prepare( $this->querystring );
		$sth->execute(array($lr_session->attr_get('user_id')));
		return (1 == $sth->rowCount() );
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
		$this->title = 'Consent Form For Dog Owners';
		$this->formFile = 'dog_waiver_form.html';
		$this->querystring = 'UPDATE person SET dog_waiver_signed=NOW() where user_id = ?';
		return true;
	}
}

class PersonSearch extends Handler
{
	function initialize ()
	{
		global $lr_session;
		$this->ops = array(
			'view' => 'person/view/%d'
		);

		$this->title = "Player Search";

		$this->extra_where = '';
		if( $lr_session->has_permission('person','delete') ) {
			$this->ops['delete'] = 'person/delete/%d';
		}
		return true;
	}

	function has_permission ()
	{
		global $lr_session;
	 	return $lr_session->has_permission('person','search');
	}

	function process ()
	{
		$edit = &$_POST['edit'];

		$this->max_results = variable_get('items_per_page', 25);

		switch($edit['step']) {
			case 'perform':
				$rc = $this->perform( $edit );
				break;
			default:
				$rc = $this->form();
		}
		$this->setLocation( array($this->title => 0 ));
		return $rc;
	}

	function form ( $data = '' ) 
	{

		$output = para("Enter last name of person to search for and click 'submit'.  You may use '*' as a wildcard");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= "<div class='form-item'><label>Last Name: </label><input type='textfield' size='25' name = 'edit[lastname]' value='$data' />";
		$output .= "<input type='submit' value='search' /></div>";
		return form($output);
	}

	function perform ( &$edit )
	{
		global $lr_session;

		if( $edit['lastname'] == '' ) {
			error_exit("You must provide a last name");
		}

		$offset = $edit['offset'];
		if( !$offset ) {
			$limit = $this->max_results + 1;
		} else {
			$limit = "$offset," . ($offset + $this->max_results + 1);
		}

		$search = array(
			'lastname_wildcard' => $edit['lastname'],
			'_order' => 'p.lastname, p.firstname',
			'_limit' => $limit
		);
		if( strlen($this->extra_where) ) {
			$search['_extra'] = $this->extra_where;
		}

		$result = person_query( $search );
		if( $result->rowCount() < 1 ) {
			return "No players matching <b>" . $edit['lastname'] . "</b> found";
		}

		$output = $this->form( $edit['lastname' ]);

		$output .= "<table><tr><td>";

		if( $offset > 0 ) {
			$output .= form( 
				form_hidden("edit[step]",'perform')
				. form_hidden('edit[lastname]', $edit['lastname'])
				. form_hidden('edit[offset]', $offset - $this->max_results )
				. form_submit("Prev")
			);
		}

		$count = 0;
		$people = '';
		$result->setFetchMode(PDO::FETCH_CLASS, 'Person', array(LOAD_OBJECT_ONLY));
		while($person = $result->fetch() ) {
			if(++$count > $this->max_results) {
				break;
			}
			$people .= "<tr><td>$person->lastname, $person->firstname</td>";
			while ( list($key, $value) = each($this->ops)) {
				$people .= '<td>' .l($key,sprintf($value, $person->user_id)) . "</td>";
			}
			reset($this->ops);
			$people .= "</tr>";
		}

		$output .= "</td><td align='right'>";

		if( $count > $this->max_results ) {
			$output .= form( 
				form_hidden("edit[step]",'perform')
				. form_hidden('edit[lastname]', $edit['lastname'])
				. form_hidden('edit[offset]', $edit['offset'] + $this->max_results )
				. form_submit("Next")
			);
		}
		$output .= "</td></tr>$people</table>";

		return $output;
	}
}

class PersonListNewAccounts extends Handler
{
	function has_permission ()
	{
		global $lr_session;
	 	return $lr_session->has_permission('person','listnew');
	}

	function process ()
	{
		$this->title = "New Accounts";
		$search = array(
			'status' => 'new',
			'_order' => 'p.lastname, p.firstname'
		);
		$ops = array(
			'view' => 'person/view/%d',
			'approve' => 'person/approve/%d',
			'delete' => 'person/delete/%d'
		);

		$sth = person_query( $search );

		$output = "<table>";
		while( $person = $sth->fetchObject('Person', array(LOAD_OBJECT_ONLY)) ) {
			$output .= "<tr><td>$person->lastname, $person->firstname</td><td>";
			while ( list($key, $value) = each($ops)) {
				$output .= '[&nbsp;' .l($key,sprintf($value, $person->user_id)) . '&nbsp;]';
				$output .= "&nbsp;";
			}
			reset($ops);
			$output .= "</td></tr>";
		}
		$output .= "</table>";


		$this->setLocation(array( $this->title => 'person/listnew' ));

		return $output;
	}
}

/**
 * Player password change
 */
class PersonChangePassword extends Handler
{
	var $person;

	function has_permission ()
	{
		global $lr_session;
		if( ! $this->person ) {
			$this->person =& $lr_session->user;
		}
		return $lr_session->has_permission('person','password_change', $this->person->user_id);
	}

	function process()
	{
		global $lr_session;
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				if($edit['password_one'] != $edit['password_two']) {
					error_exit("You must enter the same password twice.");
				}
				$this->person->set('password', md5($edit['password_one']));
				if( ! $this->person->save() ) {
					error_exit("Couldn't change password due to internal error");
				}
				local_redirect(url("person/view/" . $this->person->user_id));
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm( )
	{
		$this->setLocation(array(
			$user->fullname => "person/view/" . $this->person->user_id,
			'Change Password' => 0
		));

		$output = para("You are changing the password for '" . $this->person->fullname . "' (username '" . $this->person->username . "').");

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
}

class PersonForgotPassword extends Handler
{

	function checkPrereqs( $next )
	{
		return false;
	}

	function has_permission ()
	{
		// Can always request a password reset
		return true;
	}

	function process()
	{
		global $lr_session;
		$this->title = "Request New Password";
		$edit = $_POST['edit'];
		if ($lr_session->is_admin()) {
			$edit = $_GET['edit'];
		}
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
		$admin_addr = variable_get('app_admin_email', '');
		$org = variable_get('app_org_short_name', 'league');
		$output = <<<END_TEXT
<p>
	If you'd like to reset your password, please enter ANY ONE OF:
	<ul>
		<li>Your username 
		<li>Your email address
		<li>Your member number
	</ul>
	in the form below.  You only need to provide multiple pieces of
	information if you are sharing an email account with another $org player.
</p>
<p>
	If the information you provide matches an account, an email will be sent
	to the address on file, containing login information and a new password.
	If you don't receive an email within a few hours, you may not have
	remembered your information correctly.
</p>
<p>
  If you really can't remember any of these, you can mail <a
  href="mailto:$admin_addr">$admin_addr</a> for support.  <b>DO NOT CREATE A NEW ACCOUNT!</b>
</p>
END_TEXT;

		$output .= form_hidden('edit[step]', 'perform');
		$output .= "<div class='pairtable'>";
		$output .= table(null, array(
			array("Username:", form_textfield('', 'edit[username]', '', 25, 100)),
			array("Member ID Number:", form_textfield('', 'edit[member_id]', '', 25, 100)),
			array("Email Address:", form_textfield('', 'edit[email]', '', 40, 100, "(please enter only ONE email address in this box)"))
		));
		$output .= "</div>";
		$output .= form_submit("Submit") . form_reset("Reset");

		return form($output);
	}

	function perform ( $edit = array() )
	{
		$fields = array();
		if(validate_nonblank($edit['username'])) {
			$fields['username'] = $edit['username'];
		}
		if(validate_nonblank($edit['email'])) {
			$fields['email'] = $edit['email'];
		}
		if(validate_nonblank($edit['member_id'])) {
			$fields['member_id'] = $edit['member_id'];
		}

		if( count($fields) < 1 ) {
			error_exit("You must supply at least one of username, member ID, or email address");
		}

		/* Now, try and find the user */
		$user = person_load( $fields );

		/* Now, we either have one or zero users.  Regardless, we'll present
		 * the user with the same output; that prevents them from using this
		 * to guess valid usernames.
		 */
		if( $user ) {
			/* Generate a password */
			$pass = generate_password();
			$cryptpass = md5($pass);

			$user->set('password', $cryptpass);

			if( ! $user->save() ) {
				error_exit("Error setting password");
			}

			/* And fire off an email */
			$rc = send_mail($user->email, "$user->firstname user->lastname",
				false, false, // from the administrator
				false, false, // no Cc
				_person_mail_text('password_reset_subject', array('%site' => variable_get('app_name','Leaguerunner'))),
				_person_mail_text('password_reset_body', array(
					'%fullname' => "$user->firstname $user->lastname",
					'%username' => $user->username,
					'%password' => $pass,
					'%site' => variable_get('app_name','Leaguerunner')
				)));
			if($rc == false) {
				error_exit("System was unable to send email to that user.  Please contact system administrator.");
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

/**
 * Player historical data handler
 */
class PersonHistorical extends Handler
{
	var $person;

	function has_permission ()
	{
		global $lr_session;

		if(!$this->person) {
			error_exit("That user does not exist");
		}

		return $lr_session->has_permission('person','view', $this->person->user_id);
	}

	function process ()
	{
		$this->title = 'View Historical Teams';
		$this->setLocation(array(
			$this->person->fullname => 'person/view/' . $this->person->user_id,
			$this->title => 0));

		return $this->generateView($this->person);
	}

	function generateView (&$person)
	{
		global $lr_session;

		$rosterPositions = getRosterPositions();
		$rows = array();
		$last_year = $last_season = '';

		foreach($person->historical_teams as $team) {
			if( $team->year == $last_year ) {
				$year = '';
				if( $team->season == $last_season ) {
					$season = '';
				} else {
					$season = $team->season;
				}
			} else {
				$year = $team->year;
				$season = $team->season;
			}
			$last_year = $team->year;
			$last_season = $team->season;

			$rows[] = array(
				$year,
				$season,
				$rosterPositions[$team->position],
				'on',
				l($team->name, "team/view/$team->id"),
				"(" . l($team->league_name, "league/view/$team->league_id") . ")"
			);
		}

		return table(null, $rows);
	}
}

function person_add_to_menu( &$person ) 
{
	global $lr_session;
	if( ! ($lr_session->attr_get('user_id') == $person->user_id) ) {
		// These links already exist in the 'My Account' section if we're
		// looking at ourself
		menu_add_child('person', $person->fullname, $person->fullname, array('weight' => -10, 'link' => "person/view/$person->user_id"));
		if($lr_session->has_permission('person', 'edit', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/edit",'edit account', array('weight' => -10, 'link' => "person/edit/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'delete', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/delete",'delete account', array('weight' => -10, 'link' => "person/delete/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'password_change', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/changepassword",'change password', array('weight' => -10, 'link' => "person/changepassword/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'password_reset') ) {
			menu_add_child($person->fullname, "$person->fullname/forgotpassword", 'send new password', array( 'link' => "person/forgotpassword?edit[username]=$person->username&amp;edit[step]=perform"));
		}
	}
}

function _person_mail_text($messagetype, $variables = array() ) 
{
	// Check if the default has been overridden by the DB
	if( $override = variable_get('person_mail_' . $messagetype, false) ) {
		return strtr($override, $variables);
	} else {
		switch($messagetype) {
			case 'approved_subject':
				return strtr("%site Account Activation for %username", $variables);
			case 'approved_body_player':
				return strtr("Dear %fullname,\n\nYour %site account has been approved.\n\nYour new permanent member number is\n\t%memberid\nThis number will identify you for member services, discounts, etc, so please write it down in a safe place so you'll remember it.\n\nYou may now log in to the system at\n\t%url\nwith the username\n\t%username\nand the password you specified when you created your account.  You will be asked to confirm your account information and sign a waiver form before your account will be activated.\n\nThanks,\n%adminname", $variables);
			case 'approved_body_visitor':
				return strtr("Dear %fullname,\n\nYour %site account has been approved.\n\nYou may now log in to the system at\n\t%url\nwith the username\n\t%username\nand the password you specified when you created your account.  You will be asked to confirm your account information and sign a waiver form before your account will be activated.\n\nThanks,\n%adminname", $variables);
			case 'password_reset_subject':
				return strtr("%site Password Reset",$variables);
			case 'password_reset_body':
				return strtr("Dear %fullname,\n\nSomeone, probably you, just requested that your password for the account\n\t%username\nbe reset.  Your new password is\n\t%password\nSince this password has been sent via unencrypted email, you should change it as soon as possible.\n\nIf you didn't request this change, don't worry.  Your account password can only ever be mailed to the email address specified in your %site system account.  However, if you think someone may be attempting to gain unauthorized access to your account, please contact the system administrator.", $variables);
			case 'dup_delete_subject':
				return strtr("%site Account Update", $variables);
			case 'dup_delete_body':
				return strtr("Dear %fullname,\n\nYou seem to have created a duplicate %site account.  You already have an account with the username\n\t%existingusername\ncreated using the email address\n\t%existingemail\nYour second account has been deleted.  If you cannot remember your password for the existing account, please use the 'Forgot your password?' feature at\n\t%passwordurl\nand a new password will be emailed to you.\n\nIf the above email address is no longer correct, please reply to this message and request an address change.\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'dup_merge_subject':
				return strtr("%site Account Update", $variables);
			case 'dup_merge_body':
				return strtr("Dear %fullname,\n\nYou seem to have created a duplicate %site account.  You already had an account with the username\n\t%existingusername\ncreated using the email address\n\t%existingemail\nTo preserve historical information (registrations, team records, etc.) this old account has been merged with your new information.  You will be able to access this account with your newly chosen user name and password.\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'captain_request_subject':
			case 'player_request_subject':
				return strtr("%site Request to Join Team", $variables);
			case 'captain_request_body':
				return strtr("Dear %fullname,\n\nYou have been invited to join the roster of the %site team %team playing on %day in the '%league' league.  We ask that you please accept or decline this invitation at your earliest convenience.  More details about %team may be found at\n%teamurl\n\nIf you accept the invitation, you will be added to the team's roster and your contact information will be made available to the team captain.  If you decline the invitation you will be removed from this team's roster and your contact information will not be made available to the captain.  This protocol is in accordance with the %site Privacy Policy.\n\nPlease be advised that players are NOT considered a part of a team roster until they have accepted a captain's request to join.  Your team's roster must be completed (minimum of 12 rostered players) by the team roster deadline, and all team members must be listed as a 'regular player' (accepted the captain request).\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'player_request_body':
				return strtr("Dear %captains,\n\n%fullname has requested to join the roster of the %site team %team playing on %day in the '%league' league.  We ask that you please accept or decline this request at your earliest convenience.  Your team roster may be accessed at\n%teamurl\n\nIf you accept the invitation, %fullname will be added to the team's roster in whatever capacity you assign.  If you decline the invitation they will be removed from this team's roster.\n\nPlease be advised that players are NOT considered a part of a team roster until their request to join has been accepted by a captain.  Your team's roster must be completed (minimum of 12 rostered players) by the team roster deadline, and all team members must be listed as a 'regular player' (accepted by the captain).\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'score_reminder_subject':
				return strtr("%site Reminder to Submit Score", $variables);
			case 'score_reminder_body':
				return strtr("Dear %fullname,\n\nYou have not yet submitted a score for the game between your team %team and %opponent, which was scheduled for %gamedate in %league. Scores need to be submitted in a timely fashion by both captains to substantiate results and for optimal scheduling of future games.  We ask you to please update the score as soon as possible.  You can submit the score for this game at\n%scoreurl\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);
			case 'approval_notice_subject':
				return strtr("%site Notification of Score Approval", $variables);
			case 'approval_notice_body':
				return strtr("Dear %fullname,\n\nYou have not submitted a score for the game between your team %team and %opponent, which was scheduled for %gamedate in %league. Scores need to be submitted in a timely fashion by both captains to substantiate results and for optimal scheduling of future games.  Your opponent's submission for this game has been accepted, they have been given a perfect spirit score, and your spirit score has been penalized.\n\nIf there is some reason why you were unable to submit your score in time, you may contact your convener who will consider reversing the penalty. To avoid such penalties in the future, please be sure to submit your scores promptly.\n\nThanks,\n%adminname\n" . variable_get('app_org_short_name', 'League') . " Webteam", $variables);

			default:
				return "Unknown message type '$messagetype'!";
		}
	}
}

function person_settings ( )
{
	$group = form_textfield("Subject of account approval e-mail", "edit[person_mail_approved_subject]", _person_mail_text("approved_subject"), 70, 180, "Customize the subject of your approval e-mail, which is sent after account is approved." ." ". "Available variables are:" ." %username, %site, %url.");
 
	$group .= form_textarea('Body of account approval e-mail (player)', 'edit[person_mail_approved_body_player]', _person_mail_text('approved_body_player'), 70, 10, 'Customize the body of your approval e-mail, to be sent to players after accounts are approved. Available variables are: %fullname, %memberid, %adminname, %username, %site, %url.');

	$group .= form_textarea('Body of account approval e-mail (visitor)', 'edit[person_mail_approved_body_visitor]', _person_mail_text('approved_body_visitor'), 70, 10, 'Customize the body of your approval e-mail, to be sent to a non-player visitor after account is approved. Available variables are: %fullname, %adminname, %username, %site, %url.');

	$group .= form_textfield('Subject of password reset e-mail', 'edit[person_mail_password_reset_subject]', _person_mail_text('password_reset_subject'), 70, 180, 'Customize the subject of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %site.');
 
	$group .= form_textarea('Body of password reset e-mail', 'edit[person_mail_password_reset_body]', _person_mail_text('password_reset_body'), 70, 10, 'Customize the body of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %fullname, %adminname, %username, %password, %site, %url.');

	$group .= form_textfield('Subject of duplicate account deletion e-mail', 'edit[person_mail_dup_delete_subject]', _person_mail_text('dup_delete_subject'), 70, 180, 'Customize the subject of your account deletion mail, sent to a user who has created a duplicate account. Available variables are: %site.');
 
	$group .= form_textarea('Body of duplicate account deletion e-mail', 'edit[person_mail_dup_delete_body]', _person_mail_text('dup_delete_body'), 70, 10, 'Customize the body of your account deletion e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.');

	$group .= form_textfield('Subject of duplicate account merge e-mail', 'edit[person_mail_dup_merge_subject]', _person_mail_text('dup_merge_subject'), 70, 180, 'Customize the subject of your account merge mail, sent to a user who has created a duplicate account. Available variables are: %site.');
 
	$group .= form_textarea('Body of duplicate account merge e-mail', 'edit[person_mail_dup_merge_body]', _person_mail_text('dup_merge_body'), 70, 10, 'Customize the body of your account merge e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.');

	$group .= form_textfield('Subject of captain request e-mail', 'edit[person_mail_captain_request_subject]', _person_mail_text('captain_request_subject'), 70, 180, 'Customize the subject of your captain request mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %league, %day, %adminname.');
 
	$group .= form_textarea('Body of captain request e-mail', 'edit[person_mail_captain_request_body]', _person_mail_text('captain_request_body'), 70, 10, 'Customize the body of your captain request e-mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %teamurl, %league, %day, %adminname.');

	$group .= form_textfield('Subject of player request e-mail', 'edit[person_mail_player_request_subject]', _person_mail_text('player_request_subject'), 70, 180, 'Customize the subject of your player request mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %team, %league, %day, %adminname.');
 
	$group .= form_textarea('Body of player request e-mail', 'edit[person_mail_player_request_body]', _person_mail_text('player_request_body'), 70, 10, 'Customize the body of your player request e-mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %captains, %team, %teamurl, %league, %day, %adminname.');

	$group .= form_textfield('Subject of score reminder e-mail', 'edit[person_mail_score_reminder_subject]', _person_mail_text('score_reminder_subject'), 70, 180, 'Customize the subject of your score reminder mail, sent to captains when they have not submitted a score in a timely fashion. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');
 
	$group .= form_textarea('Body of score reminder e-mail', 'edit[person_mail_score_reminder_body]', _person_mail_text('score_reminder_body'), 70, 10, 'Customize the body of your score reminder e-mail, sent to captains when they have not submitted a score in a timely fashion. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');

	$group .= form_textfield('Subject of approval notice e-mail', 'edit[person_mail_approval_notice_subject]', _person_mail_text('approval_notice_subject'), 70, 180, 'Customize the subject of your approval notice mail, sent to captains when a game has been approved without a score submission from them. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');
 
	$group .= form_textarea('Body of approval notice e-mail', 'edit[person_mail_approval_notice_body]', _person_mail_text('approval_notice_body'), 70, 10, 'Customize the body of your approval notice e-mail, sent to captains when a game has been approved without a score submission from them. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');

	$output = form_group('User email settings', $group);

	return settings_form($output);
}

function person_statistics ( )
{
	global $dbh;
	$rows = array();

	$sth = $dbh->prepare('SELECT status, COUNT(*) FROM person GROUP BY status');
	$sth->execute();

	$sub_table = array();
	$sum = 0;
	while($row = $sth->fetch(PDO::FETCH_NUM)) {
		$sub_table[] = $row;
		$sum += $row[1];
	}
	$sub_table[] = array('Total', $sum);
	$rows[] = array('Players by account status:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT class, COUNT(*) FROM person GROUP BY class');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by account class:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT gender, COUNT(*) FROM person GROUP BY gender');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by gender:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 as age_bucket, COUNT(*) AS count FROM person GROUP BY age_bucket');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
		$sub_table[] = array($row['age_bucket'] . ' to ' . ($row['age_bucket'] + 4), $row['count']);
	}
	$rows[] = array('Players by age:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT addr_city, COUNT(*) AS num FROM person GROUP BY addr_city HAVING num > 2 ORDER BY num DESC');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by city:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT skill_level, COUNT(*) FROM person GROUP BY skill_level');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by skill level:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT year_started, COUNT(*) FROM person GROUP BY year_started');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by starting year:', table(null, $sub_table));

	if (variable_get('dog_questions', 1)) {
		$sth = $dbh->prepare("SELECT COUNT(*) FROM person where has_dog = 'Y'");
		$sth->execute();
		$rows[] = array('Players with dogs :', $sth->fetchColumn());
	}

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group('Player Statistics', $output);
}

?>
