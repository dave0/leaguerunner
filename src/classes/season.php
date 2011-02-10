<?php
class Season extends LeaguerunnerObject
{
	public $id;
	public $display_name;
	public $season;
	public $year;

	public $leagues;

	private $_leagues_loaded;

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

		$this->leagues = League::load_many( array( 'season' => $this->id, '_order' => "year,FIELD(MAKE_SET((day & 62), 'BUG','Monday','Tuesday','Wednesday','Thursday','Friday'),'Monday','Tuesday','Wednesday','Thursday','Friday'), tier, league_id") );

		// Cheat.  If we didn't find any leagues, set $this->leagues to an empty
		// array again.
		if( !is_array($this->leagues) ) {
			$this->leagues = array();
		}

		$this->_leagues_loaded = true;
		return true;
	}

}


?>
