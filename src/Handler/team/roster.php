<?php
require_once('Handler/TeamHandler.php');
require_once('Handler/person/search.php');

class team_roster extends TeamHandler
{
	protected $player;

	function __construct ( $id, $player_id )
	{
		parent::__construct( $id );
		if( $player_id ) {
			$this->player = person_load( array('user_id' => $player_id ) );
		}
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','player status',$this->team->team_id, $this->player->user_id);
	}

	/**
	 * Loads the permittedStates variable, and checks that the session user is
	 * allowed to change the state of the specified player on this team.
	 */
	function loadPermittedStates ($teamId, $playerId)
	{
		global $lr_session, $dbh;

		$is_captain = false;
		$is_administrator = false;

		if($lr_session->attr_get('class') == 'administrator') {
			$is_administrator = true;
		}

		if($lr_session->is_captain_of($teamId)) {
			$is_captain = true;
		}

		/* Ordinary player can only set things for themselves */
		if(!($is_captain  || $is_administrator)) {
			$allowed_id = $lr_session->attr_get('user_id');
			if($allowed_id != $playerId) {
				error_exit("You cannot change status for that player ID");
			}
		}

		/* Now, check for the player's status, or set 'none' if
		 * not currently on team.
		 */
		$sth = $dbh->prepare('SELECT status FROM teamroster WHERE team_id = ? AND player_id = ?');
		$sth->execute( array( $teamId, $playerId) );
		$this->currentStatus = $sth->fetchColumn();

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
		global $dbh;
		switch($this->currentStatus) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster where status = ? AND team_id = ?');
			$sth->execute( array('captain', $id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'coach', 'assistant', 'player', 'substitute');
		case 'coach':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'coach', 'captain', 'player', 'substitute');
		case 'player':
			return array( 'none', 'coach', 'captain', 'assistant', 'substitute');
		case 'substitute':
			return array( 'none', 'coach', 'captain', 'assistant', 'player');
		case 'captain_request':
			/* Captains cannot move players from this state,
			 * except to remove them.
			 */
			return array( 'none' );
		case 'player_request':
			return array( 'none', 'coach', 'captain', 'assistant', 'player', 'substitute');
		case 'none':
			return array( 'captain_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function getStatesForPlayer($id)
	{
		global $dbh;
		switch($this->currentStatus) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster WHERE status = ? AND team_id = ?');
			$sth->execute( array('captain', $id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'coach', 'assistant', 'player', 'substitute');
		case 'coach':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
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
			$sth = $dbh->prepare('SELECT status FROM team WHERE team_id = ?');
			$sth->execute( array( $id ));
			if($sth->fetchColumn() != 'open') {
				error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Roster Status";

		if( $this->team->roster_deadline > 0 &&
			!$lr_session->is_admin() &&
			time() > $this->team->roster_deadline )
		{
			return para( 'The roster deadline has passed.' );
		}
		$this->positions = getRosterPositions();
		$this->currentStatus = null;

		$edit = &$_POST['edit'];

		if( !$this->player ) {
			if( !($lr_session->is_admin() || $lr_session->is_captain_of($this->team->team_id))) {
				error_exit("You cannot add a person to that team!");
			}

			$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, "Add Player" => 0));

			// Handle bulk player adds from previous rosters
			if( array_key_exists ('add', $edit)) {
				$this->processBatch ($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
			} else if( array_key_exists ('team', $edit)) {
				return $this->generatePastRosterForm ($edit);
			}

			$new_handler = new person_search;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->team->name] = 'team/roster/' .$this->team->team_id . '/%d';
			$search = $new_handler->process();

			$team = para('Or select a team from your history below to invite people from that roster.');
			// The "home_team" part of the query is to include only teams that played
			// games, not ones created for tracking playoffs, tournaments, etc.  Not
			// sure if it's the best way to go.
			$team .= form_select('', 'edit[team]', '', getOptionsFromQuery(
				'SELECT t.team_id as theKey, CONCAT(t.name, " (", l.season, " ", l.year, ")") as theValue FROM team t
					LEFT JOIN leagueteams lt ON t.team_id = lt.team_id
					LEFT JOIN league l       ON lt.league_id = l.league_id
					WHERE t.team_id IN
						(SELECT team_id FROM teamroster WHERE player_id = ? AND team_id != ?)
					AND t.team_id IN
						(SELECT DISTINCT home_team FROM schedule)
					AND l.status = "closed"
					ORDER BY t.team_id DESC',
				array($lr_session->user->user_id, $this->team->team_id)
			));
			$team .= form_hidden('edit[step]', 'team');

			$team .= para(form_submit("show roster"));

			return $search . form ($team);
		}

		$this->loadPermittedStates($this->team->team_id, $this->player->user_id);

		if($this->player->status != 'active' && $edit['status'] && $edit['status'] != 'none') {
			error_exit("Inactive players may only be removed from a team.  Please contact this player directly to have them activate their account.");
		}
		if(!$this->player->is_member() && !$lr_session->is_admin()) {
			if(!$this->player->is_player() ) {
				error_exit('Only registered players can be added to a team.');
			} else {
				$he = ($this->player->gender == 'Male' ? 'he' : 'she');
				$his = ($this->player->gender == 'Male' ? 'his' : 'her');
				$him = ($this->player->gender == 'Male' ? 'him' : 'her');
				$mail = l(variable_get('app_admin_name', 'Leaguerunner Administrator'),
							'mailto:' . variable_get('app_admin_email','webmaster@localhost'));
				error_exit("Only registered players can be added to a team. {$this->player->firstname} has yet to register and pay for this year's membership.  Please contact {$this->player->firstname} to remind $him to pay for $his membership.  If $he has registered and paid for $his membership please have $him contact $mail.");
			}
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

	function formPrompt()
	{
		$output = para("You are attempting to change player status for <b>" . $this->player->fullname . "</b> on team <b>" . $this->team->name . "</b>.");
		$output .= para("Current status: <b>" . $this->positions[$this->currentStatus] . "</b>");

		return $output;
	}

	function generateForm ()
	{
		$this->setLocation(array( $this->team->name => "team/view/" . $this->team->team_id, $this->title => 0));

		$output .= form_hidden('edit[step]', 'perform');

		$output .= $this->formPrompt();

		$options = "";
		foreach($this->permittedStates as $state) {
			$options .= form_radio($this->positions[$state], 'edit[status]', $state);
		}
		reset($this->permittedStates);

		$output .= para("Choices are:<br />$options");

		$output .= para( form_submit('submit') . form_reset('reset') );

		return form($output);
	}

	function generatePastRosterForm ( $edit )
	{
		$old_team = team_load( array('team_id' => $edit['team']) );
		if (! $old_team) {
			return error_exit('That team does not exist. Please select a valid team from the list.');
		}
		$old_team->get_roster();
		$this->team->get_roster();

		$form = '';
		$non_members = array();

		foreach ($old_team->roster as $old_player) {
			$present = false;
			foreach ($this->team->roster as $player) {
				if ($player->id == $old_player->id) {
					$present = true;
					break;
				}
			}
			if (!$present) {
				// This is really ugly, but the object returned as part of the roster
				// is incomplete for these purposes.
				$user = person_load (array('user_id' => $old_player->id));
				if ($user->is_member()) {
					$form .= form_checkbox($old_player->fullname, 'edit[add][]', $old_player->id);
				} else {
					$non_members[] = l($old_player->fullname, url("person/view/{$old_player->id}"));
				}
			}
		}

		if (!empty ($form)) {
			$form = para("The following players were on the roster for {$old_team->name} in {$old_team->league_season}, {$old_team->league_year} but are not on your current roster:") .
				form ($form . para( form_submit('invite') ) );
		}

		if (!empty ($non_members)) {
			$form .= para('The following players are not yet registered members for this year and cannot be added to your roster:') . para (implode(', ', $non_members));
		}

		return $form;
	}

	// TODO: Merge common code from here and perform
	function processBatch ( $edit )
	{
		global $dbh;

		/* To have gotten here, the current user is the captain of the team,
		 * so we assume that it's okay to send a captain request to the player.
		 */
		foreach ($edit['add'] as $id) {
			$player = person_load (array('user_id' => $id));
			if ($player->is_member() && !$player->is_player_on ($this->team->team_id)) {
				$sth = $dbh->prepare('INSERT INTO teamroster VALUES(?,?,?,NOW())');
				$sth->execute( array($this->team->team_id, $player->user_id, 'captain_request'));

				// Send an email, if configured
				$this->sendInvitation ('captain_request', $player);
			}
		}
	}

	function perform ( $edit )
	{
		global $lr_session, $dbh;

		/* To be valid:
		 *  - ID and player ID required (already checked by the
		 *    has_permission code)
		 *  - status variable set to a valid value
		 */
		if( ! in_array($edit['status'], $this->permittedStates) ) {
			error_exit("You do not have permission to set that status.");
		}

		/* Perms already checked, so just do it */
		// TODO: this belongs in classes/team.inc
		if($this->currentStatus != 'none') {
			switch($edit['status']) {
			case 'coach':
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$sth = $dbh->prepare('UPDATE teamroster SET status = ? WHERE team_id = ? AND player_id = ?');
				$sth->execute( array($edit['status'], $this->team->team_id, $this->player->user_id) );
				break;
			case 'none':
				$sth = $dbh->prepare('DELETE FROM teamroster WHERE team_id = ? AND player_id = ?');
				$sth->execute( array($this->team->team_id, $this->player->user_id));
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
			if( 1 != $sth->rowCount() ) {
				return false;
			}
		} else {
			switch($edit['status']) {
			case 'coach':
			case 'captain':
			case 'assistant':
			case 'player':
			case 'substitute':
			case 'captain_request':
			case 'player_request':
				$sth = $dbh->prepare('INSERT INTO teamroster VALUES(?,?,?,NOW())');
				$sth->execute( array($this->team->team_id, $this->player->user_id, $edit['status']));
				if( 1 != $sth->rowCount() ) {
					return false;
				}
				break;
			default:
				error_exit("Cannot set player to that state.");
			}
		}

		// Send an email, if configured
		$this->sendInvitation ($edit['status'], $this->player);

		return true;
	}

	function sendInvitation ($status, $player) {
		global $lr_session, $dbh;

		if( variable_get( 'generate_roster_email', 0 ) ) {
			if( $status == 'captain_request') {
				$variables = array(
					'%fullname' => $player->fullname,
					'%userid' => $player->user_id,
					'%captain' => $lr_session->attr_get('fullname'),
					'%teamurl' => url("team/view/{$this->team->team_id}"),
					'%team' => $this->team->name,
					'%league' => $this->team->league_name,
					'%day' => $this->team->league_day,
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_org_name','league'));
				$message = _person_mail_text('captain_request_body', $variables);

				$rc = send_mail($player->email, $player->fullname,
					false, false, // from the administrator
					false, false, // no Cc
					_person_mail_text('captain_request_subject', $variables),
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $player->email);
				}
			}
			else if( $status == 'player_request') {

				// Find the list of captains and assistants for the team
				if( variable_get('postnuke', 0) ) {
					$sth = $dbh->prepare("SELECT
								firstname,
								lastname,
								n.pn_email as email,
								r.status
							FROM
								person p
							LEFT JOIN
								nuke_users n
							ON
								p.user_id = n.pn_uid
							LEFT JOIN
								teamroster r
							ON
								p.user_id = r.player_id
							WHERE
								team_id = ?
							AND
								(
									r.status = 'captain'
								OR
									r.status = 'assistant'
								)");
				} else {
					$sth = $dbh->prepare("SELECT
								firstname,
								lastname,
								email,
								r.status
							FROM
								person p
							LEFT JOIN
								teamroster r
							ON
								p.user_id = r.player_id
							WHERE
								team_id = ?
							AND
								(
									r.status = 'captain'
								OR
									r.status = 'assistant'
								)");
				}
				$sth->execute( array( $this->team->team_id) );

				$captains = array();
				$captain_names = array();
				$assistants = array();
				$assistant_names = array();
				while( $row = $sth->fetch(PDO::FETCH_OBJ) ) {
					if( $row->status == 'captain' ) {
						$captains[] = $row->email;
						$captain_names[] = "$row->firstname $row->lastname";
					} else {
						$assistants[] = $row->email;
						$assistant_names[] = "$row->firstname $row->lastname";
					}
				}

				$variables = array(
					'%fullname' => $player->fullname,
					'%userid' => $player->user_id,
					'%captains' => join(',', $captain_names),
					'%teamurl' => url("team/view/{$this->team->team_id}"),
					'%team' => $this->team->name,
					'%league' => $this->team->league_name,
					'%day' => $this->team->league_day,
					'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
					'%site' => variable_get('app_org_name','league'));
				$message = _person_mail_text('player_request_body', $variables);

				$rc = send_mail($captains, $captain_names,
					false, false, // from the administrator
					$assistants, $assistant_names,
					_person_mail_text('player_request_subject', $variables),
					$message);
				if($rc == false) {
					error_exit("Error sending email to team captains");
				}
			}
		}
	}
}

?>
