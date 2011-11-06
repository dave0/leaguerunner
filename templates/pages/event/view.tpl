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
<h2>Registration</h2>
<b>You may now:</b>
<ul>
{if $allow_register}
	<li><a href="{lr_url path="event/register/`$event->registration_id`"}" title="Register for {$event->name}">register yourself</a> for this event.
{/if}
{if $allow_unregister}
	<li><a href="{lr_url path="registration/unregister/`$registration->order_id`"}" title="Unregister from {$event->name}">unregister yourself</a> from this event.
{/if}
{if session_perm("registration/register/other")}
	<li><a href="{lr_url path="event/register/`$event->registration_id`/choose"}" title="Register another user for {$event->name}">register another player</a> for this event.
{/if}
</ul>
{if $offline_payment_text}
<h2>Payment</h2>
{if $paypal}
{include file=$paypal}
{/if}
<h3>Offline Payment</h3>
{$offline_payment_text}
{/if}
{if $refund_policy_text}
<h2>Refund Policy</h2>
{$refund_policy_text}
{/if}

{include file=footer.tpl}
