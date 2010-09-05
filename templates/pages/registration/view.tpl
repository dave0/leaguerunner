{include file=header.tpl}
<h1>{$title}</h1>
<div class='pairtable'><table>
{include file=pages/registration/components/short_view.tpl registrant=$registrant event=$event reg=$reg}
</table></div>
<form method='POST' action="{lr_url path="registration/addpayment/`$reg->order_id`"}">
<fieldset>
  <legend>Payment Details</legend>
  <table>
  <tr>
    <th>Type</th>
    <th>Amount</th>
    <th>By</th>
    <th>Date Paid</th>
    <th>Payment Method</th>
    <th>Entered By</th>
    <th></th>
  </tr>
  {foreach item=payment from=$payment_details}
  <tr>
    <td>{$payment->payment_type}</td>
    <td>${$payment->payment_amount}</td>
    <td>{$payment->paid_by}</td>
    <td>{$payment->date_paid|date_format:"%Y-%m-%d"}</td>
    <td>{$payment->payment_method}</td>
    <td><a href="{lr_url path="person/view/`$payment->entered_by`"}">{$payment->entered_by_name()}</a></td>
    <td><a href="{lr_url path="registration/delpayment/`$reg->order_id`/`$payment->payment_type`"}">[delete]</a></td>
  </tr>
  {/foreach}
  <tr>
    <td>{html_options name="edit[payment_type]" options=$payment_types}</td>
    <td><input type="text" maxlength="8" name="edit[payment_amount]" size="8" /></td>
    <td><input type="text" maxlength="255" name="edit[paid_by]" value="{$registrant->fullname}" size="30" /></td>
    <td><input type="text" maxlength="15" name="edit[date_paid]" size="15" id="datepicker" /></td>
    <td><input type="text" maxlength="255" name="edit[payment_method]" size="20" /></td>
    <td colspan="2">
	<input type="hidden" name="edit[step]" value="confirm" />
	<input type="submit" name="submit" value="Submit" />
    </td>
  </tr>
  </table>
  <div style="font-size: 0.8em">
When adding payments:
<ul>
  <li><b>Type</b> should be the payment type - "Full" should be used for single-payment items.  "Deposit" and "Remaining Balance" should be used for events that require a deposit.
  <li><b>By</b> should be the name of the person submitting the payment.  It's pre-filled to the registrant, but may be changed.
  <li><b>Payment Method</b> should contain the payment method (cash, cheque, email transfer) and any relevant details (such as cheque number, transfer reference number, etc)
</ul>
  </div>
</fieldset>
</form>
<fieldset>
  <legend>Registration Questionnaire</legend>
  {if $formbuilder_render_viewable}
  {$formbuilder_render_viewable}
  {else}
  No questionnaire.
  {/if}
</fieldset>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy-mm-dd'
		});
	});
{/literal}
</script>
{include file=footer.tpl}
