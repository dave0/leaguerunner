{include file='header.tpl'}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
<fieldset>
    <legend>Registration configuration</legend>

	<label for="edit[paypal]">Use PayPal for Registration payment</label>
	{html_radios name="edit[paypal]" options=$enable_disable labels=FALSE}<div class="description">Use PayPal to take credit card payments</div>

	<label for="edit[paypal_url]">Payment Processing</label>
	{html_radios name="edit[paypal_url]" options=$live_sandbox labels=FALSE}<div class="description">Using live for real payments or Sandbox for testing?</div>

	<labe3 for="edit[paypal_sandbox_email]">Sandbox account email address</label>
	<input id="edit[paypal_sandbox_email]" name="edit[paypal_sandbox_email]" maxlength="100" size="50" value="" type="text" /><div class="description">Email address of Sandbox account</div>

	<label for="edit[paypal_sandbox_pdt]">Sandbox PDT Identity Token</label>
	<input id="edit[paypal_sandbox_pdt]" name="edit[paypal_sandbox_pdt]" maxlength="100" size="50" value="" type="text" /><div class="description">Identity Token for Sandbox testing</div>

	<label for="edit[paypal_sandbox_url]">Sandbox URL</label>
	<input id="edit[paypal_sandbox_url]" name="edit[paypal_sandbox_url]" maxlength="100" size="50" value="" type="text" /><div class="description">URL for testing PayPal payments</div>

	<label for="edit[paypal_live_email]">Live account primary email address</label>
	<input id="edit[paypal_live_email]" name="edit[paypal_live_email]" maxlength="100" size="50" value="" type="text" /><div class="description">Email address of Paypal account handling payments</div>

	<label for="edit[paypal_live_pdt]">Sandbox PDT Identity Token</label>
	<input id="edit[paypal_live_pdt]" name="edit[paypal_live_pdt]" maxlength="100" size="50" value="" type="text" /><div class="description">Identity Token PayPal uses to return information regarding the transaction</div>

	<label for="edit[paypal_live_url]">Live URL</label>
	<input id="edit[paypal_live_url]" name="edit[paypal_live_url]" maxlength="100" size="50" value="" type="text" /><div class="description">Standard URL for submitting queries to the PayPal system</div>

	<label for="edit[order_id_format]">Order ID format string</label>
	<input id="edit[order_id_format]" name="edit[order_id_format]" maxlength="120" size="50" value="" type="text" /><div class="description">sprintf format string for the unique order ID.</div>

	<label for="edit[refund_policy_text]">Text of refund policy</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[refund_policy_text]" ></textarea><div class="description">Customize the text of your refund policy, to be shown on registration pages and invoices.</div>

	<label for="edit[offline_payment_text]">Text of offline payment directions</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[offline_payment_text]" ></textarea><div class="description">Customize the text of your offline payment policy. Available variables are: %order_num</div>

	<label for="edit[partner_info_text]">Text for "Partner Info" section</label>
	<textarea wrap="virtual" cols="60" rows="10" name="edit[partner_info_text]" ></textarea><div class="description">Customize the text for the "Partner Info" section of the registration results.</div>
</fieldset>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
