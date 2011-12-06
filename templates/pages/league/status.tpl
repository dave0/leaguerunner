{include file='header.tpl'}
<h1>{$title}</h1>
<p>
	This is a general scheduling status report for rating ladder leagues
</p>
<table id="teams">
    <thead>
	<tr>
	    <th rowspan="2">Rating</th>
	    <th rowspan="2">Team</th>
	    <th rowspan="2">Home&nbsp;Pct</th>
	    <th rowspan="2">Want Fld&nbsp;Pct</th>
	    <th colspan="6">Games Played</th>
	    <th rowspan="2">Opponents</th>
	    <th rowspan="2">Repeat Opponents</th>
	</tr>
	<tr>
	    <th>Total</th>
	    <th>Home</th>
	    <th>C</th>
	    <th>E</th>
	    <th>S</th>
	    <th>W</th>
	</tr>
    </thead>
    <tbody>
    {foreach from=$teams item=team}
	<tr>
	    <td>{$team->rating}</td>
	    <td><a href="{lr_url path="team/view/`$team->team_id`}">{$team->name|truncate:35}</a></td>
	    <td {if $team->home_game_ratio_bad}style="color: white; background-color: red; font-weight: bold"{/if}>
		{$team->home_game_ratio}
	    </td>
	    <td {if $team->preferred_ratio_bad}style="color: white; background-color: red; font-weight: bold"{/if}>
		{$team->preferred_ratio}
	    </td>
	    <td>{$team->game_count}</td>
	    <td>{$team->home_game_count}</td>
	    <td>{$team->region_game_counts.Central|default:0}</td>
	    <td>{$team->region_game_counts.East|default:0}</td>
	    <td>{$team->region_game_counts.South|default:0}</td>
	    <td>{$team->region_game_counts.West|default:0}</td>
	    <td>{$team->opponent_counts|@count}</td>
	    <td>
		{foreach from=$team->opponent_counts key=name item=repeats}
		    {if $repeats > 2}
			{$name} (<font color="red"><b>{$repeats}</b></font>)<br />
		    {elseif $repeats > 1}
			{$name} (<b>{$repeats}</b>)<br />
		    {/if}
		{/foreach}
	    </td>
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
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			null,
			{ "sType" : "html" },
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			{ bSortable : false }
		]
	} );
})
{/literal}
</script>
{include file='footer.tpl'}
