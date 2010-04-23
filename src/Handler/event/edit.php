<?php
require_once('Handler/EventHandler.php');
class event_edit extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
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

		$output .= form_radios('Event type', 'edit[type]', $data['type'], $this->event_types, 'Team registrations will prompt registrant to choose an existing team, or create a new team before completing registration' );

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

?>
