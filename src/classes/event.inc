<?php
class Event extends LeaguerunnerObject
{
	function load ( $array = array() )
	{
		$result = event_query( &$array );

		if(1 != db_num_rows($result)) {
			return false;
		}

		$this->_in_database = true;

		$item = db_fetch_array($result);

		foreach ($item as $key => $value) {
			$this->{$key} = $value;
		}

		// Split the open and close dates
		list ($this->open_date, $this->open_time) = explode (' ', $this->open);
		$this->open_time = substr ($this->open_time, 0, 5);
		list ($this->close_date, $this->close_time) = explode (' ', $this->close);
		$this->close_time = substr ($this->close_time, 0, 5);
		if ($this->close_time == '23:59') {
			$this->close_time = '24:00';
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
				error_exit("Couldn't create event");
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

		$sql = "UPDATE registration_events SET ";
		$sql .= join(", ", $fields);
		$sql .= " WHERE registration_id = %d";

		$fields_data[] = $this->registration_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}

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
		db_query("INSERT into registration_events (name) VALUES ('%s')", $this->name);

		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->registration_id = db_result(db_query("SELECT LAST_INSERT_ID() from registration_events"));

		return true;
	}
}

function event_query ( $array = array() )
{
	$query = array();
	$query[] = '1 = 1';
	$fields = '';
	$order = '';
	foreach ($array as $key => $value) {
		switch( $key ) {
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_fields':
				$fields = ", $value";
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[] = "e.$key = '" . check_query($value) . "'";
		}
	}

	return db_query("SELECT
		e.*,
		UNIX_TIMESTAMP(e.open) as open_timestamp,
		UNIX_TIMESTAMP(e.close) as close_timestamp
		$fields
		FROM registration_events e
	WHERE " . implode(' AND ',$query) .  $order);
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function event_load( $array = array() )
{
	$s = new Event;
	if($s->load($array)) {
		return $s;
	} else {
		return null;
	}
}
?>
