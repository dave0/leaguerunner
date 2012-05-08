<?php
class GameSlot extends LeaguerunnerObject
{
	var $leagues;

	function __construct ( )
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT league_id from league_gameslot_availability WHERE slot_id = ?');
		$sth->execute(array($this->slot_id));
		$this->leagues = array();
		while( $league = $sth->fetch(PDO::FETCH_OBJ) ) {
			$league->league_status = 'loaded';
			$this->leagues[$league->league_id] = $league;
		}

		/* set derived attributes */
		if($this->fid) {
			$this->field = Field::load( array('fid' => $this->fid) );
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
		global $dbh;
		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create gameslot");
			}
		}

		if(count($this->_modified_fields)) {
			$fields      = array();
			$fields_data = array();

			foreach ( $this->_modified_fields as $key => $value) {
				$fields[] = "$key = ?";
				if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
					$fields_data[] = null;
				} else {
					$fields_data[] = $this->{$key};
				}
			}

			if(count($fields_data) != count($fields)) {
				error_exit("Internal error: Incorrect number of fields set");
			}

			$fields_data[] = $this->slot_id;

			$sth = $dbh->prepare('UPDATE gameslot SET '
				. join(", ", $fields)
				. 'WHERE slot_id = ?');

			$sth->execute( $fields_data);

			if($sth->rowCount() > 1) {
				error_exit("Internal error: Strange number of rows affected");
			}
			unset($this->_modified_fields);
		}

		reset($this->leagues);
		foreach ( $this->leagues as $l ) {
			switch( $l->league_status ) {
				case 'add':
					$sth = $dbh->prepare('INSERT INTO league_gameslot_availability (slot_id, league_id) VALUES (?,?)');
					$sth->execute(array($this->slot_id, $l->league_id));
					$this->leagues[$l->league_id]->league_status = 'loaded';
					break;
				case 'delete':
					$sth = $dbh->prepare('DELETE FROM league_gameslot_availability WHERE slot_id = ? AND league_id = ?');
					$sth->execute(array($this->slot_id, $l->league_id));
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
		global $dbh;
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

		$sth = $dbh->prepare('INSERT into gameslot (fid,game_date,game_start) VALUES(?,?,?)');
		$sth->execute(array($this->fid, $this->game_date, $this->game_start));
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() from gameslot');
		$sth->execute();
		$this->slot_id = $sth->fetchColumn();
		return true;
	}

	function delete ()
	{
		if ( ! $this->_in_database ) {
			return false;
		}

		// Check that no games are scheduled
		if ( $this->game_id ) {
			return false;
		}

		$queries = array(
			'DELETE FROM league_gameslot_availability WHERE slot_id = ?',
			'DELETE FROM gameslot WHERE slot_id = ?'
		);

		return $this->generic_delete( $queries, $this->slot_id );
	}

	/**
	 * Game end time, for display purposes.
	 *
	 * If one is provided for this game, we use it.  Otherwise, we calculate based on sunset time.
	 */
	function display_game_end()
	{
		# Return our end time, if available
		if( $this->game_end ) {
			return $this->game_end;
		}

		# Otherwise, guess based on date
		if( $this->date_timestamp ) {
			return local_sunset_for_date( $this->date_timestamp );
		}

		return '';
	}

	static function query ( $array = array() )
	{
		global $dbh;
		$query  = array();
		$params = array();
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
					$query[] = "g.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			g.slot_id,
			COALESCE(field.name, pf.name) AS field_name,
			field.num  AS field_num,
			COALESCE(field.code, pf.code) AS field_code,
			g.fid,
			g.game_date,
			UNIX_TIMESTAMP(g.game_date) AS date_timestamp,
			TIME_FORMAT(g.game_start,'%H:%i') AS game_start,
			TIME_FORMAT(g.game_end,'%H:%i') AS game_end,
			g.game_id,
			1 as _in_database
		FROM
			gameslot g
			INNER JOIN field ON (g.fid = field.fid)
			LEFT JOIN field pf ON (pf.fid = field.parent_fid)
		WHERE " . implode(' AND ',$query) .  $order);

		$sth->execute($params);
		return $sth;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}
}
?>
