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
			$this->fullname = "$this->name Tier $this->tier";
		} else {
			$this->fullname = $this->name;
		}

		return true;
	}

	/**
	 * Load teams for this league.
	 */
	function load_teams ()
	{
		if($this->_teams_loaded) {
			return true;
		}

		$result = db_query("SELECT t.team_id FROM leagueteams l INNER JOIN team t ON (t.team_id = l.team_id) WHERE l.league_id = %d ORDER BY t.name", $this->league_id);
		while( $array = db_fetch_array($result) ) {
			$t = team_load( array('team_id' => $array['team_id']) );
			if( ! $t ) {
				error_exit("Invalid team listed in league $league->league_id");
			}
			$t->team_status = 'loaded';
			$this->teams[$t->team_id] = $t;
		}
		$this->_teams_loaded = true;	
		return true;
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
			case 'max_teams':
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
		return db_result(db_query("SELECT ROUND(AVG(ABS(s.home_score - s.away_score)), 2) FROM schedule s WHERE s.league_id = %d", $this->league_id));
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
