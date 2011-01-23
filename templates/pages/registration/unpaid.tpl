{include file=header.tpl}
<h1>{$title}</h1>
<table>
  <thead>
    <tr>
      <th>Order#</th>
      <th>Player</th>
      <th>Last Modified</th>
      <th>Registration Status</th>
    </tr>
  </thead>
{foreach item=u from=$unpaid}
<tr>
  <td><a href="{lr_url path="registration/view/`$u.order_id`"}">{$u.order_id|string_format:"`$order_id_format`"}</a></td>
  <td><a href="{lr_url path="person/view/`$u.user_id`}">{$u.firstname} {$u.lastname}</a></td>
  <td>{$u.modified}</td>
  <td>{$u.payment}</td>
</tr>
{if $u.notes}
<tr>
  <td></td>
  <td colspan="5">{$u.notes}</td>
</tr>
{/if}
{/foreach}
<table>
{include file=footer.tpl}
