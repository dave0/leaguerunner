{include file=header.tpl}
<h1>{$title}</h1>
<div class='pairtable'><table>
  <tr>
    <td>Name:</td>
    <td><a href="{lr_url path="person/view/`$registrant->user_id`"}">{$registrant->fullname}</a></td>
  </tr>
  <tr>
    <td>Member ID:</td>
    <td>{$registrant->member_id}</td>
  </tr>
  <tr>
    <td>Event:</td>
    <td><a href="{lr_url path="event/view/`$event->registration_id`"}">{$event->name}</a></td>
  </tr>
  <tr>
    <td>Created On:</td>
    <td>{$reg->time}</td>
  </tr>
  <tr>
    <td>Last Modified On:</td>
    <td>{$reg->modified}</td>
  </tr>
  <tr>
    <td>Registered Price:</td>
    <td>{$reg->total_amount}</td>
  </tr>
  <tr>
    <td>Payment Status:</td>
    <td>{$reg->payment}</td>
  </tr>
  <tr>
    <td>Payment Amount:</td>
    <td>{$reg->paid_amount}</td>
  </tr>
  <tr>
    <td>Payment Method:</td>
    <td>{$reg->payment_method}</td>
  </tr>
  <tr>
    <td>Payment Date:</td>
    <td>{$reg->date_paid}</td>
  </tr>
  <tr>
    <td>Paid By (if different):</td>
    <td>{$reg->paid_by}</td>
  </tr>
  <tr>
    <td>Notes:</td>
    <td>{$reg->notes}</td>
  </tr>
</table></div>
<fieldset>
  <legend>Registration Questionnaire</legend>
  {if $formbuilder_render_viewable}
  {$formbuilder_render_viewable}
  {else}
  No questionnaire.
  {/if}
</fieldset>
{if $payment_details}
<fieldset>
  <legend>Payment Details</legend>
  {foreach item=payment from=$payment_details}
  {/foreach}
</fieldset>
{/if}
{include file=footer.tpl}
