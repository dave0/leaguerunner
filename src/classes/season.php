<?php
class Season extends LeaguerunnerObject
{
	public $id;
	public $display_name;
	public $season;
	public $year;

	public $leagues;
	private $_leagues_loaded;

	public $events;
	private $_events_loaded;

	static function query ( $array = array() )
	{
		global $dbh;
		$order = '';
		$query = array('1 = 1');
		$params = array();

		foreach ($array as $key => $value) {
			switch( $key ) {
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[]  = "s.$key = ?";
				$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT s.*, 1 as _in_database FROM season s WHERE " . implode(' AND ',$query) . $order);
		$sth->execute($params);
		return $sth;
	}

	static function load( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	static function load_many( $array = array() )
	{
		$sth = self::query( $array );

		$seasons = array();
		while($s = $sth->fetchObject(get_class()) ) {
			$seasons[$s->id] = $s;
		}

		return $seasons;
	}

	function load_leagues ()
	{
		if($this->_leagues_loaded) {
			return true;
		}

		$this->leagues = League::load_many( array( 'season' => $this->id, '_order' => "FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier, league_id") );

		// Cheat.  If we didn't find any leagues, set $this->leagues to an empty
		// array again.
		if( !is_array($this->leagues) ) {
			$this->leagues = array();
		}

		$this->_leagues_loaded = true;
		return true;
	}

	function load_events ()
	{
		global $lr_session;

		if($this->_events_loaded) {
			return true;
		}

		$query_args = array(
			'season_id' => $this->id,
			'_order' => 'e.type,e.open,e.close,e.registration_id'
		);

		if( $lr_session->is_admin()) {
			$query_args['_extra'] = 'e.open < e.close';
		} else {
			$query_args['_extra'] = 'e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()';
		}

		$this->events = Event::load_many( $query_args );

		// Cheat.  If we didn't find any events, set $this->events to an empty
		// array again.
		if( !is_array($this->events) ) {
			$this->events = array();
		}

		$this->_events_loaded = true;
		return true;
	}

	function save ()
	{
		global $dbh;

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create season");
			}
		}

		if( count($this->_modified_fields) > 0 ) {
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
				error_exit('Internal error: Incorrect number of fields set');
			}

			$fields_data[] = $this->id;

			$sth = $dbh->prepare( 'UPDATE season SET '
				. join(', ', $fields)
				. ' WHERE id = ?');

			$sth->execute( $fields_data );

			if($sth->rowCount() < 1) {
				$err = $sth->errorInfo();
				error_exit("Error: database not updated: $err[2]");
			}
		}

		unset($this->_modified_fields);
		return true;
	}
}


?>
