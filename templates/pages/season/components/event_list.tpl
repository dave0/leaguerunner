    <table id="events">
        <thead>
           <tr>
                <th>Name</th>
		<th>Type</th>
                <th>Price</th>
                <th>Open</th>
                <th>Close</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$events item=i}
            <tr>
                <td><a href="{lr_url path="event/view/`$i->registration_id`"}">{$i->name}</a></td>
                <td>{$i->type}</td>
                <td>${$i->total_cost()}</td>
                <td>{$i->open}</td>
                <td>{$i->close}</td>
            </tr>
        {/foreach}
	</tbody>
    </table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#events').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 3, "asc" ]],
		aoColumns: [
			{ "sType" : "html" },
			null,
			null,
			null,
			null,
		]
	} );
})
{/literal}
</script>
