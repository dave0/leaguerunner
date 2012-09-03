<?php
class League extends LeaguerunnerObject
{
	var $_teams_loaded;
	var $coordinators;
	var $teams;
	var $events;

	function __construct ( $load_mode = LOAD_RELATED_DATA )
	{
		$this->_teams_loaded = false;
		$this->coordinators = array();
		$this->teams = array();
		$this->events = array();

		/* set derived attributes */
		if($this->tier) {
			$this->fullname = sprintf("$this->name Tier %02d", $this->tier);
		} else {
			$this->fullname = $this->name;
		}

		if( $load_mode == LOAD_OBJECT_ONLY ) {
			return;
		}

		$this->load_coordinators();
		$this->load_events();

		return true;
	}

	function load_coordinators()
	{
		global $dbh;

		// TODO: this should be one query, not a select on leaguemembers and a loop over Person
		$sth = $dbh->prepare('SELECT m.player_id FROM leaguemembers m WHERE m.league_id = ?');
		$sth->execute(array($this->league_id));

		while( $id = $sth->fetchColumn() ) {
			$c_sth = Person::query( array( 'user_id' => $id ) );
			$c = $c_sth->fetchObject('Person', array(LOAD_OBJECT_ONLY));
			$c->coordinator_status = 'loaded';
			$this->coordinators[$c->user_id] = $c;
		}
	}

	/**
	 * Pull linked Registration Events into the League Object
	 */
	function load_events()
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			 r.registration_id AS id, e.name AS name
			 FROM registration_prerequisites r
			 INNER JOIN registration_events e ON (r.registration_id = e.registration_id)
			 WHERE r.league_id = ?");
		$sth->execute(array($this->league_id));

		while($row = $sth->fetch()) {
			$this->events[$row['id']] = $row['name'];
		}
	}


	function get_captains()
	{
		global $dbh;

		// TODO: this should be one query, not a select on leaguemembers and a loop over Person
		$sth = $dbh->prepare("SELECT p.user_id
			FROM leagueteams l, teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				l.league_id = ?
				AND l.team_id = r.team_id
				AND (r.status = 'coach' OR r.status = 'captain' OR r.status = 'assistant')
			ORDER BY
				p.lastname, p.firstname");
		$sth->execute(array($this->league_id));

		$captains = array();
		while( $id = $sth->fetchColumn() ) {
			$c_sth = Person::query( array( 'user_id' => $id ) );
			$captains[] = $c_sth->fetchObject('Person', array(LOAD_OBJECT_ONLY));
		}

		return $captains;
	}

	/**
	* Check if this league contains a particular team.  There are
	* two modes of operation, to take advantage of team data if we
	* already have it.
	*/
	function contains_team( $team_id )
	{
		global $dbh;
		if($this->_teams_loaded) {
			return array_key_exists( $team_id , $this->teams );
		}

		// Otherwise, we have to check directly
		$sth = $dbh->prepare('SELECT team_id FROM leagueteams WHERE league_id = ? AND team_id = ?');
		$sth->execute( array( $this->league_id, $team_id) );

		return ($sth->fetchColumn() == $team_id);
	}

	/**
	* Load teams for this league.
	*/
	function load_teams ()
	{
		if($this->_teams_loaded) {
			return true;
		}

		$this->teams = Team::load_many( array('league_id' => $this->league_id, '_order' => 't.name'));

		// Cheat.  If we didn't find any teams, set $this->teams to an empty
		// array again.
		if( !is_array($this->teams) ) {
			$this->teams = array();
		}

		$this->_teams_loaded = true;
		return true;
	}

	/**
	* Return array of teams, suitable for use in pulldown
	*/
	function teams_as_array ()
	{
		if(!$this->_teams_loaded) {
			$this->load_teams();
		}

		$teams = array();
		while(list($id, $team) = each($this->teams)) {
			$teams[$id] = $team->name;
		}
		reset($this->teams);
		return $teams;
	}

	/**
	* Return true if this league has had any games scheduled.
	*/
	function has_schedule()
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT COUNT(*) from schedule where league_id = ?');
		$sth->execute(array( $this->league_id));
		return ($sth->fetchColumn() > 0);
	}

	/**
	* Return array of rounds, suitable for use in pulldown
	* For now, this is just a braindead list, but in future, it
	* will have playoff semis/quarters/finals available
	*/
	function rounds_as_array()
	{
		$rounds = array();
		for($i = 1; $i <= 5;  $i++) {
			$rounds[$i] = $i;
		}
		return $rounds;
	}

	function add_coordinator ( &$person )
	{
		if( array_key_exists( $person->user_id, $this->coordinators ) ) {
			return false;
		}
		$this->coordinators[$person->user_id] = $person;
		$this->coordinators[$person->user_id]->coordinator_status = 'add';
		return true;
	}

	function remove_coordinator ( &$person )
	{
		if( array_key_exists( $person->user_id, $this->coordinators ) ) {
			$this->coordinators[$person->user_id]->coordinator_status = 'delete';
			return true;
		}
		return false;
	}

	/**
	* Set current_round to a value based on whatever the current game might
	* be.
	*/
	function update_current_round()
	{
		global $CONFIG, $dbh;
		$local_adjust_secs = $CONFIG['localization']['tz_adjust'] * 60;
		$sth = $dbh->prepare("SELECT s.round FROM schedule s, gameslot g WHERE s.league_id = ? AND (NOT ISNULL(s.round)) AND g.game_id = s.game_id AND ( (UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + $local_adjust_secs) < UNIX_TIMESTAMP(NOW())) ORDER BY g.game_date DESC LIMIT 1");
		$sth->execute(array($this->league_id));

		$round = $sth->fetchColumn();
		if( $round != $this->current_round ) {
			$this->set('current_round',$round);
			return $this->save();
		}
		return true;
	}

	/**
	* Finalize any games considered 'old'.
	*/
	function finalize_old_games()
	{
		global $CONFIG, $dbh;
		if( $this->finalize_after == 0 )
			return '';

		$output = '';

		// TODO: Game::load_many()?  Or at least Game::query()?
		$sth = $dbh->prepare("SELECT
				DISTINCT s.game_id,
				(UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + ?) AS start_timestamp
			FROM
				schedule s,
				gameslot g
			WHERE
				s.league_id = ?
			AND
				g.game_id = s.game_id
			AND
				(UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + ?) < UNIX_TIMESTAMP(NOW())
			AND
				(ISNULL(s.home_score) OR ISNULL(s.away_score))
			ORDER BY
				g.game_id");

		$local_adjust_secs = $CONFIG['localization']['tz_adjust'] * 60;
		$sth->execute( array(
			$local_adjust_secs,
			$this->league_id,
			$local_adjust_secs + ($this->finalize_after * 60) * 60
		));

		while( $game_id = $sth->fetchColumn() ) {
			$game = Game::load( array('game_id' => $game_id) );
			if ($game->finalize())
			{
				$stat = 'Finalized';
			}
			else
			{
				$stat = 'DID NOT finalize';
			}
			$output .= "<p>$stat $game->game_date-$game->game_start game " .
				l($game_id, "game/edit/$game_id") .
				' between ' .
				l($game->home_name, "team/view/$game->home_team") .
				' and ' .
				l($game->away_name, "team/view/$game->away_team") .
				", status {$game->status}</p>";
		}

		return $output;
	}

	# TODO: add_team and remove_team, same as add and remove coordinator.

	function save ()
	{
		global $dbh;

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create league");
			}
		}

		if( count($this->_modified_fields) > 0 ) {
			$fields      = array();
			$fields_data = array();

			foreach ( $this->_modified_fields as $key => $value) {
				$fields[] = "$key = ?";
				if( empty($this->{$key}) ) {
					$fields_data[] = null;
				} else {
					$fields_data[] = $this->{$key};
				}
			}

			if(count($fields_data) != count($fields)) {
				error_exit('Internal error: Incorrect number of fields set');
			}

			$fields_data[] = $this->league_id;

			$sth = $dbh->prepare( 'UPDATE league SET '
				. join(', ', $fields)
				. ' WHERE league_id = ?');

			$test = $sth->execute( $fields_data );

			/** TODO
			 * PHP 5.2 returns 0 on a successful UPDATE query that doesn't modify anything.
			 * At minimum, the update sets capt & coord lists to null.

			if($sth->rowCount() < 1) {
				$err = $sth->errorInfo();
				error_exit("Error: database not updated: $err[2]");
			}
			*/
		}

		$add_sth = $dbh->prepare('INSERT INTO leaguemembers (league_id, player_id, status) VALUES (?,?,?)');
		$del_sth = $dbh->prepare('DELETE FROM leaguemembers WHERE league_id = ? AND player_id = ?');
		foreach( $this->coordinators as $coord ) {
			switch( $coord->coordinator_status ) {
				case 'add':
					$add_sth->execute( array($this->league_id, $coord->user_id, 'coordinator') );
					$this->coordinators[$coord->user_id]->coordinator_status = 'loaded';
					break;
				case 'delete':
					$del_sth->execute( array($this->league_id, $coord->user_id ));
					unset($this->coordinators[$coord->user_id]);
					break;
				default:
					# Skip if not add or delete.
					break;
			}
		}
		reset($this->coordinators);

		// Execute same process for registration events
		$add_sth = $dbh->prepare('INSERT INTO registration_prerequisites (registration_id, league_id) VALUES (?,?)');
		$del_sth = $dbh->prepare('DELETE FROM registration_prerequisites WHERE registration_id = ? AND league_id = ?');
		foreach( $this->events as $event => $name ) {
			switch( $name ) {
				case "add":
					$add_sth->execute( array($event, $this->league_id) );
					break;
				case "delete":
					$del_sth->execute( array($event, $this->league_id));
					unset($this->events[$event]);
					break;
				default:
					# Skip if not add or delete.
					break;
			}
		}
		reset($this->events);

		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		global $dbh;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->name ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT into league (name) VALUES(?)');
		$sth->execute( array( $this->name) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}
		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM league');
		$sth->execute();
		$this->league_id = $sth->fetchColumn();

		return true;
	}

	function delete()
	{
		if ( ! $this->_in_database ) {
			return false;
		}

		if ( $this->league_id == 1 ) {
			error_exit("Cannot delete the 'Inactive Teams' league");
		}

		$queries = array(
			'UPDATE leagueteams SET league_id = 1 WHERE league_id = ?',
			'DELETE FROM leaguemembers WHERE league_id = ?',
			'DELETE FROM score_entry USING score_entry, schedule WHERE score_entry.game_id = schedule.game_id AND schedule.league_id = ?',
			'DELETE FROM field_report USING field_report, schedule WHERE field_report.game_id = schedule.game_id AND schedule.league_id = ?',
			'DELETE FROM spirit_entry USING spirit_entry,schedule WHERE spirit_entry.gid = schedule.game_id AND schedule.league_id = ?',
			'DELETE FROM gameslot USING gameslot, schedule WHERE gameslot.game_id = schedule.game_id AND schedule.league_id = ?',
			'DELETE FROM league_gameslot_availability WHERE league_id = ?',
			'DELETE FROM schedule WHERE league_id = ?',
			'DELETE FROM league WHERE league_id = ?'
		);

		return $this->generic_delete( $queries, $this->league_id );
	}

	/**
	* Calcluates the SBF "Spence" or "Sutton" Balance Factor.
	* This is the average of all score differentials for games played
	* to-date.  A lower value indicates a more evenly matched league.
	*/
	function calculate_sbf()
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT ROUND(AVG(ABS(s.home_score - s.away_score)), 2) FROM schedule s WHERE s.league_id = ?');
		$sth->execute(array( $this->league_id) );

		$sbf = $sth->fetchColumn();

		if( $sbf == "") {
			$sbf = "n/a";
		}

		return $sbf;
	}

	/**
	* Get all available gameslots for a given date
	* We get any unbooked slots, as well as any currently in use by this set
	* of games.
	* Returns array suitable for use in html select list
	*/
	function get_gameslots( $timestamp )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			s.slot_id AS slot_id,
			IF( f.parent_fid,
				CONCAT_WS(' ', s.game_start, p.name, f.num),
				CONCAT_WS(' ', s.game_start, f.name, f.num)
			) AS value
			FROM gameslot s
				INNER JOIN field f ON (s.fid = f.fid)
				LEFT JOIN field p ON (p.fid = f.parent_fid)
				LEFT JOIN league_gameslot_availability a ON (s.slot_id = a.slot_id)
				LEFT JOIN schedule g ON (s.game_id = g.game_id)
			WHERE
				UNIX_TIMESTAMP(s.game_date) = :timestamp
				AND (
					(a.league_id=:league_id AND ISNULL(s.game_id))
					OR
					g.league_id=:league_id
				)
			ORDER BY s.game_start, value");
		$sth->execute(array('timestamp' => $timestamp, 'league_id' =>  $this->league_id) );

		$gameslots[0] = "---";
		while($slot = $sth->fetch(PDO::FETCH_OBJ) ) {
			$gameslots[$slot->slot_id] = $slot->value;
		}
		return $gameslots;
	}


	/**
	 * Calculate standings for this league
	 * Returns array containing league standings info, ordered from best team
	 * to worst team.
	 */
	function calculate_standings( $args = array() )
	{
		global $dbh;

		if ($args['round'] && $args['round'] != 'all' ) {
			$current_round = $args['round'];
		} else {
			$current_round = 0;
		}

		$this->load_teams();

		if( count($this->teams) < 1 ) {
			error_exit("Cannot generate standings for a league with no teams");
		}

		// Initialise stats for each team
		$season = $this->teams;
		$round  = array();
		while(list($id,) = each($season)) {
			$season[$id]->points_for = 0;
			$season[$id]->points_against = 0;
			$season[$id]->spirit = array();
			$season[$id]->win = 0;
			$season[$id]->loss = 0;
			$season[$id]->tie = 0;
			$season[$id]->defaults_for = 0;
			$season[$id]->defaults_against = 0;
			$season[$id]->games = 0;
			$season[$id]->vs = array();
			$season[$id]->vspm = array();
			$season[$id]->streak = array();
			if( $current_round ) {
				$round[$id]->points_for = 0;
				$round[$id]->points_against = 0;
				$round[$id]->spirit = array();
				$round[$id]->win = 0;
				$round[$id]->loss = 0;
				$round[$id]->tie = 0;
				$round[$id]->defaults_for = 0;
				$round[$id]->defaults_against = 0;
				$round[$id]->games = 0;
				$round[$id]->vs = array();
				$round[$id]->vspm = array();
				$round[$id]->streak = array();
			}
		}

		/* Now, fetch the schedule.  Get all games played by anyone who
		 * is currently in this league, regardless of whether or not
		 * their opponents are still here
		 */
		// TODO: I'd like to use Game::load_many here, but it's too slow.
		$sth = $dbh->prepare('SELECT DISTINCT
			s.*,
			1 as _in_database,
			s.home_team AS home_id,
			h.name AS home_name,
			s.away_team AS away_id,
			a.name AS away_name,
			(hsotg.timeliness + hsotg.rules_knowledge + hsotg.sportsmanship + hsotg.rating_overall + hsotg.score_entry_penalty) AS home_spirit,
			(asotg.timeliness + asotg.rules_knowledge + asotg.sportsmanship + asotg.rating_overall + asotg.score_entry_penalty) AS away_spirit
			FROM leagueteams t, schedule s
				LEFT JOIN team h ON (h.team_id = s.home_team)
				LEFT JOIN team a ON (a.team_id = s.away_team)
				LEFT JOIN gameslot g ON (g.game_id = s.game_id)
				LEFT JOIN spirit_entry hsotg ON (hsotg.tid = s.home_team AND g.game_id = hsotg.gid)
				LEFT JOIN spirit_entry asotg ON (asotg.tid = s.away_team AND g.game_id = asotg.gid)
			WHERE t.league_id = ?
				AND NOT ISNULL(s.home_score)
				AND NOT ISNULL(s.away_score)
				AND (s.home_team = t.team_id
					OR s.away_team = t.team_id)
			ORDER BY g.game_date, g.game_start');
		$sth->execute( array($this->league_id) );

		while( $g = $sth->fetchObject('Game') ) {
			$this->standings_record_game($season, $g);
			if($current_round && $current_round == $g->round) {
				$this->standings_record_game($round, $g);
			}
		}

		/* HACK: Before we sort everything, we've gotta copy the
		 * $season's spirit and games values into the $round array
		 * because otherwise, in any round after the first we're
		 * only sorting on the spirit scores received in the current
		 * round.
		 */
		if( $current_round ) {
			while(list($team_id,$info) = each($season))
			{
				$round[$team_id]->spirit = $info->spirit;
				$round[$team_id]->games = $info->games;
			}
			reset($season);
		}

		// Now, sort it all
		if ($this->schedule_type == "ratings_ladder" || $this->schedule_type == 'ratings_wager_ladder' ) {
			// Call a function that will handle complete sorting of ladder standings:
			$season = $this->sortLadderRating($season);
			$sorted_order = &$season;
		} else {
			if($current_round) {
				uasort($round, array($this, 'standings_sort_bywinloss'));
				$sorted_order = &$round;
			} else {
				uasort($season, array($this, 'standings_sort_bywinloss'));
				$sorted_order = &$season;
			}
		}

		reset($sorted_order);
		return array(array_keys($sorted_order), $season, $round);
	}

	function standings_record_game(&$season, &$game)
	{
		if(isset($season[$game->home_team])) {
			$team = &$season[$game->home_team];

			$team->games++;
			$team->points_for += $game->home_score;
			$team->points_against += $game->away_score;

			$team->spirit[] = $game->home_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->away_team])) {
				$team->vs[$game->away_team] = 0;
			}
			if(!isset($team->vspm[$game->away_team])) {
				$team->vspm[$game->away_team] = 0;
			}

			if($game->status == 'home_default') {
				$team->defaults_against++;
			} else if($game->status == 'away_default') {
				$team->defaults_for++;
			}

			$status = '';
			if($game->home_score == $game->away_score) {
				$team->tie++;
				$team->vs[$game->away_team]++;
				$team->vspm[$game->away_team] += 0;
				$status = 'T';
			} else if($game->home_score > $game->away_score) {
				$team->win++;
				$team->vs[$game->away_team] += 2;
				$team->vspm[$game->away_team] += $game->home_score - $game->away_score;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->away_team] += 0;
				$team->vspm[$game->away_team] += $game->home_score - $game->away_score;
				$status = 'L';
			}
			if(in_array($status, $team->streak)) {
				array_push($team->streak, $status);
			} else {
				$team->streak = array($status);
			}
		}
		if(isset($season[$game->away_team])) {
			$team = &$season[$game->away_team];

			$team->games++;
			$team->points_for += $game->away_score;
			$team->points_against += $game->home_score;

			$team->spirit[] = $game->away_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->home_team])) {
				$team->vs[$game->home_team] = 0;
			}
			if(!isset($team->vspm[$game->home_team])) {
				$team->vspm[$game->home_team] = 0;
			}

			if($game->status == 'away_default') {
				$team->defaults_against++;
			} else if($game->status == 'home_default') {
				$team->defaults_for++;
			}

			$status = '';
			if($game->away_score == $game->home_score) {
				$team->tie++;
				$team->vs[$game->home_team]++;
				$team->vspm[$game->home_team] += 0;
				$status = 'T';
			} else if($game->away_score > $game->home_score) {
				$team->win++;
				$team->vs[$game->home_team] += 2;
				$team->vspm[$game->home_team] += $game->away_score - $game->home_score;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->home_team] += 0;
				$team->vspm[$game->home_team] += $game->away_score - $game->home_score;
				$status = 'L';
			}
			if(in_array($status, $team->streak)) {
				array_push($team->streak, $status);
			} else {
				$team->streak = array($status);
			}
		}
	}

	/**
	* Sort a ladder league by:
	* 1- RATING
	* 2- SOTG
	* 3- WINS/TIES
	* 4- +/-
	* 5- GOALS FOR
	* 6- RANDOM (team id)
	**/
	function standings_sort_rating_ladder (&$a, &$b)
	{
		/* Check Rating */
		if ($a->rating != $b->rating) {
			return ($a->rating > $b->rating) ? -1 : 1;
		}

		$s = new Spirit;

		/* Check SOTG */
		if ( $s->average_sotg($a->spirit, true) > $s->average_sotg($b->spirit, true) ) {
			return -1;
		} elseif ( $s->average_sotg($a->spirit, true) < $s->average_sotg($b->spirit, true) ) {
			return 1;
		}

		/* Check wins & ties */
		$b_points = (( 2 * $b->win ) + $b->tie);
		$a_points = (( 2 * $a->win ) + $a->tie);
		if( $a_points > $b_points ) {
			return -1;
		} else if( $a_points < $b_points ) {
			return 1;
		}

		/* Next, check +/- */
		if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return 1;
		} else if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return -1;
		}

		/* Check goals for */
		if ($a->points_for != $b->points_for) {
			return ($a->points_for > $b->points_for) ? -1 : 1;
		}

		/* Check team id as last resort */
		return ($a->team_id < $b->team_id) ? -1 : 1;
	}

	function standings_sort_bywinloss (&$a, &$b)
	{
		/* First, order by wins */
		$b_points = (( 2 * $b->win ) + $b->tie);
		$a_points = (( 2 * $a->win ) + $a->tie);
		if( $a_points > $b_points ) {
			return -1;
		} else if( $a_points < $b_points ) {
			return 1;
		}

		/* Then, check head-to-head wins */
		if(isset($b->vs[$a->team_id]) && isset($a->vs[$b->team_id])) {
			if( $b->vs[$a->team_id] > $a->vs[$b->team_id]) {
				return 1;
			} else if( $b->vs[$a->team_id] < $a->vs[$b->team_id]) {
				return -1;
			}
		}

		$s = new Spirit;

		/* Check SOTG */
		if ( $s->average_sotg($a->spirit, true) > $s->average_sotg($b->spirit, true) ) {
			return -1;
		} elseif ( $s->average_sotg($a->spirit, true) < $s->average_sotg($b->spirit, true) ) {
			return 1;
		}

		/* Next, check +/- */
		if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return 1;
		} else if( ($b->points_for - $b->points_against) > ($a->points_for - $a->points_against) ) {
			return -1;
		}

		/*
		* Finally, check losses.  This ensures that teams with no record
		* appear above teams who have losses.
		*/
		if( $a->loss < $b->loss ) {
			return -1;
		} else if( $a->loss > $b->loss ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Create a single game in this league
	 */
	function create_empty_game( $datestamp, $should_publish = true )
	{
		global $dbh;

		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		$dbh->beginTransaction();
		$g = new Game;
		$g->set('league_id', $this->league_id);
		$g->set('published', $should_publish);
		if ( ! $g->save() ) {
			if( ! $dbh->rollback() ) {
				$extra_errors = "<br />Also, failed to roll back transaction.  Please contact the system administrator";
			}
			return array(false, "Failed to create a single blank game.$extra_errors");
		}

		try {
			$g->select_random_gameslot($datestamp);
		} catch (Exception $e) {
			$extra_errors = $e->getMessage();
			if( ! $dbh->rollback() ) {
				$extra_errors .= "<br />Also, failed to roll back transaction.  Please contact the system administrator";
			}
			return array(false, "Failed to add gameslots to single blank game.$extra_errors");
		}

		$rc = $dbh->commit();

		if( ! $rc ) {
			return array( false, 'Transaction commit failed');
		}

		return array(true,'');
	}

	/*
	 * Create an empty set of games for this league
	 */
	function create_empty_set( $datestamp, $excludeTeamsIDs = array(), $should_publish = true )
	{
		global $dbh;

		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams) - count($excludeTeamsIDs);

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}


		/* Now, randomly create our games.  Don't add any teams, or set a
		* round, or anything.  Then, use that game ID to randomly allocate us
		* a gameslot.
		*/
		$num_games = ( $num_teams / 2 );
		$dbh->beginTransaction();
		for($i = 0; $i < $num_games; $i++) {
			$g = new Game;
			$g->set('league_id', $this->league_id);
			$g->set('published', $should_publish);
			if ( ! $g->save() ) {
				if( ! $dbh->rollback() ) {
					$extra_errors = "<br />Also, failed to roll back transaction.  Please contact the system administrator";
				}
				return array(false, "Failed to create blank games.$extra_errors");
			}

			try {
				$g->select_random_gameslot($datestamp);
			} catch (Exception $e) {
				$extra_errors = $e->getMessage();
				if( ! $dbh->rollback() ) {
					$extra_errors .= "<br />Also, failed to roll back transaction.  Please contact the system administrator";
				}
				return array(false, "Failed to add gameslots to blank games.$extra_errors");
			}
		}

		$rc = $dbh->commit();

		if( ! $rc ) {
			return array( false, 'Transaction commit failed');
		}

		return array(true,'');
	}

	/*
	 * Create a scheduled set of games for this league
	 */
	function create_scheduled_set_ratings_ladder( $datestamp, $excludeTeamsIDs = array(), $should_publish = true )
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);
		if (isset($excludeTeamsIDs)) {
			$num_teams = $num_teams - count($excludeTeamsIDs);
		}

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}

		# sort teams so ratings scheduling works properly
		$teams = array();
		list($team_ids, $junk, $morejunk) = $this->calculate_standings();
		foreach($team_ids as $id) {
			if (isset($excludeTeamsIDs)) {
				if ( ! in_array($id, $excludeTeamsIDs) ) {
					array_push($teams, $this->teams[$id]);
				}
			} else {
				array_push($teams, $this->teams[$id]);
			}
		}

		return $this->schedule_one_set_ratings_ladder( $teams, $datestamp, $should_publish );
	}

	/*
	 * Create a scheduled set of games for this league
	 */
	function create_scheduled_set( $datestamp, $excludeTeamsIDs = array(), $should_publish = true )
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);
		if (isset($excludeTeamsIDs)) {
			$num_teams = $num_teams - count($excludeTeamsIDs);
		}

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}
		$teams = array();
		foreach(array_keys($this->teams) as $id) {
			if (isset($excludeTeamsIDs)) {
				if ( ! in_array($id, $excludeTeamsIDs) ) {
					array_push($teams, $this->teams[$id]);
				}
			} else {
				array_push($teams, $this->teams[$id]);
			}
		}

		# randomize team IDs
		shuffle($teams);

		return $this->schedule_one_set( $teams, $datestamp, $should_publish );
	}

	/*
	 * Create a half round-robin for this league.
	 */
	function create_half_roundrobin( $datestamp, $how_split, $should_publish = true )
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$n = count($this->teams);

		if($n < 2) {
			return array(false, "Must have two teams");
		}

		if($n % 2) {
			return array(false, "Must have even number of teams");
		}

		# Split league teams into $top_half and $bottom_half according to
		# $how_split
		switch($how_split) {
			case 'rating':
				$teams = array_values($this->teams);
				uasort($teams, 'teams_sort_rating');
				break;
			case 'standings':
			default:
				$teams = array();
				list($team_ids, $junk, $morejunk) = $this->calculate_standings();
				foreach($team_ids as $id) {
					array_push($teams, $this->teams[$id]);
				}
		}

		$n = count($teams);
		$top_half = array_slice($teams, 0, ($n / 2));
		$bottom_half = array_slice($teams, ($n / 2));

		# Schedule both halves.
		list($rc, $message) = $this->create_full_roundrobin($datestamp, $top_half, $should_publish);
		if( !$rc ) {
			return array($rc, $message);
		}
		list($rc, $message) = $this->create_full_roundrobin($datestamp, $bottom_half, $should_publish);
		if( !$rc ) {
			return array($rc, $message);
		}

		return array(true,'');
	}

	/**
	 * Count how many distinct gameslot days are availabe from $datestamp onwards
	 *
	 */
	function count_available_gameslot_days( $datestamp )
	{
		global $dbh;

		$sth = $dbh->prepare('SELECT count(game_date) FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) >= ? AND a.league_id = ? AND ISNULL(s.game_id)');
		$sth->execute(array($datestamp, $this->league_id));
		return $sth->fetchColumn();
	}

	/**
	 * Return next available day of play after $datestamp, based on gameslot availability
	 *
	 * value returned is a UNIX timestamp for the game day.
	 */
	function next_gameslot_day( $datestamp )
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT UNIX_TIMESTAMP(game_date) FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) > ? AND a.league_id = ? AND ISNULL(s.game_id) ORDER BY game_date LIMIT 1');
		$sth->execute(array( $datestamp, $this->league_id) );

		return $sth->fetchColumn();
	}

	/*
	 * Create a full round-robin for this league.
	 */
	function create_full_roundrobin( $datestamp, $teams = null, $should_publish = true)
	{

		if( is_null($teams) ) {
			if ( ! $this->load_teams() ) {
				return(array(false, "Couldn't load team information"));
			}
			$teams = array_values($this->teams);
		}

		$n = count($teams);

		if($n < 2) {
			return array(false, "Must have two teams");
		}

		if($n % 2) {
			return array(false, "Must have even number of teams");
		}

		# For n-1 iterations, generate games by pairing up teams
		$iterations_remaining = $n - 1;

		# and so we need n-1 days worth of gameslots
		$day_count = $this->count_available_gameslot_days( $datestamp );

		if( $day_count < $iterations_remaining ) {
			return array(false, "Need $iterations_remaining weeks of gameslots, yet only $day_count are available.  Add more gameslots");
		}

		while($iterations_remaining--) {

			# Round-robin algorithm for n teams:
			#   a.  pair each team k up with its (n - k - 1) partner in the
			#   list.  schedule_one_set() takes the array pairwise, so we do
			#   it like this.
			$set_teams = array();
			for($k = 0; $k < ($n / 2); $k++) {
				$set_teams[] = $teams[$k];
				$set_teams[] = $teams[($n - $k - 1)];
			}
			#   b.  schedule them
			list($rc, $message) = $this->schedule_one_set( $set_teams, $datestamp, $should_publish );
			if( ! $rc ) {
				return array( false, "Aieee... had to stop with $iterations_remaining sets left to schedule: $message");
			}

			# c.  keep k=0 element in place, move k=1 element to end, and move
			# k=2 through n elements left one position.
			$teams = rotate_all_except_first( $teams );

			# Now, move the datestamp forward to next available game date
			$datestamp = $this->next_gameslot_day( $datestamp );

		}

		return array(true,'');
	}

	function getRecentGames( $teamid, $gbr ) {
		static $recent_games_cache = array();
		$past_games = array();
		$load_from_db = true;
		$now = time();

		if ($recent_games_cache[$teamid] != null) {
			$past_games = $recent_games_cache[$teamid];
			$time = $past_games[0]; // first item in the array is the timestamp of when it was added to the cache
			// if this data is more than 1 minute old, get rid of it...
			if ($now - $time > 60) {
				$past_games = array();
				$load_from_db = true;
			} else {
				$load_from_db = false;
			}
		}
		if ($load_from_db) {
			// gotta load this team's games to see who they've played recently...
			$past_games = Game::load_many( array( 'either_team' => $teamid, '_order' => 'g.game_date') );
			if ($past_games == null)
				$past_games = array();
			// make the most recent game first in the array:
			$past_games = array_reverse($past_games);
			// put the time as the first element:
			array_unshift($past_games, $now);
			// save in the cache
			$recent_games_cache[$teamid] = $past_games;
		}

		// reduce the past_games down to be only equal to the current games_before_repeat (gbr)
		$return = array();
		// start from 1 since element 0 is the timestamp
		for ($i = 1; $i <= $gbr; $i++) {
			if ( $i < sizeof($past_games) ) {
				array_push($return, $past_games[$i]);
			} else {
				break;
			}
		}

		return $return;
	}

	/**
	 * Schedule one set of games using the ratings_ladder scheme!
	 */
	function schedule_one_set_ratings_ladder( $teams, $datestamp, $should_publish = true )
	{
		global $headers_sent;
		$headers_sent= 1;
		$games_before_repeat = $this->games_before_repeat;
		$min_games_before_repeat = 0;
		$max_retries = $this->schedule_attempts;
		$ret = null;

		$start_time = time();

		global $CONFIG;
		print "<html>";
		print "<head>";
		print "<base href=\"{$CONFIG['paths']['base_url']}/\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$CONFIG['paths']['base_url']}/themes/pushbutton/style.css\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$CONFIG['paths']['base_url']}/css/style.css\" />";
		print "</head>";
		print "<body><div id='main'><h1>Processing your request</h1>\n";
		print "<h2>Scheduling games using the Ratings Ladder scheduler</h2>\n";
		print "<p><b>Please be patient as this may take a few minutes.  Don't close ";
		print "your browser or go to a different page until it is finished</b></p>";

		echo "<p>Scheduling...";

		$versus_teams = array();
		$gbr_diff = array();
		$seed_closeness = array();
		$ratings_closeness = array();

		for ($j = 0; $j < $max_retries; $j++) {

			if (array_sum($seed_closeness) == sizeof($teams)/2) {
				// that's enough - don't bother getting any more, you have a perfect schedule (ie: 1 vs 2, 3 vs 4, etc).
				break;
			}

			set_time_limit(45); // Give this one call 45 seconds to return
			$ret = $this->schedule_one_set_ratings_ladder_try( $teams, $games_before_repeat, $j%2);

			if ($ret[0] == false) {
				echo ". ";
				flush();
				continue;
			}

			// Keep the best schedule by checking how many times we had to decrement
			// the games_before_repeat restriction in order to be able to generate
			// this schedule...

			// The best possible schedule will first have the smallest games before repeat sum,
			// then will have the smallest seed_closeness, and then will have the smallest ratings_closeness

			if (	( count($gbr_diff) == 0  ||  array_sum($gbr_diff) > array_sum($ret[2]) ) ||
					( array_sum($gbr_diff) == array_sum($ret[2]) && array_sum($seed_closeness) > array_sum($ret[3]) ) ||
					( array_sum($gbr_diff) == array_sum($ret[2]) && array_sum($seed_closeness) == array_sum($ret[3]) && array_sum($ratings_closeness) > array_sum($ret[4]) ) ) {
				$versus_teams = $ret[1];
				$gbr_diff = $ret[2];
				$seed_closeness = $ret[3];
				$ratings_closeness = $ret[4];
				echo "! ";
			}

			// keep browser alive...
			echo ". ";
			flush();
			continue;
		}

		if ( array_sum($gbr_diff) == 0 ) {
			echo "Complete!</p>";
		} else {
			echo "Complete...</p>";
		}
		flush();

		// Now, call schedule_one_set() to actually create the games
		$ret = $this->schedule_one_set( $versus_teams, $datestamp, $should_publish );
		if ($ret[0] == false) {
			return $ret;
		}

		$stop_time = time();
		$total_time = $stop_time - $start_time;
		print "<p>Time elapsed: $total_time</p>";

		print "<div class='schedule'>";
		print "<table align=center>";
		print "<tr><td class='column-heading'>Team 1</td><td class='column-heading'>Team 2</td>";
		print "<td class='column-heading'>Seed Diff<br>(". array_sum($seed_closeness) .")</td><td class='column-heading'>Played each other<br>X games ago...</td></tr>";
		$team_idx = 0;
		for ($i = 0; $i < count($gbr_diff); $i++)  {
			$font = "black";
			$played = $gbr_diff[$i];
			if ($played != 0) {
				$font = "red";
				$played = $games_before_repeat - $gbr_diff[$i] + 1;
			} else {
				$played = "&nbsp;";
			}
			print "<tr>";
			print "<td><font color=$font>" . $versus_teams[$team_idx++]->name . "</font></td>";
			print "<td><font color=$font>" . $versus_teams[$team_idx++]->name . "</font></td>";
			print "<td><font color=$font>" . $seed_closeness[$i] . "</font></td>";
			print "<td align=center><font color=$font>" . $played . "</font></td>";
			print "</tr>\n";
		}
		print "</table>\n";
		print "</div>\n";


		print "</p><hr></div></body></html>";
		flush();

		return $ret;
	}

	/**
	 * This does the actual work of scheduling a one set rattings_ladder set of games.
	 * However it has some problems where it may not properly schedule all
	 * the games.  If it runs into problems then we use the wrapper
	 * function that calls this one to retry it.
	 * If any problems are found then this function rolls back it's changes.
	 *
	 * The algorithm is as follows...
	 * - start at either top or bottom of ordered ladder
	 * - grab a "group" of teams, starting with a group size of 1 (and increasing to a per-league-defined MAX)
	 * - take the first team in the group, and find a random opponent within the group that meats the GBR criteria
	 * - remove those 2 teams from the ordered ladder and repeat
	 *
	 */
	function schedule_one_set_ratings_ladder_try( $teams, $games_before_repeat, $down)
	{
		$ratings_closeness = array();
		$seed_closeness = array();
		$gtr = array();
		$versus_teams = array();

		//TODO: make this maximum a per-league variable, and enforce it in the caller function?
		// maximum standings difference of matched teams:
		$MAX_STANDINGS_DIFF = 8;
		// NOTE: that's not REALLY the max standings diff...
		// it's more like the max grouping of teams to use as possible opponents, and they
		// may be well over 8 seeds apart...

		// current standings diff (starts at 1, counts up to MAX_STANDINGS_DIFF)
		$CURRENT_STANDINGS_DIFF = 1;

		$NUM_TIMES_TO_TRY_CURRENT = 50;

		// copy the games before repeat variable
		$gbr = $games_before_repeat;
		// copy the teams array
		$workingteams = $teams;

		if ($down == 1) {
			$workingteams = array_reverse($workingteams);  // go up instead
		}

		// main loop - go through all of the teams
		while(sizeof($workingteams) > 0) {

			// start with the first team (remove from array)
			$current_team = array_shift($workingteams);

			// get the group of teams that are possible opponents
			$possible_opponents = array();
			for ($i = 0; $i < $CURRENT_STANDINGS_DIFF; $i++) {
				if ( sizeof($workingteams) > 0) {
					array_unshift($possible_opponents, array_shift($workingteams));
				} else {
					break;
				}
			}

			$past_games = $this->getRecentGames($current_team->team_id, $gbr);

// TONY HERE

			$new_possible_opponents = array();
			// now, loop through the possible opponents and save only the ones who have not been in recent games
			foreach ($possible_opponents as $po) {
				$recent = false;
				foreach ($past_games as $game) {
					$teamid = $game->home_team;
					if ($game->away_team != $current_team->team_id) {
						$teamid = $game->away_team;
					}
					if ($po->team_id == $teamid) {
						$recent = true;
						break;
					}
				}
				if (!$recent) {
					// if this possible opponent wasn't a recent opponent, then add it into the new possible opponents list
					array_push($new_possible_opponents, $po);
				}
			}

			// if at this point there are no possible opponents, then you have to relax one of the restrictions:
			if ( sizeof($new_possible_opponents) == 0 ) {
				if ($NUM_TIMES_TO_TRY_CURRENT > 0) {
					$NUM_TIMES_TO_TRY_CURRENT--;
				} else if ($CURRENT_STANDINGS_DIFF < $MAX_STANDINGS_DIFF) {
					$NUM_TIMES_TO_TRY_CURRENT = 10;
					// try increasing the current standings diff...
					$CURRENT_STANDINGS_DIFF++;
				} else {
					$NUM_TIMES_TO_TRY_CURRENT = 10;
					// try to decrease games before repeat:
					$gbr--;
					$CURRENT_STANDINGS_DIFF = 1;
				}

				// but, if games before repeat goes negative, you're screwed!
				if ($gbr < 0) {
					print "<br><b>FAILURE: scheduler cannot find valid opponents for " . $current_team->team_id . "</b></br>";
					return false;
				}

				// now, before starting over, put back some stuff...

				// put back the teams:
				$workingteams = $teams;

				// reset these arrays
				$ratings_closeness = array();
				$seed_closeness = array();
				$gtr = array();
				$versus_teams = array();

				// start over:
				continue;

			} // end if sizeof possible opponents

			// now find them an opponent by randomly choosing one of the remaining possible opponents
			shuffle($new_possible_opponents);
			$opponent = $new_possible_opponents[0];

			// now, put the original possible opponents back into the teams array, except for the real opponent
			foreach ($possible_opponents as $po) {
				if ($po->team_id != $opponent->team_id) {
					array_unshift($workingteams, $po);
				}
			}

			// Create the matchup
			$versus_teams[] = $current_team;
			$versus_teams[] = $opponent;
			$gtr[] = $games_before_repeat - $gbr;

			$counter = 0;
			$seed1 = 0;
			$seed2 = 0;
			$rating1 = $current_team->rating;
			$rating2 = $opponent->rating;
			foreach ($teams as $t) {
				$counter++;
				if ($t->team_id == $current_team->team_id) {
					$seed1 = $counter;
				}
				if ($t->team_id == $opponent->team_id) {
					$seed2 = $counter;
				}
				if ($seed1 != 0 && $seed2 != 0) {
					break;
				}
			}
			$seed_closeness[] = abs($seed2-$seed1);
			$ratings_closeness[] = pow($rating1-$rating2, 2);

		} // main loop

		return array(true, $versus_teams, $gtr, $seed_closeness, $ratings_closeness);
	}

	/**
	 * Schedule one set of games, using weighted field assignment
	 *
	 * Takes an array of teams and a datestamp.  From this:
	 * 	- iterate over teams array pairwise and call add_teams_balanced() to create a game with balanced home/away
	 * 	- iterate over all newly-created games, and assign fields based on region preference.
	 * All of this is performed in a transaction and rolled back if any game fails.
	 */
	function schedule_one_set( $teams, $datestamp, $should_publish = true )
	{
		global $dbh;
		$dbh->beginTransaction();
		$games_list = array();
		for($team_idx = 0; $team_idx < count($teams); $team_idx += 2) {
			$g = new Game;
			$g->set('league_id', $this->league_id);
			$g->add_teams_balanced( $teams[$team_idx], $teams[$team_idx + 1]);
			$g->set('published', $should_publish);
			if ( ! $g->save() ) {
				if( ! $dbh->rollback() ) {
					$extra_errors = "<br />Also, failed to roll back transaction.  Please contact the system administrator";
				}
				return array(false, "Could not create the games you requested, during addition of opponents.$extra_errors");
			}
			$games_list[] = $g;
		}

		try {
			$this->assign_fields_by_preferences($games_list, $datestamp);
		} catch (Exception $e) {
			$extra_errors = $e->getMessage();
			if( ! $dbh->rollback() ) {
				$extra_errors .= "<br />Also, failed to roll back transaction.  Please contact the system administrator";
			}
			return array(false, "Failed to assign gameslots for requested games on " . strftime('%c', $datestamp) . ": $extra_errors");
		}

		$rc = $dbh->commit();

		if( ! $rc ) {
			return array( false, 'Transaction commit failed');
		}
		return array(true,'');
	}

	function reschedule_games_for_day( $olddate, $newdate )
	{
		global $dbh;
		$dbh->beginTransaction();
		$games_list = Game::load_many(array(
			'league_id' => $this->league_id,
			'game_date' => strftime("%Y-%m-%d", $olddate)
		));

		$gameslot_sth = $dbh->prepare('UPDATE gameslot SET game_id = NULL where game_id = ?');
		$fieldranking_sth = $dbh->prepare('DELETE FROM field_ranking_stats WHERE game_id = ?');
		foreach($games_list as $game) {
			$gameslot_sth->execute( array( $game->game_id ) );
			$fieldranking_sth->execute( array( $game->game_id ) );
			$game->slot_id = null;
		}


		try {
			$this->assign_fields_by_preferences($games_list, $newdate);
		} catch (Exception $e) {
			$extra_errors = $e->getMessage();
			if( ! $dbh->rollback() ) {
				$extra_errors .= "<br />Also, failed to roll back transaction.  Please contact the system administrator";
			}
			return array(false, "Failed to assign gameslots for requested games on " . strftime('%c', $datestamp) . ": $extra_errors");
		}

		$rc = $dbh->commit();

		if( ! $rc ) {
			return array( false, 'Transaction commit failed');
		}
		return array(true,'');
	}

	function sort_league_teams () {
		$this->load_teams();
		if ($this->schedule_type == 'ratings_ladder' || $this->schedule_type == 'ratings_wager_ladder' ) {
			uasort($this->teams, array($this, 'standings_sort_rating_ladder'));
		} else {
			uasort($this->teams, array($this, 'standings_sort_bywinloss'));
		}
	}

	function sortLadderRating ( $season ) {
		uasort($season, array($this, 'standings_sort_rating_ladder'));
		return $season;
	}

	function display_numeric_sotg ( ) {
		global $lr_session;

		$display_numeric_sotg = false;
		switch( $this->display_sotg ) {
			case 'symbols_only':
				$display_numeric_sotg = $lr_session->is_coordinator_of( $this->league_id );
				break;
			case 'coordinator_only':
				if( $lr_session->is_coordinator_of( $this->league_id ) ) {
					$display_numeric_sotg = true;
				} else {
					$display_numeric_sotg = false;
				}
				break;
			case 'all':
			default:
				$display_numeric_sotg = true;
				break;
		}

		return $display_numeric_sotg;
	}

	/**
	 * Assign field based on home field or region preference.
	 *
	 * This is called from within a transaction and the caller is responsible for
	 * rolling back if errors are returned.
	 *
	 * Returns true on success, throws on failure.
	 *
	 */
	function assign_fields_by_preferences( $games, $timestamp )
	{

		// First pass - cherry-pick anyone with a home field
		$by_home_preference = array();
		while($game = array_pop($games)) {
			if( ! $game->select_home_field_gameslot( $timestamp ) ) {
				$by_home_preference[] = $game;
			}
		}

		if( ! count($by_home_preference) ) {
			return true;
		}

		// Second pass: by home region.  We sort by preference ratio (ascending) so that
		// teams who received their field preference least get first crack.
		usort( $by_home_preference, array('Game', 'cmp_hometeam_field_ratio'));
		$by_away_preference = array();
		while($game = array_pop($by_home_preference)) {
			if( ! $game->select_team_preference_gameslot( $game->home_team, $timestamp ) ) {
				$by_away_preference[] = $game;
			}
		}

		if( ! count($by_away_preference) ) {
			return true;
		}

		// Third pass: by away region, again sorting first
		usort( $by_away_preference, array('Game', 'cmp_awayteam_field_ratio'));
		$by_random = array();
		while($game = array_pop($by_away_preference)) {
			if( ! $game->select_team_preference_gameslot( $game->away_team, $timestamp ) ) {
				$by_home_preference_nolimit[] = $game;
				$by_random[] = $game;
			}
		}

		if( ! count($by_random) ) {
			return true;
		}

		// Fourth pass: randomly,  If nothing found, select_random_gameslot() will throw exception
		while($game = array_pop($by_random)) {
			$game->select_random_gameslot( $timestamp );
		}

		return true;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	function query( $array = array() )
	{
		global $dbh;
		$order = '';
		$query = array();
		$params = array();

		foreach ($array as $key => $value) {
			switch( $key ) {
			case '_day':
				$query[] = '(FIND_IN_SET(?, l.day) > 0)';
				$params[] = $value;
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[]  = "l.$key = ?";
				$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			l.*,
			DATE(l.roster_deadline) AS roster_deadline,
			s.display_name AS season_name,
			1 as _in_database
		FROM league l
			LEFT JOIN season s ON (s.id = l.season)
		WHERE " . implode(' AND ',$query) . $order);
		$sth->execute($params);
		return $sth;
	}

	function load_many( $array = array() )
	{
		$sth = self::query( $array );

		$leagues = array();
		while($l = $sth->fetchObject( get_class() ) ) {
			$leagues[$l->league_id] = $l;
		}

		return $leagues;
	}
}

/**
 * Given an array, keep the first element in place, but rotate the
 * remaining elements by one.
 */
function rotate_all_except_first ( $ary )
{
	$new_ary = array( array_shift($ary) );
	$new_last = array_shift($ary);
	$result = array_merge( $new_ary, $ary );
	array_push($result, $new_last);
	return $result;
}

/**
 * Sort teams by rating, and then by average skill as tiebreaker
 */
function teams_sort_rating(&$a, &$b)
{
	if( $a->rating > $b->rating ) {
		return -1;
	} else if( $a->rating < $b->rating ) {
		return 1;
	}

	if( $a->avg_skill() > $b->avg_skill() ) {
		return -1;
	} else if( $a->avg_skill() < $b->avg_skill() ) {
		return 1;
	}

	return 0;
}

?>
