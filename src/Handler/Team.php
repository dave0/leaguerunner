<?php
/*
 * Code for dealing with teams
 */
function team_dispatch() 
{
	global $session;
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			return new TeamCreate;
			break;
		case 'list':
			return new TeamList;
			break;
		case 'edit':
			$obj = new TeamEdit;
			break;
		case 'history':
			$obj = new TeamHistory;
			break;
		case 'view':
			$obj = new TeamView;
			break;
		case 'delete':
			$obj = new TeamDelete;
			break;
		case 'roster':
			$obj = new TeamRosterStatus;
			$player_id = arg(3);
			if( $player_id ) {
				$obj->player = person_load( array('user_id' => arg(3)) );
			}
			break;
		case 'schedule':
			$obj = new TeamSchedule;
			break;
		case 'spirit':
			$obj = new TeamSpirit;
			break;
		case 'emails':
			$obj = new TeamEmails;
			break;
		default:
			$obj = null;
	}
	$obj->team = team_load( array('team_id' => $id) );
	if( ! $obj->team ) {
		error_exit('That team does not exist');
	}
	team_add_to_menu( $obj->team );
	return $obj;
}

function team_permissions ( &$user, $action, $id, $data_field )
{
	if( $user->status != 'active') {
		return false;
	}
	
	switch( $action )
	{
		case 'create':
			// Players can create teams at-will
			return ($user->is_player());
		case 'list':
		case 'view schedule':
		case 'view':
			// Everyone can list, view, and view schedule if they're a player
			return ($user->is_player());
		case 'edit':
			return ($user->is_captain_of( $id ) );
		case 'email':
			if( $user->is_captain_of( $id ) ) {
				return true;
			}
			if( $user->coordinates_league_containing( $id ) ) {
				return true;
			}
			break;
		case 'player status':
			if( $user->is_captain_of( $id ) ) {	
				// Captain can adjust status of other players
				return true;
			} 
			if( $user->user_id == $data_field ) {
				// Player can adjust status of self
				return true;
			}
			break;
		case 'delete':
			if( $user->is_captain_of( $id ) ) {
				return true;
			}
			break;
		case 'statistics':
			// admin-only
			break;
	}
	return false;
}

function team_menu()
{
	global $session;

	if( ! $session->is_player() ) {
		return;
	}
	
	menu_add_child('_root','team','Teams', array('weight' => -8));
	menu_add_child('team','team/list','list teams', array('link' => 'team/list') );
	menu_add_child('team','team/create','create team', array('link' => 'team/create', 'weight' => 1) );

	if( $session->is_valid() ) {
		while(list(,$team) = each($session->user->teams) ) {
			team_add_to_menu($team);
		}
		reset($session->user->teams);
	}
	
	if($session->has_permission('team','statistics')) {
		menu_add_child('statistics', 'statistics/team', 'team statistics', array('link' => 'statistics/team'));
	}
}

/**
 * Add view/edit/delete links to the menu for the given team
 */
function team_add_to_menu( &$team ) 
{
	global $session;
	
	menu_add_child('team', $team->name, $team->name, array('weight' => -10, 'link' => "team/view/$team->team_id"));
	menu_add_child($team->name, "$team->name/standings",'standings', array('weight' => -1, 'link' => "league/standings/$team->league_id"));
	menu_add_child($team->name, "$team->name/schedule",'schedule', array('weight' => -1, 'link' => "team/schedule/$team->team_id"));

	if( $session->user && !array_key_exists( $team->team_id, $session->user->teams ) ) {
		if($team->status != 'closed') {
			menu_add_child($team->name, "$team->name/join",'join team', array('weight' => 0, 'link' => "team/roster/$team->team_id/" . $session->attr_get('user_id')));
		}
	} 

	menu_add_child($team->name, "$team->name/spirit", "spirit", array('weight' => 1, 'link' => "team/spirit/$team->team_id"));
	
	if( $session->has_permission('team','edit',$team->team_id)) {
		menu_add_child($team->name, "$team->name/edit",'edit team', array('weight' => 1, 'link' => "team/edit/$team->team_id"));
		menu_add_child($team->name, "$team->name/add",'add player', array('weight' => 0, 'link' => "team/roster/$team->team_id"));
	}
	
	if( $session->has_permission('team','email',$team->team_id)) {
		menu_add_child($team->name, "$team->name/emails",'player emails', array('weight' => 2, 'link' => "team/emails/$team->team_id"));
	}
		
	if( $session->has_permission('team','delete',$team->team_id)) {
		menu_add_child($team->name, "$team->name/delete",'delete team', array('weight' => 1, 'link' => "team/delete/$team->team_id"));
	}
}

/**
 * Generate view of teams for initial login splash page.
 */
function team_splash ()
{
	global $session;
	$rows = array();
	$rows[] = array('','', array( 'data' => '','width' => 90), '');
		
	$rosterPositions = getRosterPositions();
	$rows = array();
	while(list(,$team) = each($session->user->teams)) {
		$position = $rosterPositions[$team->position];
		
		$rows[] = 
			array(
				l($team->name, "team/view/$team->id") . " ($team->position)",
				array('data' => theme_links(array(
						l("schedules", "team/schedule/$team->id"),
						l("standings", "league/standings/$team->league_id"))),
					  'align' => 'right')
		);

	}
	reset($session->user->teams);
	if( count($session->user->teams) < 1) {
		$rows[] = array( array('colspan' => 2, 'data' => "You are not yet on any teams"));
	}
	return table( array( array('data' => 'My Teams', colspan => 4),), $rows);
}


/**
 * Team create handler
 */
class TeamCreate extends TeamEdit
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','create');
	}
	
	function process ()
	{
		$this->title = "Create Team";
		$edit = &$_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->team = new Team;
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ($edit = array() )
	{
		global $session;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$existing_team = team_load( array('name' => trim($edit['name'])) );
		if($existing_team) {
			error_exit("A team with that name already exists; please go back and try again");
		}

		if( ! parent::perform($edit) ) {
			return false;
		}
		
		db_query("INSERT INTO leagueteams (league_id, team_id) VALUES(1, %d)", $this->team->team_id);
		if( 1 != db_affected_rows() ) {
			return false;
		}
	
		# TODO: Replace with $team->add_player($session->user,'captain')
		#       and call before parent::perform()
		db_query("INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(%d, %d, 'captain', NOW())", $this->team->team_id, $session->attr_get('user_id'));
		if( 1 != db_affected_rows() ) {
			return false;
		}

		return true;
	}
}

/**
 * Team edit handler
 */
class TeamEdit extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','edit',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Edit Team";
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				$edit = object2array($this->team);
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($edit['name']  => "team/view/" . $this->team->team_id, $this->title => 0));
		return $rc;
	}

	function generateForm (&$formData)
	{
		$output = form_hidden("edit[step]", 'confirm');
		
		$rows = array();
		$rows[] = array("Team Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The full name of your team.  Text only, no HTML"));
		$rows[] = array("Website:", form_textfield('', 'edit[website]', $formData['website'], 35,200, "Your team's website (optional)"));
		$rows[] = array("Shirt Colour:", form_textfield('', 'edit[shirt_colour]', $formData['shirt_colour'], 35,200, "Shirt colour of your team.  If you don't have team shirts, pick 'light' or 'dark'"));
		$rows[] = array("Team Status:", 
			form_select("", "edit[status]", $formData['status'], getOptionsFromEnum('team','status'), "Is your team open (others can join) or closed (only captain can add players)"));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		return form($output);
	}

	function generateConfirm ($edit = array() )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click'Submit'  to make your changes");
		$output .= form_hidden("edit[step]", 'perform');
		
		$rows[] = array("Team Name:", form_hidden('edit[name]',$edit['name']) .  $edit['name']);
		$rows[] = array("Website:", form_hidden('edit[website]',$edit['website']) .  $edit['website']);
		$rows[] = array("Shirt Colour:", form_hidden('edit[shirt_colour]',$edit['shirt_colour']) .  $edit['shirt_colour']);
		$rows[] = array("Team Status:", form_hidden('edit[status]',$edit['status']) .  $edit['status']);
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));
		
		return form($output);
	}

	function perform ($edit = array())
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->team->set('name', $edit['name']);
		$this->team->set('website', $edit['website']);
		$this->team->set('shirt_colour', $edit['shirt_colour']);
		$this->team->set('status', $edit['status']);
		
		if( !$this->team->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = "";

		if( !validate_nonhtml($edit['name']) ) {
			$errors .= "<li>You must enter a valid team name";
		}
		
		if( !validate_nonhtml($edit['shirt_colour']) ) {
			$errors .= "<li>Shirt colour cannot be left blank";
		}
		
		if(validate_nonblank($edit['website'])) {
			if( ! validate_nonhtml($edit['website']) ) {
				$errors .= "<li>If you provide a website URL, it must be valid. Otherwise, leave the website field blank.";
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
 * Team viewing handler
 */
class TeamDelete extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','delete',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Delete Team";
		
		$this->setLocation(array( 
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0
		));

		switch($_POST['edit']['step']) {
			case 'perform':
				if ( $this->team->delete() ) {
					local_redirect(url("league/view/1"));	
				} else {
					error_exit("Failure deleting team");
				}
				break;
			case 'confirm':
			default:
				return $this->generateConfirm();
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function generateConfirm ()
	{
		$rows = array();
		$rows[] = array("Team Name:", check_form($this->team->name));
		if($this->team->website) {
			if(strncmp($this->team->website, "http://", 7) != 0) {
				$this->team->website = "http://" . $this->team->website;
			}
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));
		
		$rows[] = array("Team Status:", $this->team->status);

		/* and, grab roster */
		$result = db_query( "SELECT COUNT(r.player_id) as num_players FROM teamroster r WHERE r.team_id = %d", $this->team->team_id);

		$rows[] = array("Num. players on roster:", db_result($result));

		$output = form_hidden('edit[step]', 'perform');
		$output .= "<p>Do you really wish to delete this team?</p>";
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= form_submit('submit');
	
		return form($output);
	}
}

/**
 * Team list handler
 */
class TeamList extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','list');
	}
	
	function process ()
	{
		global $session;
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'team/view/'
			),
		);
		if($session->has_permission('team','delete')) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'team/delete/'
			);
		}
		
		$this->setLocation(array("List Teams" => 'team/list'));
		return $this->generateAlphaList("SELECT name AS value, team_id AS id FROM team WHERE name LIKE '%s%%' ORDER BY name",
			$ops, 'name', 'team', 'team/list', $_GET['letter']);
	}
}

/**
 * Player status handler
 */
class TeamRosterStatus extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','player status',$this->team->team_id, $this->player->user_id);
	}

	/**
	 * Loads the permitedStates variable, and checks that the session user is
	 * allowed to change the state of the specified player on this team.
	 */
	function loadPermittedStates ($teamId, $playerId)
	{
		global $session;

		$is_captain = false;
		$is_administrator = false;
		
		if($session->attr_get('class') == 'administrator') {
			$is_administrator = true;
		}
		
		if($session->is_captain_of($teamId)) {  
			$is_captain = true;
		}

		/* Ordinary player can only set things for themselves */
		if(!($is_captain  || $is_administrator)) {
			$allowed_id = $session->attr_get('user_id');
			if($allowed_id != $playerId) {
				error_exit("You cannot change status for that player ID");
			}
		}
		
		/* Now, check for the player's status, or set 'none' if
		 * not currently on team.
		 */
		$this->currentStatus = db_result(db_query("SELECT status FROM teamroster WHERE team_id = %d and player_id = %d", $teamId, $playerId));
		
		if(!$this->currentStatus) {
			$this->currentStatus = 'none';
		}

		/*
		 * Sets the permissions for a state change.
		 * 	- check who user is.  Captain and administrator can:
		 * 		- 'none' -> 'captain_request'
		 *	 	- 'player_request' -> 'none', 'player' or 'substitute'
		 *	 		- 'player' -> 'captain', 'substitute', 'none'
		 * 		- 'substitute' -> 'captain', 'player', 'none'
		 * 		- 'captain' -> 'player', 'substitute', 'none'
		 * 	  in addition, administrator can go from anything to anything.
		 * 	  Players are allowed to (for their own player_id):
		 * 	  	- 'none' -> 'player_request'
		 * 	  	- 'captain_request' -> 'none', 'player' or 'substitute'
		 * 	  	- 'player_request' -> 'none'
		 * 	  	- 'player' -> 'substitute', 'none'
		 * 	  	- 'substitute' -> 'none'
		 */
		$this->permittedStates = array();
		if($is_administrator) {
			/* can't change to current value, but all others OK */
			$this->permittedStates = array_keys($this->positions);
			array_splice($this->permittedStates, array_search($this->currentStatus, $this->permittedStates), 1);

		} else if ($is_captain) {
			$this->permittedStates = $this->getStatesForCaptain($teamId);
		} else {
			$this->permittedStates = $this->getStatesForPlayer($teamId);
		}

		return true;
	}
	
	function getStatesForCaptain($id)
	{
		switch($this->currentStatus) {
		case 'captain':
			$num_captains = db_result(db_query("SELECT COUNT(*) FROM teamroster where status = 'captain' AND team_id = %d", $id));
			
			if($num_captains <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'captain', 'player', 'substitute');
		case 'player':
			return array( 'none', 'captain', 'assistant', 'substitute');
		case 'substitute':
			return array( 'none', 'captain', 'assistant', 'player');
		case 'captain_request':
			/* Captains cannot move players from this state,
			 * except to remove them.
			 */
			return array( 'none' );
		case 'player_request':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
		case 'none':
			return array( 'captain_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function getStatesForPlayer($id)
	{
		switch($this->currentStatus) {
		case 'captain':
			$num_captains = db_result(db_query("SELECT COUNT(*) FROM teamroster where status = 'captain' AND team_id = %d", $id));
			
			if($num_captains <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'player', 'substitute');
		case 'player':
			return array( 'none', 'substitute');
		case 'substitute':
			return array( 'none' );
		case 'captain_request':
			return array( 'none', 'player', 'substitute');
		case 'player_request':
			return array( 'none' );
		case 'none':
			$is_open = db_result(db_query("SELECT status from team where team_id = %d",$id));
			
			if($is_open != 'open') {
				error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			error_exit("Internal error in player status");
		}
	}
	
	function process ()
	{
		global $session;
		
		$this->title = "Roster Status";
		
		$this->positions = getRosterPositions();
		$this->currentStatus = null;
		
		if( !$this->player ) {
			if( !($session->is_admin() || $session->is_captain_of($team->team_id))) {
				error_exit("You cannot add a person to that team!");
			}

			$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, "Add Player" => 0));

			$new_handler = new PersonSearch;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->team->name] = 'team/roster/' .$this->team->team_id . '/%d';
			return $new_handler->process();
		}

		if(!$this->player->is_player()) {
			error_exit("Only OCUA-registered players can be added to a team");
		}
		
		$this->loadPermittedStates($this->team->team_id, $this->player->user_id);
		$edit = &$_POST['edit'];
		
		if($this->player->status != 'active' && $edit['status'] && $edit['status'] != 'none') {
			error_exit("Inactive players may only be removed from a team.  Please contact this player directly to have them activate their account.");
		}

		if( $edit['step'] == 'perform' ) {
				if($this->perform($edit)) {
					local_redirect(url("team/view/" . $this->team->team_id));
				} else {
					return false;
				}
		} else {
				$rc = $this->generateForm();
		}
	
		return $rc;
	}

	function generateForm () 
	{
		$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, $this->title => 0));
	
		$output .= form_hidden('edit[step]', 'perform');
		
		$output .= para("You are attempting to change player status for <b>" . $this->player->fullname . "</b> on team <b>" . $this->team->name . "</b>.");
		
		$output .= para("Current status: <b>" . $this->positions[$this->currentStatus] . "</b>");

		$options = "";
		foreach($this->permittedStates as $state) {
			$options .= form_radio($this->positions[$state], 'edit[status]', $state);
		}
		reset($this->permittedStates);

		$output .= para("Choices are:<br />$options");

		$output .= para( form_submit('submit') . form_reset('reset') );

		return form($output);
	}

	function perform ( $edit )
	{
		global $session;
		
		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		if( ! in_array($edit['status'], $this->permittedStates) ) {
			error_exit("You do not have permission to set that status.");
		}

		/* Perms already checked, so just do it */
		if($this->currentStatus != 'none') {
			switch($edit['status']) {
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				db_query("UPDATE teamroster SET status = '%s' WHERE team_id = %d AND player_id = %d", $edit['status'], $this->team->team_id, $this->player->user_id);
				break;
			case 'none':
				db_query("DELETE FROM teamroster WHERE team_id = %d AND player_id = %d", $this->team->team_id, $this->player->user_id);
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
			if( 1 != db_affected_rows() ) {
				return false;
			}
		} else {
			switch($edit['status']) {
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				db_query("INSERT INTO teamroster VALUES(%d,%d,'%s',NOW())", $this->team->team_id, $this->player->user_id, $edit['status']);
				if( 1 != db_affected_rows() ) {
					return false;
				}
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
		}

		return true;	
	}
}

class TeamView extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $session;

		// Team names might have HTML in them, so we need to nuke it.
		$team_name = check_form($this->team->name);
		$this->setLocation(array(
			$team_name => "team/view/" . $this->team->team_id,
			"View Team" => 0));

		/* Now build up team data */
		$rows = array();
		if($this->team->website) {
			if(strncmp($this->team->website, "http://", 7) != 0) {
				$this->team->website = "http://" .$this->team->website;
			}
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));

		if($this->team->rank) {
			$rows[] = array("Ranked:", $this->team->rank);
		}
		
		$rows[] = array("Team Status:", $this->team->status);

		/* Spence Balancing Factor:
		 * Average of all score differentials.  Lower SBF means more
		 * evenly-matched games.
		 */
		$teamSBF = $this->team->calculate_sbf( );
		if( $teamSBF ) {
			$league = league_load( array('league_id' => $this->team->league_id) );
			$leagueSBF = $league->calculate_sbf();
			if( $leagueSBF ) {
				$teamSBF .= " (league $leagueSBF)";
			} 
			$rows[] = array("Team SBF:", $teamSBF);
		}
		$rows[] = array("Rating:", $this->team->rating);
		

		$teamdata = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		/* and, grab roster */
		// TODO: turn this into $team->get_roster() 
		$result = db_query(
			"SELECT 
				p.user_id as id,
				CONCAT(p.firstname, ' ', p.lastname) as fullname,
				p.gender,
				p.skill_level,
				p.status AS player_status,
				r.status
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = %d
			ORDER BY r.status, p.gender, p.lastname", $this->team->team_id);
		
		$header = array( 'Name', 'Position', 'Gender','Rating' );
		$rows = array();	
		$totalSkill = 0;
		$count = db_num_rows($result);
		$rosterPositions = getRosterPositions();
		while($player = db_fetch_object($result)) {
	
			/* 
			 * Now check for conflicts.  Players who are subs get
			 * conflicts ignored, but not others.
			 *
			 * TODO: This is time-consuming and resource-inefficient.
			 * TODO: Turn this into $team->check_roster_conflicts()
			 */
			$conflict = db_result(db_query("SELECT COUNT(*) from
					league l, leagueteams t, teamroster r
				WHERE
					l.season = '%s' AND l.day = '%s' 
					AND l.schedule_type != 'none'
					AND l.league_id = t.league_id 
					AND t.team_id = r.team_id
					AND r.player_id = %d",$this->team->league_season,$this->team->league_day,$player->id));
					
			if($conflict > 1) {
				$conflictText = "(roster conflict)";
			} else {
				$conflictText = null;
			}
			
			if($player->player_status == "inactive" ) {
				if($conflictText) {
					$conflictText .= "<br />(account inactive)";
				} else {
					$conflictText .= "(account inactive)";
				}
			}

			$player_name = l($player->fullname, "person/view/$player->id");
			if( $conflictText ) {
				$player_name .= "<div class='roster_conflict'>$conflictText</div>";
			}
			
			if($session->has_permission('team','player status', $this->team->team_id, $player->id) ) {
				$roster_info = l($rosterPositions[$player->status], "team/roster/" . $this->team->team_id . "/$player->id");
			} else {
				$roster_info = $rosterPositions[$player->status];
			}
			
			$rows[] = array(
				$player_name,
				$roster_info,
				$player->gender,
				$player->skill_level
			);

			$totalSkill += $player->skill_level;
		}

		if($count > 0) {
			$avgSkill = sprintf("%.2f", ($totalSkill / $count));
		} else {
			$avgSkill = 'N/A';
		}
		$rows[] = array(
			array('data' => 'Average Skill Rating', 'colspan' => 3),
			$avgSkill
		);
		
		$rosterdata = "<div class='listtable'>" . table($header, $rows) . "</div>";
		
		return table(null, array(
			array( $teamdata, $rosterdata ),
		));
	}
}

class TeamHistory extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $session;

		// Get games
		$games = game_load_many( array('either_team' => $this->team->team_id) );
		if($games) {
			$output = '<pre>';
			foreach($games as $game) {
				if( ! $game->is_finalized() ) {
					continue;
				}
				$rank = '';
				if( $game->home_team == $this->team->team_id ) {
					$rank = $game->home_dependant_rank;
				} else {
					$rank = $game->away_dependant_rank;
				}
				$output .= "$game->game_id $game->game_date:  $rank\n";
			}
			$output .= '<pre>';
		} else {
			$output = 'No Info';
		}
		return $output;
	}
}

/**
 * Team schedule viewing handler
 */
class TeamSchedule extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view schedule', $this->team->team_id);
	}

	function process ()
	{
		global $session;
		$this->title = "Schedule";
		$this->setLocation(array(
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		/*
		 * Grab schedule info 
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this team");
		}

		$header = array(
			"Game",
			"Date",
			"Start",
			"End",
			"Opponent",
			array('data' => "Location",'colspan' => 2),
			array('data' => "Score",'colspan' => 2)
		);
		$rows = array();
			
		$empty_row_added = 0;
		$prev_game_id = 0;
		$countgames = 0;
		$numgames = count($games);
		$update_prev_game_id = 1;
		while(list(,$game) = each($games)) {
			$countgames++;
			$space = '&nbsp;';
			$dash = '-';
			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}

			if ($opponent_name == "") {
				$opponent_name = "(to be determined)";
			} else {
				$opponent_name = l($opponent_name, "team/view/$opponent_id");
			}

			$game_score = $space;
			$score_type = $space;
			
			if($game->is_finalized()) {
				/* Already entered */
				$score_type = '(accepted final)';
				if($game->home_id == $this->team->team_id) {
					$game_score = "$game->home_score - $game->away_score";
				} else {
					$game_score = "$game->away_score - $game->home_score";
				}
			} else {
				/* Not finalized yet, so we will either:
				 *   - display entered score if present
				 *   - display score entry link if game date has passed
				 *   - display a blank otherwise
				 */
				$entered = $game->get_score_entry( $this->team->team_id );
				if($entered) {
					$score_type = '(unofficial, waiting for opponent)';
					$game_score = "$entered->score_for - $entered->score_against";
				} else if($session->has_permission('game','submit score', $game) 
				    && ($game->timestamp < time()) ) {
						$score_type = l("submit score", "game/submitscore/$game->game_id/" . $this->team->team_id);
				} else {
					$score_type = "&nbsp;";
				}
			}
			if($game->status == 'home_default' || $game->status == 'away_default') {
				$score_type .= " (default)";
			}

			// see if you're at the next dependant games (only for ladder!)
			if ( ($numgames - $countgames < 2) && ($game->home_id == "" || $game->away_id == "") ) {
				$update_prev_game_id = 0;
				if (!$empty_row_added) {
					$rows[] = array($dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash);
					$empty_row_added = 1;
				}
				if ($game->home_dependant_game == $prev_game_id) {
					if ($game->home_dependant_type == "winner") {
						$score_type = "<b>(if win $prev_game_id)</b>";
					} else {
						$score_type = "<b>(if lose $prev_game_id)</b>";
					}
				}
				if ($game->away_dependant_game == $prev_game_id) {
					if ($game->away_dependant_type == "winner") {
						$score_type = "<b>(if win $prev_game_id)</b>";
					} else {
						$score_type = "<b>(if lose $prev_game_id)</b>";
					}
				}
			}
			$rows[] = array(
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				$game->game_start,
				$game->game_end,
				$opponent_name,
				l($game->field_code, "field/view/$game->fid"),
				$home_away,
				$game_score,
				$score_type
			);

			if ($update_prev_game_id) {
				$prev_game_id = $game->game_id;
			}
		}
		// add another row of dashes when you're done.
		$rows[] = array($dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash,$dash);

		return "<div class='schedule'>" . table($header,$rows, array('alternate-colours' => true) ) . "</div>";
	}
}

class TeamSpirit extends Handler
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $session;
		$this->title = "Team Spirit";
		
		$this->setLocation(array(
			$this->team->name => "team/spirit/". $this->team->team_id,
			$this->title => 0));

		/*
		 * Grab schedule info 
		 */
		$games = game_load_many( array( 'either_team' => $this->team->team_id, '_order' => 'g.game_date') );

		if( !is_array($games) ) {
			error_exit("There are no games scheduled for this team");
		}

		$header = array();
		if( $session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
			$header = array(
				"ID",
				"Date",
				"Opponent"
			);
		}
		$rows = array();

		# TODO load all point values for answers into array
		$answer_values = array();
		$result = db_query("SELECT akey, value FROM multiplechoice_answers");
		while( $ary = db_fetch_array($result) ) {
			$answer_values[ $ary['akey'] ] = $ary['value'];
		}

		$question_sums = array();
		$num_games = 0;

		while(list(,$game) = each($games)) {
		
			if( ! $game->is_finalized() ) {
				continue;
			}
			
			if($game->home_id == $this->team->team_id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}
			
			$thisrow = array(
				l($game->game_id, "game/view/$game->game_id"),
				strftime('%a %b %d %Y', $game->timestamp),
				l($opponent_name, "team/view/$opponent_id")
			);

			
			# Fetch spirit answers for games
			$entry = $game->get_spirit_entry( $this->team->team_id );
			if( !$entry ) {
				continue;
			}
			while( list($qkey,$answer) = each($entry) ) {
				if( !$num_games ) {
					$header[] = $qkey;
				}
				switch( $answer_values[$answer] ) {
					case -2:
						$thisrow[] = "<img src='/leaguerunner/misc/x.png' />";
						break;
					case -1:
						$thisrow[] = "-";
						break;
					case 0:
						$thisrow[] = "<img src='/leaguerunner/misc/check.png' />";
						break;
					default:
						$thisrow[] = "?";
				}
				$question_sums[ $qkey ] += $answer_values[ $answer ];
			}

			$num_games++;
		
			if( !$session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
				continue;
			}

			$rows[] = $thisrow;
		}
	
		$thisrow = array();
		if( $session->has_permission('league', 'view', $this->team->league_id, 'spirit') ) {
			$thisrow = array(
				"Total","-","-"
			);
		}
		reset($question_sums);
		foreach( $question_sums as $qkey => $answer) {
			$avg = ($answer / $num_games);
			if( $avg < -1.5 ) {
				$thisrow[] = "<img src='/leaguerunner/misc/x.png' />";
			} else if ( $avg < -0.5 ) {
				$thisrow[] = "-";
			} else {
				$thisrow[] = "<img src='/leaguerunner/misc/check.png' />";
			}
		}
		$rows[] = $thisrow;

		return table($header,$rows, array('alternate-colours' => true) );
	}
}

class TeamEmails extends Handler 
{
	function has_permission ()
	{
		global $session;
		return $session->has_permission('team','email',$this->team->team_id);
	}

	function process ()
	{
		$this->title = 'Player Emails';
		$result = db_query(
			"SELECT
				p.firstname, p.lastname, p.email
			FROM 
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = %d",$this->team->team_id);
				
		if( db_num_rows($result) <= 0 ) {
			return false;
		}
		
		$emails = array();
		$nameAndEmails = array();
		while($user = db_fetch_object($result)) {
			$nameAndEmails[] = sprintf("\"%s %s\" &lt;%s&gt;",
				$user->firstname,
				$user->lastname,
				$user->email);
			$emails[] = $user->email;
		}

		$team = team_load( array('team_id' => $this->team->team_id) );

		$this->setLocation(array(
			$team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', 'mailto:' . join(',',$emails)) . " right away.");
	
		$output .= pre(join(",\n", $nameAndEmails));
		return $output;
	}
}

function team_statistics ( )
{
	$rows = array();

	$current_season = variable_get('current_season', 'Summer');

	$result = db_query("SELECT COUNT(*) FROM team");
	$rows[] = array("Number of teams (total):", db_result($result));

	$result = db_query("SELECT l.season, COUNT(*) FROM leagueteams t, league l WHERE t.league_id = l.league_id GROUP BY l.season");
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		$sub_table[] = $row;
	}
	$rows[] = array("Teams by season:", table(null, $sub_table));

	$result = db_query("SELECT t.team_id,t.name, COUNT(r.player_id) as size 
        FROM teamroster r , league l, leagueteams lt
        LEFT JOIN team t ON (t.team_id = r.team_id) 
        WHERE 
                lt.team_id = r.team_id
                AND l.league_id = lt.league_id 
                AND l.schedule_type != 'none' 
				AND l.season = '%s'
                AND (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
        GROUP BY t.team_id 
        HAVING size < 12
        ORDER BY size desc", $current_season);
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		if( $row['size'] < 12 ) {
			$substitutes = db_result(db_query("SELECT COUNT(*) FROM teamroster r WHERE r.team_id = %d AND r.status = 'substitute'", $row['team_id']));
			if( ($row['size'] + floor($substitutes / 3)) < 12 ) {
				$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), ($row['size'] + floor($substitutes / 3)));
			}
		}
	}
	$rows[] = array("$current_season teams with too few players:", table(null, $sub_table));

	$result = db_query("SELECT t.team_id, t.name, t.rating FROM team t, league l, leagueteams lt WHERE lt.team_id = t.team_id AND l.league_id = lt.league_id AND l.schedule_type != 'none' AND l.season = '%s' ORDER BY t.rating DESC LIMIT 10", $current_season);
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Top-rated $current_season teams:", table(null, $sub_table));
	
	$result = db_query("SELECT t.team_id, t.name, t.rating FROM team t, league l, leagueteams lt WHERE lt.team_id = t.team_id AND l.league_id = lt.league_id AND l.schedule_type != 'none' AND l.season = '%s' ORDER BY t.rating ASC LIMIT 10", $current_season);
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		$sub_table[] = array( l($row['name'],"team/view/" . $row['team_id']), $row['rating']);
	}
	$rows[] = array("Lowest-rated $current_season teams:", table(null, $sub_table));

	$result = db_query("SELECT COUNT(*) AS num, IF(s.status = 'home_default',s.home_team,s.away_team) AS team_id FROM schedule s, league l WHERE s.league_id = l.league_id AND l.season = '%s' AND (s.status = 'home_default' OR s.status = 'away_default') GROUP BY team_id ORDER BY num DESC", $current_season);
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top defaulting $current_season teams:", table(null, $sub_table));
	
	$result = db_query("SELECT COUNT(*) AS num, IF(s.approved_by = -3,s.home_team,s.away_team) AS team_id FROM schedule s, league l WHERE s.league_id = l.league_id AND l.season = '%s' AND (s.approved_by = -2 OR s.approved_by = -3) GROUP BY team_id ORDER BY num DESC", $current_season);
	$sub_table = array();
	while($row = db_fetch_array($result)) {
		$team = team_load( array('team_id' => $row['team_id']) );
		$sub_table[] = array( l($team->name,"team/view/" . $row['team_id']), $row['num']);
	}
	$rows[] = array("Top non-score-submitting $current_season teams:", table(null, $sub_table));
	
	
	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group("Team Statistics", $output);
}

?>
