<?php
class League extends LeaguerunnerObject
{
	var $_teams_loaded;
	var $coordinators;
	var $teams;

	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{
		$this->_teams_loaded = false;
		$this->coordinators = array();
		$this->teams = array();

		/* set derived attributes */
		$year = '';
		if($this->year) { 
			$year = $this->year;
		}
		if($this->tier) {
			$this->fullname = sprintf("$this->name Tier %02d $year", $this->tier);
		} else {
			$this->fullname = "$this->name $year";
		}

		if( $load_mode == LOAD_OBJECT_ONLY ) {
			return;
		}

		$this->load_coordinators();

		return true;
	}

	function load_coordinators() 
	{
		global $dbh;

		$sth = $dbh->prepare('SELECT m.player_id FROM leaguemembers m WHERE m.league_id = ?');
		$sth->execute(array($this->league_id));

		while( $id = $sth->fetchColumn() ) {
			$c_sth = person_query( array( 'user_id' => $id ) );
			$c = $c_sth->fetchObject('Person', array(LOAD_OBJECT_ONLY));
			$c->coordinator_status = 'loaded';
			$this->coordinators[$c->user_id] = $c;
		}
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

		$this->teams = team_load_many( array('league_id' => $this->league_id, '_order' => 't.name'));

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
		global $TZ_ADJUST, $dbh;
		$sth = $dbh->prepare("SELECT s.round FROM schedule s, gameslot g WHERE s.league_id = ? AND (NOT ISNULL(s.round)) AND g.game_id = s.game_id AND ( (UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + ($TZ_ADJUST * 60)) < UNIX_TIMESTAMP(NOW())) ORDER BY g.game_date DESC LIMIT 1");
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
		global $dbh;
		if( $this->finalize_after == 0 )
			return '';

		global $TZ_ADJUST;
		$output = '';

		// TODO: game_load_many()?  Or at least game_query()?
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

		$sth->execute( array(
			$TZ_ADJUST * 60,
			$this->league_id,
			// TZ_ADJUST is in minutes, finalize_after is in hours
			$TZ_ADJUST + ($this->finalize_after * 60) * 60
		));

		while( $game_id = $sth->fetchColumn() ) {
			$game = game_load( array('game_id' => $game_id) );
			if ($game->finalize())
			{
				$stat = 'Finalized';
			}
			else
			{
				$stat = 'DID NOT finalize';
			}
			$output .= "<p>$stat game " .
				l($id, "game/edit/$id") .
				' between ' .
				l($game->home_name, "team/view/$game->home_team") .
				' and ' .
				l($game->away_name, "team/view/$game->away_team") .
				", status {$game->status}</p>";
		}

		return $output;
	}

	/**
	* Send email reminders to any captains that haven't scored their games.
	*/
	function send_scoring_reminders()
	{
		if( $this->email_after == 0 )
			return '';

		global $TZ_ADJUST, $dbh;
		$output = '';

		// TODO: game_load_many()?  Or at least game_query()?
		// TZ_ADJUST is in minutes, email_after is in hours
		$start_offset = $TZ_ADJUST * 60;
		$email_offset = $TZ_ADJUST + ($this->email_after * 60) * 60;
		$sth = $dbh->prepare("SELECT
				DISTINCT s.game_id,
				(UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + $start_offset) AS start_timestamp
			FROM
				schedule s,
				gameslot g
			WHERE
				s.league_id = ?
			AND
				g.game_id = s.game_id
			AND
				s.status = 'normal'
			AND
				(UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + $email_offset) < UNIX_TIMESTAMP(NOW())
			AND
				(ISNULL(s.home_score) OR ISNULL(s.away_score))
			ORDER BY
				g.game_id
			");

		$sth->execute( array( $this->league_id ) );

		while( $game_id = $sth->fetchColumn() ) {
			$game = game_load( array('game_id' => $game_id) );
			if ($game->remind())
			{
				$stat = 'Emailed';
			}
			else
			{
				$stat = 'DID NOT email';
			}
			$output .= "<p>$stat reminder for $game->game_date-$game->game_start game " .
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

			$sth->execute( $fields_data );

			# Affecting zero rows is possible
			if(1 < $sth->rowCount()) {
				error_exit('Internal error: Strange number of rows affected');
			}
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
			'DELETE FROM activity_log USING activity_log,schedule WHERE activity_log.primary_id = schedule.game_id AND (type = "email_score_mismatch" OR type = "email_score_reminder") AND schedule.league_id = ?',
			'DELETE FROM team_spirit_answers USING team_spirit_answers,schedule WHERE team_spirit_answers.gid = schedule.game_id AND schedule.league_id = ?',
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

	/** DELETE GAMES FUNCTION **/
	# TODO: clean this up so that it makes sense for non-ladder
	function cancelround ( $round ) 
	{

		// make sure the teams are loaded
		if (! $this->load_teams() ) {
			return false;
		}

		$number_of_teams = count($this->teams);
		$game_set = $number_of_teams / 2;

		// get the schedule
		$sth = game_query ( array( 'league_id' => $this->league_id, '_order' => 'g.game_date, g.game_id, g.game_start') );
		if( ! $sth ) {
			return false;
		}

		// Super important!  We make a back-up copy of all the game objects in case we have to rollback!
		$backup_games = array();
		$games = array();
		$changed_games = array();
		while($game = $sth->fetch()) {
			if ($game['round'] >= $round) {
				$game = game_load( array('game_id' => $game['game_id']) );
				if ($game->home_score || $game->away_score) {
					error_exit("You're still ok, because I didn't actually modify or delete anything yet!" .
					" But, can't cancel games because Game # $game->game_id has results! ");
				}
				array_push($games, $game);
				array_push($backup_games, $game);
			}
		}
		reset($games);

		// ASSUMPTION: the array is properly sorted such that games that swap info are "$game_set" apart
		for ($i = count($games)-1; $i >= 0; $i--) {
			$game = $games[$i];
			if ( ($i - $game_set) >= 0 ) {
				$prev_game = $games[$i-$game_set];

				$game->set('round', $prev_game->round);

				$game->set('home_team', $prev_game->home_team);
				$game->set('away_team', $prev_game->away_team);

				// the only dependant info that you need to update is for the
				// game_set AFTER the game_set you're deleting!
				if ($prev_game->round == $round) {
					$game->set('home_dependant_game', $prev_game->home_dependant_game);
					$game->set('away_dependant_game', $prev_game->away_dependant_game);
					$game->set('home_dependant_type', $prev_game->home_dependant_type);
					$game->set('away_dependant_type', $prev_game->away_dependant_type);
				}

				if (! $game->save() ) {
					// there was a problem, rollback!
					foreach ($backup_games as $bgame) {
						if (! $bgame->save() ) {
							error_exit("Oh Crap! Could not rollback during cancelround($round)! This is VERY bad!  You schedule is most likely HOSED now, you should revert to a backed up copy! Original problem was saving games...");
						}
					}
					error_exit("Could not save the game info during cancelround($round)! The changes made here have been reverted! Whew!");
				} else {
					// save the changed game (use unshift because here we are looping backwards!)
					array_unshift($changed_games, $game);
				}
			} else {
				if (! $game->delete() ) {
					// there was a problem, rollback!
					foreach ($backup_games as $bgame) {
						if (! $bgame->save() ) {
							error_exit("Oh Crap! Could not rollback during cancelround($round)! This is VERY bad!  You schedule is most likely HOSED now, you should revert to a backed up copy! Original problem was deleting games...");
						}
					}
					error_exit("Could not delete the game during cancelround($round)! The changes made here have been reverted! Whew!");
				}
			}
		}

		// now fix all of the dependant game info!
		$this->set_dependants($changed_games, $number_of_teams);

		return true;
	}


	/*
	*  This function expects one ORDERED array with any number of sets of games, and will return a copy
	*  of the input array with the dependant games filled in.
	*  - The array should start with the first set of games with home/away teams already assigned, where
	*    the first game is 1 vs 2, second game is 3 vs 4, etc...
	*  - The function will skip the first game set, and then use it to assign the dependant games for
	*    the second game set.
	*  - It will then use the second game set to assign the dependant games for the third game set, and so on
	*  - ASSUMPTION: the round number is used to determine HOLD and MOVE transitions, and it is assumed
	*    that each game set has a round number incremented by one compared to the previous game set.  Furthermore,
	*    it is assumed that the first game set starts with round 1.
	*/
	function set_dependants ($games, $number_of_teams) 
	{

		$games_per_set = $number_of_teams / 2;
		$sets = count($games) / $games_per_set;

		$return_games = array();
		$count = 1;
		$wlflag = 0;
		$game_set = 1;
		$rankings = 1;
		foreach ($games as $g) {
			// don't do anything for the first game set
			if ($count <= $games_per_set) {
				array_push ( $return_games, $g );
				$count++;
				continue;
			}

			// first game will always be winners of first 2 "prev" games
			if ( $count - ($games_per_set*$game_set) == 1 ) {
				// you ALWAYS want to start the rankings at 1 here!
				$rankings = 1;
				$get = $count - $games_per_set - 1;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "winner");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				$game = $games[ $get+1 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "winner");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					return "Could not save a game!";
				}
				array_push ( $return_games, $g );
				$count++;
				continue;
			}
			// the last game will always be the losers of the last 2 "prev" games
			if ( $count - ($games_per_set*$game_set) == $games_per_set ) {
				$get = $count - $games_per_set - 2;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "loser");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				$game = $games[ $get+1 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "loser");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					return "Could not save a game !";
				}
				array_push ( $return_games, $g );
				$count++;
				$game_set++;
				continue;
			}

			$holdmove = $g->round % 2;
			// Invert the holdmove since very first set of games will be round 1, and
			// so the subsequent games which you're now scheduling should start with
			// a hold week, but because we're using the next game's round number,
			// that number mod 2 will be 0, and we want 1 to start!
			$holdmove = !$holdmove;

			// if you've got here, you're looking at middle games, and the behaviour
			// here is dependant on the hold or move weeks!
			if ($holdmove) {
				// HOLD TRANSITION:
				if ($wlflag) {
					// do winners:
					$get = $count - $games_per_set - 1;
					$game = $games[ $get ];
					$g->set('home_dependant_game', $game->game_id);
					$g->set('home_dependant_type', "winner");
					$g->set('home_dependant_rank', $rankings);
					$rankings++;
					$game = $games[ $get+1 ];
					$g->set('away_dependant_game', $game->game_id);
					$g->set('away_dependant_type', "winner");
					$g->set('away_dependant_rank', $rankings);
					$rankings++;
				} else {
					// do losers:
					$get = $count - $games_per_set - 2;
					$game = $games[ $get ];
					$g->set('home_dependant_game', $game->game_id);
					$g->set('home_dependant_type', "loser");
					$g->set('home_dependant_rank', $rankings);
					$rankings++;
					$game = $games[ $get+1 ];
					$g->set('away_dependant_game', $game->game_id);
					$g->set('away_dependant_type', "loser");
					$g->set('away_dependant_rank', $rankings);
					$rankings++;
				}
				if ( !$g->save() ) {
					return "Could not save a game ! ";
				}
				array_push ( $return_games, $g );
				$count++;
				$wlflag = !$wlflag;
			} else {
				// MOVE TRANSITION:
				// get the loser:
				$get = $count - $games_per_set - 2;
				$game = $games[ $get ];
				$g->set('home_dependant_game', $game->game_id);
				$g->set('home_dependant_type', "loser");
				$g->set('home_dependant_rank', $rankings);
				$rankings++;
				// get the winner:
				$game = $games[ $get+2 ];
				$g->set('away_dependant_game', $game->game_id);
				$g->set('away_dependant_type', "winner");
				$g->set('away_dependant_rank', $rankings);
				$rankings++;
				if ( !$g->save() ) {
					return "Could not save a game ! ";
				}
				array_push ( $return_games, $g );
				$count++;
			}
		}
		return $return_games;
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
		$this->sanitize_ranks();

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
		// TODO: I'd like to use game_load_many here, but it's too slow.
		$sth = $dbh->prepare('SELECT DISTINCT 
			s.*, 
			1 as _in_database,
			s.home_team AS home_id, 
			h.name AS home_name, 
			s.away_team AS away_id,
			a.name AS away_name
			FROM leagueteams t, schedule s
				LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
				LEFT JOIN gameslot g ON (g.game_id = s.game_id)
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
		} else if ($this->schedule_type == "ladder" || $this->schedule_type == "pyramid") {
			// Call a function that will handle complete sorting of ladder standings:
			$season = $this->sortLadder($season);
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
	
		// Make sure we have a numeric spirit value for each game.
		if ($game->home_spirit == null || $game->home_spirit == "") {
			$game->home_spirit = $game->get_spirit_numeric( $game->home_team );
		}

		if ($game->away_spirit == null || $game->away_spirit == "") {
			$game->away_spirit = $game->get_spirit_numeric( $game->away_team );
		}

		if(isset($season[$game->home_team])) {
			$team = &$season[$game->home_team];

			$team->games++;
			$team->points_for += $game->home_score;
			$team->points_against += $game->away_score;

			if ( is_numeric($game->home_spirit) ) {
				$team->spirit[] = $game->home_spirit;
			}

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

			if ( is_numeric($game->away_spirit) ) {
				$team->spirit[] = $game->away_spirit;
			}

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

	function standings_sort_ladder_byrankonly (&$a, &$b) 
	{
		return ($a->rank < $b->rank) ? -1 : 1;
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

		/* Check SOTG */
		if ( calculateAverageSOTG($a->spirit, true) > calculateAverageSOTG($b->spirit, true) ) {
			return -1;
		} elseif ( calculateAverageSOTG($a->spirit, true) < calculateAverageSOTG($b->spirit, true) ) {
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

	function standings_sort_ladder (&$a, &$b) 
	{
		/* Check Rank */
		if ($a->rank != $b->rank) {
			return ($a->rank < $b->rank) ? -1 : 1;
		}

		/* Check head-to-head by looking at rung_points */
		if ($a->rung_points != $b->rung_points) {
			return ($a->rung_points > $b->rung_points) ? -1 : 1;
		}

		/* Continue Check head-to-head by looking at rung_points_difference */
		if ($a->rung_points_diff != $b->rung_points_diff) {
			return ($a->rung_points_diff > $b->rung_points_diff) ? -1 : 1;
		}

		/* Check Rating */
		if ($a->rating != $b->rating) {
			return ($a->rating > $b->rating) ? -1 : 1;
		}

		/* Check SOTG */
		if ( calculateAverageSOTG($a->spirit, true) > calculateAverageSOTG($b->spirit, true) ) {
			return -1;
		} elseif ( calculateAverageSOTG($a->spirit, true) < calculateAverageSOTG($b->spirit, true) ) {
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

		/* Check SOTG */
		if ( calculateAverageSOTG($a->spirit, true) > calculateAverageSOTG($b->spirit, true) ) {
			return -1;
		} elseif ( calculateAverageSOTG($a->spirit, true) < calculateAverageSOTG($b->spirit, true) ) {
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


	/*
	* Create an empty set of games for this league
	*/
	function create_empty_set( $datestamp ) 
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}


		/* Now, randomly create our games.  Don't add any teams, or set a
		* round, or anything.  Then, use that game ID to randomly allocate us
		* a gameslot.
		* TODO This would be soooo much nicer with transactions...
		*/
		$num_games = ( $num_teams / 2 );
		$rollback_list = array();
		for($i = 0; $i < $num_games; $i++) {
			$g = new Game;
			$g->set('league_id', $this->league_id);
			if ( ! $g->save() ) {
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields.$extra_errors");
			}
			$rollback_list[] = $g;

			list($rc, $message) = $g->select_random_gameslot($datestamp);
			if( ! $rc ) { 
				/* Argh, something failed, so roll back the whole pile of
				* games */
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields.$extra_errors");
			}
		}

		return array(true,'');
	}


	/*
	* Create a scheduled set of games for this league
	*/
	function create_scheduled_set_pyramid( $datestamp ) 
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}

		$rollback_list = array();

		# sort team's so that pyramid scheme can work properly
		$teams = array();
		list($team_ids, $junk, $morejunk) = $this->calculate_standings();
		foreach($team_ids as $id) {
			array_push($teams, $this->teams[$id]);
		}

		return $this->schedule_one_set_pyramid( $teams, $datestamp );
	}

	/*
	 * Create a scheduled set of games for this league
	 */
	function create_scheduled_set_ratings_ladder( $datestamp, $excludeTeamsIDs = array() ) 
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

		$rollback_list = array();

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

		return $this->schedule_one_set_ratings_ladder( $teams, $datestamp );
	}

	/*
	 * Create a scheduled set of games for this league
	 */
	function create_scheduled_set( $datestamp ) 
	{
		if ( ! $this->load_teams() ) {
			return(array(false, "Couldn't load team information"));
		}

		$num_teams = count($this->teams);

		if($num_teams < 2) {
			return array(false, "Must have two teams");
		}

		if($num_teams % 2) {
			return array(false, "Must have even number of teams");
		}

		$rollback_list = array();

		# randomize team IDs
		$teams = array_values($this->teams);
		shuffle($teams);

		return $this->schedule_one_set( $teams, $datestamp );
	}

	/*
	* Create a half round-robin for this league.
	*/
	function create_half_roundrobin( $datestamp, $how_split ) 
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
		list($rc, $message) = $this->create_full_roundrobin($datestamp, $top_half);
		if( !$rc ) { 
			return array($rc, $message); 
		}
		list($rc, $message) = $this->create_full_roundrobin($datestamp, $bottom_half);
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
		$sth = $dbh->prepare('SELECT UNIX_TIMESTAMP(game_date) FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) >= ? AND a.league_id = ? AND ISNULL(s.game_id) ORDER BY game_date LIMIT 1');
		$sth->execute(array( $datestamp, $this->league_id) );

		return $sth->fetchColumn();
	}

	/*
	* Create a full round-robin for this league.
	*/
	function create_full_roundrobin( $datestamp, $teams = null) 
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
			list($rc, $message) = $this->schedule_one_set( $set_teams, $datestamp );
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


	/**
	* This does the actual work of scheduling a one set pyramid set of games.
	* However it has some problems where it may not properly schedule all
	* the games.  If it runs into problems then we use the wrapper
	* function that calls this one to retry it.
	* If any problems are found then this function rolls back it's changes.
	*/
	function schedule_one_set_pyramid_try( $teams, $datestamp, $games_before_repeat, $down)
	{

		$gtr = array();
		$versus_team1 = array();
		$versus_team2 = array();
		$matched_teams = array();

		// create a copy of the teams, split up into rungs of the ladder...
		if ($down == 1) {
			$teams = array_reverse($teams);  // go up instead
		}
		$rungs = array();
		$last_rank = null;
		$temp_array = array();
		foreach ($teams as $team) {
			if ($last_rank != null && $team->rank != $last_rank) {
				// done with one rung of the ladder
				$rungs[$last_rank] = $temp_array;
				$temp_array = array();
			}
			$last_rank = $team->rank;
			$temp_array[] = $team;
		}
		$rungs[$last_rank] = $temp_array;
		$temp_array = array();
		$last_rung = $last_rank;

		// ASSUMPTION: the array of teams is already ordered.
		// now, loop through the teams
		while ($teams) {
			// this is the local variable for this loop....
			$single_gtr = $games_before_repeat;

			// get the current team to work on:
			$team = array_shift($teams);
			// gotta load this team's games to see who they've played recently...
			$past_games = game_load_many( array( 'either_team' => $team->team_id, '_order' => 'g.game_date') );
			if ($past_games == null) {
				$past_games = array();
			}

			// make the most recent game first in the array:
			$past_games = array_reverse($past_games);

			// now, loop through the past_games array to find the most recent teams
			$recent_teams = array();
			foreach ($past_games as $game) {
				if ($game->home_team != $team->team_id) {
					$recent_teams[] = $game->home_team;
				} else {
					$recent_teams[] = $game->away_team;
				}
				if (count($recent_teams) == $games_before_repeat) {
					break; // We don't need or want more than the most recent ones
				}
			}

			// now that we know who they've played recently, let's find the next opponent
			$opponent = null;
			$current_rung = $team->rank;
			while ($opponent == null) {
				// if we move past the bottom or top of the pyramid...
				if (($down==0 && ($current_rung > $last_rung)) || ($down==1 && ($current_rung < $last_rung))) {
					$current_rung = $team->rank;
					$single_gtr --;
					array_pop($recent_teams);
					continue;
					// We've gone too far and can't find a match.  Roll back
					// Everything and return false so that this can be retried
					//print "\nFailed team: " . $team->name . " rank: " . $team->rank;
					//return array(false, "REPEAT");
					//return array(true, $versus_team1, $versus_team2, $gtr);
				}
				$possible_opponents = null;
				// if there's only one team left, no choice but to match them...
				$possible_opponents = $rungs[$current_rung];
				if ( $possible_opponents == null || count($possible_opponents) == 0 ) {
					if ($down == 0) {
						$current_rung++;
					} else {
						$current_rung--;
					}
					continue;
				}
				// remove "invalid" teams from the list of possible opponents
				$real_possible_opponents = array();
				foreach ($possible_opponents as $po) {
					if ( in_array($po->team_id, $matched_teams) ) {
						continue;
					}
					if ( in_array($po->team_id, $recent_teams) ) {
						continue;
					}
					if ($po->team_id == $team->team_id) {
						continue;
					}
					$real_possible_opponents[] = $po;
				}
				if ( count($real_possible_opponents) == 0 ) {
					if ($down == 0) {
						$current_rung++;
					} else {
						$current_rung--;
					}
					continue;
				}
				// randomize all teams inside that rung
				shuffle($real_possible_opponents);
				$opponent = $real_possible_opponents[0];

				// Now that we've got the opponent, they need to be removed
				// from their rung so they don't get matched up again.
				$matched_teams[] = $opponent->team_id;
				$matched_teams[] = $team->team_id;
			}

			// take the opponent out of the $teams array
			$tmp_teams = array();
			foreach($teams as $key=>$thisteam) {
				if ($thisteam->team_id != $opponent->team_id) {
					$tmp_teams[$key] = $thisteam;
				}
			}
			$teams = $tmp_teams;

			// Create the matchup
			$versus_team1[] = $team;
			$versus_team2[] = $opponent;
			$gtr[] = $games_before_repeat - $single_gtr;
		}

		return array(true, $versus_team1, $versus_team2, $gtr);
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
	function schedule_one_set_ratings_ladder_try( $teams, $datestamp, $games_before_repeat, $down)
	{
		$ratings_closeness = array();
		$seed_closeness = array();
		$gtr = array();
		$versus_team1 = array();
		$versus_team2 = array();

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
				$versus_team1 = array();
				$versus_team2 = array();
				
				// start over:
				continue;
				
			} // end if sizeof possible opponents
			
			// now find them an opponent by randomly choosing one of the remaining possible opponents
			srand(make_seed());
			shuffle($new_possible_opponents);
			$opponent = $new_possible_opponents[0];
			
			// now, put the original possible opponents back into the teams array, except for the real opponent
			foreach ($possible_opponents as $po) {
				if ($po->team_id != $opponent->team_id) {
					array_unshift($workingteams, $po);
				}
			}
			
			// Create the matchup
			$versus_team1[] = $current_team;
			$versus_team2[] = $opponent;
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

		return array(true, $versus_team1, $versus_team2, $gtr, $seed_closeness, $ratings_closeness);
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
			$past_games = game_load_many( array( 'either_team' => $teamid, '_order' => 'g.game_date') );
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

	/*
	* versus team 1 and 2 arrays
	*/
	function schedule_one_set_pyramid_commit ( $vt1, $vt2, $datestamp ) {
		$rollback_list = array();

		// now, you have the matchups, we can create the games
		// use random indexes so that field preferences are not given always to top or bottom teams...
		$random_indexes = array_keys($vt1);
		shuffle($random_indexes);
		foreach( $random_indexes as $i ) {
			$team = $vt1[$i];
			$opponent = $vt2[$i];

			$g = new Game;
			$g->set('league_id', $this->league_id);
			$g->add_teams_balanced( $team, $opponent );
			if ( ! $g->save() ) {
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields. Balanced.$extra_errors");
			}
			$rollback_list[] = $g;

			// weighted gameslot will take into account field preferences
			list($rc, $message) = $g->select_weighted_gameslot($datestamp);
			if( ! $rc ) {
				// something failed, so roll back the whole pile of games
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields. Gameslot.$extra_errors");
			}
		}
		return array(true, "");
	}

	/*
	 * versus team 1 and 2 arrays
	 */
	function schedule_one_set_ratings_ladder_commit ( $vt1, $vt2, $datestamp ) {
		$games_list = array();
		$teams_list = array();

		// now, you have the matchups, we can create the games
		// use random indexes so that field preferences are not given always to top or bottom teams...
		$random_indexes = array_keys($vt1);
		shuffle($random_indexes);
		foreach( $random_indexes as $i ) {
			$team = $vt1[$i];
			$opponent = $vt2[$i];

			$teams_list[] = $team;
			$teams_list[] = $opponent;
					
			$g = new Game;
			$g->set('league_id', $this->league_id);
			$g->add_teams_balanced( $team, $opponent );
			if ( ! $g->save() ) {
				foreach( $games_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields. Balanced.$extra_errors");
			}
			$games_list[] = $g;
		}
		
		list($success, $rc, $message) = assign_fields_by_preferences($games_list, $teams_list, $datestamp);

		if (!$success) {
			foreach( $games_list as $to_rollback ) {
				if( ! $to_rollback->delete() ) {
					$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
				}
			}
			return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields. Gameslot.$extra_errors");
		}
		return array(true, "");
	}


	/**
	* Schedule one set of games using the pyramid scheme!
	*/
	function schedule_one_set_pyramid( $teams, $datestamp )
	{
		global $headers_sent;
		$headers_sent= 1;
		ob_start();
		$games_before_repeat = $this->games_before_repeat;
		$min_games_before_repeat = 0;
		$max_retries = $this->schedule_attempts;
		$ret = null;

		global $FILE_URL;
		print "<html>";
		print "<head>";
		print "<base href=\"$FILE_URL/\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$FILE_URL/themes/pushbutton/style.css\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$FILE_URL/style.css\" />";
		print "</head>";
		print "<body><div id='main'><h1>Processing your request</h1>\n";
		print "<h2>Scheduling games using the Pyramid Ladder scheduler</h2>\n";
		print "<p><b>Please be patient as this may take a few minutes.  Don't close ";
		print "your browser or go to a different page until it is finished</b></p>";

		echo "<p>Scheduling...";

		$versus_team1 = array();
		$versus_team2 = array();
		$gtr = array();

		for ($j = 0; $j < $max_retries; $j++) {
			set_time_limit(30); // Give this one call 30 seconds to return
			$ret = $this->schedule_one_set_pyramid_try( $teams, $datestamp, $games_before_repeat, $j%2);

			if ($ret[0] == false) {
				echo ". ";
				flush();
				ob_flush();
				continue;
			}

			// Keep the best schedule by checking how many times we had to decrement
			// the games_before_repeat restriction in order to be able to generate
			// this schedule...
			if ( count($gtr) == 0  ||  array_sum($gtr) > array_sum($ret[3]) ) {
				$versus_team1 = $ret[1];
				$versus_team2 = $ret[2];
				$gtr = $ret[3];
				reset($gtr);
			}

			// if we got the perfect result, we're done... else keep browser alive...
			if ( array_sum($gtr) == 0 ) {
				break;
			} else {
				echo ". ";
				flush();
				ob_flush();
				continue;
			}
		}
		
		if ( array_sum($gtr) == 0 ) {
			echo "Complete!</p>";
		} else {
			echo "Complete...</p>";
		}
		flush();
		ob_flush();

		// now, try to actually create the games
		$ret = $this->schedule_one_set_pyramid_commit( $versus_team1, $versus_team2, $datestamp );
		if ($ret[0] == false) {
			return $ret;
		}

		if ( ! array_sum($gtr) == 0 ) {
			print "<div class='schedule'>";
			print "<table align=center>";
			print "<tr><td class='column-heading'>Team 1</td><td class='column-heading'>Team 2</td><td class='column-heading'>Played each other<br>X games ago...</td></tr>";
			for ($i = 0; $i < count($gtr); $i++)  {
				$font = "black";
				$played = $gtr[$i];
				if ($played != 0) {
					$font = "red";
					$played = $games_before_repeat - $gtr[$i] + 1;
				} else {
					$played = "&nbsp;";
				}
				print "<tr>";
				print "<td><font color=$font>" . $versus_team1[$i]->name . "</font></td>";
				print "<td><font color=$font>" . $versus_team2[$i]->name . "</font></td>";
				print "<td align=center><font color=$font>" . $played . "</font></td>";
				print "</tr>\n";
			}
			print "</table>\n";
			print "</div>\n";
		}


		print "</p><hr></div></body></html>";
		flush();
		ob_flush();

		return $ret;
	}


	/**
	 * Schedule one set of games using the ratings_ladder scheme!
	 */
	function schedule_one_set_ratings_ladder( $teams, $datestamp )
	{
		global $headers_sent;
		$headers_sent= 1;
		ob_start();
		$games_before_repeat = $this->games_before_repeat;
		$min_games_before_repeat = 0;
		$max_retries = $this->schedule_attempts;
		$ret = null;

		$start_time = time();
		
		global $FILE_URL;
		print "<html>";
		print "<head>";
		print "<base href=\"$FILE_URL/\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$FILE_URL/themes/pushbutton/style.css\" />";
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$FILE_URL/style.css\" />";
		print "</head>";
		print "<body><div id='main'><h1>Processing your request</h1>\n";
		print "<h2>Scheduling games using the Ratings Ladder scheduler</h2>\n";
		print "<p><b>Please be patient as this may take a few minutes.  Don't close ";
		print "your browser or go to a different page until it is finished</b></p>";

		echo "<p>Scheduling...";

		$versus_team1 = array();
		$versus_team2 = array();
		$gbr_diff = array();
		$seed_closeness = array();
		$ratings_closeness = array();

		for ($j = 0; $j < $max_retries; $j++) {
			
			if (array_sum($seed_closeness) == sizeof($teams)/2) {
				// that's enough - don't bother getting any more, you have a perfect schedule (ie: 1 vs 2, 3 vs 4, etc).
				break;
			}
			
			set_time_limit(45); // Give this one call 45 seconds to return
			$ret = $this->schedule_one_set_ratings_ladder_try( $teams, $datestamp, $games_before_repeat, $j%2);
			
			if ($ret[0] == false) {
				echo ". ";
				flush();
				ob_flush();
				continue;
			}

			// Keep the best schedule by checking how many times we had to decrement
			// the games_before_repeat restriction in order to be able to generate
			// this schedule...
			
			// The best possible schedule will first have the smallest games before repeat sum,
			// then will have the smallest seed_closeness, and then will have the smallest ratings_closeness
			
			if (	( count($gbr_diff) == 0  ||  array_sum($gbr_diff) > array_sum($ret[3]) ) ||
					( array_sum($gbr_diff) == array_sum($ret[3]) && array_sum($seed_closeness) > array_sum($ret[4]) ) ||
					( array_sum($gbr_diff) == array_sum($ret[3]) && array_sum($seed_closeness) == array_sum($ret[4]) && array_sum($ratings_closeness) > array_sum($ret[5]) ) ) {
				$versus_team1 = $ret[1];
				$versus_team2 = $ret[2];
				$gbr_diff = $ret[3];
				$seed_closeness = $ret[4];
				$ratings_closeness = $ret[5];
				echo "! ";
			}

			// keep browser alive...
			echo ". ";
			flush();
			ob_flush();
			continue;
		}
		
		//TONY
		print "<br>";
		foreach($seed_closeness as $sc) {
			print "$sc ";
		}
		print "<br>";
		
		if ( array_sum($gbr_diff) == 0 ) {
			echo "Complete!</p>";
		} else {
			echo "Complete...</p>";
		}
		flush();
		ob_flush();

		// now, try to actually create the games
		$ret = $this->schedule_one_set_ratings_ladder_commit( $versus_team1, $versus_team2, $datestamp );
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
			print "<td><font color=$font>" . $versus_team1[$i]->name . "</font></td>";
			print "<td><font color=$font>" . $versus_team2[$i]->name . "</font></td>";
			print "<td><font color=$font>" . $seed_closeness[$i] . "</font></td>";
			print "<td align=center><font color=$font>" . $played . "</font></td>";
			print "</tr>\n";
		}
		print "</table>\n";
		print "</div>\n";


		print "</p><hr></div></body></html>";
		flush();
		ob_flush();

		return $ret;
	}

	/**
	* Schedule one set of games, using weighted field assignment
	* TODO: Need to modify this to balance home/away assignment as well.
	*
	* To do this:
	*   - modify to take array of team objects
	*   - have team object know its home/away ratio
	*/
	function schedule_one_set( $teams, $datestamp )
	{
		$rollback_list = array();
		while ($teams) {
			$g = new Game;
			$g->set('league_id', $this->league_id);
			$g->add_teams_balanced( array_shift($teams), array_shift($teams));
			if ( ! $g->save() ) {
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
					$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, during addition of opponents.$extra_errors");
			}
			$rollback_list[] = $g;

			list($rc, $message) = $g->select_weighted_gameslot($datestamp);
			if( ! $rc ) { 
				/* Argh, something failed, so roll back the whole pile of
				* games */
				foreach( $rollback_list as $to_rollback ) {
					if( ! $to_rollback->delete() ) {
						$extra_errors = "<br />Also, failed to delete failed games correctly.  Please contact the system administrator";
					}
				}
				return array(false, "Could not create the games you requested, most likely due to an insufficient number of available fields: $message $extra_errors");
			}
		}

		return array(true,'');
	}

	function sort_league_teams () {
		$this->load_teams();
		if ($this->schedule_type == 'ratings_ladder' || $this->schedule_type == 'ratings_wager_ladder' ) {
			uasort($this->teams, array($this, 'standings_sort_rating_ladder'));	
		} else if ($this->schedule_type == 'ladder' || $this->schedule_type == 'pyramid') {
			uasort($this->teams, array($this, 'standings_sort_ladder'));	
		} else {
			uasort($this->teams, array($this, 'standings_sort_bywinloss'));	
		}
	}

	function sanitize_ranks() {
		// don't do anything if this is not a pyramid ladder
		if ($this->schedule_type != 'pyramid') {
			return;
		}

		// sort the teams (by rank)
		$this->sort_league_teams();

		// get the teams array
		$teams = $this->teams;

		// prepare the value to subtract
		$tosubtract = null;

		// prepare a temporary teams array to transfer teams into while manipulating
		// their ranks while not changing anything in the back-end database!
		$tmp_teams = array();

		// loop through the teams
		foreach ($teams as $key => $team) {
			// do we have the value to subtract yet?
			if ($tosubtract == null) {
				$tosubtract = $team->rank - 1;
			}
			// make the rank a "normal" number:  1, 2, 3...  by subtracting the best rank minus 1
			$team->rank = $team->rank - $tosubtract;
			// store the team in the temporary array
			$tmp_teams[$key] = $team;
		}
		// overwrite the regular teams array with the temporary one
		$this->teams = $tmp_teams;

		return;
	}

	function sortLadderRating ( $season ) {
		uasort($season, array($this, 'standings_sort_rating_ladder'));
		return $season;
	}
      
	function sortLadder ( $season ) {

		// first sort by rank:
		uasort($season, array($this, 'standings_sort_ladder_byrankonly'));
		// now, copy the array and add rung_points and rung_points_diff for sorting...
		$rungs = array();
		foreach ($season as $key => $value) {
			$value->rung_points = 0;
			$value->rung_points_diff = 0;
			$rungs[$key] = $value;
		}
		reset ($season);
		reset ($rungs);

		// now do some parsing to fill in the rung_points and rung_points_diff:
		$new_teams_array = array();
		$one_rung = array();
		$current_rung = -1;
		foreach ($rungs as $key => $value) {
			if ($current_rung == -1) {
				$current_rung = $value->rank;
			}
			if ($value->rank == $current_rung) {
				$one_rung[$key] = $value;
			} else {
				if ( sizeof($one_rung) > 1 ) {
					$one_rung = $this->getRungPoints($one_rung);
				}
				foreach ($one_rung as $subkey => $subvalue) {
					$new_teams_array[$subkey] = $subvalue;
				}
				$one_rung = array();
				$one_rung[$key] = $value;
				$current_rung = $value->rank;
			}
		}
		// end of loop, but have to finish off the last one:
		if ( sizeof($one_rung) > 1 ) {
			$one_rung = $this->getRungPoints($one_rung);
		}
		foreach ($one_rung as $subkey => $subvalue) {
			$new_teams_array[$subkey] = $subvalue;
		}

		return $new_teams_array;
	}

	function getRungPoints( $one_rung ) {
		$fixed_rungPoints = array();
		foreach ( $one_rung as $key => $value ) {
			foreach ( $one_rung as $subkey => $subvalue ) {
				if ($key == $subkey) {
					continue;
				}
				if (isset($value->vs[$subkey])) {
					$value->rung_points += $value->vs[$subkey];
				}
			}
			$fixed_rungPoints[$key] = $value;
		}
		uasort($fixed_rungPoints, array($this, 'standings_sort_ladder'));

		// now look into the equal rung points to set the rung_points_diff
		$new_fixed_rungs = array();
		$one_rungPoints = array();
		$current_rungPoints = -1;
		foreach ($fixed_rungPoints as $key => $value) {
			if ($current_rungPoints == -1) {
				$current_rungPoints = $value->rung_points;
			}
			if ($value->rung_points == $current_rungPoints) {
				$one_rungPoints[$key] = $value;
			} else {
				if ( sizeof($one_rungPoints) > 1 ) {
					$one_rungPoints = $this->getRungPointsDiff($one_rungPoints);
				}
				foreach ($one_rungPoints as $subkey => $subvalue) {
					$new_fixed_rungs[$subkey] = $subvalue;
				}
				$one_rungPoints = array();
				$one_rungPoints[$key] = $value;
				$current_rungPoints = $value->rung_points;
			}
		}
		// end of loop, but finish off the last one:
		if ( sizeof($one_rungPoints) > 1 ) {
			$one_rungPoints = $this->getRungPointsDiff($one_rungPoints);
		}
		foreach ($one_rungPoints as $subkey => $subvalue) {
			$new_fixed_rungs[$subkey] = $subvalue;
		}

		uasort($new_fixed_rungs, array($this, 'standings_sort_ladder'));
		return $new_fixed_rungs;
	}
      
	function getRungPointsDiff( $one_rungPoints ) {
		$fixed_rungPointsDiff = array();
		foreach ( $one_rungPoints as $key => $value ) {
			foreach ( $one_rungPoints as $subkey => $subvalue ) {
				if ($key == $subkey) {
					continue;
				}
				if (isset($value->vspm[$subkey])) {
					$value->rung_points_diff += $value->vspm[$subkey];
				}
			}
			$fixed_rungPointsDiff[$key] = $value;
		}
		uasort($fixed_rungPointsDiff, array($this, 'standings_sort_ladder'));
		return $fixed_rungPointsDiff;
	}

}

function league_query( $array = array() )
{
	global $dbh;
	$order = '';
	$query = array();
	$params = array();

	foreach ($array as $key => $value) {
		switch( $key ) {
		case '_order':
			$order = ' ORDER BY ' . $value;
			break;
		default:
			$query[]  = "l.$key = ?";
			$params[] = $value;
		}
	}

	$sth = $dbh->prepare("SELECT l.*, 1 as _in_database FROM league l WHERE " . implode(' AND ',$query) . $order);
	$sth->execute($params);
	return $sth;
}

function league_load( $array = array() )
{
	$result = league_query( $array );
	return $result->fetchObject('League');
}

function league_load_many( $array = array() )
{
	$sth = league_query( $array );

	$leagues = array();
	while($l = $sth->fetchObject('League') ) {
		$leagues[$l->league_id] = $l;
	}

	return $leagues;
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

// seed with microseconds
function make_seed()
{
  list($usec, $sec) = explode(' ', microtime());
  return (float) $sec + ((float) $usec * 100000);
}
?>
