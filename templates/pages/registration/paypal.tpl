{include file=header.tpl}
<h1>{$title}</h1>
<p>Payment has been received by PayPal and updated in Leaguerunner.  The transaction is now complete.</p>
<p>You will receive an email from PayPal outlining the details of this transaction.</p>
<fieldset>
  <legend>Payment Details</legend>
  <table>
  <tr>
    <th>Registration ID</th>
    <th>Amount</th>
    <th>Date Paid</th>
    <th>Payment Method</th>
    <th>Entered By</th>
  </tr>
  {foreach item=p from=$payments}
  <tr>
  	<td><a href="{lr_url path="registration/view/`$p->order_id`"}">{$p->order_id|string_format:"`$order_id_format`"}</a></td>
    <td>{$p->payment_amount}</td>
    <td>{$smarty.now|date_format:"%Y-%m-%d"}</td>
    <td>{$p->payment_method}</td>
    <td><a href="{lr_url path="person/view/`$p->entered_by`"}">{$p->entered_by_name()}</a></td>
  </tr>
  {/foreach}
  </table>
</fieldset>
{include file=footer.tpl}