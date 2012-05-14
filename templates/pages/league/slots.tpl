{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
	var page_date   = {$date};
	var league_days = [ {$league_days} ];

{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy/mm/dd',
			beforeShowDay: function(date) {
				for(key in league_days) {
					if (date.getDay() == league_days[key]) {
						return [true,''];
					}
				}
				return [false, ''];
			}
		});

		$("#datepicker").change(function() {
			$("#dateform").submit();
		});

		$('#slots').dataTable( {
			"sDom": 'lfrtip',
			"bJQueryUI": true,
			"bPaginate": false,
			"bFilter": false,
			"bInfo": false,
			"aoColumns": [
				null,
				{ "sType" : "html" },
				{ "sType" : "html" },
				{ "sType" : "html" },
				{ "sType" : "html" },
				null,
				{ "sType" : "html" },
				{ "sType" : "html" }
			]
		});
	});

{/literal}
</script>

<form method="GET" id="dateform">
    <label>Gameslots for date: <input type="text" maxlength="15" name="date" size="15" value="{$date}" id="datepicker"/></label>
</form>
<p></p>
<p>
There are {$num_fields} fields available for use this week, currently {$num_open} of these are unused.
</p>
<p>
Games where home team was not assigned a preferred field are highlighted.
</p>

<table id="slots">
<thead>
  <tr><th>Slot</th><th>Field</th><th>Game</th><th>Home</th><th>Away</th><th>Field Region</th><th>Home Rank</th><th>Away Rank</th></tr>
</thead>
<tbody>
   {foreach from=$slots item=s}
   <tr {if $s.site_rank && $s.site_rank > 5 }class='region_mismatch'{/if}>
	<td>{$s.slot_id}</td>
	<td><a href="{lr_url path="field/view/`$s.fid`"}">{$s.field_code}{$s.field_num}</a></td>
	{if $s.game_id}
	<td><a href="{lr_url path="game/view/`$s.game_id`"}">{$s.game_id}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->home_id`"}">{$s.game->home_name|truncate:20}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->away_id`"}">{$s.game->away_name|truncate:20}</a></td>
	<td>{$s.field_region}</td>
	<td>{$s.home_site_rank}</td>
	<td>{$s.away_site_rank}</td>
	{else}
	<td>---</td>
	<td>open</td>
	<td>---</td>
	<td>{$s.field_region}</td>
	<td>{$s.home_site_rank}</td>
	<td>{$s.away_site_rank}</td>
	{/if}
   </tr>
   {/foreach}
   <tfoot></tfoot>
</table>
{include file='footer.tpl'}
