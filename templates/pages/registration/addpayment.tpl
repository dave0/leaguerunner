{include file=header.tpl}
<h1>{$title}</h1>
<p>Please confirm that you wish to add a payment to this registration:</p>
<div class='pairtable'><table>
{include file=pages/registration/components/short_view.tpl registrant=$registrant event=$event reg=$reg}
</table></div>
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
  </tr>
  <tr>
    <td>{$payment->payment_type}</td>
    <td>{$payment->payment_amount}</td>
    <td>{$payment->paid_by}</td>
    <td>{$payment->date_paid|date_format:"%Y/%m/%d"}</td>
    <td>{$payment->payment_method}</td>
    <td><a href="{lr_url path="person/view/`$payment->entered_by`"}">{$payment->entered_by_name()}</a></td>
  </tr>
  </table>
</fieldset>
<form method="POST">
  <input type="hidden" name="edit[payment_method]" value="{$payment->payment_method|escape}" />
  <input type="hidden" name="edit[date_paid]" value="{$payment->date_paid|escape}" />
  <input type="hidden" name="edit[paid_by]" value="{$payment->paid_by|escape}" />
  <input type="hidden" name="edit[payment_amount]" value="{$payment->payment_amount|escape}" />
  <input type="hidden" name="edit[payment_type]" value="{$payment->payment_type|escape}" />
  <input type="hidden" name="edit[step]" value="submit" />
  <input type="submit" name="submit" value="Add Payment" />
</form>
{include file=footer.tpl}
