<?php
class Registration extends LeaguerunnerObject
{
	var $_answer_entries;

	function load ( $array = array() )
	{
		$result = registration_query( &$array );

		if(1 != db_num_rows($result)) {
			return false;
		}

		$this->_in_database = true;

		$item = db_fetch_array($result);

		foreach ($item as $key => $value) {
			$this->{$key} = $value;
		}

		return true;
	}

	function save ()
	{
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
			if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
				$fields[] = "$key = %s";
				$fields_data[] = 'NULL';
			} else {
				$fields[] = $key . " = " . $this->get_placeholder($key, $this->{$key});
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sql = "UPDATE registrations SET ";
		$sql .= join(", ", $fields);
		$sql .= " WHERE order_id = %d";

		$fields_data[] = $this->order_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);

		return true;
	}

	function create ()
	{
		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->user_id || ! $this->registration_id ) {
			return false;
		}

		db_query('INSERT
					INTO registrations (user_id, registration_id, time)
					VALUES (%d, %d, NOW())',
				$this->user_id,
				$this->registration_id);

		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->order_id = db_result(db_query("SELECT LAST_INSERT_ID() from registrations"));

		return true;
	}

	/**
	 * Save the answers to the registration questions
	 */
	function save_answers ($formbuilder, $answers)
	{
		if( !is_array($answers) ) {
			die("Answser argument to save_answers() must be an array");
		}

		// Store in object
		$this->_answer_entries = $answers;

		// form builder might be null if there are no questions
		if( isset( $formbuilder ) ) {
			// save in DB
			while( list($qkey, $answer) = each($answers) ) {
				$question = $formbuilder->_questions[$qkey];
				// We need to skip "answers" for labels and descriptions
				if($question->qtype != 'label' && $question->qtype != 'description')
				{
					db_query("REPLACE INTO registration_answers (order_id,qkey,akey) VALUES (%d,'%s','%s')",
						$this->order_id,
						$qkey,
						$answer
					);
					if( db_affected_rows() < 1) {
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
				$query[] = "r.$key = '" . check_query($value) . "'";
		}
	}

	global $TZ_ADJUST;
	return db_query("SELECT
		r.*,
		DATE_ADD(r.time, INTERVAL %d MINUTE) as time,
		DATE_ADD(r.modified, INTERVAL %d MINUTE) as modified
		FROM registrations r
		WHERE " . implode(' AND ',$query) .  $order,
		-$TZ_ADJUST,
		-$TZ_ADJUST
	);
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function registration_load( $array = array() )
{
	$s = new Registration;
	if($s->load($array)) {
		return $s;
	} else {
		return null;
	}
}
?>
