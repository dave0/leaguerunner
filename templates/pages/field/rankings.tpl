{include file=header.tpl}
<h1>{$title}</h1>
<p>
	This table shows how the teams below have ranked this site.  Teams not shown have not ranked it.
</p>
<form method="GET">
    {html_options name="season" selected=$current_season_id options=$seasons onChange="form.submit()"}
</form>
<table id="teams">
    <thead>
	<tr>
		<th>Team</th>
		<th>League</th>
		<th>Ranking</th>
	</tr>
    </thead>
    <tbody>
	{foreach from=$teams item=t}
	<tr>
	    <td><a href="{lr_url path="team/view/`$t->team_id`"}">{$t->name}</a></td>
	    <td><a href="{lr_url path="league/view/`$t->league_id`"}">{$t->league_name}</a></td>
	    <td>{$t->rank}</td>
	</tr>
	{/foreach}
    </tbody>
</table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#teams').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 2, "asc" ] ,[ 1, "asc"] , [0, "asc"] ],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "html" },
			{ "sType" : "numeric" }
		]
	} );
});
{/literal}
</script>
{include file=footer.tpl}
