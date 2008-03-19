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
		case 'register':
			$obj = new RegistrationRegister;
			$obj->event = event_load( array('registration_id' => $id) );
			$obj->registration_form_load($id, true);
			break;
		case 'unregister':
			$obj = new RegistrationUnregister;
			$obj->order_id = $id;
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

	switch( $action )
	{
		case 'view':
		case 'edit':
			// Only admin can view details or edit
			break;
		case 'register':
		case 'unregister':
			// Only players with completed profiles can register
			return ($lr_session->user->is_active() && $lr_session->is_complete());
		case 'history':
			// Players with completed profiles can view their own history
			if ($id) {
				return ($lr_session->is_complete() && $lr_session->user->user_id == $id);
			}
			else {
				return ($lr_session->is_complete());
			}
		case 'statistics':
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
			if ($lr_session->has_permission('registration','unregister', $registration->order_id) ) {
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
		global $lr_session;
		$this->title= 'View Registration';

		// Get user information
		$result = db_query('SELECT
								firstname,
								lastname
							FROM
								person
							WHERE
								person.user_id = %d',
							$this->registration->user_id);

		if(1 != db_num_rows($result)) {
			return false;
		}
		$item = db_fetch_array($result);

		$event_name = db_result(db_query('SELECT name FROM registration_events WHERE registration_id = %d',
				$this->registration->registration_id));

		$userrows = array();
		$userrows[] = array ('Name', $item['firstname'] . ' ' . $item['lastname']);
		$userrows[] = array ('User&nbsp;ID', $this->registration->user_id);
		$userrows[] = array ('Event', l($event_name, "event/view/{$this->registration->registration_id}"));
		$userrows[] = array ('Payment', $this->registration->payment);
		$userrows[] = array ('Created', $this->registration->time);
		$userrows[] = array ('Modified', $this->registration->modified);
		$userrows[] = array ('Notes', $this->registration->notes);
		$output = form_group('Registration details', '<div class="pairtable">' . table(NULL, $userrows) . '</div>');

		// Get registration answers/preferences
		$result = db_query('SELECT
								qkey, akey
							FROM
								registration_answers
							WHERE
								order_id = %d',
							$this->registration->order_id);

		$prefrows = array();
		if(0 != db_num_rows($result)) {

			while($row = db_fetch_array($result)) {
				$prefrows[] = $row;
			}
			$output .= form_group('Registration answers', '<div class="pairtable">' . table(NULL, $prefrows) . '</div>');
		}

		// Get payment audit information, if available
		$result = db_query('SELECT
								*
							FROM
								registration_audit
							WHERE
								order_id = %d',
							$this->registration->order_id);

		if(0 != db_num_rows($result)) {
			$payrows = array();

			$item = db_fetch_array($result);
			foreach ($item as $key => $value) {
				$payrows[] = array ($key, $value);
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
		$this->title = 'Edit registration';

		$output = form_hidden('edit[step]', 'confirm');

		// Get user information
		$result = db_query('SELECT
								firstname,
								lastname
							FROM
								person
							WHERE
								person.user_id = %d',
							$this->registration->user_id);

		if(1 != db_num_rows($result)) {
			return false;
		}
		$item = db_fetch_array($result);

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
			$result = db_query('SELECT
									qkey, akey
								FROM
									registration_answers
								WHERE
									order_id = %d',
								$this->registration->order_id);

			if(0 != db_num_rows($result)) {
				$prefrows = array();

				while($row = db_fetch_array($result)) {
					$prefrows[$row['qkey']] = $row['akey'];
				}
				$this->formbuilder->bulk_set_answers ($prefrows);
				$output .= form_group('Registration answers', $this->formbuilder->render_editable (true));
			}
			else {
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
		$this->title = 'Preferences';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		ob_start();
		$retval = @readfile('data/registration_notice.html');
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

		$output = para();

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

		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);

		if( $this->event->cost == 0 ) {
			db_query ('UPDATE
							registrations
						SET
							payment = "Paid"
						WHERE
							order_id = %d',
						$this->registration->order_id);
			if ( 1 != db_affected_rows() ) {
				$errors .= para( theme_error( "Your registration was received, but there was an error updating the database. Contact the TUC office to ensure that your information is updated, quoting order #<b>$order_num</b>, or you may not be allowed to be added to rosters, etc." ) );
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
		}
		else
		{
			$output = para( 'No text provided yet for all offline payments.' );
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
		global $lr_session;
		$result = db_query ('DELETE FROM
								preregistrations
							WHERE
								user_id = %d
							AND
								registration_id = %d',
							$lr_session->user->user_id,
							$this->event->registration_id);
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
		return $lr_session->has_permission('registration','unregister');
	}

	function process()
	{
		global $lr_session;
		$edit = $_POST['edit'];
		$this->title = 'Unregistering';
		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->order_id);

		switch($edit['step']) {
			case 'submit':
				// TODO If this is a team registration, delete the team record

				db_query('DELETE
							FROM
								registration_answers
							WHERE
								order_id = %d',
						$this->order_id);

				db_query('DELETE
							FROM
								registrations
							WHERE
								order_id = %d',
						$this->order_id);
				if ( 1 != db_affected_rows() ) {
					error_exit ( para( theme_error( "There was an error deleting your registration information. Contact the TUC office, quoting order #<b>$order_num</b>, to have the problem resolved." ) ) );
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
		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->order_id);

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
		print <<<HTML_HEADER
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>Toronto Ultimate Club - Online Transaction Result</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="StyleSheet" href="/themes/SeaBreeze/style/tuc.css" type="text/css">
<link rel="stylesheet" type="text/css" href="/leaguerunner/style.css">
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

		$order_num_len = strlen(sprintf(variable_get('order_id_format', '%d'), 0));

		// Check for cancellation
		$cancel = $_GET['cancelTXN'];
		if ($cancel) {
			$long_order_id = $_GET['order_id'];
			$order_id = substr( $long_order_id, 0, $order_num_len );
			print para(theme_error('You cancelled the transaction.'));

			print OfflinePaymentText($order_id);

			print para('Alternately, if you choose not to complete the payment process at this time, you will be able to start the registration process again at a later time and it will pick up where you have left off.');
		}

		else {
			// Retrieve the parameters sent from the server
			$long_order_id = $_GET['response_order_id'];
			$order_id = substr( $long_order_id, 0, $order_num_len );
			$date_stamp = $_GET['date_stamp'];
			$time_stamp = $_GET['time_stamp'];
			$bank_transaction_id = $_GET['bank_transaction_id'];
			$charge_total = $_GET['charge_total'];
			$bank_approval_code = $_GET['bank_approval_code'];
			$response_code = $_GET['response_code'];
			$cardholder = $_GET['cardholder'];
			$expiry = $_GET['expiry_date'];
			$f4l4 = $_GET['f4l4'];
			$card = $_GET['card'];
			$iso_code = $_GET['iso_code'];
			$message = $_GET['message'];
			$trans_name = $_GET['trans_name'];

			// Values specific to INTERAC
			if ($trans_name == 'idebit_purchase')
			{
				$issuer = $_GET['ISSNAME'];
				$issuer_invoice = $_GET['INVOICE'];
				$issuer_confirmation = $_GET['ISSCONF'];
			}
			else
			{
				$issuer = '';
				$issuer_invoice = '';
				$issuer_confirmation = '';
			}

			// TODO: Make the extraction of the short order ID configurable
			$short_order_id = substr($order_id, 1);

			// We can't necessarily rely on the session variable, in the
			// case that the user is signed into tuc.org but the redirect
			// went to www.tuc.org
			$info = db_fetch_object(
						db_query( 'SELECT
										p.firstname,
										p.lastname,
										p.addr_street,
										p.addr_city,
										p.addr_prov,
										p.addr_postalcode,
										e.registration_id,
										e.name,
										e.cost,
										e.gst,
										e.pst
									FROM
										registrations r
									LEFT JOIN
										person p
									ON
										r.user_id = p.user_id
									LEFT JOIN
										registration_events e
									ON
										r.registration_id = e.registration_id
									WHERE
										r.order_id = %d',
									$short_order_id ) );

			// Validate the response code
			if ($response_code < 50 &&
				$bank_transaction_id > 0 )
			{
				$errors = '';

				db_query ('UPDATE
								registrations
							SET
								payment = "Paid"
							WHERE
								order_id = %d',
							$short_order_id);
				if ( 1 != db_affected_rows() ) {
					$errors .= para( theme_error( "Your payment was approved, but there was an error updating your payment status in the database. Contact the TUC office to ensure that your information is updated, quoting order #<b>$order_id</b>, or you may not be allowed to be added to rosters, etc." ) );
				}

				db_query ("INSERT INTO
								registration_audit
							VALUES (
								%d, %d, %d,
								'%s', '%s',
								%s, '%s',
								'%s', %.2f,
								'%s', '%s', '%s', '%s',
								'%s',
								'%s', '%s', '%s'
							)",
						$short_order_id, $response_code, $iso_code,
						$date_stamp, $time_stamp,
						$bank_transaction_id, $bank_approval_code,
						$trans_name, $charge_total,
						$cardholder, $expiry, $f4l4, $card,
						$message,
						$issuer, $issuer_invoice, $issuer_confirmation);
				if ( 1 != db_affected_rows() ) {
					$errors .= para( theme_error( "There was an error updating the audit record in the database. Contact the TUC office to ensure that your information is updated, quoting order #<b>$order_id</b>, or you may not be allowed to be added to rosters, etc." ) );
				}

				$file = variable_get('invoice_implementation', 'invoice');
				include "includes/$file.inc";
				print $errors;
			}

			else {
				print para(theme_error('Your payment was declined. The reason given was:'));
				print para(theme_error($message));

				print OfflinePaymentText($order_id);

				print para('Alternately, you can <a href="/" onClick="close_and_redirect(\'/leaguerunner/event/view/' . $info->registration_id . '\')">start the registration process again</a> and try a different payment option.');
			}
		}

		print para('Click <a href="/" onClick="close_and_redirect(\'/leaguerunner/event/list\')">here</a> to close this window.');

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

	function process ($id)
	{
		global $lr_session;

		$this->title= 'View Registration History';
		$rows = array();

		$result = db_query('SELECT
								e.registration_id,
								e.name,
								r.order_id,
								r.time,
								r.payment
							FROM
								registrations r
							LEFT JOIN
								registration_events e
							ON
								r.registration_id = e.registration_id
							WHERE
								r.user_id = %d
							ORDER BY
								r.time',
							$this->user);
		while($row = db_fetch_array($result)) {
			$name = l($row['name'], 'event/view/' . $row['registration_id']);
			$order = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

			if( $lr_session->has_permission('registration', 'view', $row['order_id']) ) {
				$order = l($order, 'registration/view/' . $row['order_id']);
			}

			$rows[] = array( $name, $order, substr($row['time'], 0, 10), $row['payment'] );
		}

		/* Add in any preregistrations */
		$result = db_query('SELECT
								e.registration_id,
								e.name
							FROM
								preregistrations r
							LEFT JOIN
								registration_events e
							ON
								r.registration_id = e.registration_id
							WHERE
								r.user_id = %d
							ORDER BY
								r.registration_id',
							$this->user);
		while($row = db_fetch_array($result)) {
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
			$areas = array(
				'East' => 'East (any field East of Keele St.)',
				'West' => 'West (any field West of Hwy 404/DVP)',
				'North' => 'North (any field North of Eglinton)',
				'South' => 'South (any field South of Hwy 401)',
				'North East' => 'North East (any field North of Eglinton and East of Keele)',
				'North West' => 'North West (any field North of Eglinton and East of DVP)',
				'South East' => 'South East (any field South of Hwy 401 and East of Keele)',
				'South West' => 'South West (any field South of Hwy 401 and West of DVP)'
			);
			$formbuilder->add_question('__auto__region_preference', 'Region Preference', 'Area of city where you would prefer to play', 'multiplechoice', true, -49, $areas);
			//$formbuilder->add_question('__auto__region_preference', 'Region Preference', 'Area of city where you would prefer to play', 'multiplechoice', true, -49, getOptionsFromEnum('field', 'region'));
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

	$group .= form_radios('Online payments', 'edit[online_payments]', variable_get('online_payments', 1), array('Disabled', 'Enabled'), 'Do we handle online payments?');

	$group_online = form_textfield('Payment provider implementation file', 'edit[payment_implementation]', variable_get('payment_implementation', 'moneris'), 60, 120, 'File will have .inc added, and be looked for in the includes folder.');

	$group_online .= form_textfield('Invoice implementation file', 'edit[invoice_implementation]', variable_get('invoice_implementation', 'invoice'), 60, 120, 'File will have .inc added, and be looked for in the includes folder.');

	$group_online .= form_textfield('Registration ID format string', 'edit[reg_id_format]', variable_get('reg_id_format', 'Reg%05d'), 60, 120, 'sprintf format string for the registration ID, sent to the payment processor as the item number.');

	$group_online .= form_radios('Testing payments', 'edit[test_payments]', variable_get('test_payments', 0), array('Nobody', 'Everybody', 'Admins'), 'Who should get test instead of live payments?');

	$group_online .= form_textfield('Live payment store ID', 'edit[live_store]', variable_get('live_store', ''), 60, 120);
	$group_online .= form_textfield('Live payment password', 'edit[live_password]', variable_get('live_password', ''), 60, 120);

	$group_online .= form_textfield('Test payment store ID', 'edit[test_store]', variable_get('test_store', ''), 60, 120);
	$group_online .= form_textfield('Test payment password', 'edit[test_password]', variable_get('test_password', ''), 60, 120);

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
	$level = arg(2);
	global $TZ_ADJUST;

	if (!$level)
	{
		$result = db_query('SELECT
								r.registration_id,
								name,
								COUNT(*)
							FROM
								registrations r
							LEFT JOIN
								registration_events e
							ON
								r.registration_id = e.registration_id
							WHERE
								r.payment != "Refunded"
							GROUP BY
								r.registration_id
							ORDER BY
								e.open DESC, r.registration_id');
		$rows = array();
		while($row = db_fetch_array($result)) {
			$rows[] = array( l($row['name'], "statistics/registration/summary/${row['registration_id']}"),
							$row['COUNT(*)'] );
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
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
				$result = db_query('SELECT
										p.gender,
										COUNT(order_id)
									FROM
										registrations r
									LEFT JOIN
										person p
									ON
										r.user_id = p.user_id
									WHERE
										r.registration_id = %d
									AND
										r.payment != "Refunded"
									GROUP BY
										p.gender
									ORDER BY
										gender',
									$id);

				$sub_table = array();
				while($row = db_fetch_array($result)) {
					$sub_table[] = $row;
				}
				$rows[] = array("By gender:", table(null, $sub_table));
			}

			$result = db_query('SELECT
									payment,
									COUNT(order_id)
								FROM
									registrations
								WHERE
									registration_id = %d
								GROUP BY
									payment
								ORDER BY
									payment',
								$id);

			$sub_table = array();
			while($row = db_fetch_array($result)) {
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
						$result = db_query('SELECT
												akey,
												COUNT(registration_answers.order_id)
											FROM
												registration_answers
											LEFT JOIN
												registrations
											ON
												registration_answers.order_id = registrations.order_id
											WHERE
												registration_id = %d
											AND
												qkey = "%s"
											AND
												payment != "Refunded"
											GROUP BY
												akey
											ORDER BY
												akey',
											$id,
											$qkey);

						$sub_table = array();
						while($row = db_fetch_array($result)) {
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

				if( $event->anonymous ) {
					$output .= para( l('Download detailed registration list as CSV', "statistics/registration/csv/$id") );
				} else {
					$output .= para( l('See detailed registration list', "statistics/registration/users/$id/1") . ' or ' . l('download detailed registration list as CSV', "statistics/registration/csv/$id") );
				}
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
			if ( $event->anonymous )
			{
				return para( "Cannot view detailed registration list for anonymous event $id" );
			}
			$output = h2('Event: ' .
							l($event->name, "event/view/$id"));

			$items = variable_get('items_per_page', 25);
			if( $items == 0 ) {
				$items = 1000000;
			}
			$from = ($page - 1) * $items;
			$total = db_result( db_query( 'SELECT
												COUNT(order_id)
											FROM
												registrations
											WHERE
												registration_id = %d',
											$id ) );

			if( $from <= $total )
			{
				$result = db_query('SELECT
										order_id,
										DATE_ADD(time, INTERVAL %d MINUTE) as time,
										payment,
										p.user_id,
										p.firstname,
										p.lastname
									FROM
										registrations r
									LEFT JOIN
										person p
									ON
										r.user_id = p.user_id
									WHERE
										r.registration_id = %d
									ORDER BY
										payment,
										order_id
									LIMIT
										%d,%d',
									-$TZ_ADJUST,
									$id,
									$from,
									$items);

				$rows = array();
				while($row = db_fetch_array($result)) {
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

		else if ($level == 'csv')
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

			if( ! $event->anonymous ) {
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
			} else {
				$data = array();
			}

			if( $formbuilder )
			{
				foreach ($formbuilder->_questions as $question)
				{
					$data[] = $question->qkey;
				}
			}

			if( ! $event->anonymous ) {
				$data[] = 'Notes';
			}

			if( empty( $data ) ) {
				return para( 'No details available for download.' );
			}

			// Start the output, let the browser know what type it is
			header('Content-type: text/x-csv');
			header("Content-Disposition: attachment; filename=\"$event->name.csv\"");
			$out = fopen('php://output', 'w');
			fputcsv($out, $data);

			$result = db_query('SELECT
									order_id,
									DATE_ADD(time, INTERVAL %d MINUTE) as time,
									DATE_ADD(modified, INTERVAL %d MINUTE) as modified,
									payment,
									p.*,
									n.pn_email
								FROM
									registrations r
								LEFT JOIN
									person p
								ON
									r.user_id = p.user_id
								LEFT JOIN
									nuke_users n
								ON
									r.user_id = n.pn_uid
								WHERE
									r.registration_id = %d
								ORDER BY
									payment,
									order_id',
								-$TZ_ADJUST,
								-$TZ_ADJUST,
								$id);

			while($row = db_fetch_array($result)) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

				if( ! $event->anonymous ) {
					$data = array( $row['user_id'],
									$row['member_id'],
									$row['firstname'],
									$row['lastname'],
									$row['pn_email'],
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
				} else {
					$data = array();
				}

				// Add all of the answers
				if( $formbuilder )
				{
					foreach ($formbuilder->_questions as $question)
					{
						$data[] = db_result (db_query("SELECT
												akey
											FROM
												registration_answers
											WHERE
												order_id = %d
											AND
												qkey = '%s'",
											$row['order_id'],
											$question->qkey));
					}
				}

				if( ! $event->anonymous ) {
					$data[] = $row['notes'];
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

			$result = db_query('SELECT
									r.order_id,
									r.registration_id,
									r.payment,
									r.modified,
									r.notes,
									e.name,
									p.user_id,
									p.firstname,
									p.lastname
								FROM
									registrations r
								LEFT JOIN
									registration_events e
								ON
									r.registration_id = e.registration_id
								LEFT JOIN
									person p
								ON
									r.user_id = p.user_id
								WHERE
									r.payment = "Unpaid" OR r.payment = "Pending"
								ORDER BY
									r.payment, r.modified');
			$rows = array();
			while($row = db_fetch_array($result)) {
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
