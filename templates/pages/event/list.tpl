{include file=header.tpl}
<h1>{$title}</h1>
<p>Please be sure to read all information carefully and complete all
preferences when registering.</p>
<table>
<tr>
  <th>Registration</th>
  <th>Cost</th>
  <th>Opens on</th>
  <th>Closes on</th>
</tr>
{assign var='last_type' value=''}
{foreach from=$events item=e}
{if $e->type != $last_type}
<tr><td colspan="4"><h2>{$e->full_type}</h2></td></tr>
{assign var='last_type' value=$e->type}
{/if}
<tr>
  <td><a href="{lr_url path="event/view/`$e->registration_id`"}" title="View event details">{$e->name}</a></td>
  <td>${$e->total_cost()}</td>
  <td>{$e->open}</td>
  <td>{$e->close}</td>
</tr>
{/foreach}
</table>
{include file=footer.tpl}
