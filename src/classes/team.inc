<?php
class Team extends LeaguerunnerObject
{
	/**
	 * Load a single team object from the database using the supplied query data.
	 * If more than one object matches, we will load only the first one.  If
	 * fewer than one matches, this object remains empty.
	 *
	 * @param	mixed 	$array key-value pairs that identify the team to be loaded.
	 */
	function load ( $array = array() )
	{
		$query = array();

		foreach ($array as $key => $value) {
			if($key == '_extra') {
				/* Just slap on any extra query fields desired */
				$query[] = $value;
			} else {
				$query[] = "t.$key = '" . check_query($value) . "'";
			}
		}
		
		$result = db_query_range("SELECT 
			t.*,
			IF(l.tier,CONCAT(l.name,' Tier ',l.tier),l.name) AS league_name,
			l.day AS league_day, 
			l.season AS league_season, 
			l.league_id
			FROM team t
			INNER JOIN leagueteams s ON (s.team_id = t.team_id)
			INNER JOIN league l ON (s.league_id = l.league_id)
			WHERE " . implode(' AND ',$query),0,1);

		if(1 != db_num_rows($result)) {
			return false;
		}
		
		$this->_in_database = true;
		
		$item = db_fetch_array($result);
		foreach ($item as $key => $value) {
			$this->{$key} = $value;
		}

		return true;
	}

	/**
	 * Add a player to the roster, with the given status
	 * TODO:
	 */
	function add_player( &$player, $status )
	{
	}

	/**
	 * Update status of a player currently on the roster
	 * TODO:
	 */
	function set_player_status( &$player, $status )
	{
	}

	/**
	 * Remove a player from the roster.
	 * TODO:
	 */
	function remove_player( &$player )
	{
	}

	function save ()
	{
		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create user account");
			}
		}
	
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
		
		$sql = "UPDATE team SET ";
		$sql .= join(", ", $fields);	
		$sql .= " WHERE team_id = %d";

		$fields_data[] = $this->team_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}
		
		unset($this->_modified_fields);

		# TODO: process roster list and add/remove as necessary
		
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

		db_query("INSERT into team (name) VALUES('%s')", $this->name);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->team_id = db_result(db_query("SELECT LAST_INSERT_ID() from team"));
		
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
			case 'team_id':
			case 'rating':
				return "%d";
			default:
				return "'%s'";
		}
	}

	/**
	 * Calculates the "Spence Balancing Factor" or SBF for the team.
	 * This is the average of all score differentials for games played 
	 * to-date.  A lower value indicates more even match-ups with opponents.
	 *
	 * The team SBF can be compared to the SBF for the division.  If it's too far
	 * off from the division/league SBF, it's an indication that the team is
	 * involved in too many blowout wins/losses.
	 */
	function calculate_sbf()
	{
		return db_result(db_query("SELECT ROUND(AVG(ABS(s.home_score - s.away_score)),2) FROM schedule s WHERE s.home_team = %d or s.away_team = %d", $this->team_id, $this->team_id));
	}

	/** 
	 * Calculate the average skill for this team
	 */
	function calculate_avg_skill()
	{
		return db_result(db_query("SELECT ROUND(AVG(p.skill_level),2) FROM teamroster r INNER JOIN person p ON (r.player_id = p.user_id) WHERE r.team_id = %d", $this->team_id));
	}
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function team_load( $array = array() )
{
	$t = new Team;
	if($t->load($array)) {
		return $t;
	} else {
		return null;
	}
}

?>
