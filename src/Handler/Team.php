<?php
/*
 * Code for dealing with teams
 */

register_page_handler('team_addplayer', 'TeamAddPlayer');
register_page_handler('team_create', 'TeamCreate');
register_page_handler('team_edit', 'TeamEdit');
register_page_handler('team_list', 'TeamList');
register_page_handler('team', 'TeamList');
register_page_handler('team_playerstatus', 'TeamPlayerStatus');
register_page_handler('team_standings', 'TeamStandings');
register_page_handler('team_view', 'TeamView');
register_page_handler('team_schedule_view', 'TeamScheduleView');
register_page_handler('team_emails', 'TeamEmails');

class TeamAddPlayer extends Handler
{
	function initialize ()
	{
		$this->title = "Add Player";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'deny',
		);

		$this->op = 'team_addplayer';
		$this->section = 'team';

		return true;
	}

	function process ()
	{
		global $DB;
		
		$id = var_from_getorpost("id");
		$letter = var_from_getorpost("letter");
		
		$ops = array(
			array(
				'name' => 'view', 'target' => 'op=person_view&id='
			),
			array(
				'name' => 'request player', 'target' => "op=team_playerstatus&id=$id&step=perform&status=captain_request&player_id="
			)	
		);
		
        $query = $DB->prepare("SELECT 
			CONCAT(lastname,', ',firstname) AS value, user_id AS id 
			FROM person WHERE (class = 'active' OR class = 'volunteer' OR class='administrator') AND lastname LIKE ? ORDER BY lastname, firstname");

		$this->setLocation(array( $this->title => 0));
		
		return $this->generateAlphaList($query, $ops, 'lastname', 'person', $this->op . "&id=$id", $letter);
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
			'allow',
		);

		$this->op = 'team_create';
		$this->section = 'team';

		return true;
	}

	/*
	 * Overridden, as we have no info to put in that form.
	 */
	function getFormData ( $id )
	{
		return array();
	}

	function perform ( $id )
	{
		global $DB, $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$team_name = trim(var_from_getorpost("team_name"));
	
		$res = $DB->query("INSERT into team (name) VALUES (?)", array($team_name));
		$err = isDatabaseError($res);
		if($err != false) {
			if(strstr($err,"already exists: INSERT into team (name) VALUES")) {
				$err = "A team with that name already exists; please go back and try again";
			}
			$this->error_exit($err);
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from team");
		if($this->is_database_error($id)) {
			return false;
		}

		$res = $DB->query("INSERT INTO leagueteams (league_id, team_id) VALUES(1, ?)", array($id));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$res = $DB->query("INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?, ?, 'captain', NOW())", array($id, $session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}

		return parent::perform( $id );
	}
}

/**
 * Team edit handler
 */
class TeamEdit extends Handler
{
	function initialize ()
	{
		$this->title = "Edit Team";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'deny'
		);
		$this->op = "team_edit";
		$this->section = 'team';
		return true;
	}

	function process ()
	{
		global $DB;
		
		$id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( &$id );
				local_redirect("op=team_view&id=$id");
				break;
			default:
				$formData = $this->getFormData( $id );
				$rc = $this->generateForm($id, $formData);
		}
		return $rc;
	}

	function getFormData ( $id )
	{
		global $DB;

		$team = $DB->getRow(
			"SELECT 
				name,
				website,
				shirt_colour,
				status
			FROM team WHERE team_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($team)) {
			return false;
		}

		return $team;
	}

	function generateForm ($id, $formData)
	{
		$output = form_hidden("op", $this->op);
		$output .= form_hidden("step", 'confirm');
		$output .= form_hidden("id", $id);
		$output .= "<table border='0'>";
		$output .= simple_row("Team Name:", form_textfield('', 'team_name', $formData['name'], 35,200, "The full name of your team.  Text only, no HTML"));
		$output .= simple_row("Website:", form_textfield('', 'team_website', $formData['website'], 35,200, "Your team's website (optional)"));
		$output .= simple_row("Shirt Colour:", form_textfield('', 'shirt_colour', $formData['shirt_colour'], 35,200, "Shirt colour of your team.  If you don't have team shirts, pick 'light' or 'dark'"));
		$output .= simple_row("Team Status:", 
			form_select("", "status", $formData['status'], getOptionsFromEnum('team','status'), "Is your team open (others can join) or closed (only captain can add players)"));

		$output .= "</table>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		if($formData['name']) {
			$this->setLocation(array(
				$formData['name']  => "op=team_view&id=$id",
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => "op=" . $this->op));
		}

		return form($output);
	}

	function generateConfirm ( $id )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$team_name = var_from_getorpost('team_name');
		$team_website = var_from_getorpost('team_website');
		$shirt_colour = var_from_getorpost('shirt_colour');
		$status = var_from_getorpost('status');

		$output = para("Confirm that the data below is correct and click'Submit'  to make your changes");
		$output .= form_hidden("op", $this->op);
		$output .= form_hidden("step", 'perform');
		$output .= form_hidden("id", $id);
		
		$output .= "<table border='0'>";
		$output .= simple_row("Team Name:", form_hidden('team_name',$team_name) .  $team_name);
		$output .= simple_row("Website:", form_hidden('team_website',$team_website) .  $team_website);
		$output .= simple_row("Shirt Colour:", form_hidden('shirt_colour',$shirt_colour) .  $shirt_colour);
		$output .= simple_row("Team Status:", form_hidden('status',$status) .  $status);
		$output .= "</table>";
		$output .= para(form_submit("submit"));
		
		if($team_name) {
			$this->setLocation(array(
				$team_name  => "op=team_view&id=$id",
				$this->title => 0));
		} else {
			$this->setLocation(array( $this->title => "op=" . $this->op));
		}
		
		return form($output);
	}

	function perform ( $id )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$res = $DB->query("UPDATE team SET name = ?, website = ?, shirt_colour = ?, status = ? WHERE team_id = ?",
			array(
				var_from_getorpost('team_name'),
				var_from_getorpost('team_website'),
				var_from_getorpost('shirt_colour'),
				var_from_getorpost('status'),
				$id,
			)
		);
		
		$err = isDatabaseError($res);
		if($err != false) {
			if(strstr($err,"uplicate entry ")) {
				$err = "A team with that name already exists; please go back and try again";
			}
			$this->error_exit($err);
		}
		
		return true;
	}

	function isDataInvalid ()
	{
		$errors = "";

		$team_name = var_from_getorpost("team_name");
		if( !validate_nonhtml($team_name) ) {
			$errors .= "<li>You must enter a valid team name";
		}
		
		$shirt_colour = var_from_getorpost("shirt_colour");
		if( !validate_nonhtml($shirt_colour) ) {
			$errors .= "<li>Shirt colour cannot be left blank";
		}
		
		$team_website = var_from_getorpost("team_website");
		if(validate_nonblank($team_website)) {
			if( ! validate_nonhtml($team_website) ) {
				$errors .= "<li>If you provide a website URL, it must be valid.";
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
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_permissions = array(
			'delete' => false,
			'create' => true,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow',
		);
		$this->op = 'team_list';
		$this->section = 'team';
		$this->setLocation(array("List Teams" => 'op=' . $this->op));
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
		global $DB;

		$letter = var_from_getorpost("letter");
		
		$query = $DB->prepare("SELECT 
			name AS value, team_id AS id
			FROM team WHERE name LIKE ? ORDER BY name");
		
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'op=team_view&id='
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'op=team_delete&id='
			);
		}
		$output = "";
		if($this->_permissions['create']) {
			$output .= blockquote(l("Create New Team", "op=team_create"));
		}
		
		$output .= $this->generateAlphaList($query, $ops, 'name', 'team', $this->op, $letter);
		return $output;
	}
}

/**
 * Player status handler
 */
class TeamPlayerStatus extends Handler
{
	function initialize ()
	{
		$this->title = "Change Player Status";

		$this->positions = getRosterPositions();

		$this->op = 'team_playerstatus';
		$this->section = 'team';

		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id, $player_id, $current_status;

		if(!$session->is_valid()) {
			$this->error_exit("You do not have a valid session");
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_exit("You must provide a team ID");
		}
		
		$player_id = var_from_getorpost('player_id');
		if(is_null($player_id)) {
			$this->error_exit("You must provide a player ID");
		}
	
		$is_captain = false;
		$is_administrator = false;
		
		if($session->attr_get('class') == 'administrator') {
			$is_administrator = true;
		}
		
		if($session->is_captain_of($id)) {  
			$is_captain = true;
		}

		/* Ordinary player can only set things for themselves */
		if(!($is_captain  || $is_administrator)) {
			$allowed_id = $session->attr_get('user_id');
			if($allowed_id != $player_id) {
				$this->error_exit("You cannot change status for that player ID");
			}
		}
		
		/* Now, check for the player's status, or set 'none' if
		 * not currently on team.
		 */
		$current_status = $DB->getOne("SELECT status FROM teamroster WHERE team_id = ? and player_id = ?",
			array($id, $player_id));
		if($this->is_database_error($current_status)) {
			trigger_error("Database error");
			return false;
		}
		if(! isset($current_status)) {
			$current_status = 'none';
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
			array_splice($this->permittedStates, array_search($current_status, $this->permittedStates), 1);

		} else if ($is_captain) {
			$this->permittedStates = $this->getStatesForCaptain($id, $current_status);
		} else {
			$this->permittedStates = $this->getStatesForPlayer($id, $current_status);
		}

		return true;
	}
	
	function getStatesForCaptain($id, $curState)
	{
		global $DB;

		switch($curState) {
		case 'captain':

			$num_captains = $DB->getOne("SELECT COUNT(*) FROM teamroster where status = 'captain' AND team_id = ?", array($id));
			if($this->is_database_error($num_captains)) {
				return false;
			}
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

	function getStatesForPlayer($id, $curState)
	{
		global $DB;

		switch($curState) {
		case 'captain':
			$num_captains = $DB->getOne("SELECT COUNT(*) FROM teamroster where status = 'captain' AND team_id = ?", array($id));
			if($this->is_database_error($num_captains)) {
				return false;
			}
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
			$is_open = $DB->getOne("SELECT status from team where team_id = ?",array($id));
			if($this->is_database_error($is_open)) {
				trigger_error("Database error");
				return false;
			}
			if($is_open == 'closed') {
				$this->error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			$this->error_exit("Internal error in player status");
		}
	}
	
	function process ()
	{
		global $id, $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				$this->perform();
				local_redirect("op=team_view&id=$id");
				break;
			default:
				$rc = $this->generateForm();
		}
	
		return $rc;
	}

	function generateForm () 
	{
		/* TODO: These shouldn't all be global variables */
		global $DB, $id, $player_id, $current_status;

		$team = $DB->getRow("SELECT name, status FROM team where team_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($team)) {
			trigger_error("Database error");
			return false;
		}

		$this->setLocation(array(
			$team['name'] => "op=team_view&id=$id",
			$this->title => 0));
		
		$player = $DB->getRow("SELECT 
			p.firstname, p.lastname, p.member_id
			FROM person p
			WHERE p.user_id = ?",
			array($player_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($player)) {
			trigger_error("Database error");
			return false;
		}

		$output = form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('player_id', $player_id);
		
		$output .= "<table border='0'>";
		$output .= tr(
			td(para("You are attempting to change player status for <b>"
				. $player['firstname'] . " " . $player['lastname'] 
				. "</b> on team <b>" . $team['name'] 
				. "</b>."), array('colspan' => 2))
		);
		$output .= simple_row("Current status:", "<b>" . $this->positions[$current_status] . "</b>");

		$options = "";
		foreach($this->permittedStates as $state) {
			$options .= form_radio($this->positions[$state], 'status', $state);
		}
		reset($this->permittedStates);

		$output .= simple_row("Choices are:", $options);

		$output .= "</table>";
		$output .= para( form_submit('submit') . form_reset('reset') );

		return form($output);
	}

	function perform ()
	{
		global $session, $DB, $id, $player_id, $current_status;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid);
		}

		$status = trim(var_from_getorpost('status'));
		/* Perms already checked, so just do it */
		if($current_status != 'none') {
			switch($status) {
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$res = $DB->query("UPDATE teamroster SET status = ? WHERE team_id = ? AND player_id = ?", array($status, $id, $player_id));
				if($this->is_database_error($res)) {
					trigger_error("Database error");
					return false;
				}
				break;
			case 'none':
				$res = $DB->query("DELETE FROM teamroster WHERE team_id = ? AND player_id = ?", array($id, $player_id));
				if($this->is_database_error($res)) {
					trigger_error("Database error");
					return false;
				}
				break;
			}
		} else {
			switch($status) {
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$res = $DB->query("INSERT INTO teamroster VALUES(?,?,?,NOW())", array($id, $player_id, $status));
				if($this->is_database_error($res)) {
					trigger_error("Database error");
					return false;
				}
				break;
			}
		}

		return true;	
	}

	function isDataInvalid ()
	{
		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		$status = trim(var_from_getorpost('status'));
		if( ! in_array($status, $this->permittedStates) ) {
			return "You do not have permission to set that status.";
		}

		return false;
	}
}

/**
 * Team standings view handler
 */
class TeamStandings extends Handler
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_var:id',
			'allow'
		);
		$this->section = 'team';
		return true;
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$league_id = $DB->getOne("SELECT league_id FROM leagueteams WHERE team_id = ?", array($id),DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league_id) || !$league_id) {
			$this->error_exit("There is no team with that ID.");
		}

		local_redirect("op=league_standings&id=$league_id");
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
			'edit_team'	=> false,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'allow'
		);
		$this->title = "View Team";
		$this->section = 'team';
		$this->op = 'team_view';

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
		global $DB, $session;

		$id = var_from_getorpost('id');

		$team = $DB->getRow("
			SELECT 
				t.team_id as id, 
				t.name AS name, 
				t.website AS website, 
				t.status AS status, 
				l.name AS league_name, 
				l.tier AS league_tier, 
				l.day AS league_day, 
				l.season AS league_season, 
				l.league_id,
				t.shirt_colour
			FROM 
				leagueteams s 
				LEFT JOIN team t ON (s.team_id = t.team_id) 
				LEFT JOIN league l ON (s.league_id = l.league_id) 
			WHERE s.team_id = ?", 
		array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($team)) {
			return false;
		}

		if(!isset($team)) {
			$this->error_exit("That is not a valid team ID");
		}

		// Team names might have HTML in them, so we need to nuke it.
		$team_name = check_form($team['name']);
		$this->setLocation(array(
			$team_name => "op=team_view&id=$id",
			$this->title => 0));

		$links = array();
		$links[] = l('schedule and scores', 
			'op=team_schedule_view&id=' . $team['id'], 
			array('title' => 'View schedule and scores'));
		if($team['status'] == 'open') {
			$links[] = l('join team', 
				'op=team_playerstatus&id=' . $team['id'] . "&player_id=" . $session->attr_get('user_id') . "&status=player_request&step=confirm", 
				array('title' => 'Request to join this team'));
		}
		if($this->_permissions['edit_team']) {
			$links[] = l('edit info', 
				'op=team_edit&id=' . $team['id'], 
				array('title' => "Edit team information"));
			$links[] = l('add player', 
				'op=team_addplayer&id=' . $team['id'], 
				array('title' => "Request a player for this team"));
            $links[] = l('player emails',
				'op=team_emails&id=' . $team['id'],
				array('title' => "Get team email addresses"));

		}
		$links[] = l('view standings', 
			'op=league_standings&id=' . $team['league_id'], 
			array('title' => 'View league standings'));


		/* Now build up team data */
		$teamdata = "<table border'0'>";
		if($team['website']) {
			if(strncmp($team['website'], "http://", 7) != 0) {
				$team['website'] = "http://" . $team['website'];
			}
			$teamdata .= simple_row("Website:", l($team['website'], $team['website']));
		}
		$teamdata .= simple_row("Shirt Colour:", check_form($team['shirt_colour']));
		$league_name = $team['league_name'];
		if($team['league_tier']) {
			$league_name .= " Tier " . $team['league_tier'];
		}
		$teamdata .= simple_row("League/Tier:", l($league_name, "op=league_view&id=" . $team['league_id']));
		$teamdata .= simple_row("Team Status:", $team['status']);

		$teamdata .= "</table>";

		/* and, grab roster */
		$roster = $DB->getAll("
			SELECT 
				p.user_id as id,
				CONCAT(p.firstname, ' ', p.lastname) as fullname,
				p.gender,
				p.skill_level,
				r.status
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?
			ORDER BY r.status, p.gender, p.lastname",
			array($id),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($roster)) {
			return false;
		}

		$rosterdata = "<table cellpadding='3' cellspacing='0' border='0'>";
		$rosterdata .= tr(
			td("Team Roster", array('colspan' => 5, 'class' => 'roster_title'))
		);
		$rosterdata .= tr(
			td("Name", array('class' => 'roster_subtitle'))
			. td("Status", array('class' => 'roster_subtitle'))
			. td("Gender", array('class' => 'roster_subtitle'))
			. td("Skill", array('class' => 'roster_subtitle'))
			. td("&nbsp;", array('class' => 'roster_subtitle'))
		);
		$count = count($roster);

		$totalSkill = 0;

		$rosterPositions = getRosterPositions();
		for($i = 0; $i < $count; $i++) {
	
			/* 
			 * Now check for conflicts.  Players who are subs get
			 * conflicts ignored, but not others.
			 *
			 * TODO: This is time-consuming and resource-inefficient.
			 */
			$row_class = 'roster_item';
			if($roster[$i]['status'] != 'substitute') {
				$conflict = $DB->getOne("SELECT COUNT(*) from
						league l, leagueteams t, teamroster r
					WHERE
						l.season = ? AND l.tier = ? AND l.day = ?
						AND l.allow_schedule = 'Y'
						AND l.league_id = t.league_id 
						AND t.team_id = r.team_id
						AND r.player_id = ?
						",array($row['league_season'],$row['league_tier'],$row['league_day'], $roster[$i]['id']));
				if($conflict) {
					$row_class = 'roster_conflict';
				}
			}

			$player_links = array();

			$player_links[] = l('view',
				'op=person_view&id=' . $roster[$i]['id']);
			
			if($this->_permissions['edit_team'] || ($roster[$i]['id'] == $session->attr_get("user_id"))) {
				$player_links[] = l('change status',
					"op=team_playerstatus&id=$id&player_id=" . $roster[$i]['id']);
			}
			
			$rosterdata .= tr(
				td($roster[$i]['fullname'], array( 'class' => $row_class))
				. td($rosterPositions[$roster[$i]['status']], array( 'class' => $row_class))
				. td($roster[$i]['gender'], array( 'class' => $row_class))
				. td($roster[$i]['skill_level'], array( 'class' => $row_class))
				. td(theme_links($player_links), array( 'class' => $row_class))
			);

			$totalSkill += $roster[$i]['skill_level'];
		}

		$rosterdata .= tr(
			td( "Average Skill", array('colspan' => 3, 'class' => 'roster_item'))
			. td( sprintf("%.2f", ($totalSkill / $count)), array('class' => 'roster_item'))
			. td( "&nbsp;", array('class' => 'roster_item')));
		
		$rosterdata .= "</table>";

		$output = blockquote(theme_links($links));
		$output .= "<table border='0'>";
		$output .= tr(
			td($teamdata, array('align' => 'left', 'valign' => 'top'))
			. td($rosterdata, array('align' => 'left', 'valign' => 'top'))
		);
		$output .= "</table>";
		
		return $output;
	}
}

/**
 * Team schedule viewing handler
 */
class TeamScheduleView extends Handler
{
	function initialize ()
	{
		$this->_permissions = array(
			'submit_score'	=> false,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'coordinate_league_containing:id',
			'allow'
		);
		$this->title = "Schedule";
		$this->op = 'team_schedule_view';
		$this->section = 'team';

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
		global $DB;

		$id = var_from_getorpost('id');

		$team = $DB->getRow("SELECT
				lt.league_id, 
				t.name AS team_name
		  	FROM
		  		leagueteams lt, team t
			WHERE
				t.team_id = lt.team_id 
				AND lt.team_id = ? ", 
		array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($team)) {
			return false;
		}
		
		if(!isset($team)) {
			$this->error_exit("That team does not exist");
		}

		$links = array();
		$links[] = l("view team", "op=team_view&id=$id");
		$links[] = l("view league", "op=league_view&id=" . $team['league_id']);
		$links[] = l("view league schedule", "op=league_schedule_view&id=" . $team['league_id']);


		$this->setLocation(array(
			$team['team_name'] => "op=team_view&id=$id",
			$this->title => 0));

		$output = blockquote(theme_links($links));
		$output .= "<table border='0' cellpadding='3' cellspacing='0'>";
		$output .= tr(
			td("Date", array('class' => 'schedule_title'))
			. td("Time", array('class' => 'schedule_title'))
			. td("Opponent", array('class' => 'schedule_title'))
			. td("Location", array('colspan' => 2, 'class' => 'schedule_title'))
			. td("Score", array('colspan' => 2, 'class' => 'schedule_title'))
		);

		/*
		 * Grab schedule info 
		 * This select is still evil, but not as evil as it could be.
		 */
		$result = $DB->query(
			"SELECT 
				s.game_id, 
				DATE_FORMAT(s.date_played, '%a %b %d %Y') as date,
				TIME_FORMAT(s.date_played,'%l:%i %p') as time,
				s.home_team AS home_id, 
				s.away_team AS away_id, 
				s.field_id, 
				f.site_id, 
				s.home_score, 
				s.away_score, 
				h.name AS home_name, 
				a.name AS away_name, 
				CONCAT(t.code,' ',f.num) AS field_code,
				s.defaulted 
			FROM schedule s 
				LEFT JOIN team h ON (s.home_team = h.team_id) 
				LEFT JOIN team a ON (s.away_team = a.team_id) 
				LEFT JOIN field f ON (s.field_id = f.field_id) 
				LEFT JOIN site t ON (t.site_id = f.site_id) 
			WHERE (s.home_team = ? OR s.away_team = ?) 
			ORDER BY s.date_played",
		array($id, $id));
			
		if($this->is_database_error($result)) {
			return false;
		}

		while($game = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
		
			if($game['home_id'] == $id) {
				$opponent_id = $game['away_id'];
				$opponent_name = $game['away_name'];
				$home_away = '(home)';
			} else {
				$opponent_id = $game['home_id'];
				$opponent_name = $game['home_name'];
				$home_away = '(away)';
			}

			$game_score = "&nbsp;";
			$score_type = "&nbsp;";
			
			if(!(is_null($game['home_score']) && is_null($game['away_score']))) {
				/* Already entered */
				$score_type = '(accepted final)';
				if($game['home_id'] == $id) {
					$game_score = $game['home_score']." - ".$game['away_score'];
				} else {
					$game_score = $game['away_score']." - ".$game['home_score'];
				}
			} else {
				/* Not finalized yet */
				$entered = $DB->getRow(
					"SELECT score_for, score_against FROM score_entry WHERE game_id = ? AND team_id = ?",
				array($game['game_id'], $id), DB_FETCHMODE_ASSOC);
				
				if($this->is_database_error($entered) ) {
					return false;
				}
				if(isset($entered)) {
					$score_type = '(unofficial, waiting for opponent)';
					$game_score = $entered['score_for']." - ".$entered['score_against'];
				} else if($this->_permissions['submit_score']) {
					$score_type = l("submit score", "op=game_submitscore&team_id=$id&id=" . $game['game_id']);
				}
			}
			if($game['defaulted'] != 'no') {
				$score_type .= " (default)";
			}

			$output .= tr(
				td($game['date'], array('class' => 'schedule_item'))
				. td($game['time'], array('class' => 'schedule_item'))
				. td(l($opponent_name, "op=team_view&id=$opponent_id"), array('class' => 'schedule_item'))
				. td(l($game['field_code'], "op=site_view&id=". $game['site_id']), array('class' => 'schedule_item'))
				. td($home_away, array('class' => 'schedule_item'))
				. td($game_score, array('class' => 'schedule_item'))
				. td($score_type, array('class' => 'schedule_item'))
			);

		}

		$output .= "</table>";

		return $output;
	}
}

class TeamEmails extends Handler 
{
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'deny',
		);
		$this->title = 'Player Emails';
		$this->op = 'team_emails';
		$this->section = 'team';

		return true;
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$addrs = $DB->getAll("SELECT 
				p.firstname, p.lastname, p.email
			FROM 
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?",
				array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($addrs)) {
			return false;
		}
		if(count($addrs) <= 0) { 
			return false;
		}
		$emails = array();
		$nameAndEmails = array();
		foreach($addrs as $addr) {
			$output .= 
			$nameAndEmails[] = sprintf("\"%s %s\" &lt;%s&gt;",
				$addr['firstname'],
				$addr['lastname'],
				$addr['email']);
			$emails[] = $addr['email'];
		}

		/* Get team info */
		$team = $DB->getRow("SELECT name FROM team WHERE team_id = ?", array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($league)) {
			return false;
		}

		$this->setLocation(array(
			$team['name'] => "op=team_view&id=$id",
			$this->title => 0));

		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', 'mailto:' . join(',',$emails)) . " right away.");
	
		$output .= pre(join(",\n", $nameAndEmails));
		return $output;
	}
}
?>
