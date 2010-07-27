<?php
class Season extends LeaguerunnerObject
{
	public $id;
	public $display_name;
	public $season;
	public $year;

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


}


?>
