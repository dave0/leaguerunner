<?php
class GameSlot extends LeaguerunnerObject
{

	var $leagues;
	var $_leagues_loaded;

	function GameSlot ()
	{
		$this->LeaguerunnerObject(); // Upcall to parent
		$this->_leagues_loaded = false;
		$this->leagues = array();
	}

	function load ( $array = array() )
	{
		$result = slot_query( &$array );

		if(1 != db_num_rows($result)) {
			return false;
		}

		$this->_in_database = true;
		
		$item = db_fetch_array($result);
		foreach ($item as $key => $value) {
			$this->{$key} = $value;
		}

		$result = db_query("SELECT league_id from league_gameslot_availability WHERE slot_id = %d", $this->slot_id);
		while( $league = db_fetch_object( $result ) ) {
			$league->league_status = 'loaded';
			$this->leagues[$league->league_id] = $league;
		}

		/* set derived attributes */
		if($this->fid) {
			$this->field = field_load( array('fid' => $this->fid) );
		}

		return true;
	}

	function add_league ( &$thing )
	{
		if( !is_object($thing) ) {
			$object->league_id = $thing;
			$thing = &$object;
		}
	
		if( array_key_exists( $thing->league_id, $this->leagues) ) {
			return false;
		}
		
		$this->leagues[$thing->league_id] = $thing;
		$this->leagues[$thing->league_id]->league_status = 'add';
	}

	function remove_league( &$thing )
	{
		if( !is_object($thing) ) {
			$object->league_id = $thing;
			$thing = &$object;
		}
		if( array_key_exists( $thing->league_id, $this->leagues) ) {
			$this->leagues[$thing->league_id]->league_status = 'delete';
		}
		return false;
	}
	
	function save ()
	{
		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create gameslot");
			}
		}
		
		if(count($this->_modified_fields)) {
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
			
			$sql = "UPDATE gameslot SET ";
			$sql .= join(", ", $fields);	
			$sql .= " WHERE slot_id = %d";

			$fields_data[] = $this->slot_id;

			db_query( $sql, $fields_data);
			if(1 < db_affected_rows()) {
				# Affecting zero rows is possible
				error_exit("Internal error: Strange number of rows affected");
			}
			unset($this->_modified_fields);
		}

		reset($this->leagues);
		foreach ( $this->leagues as $l ) {
			switch( $l->league_status ) {
				case 'add':
					db_query('INSERT INTO league_gameslot_availability (slot_id, league_id) VALUES (%d,%d)', $this->slot_id, $l->league_id);
					$this->leagues[$l->league_id]->league_status = 'loaded';
					break;
				case 'delete':
					db_query("DELETE FROM league_gameslot_availability WHERE slot_id = %d AND league_id = %d", $this->slot_id, $l->league_id);
					unset($this->leagues[$l->league_id]);
					break;
				default:
					# Skip if not add or delete
					break;
			}
		}
		
		return true;
	}

	function create ()
	{
		if ($this->_in_database) {
			return false;
		}

		if ( ! $this->fid ) {
			return false;
		}
		
		if ( ! $this->game_date ) {
			return false;
		}
		
		if ( ! $this->game_start ) {
			return false;
		}

		db_query("INSERT into gameslot (fid,game_date,game_start) VALUES(%d, '%s', '%s')", $this->fid, $this->game_date, $this->game_start);
		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		$this->slot_id = db_result(db_query("SELECT LAST_INSERT_ID() from gameslot"));
		return true;
	}

	/*
	 * Wow, this would be so much safer if we used transactions...
	 */
	function delete ()
	{
		if ( ! $this->_in_database ) {
			return false;
		}

		// Check that no games are scheduled
		if ( $this->game_id ) {
			return false;
		}

		// If we have assignments to leagues, remove them
		db_query("DELETE FROM league_gameslot_availability WHERE slot_id = %d", $this->slot_id);

		// Then, delete gameslot from table
		db_query("DELETE FROM gameslot WHERE slot_id = %d", $this->slot_id);
		return true;
	}
}

function slot_query ( $array = array() )
{

	$order = '';
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
				$query[] = "g.$key = '" . check_query($value) . "'";
		}
	}

	return db_query("SELECT 
		g.slot_id,
		field.name AS field_name,
		field.num AS field_num,
		g.fid,
		g.game_date,
		UNIX_TIMESTAMP(g.game_date) AS date_timestamp,
		TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
		TIME_FORMAT(g.game_end,'%H:%i') AS game_end,
		g.game_id
	FROM
		gameslot g
		INNER JOIN field ON (g.fid = field.fid)
	WHERE " . implode(' AND ',$query) .  $order);
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function slot_load( $array = array() )
{
	$g = new GameSlot;
	if($g->load($array)) {
		return $g;
	} else {
		return null;
	}
}
?>
