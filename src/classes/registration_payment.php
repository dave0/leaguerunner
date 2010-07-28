<?php
class RegistrationPayment extends LeaguerunnerObject
{
	public $order_id;
	public $payment_type;
	public $payment_amount;
	public $paid_by;
	public $date_paid;
	public $payment_method;
	public $entered_by;
	public $entered_by_user;

	function save ()
	{
		global $dbh;

		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create registration_payments entry");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			if( $key == 'order_id' || $key == 'payment_type' ) {
				// Skip these two, they're our primary key
				continue;
			}
			$fields[] = "$key = ?";
			if( empty($this->{$key}) ) {
				$fields_data[] = null;
			} else {
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sth = $dbh->prepare('UPDATE registration_payments SET '
			. join(", ", $fields)
			. ' WHERE order_id = ? AND payment_type = ?');

		$fields_data[] = $this->order_id;
		$fields_data[] = $this->payment_type;

		$sth->execute($fields_data);
		if(1 < $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);

		return true;
	}

	function delete()
	{
		global $dbh;


		if ( ! $this->_in_database ) {
			return false;
		}

		$queries = array(
			'DELETE FROM registration_payments WHERE order_id = ? AND payment_type = ?'
		);

		return $this->generic_delete( $queries, array( $this->order_id, $this->payment_type) );
	}

	function create ()
	{
		global $dbh;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->order_id || ! $this->payment_type ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT INTO registration_payments (order_id, payment_type, date_paid) VALUES (?,?, NOW())');
		$sth->execute( array( $this->order_id, $this->payment_type) );

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		return true;
	}

	function validate ()
	{
		$errors = "";

		if( ! validate_nonblank($this->payment_type) ) {
			$errors .= "\n<li>Payment Type must be nonblank";
		}

		if( ! validate_nonblank($this->payment_method) ) {
			$errors .= "\n<li>Payment Method must be nonblank";
		}

		if( ! preg_match("/^\d+(?:\.\d\d)?$/", $this->payment_amount) ) {
			$errors .= "\n<li>Amount must be nonblank and a valid dollar amount";
		}

		if( ! validate_nonblank($this->date_paid) ) {
			$errors .= "\n<li>Payment date must be nonblank";
		}

		list( $yyyy, $mm, $dd) = preg_split("/[\/-]/", $this->date_paid);
		if( !validate_date_input($yyyy, $mm, $dd) ) {
			$errors .= "\n<li>Payment date must be valid";
		}

		return $errors;
	}

	function entered_by_name()
	{
		if( ! $this->entered_by_user ) {
			$this->entered_by_user = Person::load(array( 'user_id' => $this->entered_by ));
		}
		return $this->entered_by_user->fullname;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	function query ( $array = array() )
	{
		global $dbh;

		$query = array();
		$query[] = '1 = 1';
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
					$query[] = "p.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			1 as _in_database,
			p.*
			FROM registration_payments p
			WHERE " . implode(' AND ',$query) .  $order
		);
		$sth->execute( $params );
		return $sth;
	}

	function load_many ( $array = array() )
	{
		$sth = self::query( $array );

		$results = array();
		while( $r = $sth->fetchObject(get_class(), array(LOAD_RELATED_DATA))) {
			array_push( $results, $r);
		}

		return $results;
	}

}

?>
