<?php
/*
 * Code for dealing with teams
 */

register_page_handler('team_addplayer', 'TeamAddPlayer');
register_page_handler('team_create', 'TeamCreate');
register_page_handler('team_edit', 'TeamEdit');
register_page_handler('team_list', 'TeamList');
register_page_handler('team_playerstatus', 'TeamPlayerStatus');
register_page_handler('team_standings', 'TeamStandings');
register_page_handler('team_view', 'TeamView');
register_page_handler('team_schedule_view', 'TeamScheduleView');

/**
 * Format roster status as human-readable.
 */
function display_roster_status( $short_form )
{
	switch($short_form) {
	case 'captain':
		return "captain";
	case 'player':
		return "player";
	case 'substitute':
		return "substitute";
	case 'captain_request':
		return "requested by captain";
	case 'player_request':
		return "request to join by player";
	case 'none':
		return "not on team";
	default:
		trigger_error("invalid status: $short_form");
		return "ERROR: invalid status";
	}
}

/**
 * List players for addition to team
 *
 * @todo this is an evil duplication of Person/List.php
 */
class TeamAddPlayer extends Handler
{
	/** 
	 * Initializer for PersonList class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("Add Player");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'deny',
		);

		return true;
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("Team/add_player_list.tmpl");

		$id = var_from_getorpost("id");
		$letter = var_from_getorpost("letter");
		$letters = $DB->getCol("SELECT DISTINCT UPPER(SUBSTRING(lastname,1,1)) as letter from person ORDER BY letter asc");
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
		
		$letter = strtoupper($letter);
		$this->tmpl->assign("letter", $letter);
		
		$this->tmpl->assign("letters", $letters);
		$this->tmpl->assign("list", $found);
		$this->tmpl->assign("id", $id);
		
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		return true;
	}
}

/**
 * Team create handler
 */
class TeamCreate extends TeamEdit
{
	function initialize ()
	{
		$this->set_title("Create New Team");
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website'		=> true,
			'edit_shirt'		=> true,
			'edit_captain' 		=> false,
			'edit_assistant'	=> false,
			'edit_status'		=> true,
		);

		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);

		return true;
	}

	/*
	 * Overridden, as we have no info to put in that form.
	 */
	function generate_form () 
	{
		return true;
	}

	function perform ()
	{
		global $DB, $session;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$team_name = trim(var_from_getorpost("team_name"));
	
		$res = $DB->query("INSERT into team (name,established) VALUES (?, NOW())", array($team_name));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$id = $DB->getOne("SELECT LAST_INSERT_ID() from team");
		if($this->is_database_error($id)) {
			return false;
		}

		$res = $DB->query("INSERT INTO leagueteams (league_id, team_id, status) VALUES(1, ?, 'requested')", array($id));
		if($this->is_database_error($res)) {
			return false;
		}
		
		$res = $DB->query("INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?, ?, 'captain', NOW())", array($id, $session->data['user_id']));
		if($this->is_database_error($res)) {
			return false;
		}

		$this->_id = $id;
		
		return parent::perform();
	}
}

/**
 * Team edit handler
 */
class TeamEdit extends Handler
{
	var $_id;
	
	function initialize ()
	{
		$this->_permissions = array(
			'edit_name'			=> true,
			'edit_website'		=> true,
			'edit_shirt'		=> true,
			'edit_status'		=> true,
		);
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'captain_of:id',
			'deny'
		);
		return true;
	}

	function process ()
	{
		global $DB;
		
		$this->_id = var_from_getorpost('id');
		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Team/edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("Team/edit_form.tmpl");
				$this->tmpl->assign("page_step", 'confirm');
				$rc = $this->generate_form();
		}
	
		if($this->_id) {
			$this->set_title("Edit Team: ". $DB->getOne("SELECT name FROM team where team_id = ?", array($this->_id)));
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

	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=team_view&id=" . $this->_id);
		}
		return parent::display();
	}
	

	function generate_form ()
	{
		global $DB;

		$row = $DB->getRow(
			"SELECT 
				t.name          AS team_name, 
				t.website       AS team_website,
				t.shirt_colour  AS shirt_colour,
				t.status,
				t.established
			FROM team t WHERE t.team_id = ?", 
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("id", $this->_id);
		
		$this->tmpl->assign("team_website", $row['team_website']);
		$this->tmpl->assign("shirt_colour", $row['shirt_colour']);
		$this->tmpl->assign("status", $row['status']);
		$this->tmpl->assign("established", $row['established']);

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$this->tmpl->assign("team_name", var_from_getorpost('team_name'));
		$this->tmpl->assign("id", $this->_id);
		
		$this->tmpl->assign("team_website", var_from_getorpost('team_website'));
		$this->tmpl->assign("shirt_colour", var_from_getorpost('shirt_colour'));
		$this->tmpl->assign("status", var_from_getorpost('status'));
		$this->tmpl->assign("established", var_from_getorpost('established'));
		return true;
	}

	function perform ()
	{
		global $DB;

		if(! $this->validate_data()) {
			/* Oops... invalid data.  Redisplay the confirmation page */
			$this->set_template_file("Team/edit_form.tmpl");
			$this->tmpl->assign("error_message", $this->error_text);
			$this->tmpl->assign("page_step", 'confirm');
			return $this->generate_form();
		}

		$res = $DB->query("UPDATE team SET name = ?, website = ?, shirt_colour = ?, status = ? WHERE team_id = ?",
			array(
				var_from_getorpost('team_name'),
				var_from_getorpost('team_website'),
				var_from_getorpost('shirt_colour'),
				var_from_getorpost('status'),
				$this->_id,
			)
		);
		
		if($this->is_database_error($res)) {
			trigger_error("Database error");
			return false;
		}
		
		return true;
	}

	function validate_data ()
	{
		$rc = true;
		
		$team_name = var_from_getorpost("team_name");
		if( !validate_name_input($team_name) ) {
			$this->error_text .= "Team name cannot be left blank<br>";
			$rc = false;
		}
		
		$shirt_colour = var_from_getorpost("shirt_colour");
		if( !validate_name_input($shirt_colour) ) {
			$this->error_text .= "Shirt colour cannot be left blank<br>";
			$rc = false;
		}
		
		$team_website = var_from_getorpost("team_website");
		if(validate_nonblank($team_website)) {
			if( ! validate_nonhtml($team_website) ) {
				$this->error_text .= "If you provide a website URL, it must be valid.<br>";
				$rc = false;
			}
		}

		return $rc;
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
		$this->set_title("List Teams");
		$this->_required_perms = array(
			'require_valid_session',
			'allow',
		);

		return true;
	}
	
	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$this->set_template_file("common/generic_list.tmpl");

		$letter = var_from_getorpost("letter");
		$letters = $DB->getCol("select distinct UPPER(SUBSTRING(name,1,1)) as letter from team order by letter asc");
		if($this->is_database_error($letters)) {
			return false;
		}
		
		if(!isset($letter)) {
			$letter = $letters[0];
		}

		$found = $DB->getAll(
			"SELECT 
				name AS value, 
				team_id AS id_val 
			 FROM team 
			 WHERE name LIKE ? ORDER BY name",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'team_view'
			),
		));
		$this->tmpl->assign("page_op", "team_list");
		$this->tmpl->assign("letter", $letter);
		
		$this->tmpl->assign("letters", $letters);
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}

}

/**
 * Player status handler
 */
class TeamPlayerStatus extends Handler
{
	function initialize ()
	{
		$this->set_title("Change Player Status");
		$this->_permissions = array(
			'set_player'            => false,
			'set_substitute'        => false,
			'set_captain_request'   => false,
			'set_player_request'    => false,
			'set_captain'	        => false,
			'set_none'	        => false,
		);

		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id, $player_id, $current_status;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a team ID");
			return false;
		}
		
		$player_id = var_from_getorpost('player_id');
		if(is_null($player_id)) {
			$this->error_text = gettext("You must provide a player ID");
			return false;
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
				$this->error_text = gettext("You cannot change status for that player ID");
				return false;
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
		if(is_null($current_status)) {
			$current_status = 'none';
		}

		return $this->set_permissions_for_transition($is_captain, $is_administrator, $current_status);
	}
	
/*
 * FLow:
 * 	- request comes in.  Ensure it has a $id and $player_id
 * 	- if $session user isn't captain or administrator, override $player_id
 * 	  with their own ID.
 * 	- check if player is already on team.  If not, set current status to
 * 	  'none';
 * 	- check who user is.  Captain and administrator can:
 * 		- 'none' -> 'captain_request'
 * 		- 'player_request' -> 'none', 'player' or 'substitute'
 * 		- 'player' -> 'captain', 'substitute', 'none'
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

	function set_permissions_for_transition($is_captain, $is_administrator, $from_state)
	{
		global $DB, $id;
		/* Assumption: if !($is_captain || $is_administrator) means
		 * that we're dealing with a player attempting to change
		 * their own settings.
		 */
		switch($from_state) {
		case 'captain':
			$this->_permissions['set_none'] = true;
			$this->_permissions['set_player'] = true;
			$this->_permissions['set_substitute'] = true;
			break;
		case 'player':
			if($is_captain || $is_administrator) {
				$this->_permissions['set_captain'] = true;
			}
			$this->_permissions['set_substitute'] = true;
			$this->_permissions['set_none'] = true;
			break;
		case 'substitute':
			if($is_captain || $is_administrator) {
				$this->_permissions['set_captain'] = true;
				$this->_permissions['set_player'] = true;
			}
			$this->_permissions['set_none'] = true;
			break;
		case 'captain_request':
			/* Captains cannot move players from this state,
			 * except to remove them.
			 * Administrators can move anyone, players can move
			 * self.
			 */
			if(!$is_captain) {
				$this->_permissions['set_player'] = true;
				$this->_permissions['set_substitute'] = true;
			}
			if($is_administrator) {
				$this->_permissions['set_captain'] = true;
			}
			$this->_permissions['set_none'] = true;
			break;
		case 'player_request':
			/* Captains and admins can promote, player can only
			 * remove
			 */
			if($is_captain || $is_administrator) {
				$this->_permissions['set_player'] = true;
				$this->_permissions['set_substitute'] = true;
			}
			if($is_administrator) {
				$this->_permissions['set_captain'] = true;
			}
			$this->_permissions['set_none'] = true;
			break;
		case 'none':
			if($is_captain || $is_administrator) {
				$this->_permissions['set_captain_request'] = true;
			} else if ($is_administrator) {
				$this->_permissions['set_captain'] = true;
				$this->_permissions['set_player'] = true;
				$this->_permissions['set_substitute'] = true;
				$this->_permissions['set_captain_request'] = true;
				$this->_permissions['set_player_request'] = true;
			} else {
				$is_open = $DB->getOne("SELECT status from team where team_id = ?",array($id));
				if($this->is_database_error($is_open)) {
					trigger_error("Database error");
					return false;
				}
				if($is_open == 'closed') {
					$this->error_text = gettext("Sorry, this team is not open for new players to join");
					return false;
				}
				$this->_permissions['set_player_request'] = true;
			}
			break;
		default:
			$this->error_text = "Internal error in player status";
			trigger_error("Player status error");
			return false;	
		}
		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'confirm':
				$this->set_template_file("Team/player_status_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				return $this->perform();
				break;
			default:
				$this->set_template_file("Team/player_status_form.tmpl");
				$this->tmpl->assign("page_step", 'perform');
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
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=team_view&id=$id");
		}
		return parent::display();
	}

	function generate_form () 
	{
		global $DB, $id, $player_id, $current_status;

		$team = $DB->getRow("SELECT name, status FROM team where team_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($team)) {
			trigger_error("Database error");
			return false;
		}

		$player = $DB->getRow("SELECT 
			p.firstname, p.lastname, p.member_id
			FROM person p
			WHERE p.user_id = ?",
			array($player_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($player)) {
			trigger_error("Database error");
			return false;
		}
		
		$this->tmpl->assign("team_name", $team['name']);
		$this->tmpl->assign("player_name", $player['firstname'] . " " . $player['lastname']);
		$this->tmpl->assign("current_status", display_roster_status($current_status));
		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("player_id", $player_id);

		return true;
	}

	function generate_confirm()
	{
		global $DB, $id, $player_id, $current_status;
		if(! $this->validate_data()) {
			return false;
		}
		
		$team = $DB->getRow("SELECT name, status FROM team where team_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($team)) {
			trigger_error("Database error");
			return false;
		}
		
		$player = $DB->getRow("SELECT 
			p.firstname, p.lastname, p.member_id
			FROM person p
			WHERE p.user_id = ? ",
			array($player_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($player)) {
			trigger_error("Database error");
			return false;
		}
		
		$this->tmpl->assign("status", var_from_getorpost('status'));
		$this->tmpl->assign("new_status", display_roster_status(var_from_getorpost('status')));

		$this->tmpl->assign("team_name", $team['name']);
		$this->tmpl->assign("player_name", $player['firstname'] . " " . $player['lastname']);
		$this->tmpl->assign("current_status", display_roster_status($current_status));
		$this->tmpl->assign("id", $id);
		$this->tmpl->assign("player_id", $player_id);
		
		return true;
	}


	function perform ()
	{
		global $session, $DB, $id, $player_id, $current_status;
		if(! $this->validate_data()) {
			return false;
		}

		$status = trim(var_from_getorpost('status'));
		/* Perms already checked, so just do it */
		if($current_status != 'none') {
			switch($status) {
			case 'captain':
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

	function validate_data ()
	{
		global $id, $player_id, $session;

		$err = true;

		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		$status = trim(var_from_getorpost('status'));
		switch($status) {
		case 'captain':
			$err = $this->_permissions['set_captain'];
			break;
		case 'player':
			$err = $this->_permissions['set_player'];
			break;
		case 'substitute':
			$err = $this->_permissions['set_substitute'];
			break;
		case 'captain_request':
			$err = $this->_permissions['set_captain_request'];
			break;
		case 'player_request':
			$err = $this->_permissions['set_player_request'];
			break;
		case 'none':
			$err = $this->_permissions['set_none'];
			break;
		default:
			$err = false;
			$this->error_text = "Invalid status for player";
			trigger_error("invalid status");
		}
		if(false == $err && (strlen($this->error_text) <= 0)) {
			$this->error_text = "You do not have permission to set that status.";
		}

		return $err;
	}
}

/**
 * Team standings view handler
 */
class TeamStandings extends Handler
{
	var $_league_id;
	function initialize ()
	{
		$this->_league_id = null;
		
		$this->_required_perms = array(
			'require_var:id',
			'allow'
		);
		return true;
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$this->_league_id = $DB->getOne("SELECT league_id FROM leagueteams WHERE team_id = ?", array($id));

		if($this->is_database_error($this->_league_id)) {
			$this->error_text .= '<br' . gettext("The team [$id] may not exist");
			return false;
		}

		return true;
	}

	function display()
	{
		return $this->output_redirect("op=league_standings&id=" . $this->_league_id);
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

		$this->set_template_file("Team/view.tmpl");
		
		$row = $DB->getRow("
			SELECT 
				t.team_id, 
				t.name AS team_name, 
				t.website AS team_website, 
				t.status AS team_status, 
				l.name AS league_name, 
				l.tier AS league_tier, 
				l.league_id,
				t.shirt_colour
			FROM 
				leagueteams s 
				LEFT JOIN team t ON (s.team_id = t.team_id) 
				LEFT JOIN league l ON (s.league_id = l.league_id) 
			WHERE s.team_id = ?", 
		array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("That is not a valid team ID");
			return false;
		}

		$this->set_title("View Team: " . $row['team_name']);
		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("team_id", $id);
		if( strncmp($row['team_website'], "http://", 7) != 0 ) {
			$row['team_website'] = "http://" . $row['team_website'];
		}
		$this->tmpl->assign("team_website", $row['team_website']);
		$this->tmpl->assign("team_status", $row['team_status']);
		$this->tmpl->assign("shirt_colour", $row['shirt_colour']);
		
		$this->tmpl->assign("league_name", $row['league_name']);
		$this->tmpl->assign("league_id", $row['league_id']);
		$this->tmpl->assign("league_tier", $row['league_tier']);

		/* and, grab roster */
		$rows = $DB->getAll("
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
			
		if($this->is_database_error($rows)) {
			return false;
		}

		for($i = 0; $i < count($rows); $i++) {
			if($rows[$i]['id'] == $session->attr_get("user_id")) {
				$rows[$i]['allow_status_change'] = true;
				
			}
			$rows[$i]['status'] = display_roster_status($rows[$i]['status']);
		}
		
		$this->tmpl->assign("roster", $rows);

		/* Assign our own user ID */
		$this->tmpl->assign("user_id", $session->attr_get("user_id"));

		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
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
			'allow'
		);

		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		} else if ($type == 'captain') {
			$this->_permissions['submit_score'] = true;
		}
	}

	function process ()
	{
		global $DB;

		$id = var_from_getorpost('id');

		$this->set_template_file("Team/schedule_view.tmpl");
		
		$row = $DB->getRow("
			SELECT
				l.name AS league_name, 
				l.tier,
				l.league_id, 
				t.name AS team_name
		  	FROM
		  		league l, leagueteams lt, team t
			WHERE
		  		l.league_id = lt.league_id 
				AND t.team_id = lt.team_id 
				AND lt.team_id = ? ", 
		array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($row)) {
			return false;
		}

		if(!isset($row)) {
			$this->error_text = gettext("The team [$id] does not exist");
			return false;
		}

		$this->set_title("View Schedule for " . $row['team_name']);

		$this->tmpl->assign("team_name", $row['team_name']);
		$this->tmpl->assign("team_id", $id);
		$this->tmpl->assign("league_name", $row['league_name']);
		$this->tmpl->assign("league_tier", $row['tier']);
		$this->tmpl->assign("league_id", $row['league_id']);

		/*
		 * Grab schedule info 
		 * This select is still evil, but not as evil as it could be.
		 */
		$rows = $DB->getAll(
			'SELECT 
				s.game_id, 
				DATE_FORMAT(s.date_played, "%a %b %d %Y") as game_date,
				TIME_FORMAT(s.date_played,"%l:%i %p") as game_time,
				s.home_team AS home_id, 
				s.away_team AS away_id, 
				s.field_id, 
				s.home_score, 
				s.away_score, 
				h.name AS home_name, 
				a.name AS away_name, 
				f.name AS field_name, 
				s.defaulted 
			FROM schedule s 
				LEFT JOIN team h ON (s.home_team = h.team_id) 
				LEFT JOIN team a ON (s.away_team = a.team_id) 
				LEFT JOIN field_info f ON (s.field_id = f.field_id) 
			WHERE (s.home_team = ? OR s.away_team = ?) 
			ORDER BY s.date_played',
		array($id, $id),
		DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($rows)) {
			return false;
		}

		$games = array();
		$schedule = array();
		while(list(,$this_row) = each($rows)) {
			/* Grab game info.  We will assume that we're away, and correct
			 * for it if we're not
			 */
			$week = array(
				'id' => $this_row['game_id'],
				'date' => $this_row['game_date'],
				'time' => $this_row['game_time'],
				'opponent_id' => $this_row['away_id'],
				'opponent_name' => $this_row['away_name'],
				'field_id' => $this_row['field_id'],
				'field_name' => $this_row['field_name'],
				'home_away' => 'home'
			);
			/* now, fix it */
			if($this_row['away_id'] == $id) {
				$week['opponent_id'] = $this_row['home_id'];
				$week['opponent_name'] = $this_row['home_name'];
			}

			/* Now, look for a score entry */
			if(!(is_null($this_row['home_score']) || is_null($this_row['away_score']))) {
				/* Already entered */
				$week['score_type'] = 'final';
				if($week['home_away'] == 'home') {
					$week['score_us'] = $this_row['home_score'];
					$week['score_them'] = $this_row['away_score'];
				} else {
					$week['score_us'] = $this_row['away_score'];
					$week['score_them'] = $this_row['home_score'];
				}
			} else {
				/* Not finalized yet */
				$score = $DB->getRow(
					"SELECT
						score_for,
						score_against
					FROM
						score_entry
					WHERE
						game_id = ?
						AND team_id = ?",
				array($this_row['game_id'], $id),
				DB_FETCHMODE_ASSOC);
				
				if($this->is_database_error($score) ) {
					return false;
				}
				if(!is_null($score)) {
					$week['score_type'] = 'entered';
					$week['score_us'] = $score['score_for'];
					$week['score_them'] = $score['score_against'];
				}
				
			}
			$schedule[] = $week;
		}
		
		$this->tmpl->assign("schedule", $schedule);

		/* ... and set permissions flags */
		while(list($key,$val) = each($this->_permissions)) {
			if($val) {
				$this->tmpl->assign("perm_$key", true);
			}
		}

		return true;
	}
}

?>
