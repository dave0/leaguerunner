{include file=header.tpl}
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
				null
			]
		});
	});

{/literal}
</script>

<form method="GET" id="dateform">
    <label>Reports for date: <input type="text" maxlength="15" name="date" size="15" value="{$date}" id="datepicker"/></label>
</form>
<p></p>
<p>
There are {$num_fields} fields available for use this week, currently {$num_open} of these are unused.
</p>
<p>
Games where home team was not assigned a field in their home region are highlighted.
</p>

<table id="slots">
<thead>
  <tr><th>Slot</th><th>Field</th><th>Game</th><th>Home</th><th>Away</th><th>Home Pref</th><th>Field Region</th></tr>
</thead>
<tbody>
   {foreach from=$slots item=s}
   <tr {if ! $s.is_preferred && $s.home_region_preference && $s.home_region_preference != '---'}class='region_mismatch'{/if}>
	<td>{$s.slot_id}</td>
	<td><a href="{lr_url path="field/view/`$s.fid`"}">{$s.field_code}{$s.field_num}</a></td>
	{if $s.game_id}
	<td><a href="{lr_url path="game/view/`$s.game_id`"}">{$s.game_id}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->home_id`"}">{$s.game->home_name|truncate:20}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->away_id`"}">{$s.game->away_name|truncate:20}</a></td>
	<td>{$s.home_region_preference}</td>
	<td>{$s.field_region}</td>
	{else}
	<td>---</td>
	<td>open</td>
	<td>---</td>
	<td>&nbsp;</td>
	<td>{$s.field_region}</td>
	{/if}
   </tr>
   {/foreach}
   <tfoot></tfoot>
</table>
{include file=footer.tpl}
