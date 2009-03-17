<?php
/*
 * Code for dealing with Moneris online payment.
 */

function generatePayForm ($event, $order_num)
{
	global $lr_session;

	$test_config = variable_get('test_payments', 0);
	switch ($test_config)
	{
		case 1:
			$test_payments = true;
			break;

		case 2:
			$test_payments = $lr_session->is_admin();
			break;

		default:
			$test_payments = false;
			break;
	}

	// JavaScript for no address bar
	$output = <<<SCRIPT

<script language="JavaScript" type="text/javascript">
<!--
	function open_payment_window()
	{
		window.open("", "payment_window", "menubar=1,toolbar=1,scrollbars=1,resizable=1,status=1,location=0");
		var a = window.setTimeout("document.payment_form.submit();", 500);
	}
-->
</script>

SCRIPT;

	// Generate a unique order id
	$unique_order_num = $order_num . sprintf('-%010d', time());

	// Build the online payment form
	if ($test_payments) {
		$online = form_hidden('ps_store_id', variable_get('test_store', ''));
		$online .= form_hidden('hpp_key', variable_get('test_password', ''));
	}
	else {
		$online = form_hidden('ps_store_id', variable_get('live_store', ''));
		$online .= form_hidden('hpp_key', variable_get('live_password', ''));
	}
	$online .= form_hidden('charge_total', sprintf('%.2f', $event->cost + $event->gst + $event->pst));
	$online .= form_hidden('id1', sprintf(variable_get('reg_id_format', '%d'), $event->registration_id));
	$online .= form_hidden('description1', $event->name);
	$online .= form_hidden('quantity1', '1');
	$online .= form_hidden('price1', $event->cost);
	$online .= form_hidden('subtotal1', $event->cost);
	$online .= form_hidden('cust_id', $lr_session->user->user_id);
	$online .= form_hidden('email', $lr_session->user->email);
	$online .= form_hidden('order_id', $unique_order_num);
	$online .= form_hidden('lang', 'en-ca');
	if ($event->gst > 0) {
		$online .= form_hidden('gst', $event->gst);
	}
	if ($event->pst > 0) {
		$online .= form_hidden('pst', $event->pst);
	}
	$online .= form_hidden('bill_first_name', $lr_session->user->firstname);
	$online .= form_hidden('bill_last_name', $lr_session->user->lastname);
	$online .= form_hidden('bill_address_one', $lr_session->user->addr_street);
	$online .= form_hidden('bill_city', $lr_session->user->addr_city);
	$online .= form_hidden('bill_state_or_province', $lr_session->user->addr_prov);
	$online .= form_hidden('bill_postal_code', $lr_session->user->addr_postalcode);
	$online .= form_hidden('bill_country', $lr_session->user->addr_country);
	$online .= form_hidden('bill_phone', $lr_session->user->home_phone);

	$online .= para('<span class="highlight">Payment completes your registration and confirms your booking/purchase.</span> If you want to pay online with Visa, MasterCard or INTERAC&reg; Online, ' . form_submit('click here', 'submit', 'onClick="open_payment_window();"') . ' to proceed to the secure online payment form (which will open in a new window).');

	if ($test_payments) {
		$output .= form($online, 'post', 'https://esqa.moneris.com/HPPDP/index.php', 'target="payment_window" name="payment_form"');
	}
	else {
		$output .= form($online, 'post', 'https://www3.moneris.com/HPPDP/index.php', 'target="payment_window" name="payment_form"');
	}

	$output .= para( '&reg; Trade-mark of Interac Inc. Used under licence. <a href="http://www.interaconline.com/learn/" target="_blank">Learn more</a> about INTERAC Online.', array( 'class' => 'small' ) );

	return $output;
}

function handlePaymentResponse()
{
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
		global $dbh;

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
		$sth = $dbh->prepare('SELECT
				p.firstname, p.lastname, p.addr_street, p.addr_city,
				p.addr_prov, p.addr_country, p.addr_postalcode,
				e.registration_id, e.name, e.cost, e.gst, e.pst
			FROM
				registrations r
				LEFT JOIN person p ON r.user_id = p.user_id
				LEFT JOIN registration_events e ON r.registration_id = e.registration_id
			WHERE r.order_id = ?');
		$sth->execute( array( $short_order_id ) );
		$info = $sth->fetch( PDO::FETCH_OBJ );

		// Validate the response code
		if ($response_code < 50 &&
			$bank_transaction_id > 0 )
		{
			$errors = '';

			$sth = $dbh->prepare("UPDATE registrations
					SET payment = 'Paid'
					WHERE order_id = ?");
			$sth->execute( array($short_order_id) );
			if ( 1 != $sth->rowCount() ) {
				$errors .= para( theme_error( "Your payment was approved, but there was an error updating your payment status in the database. Contact the office to ensure that your information is updated, quoting order #<b>$order_id</b>, or you may not be allowed to be added to rosters, etc." ) );
			}

			// TODO: Specify explicit column names
			$sth = $dbh->prepare('INSERT INTO
							registration_audit
						VALUES ( ?, ?, ?,
							?, ?,
							?, ?,
							?, ?,
							?, ?, ?, ?, 
							?, 
							?, ?, ?)');
			$sth->execute( array(
					$short_order_id, $response_code, $iso_code,
					$date_stamp, $time_stamp,
					$bank_transaction_id, $bank_approval_code,
					$trans_name, $charge_total,
					$cardholder, $expiry, $f4l4, $card,
					$message,
					$issuer, $issuer_invoice, $issuer_confirmation)
			);
			if ( 1 != $sth->rowCount() ) {
				$errors .= para( theme_error( "There was an error updating the audit record in the database. Contact the office to ensure that your information is updated, quoting order #<b>$order_id</b>, or you may not be allowed to be added to rosters, etc." ) );
			}

			$file = variable_get('invoice_implementation', 'invoice');
			include "includes/$file.inc";
			print $errors;
		}

		else {
			print para(theme_error('Your payment was declined. The reason given was:'));
			print para(theme_error($message));

			print OfflinePaymentText($order_id);

			print para("Alternately, you can <a href=\"/\" onClick=\"close_and_redirect('$BASE_URL/event/view/{$info->registration_id}')\">start the registration process again</a> and try a different payment option.");
		}
	}
}

?>
