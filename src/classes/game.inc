<?php
class Game extends LeaguerunnerObject
{
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
		
		$sql = "UPDATE schedule SET ";
		$sql .= join(", ", $fields);	
		$sql .= " WHERE game_id = %d";

		$fields_data[] = $this->game_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}
		
		unset($this->_modified_fields);
		return true;
	}

	/**
	 * TODO
	 */
	function create ()
	{
		return false;
	}
}

function game_query ( $array = array() )
{

	$query[] = 's.game_id = g.game_id';
	$order = '';
	foreach ($array as $key => $value) {
		switch( $key ) {
			case 'game_date':
				$query[] = "g.game_date = '" . check_query($value) . "'";
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

	return db_query("SELECT 
		s.*,
		s.home_team AS home_id,
		h.name AS home_name, 
		h.rating AS home_rating,
		s.away_team AS away_id,
		a.name AS away_name,
		a.rating AS away_rating,
		g.game_date,
		TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
		g.site_id,
		g.field_num,
		CONCAT(YEAR(g.game_date),DAYOFYEAR(g.game_date)) as day_id,
		CONCAT(t.code, ' ', g.field_num) AS field_code,
		UNIX_TIMESTAMP(CONCAT(g.game_date,' ',g.game_start)) as timestamp
	FROM
		schedule s,
		gameslot g
		LEFT JOIN site t ON (t.site_id = g.site_id)
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
