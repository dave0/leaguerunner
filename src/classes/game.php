<?php
// Define constants for use throughout
define("APPROVAL_AUTOMATIC", -1);		// approval, scores agree
define("APPROVAL_AUTOMATIC_HOME", -2);  // approval, home score used
define("APPROVAL_AUTOMATIC_AWAY", -3);  // approval, away score used
define("APPROVAL_AUTOMATIC_FORFEIT", -4); // approval, no score entered

class Game extends LeaguerunnerObject
{

	var $_score_entries;
	var $_spirit_entries;
	var $_home_team_object;
	var $_away_team_object;

	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{

		if( ! $this->_in_database ) {
			return true;
		}

		$this->load_score_entries();

		return true;
	}

	/**
	 * Game end time, for display purposes.
	 *
	 * If one is provided for this game, we use it.  Otherwise, we calculate based on sunset time.
	 */
	function display_game_end()
	{
		# Return our end time, if available
		if( $this->game_end ) {
			return $this->game_end;
		}

		# Otherwise, guess based on date
		if( $this->timestamp ) {
			return local_sunset_for_date( $this->timestamp );
		}

		return '';
	}

	function iso8601_local_game_start ()
	{
		return strftime('%Y%m%dT%H%M%S', $this->timestamp);
	}

	function iso8601_local_game_end ()
	{
		list($hh,$mm) = preg_split('/:/', $this->display_game_end());
		list($year,$mon, $mday) = preg_split('/-/', $this->game_date);
		return strftime(
			'%Y%m%dT%H%M%S',
			mktime($hh, $mm, 00, $mon, $mday, $year)
		);
	}

	/**
	 * Add two opponents to the game, attempting to balance the number of home
	 * and away games
	 * Note that we do NOT call save() within this function... the caller must
	 * do that.
	 */
	function add_teams_balanced( $a, $b)
	{
		$a_ratio = $a->home_away_ratio();
		$b_ratio = $b->home_away_ratio();

		// team with lowest ratio (fewer home games) gets to be home.
		if( $a_ratio < $b_ratio ) {
			$home = $a;
			$away = $b;
		} elseif( $a_ratio > $b_ratio ) {
			$home = $b;
			$away = $a;
		} else {
			// equal ratios... choose randomly.
			if( rand(0,1) > 0 ) {
				$home = $a;
				$away = $b;
			} else {
				$home = $b;
				$away = $a;
			}
		}

		$home->home_games++;
		$this->set('home_team', $home->team_id);
		$away->away_games++;
		$this->set('away_team', $away->team_id);

		// Keep track of our home and away objects to avoid extraneous queries in the future.
		$this->_home_team_object = $home;
		$this->_away_team_object = $away;
	}

	function get_away_team_object ()
	{
		if( is_null($this->_away_team_object) ) {
			if( $this->away_id ) {
				$this->_away_team_object = Team::load( array('team_id' => $this->away_id) );
			} else {
				return null;
			}
		}
		return $this->_away_team_object;
	}

	function get_home_team_object ()
	{
		if( is_null($this->_home_team_object) ) {
			if( $this->home_id ) {
				$this->_home_team_object = Team::load( array('team_id' => $this->home_id) );
			} else {
				return null;
			}
		}
		return $this->_home_team_object;
	}

	function calculate_winner_loser()
	{

		if ((int)$this->home_score > (int)$this->away_score) {
			$result['winner'] = $this->home_team;
			$result['loser']  = $this->away_team;
		} else if ((int)$this->home_score < (int)$this->away_score) {
			$result['winner'] = $this->away_team;
			$result['loser']  = $this->home_team;
		} else if ($this->home_score == $this->away_score) {
			// Is this a tie game that wasn't played?
			if (($this->home_team + $this->away_team) % 2) {
				$result['winner'] = $this->home_team;
				$result['loser']  = $this->away_team;
			} else {
				$result['winner'] = $this->away_team;
				$result['loser']  = $this->home_team;
			}
		} else {
			error_exit("Unable to determine a win/loss/tie for this game!");
		}
		return $result;
	}

	/**
	 * Take what is currently known about the game, and finalize it.
	 * If we have:
	 * 	0) no scores entered
	 * 		- forfeit game as 0-0 tie
	 * 		- give poor spirit to both
	 * 	1) one score entered
	 * 		- use single score as final
	 * 		- give full spirit to entering team, assigned spirit, less
	 * 		  some configurable penalty, to non-entering team.
	 * 	2) two scores entered, not agreeing
	 * 		- if there is an agreed winner, but the score itself is in
	 * 		  dispute, enter as final using the minimum score differential,
	 * 		  and assigned spirit.
	 * 		- if there is no agreed-upon winner, send email to the
	 * 		  coordinator(s).
	 * 		*** TODO: This is not how the code below works! It emails
	 * 			the coordinator(s) any time the scores don't agree.
	 * 			Which is preferred? Update either the code or the comment...
	 *  3) two scores entered, agreeing
	 *  	- scores are entered as provided, as are spirit values.
	 */
	function finalize()
	{
		global $dbh;

		if( $this->is_finalized() ) {
			return false;
		}

		$home_entry = $this->get_score_entry( $this->home_id );
		$away_entry = $this->get_score_entry( $this->away_id );
		if( $home_entry && $away_entry ) {
			if( $this->score_entries_agree( (array)$home_entry, (array)$away_entry) ) {
				switch( $home_entry->defaulted ) {
					case 'us':
						// HOME default
						$this->set('status', 'home_default');
						break;
					case 'them':
						// AWAY default
						$this->set('status', 'away_default');
						break;
					case 'no':
					default:
						// No default.  Just finalize score.
						$this->set('home_score', $home_entry->score_for);
						$this->set('away_score', $home_entry->score_against);
				}
				$this->set('approved_by', APPROVAL_AUTOMATIC);
			} else {
				$sth = $dbh->prepare(
					"SELECT
						CONCAT(p.firstname, ' ', p.lastname) as fullname,
						email
					FROM leaguemembers m
					LEFT JOIN person p ON m.player_id = p.user_id
					WHERE league_id = ?
					AND (m.status = 'coordinator')");
				$sth->execute( array($this->league_id) );

				$to_list = array();
				while( $p = $sth->fetchObject('Person') ) {
					$to_list[] = $p;
				}

				$message = "Game {$this->game_id} between {$this->home_name} and {$this->away_name} in {$this->league_name} has score entries which do not match. Edit the game here:\n" . url("game/edit/{$this->game_id}");

				send_mail($to_list,
					false, // from the administrator
					false, // no Cc
					'Score entry mismatch',
					$message);
				return false;
			}
		} else if ( $home_entry && !$away_entry ) {
			switch( $home_entry->defaulted ) {
				case 'us':
					// HOME default with no entry by AWAY
					$this->set('status', 'home_default');
					break;
				case 'them':
					// AWAY default with no entry by AWAY
					$this->set('status', 'away_default');
					break;
				default:
					// no default, no entry by AWAY
					$this->penalize_team_for_non_entry( $this->away_id );
			}
			$this->set('home_score', $home_entry->score_for);
			$this->set('away_score', $home_entry->score_against);
			$this->set('approved_by', APPROVAL_AUTOMATIC_HOME);
		} else if ( !$home_entry && $away_entry ) {
			switch( $away_entry->defaulted ) {
				case 'us':
					// AWAY default with no entry by HOME
					$this->set('status', 'away_default');
					break;
				case 'them':
					// HOME default with no entry by HOME
					$this->set('status', 'home_default');
					break;
				default:
					// no default, no entry by HOME
					$this->penalize_team_for_non_entry( $this->home_id );
			}
			$this->set('home_score', $away_entry->score_against);
			$this->set('away_score', $away_entry->score_for);
			$this->set('approved_by', APPROVAL_AUTOMATIC_AWAY);
		} else if ( !$home_entry && !$away_entry ) {
			// TODO: don't do automatic forfeit yet.  Make it per-league configurable
			return false;
		} else {
			die("Unreachable code hit in Game::finalize()");
		}

		// load the teams in order to be able to save their current rating
		$home_team = $this->get_home_team_object();
		$away_team = $this->get_away_team_object();

		// save the current snapshot of each team's rating:
		$this->set('rating_home', $home_team->rating);
		$this->set('rating_away', $away_team->rating);

		if ( ! $this->save() ) {
			error_exit("Could not successfully save game results");
		}

		return true;
	}

	function get_captains ()
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT user_id
					FROM person p
						LEFT JOIN teamroster r ON p.user_id = r.player_id
					WHERE r.team_id IN (?,?)
						AND r.status IN ( 'captain', 'assistant', 'coach')");
		$sth->execute( array( $this->home_id, $this->away_id) );
		$captains = array();
		while($user = $sth->fetch(PDO::FETCH_OBJ)) {
			$captains[] = Person::load(array('user_id' => $user->user_id));
		}

		return $captains;
	}

	function save ()
	{
		global $dbh;
		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create new game");
			}
		}

		// First, perform some evil.  Depending on game status, we may wish to
		// override some values. But, we only want to do this when we're
		// changing the status.
		if( array_key_exists('status', $this->_modified_fields) ) {
			switch( $this->status ) {
				case 'home_default':
					$this->set('home_score', variable_get('default_losing_score', 0));
					$this->set('away_score', variable_get('default_winning_score', 6));
					break;
				case 'away_default':
					$this->set('home_score', variable_get('default_winning_score', 6));
					$this->set('away_score', variable_get('default_losing_score', 0));
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

		$fields      = array();
		$fields_data = array();
		foreach ( $this->_modified_fields as $key => $value) {
			if( $key == 'slot_id' ) {
				continue;
			}

			$fields[] = "$key = ?";
			if( !isset($this->{$key}) ) {
				$fields_data[] = null;
			} else {
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}
		if(count($fields)) {
			$sth = $dbh->prepare('UPDATE schedule SET '
				. join(", ", $fields)
				. ' WHERE game_id = ?');

			$fields_data[] = $this->game_id;

			$sth->execute( $fields_data );
			if($sth->rowCount() > 1) {
				error_exit("Internal error: Strange number of rows affected");
			}
		}

		// Now deal with slot_id
		if( array_key_exists('slot_id', $this->_modified_fields) ) {
			$sth = $dbh->prepare('UPDATE gameslot SET game_id = ? WHERE slot_id = ?');
			$sth->execute( array($this->game_id, $this->slot_id) );
			if(1 != $sth->rowCount()) {
				return false;
			}
		}

		// Delete score entries and finalize the rating change if
		// we've just updated the score
		if( array_key_exists('home_score', $this->_modified_fields) || array_key_exists('away_score', $this->_modified_fields)) {
			$sth = $dbh->prepare('DELETE FROM score_entry WHERE game_id = ?');
			$sth->execute( array($this->game_id) );

			$this->modify_team_ratings();
		}

		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		global $dbh;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->league_id ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT into schedule (league_id) VALUES(?)'); 
		$sth->execute( array($this->league_id) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM schedule');
		$sth->execute();
		$this->game_id = $sth->fetchColumn();

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
			error_exit("Cannot delete games which have been scored");
		}

		$queries = array(
			'UPDATE gameslot SET game_id = NULL where game_id = ?',
			'DELETE FROM spirit_entry WHERE gid = ?',
			'DELETE FROM score_entry WHERE game_id = ?',
			'DELETE FROM field_ranking_stats WHERE game_id = ?',
			'DELETE FROM schedule WHERE game_id = ?'
		);

		return $this->generic_delete( $queries, $this->game_id );
	}


	function removeresults()
	{
		global $dbh;

		if(!$this->_in_database) {
			return false;
		}

		$dbh->beginTransaction();

		// TODO: is there anything that should prevent removal of results??

		$errors = array();
		// Remove the spirit answers
		$sth = $dbh->prepare('DELETE FROM spirit_entry WHERE gid = ?');
		if( ! $sth->execute( array($this->game_id) ) ) {
			$errors[] = "Couldn't remove spirit answers: " . $sth->errorCode;
		}

		// Remove any submitted scores
		$sth = $dbh->prepare('DELETE FROM score_entry WHERE game_id = ?');
		if( ! $sth->execute( array($this->game_id) ) ) {
			$errors[] = "Couldn't remove submitted scores: " . $sth->errorCode;
		}

		// Update SCHEDULE table to remove these results
		$sth = $dbh->prepare("UPDATE schedule SET home_score = NULL, away_score = NULL, rating_points = NULL, rating_home = NULL, rating_away = NULL, approved_by = NULL, status = 'normal' where game_id = ?");
		$sth->execute( array($this->game_id) );
		if( ! $sth->execute( array($this->game_id) ) ) {
			$errors[] = "Couldn't remove results from schedule: " . $sth->errorCode;
		}

		// put the rating points back
		if( $this->rating_points != 0 ) {
			$rating_sth = $dbh->prepare('UPDATE team SET rating = rating + ? WHERE team_id = ?');
			if($this->home_score > $this->away_score) {
				// home win
				$rating_sth->execute( array( $this->rating_points, $this->away_team ) );
				if($rating_sth->rowCount() == 0) {
					$errors[] = "Couldn't fix the rating for team " . $this->away_team;
				}

				$rating_sth->execute( array( 0 - $this->rating_points, $this->home_team ) );
				if($rating_sth->rowCount() == 0) {
					$errors[] = "Couldn't fix the rating for team " . $this->home_team;
				}
			} else if($this->away_score > $this->home_score) {
				// away win
				$rating_sth->execute( array( $this->rating_points, $this->home_team ) );
				if($rating_sth->rowCount() == 0) {
					$errors[] = "Couldn't fix the rating for team " . $this->home_team;
				}

				$rating_sth->execute( array( 0 - $this->rating_points, $this->away_team ) );
				if($rating_sth->rowCount() == 0) {
					$errors[] = "Couldn't fix the rating for team " . $this->away_team;
				}
			}
		}

		if( count($errors) ) {
			$dbh->rollback();
			error_exit(join("<br/>", $errors));
		} else {
			$dbh->commit();
		}
		return true;
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
					. "AWAY_TEAM:     $this->away_team \n"
					. "AWAY_NAME:     $this->away_name \n"
					. "AWAY_SCORE:    $this->away_score \n</pre>\n";
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
		global $dbh;
		if( ! $this->_in_database ) {
			die( "Cannot load entered scores for game not in database: ". $this->game_id);
		}

		$sth = $dbh->prepare('SELECT * from score_entry WHERE game_id = ?');
		$sth->execute(array($this->game_id));

		while( $entry = $sth->fetch(PDO::FETCH_OBJ) ) {
			$this->_score_entries[ $entry->team_id ] = $entry;
		}

		$this->load_spirit_entries();

		return true;
	}

	function load_spirit_entries ( )
	{
		global $dbh;

		if( ! $this->_in_database ) {
			die( "Cannot load entered scores for game not in database: ". $this->game_id);
		}
		$sth = $dbh->prepare('SELECT * FROM spirit_entry WHERE gid = ? ORDER BY tid');
		$sth->execute(array( $this->game_id));

		while( $row = $sth->fetch() ) {
			$this->_spirit_entries[ $row['tid'] ] = array(
				'timeliness'      => $row['timeliness'],
				'rules_knowledge' => $row['rules_knowledge'],
				'sportsmanship'   => $row['sportsmanship'],
				'rating_overall'  => $row['rating_overall'],
				'score_entry_penalty'  => $row['score_entry_penalty'],
				'comments'        => $row['comments'] ? $row['comments'] : '',
				'entered_by'      => $row['entered_by'],
				'numeric_sotg'    => $row['timeliness'] + $row['rules_knowledge'] + $row['sportsmanship'] + $row['rating_overall'] + $row['score_entry_penalty'],
			);
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
		global $dbh;

		$entry->team_id = $team_id;
		$entry->entry_id = $this->entry_id;
		$entry->entered_by = $user_id;
		$entry->defaulted = $defaulted;

		switch ($defaulted) {
			case 'us':
				$entry->score_for = variable_get('default_losing_score', 0);
				$entry->score_against = variable_get('default_winning_score', 6);
				break;
			case 'them':
				$entry->score_against = variable_get('default_losing_score', 0);
				$entry->score_for = variable_get('default_winning_score', 6);
				break;
			case 'no':
			default:
				$entry->score_for = $score_for;
				$entry->score_against = $score_against;
				break;
		}

		$this->_score_entries[$team_id] = $entry;

		// Save entry object in DB.  Use 'replace into' to handle the case
		// where one might already exist.
		$sth = $dbh->prepare('REPLACE INTO score_entry (game_id,team_id,entered_by,score_for,score_against,defaulted,entry_time) VALUES(?,?,?,?,?,?,NOW())');
		$sth->execute( array(
			$this->game_id,
			$entry->team_id,
			$entry->entered_by,
			$entry->score_for,
			$entry->score_against,
			$entry->defaulted));

		if( 1 != $sth->rowCount() ) {
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
			$this->load_spirit_entries();
			if( !is_array($this->_spirit_entries) ) {
				return false;
			}
		}

		if( array_key_exists( $team_id, $this->_spirit_entries) ) {
			return $this->_spirit_entries[$team_id];
		}

		return false;
	}

	function penalize_team_for_non_entry ( $team_id )
	{
		global $dbh;

		$this->_spirit_entries[$team_id]['score_entry_penalty'] = 0 - variable_get('missing_score_spirit_penalty', 3);

		$sth = $dbh->prepare('UPDATE spirit_entry
			SET
				score_entry_penalty = ?
			WHERE
				gid = ?
				AND tid = ?');
		$sth->execute( array( $this->_spirit_entries[$team_id]['score_entry_penalty'], $this->game_id, $team_id) );
		if( $sth->rowCount() < 1) {
			return false;
		}

		return true;
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
	 * Calculate the expected win ratio.  Answer
	 * is always 0 <= x <= 1
	 */
	function calculate_expected_win ($rating1, $rating2) {
		$difference = $rating1 - $rating2;
		$power = pow(10, (0 - $difference) / 400);
		return ( 1 / ($power + 1) );
	}

	/**
	 * Calculate the expected win ratio of the home team.  Answer
	 * is always 0 <= x <= 1
	 */
	function home_expected_win()
	{
		return $this->calculate_expected_win ($this->rating_home, $this->rating_away);
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
	 *
	 * This can use either:
	 * 	- a modified elo system
	 * 	- a wager system
	 */
	function modify_team_ratings ( )
	{
		global $dbh;

		if(!is_null($this->rating_points) && $this->rating_points >= 0) {
			// If we already have a rating, don't recalculate it.
			// TODO: in the future, it might be nice if we can
			// recalculate the rating if the score changes, but for
			// now we'll let the perl script do that
			return true;
		}

		$change_calculator = 'calculate_elo_change';
		$sth = $dbh->prepare('SELECT schedule_type from league where league_id = ?');
		$sth->execute( array($this->league_id) );
		if( $sth->fetchColumn() == 'ratings_wager_ladder' ) {
			$change_calculator = 'calculate_wager_change';
		}

		$rating_sth = $dbh->prepare('UPDATE schedule SET rating_points = ? WHERE game_id = ?');

        // If we're not a normal game, avoid changing the rating.
		$change_rating = false;
		if( $this->status == 'normal' )
		{
			$change_rating = true;
		}
		if( variable_get('default_transfer_ratings', 0) &&
			($this->status == 'home_default' || $this->status == 'away_default') )
		{
			$change_rating = true;
		}

		if( ! $change_rating ) {
			$rating_sth->execute( array( 0, $this->game_id ) );
			return (1 == $rating_sth->rowCount());
		}

		$change = 0;

		if($this->home_score == $this->away_score) {
			// TODO FIXME: should treat ties as a win by the lower-rated team, rather than as a home win.
			$winner = $this->home_id;
			$loser = $this->away_id;
			$change = $this->$change_calculator($this->home_score, $this->away_score, $this->home_expected_win());
		} else if($this->home_score > $this->away_score) {
			$winner = $this->home_id;
			$loser = $this->away_id;
			$change = $this->$change_calculator($this->home_score, $this->away_score, $this->home_expected_win());
		} else {
			$winner = $this->away_id;
			$loser = $this->home_id;
			$change = $this->$change_calculator($this->home_score, $this->away_score, $this->away_expected_win());
		}

		$rating_sth->execute( array( $change, $this->game_id ) );
		if(1 != $rating_sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('UPDATE team SET rating = rating + ? WHERE team_id = ?');
		$sth->execute( array($change, $winner) );
		if(1 != $rating_sth->rowCount() ) {
			return false;
		}

		$sth->execute( array(0 - $change, $loser) );
		if(1 != $rating_sth->rowCount() ) {
			return false;
		}

		return true;
	}

	/**
	 * Calculate the ELO change for the result provided.
	 *
	 * This uses a modified Elo system, similar to the one used for
	 * international soccer (http://www.eloratings.net) with several
	 * modifications:
	 * 	- all games are equally weighted
	 * 	- score differential bonus adjusted for Ultimate patterns (ie: a 3
	 * 	  point win in soccer is a much bigger deal than in Ultimate
	 * 	- no bonus given for home-field advantage
	 */
	function calculate_elo_change ( $home_score, $away_score, $expected_win ) {
		$weight_constant = 40;  // All games weighted equally
		$score_weight    = 1;   // Games start with a weight of 1

		$game_value      = 1;   // Game value is always 1 or 0.5 as we're calculating the elo change for the winning team

		// Find winning/losing scores.  In the case of a tie,
		// the home team is considered the winner for purposes of
		// rating calculation.  This has nothing to do with the
		// tiebreakers used for standings purposes as in tie cases,
		// the $elo_change will work out the same regardless of which team is
		// considered the 'winner'
		if( $home_score == $away_score) {
			# For a tie, we assume the home team wins, but give the game a
			# value of 0.5
			$game_value = 0.5;
		}

		// Calculate score differential bonus.
		// If the difference is greater than 1/3 the winning score, the bonus
		// added is the ratio of score difference over winning score.
		$score_diff = abs($home_score - $away_score);
		$score_max  = max($home_score, $away_score);
		if( $score_max && ( ($score_diff / $score_max) > (1/3) )) {
			$score_weight += $score_diff / $score_max;
		}

		$elo_change = $weight_constant * $score_weight * ($game_value - $expected_win);
		return ceil($elo_change);
	}

	/**
	 * Calculate the wager ratings change for the result provided. 
	 *
	 * This uses a wagering system, where:
	 * 	- the final score determines the total amount of the pot.
	 * 	  It's based around a winning score of 15 points and tweaked
	 * 	  to produce the same ratings change for similar point
	 * 	  differentials for higher/lower final scores.
	 *
	 * 	- each team contributes a percentage of the pot based on their
	 * 	  expected chance to win
	 *
	 * 	- the losing team always takes away the same number of rating
	 * 	  points as their game points
	 *
	 * 	- the winning team takes away the remainder
	 *
	 * 	- thus, the point differential change amounts to:
	 * 	   ($total_pot - $loser_score - $winner_wager)
	 */
	function calculate_wager_change ( $home_score, $away_score, $expected_win ) {

		// Total wager value varies based on score
		// High scoring games increase the wager value
		$weight_constant = max($home_score, $away_score) * 2 + 10;

		$winner_wager = ceil( $weight_constant * $expected_win );

		$winner_gain = 0;
		if($home_score == $away_score) {
			$winner_gain = $weight_constant / 2;
		} else if ( $home_score > $away_score ) {
			$winner_gain = $weight_constant - $away_score;
		} else {
			$winner_gain = $weight_constant - $home_score;
		}

		return $winner_gain - $winner_wager;
	}

	function _gameslot_assign_sanity_check ()
	{
		if( $this->slot_id ) {
			throw new Exception("Cannot select gameslot for a game with an existing gameslot value");
		}

		if( !$this->game_id ) {
			throw new Exception("Cannot select gameslot for game without a game_id");
		}

		if( !$this->league_id ) {
			throw new Exception("Cannot select gameslot for game without league_id");
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
	 *
	 * Returns true on success, throws exception on failure
	 */
	function select_random_gameslot( $timestamp )
	{
		global $dbh;

		$this->_gameslot_assign_sanity_check();

		$sth = $dbh->prepare('SELECT s.slot_id
			FROM gameslot s,
				league_gameslot_availability a
			WHERE a.slot_id = s.slot_id
				AND UNIX_TIMESTAMP(s.game_date) = ?
				AND a.league_id = ?
				AND ISNULL(s.game_id)
			ORDER BY RAND()
			LIMIT 1');
		$sth->execute( array( $timestamp, $this->league_id) );
		$slot_id = $sth->fetchColumn();
		if( ! $slot_id ) {
			throw new Exception("Couldn't randomly assign a gameslot: no slot id found");
		}

		return $this->assign_gameslot( $slot_id );
	}

	/**
	 * Return the site ranking for the given team for this game
	 */
	function get_site_ranking ( $team_id )
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT rank from field_ranking_stats
			WHERE game_id = ? AND team_id = ?');
		$sth->execute( array( $this->game_id, $team_id) );
		$rank = $sth->fetchColumn();
		if( !isset($rank) || !$rank ) {
			return 'unranked';
		}
		return $rank;
	}

	/**
	 * Select a gameslot by the provided team's field rankings.
	 * Changes are made directly in the database (no need to ->save() the
	 * game) however this means that you should probably call this only
	 * within a transaction if you want to roll back changes easily on
	 * error.
	 *
	 * Returns true on success, throws exception on failure.
	 *
	 * TODO: Take field quality into account when assigning.  Easiest way
	 * to do this would be to order by field quality instead of RAND(),
	 * keeping our best fields in use.
	 */
	function select_team_preference_gameslot( $team_id, $timestamp, $rank_limit = 9999 )
	{
		global $dbh;

		$this->_gameslot_assign_sanity_check();

		$region_pref_sth = $dbh->prepare('SELECT
				s.slot_id
			FROM
				gameslot s,
				league_gameslot_availability a,
				team_site_ranking r,
				field f
			WHERE
				a.slot_id = s.slot_id
				AND UNIX_TIMESTAMP(s.game_date) = ?
				AND a.league_id = ?
				AND ISNULL(s.game_id)
				AND f.fid = s.fid
				AND (
					(ISNULL(f.parent_fid) AND f.fid = r.site_id)
					OR f.parent_fid = r.site_id
				)
				AND r.team_id = ?
				AND r.rank < ?
			ORDER BY r.rank ASC, RAND() LIMIT 1');

		$region_pref_sth->execute( array($timestamp, $this->league_id, $team_id, $rank_limit) );
		$slot_id = $region_pref_sth->fetchColumn();

		if( ! $slot_id ) {
			return false;
		}
		return $this->assign_gameslot( $slot_id );
	}

	function select_home_field_gameslot( $timestamp )
	{
		global $dbh;

		$this->_gameslot_assign_sanity_check();

		// try to adhere to the home team's HOME FIELD DESIGNATION
		$t = $this->get_home_team_object();
		if( ! $t ) {
			throw new Exception("Cached home team wasn't there!");
		}

		if ( ! $t->home_field ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT
			s.slot_id
		FROM
			gameslot s,
			league_gameslot_availability a,
			team t
		WHERE a.slot_id = s.slot_id
			AND UNIX_TIMESTAMP(s.game_date) = ?
			AND a.league_id = ?
			AND ISNULL(s.game_id)
			AND t.team_id = ?
			AND t.home_field = s.fid
		LIMIT 1');
		$sth->execute( array($timestamp, $this->league_id, $this->home_team) );
		$slot_id = $sth->fetchColumn();
		if( ! $slot_id ) {
			return false;
		}

		return $this->assign_gameslot( $slot_id );
	}

	/*
	 * Assign a gameslot to this game.
	 *
	 * Returns true on success, throws exception on error.
	 */
	function assign_gameslot ($slot_id)
	{
		global $dbh;

		if( ! $slot_id ) {
			throw new Exception('no slot id provided to assign_gameslot()');
		}

		$sth = $dbh->prepare('UPDATE gameslot SET game_id = ? WHERE ISNULL(game_id) AND slot_id = ?');
		$sth->execute(array($this->game_id, $slot_id));
		$gameslot_affected_rows = $sth->rowCount();
		if(1 != $gameslot_affected_rows) {
			throw new Exception('Could not assign gameslot: failed to update table');
		}
		$this->slot_id = $slot_id;

		// Now update some statistics for future calculation of
		// "preferred" field assignments.
		$teams = array($this->home_team, $this->away_team);
		$sth = $dbh->prepare(
		'INSERT INTO field_ranking_stats (game_id, team_id, rank)
			SELECT g.game_id, r.team_id, IF(g.fid = t.home_field, 1, r.rank)
				FROM team_site_ranking r, field f, gameslot g, team t
				WHERE g.game_id = ?
					AND g.fid = f.fid
					AND ( (ISNULL(f.parent_fid) AND f.fid = r.site_id)
						OR f.parent_fid = r.site_id
						OR g.fid = t.home_field
					)
					AND t.team_id = r.team_id
					AND r.team_id = ?');
		foreach($teams as $team) {
			$sth->execute( array( $this->game_id, $team ) );
		}

		return true;
	}

	// TODO: this belongs in Handler/game/ratings.php
	function get_ratings_table ( $rating_home, $rating_away)
	{
		global $dbh, $CONFIG;

		$header = array( array('data' => '&nbsp') );
		$rows = array( );

		$home = $this->home_name;
		$away = $this->away_name;

		$change_calculator = 'calculate_elo_change';
		$sth = $dbh->prepare('SELECT schedule_type from league where league_id = ?');
		$sth->execute(array($this->league_id));
		if($sth->fetchColumn() == 'ratings_wager_ladder' ) {
			$change_calculator = 'calculate_wager_change';
		}

		// assume that games can have scores up to 17...
		for ($h = 0; $h <= 17; $h++) {
			$header[] =  array('data' => $h);
			$current_row = array('data' => "<b>$h</b>");
			for ($a = 0; $a <= 17; $a++) {
				$change = 0;
				if ($h > $a) {
					// home win
					$change = $this->$change_calculator($h, $a,
						$this->calculate_expected_win($rating_home, $rating_away));
					$current_row[] = array('data' => $change, 'title'=>"'$home' wins $h to $a, takes $change rating points from '$away'", 'class'=>"highlight");
				} else if ($h == $a) {
					// TODO FIXME: should treat ties as a win by the lower-rated team, rather than as a home win.
					$change = $this->$change_calculator($h, $a,
						$this->calculate_expected_win($rating_home, $rating_away));
					$current_row[] = array('data' => $change, 'title'=>"Tie $h to $a, '$home' takes $change rating points from '$away'", 'class'=>"highlight");
				} else {
					$change = $this->$change_calculator($h, $a,
						$this->calculate_expected_win($rating_away, $rating_home));
					$current_row[] = array('data' => $change, 'title'=>"'$away' wins $a to $h, takes $change rating points from '$home'");
				}
			}
			$rows[] = $current_row ;
		}
		return table($header, $rows, array('border'=>1));
	}

	function time_until ( )
	{
		$minutesleft = ( $this->timestamp - time()) / 60;

		// Format the minutes left text
		if ( $minutesleft < 0  ) {
			$timeleft = 'already played';
		} else if ( $minutesleft < 90 ) {
			$timeleft = round($minutesleft) . " " . 'minutes';
		} else if ( $minutesleft < (2*24*60) ) {
			$timeleft = round($minutesleft/60) . " " . 'hours';
		} else {
			$timeleft = round($minutesleft/(24*60)) . " " . 'days';
		}

		return $timeleft;
	}

	static function cmp_hometeam_field_ratio ( $a, $b )
	{
		$a_home = $a->get_home_team_object();
		$b_home = $b->get_home_team_object();

		return Team::cmp_home_field_ratio( $a_home, $b_home);
	}

	static function cmp_awayteam_field_ratio ( $a, $b )
	{
		$a_away = $a->get_away_team_object();
		$b_away = $b->get_away_team_object();

		return Team::cmp_home_field_ratio( $a_away, $b_away);
	}

	static function query ( $array = array() )
	{
		global $CONFIG, $dbh;

		$order = '';
		$tables = array( 'schedule s');

		$local_adjust_secs = $CONFIG['localization']['tz_adjust'] * 60;

		$query  = array();
		$params = array();
		foreach ($array as $key => $value) {
			switch( $key ) {
				case 'game_date':
					$query[] = "g.game_date = ?";
					$params[] = $value;
					break;
				case 'game_date_past':
					$query[] = "(UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + $local_adjust_secs) < UNIX_TIMESTAMP(NOW())";
					break;
				case 'game_date_future':
					$query[] = "(UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + $local_adjust_secs) > UNIX_TIMESTAMP(NOW())";
					break;
				case 'either_team':
					$query[] = '(s.home_team = ? OR s.away_team = ?)';
					$params[] = $value;
					$params[] = $value;
					break;
				case '_extra':
					/* Just slap on any extra query fields desired */
					$query[] = $value;
					break;
				case '_order':
					$order = ' ORDER BY ' . $value;
					break;
				case '_limit':
					$limit = ' LIMIT ' . $value;
					break;
				case '_extra_table':
					array_unshift($tables, $value);
					break;
				case '_extra_params':
					$params = array_merge($params, $value);
					break;
				default:
					$query[] = "s.$key = ?";
					$params[] = $value;
			}
		}

		// TODO FIXME we use both home_team and home_id, away_team and away_id.  Should standardize on one!
		$sth = $dbh->prepare("SELECT 
			s.*,
			1 as _in_database,
			IF(l.tier,CONCAT(l.name,' ',l.tier), l.name) AS league_name,
			s.home_team AS home_id,
			h.name AS home_name,
			h.rating AS rating_home,
			s.away_team AS away_id,
			a.name AS away_name,
			a.rating AS rating_away,
			g.slot_id,
			g.game_date,
			TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
			TIME_FORMAT(g.game_end,'%H:%i') AS game_end,
			g.fid,
			UNIX_TIMESTAMP(g.game_date) as day_id,
			IF(f.parent_fid, CONCAT(pf.code, ' ', f.num), CONCAT(f.code, ' ', f.num)) AS field_code,
			UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + $local_adjust_secs as timestamp
		FROM " . join(',', $tables) .
			" INNER JOIN league l ON (l.league_id = s.league_id)
			LEFT JOIN gameslot g ON (g.game_id = s.game_id)
			LEFT JOIN field f ON (f.fid = g.fid)
			LEFT JOIN field pf ON (pf.fid = f.parent_fid)
			LEFT JOIN team h ON (h.team_id = s.home_team)
			LEFT JOIN team a ON (a.team_id = s.away_team)
		WHERE " . implode(' AND ',$query) .  $order . $limit);

		$sth->execute( $params );

		return $sth;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		$game = $result->fetchObject( get_class() );
		if( !$game ) {
			return null;
		}

		// These two fields come from a different table, but need to be saved
		// when we update this record.
		$game->touch ('rating_home');
		$game->touch ('rating_away');

		return $game;
	}

	static function load_many( $array = array() )
	{
		$sth = self::query( $array );

		$games = array();
		while($g = $sth->fetchObject( get_class() ) ) {
			// These two fields come from a different table, but need to be saved
			// when we update this record.
			$g->touch ('rating_home');
			$g->touch ('rating_away');

			$games[$g->game_id] = $g;
		}

		return $games;
	}
}
?>
