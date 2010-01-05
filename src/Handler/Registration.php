<?php

/*
 * Handlers for dealing with registrations
 */
function registration_dispatch()
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'view':
			$obj = new RegistrationView;
			$obj->registration = registration_load( array('order_id' => $id ) );
			break;
		case 'edit':
			$obj = new RegistrationEdit;
			$obj->registration = registration_load( array('order_id' => $id) );
			$obj->event = event_load( array('registration_id' => $obj->registration->registration_id) );
			$obj->registration_form_load($obj->registration->registration_id, true);
			break;
		case 'history':
			$obj = new RegistrationHistory;
			$obj->user = $id;
			break;
		case 'download':
			$obj = new RegistrationDownload;
			break;
		case 'register':
			$obj = new RegistrationRegister;
			$obj->event = event_load( array('registration_id' => $id) );
			$obj->registration_form_load($id, true);
			break;
		case 'unregister':
			$obj = new RegistrationUnregister;
			$obj->registration = registration_load( array('order_id' => $id ) );
			break;
		case 'online':
			$obj = new RegistrationOnlinePaymentResponse;
			break;
		default:
			$obj = null;
	}
	if( $obj->registration ) {
		registration_add_to_menu( $obj->registration );
	}
	return $obj;
}

function registration_permissions ( &$user, $action, $id, $data_field )
{
	global $lr_session;

	if (!$lr_session || !$lr_session->user)
		return false;

	switch( $action )
	{
		case 'view':
		case 'edit':
			// Only admin can view details or edit
			break;
		case 'register':
			// Only players with completed profiles can register
			return ($lr_session->user->is_active() && $lr_session->is_complete());
		case 'unregister':
			// Players may only unregister themselves from events before paying.
			// TODO: should be $registration->user_can_unregister()
			if($lr_session->user->is_active() && $lr_session->is_complete() && $data_field->user_id == $lr_session->user->user_id) {
				if($registration->payment != 'Unpaid' || $registration->payment != 'Pending') {
					// Don't allow user to unregister from paid events themselves -- admin must do it
					return 0;
				}
				return 1;
			}
			return 0;

		case 'history':
			// Players with completed profiles can view their own history
			if ($id) {
				return ($lr_session->is_complete() && $lr_session->user->user_id == $id);
			}
			else {
				return ($lr_session->is_complete());
			}
		case 'statistics':
		case 'download':
			// admin only
			break;
	}

	return false;
}

function registration_menu()
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		if( $lr_session->has_permission('registration','history') ) {
			menu_add_child('event', 'registration/history/'.$lr_session->user->user_id, 'view history', array('link' => 'registration/history/' . $lr_session->user->user_id) );
		}

		if( $lr_session->is_admin() ) {
			menu_add_child('settings', 'settings/registration', 'registration settings', array('link' => 'settings/registration'));
			menu_add_child('statistics','statistics/registration','registration statistics', array('link' => 'statistics/registration') );
			menu_add_child('event','registration/registrations','download registrations', array('link' => 'registration/download') );
			menu_add_child('event','registration/unpaid','unpaid registrations', array('link' => 'statistics/registration/unpaid') );
		}
	}
}

/**
 * Add view/edit links to the menu for the given registration
 */
function registration_add_to_menu( &$registration )
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		$order_num = sprintf(variable_get('order_id_format', '%d'), $registration->order_id);

		menu_add_child('event', $order_num, $order_num, array('weight' => -10, 'link' => "registration/view/$registration->order_id"));

		if($lr_session->has_permission('registration','edit', $registration->order_id) ) {
			menu_add_child($order_num, "$order_num/edit",'edit registration', array('weight' => 1, 'link' => "registration/edit/$registration->order_id"));
		}
		if($registration->payment == 'Unpaid' || $registration->payment == 'Pending') {
			if ($lr_session->has_permission('registration','unregister', null, $registration) ) {
				menu_add_child($order_num, "$order_num/unregister",'unregister', array('weight' => 1, 'link' => "registration/unregister/$registration->order_id"));
			}
		}
	}
}

/**
 * Base class for registration form functionality
 */
class RegistrationForm extends Handler
{
	function registration_form_load ($id, $add_extra)
	{
		$this->formkey = 'registration_' . $id;
		$this->formbuilder = new FormBuilder;
		$this->formbuilder->load($this->formkey);

		if( $add_extra && isset($this->event) ) {
			AddAutoQuestions( $this->formbuilder, $this->event->type );
		}

		// Other code relies on the formbuilder variable not being set if there
		// are no questions.
		if( ! count( $this->formbuilder->_questions ) ) {
			unset( $this->formbuilder );
		}
	}
}

/**
 * Registration viewing handler
 */
class RegistrationView extends Handler
{
	var $registration;

	function has_permission()
	{
		global $lr_session;
		if (!$this->registration) {
			error_exit('That registration does not exist');
		}
		return $lr_session->has_permission('registration','view', $this->registration->order_id);
	}

	function process ()
	{
		global $dbh;
		$this->title= 'View Registration';

		$person = person_load( array( 'user_id' => $this->registration->user_id ) );

		$sth = $dbh->prepare('SELECT name, anonymous FROM registration_events WHERE registration_id = ?');
		$sth->execute( array( $this->registration->registration_id ) );
		$event_reg = $sth->fetch(PDO::FETCH_OBJ);

		$userrows = array();
		$userrows[] = array ('Name', $person->fullname );
		$userrows[] = array ('User&nbsp;ID', $this->registration->user_id);
		$userrows[] = array ('Event', l($event_reg->name, "event/view/{$this->registration->registration_id}"));
		$userrows[] = array ('Payment', $this->registration->payment);
		$userrows[] = array ('Created', $this->registration->time);
		$userrows[] = array ('Modified', $this->registration->modified);
		$userrows[] = array ('Notes', $this->registration->notes);
		$output = form_group('Registration details', '<div class="pairtable">' . table(NULL, $userrows) . '</div>');

		if( ! $event_reg->anonymous )
		{
			// Get registration answers/preferences
			$sth = $dbh->prepare('SELECT qkey, akey
					FROM registration_answers
					WHERE order_id = ?');
			$sth->execute( array(
				$this->registration->order_id
			));

			$prefrows = array();
			while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
				$prefrows[] = $row;
			}
			if( count($prefrows) ) {
				$output .= form_group('Registration answers', '<div class="pairtable">' . table(NULL, $prefrows) . '</div>');
			}
		}

		// Get payment audit information, if available
		$sth = $dbh->prepare('SELECT *
				FROM registration_audit
				WHERE order_id = ?');
		$sth->execute( array(
			$this->registration->order_id
		));

		$payrows = array();
		$row = $sth->fetch(PDO::FETCH_ASSOC);
		if( $row ) {
			foreach($row as $key => $value) {
				$payrows[] = array($key, $value);
			}
			$output .= form_group('Payment details', '<div class="pairtable">' . table(NULL, $payrows) . '</div>');
		}

		$this->setLocation(array($this->title => 0));
		return $output;
	}
}

class RegistrationEdit extends RegistrationForm
{
	var $registration;

	function has_permission()
	{
		global $lr_session;
		if (!$this->registration) {
			error_exit('That registration does not exist');
		}
		return $lr_session->has_permission('registration','edit', $this->registration->order_id);
	}

	function process ()
	{
		$this->title = 'Edit Registration';
		$this->setLocation(array(
			$this->registration->name => "registration/view/" .$this->registration->order_id,
			$this->title => 0
		));
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'submit':
				$this->perform( $edit );
				local_redirect(url("registration/view/" . $this->registration->order_id));
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm()
	{
		global $dbh;
		$this->title = 'Edit registration';

		$output = form_hidden('edit[step]', 'confirm');

		// Get user information
		$sth = $dbh->prepare('SELECT firstname, lastname
					FROM person
					WHERE person.user_id = ?');
		$sth->execute( array($this->registration->user_id) );

		$item = $sth->fetch();

		if( ! $item ) {
			return false;
		}

		$userrows = array();
		$userrows[] = array ('Name', $item['firstname'] . ' ' . $item['lastname']);
		$userrows[] = array ('User&nbsp;ID', $this->registration->user_id);
		$userrows[] = array ('Event&nbsp;ID', $this->registration->registration_id);
		$form = '<div class="pairtable">' . table(NULL, $userrows) . '</div>';
		$pay_opts = array('Unpaid'=>'Unpaid', 'Pending'=>'Pending', 'Paid'=>'Paid', 'Refunded'=>'Refunded');
		$form .= form_radios('Payment', 'edit[payment]', $this->registration->payment, $pay_opts);
		$form .= form_textarea('Notes', 'edit[notes]', $this->registration->notes, 45, 5);
		$output .= form_group('Registration details', $form);

		if ( $this->formbuilder )
		{
			// Get registration answers/preferences
			$sth = $dbh->prepare('SELECT qkey, akey FROM registration_answers
					WHERE order_id = ?');
			$sth->execute( array( $this->registration->order_id) );

			$prefrows = array();
			while($row = $sth->fetch() ) {
				$prefrows[$row['qkey']] = $row['akey'];
			}

			if( count($prefrows) > 0 ) {
				$this->formbuilder->bulk_set_answers ($prefrows);
				$output .= form_group('Registration answers', $this->formbuilder->render_editable (true));
			} else {
				$output .= form_group('Registration answers', $this->formbuilder->render_editable (false));
			}
		}

		$output .= form_submit('Submit') .  form_reset('Reset');

		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$this->title = 'Confirm updates';

		$dataInvalid = $this->isDataInvalid( $edit );

		if( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers( $_POST[$this->formkey] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$output = form_hidden('edit[step]', 'submit');

		$rows = array();
		$rows[] = array( 'Payment', form_hidden('edit[payment]', $edit['payment']) . check_form($edit['payment']) );
		$rows[] = array( 'Notes', form_hidden('edit[notes]', $edit['notes']) . check_form($edit['notes']) );
		$output .= form_group('Registration details', "<div class='pairtable'>" . table(null, $rows) . '</div>');

		if( $this->formbuilder )
		{
			$form = $this->formbuilder->render_viewable();
			$form .= $this->formbuilder->render_hidden();
			$output .= form_group('Registration answers', $form);
		}

		$output .= para('Please confirm that this data is correct and then proceed to arrange payment.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function perform ( &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );

		if( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers( $_POST[$this->formkey] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->registration->set('payment', $edit['payment']);
		$this->registration->set('notes', $edit['notes']);

		if( !$this->registration->save() ) {
			error_exit("Internal error: couldn't save changes to the registration details");
		}

		if( $this->formbuilder )
		{
			if( !$this->registration->save_answers( $this->formbuilder, $_POST[$this->formkey] ) ) {
				error_exit('Error saving registration question answers.');
			}
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		// nonhtml also checks that the string is not blank, so we'll just
		// tack on a trailing letter so that it will only check for HTML...
		if( !validate_nonhtml($edit['notes'] . 'a' ) ) {
			$errors .= '<li>Notes cannot contain HTML';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

/**
 * Download a CSV of all registrations
 */
class RegistrationDownload extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ()
	{
		global $dbh;

		$data = array('Date', 'Order ID', 'Event', 'User ID', 'First name', 'Last name', 'Total');

		// Start the output, let the browser know what type it is
		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"registrations.csv\"");
		$out = fopen('php://output', 'w');
		fputcsv($out, $data);

		$sth = $dbh->prepare('SELECT
								a.date,
								r.order_id,
								e.name,
								p.user_id,
								p.firstname,
								p.lastname,
								a.charge_total
							FROM
								registration_audit a
							LEFT JOIN
								registrations r
							ON a.order_id = r.order_id 
							LEFT JOIN
								registration_events e
							ON r.registration_id = e.registration_id 
							LEFT JOIN
								person p
							ON r.user_id = p.user_id
							ORDER BY
								a.date');
		$sth->execute ();

		while($row = $sth->fetch()) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			$data = array( $row['date'],
							$order_id,
							$row['name'],
							$row['user_id'],
							$row['firstname'],
							$row['lastname'],
							$row['charge_total'] );

			// Output the data row
			fputcsv($out, $data);
		}

		fclose($out);

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}

/**
 * Registration handler
 */
class RegistrationRegister extends RegistrationForm
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->event) {
			error_exit('That event does not exist');
		}
		return $lr_session->has_permission('registration','register');
	}

	function process ()
	{
		if (!$this->check_prereqs())
		{
			return para(theme_error('Pre-requisite check failed'));
		}

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm();
				break;

			case 'submit':
				$this->removePreregistration();
				$rc = $this->save();
				$rc .= $this->generatePay();
				break;

			default:
				if( $this->formbuilder ) {
					$rc = $this->generateForm();
				}
				else {
					$rc = $this->save();
					$rc .= $this->generatePay();
				}
		}

		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));
		return $rc;
	}

	function generateForm ()
	{
		global $CONFIG;

		$this->title = 'Preferences';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		ob_start();
		$retval = @readfile(trim ($CONFIG['paths']['file_url'], '/') . "/data/registration_notice.html");
		if (false !== $retval) {
			$output = ob_get_contents();
		}
		ob_end_clean();

		$output .= form_hidden('edit[step]', 'confirm');

		$output .= $this->formbuilder->render_editable (false);
		$output .= para(form_submit('submit', 'submit') . form_reset('reset'));

		return form($output);
	}

	function generateConfirm()
	{
		global $lr_session;

		$output = '';
		$this->title = 'Confirm preferences';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		$process_func = "confirm_{$this->event->type}";
		$dataInvalid = '';
		if( method_exists( $this, $process_func ) ) {
			$dataInvalid .= $this->$process_func();
		}

		$this->formbuilder->bulk_set_answers( $_POST[$this->formkey] );
		$dataInvalid .= $this->formbuilder->answers_invalid();
		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$form = form_hidden('edit[step]', 'submit');
		$form .= $this->formbuilder->render_viewable();
		$form .= $this->formbuilder->render_hidden();
		$output = form_group('Preferences', $form);

		$output .= para('Please confirm that this data is correct and then proceed to arrange payment.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function save()
	{
		global $lr_session;

		$output = para('');

		$process_func = "save_{$this->event->type}";
		if( method_exists( $this, $process_func ) ) {
			$output .= $this->$process_func();
		}

		$this->registration = new Registration;
		$this->registration->set('user_id', $lr_session->user->user_id);
		$this->registration->set('registration_id', $this->event->registration_id);

		if (! $this->registration->save() ) {
			error_exit('Could not create registration record.');
		}

		if( ! $this->formbuilder ) {
			return $output . para(theme_error('Your registration for this event has been confirmed.'));
		}

		if( !$this->registration->save_answers( $this->formbuilder, $_POST[$this->formkey] ) ) {
			error_exit('Error saving registration question answers.');
		}
		$output .= para(theme_error('Your preferences for this registration have been saved.'));

		return $output;
	}

	/**
	 * The following functions are for doing any type-specific handling.
	 * Types that have no specific handling do not need to be implemented.
	 */
	function confirm_team_league()
	{
		$auto_data = array();
		foreach( $_POST[$this->formkey] as $q => $a ) {
			if( substr( $q, 0, 8 ) == '__auto__' ) {
				$auto_data[substr( $q, 8 )] = $a;
			}
		}
		$auto_data['status'] = 'closed';

		$team = new TeamCreate;
		$team->team = new Team;	// need a team record for unique name checking
		$team->team->league_id = 1;	// inactive teams
		return $team->isDataInvalid( $auto_data );
	}

	function confirm_team_event()
	{
		return $this->confirm_team_league();
	}

	function save_team_league()
	{
		$auto_data = array();
		foreach( $_POST[$this->formkey] as $q => $a ) {
			if( substr( $q, 0, 8 ) == '__auto__' ) {
				$auto_data[substr( $q, 8 )] = $a;
			}
		}
		$auto_data['status'] = 'closed';

		$team = new TeamCreate;
		$team->team = new Team;	// need a team record for unique name checking
		$team->team->league_id = 1;	// inactive teams
		if( $team->perform( $auto_data ) ) {
			return para( theme_error( 'A team record has been created with you as captain.' ) );
		}
		else {
			return para( theme_error( 'Failed to create the team record. Contact ' . variable_get('app_admin_email', 'webmaster@localhost') . ' to ensure that this situation is resolved.' ) );
		}
	}

	function save_team_event()
	{
		return $this->save_team_league();
	}

	/**
	 * Generate the page about payment information.
	 */
	function generatePay()
	{
		global $lr_session;
		global $dbh;

		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);

		if( $this->event->cost == 0 ) {
			$sth = $dbh->prepare("UPDATE registrations SET payment = 'Paid' 
					WHERE order_id = ?");
			$sth->execute( array( $this->registration->order_id) );
			if ( 1 != $sth->rowCount() ) {
				$errors .= para( theme_error( "Your registration was received, but there was an error updating the database. Contact the office to ensure that your information is updated, quoting order #<b>$order_num</b>, or you may not be allowed to be added to rosters, etc." ) );
			}

			$this->title = 'Registration complete';

			return para('Since there is no payment associated with this event, your registration is now complete.');
		}

		$this->title = 'Arrange for payment';

		if( variable_get( 'online_payments', 1 ) )
		{
			$output .= generatePayForm($this->event, $order_num);

			$output .= OfflinePaymentText($order_num);

			$output .= para('Alternately, if you choose not to complete the payment process at this time, you will be able to start the registration process again at a later time and it will pick up where you have left off.');
		} else {
			$output .= h2 ('Payment Details');
			$output .= strtr( variable_get('offline_payment_text', ''),
						array( '%order_num' => $order_num ) );
		}
		$output .= RefundPolicyText();

		return $output;
	}

	function check_prereqs()
	{
		// TODO check again for prereq/antireq to keep people from feeding a manual URL
		return true;
	}

	function removePreregistration()
	{
		global $lr_session, $dbh;
		$sth = $dbh->prepare('DELETE FROM preregistrations
					WHERE user_id = ?
					AND registration_id = ?');
		$sth->execute( array( $lr_session->user->user_id, $this->event->registration_id) );
	}
}

/**
 * Registration unregistration handler
 */
class RegistrationUnregister extends Handler
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->registration) {
			error_exit('That registration does not exist');
		}

		return $lr_session->has_permission('registration','unregister', null, $this->registration);
	}

	function process()
	{
		global $dbh;
		$edit = $_POST['edit'];
		$this->title = 'Unregistering';
		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);

		switch($edit['step']) {
			case 'submit':
				$ok = $this->registration->delete();
				if ( ! $ok ) {
					error_exit ( para( theme_error( "There was an error deleting your registration information. Contact the office, quoting order #<b>$order_num</b>, to have the problem resolved." ) ) );
				}

				$rc = para( 'You have been successfully unregistered for this event.' );
				break;

			default:
				$rc = $this->generateConfirm();
		}

		$this->setLocation(array(
			$order_num => 'registration/view/' .$this->order_id,
			$this->title => 0
		));

		return $rc;
	}

	function generateConfirm()
	{
		$this->title = 'Confirm unregister';

		$output = form_hidden('edit[step]', 'submit');
		$output .= para('Please confirm that you want to unregister from this event');
		$output .= para(form_submit('submit'));

		return form($output);
	}
}

/**
 * Handle responses from the payment server
 */
class RegistrationOnlinePaymentResponse extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		global $CONFIG;

		$file_url = $CONFIG['paths']['file_url'];
		$base_url = $CONFIG['paths']['base_url'];
		$org = variable_get('app_org_name','league');
		print <<<HTML_HEADER
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>$org - Online Transaction Result</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="stylesheet" type="text/css" href="http://{$_SERVER["SERVER_NAME"]}$file_url/style.css">
<script type="text/javascript">
<!--
function close_and_redirect(url)
{
	window.opener.location.href = url;
	window.close();
}
-->
</script>
</head>
<body>
HTML_HEADER;

		handlePaymentResponse();

		print para("Click <a href=\"/\" onClick=\"close_and_redirect('http://{$_SERVER["SERVER_NAME"]}$base_url/event/list')\">here</a> to close this window.");

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}

/**
 * Registration history handler
 */
class RegistrationHistory extends Handler
{
	var $user;

	function has_permission()
	{
		global $lr_session;

		if (!$this->user) {
			error_exit('That user does not exist');
		}
		return $lr_session->has_permission('registration','history', $this->user);
	}

	function process ()
	{
		global $lr_session, $dbh;

		$this->title= 'View Registration History';
		$rows = array();

		$sth = $dbh->prepare('SELECT
				e.registration_id, e.name, r.order_id, r.time, r.payment
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.user_id = ?
			ORDER BY r.time');
		$sth->execute( array( $this->user ) );
		while($row = $sth->fetch() ) {
			$name = l($row['name'], 'event/view/' . $row['registration_id']);
			$order = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			if( $lr_session->has_permission('registration', 'view', $row['order_id']) ) {
				$order = l($order, 'registration/view/' . $row['order_id']);
			}

			$rows[] = array( $name, $order, substr($row['time'], 0, 10), $row['payment'] );
		}

		/* Add in any preregistrations */
		$sth = $dbh->prepare('SELECT e.registration_id, e.name
			FROM preregistrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.user_id = ?
			ORDER BY r.registration_id');
		$sth->execute( array( $this->user) );
		while($row = $sth->fetch() ) {
			$name = l($row['name'], 'event/view/' . $row['registration_id']);
			$order = 'Prereg';
			$rows[] = array( $name, $order, '', 'No' );
		}

		$header = array('Event', 'Order ID', 'Date', 'Payment');
		$output = table($header, $rows);

		$this->setLocation(array($this->title => 0));

		return $output;
	}
}

function OfflinePaymentText($order_num)
{
	$output = para("If you prefer to pay offline via cheque, the online portion of your registration process is now complete, but you must do the following to make payment:");
	$output .= strtr( variable_get('offline_payment_text', ''),
						array( '%order_num' => $order_num ) );

	return $output;
}

function RefundPolicyText()
{
	$output = h2 ('Refund Policy');
	$output .= variable_get('refund_policy_text', '');

	return $output;
}

function AddAutoQuestions( &$formbuilder, $type )
{
	switch ($type) {
		// Individual registrations have no extra questions at this time
		case 'membership':
		case 'individual_event':
		case 'individual_league':
			break;

		// League team registrations have these additional questions
		case 'team_league':
			$formbuilder->add_question('__auto__region_preference', 'Region Preference', 'Area of city where you would prefer to play', 'multiplechoice', true, -49, getOptionsFromEnum('field', 'region'));
			//$formbuilder->add_question('__auto__status', 'Team Status', 'Is your team open (others can join) or closed (only captain can add players)', 'multiplechoice', true, -48, getOptionsFromEnum('team', 'status'));
			// Note: intentionally fall through to the next case

		// All team registrations have these additional questions
		case 'team_event':
			$formbuilder->add_question('__auto__name', 'Team Name', 'The full name of your team. Text only, no HTML', 'textfield', false, -99);
			$formbuilder->add_question('__auto__shirt_colour', 'Shirt Colour', "Shirt colour of your team. If you don't have team shirts, pick 'light' or 'dark'", 'textfield', false, -98);
			break;
	}
}

function registration_settings ( )
{
	$group = form_textfield('Order ID format string', 'edit[order_id_format]', variable_get('order_id_format', 'R%09d'), 60, 120, 'sprintf format string for the unique order ID.');

	$group .= form_radios('Allow tentative members to register?', 'edit[allow_tentative]', variable_get('allow_tentative', 0), array('Disabled', 'Enabled'), 'Tentative members include those whose accounts have not yet been approved but don\'t appear to be duplicates of existing accounts, and those who have registered for membership and called to arrange an offline payment which has not yet been received.');

	$group .= form_textfield('Current membership registration event IDs', 'edit[membership_ids]', variable_get('membership_ids', ''), 60, 120, 'Comma separated list of event IDs that should be considered current memberships.');

	$group .= form_radios('Online payments', 'edit[online_payments]', variable_get('online_payments', 1), array('Disabled', 'Enabled'), 'Do we handle online payments?');

	$group_online = form_textfield('Payment provider implementation file', 'edit[payment_implementation]', variable_get('payment_implementation', 'moneris'), 60, 120, 'File will have .inc added, and be looked for in the includes/payment folder.');

	$group_online .= form_textfield('Invoice implementation file', 'edit[invoice_implementation]', variable_get('invoice_implementation', 'invoice'), 60, 120, 'File will have .inc added, and be looked for in the includes folder.');

	$group_online .= form_textfield('Registration ID format string', 'edit[reg_id_format]', variable_get('reg_id_format', 'Reg%05d'), 60, 120, 'sprintf format string for the registration ID, sent to the payment processor as the item number.');

	$group_online .= form_radios('Testing payments', 'edit[test_payments]', variable_get('test_payments', 0), array('Nobody', 'Everybody', 'Admins'), 'Who should get test instead of live payments?');

	$group_online .= form_textfield('Live payment store ID', 'edit[live_store]', variable_get('live_store', ''), 60, 120);
	$group_online .= form_textfield('Live payment password', 'edit[live_password]', variable_get('live_password', ''), 60, 120, 'For Moneris, this is the login password; for Chase it is the merchant transaction key');

	$group_online .= form_textfield('Test payment store ID', 'edit[test_store]', variable_get('test_store', ''), 60, 120);
	$group_online .= form_textfield('Test payment password', 'edit[test_password]', variable_get('test_password', ''), 60, 120, 'For Moneris, this is the login password; for Chase it is the merchant transaction key');

	$group .= form_group('Online payment options', $group_online);
 
	$group .= form_textarea('Text of refund policy', 'edit[refund_policy_text]', variable_get('refund_policy_text', ''), 70, 10, 'Customize the text of your refund policy, to be shown on registration pages and invoices.');

	$offline_steps = li('Mail (or personally deliver) a cheque for the appropriate amount to the league office');
	$offline_steps .= li('Ensure that you quote order #<b>%order_num</b> on the cheque in order for your payment to be properly credited.');
	$offline_steps .= li('Also include a note indicating which registration the cheque is for, along with your full name.');
	$offline_steps .= li('If you are paying for multiple registrations with a single cheque, be sure to list all applicable order numbers, registrations and member names.');
	$offline = ul($offline_steps);
	$offline .= para('Please note that online payment registrations are \'live\' while offline payments are not.  You will not be registered to the appropriate category that you are paying for until the cheque is received and processed (usually within 1-2 business days of receipt).');
 
	$group .= form_textarea('Text of offline payment directions', 'edit[offline_payment_text]', variable_get('offline_payment_text', $offline), 70, 10, 'Customize the text of your offline payment policy. Available variables are: %order_num');

	$output = form_group('Registration configuration', $group);

	return settings_form($output);
}

function registration_statistics($args)
{
	global $dbh;
	$level = arg(2);
	global $CONFIG;

	if (!$level || $level == 'past')
	{
		if( $level == 'past' ) {
			$year = arg(3);
		} else {
			$year = date('Y');
		}

		$sth = $dbh->prepare('SELECT r.registration_id, e.name, e.type, COUNT(*)
			FROM registrations r
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.payment != "Refunded"
				AND (
					YEAR(e.open) = :year
					OR YEAR(e.close) = :year
				)
			GROUP BY r.registration_id
			ORDER BY e.type, e.open DESC, e.close DESC, r.registration_id');
		$sth->execute( array( 'year' => $year ) );

		$type_desc = array('membership' => 'Membership Registrations',
							'individual_event' => 'One-time Individual Event Registrations',
							'team_event' => 'One-time Team Event Registrations',
							'individual_league' => 'Individual Registrations (for players without a team)',
							'team_league' => 'Team Registrations');
		$last_type = '';
		$rows = array();

		while($row = $sth->fetch() ) {
			if ($row['type'] != $last_type) {
				$rows[] = array( array('colspan' => 4, 'data' => h2($type_desc[$row['type']])));
				$last_type = $row['type'];
			}
			$rows[] = array( l($row['name'], "statistics/registration/summary/${row['registration_id']}"),
							$row['COUNT(*)'] );
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$sth = $dbh->prepare('SELECT YEAR(MIN(open)) FROM registration_events');
		$sth->execute();
		$first_year = $sth->fetchColumn();
		$current_year = date('Y');
		if( $first_year != $current_year ) {
			$output .= '<p><p>Historical data:';
			for( $year = $first_year; $year <= $current_year; ++ $year ) {
				$output .= ' ' . l($year, "statistics/registration/past/$year");
			}
		}

		return form_group('Registrations by event', $output);
	}
	else
	{
		if ($level == 'summary')
		{
			$id = arg(3);
			$event = event_load( array('registration_id' => $id) );
			if (! $event )
			{
				return para( "Unknown event ID $id" );
			}
			$output = h2('Event: ' .
							l($event->name, "event/view/$id"));
			$rows = array();

			if( ! $event->anonymous )
			{
				$sth = $dbh->prepare('SELECT p.gender, COUNT(order_id)
					FROM registrations r
						LEFT JOIN person p ON r.user_id = p.user_id
					WHERE r.registration_id = ?
						AND r.payment != "Refunded"
					GROUP BY p.gender
					ORDER BY gender');
				$sth->execute( array( $id) );

				$sub_table = array();
				while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
					$sub_table[] = $row;
				}
				$rows[] = array("By gender:", table(null, $sub_table));
			}

			$sth = $dbh->prepare('SELECT payment, COUNT(order_id)
				FROM registrations
				WHERE registration_id = ?
				GROUP BY payment
				ORDER BY payment');
			$sth->execute( array($id) );

			$sub_table = array();
			while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
				$sub_table[] = $row;
			}
			$rows[] = array("By payment:", table(null, $sub_table));

			$formkey = 'registration_' . $id;
			$formbuilder = formbuilder_load($formkey);
			if( $formbuilder )
			{
				foreach ($formbuilder->_questions as $question)
				{
					$qkey = $question->qkey;

					// We don't want to see text answers here, they won't group
					// well
					if ($question->qtype == 'multiplechoice' )
					{
						$sth = $dbh->prepare('SELECT
								akey,
								COUNT(registration_answers.order_id)
							FROM registration_answers
								LEFT JOIN registrations ON registration_answers.order_id = registrations.order_id
							WHERE registration_id = ?
								AND qkey = ?
								AND payment != "Refunded"
							GROUP BY akey
							ORDER BY akey');
						$sth->execute( array( $id, $qkey) );

						$sub_table = array();
						while($row = $sth->fetch(PDO::FETCH_ASSOC) ) {
							$sub_table[] = $row;
						}
						$rows[] = array("$qkey:", table(null, $sub_table));
					}
				}
			}

			if( ! count( $rows ) )
			{
				$output .= para( 'No statistics to report, as this event is anonymous and has no survey.' );
			}
			else
			{
				$output .= "<div class='pairtable'>" . table(NULL, $rows) . "</div>";

				$opts = array(
					l('See detailed registration list', "statistics/registration/users/$id/1"),
					l('download detailed registration list', "statistics/registration/list/$id"),
				);
				if( $event->anonymous ) {
					$opts[] = l('download survey results', "statistics/registration/survey/$id");
				}
				$output .= para( join( ' or ', $opts ) );
			}

			return form_group('Summary of registrations', $output);
		}

		else if ($level == 'users')
		{
			$id = arg(3);
			$page = arg(4);
			if( $page < 1 )
			{
				$page = 1;
			}

			$event = event_load( array('registration_id' => $id) );
			if (! $event )
			{
				return para( "Unknown event ID $id" );
			}
			$output = h2('Event: ' .
							l($event->name, "event/view/$id"));

			$items = variable_get('items_per_page', 25);
			if( $items == 0 ) {
				$items = 1000000;
			}
			$from = ($page - 1) * $items;
			$sth = $dbh->prepare('SELECT COUNT(order_id)
				FROM registrations
				WHERE registration_id = ?');
			$sth->execute( array($id));
			$total = $sth->fetchColumn();

			if( $from <= $total )
			{
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
					LIMIT $from, $items");
				$sth->execute( array(-$CONFIG['localization']['tz_adjust'], $id) );

				$rows = array();
				while($row = $sth->fetch() ) {
					$order_id = l(sprintf(variable_get('order_id_format', '%d'), $row['order_id']), 'registration/view/' . $row['order_id']);

					$rows[] = array( $order_id,
									l("${row['firstname']} ${row['lastname']}", "person/view/${row['user_id']}"),
									$row['time'], $row['payment']);
				}

				$header = array( 'Order ID', 'Player', 'Date/Time', 'Payment' );
				$output .= "<div class='pairtable'>" . table($header, $rows) . "</div>";

				if( $total )
				{
					$output .= page_links( url("statistics/registration/users/$id/"), $page, $total );
				}
			}
			else
			{
				$output .= para( 'There are no ' . ($page == 1 ? '' : 'more ') .
								'registrations for this event.' );
			}

			return form_group('Registrations by user', $output);
		}

		else if ($level == 'list')
		{
			$id = arg(3);

			$event = event_load( array('registration_id' => $id) );
			if (! $event )
			{
				return para( "Unknown event ID $id" );
			}
			if( ! $event->anonymous ) {
				$formkey = 'registration_' . $id;
				$formbuilder = new FormBuilder;
				$formbuilder->load($formkey);
				AddAutoQuestions( $formbuilder, $event->type );
			}

			$data = array( 'User ID',
							'Member ID',
							'First Name',
							'Last Name',
							'Email Address',
							'Address',
							'City',
							'Province',
							'Postal Code',
							'Home Phone',
							'Work Phone',
							'Mobile Phone',
							'Gender',
							'Birthdate',
							'Height',
							'Skill Level',
							'Shirt Size',
							'Order ID',
							'Created Date',
							'Modified Date',
							'Payment' );

			if( $formbuilder )
			{
				foreach ($formbuilder->_questions as $question)
				{
					$data[] = $question->qkey;
				}
			}

			$data[] = 'Notes';

			// Start the output, let the browser know what type it is
			header('Content-type: text/x-csv');
			header("Content-Disposition: attachment; filename=\"$event->name.csv\"");
			$out = fopen('php://output', 'w');
			fputcsv($out, $data);

			$sth = $dbh->prepare('SELECT
				r.order_id,
				DATE_ADD(r.time, INTERVAL ? MINUTE) as time,
				DATE_ADD(r.modified, INTERVAL ? MINUTE) as modified,
				r.payment,
				r.notes,
				p.*
			FROM registrations r
				LEFT JOIN person p ON r.user_id = p.user_id
			WHERE r.registration_id = ?
			ORDER BY payment, order_id');
			$sth->execute( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], $id) );

			while($row = $sth->fetch() ) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

				$data = array( $row['user_id'],
								$row['member_id'],
								$row['firstname'],
								$row['lastname'],
								$row['email'],
								$row['addr_street'],
								$row['addr_city'],
								$row['addr_prov'],
								$row['addr_postalcode'],
								$row['home_phone'],
								$row['work_phone'],
								$row['mobile_phone'],
								$row['gender'],
								$row['birthdate'],
								$row['height'],
								$row['skill_level'],
								$row['shirtsize'],
								$order_id,
								$row['time'],
								$row['modified'],
								$row['payment'] );

				// Add all of the answers
				if( $formbuilder )
				{
					$fsth = $dbh->prepare('SELECT akey FROM registration_answers WHERE order_id = ? AND qkey = ?');
					foreach ($formbuilder->_questions as $question)
					{
						$fsth->execute( array( $row['order_id'], $question->qkey));
						$data[] = $fsth->fetchColumn();
					}
				}

				$data[] = $row['notes'];

				// Output the data row
				fputcsv($out, $data);
			}

			fclose($out);

			// Returning would cause the Leaguerunner menus to be added
			exit;
		}

		else if ($level == 'survey')
		{
			$id = arg(3);

			$event = event_load( array('registration_id' => $id) );
			if (! $event )
			{
				return para( "Unknown event ID $id" );
			}
			$formkey = 'registration_' . $id;
			$formbuilder = new FormBuilder;
			$formbuilder->load($formkey);
			AddAutoQuestions( $formbuilder, $event->type );

			$data = array();

			if( $formbuilder )
			{
				foreach ($formbuilder->_questions as $question)
				{
					$data[] = $question->qkey;
				}
			}

			if( empty( $data ) ) {
				return para( 'No details available for download.' );
			}

			// Start the output, let the browser know what type it is
			header('Content-type: text/x-csv');
			header("Content-Disposition: attachment; filename=\"{$event->name}_survey.csv\"");
			$out = fopen('php://output', 'w');
			fputcsv($out, $data);

			$sth = $dbh->prepare('SELECT order_id FROM registrations r
				WHERE r.registration_id = ?  ORDER BY order_id');
			$sth->execute( array($id) );

			while($row = $sth->fetch() ) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);
				$data = array();

				// Add all of the answers
				if( $formbuilder )
				{
					$fsth = $dbh->prepare('SELECT akey
						FROM registration_answers
						WHERE order_id = ?
						AND qkey = ?');
					foreach ($formbuilder->_questions as $question)
					{
						$fsth->execute( array( $row['order_id'], $question->qkey));
						$data[] = $fsth->fetchColumn();
					}
				}

				// Output the data row
				fputcsv($out, $data);
			}

			fclose($out);

			// Returning would cause the Leaguerunner menus to be added
			exit;
		}

		else if ($level == 'unpaid')
		{
			$total = array();

			$sth = $dbh->prepare('SELECT
					r.order_id, r.registration_id,
					r.payment, r.modified, r.notes, e.name,
					p.user_id, p.firstname, p.lastname
				FROM registrations r
					LEFT JOIN registration_events e ON r.registration_id = e.registration_id
					LEFT JOIN person p ON r.user_id = p.user_id
				WHERE r.payment = "Unpaid" 
					OR r.payment = "Pending"
				ORDER BY r.payment, r.modified');
			$sth->execute();
			$rows = array();
			while($row = $sth->fetch() ) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);
				$rows[] = array(
								l($order_id, "registration/view/${row['order_id']}"),
								l("${row['firstname']} ${row['lastname']}", "person/view/${row['user_id']}"),
								$row['modified'],
								$row['payment'],
								l('Unregister', "registration/unregister/${row['order_id']}"),
								l('Edit', "registration/edit/${row['order_id']}")
								);
				$rows[] = array( '', array( 'data' => l($row['name'], "event/view/${row['registration_id']}"), 'colspan' => 5 ) );
				if( $row['notes'] ) {
					$rows[] = array( '', array( 'data' => $row['notes'], 'colspan' => 5 ) );
				}
				$rows[] = array('&nbsp;');
				$total[$row['payment']] ++;
			}

			$total_output = array();
			foreach ($total as $key => $value) {
				$total_output[] = array ($key, $value);
			}

			$output = '<div class="pairtable">' . table(null, $rows) . table(array('Totals:'), $total_output) . '</div>';

			return form_group('Unpaid registrations', $output);
		}

		else
		{
			return para( "Unknown statistics requested: $level" );
		}

	}
}

?>
