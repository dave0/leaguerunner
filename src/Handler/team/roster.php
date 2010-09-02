<?php
require_once('Handler/TeamHandler.php');
require_once('Handler/person/search.php');

class team_roster extends TeamHandler
{
	protected $player;

	function __construct ( $id, $player_id = null )
	{
		parent::__construct( $id );
		if( $player_id ) {
			$this->player = Person::load( array('user_id' => $player_id ) );
		}
		$this->template_name = 'pages/team/roster.tpl';
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','player status',$this->team->team_id, $this->player->user_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->team->name} &raquo; Roster Status";

		if( $this->team->roster_deadline > 0 &&
			!$lr_session->is_admin() &&
			time() > $this->team->roster_deadline )
		{
			error_exit( 'The roster deadline has passed.' );
		}

		if( !$this->player ) {
			$this->title = "{$this->team->name} &raquo; Add Player";

			if( !($lr_session->is_admin() || $lr_session->is_captain_of($this->team->team_id))) {
				error_exit("You cannot add a person to that team!");
			}

			$new_handler = new person_search;
			$new_handler->smarty = &$this->smarty;
			$new_handler->initialize();
			$new_handler->ops['Add to ' . $this->team->name] = 'team/roster/' .$this->team->team_id;
			$new_handler->process();
			$this->template_name = $new_handler->template_name;
			return true;
		}

		if(!$this->player->is_player()) {
			error_exit('Only registered players can be added to a team.');
		}

		$this->positions       = Team::get_roster_positions();
		$this->currentStatus   = $this->team->get_roster_status($this->player->user_id);
		$this->permittedStates = $this->permitted_roster_states();

		$edit = &$_POST['edit'];

		if($this->player->status != 'active' && $edit['status'] && $edit['status'] != 'none') {
			error_exit("Inactive players may only be removed from a team.  Please contact this player directly to have them activate their account.");
		}

		if( $edit['step'] != 'perform' ) {
			$this->smarty->assign('current_status', $this->positions[$this->currentStatus]);
			$this->smarty->assign('player', $this->player);
			$this->smarty->assign('team', $this->team);
			$this->smarty->assign('states', $this->permittedStates);
			return true;
		}

		if( ! array_key_exists($edit['status'], $this->permittedStates) ) {
			error_exit("You do not have permission to set that status.");
		}

		if( ! $this->team->set_roster_status( $this->player->user_id, $edit['status'], $this->currentStatus ) ) {
			error_exit("Could not set roster status for {$this->player->fullname}");
		}

		// Send an email, if configured
		$this->sendInvitation ($edit['status'], $this->player);

		local_redirect(url("team/view/" . $this->team->team_id));
		return true;
	}

	/*
	 * Sets the permissions for a state change.
	 *
	 * Administrator can set any roster state (except the current one)
	 *
	 * Captain can transition from:
	 *	- 'none' -> 'captain_request'
	 * 	- 'player_request' -> 'none', 'player' or 'substitute'
	 * 	- 'player' -> 'captain', 'substitute', 'none'
	 * 	- 'substitute' -> 'captain', 'player', 'none'
	 * 	- 'captain' -> 'player', 'substitute', 'none'
	 * Players can (for their own player_id):
	 *   	- 'none' -> 'player_request'
	 *   	- 'captain_request' -> 'none', 'player' or 'substitute'
	 *   	- 'player_request' -> 'none'
	 *   	- 'player' -> 'substitute', 'none'
	 *   	- 'substitute' -> 'none'
	 */
	function permitted_roster_states ()
	{
		global $lr_session;

		$state_names = array();
		if($lr_session->attr_get('class') == 'administrator') {
			$state_names = $this->team->getStatesForAdministrator($this->currentStatus);
		} elseif($lr_session->is_captain_of($this->team->team_id)) {
			$state_names = $this->team->getStatesForCaptain($this->currentStatus);
		} else {
			/* Ordinary player can only set things for themselves */
			$allowed_id = $lr_session->attr_get('user_id');
			if($allowed_id != $this->player->user_id) {
				error_exit("You cannot change status for that player ID");
			}

			$state_names = $this->team->getStatesForPlayer($this->currentStatus);
		}

		$states = array();
		foreach($state_names as $state) {
			// can't change to current value, so remove from list if present
			if( $state == $this->currentStatus ) {
				continue;
			}
			$states[$state] = $this->positions[$state];
		}

		return $states;
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

				$rc = send_mail($player,
					false, // from the administrator
					false, // no Cc
					_person_mail_text('captain_request_subject', $variables),
					$message);
				if($rc == false) {
					error_exit("Error sending email to " . $player->email);
				}
			}
			else if( $status == 'player_request') {

				// TODO: $team->get_captains()/get_assistants()
				$sth = $dbh->prepare(
					"SELECT firstname, lastname, email, r.status
					 FROM   person p
						LEFT JOIN teamroster r ON (p.user_id = r.player_id)
					 WHERE  team_id = ?
					        AND (r.status = 'captain' OR r.status = 'assistant')");
				$sth->execute( array( $this->team->team_id) );

				$captains = array();
				$assistants = array();
				while( $p = $sth->fetchObject('Person') ) {
					if( $p->status == 'captain' ) {
						$captains[] = $p;
					} else {
						$assistants[] = $p;
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

				$rc = send_mail($captains,
					false, // from the administrator
					$assistants,
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
