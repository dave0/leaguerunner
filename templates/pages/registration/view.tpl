{include file=header.tpl}
<h1>{$title}</h1>
<div class='pairtable'><table>
{include file=pages/registration/components/short_view.tpl registrant=$registrant event=$event reg=$reg}
</table></div>
{if $payment_details}
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
  {foreach item=payment from=$payment_details}
  <tr>
    <td>{$payment->payment_type}</td>
    <td>{$payment->payment_amount}</td>
    <td>{$payment->paid_by}</td>
    <td>{$payment->date_paid}</td>
    <td>{$payment->payment_method}</td>
    <td><a href="{lr_url path="person/view/`$payment->entered_by`"}">{$payment->entered_by_name()}</a></td>
  </tr>
  {/foreach}
  </table>
</fieldset>
{/if}
<fieldset>
  <legend>Registration Questionnaire</legend>
  {if $formbuilder_render_viewable}
  {$formbuilder_render_viewable}
  {else}
  No questionnaire.
  {/if}
</fieldset>
{include file=footer.tpl}
