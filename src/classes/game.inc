<?php
class Game extends LeaguerunnerObject
{

	var $_score_entries;
	var $_spirit_entries;

	/**
	 * Load a single game object from the database using the supplied query
	 * data.  If more than one matches, we will load only the first one.  If
	 * fewer than one matches, this object remains empty.
	 *
	 * @param	mixed 	$array key-value pairs that identify the team to be loaded.
	 */
	function load ( $array = array() )
	{
		$result = game_query( &$array );

		if(1 != db_num_rows($result)) {
			return false;
		}

		return $this->load_from_query_result( db_fetch_array($result) );
	}

	/** 
	 * Populate a game object, given an array result generated from
	 * game_query()
	 */
	function load_from_query_result ( $array = array() )
	{
		
		foreach ($array as $key => $value) {
			$this->{$key} = $value;
		}

		$this->_in_database = true;

		// Fixups
		if( ! $this->game_end ) {
			$this->game_end = 'dark';
		}

		$this->load_score_entries();

		return true;
	}

        function calculate_winner_loser() 
        {

          if ((int)$this->home_score > (int)$this->away_score) {
            $result['winner'] = $this->home_team;
            $result['loser']  = $this->away_team;
          }
          else if ((int)$this->home_score < (int)$this->away_score) {
            $result['winner'] = $this->away_team;
            $result['loser']  = $this->home_team;
          }
          else if ($this->home_score == $this->away_score) {

            // Is this a tie game that wasn't played?
            if (($this->home_team + $this->away_team) % 2) {

              $result['winner'] = $this->home_team;
              $result['loser']  = $this->away_team;
            }
            else {
              $result['winner'] = $this->away_team;
              $result['loser']  = $this->home_team;
            }
          }
          else {
            error_exit("Unable to determine a win/loss/tie for this game!");
          }
          return $result;
             
        }

        // This function must only be called after a save has
        // been performed on this game object.
        // Also where this function is called from must already
        // confirm that the scores have been finalized.

		// TODO: perhaps call from $game->save() instead of requiring an
		// explicit call in the handler?

        function updatedependentgames() {

           // Need to make sure this is a ladder league.
            
           $league = league_load( array('league_id' => $this->league_id) );
           if(! $league ) {
              error_exit("That league does not exist");
           }

           // Just fail out with everthing being good if this
           // isn't a ladder league.
           if($league->schedule_type != "ladder") {
             return true;
           }

           //print($this->sprintf("debug"));

          
           // Lets get the game objects.
           $queryResult = db_query("SELECT * FROM schedule WHERE home_dependant_game = %d OR away_dependant_game = %d",
                    $this->game_id,
                    $this->game_id);

			$num_rows = db_num_rows($queryResult);
			if( $num_rows == 0 ) {
				// No dependant games; that's OK, nothing more to do
				return true;
			}

            // Should only ever get two games;
            if ($num_rows != 2) {
              error_exit("Internal error: Found $num_rows dependant games, expecting 2");
            } 

            // Figure out winner and loser.
            $winlose = $this->calculate_winner_loser();
            $winnerTeamId = $winlose['winner'];
            $loserTeamId  = $winlose['loser'];


            $dbQueryPre  = "UPDATE schedule SET ";
            $dbQueryPost = " WHERE game_id = ";
        
            // Now we can figure out which team the
            // dependant games are looking for. 
            while( $row = db_fetch_array( $queryResult ) ) {

              $gameIdToUpdate = $row['game_id']; 
              $dbQuery = $dbQueryPre;

              if ($row['home_dependant_game'] == $this->game_id) {

                $teamRank = $row['home_dependant_rank'];

                if ($row['home_dependant_type'] == "winner") {
                  $teamId   = $winnerTeamId;
                }
                else if ($row['home_dependant_type'] == "loser") {
                  $teamId   = $loserTeamId;
                }
                else {
                  error_exit("Should never get here. Bad dependent type.");
                }

                // Update the database.
                db_query(
                  "UPDATE schedule SET home_team = %d WHERE game_id = %d ",
                  $teamId, $gameIdToUpdate);

                db_query(
                  "UPDATE leagueteams SET rank = %d WHERE league_id = %d " . 
                  "AND team_id = %d",
                  $teamRank, $league->league_id, $teamId);
              }

              if ($row['away_dependant_game'] == $this->game_id) {

                $teamRank = $row['away_dependant_rank'];

                if ($row['away_dependant_type'] == "winner") {
                  $teamId   = $winnerTeamId;
                }
                else if ($row['away_dependant_type'] == "loser") {
                  $teamId   = $loserTeamId;
                }
                else {
                  error_exit("Should never get here. Bad dependent type.");
                }

                // Update the database.
                db_query(
                  "UPDATE schedule SET away_team = %d WHERE game_id = %d ",
                  $teamId, $gameIdToUpdate);

                db_query(
                  "UPDATE leagueteams SET rank = %d WHERE league_id = %d " . 
                  "AND team_id = %d",
                  $teamRank, $league->league_id, $teamId);
              }

            }


            // It's all good.
            return true;
        }
	
	function save ()
	{
		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}
	
		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create new game");
			}
		}
	
		$fields      = array();
		$fields_data = array();

		// First, perform some evil.  Depending on game status, we may wish to
		// override some values. But, we only want to do this when we're
		// changing the status.
		if( array_key_exists('status', $this->_modified_fields) ) {
			switch( $this->status ) {
				case 'home_default':
					$this->set('home_score', 0);
					$this->set('away_score', 6);
					break;
				case 'away_default':
					$this->set('home_score', 6);
					$this->set('away_score', 0);
					break;
				case 'forfeit':
					$this->set('home_score', 0);
					$this->set('away_score', 0);
					break;
				case 'rescheduled':
					// TODO: Should we mangle the scores for a rescheduled
					// game?
					break;
				case 'cancelled':
					$this->set('home_score', null);
					$this->set('away_score', null);
					break;
				case 'normal':
				default;
					break;
			};
		}

		foreach ( $this->_modified_fields as $key => $value) {
			if( $key == 'slot_id' ) {
				continue;
			}
		
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
		if(count($fields)) {
			$sql = "UPDATE schedule SET ";
			$sql .= join(", ", $fields);	
			$sql .= " WHERE game_id = %d";

			$fields_data[] = $this->game_id;

			db_query( $sql, $fields_data);
			if(1 < db_affected_rows()) {
				# Affecting zero rows is possible
				error_exit("Internal error: Strange number of rows affected");
			}
		}

		// Now deal with slot_id
		if( array_key_exists('slot_id', $this->_modified_fields) ) {
			db_query("UPDATE gameslot SET game_id = %d WHERE slot_id = %d", $this->game_id, $this->slot_id);
			if(1 != db_affected_rows()) {
				return false;
			}
		}

		// And, remove any old score_entry for this game if we've entered a
		// final score
		if( array_key_exists('home_score', $this->_modified_fields) ) {
			db_query("DELETE FROM score_entry WHERE game_id = %d",$this->game_id);
		}

		// Save elo rating, if we're just adding a new score
		$this->save_elo_rating();
		
		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->league_id ) {
			return false;
		}
		
		db_query("INSERT into schedule (league_id) VALUES(%d)", $this->league_id);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->game_id = db_result(db_query("SELECT LAST_INSERT_ID() from schedule"));

		$this->_in_database = true;
		
		return true;
	}
	

	function delete()
	{
		if( ! $this->_in_database ) {
			return false;
		}

		// TODO: There may be other things that should prevent deletion
		if( $this->home_score || $this->away_score ) {
			$this->error_exit("Cannot delete games which have been scored");
		}

		// Don't check result; there may not be any to change
		db_query("UPDATE gameslot SET game_id = NULL where game_id = %d", $this->game_id);

		// Remove game from schedule
		db_query("DELETE FROM schedule WHERE game_id = %d", $this->game_id);
		return (1 == db_affected_rows());
	}

	/** 
	 * Return string-formatted game info in 'standard' formats
	 */
	function sprintf ( $format = 'short', $desired_team = NULL )
	{
		switch($format) {
			case 'debug':
				$output = "<pre>--- \n"
					. "GAME_ID:       $this->game_id \n"
					. "LEAGUE_ID:     $this->league_id \n"
					. "ROUND:         $this->round \n"
					. "DATE:          $this->game_date \n"
					. "START:         $this->game_start \n"
					. "FIELDCODE:     $this->field_code \n"
					. "HOME_TEAM:     $this->home_team \n"
					. "HOME_NAME:     $this->home_name \n"
					. "HOME_SCORE:    $this->home_score \n"
					. "HOME_DEP_GAME: $this->home_dependant_game \n"
					. "HOME_DEP_TYPE: $this->home_dependant_type \n"
					. "HOME_DEP_RANK: $this->home_dependant_rank \n"
					. "AWAY_TEAM:     $this->away_team \n"
					. "AWAY_NAME:     $this->away_name \n"
					. "AWAY_SCORE:    $this->away_score \n"
					. "AWAY_DEP_GAME: $this->away_dependant_game \n"
					. "AWAY_DEP_TYPE: $this->away_dependant_type \n"
					. "AWAY_DEP_RANK: $this->away_dependant_rank \n</pre>\n";
				break;
			case 'vs':
				$output = "$this->game_date $this->game_start at " 
					. l($this->field_code, "field/view/$this->fid")
					. " vs. ";
				if( $this->home_id == $desired_team ) {
					$output .= l($this->away_name, "team/view/$this->away_id");
					if($this->home_score || $this->away_score) {
						$output .= " ($this->home_score  - $this->away_score )";
					}
				} else if( $this->away_id == $desired_team ) {
					$output .= l($this->home_name, "team/view/$this->home_id");
					if($this->home_score || $this->away_score) {
						$output .= " ($this->away_score - $this->home_score )";
					}
				}
				break;
			case 'short':
			default:
				$output = "$this->game_date $this->game_start at $this->field_code";
				break;	
		}
		
		return $output;
	}

	/**
	 * Check if this game has been finalized
	 * Currently, game is considered finalized if both scores have been
	 * entered.
	 */
	function is_finalized ( )
	{
		if( ! $this->_in_database ) {
			die( "Cannot check finalization for game not in database!");
		}

		if( isset($this->home_score) && isset($this->away_score) ) {
			return true;
		}

		return false;
	}

	/**
	 * Load any entered scores for this game.  Returns false if none
	 * could be loaded, true if one or more loaded.
	 */
	function load_score_entries ( )
	{
		if( ! $this->_in_database ) {
			die( "Cannot load entered scores for game not in database!");
		}

		$result = db_query("SELECT * from score_entry WHERE game_id = %d", $this->game_id);
		if( db_num_rows($result) > 2 ) {
			die("Too many score entries in DB for game $game->game_id");
		}
		
		if( db_num_rows($result) > 0 ) {
			while( $entry = db_fetch_object($result) ) {
				$this->_score_entries[ $entry->team_id ] = $entry;
			}
		}

		$this->load_spirit_entries();

		return true;
	}

	function load_spirit_entries ( )
	{
		if( ! $this->_in_database ) {
			die( "Cannot load entered scores for game not in database!");
		}
		$result = db_query("SELECT tid,qkey,akey FROM team_spirit_answers WHERE gid = %d", $this->game_id);
		while( $row = db_fetch_array( $result ) ) {
			$this->_spirit_entries[ $row['tid'] ][ $row['qkey'] ] = $row['akey'] ;
		}

		return true;
	}

	/** 
	 * Retrieve score entry for given team 
	 * returns value, or false otherwise
	 */
	function get_score_entry( $team_id )
	{
		if( ! $this->_in_database ) {
			die( "Cannot get entered scores for game not in database!");
		}

		if( !is_array($this->_score_entries) ) {
			// No entries
			return false;
		}
	
		if( array_key_exists( $team_id, $this->_score_entries) ) {
			return $this->_score_entries[ $team_id ]; 
		}

		return false;
	}

	/**
	 * Save a score entry for a given team
	 * This writes directly to the database
	 */
	function save_score_entry ( $team_id, $user_id, $score_for, $score_against, $defaulted )
	{
		$entry->team_id = $team_id;
		$entry->entry_id = $this->entry_id;
		$entry->entered_by = $user_id;
		$entry->defaulted = $defaulted;

		switch ($defaulted) {
			case 'us':
				$score_for = 0;
				$score_against = 6;
				break;
			case 'them':
				$score_against = 0;
				$score_for = 6;
				break;
			case 'no':
			default:
				break;
		}
		
		$entry->score_for = $score_for;
		$entry->score_against = $score_against;

		$this->_score_entries[$team_id] = $entry;

		// Save entry object in DB.  Use 'replace into' to handle the case
		// where one might already exist.
		db_query("REPLACE INTO score_entry (game_id,team_id,entered_by,score_for,score_against,defaulted) VALUES(%d,%d,%d,%d,%d,'%s')",
			$this->game_id,
			$entry->team_id,
			$entry->user_id,
			$entry->score_for,
			$entry->score_against,
			$entry->defaulted);
			
	    if( 1 != db_affected_rows() ) {
	        return false;
	    }

		return true;		
	}
	
	/** 
	 * Retrieve spirit entry for given team 
	 * returns value, or false otherwise
	 */
	function get_spirit_entry( $team_id )
	{
		if( ! $this->_in_database ) {
			die( "Cannot get entered spirit for game not in database!");
		}

		if( !is_array($this->_spirit_entries) ) {
			// No entries
			return false;
		}
	
		if( array_key_exists( $team_id, $this->_spirit_entries) ) {
			return $this->_spirit_entries[$team_id]; 
		}

		return false;
	}

	/**
	 * Calculates a numeric value for the spirit associated with the
	 * given entry
	 */
	function get_spirit_numeric( $team_id )
	{
		$entry = $this->get_spirit_entry($team_id);
		if( !$entry ) {
			return 0;
		}

		// Start with 10 spirit, subtract down
		$spirit = 10;
		while( list($qkey, $answer) = each($entry) ) {
			$result = db_query("SELECT value from multiplechoice_answers WHERE qkey = '%s' AND akey = '%s'", $qkey, $answer);

			$spirit += db_result($result);
		}

		return $spirit;
	}

	/**
	 * Save a spirit entry for the given team
	 */
	function save_spirit_entry ( $team_id, $spirit )
	{

		if( !is_array($spirit) ) {
			die("Spirit argument to save_spirit_entry() must be an array");
		}
	
		// Store in object
		$this->_spirit_entries[$team_id] = $spirit;

		$opponent_id = $this->get_opponent_id( $team_id );
		
		// save in DB
		while( list($qkey, $answer) = each($spirit) ) {
			db_query("REPLACE INTO team_spirit_answers (tid_created,tid,gid,qkey,akey) VALUES (%d,%d,%d,'%s','%s')", 
				$opponent_id,
				$team_id,
				$this->game_id,
				$qkey,
				$answer
			);
	    	if( db_affected_rows() < 1) {
	      		return false;
	    	}
		}
		
		return true;		
	}

	/**
	 * Return values to be used for winner/loser of a default
	 * game.
	 */
	function default_spirit ( $type )
	{
		switch( $type ) {
			case 'winner':
				return array(
					'Timeliness' => 'OnTime',
					'RulesKnowlege' => 'AcceptableRules',
					'Sportsmanship' => 'AcceptableSportsmanship',
					'Enjoyment' => 'MostEnjoyed',
					'GameOverall' => 'OverallAverage'
				);
				break;
			case 'loser':
				return array(
					'Timeliness' => 'MoreThanFive',
					'RulesKnowlege' => 'AcceptableRules',
					'Sportsmanship' => 'AcceptableSportsmanship',
					'Enjoyment' => 'FewEnjoyed',
					'GameOverall' => 'OverallAverage'
					
				);
				break;
			default:
				die("Invalid type $type given to default_spirit()");
		}
	}

	/**
	 * Given the ID for one team in this game, return the ID of the other.
	 */
	function get_opponent_id ( $team_id )
	{
		if( $this->home_id == $team_id ) {
			return $this->away_id;
		} else if ( $this->away_id == $team_id ) {
			return $this->home_id;
		} else {
			die("Attempt to identify opponent of a team that didn't play in this game");
		}
	}

	/**
	 * Compare two score entries
	 */
	function score_entries_agree ( $one, $two )
	{

		if(
			($one['defaulted'] == 'us' && $two['defaulted'] == 'them') 
			||
			($one['defaulted'] == 'them' && $two['defaulted'] == 'us')
		) {
			return true;
		} 

		if(! (($one['defaulted'] == 'no') && ($two['defaulted'] == 'no'))) {
			return false;
			
		}

		if(($one['score_for'] == $two['score_against']) && ($one['score_against'] == $two['score_for']) ) {
			return true;
		} 

		return false;
	}

	/**
	 * Calculate the expected win ratio of the home team.  Answer
	 * is always 0 <= x <= 1
	 */
	function home_expected_win()
	{
		$difference = $this->home_rating - $this->away_rating;
		$power = pow(10, (0 - $difference) / 400);
		return ( 1 / ($power + 1) );
	}
	
	/**
	 * Calculate the expected win ratio of the away team.  Answer
	 * is always 0 <= x <= 1
	 */
	function away_expected_win()
	{
		return 1 - $this->home_expected_win();
	}

	/** 
	 * Calculate the value to be added/subtracted from the competing
	 * teams' ratings. 
	 * This uses a modified Elo system, similar to the one used for
	 * international soccer (http://www.eloratings.net) with several
	 * modifications:
	 * 	- all games are equally weighted
	 * 	- score differential bonus adjusted for Ultimate patterns (ie: a 3
	 * 	  point win in soccer is a much bigger deal than in Ultimate
	 * 	- no bonus given for home-field advantage
	 *
	 */
	function save_elo_rating ( )
	{
		if( $this->rating_points ) {
			// If we already have a rating, don't recalculate it.
			// TODO: in the future, it might be nice if we can 
			// recalculate the rating if the score changes, but for
			// now we'll let the perl script do that 
			return true;
		}
	
		$weight_constant = 40;  // All games weighted equally
		$score_weight    = 1;   // Games start with a weight of 1

		$game_value      = 1;   // Game value is always 1 or 0.5 as we're calculating the elo change for the winning team

		// Find winning/losing scores.  In the case of a tie, 
		// the home team is considered the winner for purposes of
		// rating calculation.  This has nothing to do with the
		// tiebreakers used for standings purposes as in tie cases,
		// the $elo_change will work out the same regardless of which team is
		// considered the 'winner'
		if( $this->home_score >= $this->away_score) {
			$winner = $this->home_id;
			$loser = $this->away_id;
			$expected_win = $this->home_expected_win();
	    } else {
			$winner = $this->away_id;
			$loser = $this->home_id;
			$expected_win = $this->away_expected_win();
		}

		// Calculate score differential bonus.  
		// If the difference is greater than 1/3 the winning score, the bonus
		// added is the ratio of score difference over winning score.
		$score_diff = abs($this->home_score - $this->away_score);
		$score_max  = max($this->home_score, $this->away_score);
		if( $score_max && ( ($score_diff / $score_max) > 0.333 )) {
			$score_weight += $score_diff / $score_max;
		}

		$elo_change = $weight_constant * $score_weight * ($game_value - $expected_win);
		db_query("UPDATE schedule SET rating_points = %d WHERE game_id = %d", $elo_change, $this->game_id);
		if( 1 != db_affected_rows()) {
			return false;
		}
	
		db_query("UPDATE team SET rating = rating + %d WHERE team_id = %d", $elo_change, $winner);
		if( 1 != db_affected_rows()) {
			return false;
		}
		
		db_query("UPDATE team SET rating = rating - %d WHERE team_id = %d", $elo_change, $loser);
		if( 1 != db_affected_rows()) {
			return false;
		}

		return true;
	}

	/**
	 * Select a random gameslot for this game.
	 * Gameslot is to be selected from those available for the league in which
	 * this game exists.  
	 * Single argument is to be the timestamp representing the date of the
	 * game.
	 * Changes are made directly in the database, no need to call save()
	 * Returns success or fail, depending on whether or not we could get a
	 * gameslot.
	 */
	function select_random_gameslot( $timestamp )
	{
		if( $this->slot_id ) {
			error_exit("Cannot call select_random_gameslot() on a game with an existing gameslot value");
		}

		if( !$this->game_id ) {
			error_exit("Game must have a game_id before calling select_random_gameslot()");
		}

		if( !$this->league_id ) {
			error_exit("Game must have a league_id before calling select_random_gameslot()");
		}


		/*
		 * To keep this safe amongst possible multiple writers, we lock.  To
		 * keep the locks a bit more granular, we lock on the timestamp, so
		 * that we only protect against people trying to add games on the same
		 * day.
		 * TODO: Transactions might make this a bit nicer...
		 */
		$lockname = "schedule_lock_$timestamp";
		
		$result = db_query("SELECT GET_LOCK('$lockname',10)");	
		$lock = db_result($result);
		if(!isset($lock) || $lock == 0) {
			// Couldn't obtain lock after 10 seconds, so bail.
			return false;	
		}
		$result = db_query("SELECT s.slot_id FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = %d AND a.league_id = %d AND ISNULL(s.game_id) ORDER BY RAND() LIMIT 1", $timestamp, $this->league_id);
		$slot_id = db_result($result);
		if( ! $slot_id ) {
			db_query("SELECT RELEASE_LOCK('$lockname')");
			return false;
		}

		db_query("UPDATE gameslot SET game_id = %d WHERE ISNULL(game_id) AND slot_id = %d", $this->game_id, $slot_id);
		$gameslot_affected_rows = db_affected_rows();
		db_query("SELECT RELEASE_LOCK('$lockname')");
		if(1 != $gameslot_affected_rows) {
			return false;
		}
		$this->slot_id = $slot_id;
		return true;
	}
}

function game_query ( $array = array() )
{

	$order = '';
	$tables = array( 'schedule s');
	foreach ($array as $key => $value) {
		switch( $key ) {
			case 'game_date':
				$query[] = "g.game_date = '" . check_query($value) . "'";
				break;
			case 'game_date_past':
				$query[] = "g.game_date < CURDATE()";
				break;
			case 'game_date_future':
				$query[] = "g.game_date > CURDATE()";
				break;
			case 'either_team':
				$query[] = '(s.home_team = ' . check_query($value) . ' OR s.away_team = ' . check_query($value) . ')';
				break;
			case 'dependant_game':
				$query[] = '(s.home_dependant_game = ' . check_query($value) . ' OR s.away_dependant_game = ' . check_query($value) . ')';
				break;
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			case '_extra_table':
				array_push($tables, $value);
				break;
			default:
				$query[] = "s.$key = '" . check_query($value) . "'";
		}
	}

	return db_query("SELECT 
		s.*,
		IF(l.tier,CONCAT(l.name,' ',l.tier), l.name) AS league_name,
		s.home_team AS home_id,
		h.name AS home_name, 
		h.rating AS home_rating,
		s.away_team AS away_id,
		a.name AS away_name,
		a.rating AS away_rating,
		g.slot_id,
		g.game_date,
		TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
		TIME_FORMAT(g.game_end,'%H:%i') AS game_end,
		g.fid,
		UNIX_TIMESTAMP(g.game_date) as day_id,
		IF(f.parent_fid, CONCAT(pf.code, ' ', f.num), CONCAT(f.code, ' ', f.num)) AS field_code,
		UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) as timestamp
	FROM " . join(',', $tables) . 
		" INNER JOIN league l ON (l.league_id = s.league_id)
		LEFT JOIN gameslot g ON (g.game_id = s.game_id)
		LEFT JOIN field f ON (f.fid = g.fid)
		LEFT JOIN field pf ON (pf.fid = f.parent_fid)
		LEFT JOIN team h ON (h.team_id = s.home_team) 
		LEFT JOIN team a ON (a.team_id = s.away_team)
	WHERE " . implode(' AND ',$query) .  $order);
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function game_load( $array = array() )
{
	$g = new Game;
	if($g->load($array)) {
		return $g;
	} else {
		return null;
	}
}

function game_load_many( $array = array() )
{
	$result = game_query( &$array );

	if(db_num_rows($result) < 1 ) {
		return false;
	}

	$g = new Game;
	$games = array();
	while($ary = db_fetch_array($result)) {
		$g = new Game;
		if( $g->load_from_query_result( $ary ) ) {
			$games[$g->game_id] = $g;
		}
	}

	// take the LAST game, and check for dependent games
	$last_game = $g;
	$dep_array = array( 'dependant_game' => $last_game->game_id, '_order' => 'g.game_date');
	$result = game_query( &$dep_array );
	while ($ary = db_fetch_array($result) ) {
		$g = new Game;
		if ($g->load_from_query_result($ary) ) {
			$games[$g->game_id] = $g;
		}
	}

	return $games;
}

?>
