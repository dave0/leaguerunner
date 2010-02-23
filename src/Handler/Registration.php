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
			if( ! $obj->registration ) {
				error_exit( 'That is not a valid registration ');
			}
			$obj->event = event_load( array('registration_id' => $obj->registration->registration_id) );
			$obj->registration_form_load();
			break;
		case 'edit':
			$obj = new RegistrationEdit;
			$obj->registration = registration_load( array('order_id' => $id) );
			if( ! $obj->registration ) {
				error_exit( 'That is not a valid registration ');
			}
			$obj->event = event_load( array('registration_id' => $obj->registration->registration_id) );
			$obj->registration_form_load();
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
			$obj->registration_form_load();
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

function registration_permissions ( &$user, $action, $id, $registration )
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
			if($lr_session->user->is_active() && $lr_session->is_complete() && $registration->user_id == $lr_session->user->user_id) {
				if($registration->payment != 'Unpaid' && $registration->payment != 'Pending') {
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
	function registration_form_load ()
	{
		global $lr_session;

		$user = $lr_session->user;
		if( $this->registration ) {
			$user = $this->registration->user();
		}

		$this->formbuilder = $this->event->load_survey( true, $user);

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
class RegistrationView extends RegistrationForm
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

		$person = $this->registration->user();

		$userrows = array();
		$userrows[] = array ('Name', l($person->fullname, url("person/view/{$person->id}")) );
		$userrows[] = array ('Member&nbsp;ID', $person->member_id);
		$userrows[] = array ('Event', l($this->event->name, url("event/view/{$this->event->registration_id}")));
		$userrows[] = array ('Registered Price', $this->registration->total_amount);
		$userrows[] = array ('Payment Status', $this->registration->payment);
		$userrows[] = array ('Payment Amount', $this->registration->paid_amount);
		$userrows[] = array ('Payment Method', $this->registration->payment_method);
		$userrows[] = array ('Payment Date', $this->registration->date_paid);
		$userrows[] = array ('Paid By (if different)', $this->registration->paid_by);
		$userrows[] = array ('Created', $this->registration->time);
		$userrows[] = array ('Last Modified', $this->registration->modified);
		$userrows[] = array ('Notes', $this->registration->notes);
		$output = form_group('Registration details', '<div class="pairtable">' . table(NULL, $userrows) . '</div>');

		if( ! $this->event->anonymous && $this->formbuilder ) {
			$this->formbuilder->bulk_set_answers_sql(
				'SELECT qkey, akey FROM registration_answers WHERE order_id = ?',
				array( $this->registration->order_id)
			);

			$output .= form_group('Registration answers', $this->formbuilder->render_viewable() );
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

		$player = $this->registration->user();
		if( ! $player ) {
			return false;
		}

		$noneditable = array();
		$noneditable[] = array ('Name', l($player->fullname, url("person/view/{$player->id}")) );
		$noneditable[] = array ('Member&nbsp;ID', $player->member_id);
		$noneditable[] = array ('Event', l($this->event->name, url("event/view/{$this->event->registration_id}")));
		$noneditable[] = array ('Registered Price', $this->registration->total_amount);
		$form = '<div class="pairtable">' . table(NULL, $noneditable) . '</div>';
		$pay_opts = getOptionsFromEnum('registrations', 'payment');
		array_shift($pay_opts);
		$form .= form_select('Payment Status', 'edit[payment]', $this->registration->payment, $pay_opts);
		$form .= form_textfield('Paid Amount', 'edit[paid_amount]', $this->registration->paid_amount, 10,10, "Amount paid to-date for this registration");
		$form .= form_textfield('Payment Method', 'edit[payment_method]', $this->registration->payment_method, 40,255, "Method of payment (cheque, email money xfer, etc).  Provide cheque or transfer number in 'notes' field.");
		$thisYear = strftime('%Y', time());
		$form .= form_select_date('Payment Date', 'edit[date_paid]', $this->registration->date_paid, ($thisYear - 1), ($thisYear + 1), 'Date payment was received');
		$form .= form_textfield('Paid By', 'edit[paid_by]', $this->registration->paid_by, 40,255, "Name of payee, if different from registrant");
		$form .= form_textarea('Notes', 'edit[notes]', $this->registration->notes, 45, 5);
		$output .= form_group('Registration details', $form);

		if ( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers_sql(
				'SELECT qkey, akey FROM registration_answers WHERE order_id = ?',
				array( $this->registration->order_id)
			);

			if( count($this->formbuilder->_answers) > 0 ) {
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
			$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}
		// Force date into single field after validation
		$edit['date_paid'] = sprintf('%04d-%02d-%02d',
			$edit['date_paid']['year'],
			$edit['date_paid']['month'],
			$edit['date_paid']['day']);

		$output = form_hidden('edit[step]', 'submit');
		$fields = array(
			'Payment Status' => 'payment',
			'Paid Amount' => 'paid_amount',
			'Payment Method' => 'payment_method',
			'Paid By' => 'paid_by',
			'Date Paid' => 'date_paid',
			'Notes' => 'notes',
		);

		$rows = array();
		foreach ($fields as $display => $column) {
			array_push( $rows,
				array( $display, form_hidden("edit[$column]", $edit[$column]) . check_form($edit[$column])));
		}
		$output .= form_group('Registration details', "<div class='pairtable'>" . table(null, $rows) . '</div>');

		if( $this->formbuilder )
		{
			$form = $this->formbuilder->render_viewable();
			$form .= $this->formbuilder->render_hidden();
			$output .= form_group('Registration answers', $form);
		}

		$output .= para('Please confirm that this data is correct and click the submit button to proceed to the payment information page.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function perform ( &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );

		if( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$fields = array(
			'payment',
			'notes',
			'paid_amount',
			'payment_method',
			'paid_by',
			'date_paid',
		);
		foreach ($fields as $field) {
			$this->registration->set($field, $edit[$field]);
		}

		if( !$this->registration->save() ) {
			error_exit("Internal error: couldn't save changes to the registration details");
		}

		if( $this->formbuilder )
		{
			if( !$this->registration->save_answers( $this->formbuilder, $_POST[$this->event->formkey()] ) ) {
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
		global $CONFIG;

		$data = array('Date', 'Order ID', 'Event', 'User ID', 'First name', 'Last name', 'Email', 'Payment Status', 'Payment Date', 'Payment From', 'Amt Paid', 'Total Cost');

		// Start the output, let the browser know what type it is
		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"registrations.csv\"");
		$out = fopen('php://output', 'w');
		fputcsv($out, $data);

		$sth = $dbh->prepare("SELECT
					r.time,
					r.order_id,
					e.name,
					p.user_id,
					p.firstname,
					p.lastname,
					p.email,
					r.payment,
					DATE_ADD(r.date_paid, INTERVAL ? MINUTE) as date_paid,
					r.paid_by,
					r.paid_amount,
					r.total_amount
				FROM
					registrations r
					LEFT JOIN registration_events e ON r.registration_id = e.registration_id
					LEFT JOIN person p ON r.user_id = p.user_id
				ORDER BY r.time");
		$sth->execute ( array(-$CONFIG['localization']['tz_adjust']) );

		while($row = $sth->fetch()) {
			$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			$data = array( $row['time'],
					$order_id,
					$row['name'],
					$row['user_id'],
					$row['firstname'],
					$row['lastname'],
					$row['email'],
					$row['payment'],
					$row['date_paid'],
					$row['paid_by'],
					$row['paid_amount'],
					$row['total_amount'] );

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
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm();
				break;

			case 'submit':
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

		$this->title = 'Registration';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		ob_start();
		$retval = @readfile(trim ($CONFIG['paths']['file_path'], '/') . "/data/registration_notice.html");
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

		$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
		$dataInvalid .= $this->formbuilder->answers_invalid();
		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$form = form_hidden('edit[step]', 'submit');
		$form .= $this->formbuilder->render_viewable();
		$form .= $this->formbuilder->render_hidden();
		$output = form_group('Registration', $form);

		$output .= para('Please confirm that this data is correct and click the submit button to proceed to the payment information page.');
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
		$this->registration->set('total_amount', $this->event->total_cost());

		if (! $this->registration->save() ) {
			error_exit('Could not create registration record.');
		}

		if( $this->formbuilder && !$this->registration->save_answers( $this->formbuilder, $_POST[$this->event->formkey()] ) ) {
			error_exit('Error saving registration question answers.');
		}

		return $output . para(theme_error('Your registration has been recorded.  See Payment Details below.'));
	}

	/**
	 * The following functions are for doing any type-specific handling.
	 * Types that have no specific handling do not need to be implemented.
	 */
	function confirm_team_league()
	{

		global $lr_session;

		$team_id = $_POST[$this->event->formkey()]['__auto__team_id'];

		if( $lr_session->user->has_position_on( $team_id, array('captain') ) ) {
			return false;
		}
		return "<ul><li>You do not captain team $team_id</li></ul>";
	}

	function confirm_team_event()
	{
		return $this->confirm_team_league();
	}

	function save_team_league()
	{
		global $lr_session;

		$team_id = $_POST[$this->event->formkey()]['__auto__team_id'];
		if( $lr_session->user->has_position_on( $team_id, array('captain') ) ) {
			return false;
		}
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

		$base_url = $CONFIG['paths']['base_url'];
		$org = variable_get('app_org_name','league');
		print <<<HTML_HEADER
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>$org - Online Transaction Result</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="stylesheet" type="text/css" href="http://{$_SERVER["SERVER_NAME"]}$base_url/style.css">
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

		$header = array('Event', 'Order ID', 'Date', 'Payment');
		$output = table($header, $rows);

		$this->setLocation(array($this->title => 0));

		return $output;
	}
}

function OfflinePaymentText($order_num)
{
	$output = para("The online portion of your registration process is now complete, but you must do the following to make payment:");
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

function registration_settings ( )
{
	$group = form_textfield('Order ID format string', 'edit[order_id_format]', variable_get('order_id_format', 'R%09d'), 60, 120, 'sprintf format string for the unique order ID.');

	$group .= form_radios('Allow tentative members to register?', 'edit[allow_tentative]', variable_get('allow_tentative', 0), array('Disabled', 'Enabled'), 'Tentative members include those whose accounts have not yet been approved but don\'t appear to be duplicates of existing accounts, and those who have registered for membership and called to arrange an offline payment which has not yet been received.');

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

		$type_desc = event_types();
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

			$formbuilder = formbuilder_load($event->formkey());
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
				$formbuilder = $event->load_survey( true, null );
			}

			$data = array(
				'User ID',
				'Member ID',
				'First Name',
				'Last Name',
				'Email',
				'Gender',
				'Skill Level',
				'Order ID',
				'Date Registered',
				'Date Modified',
				'Date Paid',
				'Payment Status',
				'Amount Owed',
				'Amount Paid'
			);

			if( $formbuilder )
			{
				foreach ($formbuilder->_questions as $question)
				{
					if( $question->qkey == '__auto__team_id' ) {
						$data[] = 'Team Name';
						$data[] = 'Team Rating';
						$data[] = 'Team ID';
					} else {
						$data[] = $question->qkey;
					}
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
				r.total_amount,
				r.paid_amount,
				r.paid_by,
				DATE_ADD(r.date_paid, INTERVAL ? MINUTE) as date_paid,
				r.payment_method,
				r.notes,
				p.*
			FROM registrations r
				LEFT JOIN person p ON r.user_id = p.user_id
			WHERE r.registration_id = ?
			ORDER BY payment, order_id');
			$sth->execute( array( -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], -$CONFIG['localization']['tz_adjust'], $id) );

			while($row = $sth->fetch() ) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

				$data = array( $row['user_id'],
					$row['member_id'],
					$row['firstname'],
					$row['lastname'],
					$row['email'],
					$row['gender'],
					$row['skill_level'],
					$order_id,
					$row['time'],
					$row['modified'],
					$row['date_paid'],
					$row['payment'],
					$row['total_amount'],
					$row['paid_amount'],
				);

				// Add all of the answers
				if( $formbuilder )
				{
					$fsth = $dbh->prepare('SELECT akey FROM registration_answers WHERE order_id = ? AND qkey = ?');
					foreach ($formbuilder->_questions as $question)
					{
						$fsth->execute( array( $row['order_id'], $question->qkey));
						$item = $fsth->fetchColumn();
						// HACK! this lets us output team names as well as ID
						if( $question->qkey == '__auto__team_id' ) {
							$usth = $dbh->prepare('SELECT name, rating FROM team WHERE team_id = ?');
							$usth->execute( array( $item ) );
							$team_info = $usth->fetch();
							$data[] = $team_info['name'];
							$data[] = $team_info['rating'];
						}

						$data[] = $item;
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
			$formbuilder = $event->load_survey( true, null );

			$data = array();

			foreach ($formbuilder->_questions as $question) {
				$data[] = $question->qkey;
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
