{include file=header.tpl}
<h1>{$title}</h1>
<p>Payment has been received by PayPal and updated in Leaguerunner.  The transaction is now complete.</p>
<p>You will receive an email from PayPal outlining the details of this transaction.</p>
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
{include file=footer.tpl}