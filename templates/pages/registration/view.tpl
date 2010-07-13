{include file=header.tpl}
<h1>{$title}</h1>
<div class='pairtable'><table>
{include file=pages/registration/components/short_view.tpl registrant=$registrant event=$event reg=$reg}
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
