<?php
register_page_handler('team_playerstatus', 'TeamPlayerStatus');

/**
 * Player status handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
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

?>
