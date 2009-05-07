<?php

/*
 * TODO: Currently, prerequisites are handled with manual database manipulation.
 */

/*
 * Handlers for dealing with registration events
 */
function event_dispatch() 
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'create':
			$obj = new EventCreate;
			break;
		case 'edit':
			$obj = new EventEdit;
			$obj->event = event_load( array('registration_id' => $id) );
			break;
		case 'survey':
			$obj = new EventSurvey;
			$obj->event = event_load( array('registration_id' => $id) );
			break;
		case 'prereq':
			$obj = new EventPrereq;
			$obj->event = event_load( array('registration_id' => $id) );
			break;
		case 'view':
			$obj = new EventView;
			$obj->event = event_load( array('registration_id' => $id,
											'_fields' => 'NOW() as now') );
			break;
		case 'list':
			return new EventList;
			break;
		case 'preregister':
			$obj = new EventPreregister;
			$obj->event = event_load( array('registration_id' => $id) );
			$player_id = arg(3);
			if( $player_id ) {
				$obj->player = person_load( array('user_id' => $player_id) );
			}
			$obj->op = arg(4);
			break;
		case 'preregisterlist':
			return new EventPreregisterList;
			break;
		default:
			$obj = null;
	}

	if( $obj ) {
		$obj->event_types = array(
			'membership' => 'Individual membership',
			'individual_league' => 'Individual for league',
			'team_league' => 'Team for league',
			'individual_event' => 'Individual for tournament or other one-time event',
			'team_event' => 'Team for tournament or other one-time event'
		);
	}

	if( $obj->event ) {
		event_add_to_menu( $obj->event );
	}
	return $obj;
}

function event_permissions ( &$user, $action, $id, $data_field )
{
	global $lr_session;

	switch( $action )
	{
		case 'create':
			// Only admin can create
			break;
		case 'edit':
			// Only admin can edit
			break;
		case 'view':
			// Only players with completed and activated profiles can view details
			return ( $lr_session->is_complete() && $lr_session->user->is_active() );
		case 'list':
			// Everyone can list
			return true;
		case 'preregister':
		case 'preregisterlist':
			// Only admin can preregister individuals
			break;
	}

	return false;
}

function event_menu()
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		if( $lr_session->has_permission('event','list') ) {
			menu_add_child('_root','event','Registration');
			menu_add_child('event','event/list','list events', array('link' => 'event/list') );
		}

		if( $lr_session->has_permission('event','create') ) {
			menu_add_child('event','event/create','create event', array('weight' => 5, 'link' => 'event/create') );
		}
		if( $lr_session->has_permission('event','preregisterlist') ) {
			menu_add_child('event','event/preregisterlist','list preregistrations', array('link' => 'event/preregisterlist') );
		}
	}
}

/**
 * Add view/edit/delete links to the menu for the given event
 */
function event_add_to_menu( &$event ) 
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		menu_add_child('event', $event->name, $event->name, array('weight' => -10, 'link' => "event/view/$event->registration_id"));

		if($lr_session->has_permission('event','edit', $event->registration_id) ) {
			menu_add_child($event->name, "$event->name/edit",'edit event', array('weight' => 1, 'link' => "event/edit/$event->registration_id"));
			menu_add_child($event->name, "$event->name/survey",'edit survey', array('weight' => 1, 'link' => "event/survey/$event->registration_id"));
		} 
		if($lr_session->has_permission('event','preregister', $event->registration_id) ) {
			menu_add_child($event->name, "$event->name/preregister",'create preregistration', array('weight' => 2, 'link' => "event/preregister/$event->registration_id"));
		} 

		if( $lr_session->is_admin() ) {
			menu_add_child($event->name, "$event->name/registrations", 'registration summary', array('weight' => 2, 'link' => "statistics/registration/summary/$event->registration_id"));
		}
	}
}

class EventCreate extends EventEdit
{
	var $event;

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','create');
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = 'Create Event';

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->event = new Event;
				$this->perform($this->event, $edit);
				local_redirect(url("event/view/" . $this->event->registration_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

class EventSurvey extends Handler
{
	var $event;

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','edit');
	}

	function process ()
	{
		$this->title = 'Maintain Event Survey';
		$formkey = 'registration_' . $this->event->registration_id;
		$rc = formbuilder_maintain( $formkey );
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

class EventEdit extends Handler
{
	var $event;

	function has_permission()
	{
		global $lr_session;
		if (!$this->event) {
			error_exit('That event does not exist');
		}
		return $lr_session->has_permission('event','edit', $this->event->registration_id);
	}

	function process ()
	{
		$this->title = 'Edit Event';
		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->perform($this->event, $edit);
				local_redirect(url("event/view/" . $this->event->registration_id));
				break;
			default:
				$edit = object2array($this->event);
				$rc = $this->generateForm( $edit );
		}

		return $rc;
	}

	function generateForm( $data = array() )
	{
		$thisYear = strftime('%Y', time());

		$output = form_hidden('edit[step]', 'confirm');

		$output .= form_textfield('Name', 'edit[name]', $data['name'], 35, 100, 'Full name of this registration event.');

		$output .= form_textarea('Description', 'edit[description]', $data['description'], 60, 5, 'Complete description of the event, HTML is allowed.');

		$output .= form_radios('Event type', 'edit[type]', $data['type'], $this->event_types, 'Note that any team type will result in a team record being created. If you don\'t want this, then use the appropriate individual type.' );

		$output .= form_textfield('Cost', 'edit[cost]', $data['cost'], 10, 10, 'Cost of this event, may be 0, ' . theme_error('not including GST'));
		$output .= form_textfield('GST', 'edit[gst]', $data['gst'], 10, 10, 'GST');
		$output .= form_textfield('PST', 'edit[pst]', $data['pst'], 10, 10, 'PST');

		$output .= form_select_date('Open date', 'edit[open_date]', $data['open_date'], ($thisYear - 1), ($thisYear + 1), 'The date on which registration for this event will open.');

		$output .= form_select('Open time', 'edit[open_time]', $data['open_time'], getOptionsFromTimeRange(0000,2400,30), 'Time at which registration will open (00:00 for 12:01AM).');

		$output .= form_select_date('Close date', 'edit[close_date]', $data['close_date'], ($thisYear - 1), ($thisYear + 1), 'The date on which registration for this event will close.');

		$output .= form_select('Close time', 'edit[close_time]', $data['close_time'], getOptionsFromTimeRange(0000,2400,30), 'Time at which registration will close (24:00 for 11:59PM).');

		$output .= form_textfield('Male cap', 'edit[cap_male]', $data['cap_male'], 10, 10, '-1 for no limit');
		$output .= form_textfield('Female cap', 'edit[cap_female]', $data['cap_female'], 10, 10, '-1 for no limit, -2 to use male cap as combined limit');

		$output .= form_radios('Allow multiple registrations', 'edit[multiple]', $data['multiple'], array('No', 'Yes'), 'Can a single user register for this event multiple times?');

		$output .= form_radios('Anonymous statistics', 'edit[anonymous]', $data['anonymous'], array('No', 'Yes'), 'Will results from this event\'s survey be kept anonymous?');

		$output .= form_submit('Submit') .  form_reset('Reset');

		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again');
		}

		$output = form_hidden('edit[step]', 'perform');

		$rows = array();
		$rows[] = array( 'Name:', form_hidden('edit[name]', $edit['name']) . check_form($edit['name']) );
		$rows[] = array( 'Description:', form_hidden('edit[description]', $edit['description']) . check_form($edit['description']) );
		$rows[] = array( 'Event type:', form_hidden('edit[type]', $edit['type']) . $this->event_types[$edit['type']] );
		$rows[] = array( 'Cost:', form_hidden('edit[cost]', $edit['cost']) . '$' . check_form($edit['cost']) );
		$rows[] = array( 'GST:', form_hidden('edit[gst]', $edit['gst']) . '$' . check_form($edit['gst']) );
		$rows[] = array( 'PST:', form_hidden('edit[pst]', $edit['pst']) . '$' . check_form($edit['pst']) );
		$rows[] = array( 'Open:',
			form_hidden('edit[open_date][year]',$edit['open_date']['year'])
			. form_hidden('edit[open_date][month]',$edit['open_date']['month'])
			. form_hidden('edit[open_date][day]',$edit['open_date']['day'])
			. form_hidden('edit[open_time]',$edit['open_time'])
			. $edit['open_date']['year'] . '/' . $edit['open_date']['month'] . '/' . $edit['open_date']['day'] . ' ' . $edit['open_time']);
		$rows[] = array( 'Close:',
			form_hidden('edit[close_date][year]',$edit['close_date']['year'])
			. form_hidden('edit[close_date][month]',$edit['close_date']['month'])
			. form_hidden('edit[close_date][day]',$edit['close_date']['day'])
			. form_hidden('edit[close_time]',$edit['close_time'])
			. $edit['close_date']['year'] . '/' . $edit['close_date']['month'] . '/' . $edit['close_date']['day'] . ' ' . $edit['close_time']);
		$rows[] = array( 'Male cap:', form_hidden('edit[cap_male]', $edit['cap_male']) . check_form($edit['cap_male']) );
		$rows[] = array( 'Female cap:', form_hidden('edit[cap_female]', $edit['cap_female']) . check_form($edit['cap_female']) );
		$rows[] = array( 'Multiples:', form_hidden('edit[multiple]', $edit['multiple']) . ($edit['multiple'] ? 'Allowed' : 'Not allowed') );
		$rows[] = array( 'Anonymous:', form_hidden('edit[anonymous]', $edit['anonymous']) . ($edit['anonymous'] ? 'Yes' : 'No') );

		$rows[] = array( form_submit('Submit'), '');

		$output .= "<div class='pairtable'>" . table(null, $rows) . '</div>';

		return form($output);
	}

	function perform ( &$event, &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again');
		}

		$event->set('name', $edit['name']);
		$event->set('description', $edit['description']);
		$event->set('type', $edit['type']);
		$event->set('cost', $edit['cost']);
		$event->set('gst', $edit['gst']);
		$event->set('pst', $edit['pst']);

		$time = $edit['open_time'];
		if ($time == '24:00') { $time = '23:59:00'; }
		$event->set('open', join('-',array(
								$edit['open_date']['year'],
								$edit['open_date']['month'],
								$edit['open_date']['day']))
							. ' ' . $time);

		$time = $edit['close_time'];
		if ($time == '24:00') { $time = '23:59:00'; }
		$event->set('close', join('-',array(
								$edit['close_date']['year'],
								$edit['close_date']['month'],
								$edit['close_date']['day']))
							. ' ' . $time);

		$event->set('cap_male', $edit['cap_male']);
		$event->set('cap_female', $edit['cap_female']);

		$event->set('multiple', $edit['multiple']);
		$event->set('anonymous', $edit['anonymous']);

		if( !$event->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		if( !validate_nonhtml($edit['name'] ) ) {
			$errors .= '<li>Name cannot be left blank, and cannot contain HTML';
		}

		if( !validate_number($edit['cost'] ) ) {
			$errors .= '<li>Invalid cost: not a number';
		}
		else if( $edit['cost'] < 0 ) {
			$errors .= '<li>Invalid cost: cannot be negative';
		}

		if( !validate_number($edit['gst'] ) ) {
			$errors .= '<li>Invalid GST: not a number';
		}
		else if( $edit['gst'] < 0 ) {
			$errors .= '<li>Invalid GST: cannot be negative';
		}

		if( !validate_number($edit['pst'] ) ) {
			$errors .= '<li>Invalid PST: not a number';
		}
		else if( $edit['pst'] < 0 ) {
			$errors .= '<li>Invalid PST: cannot be negative';
		}

		if( !validate_date_input($edit['open_date']['year'], $edit['open_date']['month'], $edit['open_date']['day']) ) {
			$errors .= '<li>You must provide a valid open date';
		}

		if( !validate_date_input($edit['close_date']['year'], $edit['close_date']['month'], $edit['close_date']['day']) ) {
			$errors .= '<li>You must provide a valid close date';
		}

		// The calculated values won't be accurate Julian dates, but they're
		// good enough for comparison purposes.
		$open = $edit['open_date']['year'] * 366 +
				$edit['open_date']['month'] * 31 +
				$edit['open_date']['day'];
		$close = $edit['close_date']['year'] * 366 +
				$edit['close_date']['month'] * 31 +
				$edit['close_date']['day'];
		if ( $open >= $close ) {
			$errors .= '<li>Open date/time must be before close date/time';
		}

		if( !validate_number($edit['cap_male'] ) ) {
			$errors .= '<li>Invalid male cap: not a number';
		}
		else if( $edit['cap_male'] < -1 ) {
			$errors .= '<li>Invalid male cap: cannot be less than -1';
		}

		if( !validate_number($edit['cap_female'] ) ) {
			$errors .= '<li>Invalid female cap: not a number';
		}
		else if( $edit['cap_female'] < -2 ) {
			$errors .= '<li>Invalid female cap: cannot be less than -2';
		}
		else if( $edit['cap_female'] == -2 && $edit['cap_male'] <= 0 ) {
			$errors .= '<li>Invalid female cap: can only be -2 if male cap is > 0';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

/**
 * Event listing handler
 */
class EventList extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','list');
	}

	function process ()
	{
		global $lr_session, $CONFIG;

		$links = $lr_session->has_permission('event','view');

		$this->title = 'Registration Event List';
		$this->setLocation(array($this->title => 0));

		$output = '';
		ob_start();
		$retval = @readfile(trim ($CONFIG['paths']['file_url'], '/') . "/data/registration_notice.html");
		if (false !== $retval) {
			$output = ob_get_contents();
		}
		ob_end_clean();

		if( $lr_session->is_admin() ) {
			$sth = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 30 DAY) AND e.close > DATE_ADD(NOW(), INTERVAL -30 DAY)', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		} else {
			$sth = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 30 DAY) AND e.close > NOW()', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		}

		$type_desc = array('membership' => 'Membership Registrations',
							'individual_event' => 'One-time Individual Event Registrations',
							'team_event' => 'One-time Team Event Registrations',
							'individual_league' => 'Individual Registrations (for players without a team)',
							'team_league' => 'Team Registrations');
		$last_type = '';
		$rows = array();

		while( $event = $sth->fetch(PDO::FETCH_OBJ) ) {
			if ($event->type != $last_type) {
				$rows[] = array( array('colspan' => 4, 'data' => h2($type_desc[$event->type])));
				$last_type = $event->type;
			}

			if ($links) {
				$name = l($event->name, "event/view/$event->registration_id", array('title' => 'View event details'));
			}
			else {
				$name = $event->name;
			}
			$rows[] = array($name,
							'$' . ($event->cost + $event->gst + $event->pst),
							$event->open,
							$event->close);
		}

		$header = array( 'Registration', 'Cost', 'Opens on', 'Closes on');
		$output .= table ($header, $rows, array('alternate-colours' => true));

		return $output;
	}
}

/**
 * Event preregistration handler.  Admins can create a preregistration that
 * will allow a user to register for an event even if the event is not open.
 * This can be used for allowing early registrations, or late (e.g. people
 * from a waiting list, when others who were registered drop out).
 */
class EventPreregister extends Handler
{
	var $event;

	function has_permission()
	{
		global $lr_session;
		if (!$this->event) {
			error_exit('That event does not exist');
		}
		return $lr_session->has_permission('event','preregister', $this->event->registration_id);
	}

	function process ()
	{
		global $lr_session, $dbh;
		$this->title = 'Create Preregistration';

		if( !$this->player ) {
			$new_handler = new PersonSearch;
			$new_handler->initialize();
			$new_handler->ops['Preregister for ' . $this->event->name] = 'event/preregister/' . $this->event->registration_id . '/%d/add';
			$output = $new_handler->process();
		}

		else if ($this->op == 'add')
		{
			// TODO: need a preregistration class to handle create/delete
			$sth = $dbh->prepare('INSERT INTO preregistrations VALUES (?,?)');
			$sth->execute( array(
				$this->player->user_id,
				$this->event->registration_id)
			);
			if( 1 != $sth->rowCount() ) {
				return false;
			}
			$output = para('Successfully created preregistration. ' . $this->player->fullname . ' is now permitted to proceed with registration for ' . $this->event->name . ', subject to the normal prerequisite checks.');
		}

		else if ($this->op == 'delete')
		{
			$this->title = 'Delete Preregistration';
			$sth = $dbh->prepare('DELETE FROM preregistrations WHERE user_id = ?  AND registration_id = ?');
			$sth->execute( array(
				$this->player->user_id,
				$this->event->registration_id)
			);
			if( 1 != $sth->rowCount() ) {
				return false;
			}
			$output = para('Successfully removed preregistration record.');
		}
		else
		{
			$output = para(theme_error('Error: unrecognized preregistration operation!'));
		}

		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));

		return $output;
	}
}

/**
 * Event preregistration listing handler
 */
class EventPreregisterList extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','preregisterlist');
	}

	function process ()
	{
		global $lr_session, $dbh;

		$this->title = 'Preregistration List';
		$this->setLocation(array($this->title => 0));

		$sth = $dbh->prepare('SELECT
				r.user_id,
				r.registration_id,
				p.firstname,
				p.lastname,
				e.name as eventname
			FROM
				preregistrations r
			LEFT JOIN
				person p ON p.user_id = r.user_id
			LEFT JOIN
				registration_events e ON e.registration_id = r.registration_id');
		$sth->execute();

		$rows = array();

		while( $row = $sth->fetch(PDO::FETCH_OBJ) ) {
			$rows[] = array(
				l($row->firstname . ' ' . $row->lastname, "person/view/$row->user_id"),
				l($row->eventname, "event/view/$row->registration_id"),
				l('delete', "event/preregister/$row->registration_id/$row->user_id/delete")
			);
		}

		$output = '';
		if (count ($rows))
		{
			$header = array( 'User', 'Event');
			$output .= table ($header, $rows, array('alternate-colours' => true));
		}
		else
		{
			$output .= para ('No preregistrations found.');
		}

		return $output;
	}
}

/**
 * Event viewing handler
 */
class EventView extends Handler
{
	var $event;

	function has_permission()
	{
		global $lr_session;
		if (!$this->event) {
			error_exit('That event does not exist');
		}
		return $lr_session->has_permission('event','view', $this->event->registration_id);
	}

	function process ()
	{
		global $dbh;
		$this->title= 'View Event';

		$rows = array();
		$rows[] = array('Event&nbsp;Name:', $this->event->name);
		$rows[] = array('Description:', $this->event->description);
		$rows[] = array('Event type:', $this->event_types[$this->event->type]);
		$rows[] = array('Cost:', '$' . ($this->event->cost + $this->event->gst + $this->event->pst));
		$rows[] = array('Opens on:', $this->event->open);
		$rows[] = array('Closes on:', $this->event->close);
		if ($this->event->cap_female == -2)
		{
			$rows[] = array('Registration cap:', $this->event->cap_male);
		}
		else
		{
			if ($this->event->cap_male > 0)
			{
				$rows[] = array('Male cap:', $this->event->cap_male);
			}
			if ($this->event->cap_female > 0)
			{
				$rows[] = array('Female cap:', $this->event->cap_female);
			}
		}
		$rows[] = array('Multiples:', $this->event->multiple ? 'Allowed' : 'Not allowed');
		if( $this->event->anonymous ) {
			$rows[] = array('Survey:', 'Results of this event\'s survey will be anonymous.');
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		// list prerequisites of this event
		$sth = $dbh->prepare('SELECT
				p.prereq_id as id,
				p.is_prereq as pre,
				e.name as name
			FROM registration_prereq p
			LEFT JOIN registration_events e ON p.prereq_id = e.registration_id
			WHERE p.registration_id = ? ORDER BY e.name');
		$sth->execute( array( $this->event->registration_id ));

		while( $prereq = $sth->fetch(PDO::FETCH_OBJ) ) {
			if ($prereq->pre) {
				$preRows .= li(l($prereq->name, "event/view/$prereq->id",
								array('title' => 'View event details')));
			}
			else {
				$antiRows .= li(l($prereq->name, "event/view/$prereq->id",
								array('title' => 'View event details')));
			}
		}

		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));

		$output .= para('');

		if ($preRows) {
			$output .= h2('Prerequisites') . ul($preRows);
		}
		if ($antiRows) {
			$output .= h2('Antirequisites') . ul($antiRows);
		}

		$output .= $this->check_prereq();

		return $output;
	}

	function check_prereq()
	{
		global $lr_session, $dbh;
		$output = $payment = '';
		$can_register = false;

		// Make sure the user is allowed to register for anything!
		if( ! $lr_session->user->is_active() ) {
			return para('You may not register for an event until your account is activated');
		}
		if( ! $lr_session->user->is_player() ) {
			return para('Your account is marked as a non-player account. Only players are allowed to register. Please ' . l('edit your account', "person/edit/{$lr_session->user->user_id}") . ' to enable this.');
		}

		$where  = '';
		$params = array( $this->event->registration_id );
		// We need these numbers in a couple of places below
		if ($this->event->cap_female == -2)
		{
			$applicable_cap = $this->event->cap_male;
		}
		else
		{
			$applicable_cap = ( $lr_session->user->gender == 'Male' ?
									$this->event->cap_male :
									$this->event->cap_female );
			$where = ' AND p.gender = ?';
			array_push( $params, $lr_session->user->gender );
		}
		$sth = $dbh->prepare("SELECT COUNT(order_id)
			FROM registrations r
			LEFT JOIN person p ON r.user_id = p.user_id
			WHERE registration_id = ?
			AND (payment = 'Paid' OR payment = 'Pending')
			$where");
		$sth->execute( $params );
		$registered_count = $sth->fetchColumn();

		// TODO: why are we not using the registrations class?
		// Check if the user has already registered for this event
		$sth = $dbh->prepare("SELECT *
				FROM registrations
				WHERE user_id = ?
				AND registration_id = ?
				AND payment != 'Refunded'
				ORDER BY payment");
		$sth->execute( array( 
			$lr_session->user->user_id,
			$this->event->registration_id)
		);
		$row = $sth->fetch(PDO::FETCH_OBJ);
		if ($row)
		{
			// If there's an unpaid registration, we may want to allow the
			// option to pay it.  However, the option may be displayed after
			// other text, so we'll build it here and save it for later.
			if( is_unpaid ($row) )
			{
				$payment = para('You have already registered for this event, but not yet paid.');
				$payment .= para('If you registered in error, or have changed your mind about participating, or want to change your previously selected preferences, you can ' . l('unregister', 'registration/unregister/' . $row->order_id) . '.');

				// An unpaid registration might have been pre-empted by someone
				// who paid.
				if ( $row->payment == 'Unpaid' &&
					$applicable_cap > 0 && $registered_count >= $applicable_cap )
				{
					$payment .= para('Your payment was not received in time, so your registration has been moved to a waiting list. If you have any questions about this, please contact the head office.');
				}
				else
				{
					$reg = registration_load( array('order_id' => $row->order_id ) );
					$order_num = sprintf(variable_get('order_id_format', '%d'), $reg->order_id);

					$payment .= h2('Payment');
					if( variable_get( 'online_payments', 1 ) ) {
						$payment .= generatePayForm($this->event, $order_num);
					}

					$payment .= OfflinePaymentText($order_num);
					$payment .= RefundPolicyText();
				}
			}

			// If the record is considered paid, and we allow multiple
			// registrations, show that.
			if( is_paid ($row) && $this->event->multiple )
			{
				$output = para('You have already registered for this event. However, this event allows multiple registrations (e.g. the same person can register teams to play on different nights).');
			}

			// Multiples are not allowed.  If the registration is actually paid
			// for (not pending payment), just exit now, with no extra
			// description required.
			else if( $row->payment == 'Paid' ) {
				return para('You have already registered and paid for this event.');
			}

			// Only way to get here is if multiple registrations are not
			// allowed, and a registration exists with a pending payment.  Let
			// the user make the payment, and nothing else.
			else {
				return $payment;
			}
		}

		// If there is a preregistration record, we ignore open and close times
		$sth = $dbh->prepare('SELECT COUNT(*)
			FROM preregistrations
			WHERE user_id = ?
			AND registration_id = ?');
		$sth->execute( array(
			$lr_session->user->user_id,
			$this->event->registration_id
		));
		$prereg = $sth->fetchColumn();
		if ($prereg == 0)
		{
			$currentTime = date ('Y-m-d H:i:s', time());

			// Admins can test registration before it opens...
			if (! $lr_session->is_admin())
			{
				if ($this->event->open_timestamp > time()) {
					// Preregistrations might allow that there is a payment-
					// pending registration already done, so we allow for
					// payment to be made anyway.
					return para('This event is not yet open for registration.') . $payment;
				}
			}
			if (time() > $this->event->close_timestamp) {
				// There may be a payment-pending registration already done,
				// so we allow for payment to be made.
				return para('Registration for this event has closed.') . $payment;
			}
		}

		// 0 means that nobody of this gender is allowed
		if ( $applicable_cap == 0 )
		{
			// No way for a payment-pending registration to have been done.
			return para( 'This event is for the opposite gender only.' );
		}

		// -1 means there is no cap, so don't even check the database
		else if ( $applicable_cap > 0 )
		{
			// Check if this event is already full
			if ( $registered_count >= $applicable_cap )
			{
				// TODO: Allow people to put themselves on a waiting list
				$admin_name = variable_get('app_admin_name', 'Leaguerunner Admin');
				$admin_addr = variable_get('app_admin_email','webmaster@localhost');
				$output .= para( "This event is already full.  You may email the <a href=\"mailto:$admin_addr\">$admin_name</a> or phone the head office to be put on a waiting list in case others drop out." );
				// There may be a payment-pending registration already done,
				// if multiples are allowed, so we allow for payment to be made.
				return $output . $payment;
			}
		}

		// Check if the user has already registered for an antirequisite event
		$sth = $this->query_prereqs(0);
		while( $prereq = $sth->fetch(PDO::FETCH_OBJ) ) {
			$output .= para("You may not register for this because you have previously registered for $prereq->name.", array('class' => 'closed'));
			// No way for a payment-pending registration to have been done.
			return $output;
		}

		// Check if this event has any prerequisites to satisfy
		$sth = $dbh->prepare('SELECT COUNT(prereq_id)
			FROM registration_prereq
			WHERE registration_id = ?
			AND is_prereq = 1');
		$sth->execute( array( $this->event->registration_id ) );
		$prereq_count = $sth->fetchColumn();
		if (! $prereq_count )
		{
			$output .= para("You may " . l('register for this', 'registration/register/' . $this->event->registration_id, array('title' => 'Register for ' . $this->event->name)) . " because there are no prerequisites.", array('class' => 'open'));
			$can_register = true;
		}
		else
		{
			// Check if the user has registered for any prerequisite event
			$sth = $this->query_prereqs(1);
			while( $prereq = $sth->fetch(PDO::FETCH_OBJ) ) {
				if( is_paid ($prereq) )
				{
					$output .= para("You may " . l('register for this', 'registration/register/' . $this->event->registration_id, array('title' => 'Register for ' . $this->event->name)) . " because you have previously registered for $prereq->name.", array('class' => 'open'));
					$can_register = true;
				}
			}
		}

		if ($can_register)
		{
			$output .= h2(l('Register now!', 'registration/register/' . $this->event->registration_id, array('title' => 'Register for ' . $this->event->name, 'style' => 'text-decoration: underline;')));
			// There may be a payment-pending registration already done,
			// if multiples are allowed, so we allow for payment to be made.
			return $output . $payment;
		}

		// If we get here, they are missing something...
		// No way for a payment-pending registration to have been done.
		return para('You may not register for this event until you have registered and paid for one of the prerequisites listed above.', array('class' => 'closed'));
	}

	function query_prereqs($is_prereq)
	{
		global $lr_session, $dbh;

		$sth = $dbh->prepare('SELECT time, payment, name
			FROM registrations r
			LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE user_id = ?
			AND r.registration_id IN (
				SELECT prereq_id
				FROM registration_prereq
				WHERE registration_id = ?
				AND is_prereq = ?
			)
			AND payment != ?');
		$sth->execute(array(
			$lr_session->user->user_id,
			$this->event->registration_id,
			$is_prereq,
			'Refunded'
		));

		return $sth;
	}
}

// These functions tell whether a registration record is considered to be paid,
// and whether it can still be paid.  They are not necessarily exclusive; if
// tentative registrations are allowed, then a record can be considered both
// paid (for the purposes of allowing further registrations) but also unpaid
// (for the purposes of deciding whether to allow the user to pay for it).
function is_paid ($record)
{
	return( $record->payment == 'Paid' || 
		( variable_get('allow_tentative', 0) && $record->payment == 'Pending' ) );
}

function is_unpaid ($record)
{
	return( $record->payment == 'Unpaid' || $record->payment == 'Pending' );
}

?>
