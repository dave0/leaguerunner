<?php
class LeaguerunnerObject 
{
	var $_modified_fields;
	var $_in_database;

	function LeaguerunnerObject ()
	{
		$this->_in_database = false;
	}

	/**
	 * Load a single object from the database using the supplied query data.
	 * If more than one object matches, we will load only the first one.  If
	 * fewer than one matches, this object remains empty.
	 *
	 * @param	mixed 	$array key-value pairs that identify the team to be
	 * loaded.
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function load ( $array = array() )
	{
		die("Implemented by subclass");
	}

	/** 
	 * Set a particular field for later insertion/update into database.
	 */
	function set ( $key, $value )
	{
		// TODO: check that key is in fact a valid key before doing this

		// No need to set it if it already has the same value
		if( array_key_exists( $key, get_object_vars( $this ) ) ) {
			if( $this->{$key} == $value ) {
				return true;
			}
		}

		$this->_modified_fields[$key] = true;	
		$this->{$key} = $value;
		return true;
	}

	/**
	 * Save the object in the database, creating if necessary
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function save ()
	{
		die("Implemented by subclass");
	}

	/**
	 * Create the object in the database.  Should only be called
	 * from within save().
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function create ()
	{
		die("Implemented by subclass");
	}

	/**
	 * Delete an object from the system
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function delete ()
	{
		die("Implemented by subclass");
	}

	/** 
	 * Get the placeholder to be used for query substitution.
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function get_placeholder( $key, $value )
	{
		// Null doesn't need to be quoted
		if(0 == strcasecmp($value,'NULL')) {
			return "%s";
		}

		// Hack for NOW() timestamps
		if(0 == strcasecmp($value,'NOW()')) {
			return "%s";
		}

		// Hack for _id values
		if( '_id' == substr($value, -3) ) {
			return '%d';
		}

		// Else, assume string
		return "'%s'";
	}
}
?>
