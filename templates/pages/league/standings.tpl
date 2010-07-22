{include file=header.tpl}
<h1>{$title}</h1>
<table id="teams">
    <thead>
	<tr>
	    <th rowspan="2">Seed</th>
	    <th rowspan="2">Team</th>
	    <th rowspan="2">Rating</th>
	    {if $display_round}
	    <th colspan="7">Current Round</th>
	    {/if}
	    <th colspan="7">Season To Date</th>
	    <th rowspan="2">Stk</th>
	    <th rowspan="2">SOTG</th>
	    {if $league->display_numeric_sotg()}
	    <th rowspan="2">(raw)</th>
	    {/if}
	</tr>
	<tr>
	    {if $display_round}
	    <th>W</th>
	    <th>L</th>
	    <th>T</th>
	    <th>Dfl</th>
	    <th>PF</th>
	    <th>PA</th>
	    <th>+/-</th>
	    {/if}
	    <th>W</th>
	    <th>L</th>
	    <th>T</th>
	    <th>Dfl</th>
	    <th>PF</th>
	    <th>PA</th>
	    <th>+/-</th>
	</tr>
    </thead>
    <tbody>
    {foreach from=$teams item=team}
	<tr{if $team->team_id == $highlight_team} style="background-color:lightgreen; font-weight:bold; font-size:1.1em"{/if}>
	    <td>{$team->seed}</td>
	    <td><a href="{lr_url path="team/view/`$team->team_id`}">{$team->name|truncate:25}</a></td>
	    <td>{$team->rating}</td>
	    {if $display_round}
	    <td>{$team->round_win}</td>
	    <td>{$team->round_loss}</td>
	    <td>{$team->round_tie}</td>
	    <td>{$team->round_defaults_against}</td>
	    <td>{$team->round_points_for}</td>
	    <td>{$team->round_points_against}</td>
	    <td>{$team->round_points_for - $team->round_points_against}</td>
	    {/if}
	    <td>{$team->win}</td>
	    <td>{$team->loss}</td>
	    <td>{$team->tie}</td>
	    <td>{$team->defaults_against}</td>
	    <td>{$team->points_for}</td>
	    <td>{$team->points_against}</td>
	    <td>{$team->points_for - $team->points_against}</td>
	    <td>{$team->display_streak}</td>
	    <td>{$team->sotg_image}</td>
	    {if $league->display_numeric_sotg()}
	    <td>{$team->sotg_average|string_format:"%.2f"}</td>
	    {/if}
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
{/literal}
	    		{if $display_round}
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			{/if}
{literal}
			{ bSortable : false }
{/literal}
			{if $league->display_numeric_sotg()}
			, null
			{/if}
{literal}
		]
	} );
})
{/literal}
</script>
{include file=footer.tpl}
