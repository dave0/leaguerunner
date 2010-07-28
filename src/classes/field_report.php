<?php
class FieldReport extends LeaguerunnerObject
{
	public $id;
	public $field_id;
	public $game_id;
	public $reporting_user_id;
	public $created;
	public $report_text;

	function save ()
	{
		global $dbh;

		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create field report");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
				$fields[] = "$key = ?";
				$fields_data[] = null;
			} else {
				$fields[] = "$key = ?";
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sql = "UPDATE field_report SET ";
		$sql .= join(", ", $fields);
		$sql .= " WHERE id = ?";

		$fields_data[] = $this->id;

		$sth = $dbh->prepare( $sql );
		$sth->execute( $fields_data );
		if(1 != $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
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

		$sth = $dbh->prepare('INSERT into field_report (field_id, game_id, reporting_user_id) VALUES(?,?,?)');
		$sth->execute(array($this->field_id, $this->game_id, $this->reporting_user_id));

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM field_report');
		$sth->execute();
		$this->id = $sth->fetchColumn();

		return true;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	static function query ( $array = array() )
	{
		global $dbh;

		$query = array();
		$params = array();
		$order = '';
		foreach ($array as $key => $value) {
			switch( $key ) {
				case 'date_played':
					$query[] = 'g.game_date = ?';
					$params[] = $value;
				case '_extra':
					/* Just slap on any extra query fields desired */
					$query[] = $value;
					break;
				case '_order':
					$order = ' ORDER BY ' . $value;
					break;
				default:
					$query[] = "t.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			CONCAT_WS(' ', p.firstname, p.lastname) AS reporting_user_fullname,
			g.game_date AS date_played,
			t.*,
			1 AS _in_database
			FROM field_report t
				LEFT JOIN gameslot g ON (g.game_id = t.game_id)
				LEFT JOIN person p ON (p.user_id = t.reporting_user_id)
		WHERE " . implode(' AND ',$query) .  $order);

		$sth->execute( $params );
		return $sth;
	}
}

?>
