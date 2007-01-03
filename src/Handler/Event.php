<?php

/*
 * TODO: Currently, prerequisites and questions are handled with manual
 *       database manipulation.
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
		default:
			$obj = null;
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
			// Only players with completed profiles can view details
			return $lr_session->is_complete();
		case 'list':
			// Everyone can list
			return true;
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

		$output .= form_textfield('Cost', 'edit[cost]', $data['cost'], 10, 10, 'Cost of this event, may be 0, ' . theme_error('not including GST'));
		$output .= form_textfield('GST', 'edit[gst]', $data['gst'], 10, 10, 'GST');
		$output .= form_textfield('PST', 'edit[pst]', $data['pst'], 10, 10, 'PST');

		$output .= form_select_date('Open date', 'edit[open_date]', $data['open_date'], $thisYear, ($thisYear + 1), 'The date on which registration for this event will open.');

		$output .= form_select('Open time', 'edit[open_time]', $data['open_time'], getOptionsFromTimeRange(0000,2400,30), 'Time at which registration will open (00:00 for 12:01AM).');

		$output .= form_select_date('Close date', 'edit[close_date]', $data['close_date'], $thisYear, ($thisYear + 1), 'The date on which registration for this event will close.');

		$output .= form_select('Close time', 'edit[close_time]', $data['close_time'], getOptionsFromTimeRange(0000,2400,30), 'Time at which registration will close (24:00 for 11:59PM).');

		$output .= form_textfield('Male cap', 'edit[cap_male]', $data['cap_male'], 10, 10, '-1 for no limit');
		$output .= form_textfield('Female cap', 'edit[cap_female]', $data['cap_female'], 10, 10, '-1 for no limit, -2 to use male cap as combined limit');

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
		global $lr_session;
		$links = $lr_session->has_permission('event','view');

		$this->title = 'Registration Event List';
		$this->setLocation(array($this->title => 0));

		ob_start();
		$retval = @readfile('data/registration_notice.html');
		if (false !== $retval) {
			$output = ob_get_contents();
		}           
		ob_end_clean();

		if( $lr_session->is_admin() ) {
			$result = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 30 DAY) AND e.close > DATE_ADD(NOW(), INTERVAL -60 DAY)', '_order' => 'e.open,e.name') );
		} else {
			$result = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 30 DAY) AND e.close > NOW()', '_order' => 'e.open,e.name') );
		}

		$rows = array();

		while( $event = db_fetch_object( $result ) ) {
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

		if (!$links) {
			$output .= para(theme_error('You cannot register for any events unless you are logged on to the site and your Leaguerunner profile has been completed. If you are were not a 2006/07 member of TUC, you will have to contact the head office at (416)426-7175 to arrange for a site account to be created for you.'));
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
		global $lr_session;
		$this->title= 'View Event';

		$rows = array();
		$rows[] = array('Event&nbsp;Name:', $this->event->name);
		$rows[] = array('Description:', $this->event->description);
		$rows[] = array('Cost:', '$' . ($this->event->cost + $this->event->gst + $this->event->pst));
		$rows[] = array('Opens on:', $this->event->open);
		$rows[] = array('Closes on:', $this->event->close);
		if ($this->event->cap_female == -2)
		{
			$rows[] = array('Registration cap:', $this->event->cap_male);
		}
		else
		{
			if ($this->event->cap_male >= 0)
			{
				$rows[] = array('Male cap:', $this->event->cap_male);
			}
			if ($this->event->cap_female >= 0)
			{
				$rows[] = array('Female cap:', $this->event->cap_female);
			}
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		// list prerequisites of this event
		$result = db_query("SELECT
								p.prereq_id as id,
								p.is_prereq as pre,
								e.name as name
							FROM registration_prereq p
							LEFT JOIN registration_events e
								ON p.prereq_id = e.registration_id
							WHERE p.registration_id = %d ORDER BY e.name",
							$this->event->registration_id);

		while( $prereq = db_fetch_object( $result ) ) {
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

		$output .= para();

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
		global $lr_session;

		// Admins can test registration before it opens...
		if (! $lr_session->is_admin())
		{
			$currentTime = date ('Y-m-d H:i:s', time() + 3 * 60 * 60);

			if ($this->event->open_timestamp > time()) {
				return para('This event is not yet open for registration.');
			}
			else if (time() > $this->event->close_timestamp) {
				return para('Registration for this event has closed.');
			}
		}

		// Check if the user has already registered for this event
		$result = db_query('SELECT
								*
							FROM
								registrations
							WHERE
								user_id = %d
							AND
								registration_id = %d',
					$lr_session->user->user_id,
					$this->event->registration_id);
		$row = db_fetch_object( $result );
		if ($row)
		{
			if ($row->paid) {
				$output = para('You have already registered and paid for this event.');
			}
			else {
				// We may need to generate a new order id, to keep the payment
				// from failing due to duplicates.  To do this, we must delete
				// the old record and create a new one.
				if( variable_get( 'online_payments', 1 ) )
				{
					db_query('DELETE FROM
								registrations
							WHERE
								order_id = %d',
							$row->order_id);

					$reg = new Registration;
					$reg->set('user_id', $lr_session->user->user_id);
					$reg->set('registration_id', $this->event->registration_id);

					if (! $reg->save() ) {
						error_exit('Could not create new registration record.');
					}
				}

				$order_num = sprintf(variable_get('order_id_format', '%d'), $reg->order_id);

				$output = para('You have already registered for this event, but not yet paid.');
				$output .= para('If you registered in error, or have changed your mind about participating, or want to change your previously selected preferences, you can ' . l('unregister', 'registration/unregister/' . $reg->order_id . '/' . $this->event->registration_id) . '.');

				$output .= h2('Payment');
				$output .= generatePayForm($this->event, $order_num);

				$output .= OfflinePaymentText($order_num);
				$output .= RefundPolicyText();
			}

			return $output;
		}

		// Check if this event is already full
		if ($this->event->cap_female == -2)
		{
			$applicable_cap = $this->event->cap_male;
			$where = '';
		}
		else
		{
			$applicable_cap = ( $lr_session->user->gender == 'Male' ?
									$this->event->cap_male :
									$this->event->cap_female );
			$where = " AND p.gender = '" . $lr_session->user->gender . "'";
		}

		// 0 means that nobody of this gender is allowed
		if ( $applicable_cap == 0 )
		{
			return para( 'This event is for the opposite gender only.' );
		}

		// -1 means there is no cap, so don't even check the database
		else if ( $applicable_cap > 0 )
		{
			$registered_count = db_result( db_query(
									'SELECT
										COUNT(order_id)
									FROM
										registrations r
									LEFT JOIN
										person p
									ON
										r.user_id = p.user_id
									WHERE
										registration_id = %d' . $where,
									$this->event->registration_id ) );

			if ( $registered_count >= $applicable_cap )
			{
				return para( 'This event is already full.  You may email <a href="mailto:gm@tuc.org">gm@tuc.org</a> or phone the head office to be put on a waiting list in case others drop out.' );
			}
		}

		// Check if the user has already registered for an antirequisite event
		$result = $this->query_prereqs(0);
		while( $prereq = db_fetch_object( $result ) ) {
			$output = para("You may not register for this because you have previously registered for $prereq->name.", array('class' => 'closed'));
			return $output;
		}

		// Check if this event has any prerequisites to satisfy
		$prereq_count = db_result( db_query(
							'SELECT
								COUNT(prereq_id)
							FROM
								registration_prereq
							WHERE
								registration_id = %d
							AND
								is_prereq = 1',
							$this->event->registration_id ) );
		if (! $prereq_count )
		{
			$output = para('You may register for this because there are no prerequisites.', array('class' => 'open'));
		}
		else
		{
			// Check if the user has registered for any prerequisite event
			$result = $this->query_prereqs(1);
			while( $prereq = db_fetch_object( $result ) ) {
				$output = para("You may register for this because you have previously registered for $prereq->name.", array('class' => 'open'));
			}
		}

		if ($output)
		{
			$output .= para(l('Register now!', 'registration/register/' . $this->event->registration_id, array('title' => 'Register for ' . $this->event->name)));
			return $output;
		}

		// If we get here, they are missing something...
		return para('You may not register for this event until you have registered for one of the prerequisites listed above.', array('class' => 'closed'));
	}

	function query_prereqs($is_prereq)
	{
		global $lr_session;

		return db_query("SELECT time, paid, name
								FROM registrations r
									LEFT JOIN registration_events e
									ON r.registration_id = e.registration_id
								WHERE user_id = %d
								AND r.registration_id
								IN (
									SELECT prereq_id
									FROM registration_prereq
									WHERE registration_id = %d
									AND is_prereq = %d
									)",
			$lr_session->user->user_id,
			$this->event->registration_id,
			$is_prereq);
	}
}

?>
