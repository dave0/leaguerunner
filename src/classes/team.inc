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

		$result = team_query( $array );
		if( 1 != db_num_rows($result) ) {
			return false;
		}

		return $this->load_from_query_result( db_fetch_array($result) );
	}

	function load_from_query_result( $array = array() ) 
	{
		foreach ($array as $key => $value) {
			$this->{$key} = $value;
		}

		$this->_in_database = true;

		// Fixups 
		if($this->league_tier) {
			$this->league_name = sprintf("$this->league_name Tier %02d", $this->league_tier);
		}

		return true;
	}

	/**
	 * Add a player to the roster, with the given status
	 */
	function add_player( &$player, $status )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	/**
	 * Update status of a player currently on the roster
	 */
	function set_player_status( &$player, $status )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	/**
	 * Remove a player from the roster.
	 */
	function remove_player( &$player )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	function count_players()
	{
		$result = db_query("SELECT COUNT(r.player_id) as size
			FROM teamroster r
			WHERE 
                (r.status = 'player' OR r.status = 'captain' OR r.status = 'assistant')
				AND r.team_id = %d", $this->team_id);
		return db_result($result);
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

	/*
	 * Wow, this would be so much safer if we used transactions...
	 */
	function delete()
	{
		if ( ! $this->_in_database ) {
			return false;
		}

		// Can only delete inactive teams
		if ( $this->league_id != 1 ) {
			error_exit("Cannot delete team: Team must be in the 'Inactive Teams' league to be deleted");
		}

		// Check that this team is not scheduled for any games.  If so, we
		// bail.
		$num_games = db_result(db_query("SELECT COUNT(*) FROM schedule WHERE home_team = %d OR away_team = %d", $this->team_id, $this->team_id));
		if( $num_games > 0) {
			error_exit("Cannot delete team: Team must not have any games on the schedule");
		}

		// If we have anyone on the roster, remove 'em
		db_query("DELETE FROM teamroster WHERE team_id = %d", $this->team_id);
		// If we're in a league, remove ourself
		db_query("DELETE FROM leagueteams WHERE team_id = %d", $this->team_id);

		// Delete from the team table
		db_query("DELETE FROM team WHERE team_id = %d", $this->team_id);
	
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
		$sbf = db_result(db_query("SELECT ROUND(AVG(ABS(s.home_score - s.away_score)),2) FROM schedule s WHERE s.home_team = %d or s.away_team = %d", $this->team_id, $this->team_id));
		
		if( $sbf == "") {
			$sbf = "n/a";
		}

		return $sbf;
	}

	/** 
	 * Calculate the average skill for this team
	 */
	function calculate_avg_skill()
	{
		return db_result(db_query("SELECT ROUND(AVG(p.skill_level),2) FROM teamroster r INNER JOIN person p ON (r.player_id = p.user_id) WHERE r.team_id = %d", $this->team_id));
	}
}

function team_query( $array = array() )
{
	$order = '';
	$query = array();

	foreach ($array as $key => $value) {
		switch( $key ) {
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[] = "t.$key = '" . check_query($value) . "'";
		}
	}
		
	return db_query("SELECT 
		t.*,
		s.rank,
		l.name AS league_name,
		l.tier AS league_tier,
		l.day AS league_day, 
		l.season AS league_season, 
		l.league_id
		FROM team t
		INNER JOIN leagueteams s ON (s.team_id = t.team_id)
		INNER JOIN league l ON (s.league_id = l.league_id)
		WHERE " . implode(' AND ',$query) . $order);
}

function team_load( $array = array() )
{
	$t = new Team;
	if($t->load($array)) {
		return $t;
	} else {
		return null;
	}
}

function team_load_many( $array = array() )
{
	$result = team_query( &$array );

	if(db_num_rows($result) < 1 ) {
		return false;
	}

	$teams = array();
	while($ary = db_fetch_array($result)) {
		$g = new Team;
		if( $g->load_from_query_result( $ary ) ) {
			$teams[$g->team_id] = $g;
		}
	}

	return $teams;
}

?>
