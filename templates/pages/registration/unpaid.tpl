{include file=header.tpl}
<h1>{$title}</h1>
<fieldset>
	<legend>Registration List</legend>
	<table id="registrations" style="width: 100%">
  	<thead>
    	<tr>
      	<th>Order ID</th>
				<th>First Name</th>
				<th>Last Name</th>
				<th>Date Registered</th>
				<th>Registration Status</th>
    	</tr>
		</thead>
		<tbody>
		{foreach item=u from=$unpaid}
			<tr>
				<td><a href="{lr_url path="registration/view/`$u.order_id`"}">{$u.order_id|string_format:"`$order_id_format`"}</a></td>
				<td><a href="{lr_url path="person/view/`$u.user_id`}">{$u.firstname}</a></td>
				<td><a href="{lr_url path="person/view/`$u.user_id`}">{$u.lastname}</a></td>
				<td>{$u.modified}</td>
				<td>{$u.payment}</td>
			</tr>
		{/foreach}
		</tbody>
	</table>
</fieldset>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#registrations').dataTable( {
		bFilter: false,
		bJQueryUI: true,
		iDisplayLength: 50,
		sPaginationType: "full_numbers",
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "html" },
			{ "sType" : "html" },
                        null,
                        null
		]
	} );
})
{/literal}
</script>
{include file=footer.tpl}
