<?php
class Registration extends LeaguerunnerObject
{
	var $_answer_entries;

	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{
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
				error_exit("Couldn't create registration");
			}
		}

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
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sth = $dbh->prepare('UPDATE registrations SET '
			. join(", ", $fields)
			. ' WHERE order_id = ?');

		$fields_data[] = $this->order_id;

		$sth->execute($fields_data);
		if(1 < $sth->rowCount()) {
			# Affecting zero rows is possible
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

		if( ! $this->user_id || ! $this->registration_id ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT INTO registrations (user_id, registration_id, time) VALUES (?,?, NOW())');
		$sth->execute( array( $this->user_id, $this->registration_id) );

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM registrations');
		$sth->execute();
		$this->order_id = $sth->fetchColumn();

		return true;
	}

	/**
	 * Save the answers to the registration questions
	 */
	function save_answers ($formbuilder, $answers)
	{
		global $dbh;

		if( !is_array($answers) ) {
			die("Answser argument to save_answers() must be an array");
		}

		// Store in object
		$this->_answer_entries = $answers;

		// form builder might be null if there are no questions
		if( isset( $formbuilder ) ) {
			// save in DB
			$sth = $dbh->prepare('REPLACE INTO registration_answers (order_id, qkey, akey) VALUES (?,?,?)');
			while( list($qkey, $answer) = each($answers) ) {
				$question = $formbuilder->_questions[$qkey];
				// We need to skip "answers" for labels and descriptions
				if($question->qtype != 'label' && $question->qtype != 'description')
				{
					$sth->execute( array(
						$this->order_id,
						$qkey,
						$answer
					) );
					if( $sth->rowCount() < 1) {
						return false;
					}
				}
			}
		}

		return true;
	}
}

function registration_query ( $array = array() )
{
	global $TZ_ADJUST, $dbh;

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
				$query[] = "r.$key = ?";
				$params[] = $value;
		}
	}

	// Yes, do it twice.
	$params = array_merge( array( -$TZ_ADJUST, -$TZ_ADJUST), $params );

	$sth = $dbh->prepare("SELECT 
		1 as _in_database,
		r.*,
		DATE_ADD(r.time, INTERVAL ? MINUTE) as time,
		DATE_ADD(r.modified, INTERVAL ? MINUTE) as modified
		FROM registrations r
		WHERE " . implode(' AND ',$query) .  $order
	);
	$sth->execute( $params );
	return $sth;
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function registration_load( $array = array() )
{
	$sth = registration_query( $array );
	return $sth->fetchObject('Registration');
}
?>
