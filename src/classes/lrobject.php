<?php
class LeaguerunnerObject
{
	protected $_modified_fields;
	protected $_in_database = false;

	function __construct ( $load_mode = LOAD_RELATED_DATA )
	{
		return true;
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

		$this->touch ($key);
		$this->{$key} = trim(stripslashes($value));
		return true;
	}

	/**
	 * Get a particular field.
	 */
	function get ( $key )
	{
		// TODO: check that key is in fact a valid key before doing this

		// No need to set it if it already has the same value
		if( array_key_exists( $key, get_object_vars( $this ) ) ) {
			return $this->{$key};
		}

		return null;
	}

	/**
	 * Sets a particular field as modified so it will be inserted/updated into database.
	 */
	function touch ( $key )
	{
		$this->_modified_fields[$key] = true;
	}

	/**
	 * Save the object in the database, creating if necessary
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function save ()
	{
		die("Save implemented by subclass");
	}

	/**
	 * Create the object in the database.  Should only be called
	 * from within save().
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function create ()
	{
		die("Create implemented by subclass");
	}

	/**
	 * Delete an object from the system
	 * TODO: pull common subclass code up here and remove from subclass
	 */
	function delete ()
	{
		die("Delete implemented by subclass");
	}

	/**
	 * Delete an object from the db
	 */
	function generic_delete ( $queries, $args )
	{
		global $dbh;

		$dbh->beginTransaction();

		if( ! is_array( $args ) ) {
			$args = array ( $args );
		}

		$all_ok = true;
		foreach($queries as $query) {
			$sth = $dbh->prepare($query);
			if( ! $sth->execute( $args ) ) {
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
