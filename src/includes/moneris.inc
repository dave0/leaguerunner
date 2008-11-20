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

	$online .= para('If you want to pay online with Visa, MasterCard or INTERAC&reg; Online, ' . form_submit('click here', 'submit', 'onClick="open_payment_window();"') . ' to proceed to the secure online payment form (which will open in a new window).');

	if ($test_payments) {
		$output .= form($online, 'post', 'https://esqa.moneris.com/HPPDP/index.php', 'target="payment_window" name="payment_form"');
	}
	else {
		$output .= form($online, 'post', 'https://www3.moneris.com/HPPDP/index.php', 'target="payment_window" name="payment_form"');
	}

	$output .= para( '&reg; Trade-mark of Interac Inc. Used under licence. <a href="http://www.interaconline.com/learn/" target="_blank">Learn more</a> about INTERAC Online.', array( 'class' => 'small' ) );

	return $output;
}

?>
