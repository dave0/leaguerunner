<?php
class Site extends LeaguerunnerObject
{
	function load ( $array = array() )
	{
		$result = site_query( &$array );

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
				error_exit("Couldn't create site");
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
		
		$sql = "UPDATE site SET ";
		$sql .= join(", ", $fields);	
		$sql .= " WHERE site_id = %d";

		$fields_data[] = $this->site_id;

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
		
		if( ! $this->code ) {
			return false;
		}

		db_query("INSERT into site (name, code) VALUES('%s','%s')", $this->name, $this->code);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->site_id = db_result(db_query("SELECT LAST_INSERT_ID() from site"));
		
		return true;
	}
}

function site_query ( $array = array() )
{

	$query = array();
	$query[] = 'w.ward_id = s.ward_id';
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
				$query[] = "s.$key = '" . check_query($value) . "'";
		}
	}

	return db_query("SELECT 
		s.*,
		w.name AS ward_name,
		w.city AS ward_city,
		w.num  AS ward_num
		FROM site s, ward w
	WHERE " . implode(' AND ',$query) .  $order);
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function site_load( $array = array() )
{
	$s = new Site;
	if($s->load($array)) {
		return $s;
	} else {
		return null;
	}
}
?>
