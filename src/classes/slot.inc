<?php
class GameSlot extends LeaguerunnerObject
{
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

		/* set derived attributes */
		if($this->site_id) {
			$this->site = site_load( array('site_id' => $this->site_id) );
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
		
		$sql = "UPDATE gameslot SET ";
		$sql .= join(", ", $fields);	
		$sql .= " WHERE id = %d";

		$fields_data[] = $this->slot_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}
		
		unset($this->_modified_fields);
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
		g.site_id,
		g.field_num,
		g.game_date,
		TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
		TIME_FORMAT(g.game_end,'%H:%i') AS game_end,
		g.game_id
	FROM
		gameslot g
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
