<?php
class Event extends LeaguerunnerObject
{
	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{
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
		global $dbh;
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

		$sth = $dbh->prepare('UPDATE registration_events SET '
			. join(", ", $fields)
			. ' WHERE registration_id = ?');

		$fields_data[] = $this->registration_id;

		$sth->execute($fields_data);
		if(1 < $sth->rowCount()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
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

		if( ! $this->name ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT into registration_events (name) VALUES (?)');
		$sth->execute( array( $this->name ) );

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM registration_events');
		$sth->execute();
		$this->registration_id = $sth->fetchColumn();

		return true;
	}
}

function event_query ( $array = array() )
{
	global $dbh; 
	$query = array();
	$params = array();
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
				$query[] = "e.$key = ?";
				$params[] = $value;
		}
	}

	$sth  = $dbh->prepare("SELECT
		e.*,
		1 as _in_database,
		UNIX_TIMESTAMP(e.open) as open_timestamp,
		UNIX_TIMESTAMP(e.close) as close_timestamp
		$fields
		FROM registration_events e
		WHERE " . implode(' AND ',$query) .  $order);

	$sth->execute( $params );

	return $sth;
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function event_load( $array = array() )
{
	$sth = event_query( $array );
	return $sth->fetchObject('Event');
}
?>
