<?php
class Game extends LeaguerunnerObject
{

	var $_score_entries;

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

		$this->_in_database = true;
		
		$item = db_fetch_array($result);
		foreach ($item as $key => $value) {
			$this->{$key} = $value;
		}

		// Fixups
		if( ! $this->game_end ) {
			$this->game_end = 'dark';
		}

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

		/* Now deal with slot_id */
		if( array_key_exists('slot_id', $this->_modified_fields) ) {
			db_query("UPDATE gameslot SET game_id = %d WHERE slot_id = %d", $this->game_id, $this->slot_id);
			if(1 != db_affected_rows()) {
				return false;
			}
		}

		/* TODO: And, remove any old score_entry for this game if we've 
		   entered a final score
         */
		
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
	 * TODO: spirit
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
		
		if( db_num_rows($result) < 1 ) {
			return false;
		}

		while( $entry = db_fetch_object($result) ) {
			$this->_score_entries[ $entry->team_id ] = $entry;
		}

		// TODO: load spirit here

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
	 * TODO: spirit
	 */
	function save_score_entry ( $team_id, $user_id, $score_for, $score_against, $defaulted, $spirit )
	{
		$entry->team_id = $team_id;
		$entry->entry_id = $this->entry_id;
		$entry->entered_by = $user_id;
		$entry->score_for = $score_for;
		$entry->score_against = $score_against;
		$entry->defaulted = $defaulted;

		// TODO: Set spirit on entry object here
		$this->_score_entries[$team_id] = $entry;

		// TODO Save entry object in DB
		// TODO Save spirit answers in DB

		/// TODO Should return true on success
		return false;		
	}

	/**
	 * Compare two score entries
	 */
	function score_entries_agree ( $team_one_id, $team_two_id )
	{
		$one = $this->get_score_entry($team_one_id);
		$two = $this->get_score_entry($team_two_id);

		if(
			($one['defaulted'] == 'us' && $two['defaulted'] == 'them') 
			||
			($one['defaulted'] == 'them' && $two['defaulted'] == 'us')
		) {
			return true;
		} 

		if(($one['score_for'] == $two['score_against']) && ($one['score_against'] == $two['score_for']) ) {
			return true;
		} 

		return false;
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
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[] = "s.$key = '" . check_query($value) . "'";
		}
	}

	// TODO: day_id is deprecated
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
	FROM
		schedule s
		INNER JOIN league l ON (l.league_id = s.league_id)
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
?>
