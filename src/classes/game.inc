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

	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{
		// Fixups
		if( ! $this->game_end ) {
			$this->game_end = 'dark';
		}

		if( ! $this->_in_database ) {
			return true;
		}

		$this->load_score_entries();

		return true;
	}

	/**
	 * Return short name for display purposes
	 */
	function short_home_name()
	{
		$short = sprintf("%.20s", $this->home_name);
		if(strlen($short) != strlen($this->home_name) ) {
			$short .= "...";
		}
		return $short;
	}

	function short_away_name()
	{
		$short = sprintf("%.20s", $this->away_name);
		if(strlen($short) != strlen($this->away_name) ) {
			$short .= "...";
		}
		return $short;
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

		# team farthest away from "normal" gets adjusted
		if( abs($a_ratio - 1) > abs($b_ratio - 1) ) {
			if( $a_ratio > 1) {
				# A is home-heavy, make it the away team.
				$home = $b;
				$away = $a;
			} else {
				# A is away-heavy, make it the home team.
				$home = $a;
				$away = $b;
			}
		} else {
			if( $b_ratio > 1) {
				# B is home-heavy, make it the away team.
				$home = $a;
				$away = $b;
			} else {
				# B is away-heavy, make it the home team.
				$home = $b;
				$away = $a;
			}
		}
		$home->home_games++;
		$this->set('home_team', $home->team_id);
		$away->away_games++;
		$this->set('away_team', $away->team_id);
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

	// This function must only be called after a save has been performed on
	// this game object.
	// Also where this function is called from must already confirm that the
	// scores have been finalized.

	// TODO: perhaps call from $game->save() instead of requiring an explicit
	// call in the handler?

	function updatedependentgames( $oldgameresults = array() ) 
	{
		global $dbh;
		$league = league_load(array('league_id' => $this->league_id));
		if(!$league) {
			error_exit("That league does not exist");
		}

		if($league->schedule_type == "pyramid") {

			# Figure out winner and loser.
			$winlose      = $this->calculate_winner_loser();
			$winnerTeamId = $winlose['winner'];
			$loserTeamId  = $winlose['loser'];

			$rank_sth = $dbh->prepare('UPDATE leagueteams SET rank = rank + ? WHERE league_id = ? AND team_id = ?');

			# see if there are old game results:
			if(count($oldgameresults) > 0) {

				// If we're changing the winner, modify the old
				// winner's rank before changing the new one.
				if($oldgameresults['home_score'] > $oldgameresults['away_score']) {
					if($this->home_score <= $this->away_score) {
						// Reset home team's rank
						$rank_sth->execute( array(1, $league->league_id, $this->home_team)  );
					} else {
						return true;
					}
				} else if($oldgameresults['home_score'] < $oldgameresults['away_score']) {
					if($this->home_score >= $this->away_score) {

						// Fix away team's rank
						// TODO: Is this even correct? Shouldn't the other team's rank change as well?
						$rank_sth->execute( array(1, $league->league_id, $this->away_team) );
					} else {
						return true;
					}
				}
			}

			# Pyramid allows for ties.  If there is a tie then just return
			if($this->home_score == $this->away_score) {
				return true;
			}

			// Bump the winner up one rank (note that it's a
			// subtraction, because lower == better)
			$rank_sth->execute( array(-1, $league->league_id, $winnerTeamId) );

			# Return true which is ok.  We don't need to update the other
			# teams for pyramid
			return true;
		}

		# Just fail out with everthing being good if this isn't a ladder
		# league.
		if($league->schedule_type != "ladder") {
			return true;
		}

		#print($this->sprintf("debug"));

		# Lets get the game objects.
		$sth = $dbh->prepare('SELECT * FROM schedule WHERE home_dependant_game = :game_id OR away_dependant_game = :game_id');
		$sth->execute( array( 'game_id' =>  $this->game_id)  );


		$games = array();
		while( $row = $sth->fetch() ) {
			$games[] = $row;
		}

		# Should only ever get two games;
		if(count($games) != 2) {
			error_exit("Internal error: Found $num_rows dependant games, expecting 2");
		}

		# Figure out winner and loser.
		$winlose      = $this->calculate_winner_loser();
		$winnerTeamId = $winlose['winner'];
		$loserTeamId  = $winlose['loser'];

		# Now we can figure out which team the
		# dependant games are looking for.
		while (list($crap,$row) = each($games)) {
			$gameIdToUpdate = $row['game_id'];

			if($row['home_dependant_game'] == $this->game_id) {
				$teamRank = $row['home_dependant_rank'];
				if($row['home_dependant_type'] == "winner") {
					$teamId = $winnerTeamId;
				} else if($row['home_dependant_type'] == "loser") {
					$teamId = $loserTeamId;
				} else {
					error_exit("Should never get here. Bad dependent type.");
				}

				# Update the database.
				$sth = $dbh->prepare('UPDATE schedule SET home_team = ? WHERE game_id = ?');
				$sth->execute( array($teamId, $gameIdToUpdate) );

				$sth = $dbh->prepare('UPDATE leagueteams SET rank = ? WHERE league_id = ? AND team_id = ?');
				$sth->execute( array($teamRank, $league->league_id, $teamId) );
			}

			if($row['away_dependant_game'] == $this->game_id) {

				$teamRank = $row['away_dependant_rank'];

				if($row['away_dependant_type'] == "winner") {
					$teamId = $winnerTeamId;
				} else if($row['away_dependant_type'] == "loser") {
					$teamId = $loserTeamId;
				} else {
					error_exit("Should never get here. Bad dependent type.");
				}

				# Update the database.
				$sth = $dbh->prepare('UPDATE schedule SET away_team = ? WHERE game_id = ?');
				$sth->execute( array($teamId, $gameIdToUpdate) );

				$sth = $dbh->prepare('UPDATE leagueteams SET rank = ? WHERE league_id = ? AND team_id = ?');
				$sth->execute( array($teamRank, $league->league_id, $teamId) );
			}

		}

		# It's all good.
		return true;
	}

	/**
	 * Take what is currently known about the game, and finalize it.
	 * If we have:
	 * 	0) no scores entered
	 * 		- forfeit game as 0-0 tie
	 * 		- give poor spirit to both
	 * 	1) one score entered
	 * 		- use single score as final
	 * 		- give full spirit to entering team, assigned spirit to
	 * 		  non-entering team.
	 * 	2) two scores entered, not agreeing
	 * 		- if there is an agreed winner, but the score itself is in
	 * 		  dispute, enter as final using the minimum score differential,
	 * 		  and assigned spirit.
	 * 		- if there is no agreed-upon winner, send email to the
	 * 		  coordinator.
	 *  3) two scores entered, agreeing
	 *  	- shouldn't occur, unless we want to move all score auto-approval
	 *  	  into this function for neatness' sake.
	 *  	- if we do, scores are entered as provided, as are spirit values.
	 */
	function finalize()
	{
#		print "Would finalize " . $this->game_id . "<br/>\n";

		if( $this->is_finalized() ) {
			return false;
		}

		// some strings that I will use later:
		$def = "default";
		$defsnr = "default, score not reported";
		$opdef = "opponent default";
		$opdefsnr = "opponent default, score not reported";
		$snr = "score not reported";
		
		$home_entry = $this->get_score_entry( $this->home_id );
		$away_entry = $this->get_score_entry( $this->away_id );
		if( $home_entry && $away_entry ) {
#			print $this->game_id ." has two entries<br/>\n";
			if( $this->score_entries_agree( object2array($home_entry), object2array($away_entry)) ) {
#				print "&nbsp;&nbsp;And both entries agree<br/>\n";
				switch( $home_entry->defaulted ) {
					case 'us':
						// HOME default
						$this->set('status', 'home_default');
						$this->save_spirit_entry( $this->away_id, $this->default_spirit('winner',false,$opdef));
						$this->save_spirit_entry( $this->home_id, $this->default_spirit('loser',false,$def));
						$this->set('home_spirit', 4);
						$this->set('away_spirit', 10);
						break;
					case 'them':
						// AWAY default
						$this->set('status', 'away_default');
						$this->save_spirit_entry( $this->away_id, $this->default_spirit('loser',false,$def));
						$this->save_spirit_entry( $this->home_id, $this->default_spirit('winner',false,$opdef));
						$this->set('home_spirit', 10);
						$this->set('away_spirit', 4);
						break;
					case 'no':
					default:
						// No default.  Just finalize score.
						$this->set('home_score', $home_entry->score_for);
						$this->set('away_score', $home_entry->score_against);
						$this->set('home_spirit', $away_entry->spirit);
						$this->set('away_spirit', $home_entry->spirit);
				}
				$this->set('approved_by', APPROVAL_AUTOMATIC);
			} else {
				// print "&nbsp;&nbsp;entries do not agree; mail coordinator<br/>\n";
				return false;
			}
		} else if ( $home_entry && !$away_entry ) {
			switch( $home_entry->defaulted ) {
				case 'us':
					// HOME default with no entry by AWAY
					$this->set('status', 'home_default');
					$this->save_spirit_entry( $this->home_id, $this->default_spirit('loser',false,$def));
					$this->save_spirit_entry( $this->away_id, $this->default_spirit('penalized',false,$opdefsnr));
					$this->set('home_spirit', 4); // home is penalized in SOTG because they defaulted
					$this->set('away_spirit', 7); // away is penalized in SOTG because they didn't report the score
					break;
				case 'them':
					// AWAY default with no entry by AWAY
					$this->set('status', 'away_default');
					$this->save_spirit_entry( $this->home_id, $this->default_spirit('perfect',false,$opdef));
					$this->save_spirit_entry( $this->away_id, $this->default_spirit('loser',false,$defsnr));
					$this->set('home_spirit', 10);
					$this->set('away_spirit', 4); // away is penalized in SOTG because they defaulted
					break;
				default:
					// no default, no entry by AWAY
					$this->save_spirit_entry( $this->home_id, $this->default_spirit('perfect'));
					$this->set('home_spirit', 10);
					$this->set('away_spirit', $home_entry->spirit);
			}
			$this->set('home_score', $home_entry->score_for);
			$this->set('away_score', $home_entry->score_against);
			$this->set('approved_by', APPROVAL_AUTOMATIC_HOME);
		} else if ( !$home_entry && $away_entry ) {
			switch( $away_entry->defaulted ) {
				case 'us':
					// AWAY default with no entry by HOME
					$this->set('status', 'away_default');
					$this->save_spirit_entry( $this->home_id, $this->default_spirit('penalized',false,$opdefsnr));
					$this->save_spirit_entry( $this->away_id, $this->default_spirit('loser',false,$def));
					$this->set('home_spirit', 7); // home is penalized in SOTG because they didn't report the score
					$this->set('away_spirit', 4); // away is penalized in SOTG because they defaulted
					break;
				case 'them':
					// HOME default with no entry by HOME
					$this->set('status', 'home_default');
					$this->save_spirit_entry( $this->home_id, $this->default_spirit('loser',false,$defsnr));
					$this->save_spirit_entry( $this->away_id, $this->default_spirit('perfect',false,$opdef));
					$this->set('home_spirit', 4); // home is penalized in SOTG because they didn't report the score
					$this->set('away_spirit', 10);
					break;
				default:
					// no default, no entry by HOME
					$this->save_spirit_entry( $this->away_id, $this->default_spirit('perfect'));
					$this->set('away_spirit', 10);
					$this->set('home_spirit', $away_entry->spirit);
	 }
			$this->set('home_score', $away_entry->score_against);
			$this->set('away_score', $away_entry->score_for);
			$this->set('approved_by', APPROVAL_AUTOMATIC_AWAY);
		} else if ( !$home_entry && !$away_entry ) {
# TODO: don't do automatic forfeit yet.  Make it per-tier configurable
			return false;
#			$this->set('status', 'forfeit');
#			$this->save_spirit_entry( $this->home_id, $this->default_spirit('loser'));
#			$this->save_spirit_entry( $this->away_id, $this->default_spirit('loser'));
#			$this->set('approved_by', APPROVAL_AUTOMATIC_FORFEIT);
		} else {
			die("Unreachable code hit in Game::finalize()");
		}

		// load the teams in order to be able to save their current rating
		$home_team = new Team();
		$home_team->load( array('team_id' => $this->home_id) );
		$away_team = new Team();
		$away_team->load( array('team_id' => $this->away_id) );

		// save the current snapshot of each team's rating:
		$this->set('rating_home', $home_team->rating);
		$this->set('rating_away', $away_team->rating);
		
		if ( ! $this->save() ) {
			error_exit("Could not successfully save game results");
		}

		// Game has been saved to database.  Now we can update the dependant games.
		if (! $this->updatedependentgames()) {
			error_exit("Could not update dependant games.");
		}
		return true;
	}
	
	/*
	 * Send any required reminders to captains about unscored games.
	 */
	function remind()
	{
		if( $this->is_finalized() ) {
			return false;
		}

		$ret = $this->remind_team( $this->home_team, $this->home_name, $this->away_name ) |
				$this->remind_team( $this->away_team, $this->away_name, $this->home_name );

		return $ret;
	}

	function remind_team($team_id, $team_name, $opponent)
	{
		global $dbh;
		if( array_key_exists($team_id, $this->_score_entries) ) {
			return false;
		}
		
		$sth = $dbh->prepare('SELECT COUNT(*) FROM score_reminder WHERE
			game_id = ? AND team_id = ?');
		$sth->execute( array( $this->game_id, $team_id) );
		$sent = $sth->fetchColumn();
		if( $sent ) {
			return false;
		}

		if( variable_get('postnuke', 0)) {
			$sth = $dbh->prepare("
				SELECT
					CONCAT(p.firstname, ' ', p.lastname) as fullname,
					n.pn_email as email,
					r.status
				FROM teamroster r
				LEFT JOIN person p ON r.player_id = p.user_id
				LEFT JOIN nuke_users n ON r.player_id = n.pn_uid
				WHERE team_id = %d
				AND (r.status = 'captain' OR r.status = 'assistant')");
		} else {
			$sth = $dbh->prepare("
				SELECT
					CONCAT(p.firstname, ' ', p.lastname) as fullname,
					email,
					r.status
				FROM teamroster r
				LEFT JOIN person p ON r.player_id = p.user_id
				WHERE team_id = %d
				AND r.status = 'captain' OR r.status = 'assistant'");
		}
		$sth = $dbh->execute( array($team_id) );

		$to_addr = $to_name = $cc_addr = $cc_name = array();
		while( $row = $sth->fetch(PDO::FETCH_OBJ) ) {
			if( $row->status == 'captain' ) {
				$to_addr[] = $row->email;
				$to_name[] = $row->fullname;
			} else {
				$cc_addr[] = $row->email;
				$cc_name[] = $row->fullname;
			}
		}

		// TODO fix tuc-ism
		$variables = array(
			'%fullname' => join(',', $to_name),
			'%team' => $team_name,
			'%opponent' => $opponent,
			'%league' => $this->league_name,
			'%gamedate' => $this->game_date,
			'%scoreurl' => "http://www.tuc.org/page.php?url=/leaguerunner/game/submitscore/{$this->game_id}/$team_id",
			'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
			'%site' => variable_get('app_name','Leaguerunner'));
		$message = _person_mail_text('score_reminder_body', $variables);

		if (!send_mail($to_addr, $to_name,
			false, false, // from the administrator
			$cc_addr, $cc_name,
			_person_mail_text('score_reminder_subject', $variables),
			$message))
		{
			return false;
		}

		$sth = $dbh->prepare('INSERT into score_reminder (game_id, team_id) VALUES(?,?)');
		$sth->execute( array( $this->game_id, $team_id) );

		return true;
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

		$fields      = array();
		$fields_data = array();
		foreach ( $this->_modified_fields as $key => $value) {
			if( $key == 'slot_id' ) {
				continue;
			}
		
			$fields[] = "$key = ?";
			if( empty($this->{$key}) ) {
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
			if(1 < $sth->rowCount()) {
				# Affecting zero rows is possible
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
		global $dbh;
		if( ! $this->_in_database ) {
			return false;
		}

		// TODO: There may be other things that should prevent deletion
		if( $this->home_score || $this->away_score ) {
			error_exit("Cannot delete games which have been scored");
		}

		// Don't check result; there may not be any to change
		$sth = $dbh->prepare('UPDATE gameslot SET game_id = NULL where game_id = ?');
		$sth->execute( array($this->game_id) );

		// Remove game from schedule
		$sth = $dbh->prepare('DELETE FROM schedule WHERE game_id = ?');
		$sth->execute( array($this->game_id) );
		return (1 == $sth->rowCount());
	}


	function removeresults()
	{
		global $dbh;

		if(!$this->_in_database) {
			return false;
		}

		// TODO: is there anything that should prevent removal of results??
		// TODO: This should really be in a transaction...

		# Remove the spirit answers
		$sth = $dbh->prepare('DELETE FROM team_spirit_answers WHERE gid = ?');
		$sth->execute( array($this->game_id) );
		if($sth->rowCount() == 0) {
			die("Uh-oh, problems removing results from the team_spirit_answers table... whew, you're still ok because this is the first thing I tried to do...");
		}

		# Update SCHEDULE table to remove these results
		$sth = $dbh->prepare("UPDATE schedule SET home_score = NULL, away_score = NULL, home_spirit = NULL, away_spirit = NULL, rating_points = NULL, rating_home = NULL, rating_away = NULL, approved_by = NULL, status = 'normal' where game_id = ?");
		$sth->execute( array($this->game_id) );
		if($sth->rowCount() == 0) {
			die("Uh-oh, problems removing results from the schedule table... too bad cause the team_spirit_answers was already removed!");
		}

		# put the rating points back...  and fix the rank...
		$rating_sth = $dbh->prepare('UPDATE team SET rating = rating + ? WHERE team_id = ?');
		$rank_sth = $dbh->prepare('UPDATE leagueteams set rank = rank + 1 where league_id = ? and team_id = ?');
		if($this->home_score > $this->away_score) {
			// home win
			$rating_sth->execute( array( $this->rating_points, $this->away_team ) );
			if($rating_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rating score for team " + $this->away_team);
			}

			$rating_sth->execute( array( 0 - $this->rating_points, $this->home_team ) );
			if($rating_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rating score for team " + $this->home_team);
			}

			$rank_sth->execute( array( $this->league_id, $this->home_team) );
			if($rank_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rank for team " + $this->home_team);
			}

		} else if($this->away_score > $this->home_score) {
			// away win
			$rating_sth->execute( array( $this->rating_points, $this->home_team ) );
			if($rating_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rating score for team " + $this->away_team);
			}

			$rating_sth->execute( array( 0 - $this->rating_points, $this->away_team ) );
			if($rating_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rating score for team " + $this->home_team);
			}

			$rank_sth->execute( array( $this->league_id, $this->away_team) );
			if($rank_sth->rowCount() == 0) {
				die("Uh-oh, problems fixing the rank for team " + $this->away_team);
			}

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
					. "ROUND:	 $this->round \n"
					. "DATE:	  $this->game_date \n"
					. "START:	 $this->game_start \n"
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
		$sth = $dbh->prepare('SELECT a.tid,a.qkey,a.akey FROM team_spirit_answers a, question q WHERE q.qkey = a.qkey AND a.gid = ? ORDER BY a.tid,q.sorder');
		$sth->execute(array( $this->game_id));

		while( $row = $sth->fetch() ) {
			$got[$row['tid']] = 1;
			$this->_spirit_entries[ $row['tid'] ][ $row['qkey'] ] = $row['akey'] ;
		}

		// Evil hack.  Previous release didn't properly fill in this 
		// field, so here we append an empty string to it so that it
		// exists if not populated.
		if( $got[$this->home_id]) { 
			$this->_spirit_entries[ $this->home_id ]['CommentsToCoordinator'] .= '';
		} 
		if( $got[$this->away_id] ) {
			$this->_spirit_entries[ $this->away_id ]['CommentsToCoordinator'] .= '';
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
	function save_score_entry ( $team_id, $user_id, $score_for, $score_against, $defaulted, $spirit )
	{
		global $dbh;

		$entry->team_id = $team_id;
		$entry->entry_id = $this->entry_id;
		$entry->entered_by = $user_id;
		$entry->defaulted = $defaulted;
		$entry->spirit = $spirit;

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
		$sth = $dbh->prepare('REPLACE INTO score_entry (game_id,team_id,entered_by,score_for,score_against,spirit,defaulted,entry_time) VALUES(?,?,?,?,?,?,?,NOW())');
		$sth->execute( array(
			$this->game_id,
			$entry->team_id,
			$entry->entered_by,
			$entry->score_for,
			$entry->score_against,
			$entry->spirit,
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
		global $dbh;
		$entry = $this->get_spirit_entry($team_id);
		if( !$entry ) {
			return null;
		}

		// Start with 10 spirit, subtract down
		$spirit = 10;
		$sth = $dbh->prepare('SELECT value from multiplechoice_answers WHERE qkey = ? AND akey = ?');
		while( list($qkey, $answer) = each($entry) ) {
			// if any of the answers are blank, they'll all be blank, so just return -1 to indicate it
			if ($qkey != "CommentsToCoordinator" && ($answer == null || $answer == "")) {
				return -1;
			}

			$sth->execute( array( $qkey, $answer) );
			$spirit += $sth->fetchColumn();
		}

		return $spirit;
	}

	/**
	 * Save a spirit entry for the given team
	 */
	function save_spirit_entry ( $team_id, $spirit )
	{
		global $dbh;

		if( !is_array($spirit) ) {
			die("Spirit argument to save_spirit_entry() must be an array");
		}
	
		// Store in object
		$this->_spirit_entries[$team_id] = $spirit;

		$opponent_id = $this->get_opponent_id( $team_id );
		
		// save in DB
		$sth = $dbh->prepare('REPLACE INTO team_spirit_answers (tid_created,tid,gid,qkey,akey) VALUES (?,?,?,?,?)');
		while( list($qkey, $answer) = each($spirit) ) {
			$sth->execute( array(
				$opponent_id,
				$team_id,
				$this->game_id,
				$qkey,
				$answer
			));
			if( $sth->rowCount() < 1) {
				return false;
			}
		}
		
		return true;		
	}

	/**
	 * Return values to be used for winner/loser of a default
	 * game.
	 */
	function default_spirit ( $type , $numeric = false, $default = "")
	{
		$comments = "Automatic spirit assigned: ";
		switch( $type ) {
			case 'penalized':
				// 7
				if ($numeric) {
					return 7;
				}
				if ($default != "") {
					return array(
						'Timeliness' => null,
						'RulesKnowledge' => null,
						'Sportsmanship' => null,
						'Enjoyment' => null,
						'CommentsToCoordinator' => $comments . "7 ($default)",
					);
				}
				return array(
					'Timeliness' => 'OnTime',
					'RulesKnowledge' => 'AcceptableRules',
					'Sportsmanship' => 'AcceptableSportsmanship',
					'Enjoyment' => 'MostEnjoyed',
					'CommentsToCoordinator' => $comments . "7",
				);
				break;
			case 'loser':
				// 4
				if ($numeric) {
					return 4;
				}
				if ($default != "") {
					return array(
						'Timeliness' => null,
						'RulesKnowledge' => null,
						'Sportsmanship' => null,
						'Enjoyment' => null,
						'CommentsToCoordinator' => $comments . "4 ($default)",
					);
				}
				return array(
					'Timeliness' => 'FiveOrLess',
					'RulesKnowledge' => 'PoorRules',
					'Sportsmanship' => 'PoorSportsmanship',
					'Enjoyment' => 'SomeEnjoyed',
					'CommentsToCoordinator' => $comments . "4",
				);
				break;
			case 'winner':
			case 'perfect':
				// 10
				if ($numeric) {
					return 10;
				}
				if ($default != "") {
					return array(
						'Timeliness' => null,
						'RulesKnowledge' => null,
						'Sportsmanship' => null,
						'Enjoyment' => null,
						'CommentsToCoordinator' => $comments . "10 ($default)",
					);
				}
				return array(
					'Timeliness' => 'OnTime',
					'RulesKnowledge' => 'ExcellentRules',
					'Sportsmanship' => 'ExcellentSportsmanship',
					'Enjoyment' => 'AllEnjoyed',
					'CommentsToCoordinator' => $comments . "10",
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
			## If we already have a rating, don't recalculate it.
			## TODO: in the future, it might be nice if we can
			## recalculate the rating if the score changes, but for
			## now we'll let the perl script do that
			return true;
		}

		$change_calculator = 'calculate_elo_change';
		$sth = $dbh->prepare('SELECT schedule_type from league where league_id = ?');
		$sth->execute( array($this->league_id) );
		if( $sth->fetchColumn() == 'ratings_wager_ladder' ) {
			$change_calculator = 'calculate_wager_change';
		}

		$rating_sth = $dbh->prepare('UPDATE schedule SET rating_points = ? WHERE game_id = ?');

		## If we're not a normal game, avoid changing the rating.
		if($this->status != 'normal') {
			$rating_sth->execute( array( 0, $this->game_id ) );
			return (1 == $rating_sth->rowCount());
		}

		$change = 0;

		if($this->home_score == $this->away_score) {
			# For a tie, we assume the home team wins
			$winner     = $this->home_id;
			$loser      = $this->away_id;
			$change = $this->$change_calculator($this->home_score, $this->away_score, $this->home_expected_win());
		} else if($this->home_score > $this->away_score) {
			$winner     = $this->home_id;
			$loser      = $this->away_id;
			$change = $this->$change_calculator($this->home_score, $this->away_score, $this->home_expected_win());
		} else {
			$winner     = $this->away_id;
			$loser      = $this->home_id;
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
		global $dbh;

		if( $this->slot_id ) {
			return array(false, "Cannot call select_random_gameslot() on a game with an existing gameslot value");
		}

		if( !$this->game_id ) {
			return array(false, "Game must have a game_id before calling select_random_gameslot()");
		}

		if( !$this->league_id ) {
			return array (false, "Game must have a league_id before calling select_random_gameslot()");
		}


		/*
		 * To keep this safe amongst possible multiple writers, we lock.  To
		 * keep the locks a bit more granular, we lock on the timestamp, so
		 * that we only protect against people trying to add games on the same
		 * day.
		 * TODO: This needs transactions!  The locking stuff is stupid.
		 */
		$lockname = "schedule_lock_$timestamp";
	
		$lock_sth = $dbh->prepare('SELECT GET_LOCK(?,10)');
		$lock_sth->execute( array( $lockname ) );
		$lock = $lock_sth->fetchColumn();
		if(!isset($lock) || $lock == 0) {
			// Couldn't obtain lock after 10 seconds, so bail.
			return array(false, "Couldn't get lock");
		}
		$unlock_sth = $dbh->prepare('SELECT RELEASE_LOCK(?)');

      		$seed = rand();
		$sth = $dbh->prepare('SELECT s.slot_id FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = ? AND a.league_id = ? AND ISNULL(s.game_id) ORDER BY RAND(?) LIMIT 1');
		$sth->execute( array( $timestamp, $this->league_id, $rand) );
		$slot_id = $sth->fetchColumn();
		if( ! $slot_id ) {
			$unlock_sth->execute( array( $lockname ) );
			return array(false, "Couldn't get slot ID");
		}

		$sth = $dbh->prepare('UPDATE gameslot SET game_id = ? WHERE ISNULL(game_id) AND slot_id = ?');
		$sth->execute( array( $this->game_id, $slot_id) );
		$gameslot_affected_rows = $sth->rowCount();
		$unlock_sth->execute( array( $lockname ) );
		if(1 != $gameslot_affected_rows) {
			return array(false, "Couldn't assign gameslot");
		}
		$this->slot_id = $slot_id;
		return array(true,'');
	}
	
	/**
	 * Select an appropriate gameslot for this game.  "appropriate" takes
	 * field quality, home field designation, and field preferences into account.
	 * TODO: FIXME: currently, no field quality are checked!
	 * Gameslot is to be selected from those available for the league in which
	 * this game exists.  
	 * Single argument is to be the timestamp representing the date of the
	 * game.
	 * Changes are made directly in the database, no need to call save()
	 * Returns success or fail, depending on whether or not we could get a
	 * gameslot.
	 */
	function select_weighted_gameslot( $timestamp )
	{
		global $dbh;

		if( $this->slot_id ) {
			return array(false, "Cannot call select_weighted_gameslot() on a game with an existing gameslot value");
		}

		if( !$this->game_id ) {
			return array(false, "Game must have a game_id before calling select_weighted_gameslot()");
		}

		if( !$this->league_id ) {
			return array (false, "Game must have a league_id before calling select_weighted_gameslot()");
		}

		$satisfied = false;
		/*
		 * To keep this safe amongst possible multiple writers, we lock.  To
		 * keep the locks a bit more granular, we lock on the timestamp, so
		 * that we only protect against people trying to add games on the same
		 * day.
		 * TODO: This needs transactions!  The locking stuff is stupid.
		 */
		$lockname = "schedule_lock_$timestamp";
	
		$lock_sth = $dbh->prepare('SELECT GET_LOCK(?,10)');
		$lock_sth->execute( array( $lockname ) );
		$lock = $lock_sth->fetchColumn();
		if(!isset($lock) || $lock == 0) {
			// Couldn't obtain lock after 10 seconds, so bail.
			return array(false, "Couldn't get lock");
		}
		$unlock_sth = $dbh->prepare('SELECT RELEASE_LOCK(?)');

		$seed = rand();

		// try to adhere to the home team's HOME FIELD DESIGNATION
		// TODO: I could save a DB hit every time if I would just pass
		// in the team object to this method...
		$t = team_load( array('team_id' => $this->home_team) );
		if ($t->home_field != null && $t->home_field != '') {
			$sth = $dbh->prepare('SELECT s.slot_id FROM gameslot s, league_gameslot_availability a, field f, team t WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = ? AND a.league_id = ? AND ISNULL(s.game_id) AND f.fid = s.fid AND t.team_id = ? AND t.home_field = f.fid ORDER BY RAND(?) LIMIT 1');
			$sth->execute( array($timestamp, $this->league_id, $this->home_team, $seed) );
			$slot_id = $sth->fetchColumn();
		}

		// if no home field, try to adhere to the home team's field preference
		if( ! $slot_id ) {
			$sth = $dbh->prepare('SELECT s.slot_id FROM gameslot s, league_gameslot_availability a, field f, team t WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = ? AND a.league_id = ? AND ISNULL(s.game_id) AND f.fid = s.fid AND t.team_id = ?  AND t.region_preference = f.region ORDER BY RAND(?) LIMIT 1');
			$sth->execute( array($timestamp, $this->league_id, $this->home_team, $seed) );
			$slot_id = $sth->fetchColumn();
		}

		// if nothing can be found by home team preference, try the away team
		if( ! $slot_id ) {
			$sth = $dbh->prepare('SELECT s.slot_id FROM gameslot s, league_gameslot_availability a, field f, team t WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = ? AND a.league_id = ? AND ISNULL(s.game_id) AND f.fid = s.fid AND t.team_id = ? AND t.region_preference = f.region ORDER BY RAND(?) LIMIT 1');
			$sth->execute( array($timestamp, $this->league_id, $this->away_team, $seed) );
			$slot_id = $sth->fetchColumn();
		}

		// if still nothing can be found, last try is just random:
		if( ! $slot_id ) {
			$sth = $dbh->prepare('SELECT s.slot_id FROM gameslot s, league_gameslot_availability a WHERE a.slot_id = s.slot_id AND UNIX_TIMESTAMP(s.game_date) = ? AND a.league_id = ? AND ISNULL(s.game_id) ORDER BY RAND(?) LIMIT 1');
			$sth->execute( array($timestamp, $this->league_id, $seed) );
			$slot_id = $sth->fetchColumn();
		}

		if( ! $slot_id ) {
			$unlock_sth->execute( array( $lockname ) );
			return array(false, "Couldn't get slot ID");
		}

		$sth = $dbh->prepare('UPDATE gameslot SET game_id = ? WHERE ISNULL(game_id) AND slot_id = ?');
		$sth->execute(array($this->game_id, $slot_id));
		$gameslot_affected_rows = $sth->rowCount();
		$unlock_sth->execute( array( $lockname ) );
		if(1 != $gameslot_affected_rows) {
			return array(false, "Couldn't assign gameslot");
		}
		$this->slot_id = $slot_id;
		return array(true,'');
	}
	
	function get_ratings_table ( $rating_home, $rating_away, $whatif ) 
	{
		global $dbh;

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
						$this->calculate_expected_win($home_rating, $away_rating));
					$current_row[] = array('data' => $change, 'title'=>"'$home' wins $h to $a, takes $change rating points from '$away'", 'class'=>"highlight");
				} else if ($h == $a) {
					// treat as a home win
					$change = $this->$change_calculator($h, $a, 
						$this->calculate_expected_win($home_rating, $away_rating));
					$current_row[] = array('data' => $change, 'title'=>"Tie $h to $a, '$home' takes $change rating points from '$away'", 'class'=>"highlight");
				} else {
					$change = $this->$change_calculator($h, $a, 
						$this->calculate_expected_win($away_rating, $home_rating));
					$current_row[] = array('data' => $change, 'title'=>"'$away' wins $a to $h, takes $change rating points from '$home'");
				}
			}
			$rows[] = $current_row ;
		}
		$output2 = "";
		if ($whatif) {
			// what if teams had different ratings?
			$output2 = para("&nbsp;");
			$output2 .= para("What if the teams had different ratings?  Check it here:");
			$output2 .= para("<form name='whatif'>$home <input type='text' size=5 name='rating_home' value='$rating_home'><br>" .
					"$away <input type='text' size=5 name='rating_away' value='$rating_away'><br>" .
					"<input type=button value='What If?' onclick='window.location=\"/leaguerunner/game/ratings/" . $this->game_id .
					"/\"+document.whatif.rating_home.value+\"/\"+document.whatif.rating_away.value'");
			
		}
		return "<div class='listtable'>" . table($header, $rows, array('border'=>1)) . "</div>" . $output2;
	}
}

function game_query ( $array = array() )
{
	global $TZ_ADJUST, $dbh;

	$order = '';
	$tables = array( 'schedule s');

	$query  = array();
	$params = array();
	foreach ($array as $key => $value) {
		switch( $key ) {
			case 'game_date':
				$query[] = "g.game_date = ?";
				$params[] = $value;
				break;
			case 'game_date_past':
				$query[] = "(UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + ($TZ_ADJUST * 60)) < UNIX_TIMESTAMP(NOW())";
				break;
			case 'game_date_future':
				$query[] = "(UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + ($TZ_ADJUST * 60)) > UNIX_TIMESTAMP(NOW())";
				break;
			case 'either_team':
				$query[] = '(s.home_team = ? OR s.away_team = ?)';
				$params[] = $value;
				$params[] = $value;
				break;
			case 'dependant_game':
				$query[] = '(s.home_dependant_game = ? OR s.away_dependant_game = ?)';
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
			case '_extra_table':
				array_push($tables, $value);
				break;
			default:
				$query[] = "s.$key = ?";
				$params[] = $value;
		}
	}

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
		UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) + ($TZ_ADJUST * 60) as timestamp
	FROM " . join(',', $tables) . 
		" INNER JOIN league l ON (l.league_id = s.league_id)
		LEFT JOIN gameslot g ON (g.game_id = s.game_id)
		LEFT JOIN field f ON (f.fid = g.fid)
		LEFT JOIN field pf ON (pf.fid = f.parent_fid)
		LEFT JOIN team h ON (h.team_id = s.home_team) 
		LEFT JOIN team a ON (a.team_id = s.away_team)
	WHERE " . implode(' AND ',$query) .  $order);

	$sth->execute( $params );

	return $sth;
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function game_load( $array = array() )
{
	$result = game_query( $array );
	if( $result->rowCount() != 1 ) {
		return null;
	}
	return $result->fetchObject('Game');
}

function game_load_many( $array = array() )
{
	$sth = game_query( $array );

	$games = array();
	while($g = $sth->fetchObject('Game') ) {
		$games[$g->game_id] = $g;
	}

	// take the LAST game, and check for dependent games
	// TODO: Necessary anymore?
	$last_game = $g;
	$dep_array = array( 'dependant_game' => $last_game->game_id, '_order' => 'g.game_date');
	$dep_sth = game_query( &$dep_array );
	while ($dep_g = $sth->fetchObject('Game') ) {
		$games[$g->game_id] = $g;
	}

	return $games;
}

/**
 * This function takes into account HOME FIELD DESIGNATION, 
 * then HOME REGION PREFERENCE,
 * then AWAY REGION PREFERENCE,
 * then THE REST.
 * 
 * It uses the select_weighted_gameslot function, which first looks at home field
 * designation, then at field region preferences.  But, in order to ensure that 
 * teams with home field designations get their choice, we need to schedule those
 * games first.  Next, schedule the games in which the home team has a field 
 * region preference.  Then, do the games where the away team has a field region
 * preference.  Finally, do the rest of the games.
 * 
 * The assumption is that the GAMES array that is passed in is already in random order.
 * 
 * This function returns an array where the first element is true/false for SUCCESS/FAILURE, 
 * and the in case of failure the remaining elements of the array are information about error.
 * 
 */
function assign_fields_by_preferences( $games, $teams, $datestamp ) {
	$temp_games = array();
	$team = null;
	$game = null;

	// FIRST PASS: all home teams with HOME FIELD DESIGNATION need to get their games scheduled first...
	// loop through the games, find the home team and see if they have a home field set... if yes, schedule it...
	while(count($games) > 0) {
		// get the game to look at
		$game = array_pop($games);
		// prepare a boolean to say whether or not the game was scheduled
		$scheduled = false;
		// loop through the teams
		foreach ($teams as $team) {
			// if the team is the home team for this game
			if ($team->team_id == $game->home_team) {
				// does this team have a home field?
				if ($team->home_field != null && $team->home_field != '') {
					// this game needs to be scheduled before others!
					// let the 'select_weighted_gameslot' function do the work
					list($rc, $message) = $game->select_weighted_gameslot($datestamp);
					$scheduled = true;
					// look for errors:
					if( ! $rc ) {
						return array(false, $rc, $message);
					}
				}
				// found the home team, so break out of the loop
				break;
			} // end if
		} // end foreach
		
		// if we haven't already scheduled the game, save it for later...
		if (!$scheduled) {
			$temp_games[] = $game;
		}
	} // end while

	// re-fill the games array
	$games = $temp_games;
	// clear the temp_games array
	$temp_games = array();
	
	// SECOND PASS: all games that remain in which the HOME team has a field REGION PREFERENCE and 
	// the AWAY team has the same preference should now be scheduled...
	while(count($games) > 0) {
		// get the game to look at
		$game = array_pop($games);
		// prepare a boolean to say whether or not the game was scheduled
		$scheduled = false;
		// home/away team id and preferences:
		$home_team_id = $game->home_team;
		$away_team_id = $game->away_team;
		$home_team_preference = "";
		$away_team_preference = "";
		$found_home = false;
		$found_away = false;
		// loop through the teams
		foreach ($teams as $team) {
			// if the team is the home team for this game
			if ($team->team_id == $home_team_id) {
				// does this team have a field region preference?
				if ($team->region_preference != null && $team->region_preference != '---') {
					$home_team_preference = $team->region_preference;
				}
				$found_home = true;
			}
			// if the team is the away team for this game
			if ($team->team_id == $away_team_id) {
				// does this team have a field region preference?
				if ($team->region_preference != null && $team->region_preference != '---') {
					$away_team_preference = $team->region_preference;
				}
				$found_away = true;
			}
			
			if ($found_home && $found_away) {
				// found the both teams, so break out of the loop
				break;
			}

		} // end foreach
		
		if ($found_home && $found_away && $home_team_preference != "" && $away_team_preference != "" && $home_team_preference == $away_team_preference) {
			// this game needs to be scheduled
			// let the 'select_weighted_gameslot' function do the work
			list($rc, $message) = $game->select_weighted_gameslot($datestamp);
			$scheduled = true;
			// look for errors:
			if( ! $rc ) {
				return array(false, $rc, $message);
			}
		}
		
		// if we haven't already scheduled the game, save it for later...
		if (!$scheduled) {
			$temp_games[] = $game;
		}
	} // end while

	// re-fill the games array
	$games = $temp_games;
	// clear the temp_games array
	$temp_games = array();
	
	// THIRD PASS: all games that remain in which the HOME team has a field REGION PREFERENCE should 
	// now be scheduled...
	while(count($games) > 0) {
		// get the game to look at
		$game = array_pop($games);
		// prepare a boolean to say whether or not the game was scheduled
		$scheduled = false;
		// loop through the teams
		foreach ($teams as $team) {
			// if the team is the home team for this game
			if ($team->team_id == $game->home_team) {
				// does this team have a field region preference?
				if ($team->region_preference != null && $team->region_preference != '---') {
					// this game needs to be scheduled next
					// let the 'select_weighted_gameslot' function do the work
					list($rc, $message) = $game->select_weighted_gameslot($datestamp);
					$scheduled = true;
					// look for errors:
					if( ! $rc ) {
						return array(false, $rc, $message);
					}
				}
				// found the home team, so break out of the loop
				break;
			} // end if
		} // end foreach
		
		// if we haven't already scheduled the game, save it for later...
		if (!$scheduled) {
			$temp_games[] = $game;
		}
	} // end while

	// re-fill the games array
	$games = $temp_games;
	// clear the temp_games array
	$temp_games = array();

	// FOURTH PASS: all games that remain in which the AWAY team has a field REGION PREFERENCE should 
	// now be scheduled...
	while(count($games) > 0) {
		// get the game to look at
		$game = array_pop($games);
		// prepare a boolean to say whether or not the game was scheduled
		$scheduled = false;
		// loop through the teams
		foreach ($teams as $team) {
			// if the team is the away team for this game
			if ($team->team_id == $game->away_team) {
				// does this team have a field region preference?
				if ($team->region_preference != null && $team->region_preference != '') {
					// this game needs to be scheduled next
					// let the 'select_weighted_gameslot' function do the work
					list($rc, $message) = $game->select_weighted_gameslot($datestamp);
					$scheduled = true;
					// look for errors:
					if( ! $rc ) {
						return array(false, $rc, $message);
					}
				}
				// found the home team, so break out of the loop
				break;
			} // end if
		} // end foreach
		
		// if we haven't already scheduled the game, save it for later...
		if (!$scheduled) {
			$temp_games[] = $game;
		}
	} // end while

	// FINAL PASS: just schedule THE REST of the games
	foreach ($temp_games as $g) {
		list($rc, $message) = $g->select_weighted_gameslot($datestamp);
		if( ! $rc ) {
			return array(false, $rc, $message);
		}
	}
	
	return array(true, "");
}

/**
 * Generates the javascript, input textarea, and button for the SOTG suggestion
 * box.
 */
function generateSOTGButtonAndJavascript ($name, $label, $default = "") {
	$use = "";
	if ($name != null && $name != "") {
		$use = "_" . $name;
	}
	$sotgjs = "<script language='javascript'> \nfunction sotg$use() {\n";
	$sotgjs .= "var sotg=10;\n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Timeliness]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Timeliness]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Timeliness]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[RulesKnowledge]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[RulesKnowledge]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[RulesKnowledge]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Sportsmanship]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Sportsmanship]'][2].checked){sotg=sotg-2;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Sportsmanship]'][3].checked){sotg=sotg-3;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Enjoyment]'][1].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Enjoyment]'][2].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "if (document.forms[0].elements['team_spirit" . $use . "[Enjoyment]'][3].checked){sotg=sotg-1;}; \n";
	$sotgjs .= "return sotg;\n";
	$sotgjs .= "}\n";
	$sotgjs .= "</script>\n";
	
    return para( $sotgjs . "<div class=\"form-item\"><label>$label</label>" . 
    	"<br> <input type=\"text\" maxlength=\"2\" class=\"form-text\" name=\"edit[sotg$use]\" size=\"2\" value=\"$default\" /> ".
		" <input type='button' name='suggest' value='Suggest' " .
		" onclick=\"document.forms[0].elements['edit[sotg$use]'].value=sotg$use();\"></div>");
}

?>
