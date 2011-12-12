{include file='header.tpl'}
<h1>{$title}</h1>
<h2>Step 3: Arrange for payment</h2>
<p></p>
<p>
   You are now registered for this event, pending arrival of your registration
   fee.  Your registration number is <b>{$order_number}</b>
</p>
{if $paypal}
{include file=$paypal}
{/if}
<p>
{$offline_payment_text}
</p>
<h2>Refund Policy</h2>
<p>
{$refund_policy_text}
</p>
{if $partner_info_text}
<p></p>
<h2>Partner Info</h2>
<p>
{$partner_info_text}
</p>
{/if}
{include file='footer.tpl'}
