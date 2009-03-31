<?php
/*
 * Code for dealing with Chase online payment.
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
	$time = time();
	$unique_order_num = $order_num . sprintf('-%010d', $time);

	$currency = variable_get('payment_currency', 'CAD');
	$amount = sprintf('%.2f', $event->cost + $event->gst + $event->pst);

	// Build the online payment form
	if ($test_payments) {
		$login = variable_get('test_store', '');
		$key = variable_get('test_password', '');
		$test = 'TRUE';
	}
	else {
		$login = variable_get('live_store', '');
		$key = variable_get('live_password', '');
		$test = 'FALSE';
	}
	$online = form_hidden('x_login', $login);
	$online .= form_hidden('x_test_request', $test);
	$online .= form_hidden('x_fp_sequence', $unique_order_num);
	$online .= form_hidden('x_fp_timestamp', $time);

	$hash_source = implode ('^', array (
			$login,
			$unique_order_num,
			$time,
			$amount,
			$currency,
	));
	$online .= form_hidden('x_fp_hash', hmac($key, $hash_source));
	$online .= form_hidden('x_show_form', 'PAYMENT_FORM');
	$online .= form_hidden('x_type', 'AUTH_CAPTURE');
	$online .= form_hidden('x_receipt_link_method', 'GET');
	$online .= form_hidden('x_relay_response', 'TRUE');

	$join = '<|>';
	$online .= form_hidden('x_line_item', implode ($join, array(
			sprintf(variable_get('reg_id_format', '%d'), $event->registration_id),
			$event->name,
			$event->name,
			'1',
			$event->cost,
			($event->gst + $event->pst > 0) ? 'YES' : 'NO',
	)) . $join);

	$online .= form_hidden('x_cust_id', $lr_session->user->user_id);
	$online .= form_hidden('x_email', $lr_session->user->email);
	$online .= form_hidden('x_invoice_num', $order_num);
	$online .= form_hidden('x_currency_code', $currency);
	$online .= form_hidden('x_amount', $amount);
	if ($event->gst + $event->pst > 0) {
		$online .= form_hidden('x_tax', $event->gst + $event->pst);
	}
	$online .= form_hidden('x_first_name', $lr_session->user->firstname);
	$online .= form_hidden('x_last_name', $lr_session->user->lastname);
	$online .= form_hidden('x_address', $lr_session->user->addr_street);
	$online .= form_hidden('x_city', $lr_session->user->addr_city);
	$online .= form_hidden('x_state', $lr_session->user->addr_prov);
	$online .= form_hidden('x_zip', $lr_session->user->addr_postalcode);
	$online .= form_hidden('x_country', $lr_session->user->addr_country);
	$online .= form_hidden('x_phone', $lr_session->user->home_phone);

	$online .= para('<span class="highlight">Payment completes your registration and confirms your booking/purchase.</span> If you want to pay online with Visa, MasterCard or INTERAC&reg; Online, ' . form_submit('click here', 'submit', 'onClick="open_payment_window();"') . ' to proceed to the secure online payment form (which will open in a new window).');

	$output .= form($online, 'post', 'https://checkout.e-xact.com/payment', 'target="payment_window" name="payment_form"');

	$output .= para( '&reg; Trade-mark of Interac Inc. Used under licence. <a href="http://www.interaconline.com/learn/" target="_blank">Learn more</a> about INTERAC Online.', array( 'class' => 'small' ) );

	return $output;
}

function handlePaymentResponse()
{
	$order_num_len = strlen(sprintf(variable_get('order_id_format', '%d'), 0));

	global $dbh;

	// Retrieve the parameters sent from the server
	$order_id = $_POST['Reference_No'];
	$bank_transaction_id = $_POST['x_trans_id'];
	$charge_total = $_POST['x_amount'];
	$bank_approval_code = $_POST['x_auth_code'];
	$response_code = $_POST['Bank_Resp_Code'];
	$cardholder = $_POST['CardHoldersName'];
	$expiry = $_POST['Expiry_Date'];
	$f4l4 = $_POST['Card_Number'];
	$card = $_POST['TransactionCardType'];
	$iso_code = $_POST['x_response_code'];
	$message = $_POST['Bank_Message'];
	$hash = $_POST['x_MD5_Hash'];

	// TODO: no better way to get these from the response?
	if (stripos ($card, 'interac') === false)
		$trans_name = 'purchase';
	else
		$trans_name = 'idebit_purchase';
	preg_match ('#DATE/TIME   : (\d+ [a-z]{3} \d+) (\d+:\d+:\d+)#im', $_POST['exact_ctr'], $matches);
	$date_stamp = $matches[1];
	$time_stamp = $matches[2];

	// Validate the hash
	if ($_POST['x_test_request'] == 'TRUE') {
		$login = variable_get('test_store', '');
		$key = variable_get('test_password', '');
	} else {
		$login = variable_get('live_store', '');
		$key = variable_get('live_password', '');
	}
	$calculated_hash = md5("$key$login$bank_transaction_id$charge_total");

	// Values specific to INTERAC
	if ($trans_name == 'idebit_purchase')
	{
		$issuer = $_POST['exact_issname'];
		$issuer_invoice = $_POST['x_invoice_num'];
		$issuer_confirmation = $_POST['exact_issconf'];
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
	if ($iso_code == 1 && $hash == $calculated_hash)
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

if (function_exists ('mhash'))
{
	// Use mhash function to compute the hash.
	function hmac($key, $data) {
		return (bin2hex (mhash(MHASH_MD5, $data, $key)));
	}

} else {

	function hmac($key, $data) {
		// RFC 2104 HMAC implementation for php to compute the MD5 HMAC.
		// Credit: Lance Rushing - http://www.php.net/manual/en/function.mhash.php

		$b = 64; // byte length for MD5
		if (strlen($key) > $b) {
		 $key = pack("H*",md5($key));
		}
		$key  = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad ;
		$k_opad = $key ^ $opad;

		return md5($k_opad  . pack("H*",md5($k_ipad . $data)));
	}

}

?>
