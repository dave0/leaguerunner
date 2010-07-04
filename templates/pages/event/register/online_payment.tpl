{include file=header.tpl}
<h1>{$title}</h1>
<h2>Step 3: Make payment</h2>
<p></p>
<p>
   You are now registered for this event, pending payment of your registration
   fee.  Your registration number is <b>{$order_number}</b>
</p>
{$online_payment_form}
<p>
	Alternately, if you choose not to complete the payment process at this
	time, you will be able to start the registration process again at a
	later time and it will pick up where you have left off.
</p>
<p>
{$offline_payment_text}
</p>
<h2>Refund Policy</h2>
<p>
{$refund_policy_text}
</p>
{include file=footer.tpl}
