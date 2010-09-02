{include file=header.tpl}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#registrations').dataTable( {
			"bAutoWidth": false,
			"sDom": 'lfrtip',
			"bJQueryUI": true,
			"bPaginate": false,
			"bFilter": false,
			"bInfo": false,
			"aoColumns": [
				{ "sType" : "html" },
				{ "sType" : "html" },
				{ "sType" : "date" },
				null
			]
		});
	});
{/literal}
</script>

<table id="registrations">
<thead>
	<tr>
		<th>Event</th>
		<th>Order ID</th>
		<th>Date</th>
		<th>Payment</th>
	</tr>
</thead>
<tbody>
{foreach from=$registrations item=r}
<tr>
  <td><a href="{lr_url path="event/view/`$r.registration_id`"}">{$r.name}</a>
  <td>
  	{if session_perm("registration/view/`$r.order_id`")}
	<a href="{lr_url path="registration/view/`$r.order_id`"}">
	{/if}
  	{$r.order_id|string_format:"`$order_id_format`"}
  	{if session_perm("registration/view/`$r.order_id`")}
	</a>
	{/if}
  </td>
  <td>{$r.time}</td>
  <td>{$r.payment}</td>
</tr>
{/foreach}
</tbody>
<tfoot></tfoot>
</table>
{include file=footer.tpl}
