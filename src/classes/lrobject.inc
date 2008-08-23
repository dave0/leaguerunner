<?php
class LeaguerunnerObject 
{
	var $_modified_fields;
	var $_in_database;

	function LeaguerunnerObject ( $array = null )
	{
		$this->_in_database = false;

		if( is_array($array) ) {
			$this->load($array);
		}
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
			if( $this->{$key} === $value ) {
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
	 * Delete an object from the db
	 */
	function generic_delete ( $queries, $id )
	{
		global $dbh;

		$dbh->beginTransaction();

		$all_ok = true;
		foreach($queries as $query) {
			$sth = $dbh->prepare($query);
			if( ! $sth->execute( array( $id ) ) ) {
				$all_ok = false;
			}
		}

		if( $all_ok ) {
			$dbh->commit();
		} else {
			$dbh->rollback();
		}

		return $all_ok;
	}
}
?>
