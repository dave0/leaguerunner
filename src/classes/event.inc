<?php
class Event extends LeaguerunnerObject
{
	var $registration_id;
	var $name;
	var $description;
	var $type;
	var $cost;
	var $gst;
	var $pst;
	var $open;
	var $close;
	var $cap_male;
	var $cap_female;
	var $multiple;
	var $anonymous;

	function __construct ( $load_mode = LOAD_RELATED_DATA ) 
	{
		// Split the open and close dates
		if( isset($this->open) ) {
			list ($this->open_date, $this->open_time) = explode (' ', $this->open);
			$this->open_time = substr ($this->open_time, 0, 5);
		}
		if( $this->close ) {
			list ($this->close_date, $this->close_time) = explode (' ', $this->close);
			$this->close_time = substr ($this->close_time, 0, 5);
			if ($this->close_time == '23:59') {
				$this->close_time = '24:00';
			}
		}

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
				error_exit("Couldn't create event");
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

		$sth = $dbh->prepare('UPDATE registration_events SET '
			. join(", ", $fields)
			. ' WHERE registration_id = ?');

		$fields_data[] = $this->registration_id;

		$sth->execute($fields_data);
		if(1 != $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
			fatal_sql_error( $sth );
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

		if( ! $this->name ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT into registration_events (name) VALUES (?)');
		$sth->execute( array( $this->name ) );

		if( 1 != $sth->rowCount() ) {
			return false;
		}

		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM registration_events');
		$sth->execute();
		$this->registration_id = $sth->fetchColumn();

		$this->_in_database = true;

		return true;
	}

	function delete()
	{
		global $dbh;
		if ( ! $this->_in_database ) {
			return false;
		}

		$queries = array(
			'DELETE FROM registration_events WHERE registration_id = ?',
			'DELETE FROM registration_answers USING registration_answers, registrations WHERE registration_answers.order_id = registrations.order_id AND registrations.registration_id = ?',
			'DELETE FROM registrations WHERE registration_id = ?'

		);

		return $this->generic_delete( $queries, $this->registration_id );
	}

	function load_survey ( $extra = false, $user = null )
	{
		$formbuilder = new FormBuilder;
		$formbuilder->load( $this->formkey() );
		if( $extra ) {
			$this->add_auto_questions( $formbuilder, $user );
		}
		return $formbuilder;
	}

	function copy_survey_from ( $other_event )
	{
		$formbuilder = $other_event->load_survey( false );
		$formbuilder->_name = $this->formkey();
		$formbuilder->save(true);
	}

	function formkey ( )
	{
		return 'registration_' . $this->registration_id;
	}

	function total_cost ( )
	{
		return sprintf('%.2f',
			$this->cost + $this->gst + $this->pst);
	}

	function add_auto_questions ( &$formbuilder, $user = null )
	{
		switch ($this->type) {
			// Individual registrations have no extra questions at this time
			case 'membership':
			case 'individual_event':
			case 'individual_league':
				break;

			// Team registrations have these additional questions
			case 'team_league':
			case 'team_event':
				$teams = array();
				if( $user ) {
					$teams = $user->teams_for_pulldown('captain');
				}
				$formbuilder->add_question('__auto__team_id', 'Team', 'Select the team you wish to register. If your team is not listed, please create a new team before continuing', 'multiplechoice', true, -99, $teams);
				break;
		}
	}

        /*
         * Returns long name of the event type
         */
	function get_long_type ()
	{
		$types = event_types();
		return $types[$this->type];
	}

	function get_gender_stats ()
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT p.gender, COUNT(order_id)
			FROM registrations r
				LEFT JOIN person p ON r.user_id = p.user_id
				WHERE r.registration_id = ?
					AND r.payment != "Refunded"
			GROUP BY p.gender
			ORDER BY gender');
		$sth->execute( array( $this->registration_id ) );

		$results = array();
		while($row = $sth->fetch(PDO::FETCH_NUM)) {
			$results[ $row[0] ] = $row[1];
		}

		return $results;
	}

	function get_payment_stats ()
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT payment, COUNT(order_id) AS count
			FROM registrations
			WHERE registration_id = ?
			GROUP BY payment
			ORDER BY count DESC');
		$sth->execute( array( $this->registration_id ) );

		$results = array();
		while($row = $sth->fetch(PDO::FETCH_NUM)) {
			$results[ $row[0] ] = $row[1];
		}

		return $results;
	}

	function get_registrations ()
	{
		global $dbh, $CONFIG;

		$sth = $dbh->prepare("SELECT
				order_id,
				DATE_ADD(time, INTERVAL ? MINUTE) as time,
				payment,
				p.user_id,
				p.firstname,
				p.lastname
			FROM registrations r
				LEFT JOIN person p ON r.user_id = p.user_id
			WHERE r.registration_id = ?
				ORDER BY payment, order_id
		");
		$sth->execute( array(-$CONFIG['localization']['tz_adjust'], $this->registration_id) );

		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	function get_registration_for( $user_id = null )
	{
		return registration_load(array(
			'user_id'         => $user_id,
			'registration_id' => $this->registration_id,
		));
	}

	function get_applicable_cap ( $user )
	{
		global $dbh;

		$where  = '';
		$params = array( $this->registration_id );
		// TODO FIXME magical values suck
		if ($this->event->cap_female == -2) {
			$applicable_cap = $this->cap_male;
		} else {
			if( $user->gender == 'Male' ) {
				$applicable_cap = $this->cap_male;
			} else {
				$applicable_cap = $this->cap_female;
			}
			$where = ' AND p.gender = ?';
			array_push( $params, $user->gender );
		}

		$sth = $dbh->prepare("SELECT COUNT(order_id)
			FROM registrations r
			LEFT JOIN person p ON r.user_id = p.user_id
			WHERE registration_id = ?
			AND (payment = 'Paid' OR payment = 'Pending')
			$where");
		$sth->execute( $params );
		$registered_count = $sth->fetchColumn();

		return array( $applicable_cap, $registered_count );
	}

}


function event_query ( $array = array() )
{
	global $dbh; 
	$query = array();
	$params = array();
	$fields = '';
	$order = '';
	foreach ($array as $key => $value) {
		switch( $key ) {
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_fields':
				$fields = ", $value";
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			default:
				$query[] = "e.$key = ?";
				$params[] = $value;
		}
	}

	$sth  = $dbh->prepare("SELECT
		e.*,
		1 as _in_database,
		UNIX_TIMESTAMP(e.open) as open_timestamp,
		UNIX_TIMESTAMP(e.close) as close_timestamp
		$fields
		FROM registration_events e
		WHERE " . implode(' AND ',$query) .  $order);

	$sth->execute( $params );

	return $sth;
}

/**
 * Wrapper for convenience and backwards-compatibility.
 */
function event_load( $array = array() )
{
	$sth = event_query( $array );
	return $sth->fetchObject('Event');
}

function event_types ()
{
	return array(
		'membership'        => 'Individual Membership',
		'individual_event'  => 'Individual Event Registration',
		'team_event'        => 'Team Event Registration',
		'individual_youth'  => 'Individual Youth and Junior League Registration',
		'individual_league' => 'Individual League Registration (for players without a team)',
		'team_league'       => 'Team League Registration'
	);
}
?>
