<?php

/*
 * Handlers for dealing with registrations
 */
function registration_dispatch()
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'clean':
			$obj = new RegistrationClean;
			break;
		case 'view':
			$obj = new RegistrationView;
			$obj->registration = registration_load( array('order_id' => $id ) );
			break;
		case 'edit':
			$obj = new RegistrationEdit;
			$obj->registration = registration_load( array('order_id' => $id) );
			break;
		case 'refund':
			$obj = new RegistrationRefund;
			$obj->registration = registration_load( array('order_id' => $id) );
			break;
		case 'history':
			$obj = new RegistrationHistory;
			$obj->user = $id;
			break;
		case 'register':
			$obj = new RegistrationRegister;
			$obj->event = event_load( array('registration_id' => $id) );
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
		case 'refund':
			// Only admin can view details or edit
			break;
		case 'register':
		case 'unregister':
			// Only players with completed profiles can register
			return $lr_session->is_complete();
		case 'history':
			// Players with completed profiles can view their own history
			if ($id) {
				return ($lr_session->is_complete() && $lr_session->user->user_id == $id);
			}
			else {
				return ($lr_session->is_complete());
			}
		case 'clean':
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
			//menu_add_child('event','registration/clean','clean test registrations', array('link' => 'registration/clean') );
			menu_add_child('settings', 'settings/registration', 'registration settings', array('link' => 'settings/registration'));
			menu_add_child('statistics','statistics/registration','registration statistics', array('link' => 'statistics/registration') );
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
		if($registration->paid) {
			if ($lr_session->has_permission('registration','refund', $registration->order_id) ) {
				menu_add_child($order_num, "$order_num/refund",'refund registration', array('weight' => 1, 'link' => "registration/refund/$registration->order_id"));
			}
		}
		else {
			if ($lr_session->has_permission('registration','unregister', $registration->order_id) ) {
				menu_add_child($order_num, "$order_num/unregister",'unregister', array('weight' => 1, 'link' => "registration/unregister/$registration->order_id/$registration->registration_id"));
			}
		}
	}
}

class RegistrationClean extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','clean');
	}

	function process ()
	{
		global $lr_session;
		db_query('DELETE
					FROM
						registrations
					WHERE
						registration_id > 24
					AND
						user_id = %d',
				$lr_session->user->user_id);
		$x = db_affected_rows();

		db_query('DELETE
					FROM
						registration_answers
					WHERE
						user_id = %d',
				$lr_session->user->user_id);

		return para("$x of your testing registrations have been removed.");
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

		$userrows = array();
		$userrows[] = array ('Name', $item['firstname'] . ' ' . $item['lastname']);
		$userrows[] = array ('User&nbsp;ID', $this->registration->user_id);
		$userrows[] = array ('Event&nbsp;ID', $this->registration->registration_id);
		$userrows[] = array ('Paid', $this->registration->paid);
		$userrows[] = array ('Notes', $this->registration->notes);
		$output = form_group('Registration details', '<div class="pairtable">' . table(NULL, $userrows) . '</div>');

		// Get registration answers/preferences
		$result = db_query('SELECT
								qkey, akey
							FROM
								registration_answers
							WHERE
								user_id = %d
							AND
								registration_id = %d',
							$this->registration->user_id,
							$this->registration->registration_id);

		$prefrows = array();
		if(0 != db_num_rows($result)) {

			while($row = db_fetch_array($result)) {
				$prefrows[] = $row;
			}
		}
		$output .= form_group('Registration answers', '<div class="pairtable">' . table(NULL, $prefrows) . '</div>');

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

class RegistrationEdit extends Handler
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

		$formkey = 'registration_' . $this->registration->registration_id;

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $formkey, $edit );
				break;
			case 'submit':
				$this->perform( $formkey, $edit );
				local_redirect(url("registration/view/" . $this->registration->order_id));
				break;
			default:
				$edit = object2array($this->registration);
				$rc = $this->generateForm( $formkey, $edit );
		}

		return $rc;
	}

	function generateForm( $formkey, $data = array() )
	{
		$this->title = 'Edit registration';

		$formbuilder = formbuilder_load($formkey);
		// TODO: handle registrations with no associated questions
		if (! $formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

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
		$form .= form_radios('Paid', 'edit[paid]', $this->registration->paid, array('No', 'Yes'));
		$form .= form_textarea('Notes', 'edit[notes]', $this->registration->notes, 45, 5);
		$output .= form_group('Registration details', $form);

		// Get registration answers/preferences
		$result = db_query('SELECT
								qkey, akey
							FROM
								registration_answers
							WHERE
								user_id = %d
							AND
								registration_id = %d',
							$this->registration->user_id,
							$this->registration->registration_id);

		if(0 != db_num_rows($result)) {
			$prefrows = array();

			while($row = db_fetch_array($result)) {
				$prefrows[$row['qkey']] = $row['akey'];
			}
			$formbuilder->bulk_set_answers ($prefrows);
			$output .= form_group('Registration answers', $formbuilder->render_editable (true));
		}
		else {
			$output .= form_group('Registration answers', $formbuilder->render_editable (false));
		}

		$output .= form_submit('Submit') .  form_reset('Reset');

		return form($output);
	}

	function generateConfirm ( $formkey, $edit )
	{
		$this->title = 'Confirm updates';

		$dataInvalid = $this->isDataInvalid( $edit );

		$formbuilder = formbuilder_load($formkey);
		// TODO: handle registrations with no associated questions
		$formbuilder->bulk_set_answers( $_POST[$formkey] );
		$dataInvalid .= $formbuilder->answers_invalid();

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$output = form_hidden('edit[step]', 'submit');

		$rows = array();
		$rows[] = array( 'Paid', form_hidden('edit[paid]', $edit['paid']) . check_form($edit['paid']) );
		$rows[] = array( 'Notes', form_hidden('edit[notes]', $edit['notes']) . check_form($edit['notes']) );
		$output .= form_group('Registration details', "<div class='pairtable'>" . table(null, $rows) . '</div>');

		$form = $formbuilder->render_viewable();
		$form .= $formbuilder->render_hidden();
		$output .= form_group('Registration answers', $form);

		$output .= para('Please confirm that this data is correct and then proceed to arrange payment.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function perform ( $formkey, &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );

		$formbuilder = formbuilder_load($formkey);
		// TODO: handle registrations with no associated questions
		$formbuilder->bulk_set_answers( $_POST[$formkey] );
		$dataInvalid .= $formbuilder->answers_invalid();

		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->registration->set('paid', $edit['paid']);
		$this->registration->set('notes', $edit['notes']);

		if( !$this->registration->save() ) {
			error_exit("Internal error: couldn't save changes to the registration details");
		}

		if( !$this->registration->save_answers( $_POST[$formkey] ) ) {
			error_exit('Error saving registration question answers.');
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		if( !validate_number($edit['paid'] ) ) {
			$errors .= '<li>Invalid paid status: not a number';
		}
		else if( $edit['paid'] < 0 || $edit['paid'] > 1 ) {
			$errors .= '<li>Invalid paid status: must be 0 or 1';
		}

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
 * Refund handler.  Does anything required to update the database.
 * TODO: Handle online credit card refunds?  We just issue cheques right now.
 */
class RegistrationRefund extends Handler
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->registration) {
			error_exit('That registration does not exist');
		}
		return $lr_session->has_permission('registration', 'refund', $this->registration->order_id);
	}

	function process()
	{
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'submit':
				$rc = $this->registration->refund();
				break;

			default:
				$rc = $this->generateConfirm();
		}

		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);
		$this->setLocation(array(
			$order_num => 'registration/view/' .$this->registration->order_id,
			$this->title => 0
		));

		return $rc;
	}

	function generateConfirm()
	{
		$this->title = 'Confirm refund';
		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);

		$output = form_hidden('edit[step]', 'submit');
		$output .= para("Please confirm that you want to issue a refund for order ID $order_num");
		$output .= para(form_submit('submit'));

		return form($output);
	}
}

/**
 * Registration handler
 */
class RegistrationRegister extends Handler
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
		$formkey = 'registration_' . $this->event->registration_id;

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($formkey);
				break;

			case 'submit':
				$rc = $this->generatePay($formkey);
				break;

			default:
				$rc = $this->generateForm($formkey);
		}

		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));
		return $rc;
	}

	function generateForm ($formkey)
	{
		$this->title = 'Preferences';

		$formbuilder = formbuilder_load($formkey);
		// TODO: handle registrations with no associated questions
		if (! $formbuilder )
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

		$output .= $formbuilder->render_editable (false);
		$output .= para(form_submit('submit', 'submit') . form_reset('reset'));

		return form($output);
	}

	function generateConfirm($formkey)
	{
		$this->title = 'Confirm preferences';

		$formbuilder = formbuilder_load($formkey);
		// TODO: handle registrations with no associated questions
		$formbuilder->bulk_set_answers( $_POST[$formkey] );
		$dataInvalid = $formbuilder->answers_invalid();
		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$form = form_hidden('edit[step]', 'submit');
		$form .= $formbuilder->render_viewable();
		$form .= $formbuilder->render_hidden();
		$output = form_group('Preferences', $form);

		$output .= para('Please confirm that this data is correct and then proceed to arrange payment.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generatePay($formkey)
	{
		global $lr_session;
		$this->title = 'Arrange for payment';

		$reg = new Registration;
		$reg->set('user_id', $lr_session->user->user_id);
		$reg->set('registration_id', $this->event->registration_id);

		if (! $reg->save() ) {
			error_exit('Could not create registration record.');
		}

		if( !$reg->save_answers( $_POST[$formkey] ) ) {
			error_exit('Error saving registration question answers.');
		}

		$output = para('Your preferences for this registration have been saved.');

		if( variable_get( 'online_payments', 1 ) )
		{
			$order_num = sprintf(variable_get('order_id_format', '%d'), $reg->order_id);
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
				db_query('DELETE
							FROM
								registration_answers
							WHERE
								user_id = %d
							AND
								registration_id = %d',
						$lr_session->user->user_id,
						arg(3));

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

		// Check for cancellation
		$cancel = $_GET['cancelTXN'];
		if ($cancel) {
			$order_id = $_GET['order_id'];
			print para(theme_error('You cancelled the transaction.'));

			print OfflinePaymentText($order_id);

			print para('Alternately, if you choose not to complete the payment process at this time, you will be able to start the registration process again at a later time and it will pick up where you have left off.');
		}

		else {
			// Retrieve the parameters sent from the server
			$order_id = $_GET['response_order_id'];
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

			// We can't necessarily rely on the session variable, in the
			// case that the user is signed into tuc.org but the redirect
			// went to www.tuc.org
			$short_order_id = substr($order_id, 1);
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
								paid = 1
							WHERE
								order_id = %d',
							$short_order_id);
				if ( 1 != db_affected_rows() ) {
					$errors .= para( theme_error( "Your payment was approved, but there was an error updating your 'paid' status in the database. Contact the TUC office to ensure that your information is updated, quoting order #<b>$order_id</b>, or you may not be allowed to be added to rosters, etc." ) );
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
								r.paid
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

			$rows[] = array( $name, $order, substr($row['time'], 0, 10),
							($row['paid'] ? 'Yes' : 'No') );
		}

		$header = array('Event', 'Order ID', 'Date', 'Paid?');
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

function registration_settings ( )
{
	$group = form_textfield('Order ID format string', 'edit[order_id_format]', variable_get('order_id_format', 'R%09d'), 60, 120, 'sprintf format string for the unique order ID.');

	$group .= form_radios('Online payments', 'edit[online_payments]', variable_get('online_payments', 1), array('Disabled', 'Enabled'), 'Do we handle online payments? (All options below are ignored if this is disabled.)');

	$group .= form_textfield('Payment provider implementation file', 'edit[payment_implementation]', variable_get('payment_implementation', 'moneris'), 60, 120, 'File will have .inc added, and be looked for in the includes folder.');

	$group .= form_textfield('Invoice implementation file', 'edit[invoice_implementation]', variable_get('invoice_implementation', 'invoice'), 60, 120, 'File will have .inc added, and be looked for in the includes folder.');

	$group .= form_textfield('Registration ID format string', 'edit[reg_id_format]', variable_get('reg_id_format', 'Reg%05d'), 60, 120, 'sprintf format string for the registration ID, sent to the payment processor as the item number.');

	$group .= form_radios('Testing payments', 'edit[test_payments]', variable_get('test_payments', 0), array('Nobody', 'Everybody', 'Admins'), 'Who should get test instead of live payments?');

	$group .= form_textfield('Live payment store ID', 'edit[live_store]', variable_get('live_store', ''), 60, 120);
	$group .= form_textfield('Live payment password', 'edit[live_password]', variable_get('live_password', ''), 60, 120);

	$group .= form_textfield('Test payment store ID', 'edit[test_store]', variable_get('test_store', ''), 60, 120);
	$group .= form_textfield('Test payment password', 'edit[test_password]', variable_get('test_password', ''), 60, 120);
 
	$group .= form_textarea('Text of refund policy', 'edit[refund_policy_text]', variable_get('refund_policy_text', ''), 70, 10, 'Customize the text of your refund policy, to be shown on registration pages and invoices.');

	$offline_steps = li('Mail (or personally deliver) a cheque for the appropriate amount to the league office');
	$offline_steps .= li("Ensure that you quote order #<b>%order_num</b> on the cheque in order for your payment to be properly credited.");
	$offline_steps .= li('Also include a note indicating which registration the cheque is for, along with your full name.');
	$offline_steps .= li('If you are paying for multiple registrations with a single cheque, be sure to list all applicable order numbers, registrations and member names.');
	$offline = ul($offline_steps);
	$offline .= para("Please note that online payment registrations are 'live' while offline payments are not.  You will not be registered to the appropriate category that you are paying for until the cheque is received and processed (usually within 1-2 business days of receipt).");
 
	$group .= form_textarea('Text of offline payment directions', 'edit[offline_payment_text]', variable_get('offline_payment_text', $offline), 70, 10, 'Customize the text of your offline payment policy. Available variables are: %order_num');

	$output = form_group("Registration configuration", $group);

	return settings_form($output);
}

function registration_statistics($args)
{
	$level = arg(2);

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
							GROUP BY
								r.registration_id');
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
			$event_name = db_result( db_query( 'SELECT
													name
												FROM
													registration_events
												WHERE
													registration_id = %d',
												$id ) );
			if (! $event_name )
			{
				return para( "Unknown event ID $id" );
			}
			$output = h2('Event: ' .
							l($event_name, "event/view/$id"));
			$rows = array();

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

			$formkey = 'registration_' . $id;
			// TODO: handle registrations with no associated questions
			$formbuilder = formbuilder_load($formkey);
			foreach ($formbuilder->_questions as $question)
			{
				$qkey = $question->qkey;

				// We don't want to see team name or teammate answers here
				if (substr(strtolower($qkey), 0, 4) != 'team')
				{
					$result = db_query("SELECT
											akey,
											COUNT(user_id)
										FROM
											registration_answers
										WHERE
											registration_id = %d
										AND
											qkey = '%s'
										GROUP BY
											akey
										ORDER BY
											akey",
										$id,
										$qkey);

					$sub_table = array();
					while($row = db_fetch_array($result)) {
						$sub_table[] = $row;
					}
					$rows[] = array("$qkey:", table(null, $sub_table));
				}
			}

			$output .= "<div class='pairtable'>" . table(NULL, $rows) . "</div>";

			$output .= para( l('See detailed registration list', "statistics/registration/users/$id/1") . ' or ' . l('download detailed registration list as CSV', "statistics/registration/csv/$id") );

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

			$event_name = db_result( db_query( 'SELECT
													name
												FROM
													registration_events
												WHERE
													registration_id = %d',
												$id ) );
			if (! $event_name )
			{
				return para( "Unknown event ID $id" );
			}
			$output = h2('Event: ' .
							l($event_name, "event/view/$id"));

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
										paid,
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
										order_id
									LIMIT
										%d,%d',
									3 * 60,
									$id,
									$from,
									$items);

				$rows = array();
				while($row = db_fetch_array($result)) {
					$order_id = l(sprintf(variable_get('order_id_format', '%d'), $row['order_id']), 'registration/view/' . $row['order_id']);

					$rows[] = array( $order_id,
									l("${row['firstname']} ${row['lastname']}", "person/view/${row['user_id']}"),
									$row['time'],
									($row['paid'] ? 'Yes' : 'No') );
				}

				$header = array( 'Order ID', 'Player', 'Date/Time', 'Paid?' );
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

			$event_name = db_result( db_query( 'SELECT
													name
												FROM
													registration_events
												WHERE
													registration_id = %d',
												$id ) );
			if (! $event_name )
			{
				return para( "Unknown event ID $id" );
			}
			$formkey = 'registration_' . $id;
			$formbuilder = formbuilder_load($formkey);
			// TODO: handle registrations with no associated questions

			// Start the output, let the browser know what type it is
			header('Content-type: application/octet-stream');
			header("Content-Disposition: attachment; filename=\"$event_name.csv\"");
			print "\n";
			$out = fopen('php://stdout', 'w');

			$data = array( 'User ID',
							'First Name',
							'Last Name',
							'Email address',
							'Gender',
							'Height',
							'Skill Level',
							'Shirt Size',
							'Order ID',
							'Date',
							'Paid' );
			foreach ($formbuilder->_questions as $question)
			{
				$data[] = $question->qkey;
			}
			$data[] = 'Notes';

			// Output the header row
			fputcsv($out, $data);

			$result = db_query('SELECT
									order_id,
									DATE_ADD(time, INTERVAL %d MINUTE) as time,
									paid,
									p.user_id,
									p.firstname,
									p.lastname,
									p.email,
									p.gender,
									p.height,
									p.skill_level,
									p.shirtsize
								FROM
									registrations r
								LEFT JOIN
									person p
								ON
									r.user_id = p.user_id
								WHERE
									r.registration_id = %d
								ORDER BY
									order_id',
								3 * 60,
								$id);

			while($row = db_fetch_array($result)) {
				$order_id = sprintf(variable_get('order_id_format', '%d'), $row['order_id']);

				$data = array( $row['user_id'],
								$row['firstname'],
								$row['lastname'],
								$row['email'],
								$row['gender'],
								$row['height'],
								$row['skill_level'],
								$row['shirtsize'],
								$order_id,
								$row['time'],
								($row['paid'] ? 'Yes' : 'No') );

				// Add all of the answers
				foreach ($formbuilder->_questions as $question)
				{
					$data[] = db_result (db_query("SELECT
											akey
										FROM
											registration_answers
										WHERE
											user_id = '%s'
										AND
											registration_id = %d
										AND
											qkey = '%s'",
										$row['user_id'],
										$id,
										$question->qkey));
				}

				$data[] = $row['notes'];

				// Output the data row
				fputcsv($out, $data);
			}

			fclose($out);

			// Returning would cause the Leaguerunner menus to be added
			exit;
		}

		else
		{
			return para( "Unknown statistics requested: $level" );
		}

	}
}

?>
