<?php
class League extends LeaguerunnerObject
{
	var $_teams_loaded;
	var $coordinators;
	var $teams;

	function League ()
	{
		$this->LeaguerunnerObject(); // Upcall to parent
		
		$this->_teams_loaded = false;
		$this->coordinators = array();
		$this->teams = array();
	}
	
	/**
	 * Load a single league object from the database using the 
	 * supplied query data.  If more than one  matches, we will return only
	 * the first one.  If fewer than one matches, this object remains empty.
 	 * @param	array 	$array key-value pairs that identify the league to be loaded.
 	 */
	function load ( $array = array() )
	{
		$query = array();
		
		foreach ($array as $key => $value) {
			if($key == '_extra') {
				/* Just slap on any extra query fields desired */
				$query[] = $value;
			} else {
				$query[] = "l.$key = '" . check_query($value) . "'";
			}
		}
		
		$result = db_query_range("SELECT 
			l.*
			FROM league l
			WHERE " . implode(' AND ',$query),0,1);

		/* TODO: we may want to abort here instead */
		if(1 != db_num_rows($result)) {
			return false;
		}
		
		$this->_in_database = true;

		$league = db_fetch_array($result);
		foreach ($league as $key => $value) {
			$this->{$key} = $value;
		}

		$result = db_query("SELECT 
			m.player_id
			FROM leaguemembers m
			WHERE m.league_id = %d", $this->league_id);
		
		while( $array = db_fetch_array($result) ) {
			$c = person_load( array('user_id' => $array['player_id']) );
			if( ! $c ) {
				error_exit("Invalid coordinator listed for league $league->league_id");
			}
			$c->coordinator_status = 'loaded';
			$this->coordinators[$c->user_id] = $c;
		}

		/* set derived attributes */
		if($this->tier) {
			$this->fullname = sprintf("$this->name Tier %02d", $this->tier);
		} else {
			$this->fullname = $this->name;
		}

		return true;
	}

	/**
	 * Check if this league contains a particular team.  There are 
	 * two modes of operation, to take advantage of team data if we
	 * already have it.
	 */
	function contains_team( $team_id ) 
	{
		if($this->_teams_loaded) {
			return array_key_exists( $team_id , $this->teams );
		}

		// Otherwise, we have to check directly
		$result = db_query("SELECT team_id FROM leagueteams WHERE league_id = %d AND team_id = %d", $this->league_id, $team_id);
		
		return (db_result($result) == $team_id);
	}

	/**
	 * Load teams for this league.
	 */
	function load_teams ()
	{
		if($this->_teams_loaded) {
			return true;
		}

		$this->teams = team_load_many( array('_extra' => 'l.league_id = ' . check_query($this->league_id), '_order' => 's.rank'));

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
		$result = db_result(db_query("SELECT COUNT(*) from schedule where league_id = %d", $this->league_id));
		return ($result > 0);
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
		$result = db_query("SELECT s.round FROM schedule s, gameslot g WHERE s.league_id = %d AND (NOT ISNULL(s.round)) AND g.game_id = s.game_id AND ( UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) < UNIX_TIMESTAMP(NOW())) ORDER BY g.game_date DESC LIMIT 1", $this->league_id);
		$round = db_result($result);
		if( $round != $this->current_round ) {
			$this->set('current_round',$round);
			return $this->save();
		}
		return true;
	}

	/**
	 * Finalize any games considered 'old'.  For now, 'old' is 24 hours after
	 * the end of the previous game. 
	 */
	function finalize_old_games()
	{
		$result = db_query("SELECT DISTINCT s.game_id, UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) AS start_timestamp FROM schedule s, gameslot g WHERE s.league_id = %d AND g.game_id = s.game_id AND UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) < UNIX_TIMESTAMP(NOW()) AND (ISNULL(s.home_score) OR ISNULL(s.away_score))", $this->league_id);
		while( $foo = db_fetch_array($result) ) {
			$id = $foo['game_id'];
#			print "$id is possibly 'old' and in need of approval<br />";

			// Expiry is 36 hours after game end, so we'll say 39 hours after start.  This is because for
			// summer games we may just have 'dark' listed rather than an end time.
			$expiry_factor = 39 * 3600; 
			if ( ($foo['start_timestamp'] + $expiry_factor) < time() ) {
				$game = game_load( array('game_id' => $id) );
				$game->finalize();
			}
		}
	}

	function cleanupgames ()
	{
		return true;
	}

	//  This function clears all of the scores and rankings in a given round.
	function cleanround( $roundNumber ) 
	{
		return false;
	}

	# TODO: add_team and remove_team, same as add and remove coordinator.

	function save ()
	{
		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create user account");
			}
		}

		if( count($this->_modified_fields) > 0 ) {
			$fields      = array();
			$fields_data = array();

			foreach ( $this->_modified_fields as $key => $value) {
				if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
					$fields[] = "$key = %s";
					$fields_data[] = 'NULL';
				} else {
					$fields[] = $key . " = " . $this->get_placeholder($key, $this->{$key});
					$fields_data[] = $this->{$key};
				}
			}
		
			if(count($fields_data) != count($fields)) {
				error_exit("Internal error: Incorrect number of fields set");
			}
		
			$sql = "UPDATE league SET ";
			$sql .= join(", ", $fields);	
			$sql .= " WHERE league_id = %d";

			$fields_data[] = $this->league_id;

			db_query( $sql, $fields_data);
			# Affecting zero rows is possible
			if(1 < db_affected_rows()) {
				error_exit("Internal error: Strange number of rows affected");
			}
		}

		foreach( $this->coordinators as $coord ) {
			switch( $coord->coordinator_status ) {
				case 'add':
					db_query("INSERT into leaguemembers (league_id, player_id, status) VALUES (%d,%d,'coordinator')", $this->league_id, $coord->user_id);
					$this->coordinators[$coord->user_id]->coordinator_status = 'loaded';
					break;
				case 'delete':	
					db_query("DELETE FROM leaguemembers WHERE league_id = %d AND player_id = %d", $this->league_id, $coord->user_id);
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
		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->name ) {
			return false;
		}
		
		db_query("INSERT into league (name) VALUES('%s')", $this->name);
		if( 1 != db_affected_rows() ) {
			return false;
		}
		$this->league_id = db_result(db_query("SELECT LAST_INSERT_ID() from league"));

		return true;
	}

	function get_placeholder( $key, $value )
	{
		if(0 == strcasecmp($value,'NULL')) {
			return "%s";
		}

		// Hack for NOW() timestamps
		if(0 == strcasecmp($value,'NOW()')) {
			return "%s";
		}
	
		switch($key) {
			case 'league_id':
			case 'tier':
			case 'current_round':
			case 'year':
				return "%d";
			default:
				return "'%s'";
		}
	}

	/**
	 * Calcluates the SBF ("Spence" or "Sutton" Balance Factor.
	 * This is the average of all score differentials for games played
	 * to-date.  A lower value indicates a more evenly matched league.
	 */
	function calculate_sbf()
	{
		$sbf = db_result(db_query("SELECT ROUND(AVG(ABS(s.home_score - s.away_score)), 2) FROM schedule s WHERE s.league_id = %d", $this->league_id));

		if( $sbf == "") {
			$sbf = "n/a";
		}

		return $sbf;
	}

	/** DELETE GAMES FUNCTION **/
	function cancelround ( $round ) {

		// make sure the teams are loaded
		if (! $this->load_teams() ) {
			return false;
		}

		$number_of_teams = count($this->teams);
		$game_set = $number_of_teams / 2;

		// get the schedule
		$result = game_query ( array( 'league_id' => $this->league_id, '_order' => 'g.game_date, g.game_id, g.game_start') );
		if( ! $result ) {
			return false;
		}

		// Super important!  We make a back-up copy of all the game objects in case we have to rollback!
		$backup_games = array();
		$games = array();
		$changed_games = array();
		while($game = db_fetch_array($result)) {
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
	function set_dependants ($games, $number_of_teams) {

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
		$result = db_query(
			"SELECT 
				s.slot_id AS slot_id,
				IF( f.parent_fid, 
					CONCAT_WS(' ', s.game_start, p.name, f.num),
					CONCAT_WS(' ', s.game_start, f.name, f.num)
				) AS value
			 FROM
				gameslot s 
				INNER JOIN field f ON (s.fid = f.fid) 
				LEFT JOIN field p ON (p.fid = f.parent_fid) 
				LEFT JOIN league_gameslot_availability a ON (s.slot_id = a.slot_id)
				LEFT JOIN schedule g ON (s.game_id = g.game_id) 
			 WHERE 
				UNIX_TIMESTAMP(s.game_date) = %d
				AND ( 
					(a.league_id=%d AND ISNULL(s.game_id)) 
					OR
					g.league_id=%d
				)
			 ORDER BY s.game_start, value", $timestamp, $this->league_id,$this->league_id);
		
		$gameslots[0] = "---";
		while($slot = db_fetch_object($result)) {
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
		while(list($id,) = each($this->teams)) {
			$this->teams[$id]->points_for = 0;
			$this->teams[$id]->points_against = 0;
			$this->teams[$id]->spirit = 0;
			$this->teams[$id]->win = 0;
			$this->teams[$id]->loss = 0;
			$this->teams[$id]->tie = 0;
			$this->teams[$id]->defaults_for = 0;
			$this->teams[$id]->defaults_against = 0;
			$this->teams[$id]->games = 0;
			$this->teams[$id]->vs = array();
			$this->teams[$id]->streak = array();
		}

		$season = $this->teams;
		if( $current_round ) {
			$round  = $this->teams;
		}

		/* Now, fetch the schedule.  Get all games played by anyone who is
		 * currently in this league, regardless of whether or not their
		 * opponents are still here
		 */
		// TODO: I'd like to use game_load_many here, but it's too slow.
		$result = db_query(
			"SELECT DISTINCT s.*, 
				s.home_team AS home_id, 
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			FROM schedule s, leagueteams t
			LEFT JOIN team h ON (h.team_id = s.home_team) 
			LEFT JOIN team a ON (a.team_id = s.away_team)
			WHERE t.league_id = %d 
				AND NOT ISNULL(s.home_score) AND NOT ISNULL(s.away_score) AND (s.home_team = t.team_id OR s.away_team = t.team_id) ORDER BY s.game_id", $this->league_id);
		while( $ary = db_fetch_array( $result) ) {
			$g = new Game;
			$g->load_from_query_result($ary);
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
		if ($this->schedule_type == "ladder") {
			uasort($season, array($this, 'standings_sort_byrank'));	

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

		$game->home_spirit = $game->get_spirit_numeric( $game->home_team );
		$game->away_spirit = $game->get_spirit_numeric( $game->away_team );
		if(isset($season[$game->home_team])) {
			$team = &$season[$game->home_team];
			
			$team->games++;
			$team->points_for += $game->home_score;
			$team->points_against += $game->away_score;
			$team->spirit += $game->home_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->away_team])) {
				$team->vs[$game->away_team] = 0;
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
				$status = 'T';
			} else if($game->home_score > $game->away_score) {
				$team->win++;
				$team->vs[$game->away_team] += 2;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->away_team] += 0;
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
			$team->spirit += $game->away_spirit;

			/* Need to initialize if not set */
			if(!isset($team->vs[$game->home_team])) {
				$team->vs[$game->home_team] = 0;
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
				$status = 'T';
			} else if($game->away_score > $game->home_score) {
				$team->win++;
				$team->vs[$game->home_team] += 2;
				$status = 'W';
			} else {
				$team->loss++;
				$team->vs[$game->home_team] += 0;
				$status = 'L';
			}
			if(in_array($status, $team->streak)) {
				array_push($team->streak, $status);
			} else {
				$team->streak = array($status);
			}
		}
	}
	
	function standings_sort_byrank (&$a, &$b) 
	{
		if ($a->rank == $b->rank) {
			return 0;
		}
		return ($a->rank < $b->rank) ? -1 : 1;
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
		if(isset($b->vs[$a['id']]) && isset($a->vs[$b['id']])) {
			if( $b->vs[$a['id']] > $a->vs[$b['id']]) {
				return 1;
			} else if( $b->vs[$a['id']] < $a->vs[$b['id']]) {
				return -1;
			}
		}

		/* Check SOTG */
		if($a->games > 0 && $b->games > 0) {
			if( ($a->spirit / $a->games) > ($b->spirit / $b->games)) {
				return -1;
			} else if( ($a->spirit / $a->games) < ($b->spirit / $b->games)) {
				return 1;
			}
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

}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function league_load( $array = array() )
{
	$l = new League;
	if($l->load($array)) {
		return $l;
	} else {
		return null;
	}
}

?>
