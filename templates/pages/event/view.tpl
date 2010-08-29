{include file=header.tpl}
<h1>{$title}</h1>
{if $message}
<blockquote>{$message}</blockquote>
{/if}
<div class="pairtable">
<table>
  {include file=pages/event/components/short_view.tpl}
  {if $event->cap_female == -2}
  <tr>
    <td>Registration capacity:</td>
    <td>{$event->cap_male}</td>
  </tr>
  {else}
  {if $event->cap_male > 0}
  <tr>
    <td>Registration capacity (male):</td>
    <td>{$event->cap_male}</td>
  </tr>
  {/if}
  {if $event->cap_female > 0}
  <tr>
    <td>Registration capacity (female):</td>
    <td>{$event->cap_female}</td>
  </tr>
  {/if}
  {/if}
  <tr>
    <td>Multiple registrations:</td>
    <td>{if $event->multiple}Allowed{else}Not allowed{/if}</td>
  </tr>
  {if $event->anonymous} 
  <tr>
    <td>Survey:</td>
    <td>Results of this event's survey are anonymous.</td>
  </tr>
  {/if}
</table>
</div>
<p></p>
{if $allow_register}
<h2>Registration</h2>
<b>You may <a href="{lr_url path="event/register/`$event->registration_id`"}" title="Register for {$event->name}">Register now!</a></b>
{/if}
{if $allow_unregister}
<h2>Unregistering</h2>
<p>
<b>You may <a href="{lr_url path="registration/unregister/`$registration->order_id`"}" title="Unregister for {$event->name}">Unregister</a> from this event if desired.</b>
</p>
{/if}
{if $offline_payment_text}
<h2>Payment</h2>
{$offline_payment_text}
{/if}
{if $refund_policy_text}
<h2>Refund Policy</h2>
{$refund_policy_text}
{/if}

{include file=footer.tpl}
