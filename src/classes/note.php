<?php
class Note extends LeaguerunnerObject
{
	public $id;
	public $creator_id;
	public $creator;
	public $assoc_id;
	public $assoc_type;
	protected $assoc_obj;
	public $note;
	public $created_ts;
	public $edited_ts;

	function create ()
	{
		global $dbh, $lr_session;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->note ) {
			return false;
		}

		if( ! $this->assoc_id ) {
			return false;
		}

		if( ! $this->assoc_type ) {
			return false;
		}

		$this->creator_id = $lr_session->user->user_id;

		$sth = $dbh->prepare('INSERT INTO note (assoc_id, assoc_type, note, creator_id) VALUES(?,?,?,?)');
		$sth->execute( array( $this->assoc_id, $this->assoc_type, $this->note, $this->creator_id ) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}
		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM note');
		$sth->execute();
		$this->id = $sth->fetchColumn();

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
				error_exit("Failed to create note");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			$fields[] = "$key = ?";
			$fields_data[] = $this->{$key};
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sth = $dbh->prepare('UPDATE note SET '
			. join(", ", $fields)
			. ', edited = NOW() WHERE id = ?');

		$fields_data[] = $this->id;

		$sth->execute( $fields_data );
		if(1 != $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);

		return true;
	}

	function delete()
	{
		if ( ! $this->_in_database ) {
			return false;
		}

		$queries = array(
			'DELETE FROM note WHERE id = ?',
		);

		return $this->generic_delete( $queries, $this->id );
	}

	static function query( $array = array() )
	{
		global $CONFIG, $dbh;

		$order = '';
		$query = array();
		$params = array();

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
					$query[]  = "n.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			n.*,
			1 AS _in_database,
			UNIX_TIMESTAMP(n.created) AS created_ts,
			UNIX_TIMESTAMP(n.edited)  AS edited_ts
			FROM note n
			WHERE " . implode(' AND ',$query) . $order);
		$sth->execute( $params );

		return $sth;
	}

	static function load( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject(get_class());
	}

	static function load_many( $array = array() )
	{
		$sth = self::query( $array );
		$objs = array();
		while( $o = $sth->fetchObject(get_class(), array(LOAD_RELATED_DATA))) {
			$objs[$o->id] = $o;
		}

		return $objs;
	}

	function load_creator ()
	{
		if( ! $this->creator ) {
			$this->creator = Person::load( array( 'user_id' => $this->creator_id ) );
		}
		return $this->creator;
	}

	function assoc_obj ()
	{
		if( ! $this->assoc_obj ) {
			if( $this->assoc_type == 'person' ) {
				$this->assoc_obj = Person::load( array( 'user_id' => $this->assoc_id ) );
			} elseif( $this->assoc_type == 'team' ) {
				$this->assoc_obj = Team::load( array( 'team_id' => $this->assoc_id ) );
			} else {
				die("Invalid assoc_type of " . $this->assoc_type);
			}
		}
		return $this->assoc_obj;
	}

	function assoc_name ()
	{
		$obj = $this->assoc_obj();
		if( $this->assoc_type == 'person' ) {
			return $obj->fullname;
		} elseif( $this->assoc_type == 'team' ) {
			return $obj->name;
		} else {
			die("Invalid assoc_type of " . $this->assoc_type);
		}
	}
}

class PersonNote extends Note
{
	function __construct ( )
	{
		$this->assoc_type = 'person';
	}

	function get_person_id()
	{
		return $this->assoc_id;
	}
}

class TeamNote extends Note
{
	function __construct ( )
	{
		$this->assoc_type = 'team';
	}

	function get_team_id()
	{
		return $this->assoc_id;
	}
}

?>
