<?php
/*
 * Code for dealing with teams
 */
function team_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'create':
			return new TeamCreate;
		case 'edit':
			return new TeamEdit;
		case 'view':
			return new TeamView;
/*
		case 'delete':
			return new TeamDelete; // TODO: CREATE 
 */
		case 'list':
			return new TeamList;
		case 'roster':
			return new TeamRosterStatus;
		case 'schedule':
			return new TeamSchedule;
		case 'emails':
			return new TeamEmails;
	}
	return null;
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
			## TODO: permissions hack must die!
			if( $session->is_captain_of($team->team_id) ) {
				$this->_permissions['edit_team'] = true;
			}
			team_add_to_menu($this, $team);
		}
		reset($session->user->teams);
	}
}

/**
 * Add view/edit/delete links to the menu for the given team
 * TODO: when permissions are fixed, remove the evil passing of $this
 * TODO: fix ugly evil things like TeamEdit so that this can be called to add
 * team being edited to the menu.
 */
function team_add_to_menu( $this, &$team, $parent = 'team' ) 
{
	global $session;
	
	menu_add_child($parent, $team->name, $team->name, array('weight' => -10, 'link' => "team/view/$team->team_id"));
	menu_add_child($team->name, "$team->name/standings",'standings', array('weight' => -1, 'link' => "league/standings/$team->league_id"));
	menu_add_child($team->name, "$team->name/schedule",'schedule', array('weight' => -1, 'link' => "team/schedule/$team->team_id"));

	if( ! array_key_exists( $team->team_id, $session->user->teams ) ) {
		if($team->status != 'closed') {
			menu_add_child($team->name, "$team->name/join",'join team', array('weight' => 0, 'link' => "team/roster/$team->team_id/" . $session->attr_get('user_id')));
		}
	} 
	
	if($this->_permissions['edit_team']) {
		menu_add_child($team->name, "$team->name/edit",'edit team', array('weight' => 1, 'link' => "team/edit/$team->team_id"));
		menu_add_child($team->name, "$team->name/emails",'player emails', array('weight' => 2, 'link' => "team/emails/$team->team_id"));
		menu_add_child($team->name, "$team->name/add",'add player', array('weight' => 0, 'link' => "team/roster/$team->team_id"));
	}
		
	if($this->_permissions['delete_team']) {
		menu_add_child($team->name, "$team->name/delete",'delete team', array('weight' => 1, 'link' => "team/delete/$team->team_id"));
	}
}	


/**
 * Team create handler
 */
class TeamCreate extends TeamEdit
{
	function initialize ()
	{
		$this->title = "Create Team";

		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'allow',
		);
		return true;
	}
	
	function process ()
	{
		$id = -1;
		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->perform( &$id, $edit);
				local_redirect(url("team/view/$id"));
				break;
			default:
				$rc = $this->generateForm($id, array());
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ( $id, $edit = array() )
	{
		global $session;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$team_name = trim($edit['name']);
		
		if(db_num_rows(db_query("SELECT name FROM team WHERE name = '%s'",$team_name))) {
			$err = "A team with that name already exists; please go back and try again";
			$this->error_exit($err);
		}

		db_query("INSERT into team (name) VALUES ('%s')", $team_name);
		if( 1 != db_affected_rows() ) {
			return false;
		}
	
		$id = db_result(db_query("SELECT LAST_INSERT_ID() from team"));

		db_query("INSERT INTO leagueteams (league_id, team_id) VALUES(1, %d)", $id);
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		db_query("INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(%d, %d, 'captain', NOW())", $id, $session->attr_get('user_id'));
		if( 1 != db_affected_rows() ) {
			return false;
		}

		return parent::perform( $id, $edit );
	}
}

/**
 * Team edit handler
 */
class TeamEdit extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'edit_team'	  => false,
			'delete_team' => false,
		);
		$this->title = "Edit Team";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'captain_of',
			'deny'
		);
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if ($type == 'captain') {
			$this->_permissions['edit_team'] = true;
		}
	}

	function process ()
	{
		$id = arg(2);
		$edit = &$_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $id, $edit );
				break;
			case 'perform':
				$this->perform( $id, $edit);
				local_redirect(url("team/view/$id"));
				break;
			default:
				$edit = $this->getFormData( $id );
				$rc = $this->generateForm($id, $edit);
		}
		$this->setLocation(array($edit['name']  => "team/view/$id", $this->title => 0));
		return $rc;
	}

	function getFormData ( $id )
	{
		$team = team_load( array('team_id' => $id) );
		return object2array($team);
	}

	function generateForm ($id, $formData)
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

	function generateConfirm ( $id, $edit = array() )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
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

	function perform ( $id, $edit = array())
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$rc = db_query("UPDATE team SET name = '%s', website = '%s', shirt_colour = '%s', status = '%s' WHERE team_id = %d",
				$edit['name'],
				$edit['website'],
				$edit['shirt_colour'],
				$edit['status'],
				$id
		);

		return ($rc != false);
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
 * Team list handler
 */
class TeamList extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'delete' => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'allow',
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
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'team/view/'
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'team/delete/'
			);
		}
		
		$query = "SELECT name AS value, team_id AS id FROM team WHERE name LIKE '%s%%' ORDER BY name";
		
		$this->setLocation(array("List Teams" => 'team/list'));
		return $this->generateAlphaList($query, $ops, 'name', 'team', 'team/list', $_GET['letter']);
	}
}

/**
 * Player status handler
 */
class TeamRosterStatus extends Handler
{
	function initialize ()
	{
		$this->title = "Roster Status";
		
		$this->positions = getRosterPositions();
		$this->currentStatus = null;
		
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'captain_of',
			'allow'
		);
		return true;
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
				$this->error_exit("You cannot change status for that player ID");
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
				$this->error_exit("All teams must have at least one player with captain status.");
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
			$this->error_exit("Internal error in player status");
		}
	}

	function getStatesForPlayer($id)
	{
		switch($this->currentStatus) {
		case 'captain':
			$num_captains = db_result(db_query("SELECT COUNT(*) FROM teamroster where status = 'captain' AND team_id = %d", $id));
			
			if($num_captains <= 1) {
				$this->error_exit("All teams must have at least one player with captain status.");
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
				$this->error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			$this->error_exit("Internal error in player status");
		}
	}
	
	function process ()
	{
		global $session;
		
		$teamId   = arg(2);
		
		if(!$teamId) {
			$this->error_exit("You must provide a team ID");
		}
		
		$playerId = arg(3);

		if( !$playerId ) {
			if( !($session->is_admin() || $session->is_captain_of($teamId))) {
				$this->error_exit("You cannot add a person to that team!");
			}
			
			$team = team_load( array('team_id' => $teamId) );

			$this->setLocation(array( $team->name => "team/view/$id", $this->title => 0));
			$ops = array(
				array( 'name' => 'view', 'target' => 'person/view/'),
				array( 'name' => 'request player', 'target' => "team/roster/$teamId/")	
			);
	
			$query = "SELECT IF( status != 'active', CONCAT(lastname,', ',firstname, ' (inactive)'), CONCAT(lastname, ', ', firstname)) AS value, user_id AS id FROM person WHERE (class = 'player' OR class ='volunteer' OR class='administrator') AND lastname LIKE '%s%%' ORDER BY lastname, firstname";
        	#$query = "SELECT CONCAT(lastname,', ',firstname) AS value, user_id AS id FROM person WHERE status = 'active' AND (class = 'player' OR class ='volunteer' OR class='administrator') AND lastname LIKE '%s%%' ORDER BY lastname, firstname";

			return
				para("Select the player you wish to add to the team")
				. $this->generateAlphaList($query, $ops, 'lastname', 'person', "team/roster/$teamId", $_GET['letter']);
		}

		$player = person_load( array('user_id' => $playerId) );
		if($player->class != 'player' && $player->class != 'volunteer' && $player->class != 'administrator') {
			$this->error_exit("Only OCUA-registered players can be added to a team");
		}
		
		$this->loadPermittedStates($teamId, $playerId);
		$edit = &$_POST['edit'];
		
		if($player->status != 'active' && $edit['status'] && $edit['status'] != 'none') {
			$this->error_exit("Inactive players may only be removed from a team.  Please contact this player directly to have them activate their account.");
		}

		if( $edit['step'] == 'perform' ) {
				if($this->perform( $teamId, $player, $edit )) {
					local_redirect(url("team/view/$teamId"));
				} else {
					return false;
				}
		} else {
				$rc = $this->generateForm( $teamId, $player );
		}
	
		return $rc;
	}

	function generateForm ( $teamId, &$player ) 
	{
		$team = team_load( array('team_id' => $teamId) );

		$this->setLocation(array( $team->name => "team/view/$id", $this->title => 0));
	
		$output .= form_hidden('edit[step]', 'perform');
		
		$output .= para("You are attempting to change player status for <b>$player->fullname</b> on team <b>$team->name</b>.");
		
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

	function perform ( $teamId, &$player, $edit )
	{
		global $session;
		
		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		if( ! in_array($edit['status'], $this->permittedStates) ) {
			$this->error_exit("You do not have permission to set that status.");
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
				db_query("UPDATE teamroster SET status = '%s' WHERE team_id = %d AND player_id = %d", $edit['status'], $teamId, $player->user_id);
				break;
			case 'none':
				db_query("DELETE FROM teamroster WHERE team_id = %d AND player_id = %d", $teamId, $player->user_id);
				break;
			default:
				$this->error_exit("Cannot set player to that state.");
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
				db_query("INSERT INTO teamroster VALUES(%d,%d,'%s',NOW())", $teamId, $player->user_id, $edit['status']);
				if( 1 != db_affected_rows() ) {
					return false;
				}
				break;
			default:
				$this->error_exit("Cannot set player to that state.");
			}
		}

		return true;	
	}
}

/**
 * Team viewing handler
 */
class TeamView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'edit_team'	  => false,
			'delete_team' => false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'captain_of',
			'allow'
		);
		$this->title = "View Team";
		return true;
	}

	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if ($type == 'captain') {
			$this->_permissions['edit_team'] = true;
		}
	}

	function process ()
	{
		global $session;

		$id = arg(2);

		$team = team_load( array('team_id' => $id) );

		if(!$team) {
			$this->error_exit("That is not a valid team ID");
		}

		// Team names might have HTML in them, so we need to nuke it.
		$team_name = check_form($team->name);
		$this->setLocation(array(
			$team_name => "team/view/$id",
			$this->title => 0));

		/* Now build up team data */
		$rows = array();
		if($team->website) {
			if(strncmp($team->website, "http://", 7) != 0) {
				$team->website = "http://$team->website";
			}
			$rows[] = array("Website:", l($team->website, $team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($team->shirt_colour));
		$rows[] = array("League/Tier:", l($team->league_name, "league/view/$team->league_id"));
		
		$rows[] = array("Team Status:", $team->status);

		/* Spence Balancing Factor:
		 * Average of all score differentials.  Lower SBF means more
		 * evenly-matched games.
		 */
		$teamSBF = $team->calculate_sbf( );
		if( $teamSBF ) {
			$league = league_load( array('league_id' => $team->league_id) );
			$leagueSBF = $league->calculate_sbf();
			if( $leagueSBF ) {
				$teamSBF .= " (league $leagueSBF)";
			} 
			$rows[] = array("Team SBF:", $teamSBF);
		}
		$rows[] = array("Rating:", $team->rating);
		

		$teamdata = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		/* and, grab roster */
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
			ORDER BY r.status, p.gender, p.lastname", $id);
		
		$header = array( array( 'data' => 'Team Roster', 'colspan' => 5));
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
			 */
			$conflict = db_result(db_query("SELECT COUNT(*) from
					league l, leagueteams t, teamroster r
				WHERE
					l.season = '%s' AND l.day = '%s' 
					AND l.allow_schedule = 'Y'
					AND l.league_id = t.league_id 
					AND t.team_id = r.team_id
					AND r.player_id = %d",$team->league_season,$team->league_day,$player->id));
					
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

			if($conflictText) {
				$conflictText = "<div class='roster_conflict'>$conflictText</div>";
			}
			

			$player_links = array( l('view', "person/view/$player->id") );
			if($this->_permissions['edit_team'] || ($player->id == $session->attr_get("user_id"))) {
				$player_links[] = l('change status',
					"team/roster/$id/$player->id");
			}
			
			$rows[] = array(
				$player->fullname.$conflictText,
				$rosterPositions[$player->status],
				$player->gender,
				$player->skill_level,
				theme_links($player_links)
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
			$avgSkill,
			"&nbsp;",
		);
		
		$rosterdata = "<div class='listtable'>" . table($header, $rows) . "</div>";


		team_add_to_menu($this, $team);
		
		return table(null, array(
			array( $teamdata, $rosterdata ),
		));
	}
}

/**
 * Team schedule viewing handler
 */
class TeamSchedule extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'submit_score'	=> false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_player',
			'admin_sufficient',
			'captain_of',
			'coordinate_league_containing',
			'allow'
		);
		$this->title = "Schedule";

		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if ($type == 'coordinator') {
			$this->_permissions['submit_score'] = true;
		} else if ($type == 'captain') {
			$this->_permissions['submit_score'] = true;
		}
	}

	function process ()
	{
		$id = arg(2);

		$team = team_load( array('team_id' => $id) );
		
		if(!$team) {
			$this->error_exit("That team does not exist");
		}

		$this->setLocation(array(
			$team->name => "team/view/$id",
			$this->title => 0));

		/*
		 * Grab schedule info 
		 */
		$result = game_query( array( 'either_team' => $id, '_order' => 'g.game_date') );

		$header = array(
			"Date",
			"Time",
			"Opponent",
			array('data' => "Location",'colspan' => 2),
			array('data' => "Score",'colspan' => 2)
		);
		$rows = array();
			
		while($game = db_fetch_object($result)) {
		
			if($game->home_id == $id) {
				$opponent_id = $game->away_id;
				$opponent_name = $game->away_name;
				$home_away = '(home)';
			} else {
				$opponent_id = $game->home_id;
				$opponent_name = $game->home_name;
				$home_away = '(away)';
			}

			$game_score = "&nbsp;";
			$score_type = "&nbsp;";
			
			if($game->home_score && $game->away_score) {
				/* Already entered */
				$score_type = '(accepted final)';
				if($game->home_id == $id) {
					$game_score = "$game->home_score - $game->away_score";
				} else {
					$game_score = "$game->away_score - $game->home_score";
				}
			} else {
				/* Not finalized yet */
				$entered = db_fetch_array(db_query(
					"SELECT score_for, score_against FROM score_entry WHERE game_id = %d AND team_id = %d",$game->game_id, $id));
				
				if($entered) {
					$score_type = '(unofficial, waiting for opponent)';
					$game_score = $entered['score_for']." - ".$entered['score_against'];
				} else if($this->_permissions['submit_score']) {
					$score_type = l("submit score", "game/submitscore/$game->game_id/$id");
				}
			}
			if($game->defaulted != 'no') {
				$score_type .= " (default)";
			}

			$rows[] = array(
				$game->game_date, 
				$game->game_start,
				l($opponent_name, "team/view/$opponent_id"),
				l($game->field_code, "site/view/$game->site_id"),
				$home_away,
				$game_score,
				$score_type
			);

		}
		team_add_to_menu($this, $team);
		return "<div class='schedule'>" . table($header,$rows, array('alternate-colours' => true) ) . "</div>";
	}
}

class TeamEmails extends Handler 
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'captain_of',
			'deny',
		);
		$this->title = 'Player Emails';
		return true;
	}

	function process ()
	{
		$id = arg(2);

		$result = db_query(
			"SELECT
				p.firstname, p.lastname, p.email
			FROM 
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = %d",$id);
				
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

		$team = team_load( array('team_id' => $id) );

		$this->setLocation(array(
			$team->name => "team/view/$id",
			$this->title => 0));

		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', 'mailto:' . join(',',$emails)) . " right away.");
	
		$output .= pre(join(",\n", $nameAndEmails));
		return $output;
	}
}

?>
