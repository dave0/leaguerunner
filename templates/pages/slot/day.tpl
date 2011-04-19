{include file=header.tpl}
<h1>{$title}</h1>
<script type="text/javascript">
	var page_date   = {$date};
{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy/mm/dd'
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
				null
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
Games where home team was not assigned a preferred  field are highlighted.
</p>

<table id="slots">
<thead>
  <tr><th>Slot</th><th>Field</th><th>Game</th><th>League</th><th>Home</th><th>Away</th><th>Actions</th></tr>
</thead>
<tbody>
   {foreach from=$slots item=s}
   <tr {if $s.site_rank && $s.site_rank > 5}class='region_mismatch'{/if}>
	<td>{$s.slot_id}</td>
	<td><a href="{lr_url path="field/view/`$s.fid`"}">{$s.field_code}{$s.field_num}</a></td>
	{if $s.game_id}
	<td><a href="{lr_url path="game/view/`$s.game_id`"}">{$s.game_id}</a></td>
	<td><a href="{lr_url path="league/view/`$s.game->league_id`"}">{$s.game->league_name}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->home_id`"}">{$s.game->home_name|truncate:20}</a></td>
	<td><a href="{lr_url path="team/view/`$s.game->away_id`"}">{$s.game->away_name|truncate:20}</a></td>
	{else}
	<td>---</td>
	<td>open</td>
	<td>---</td>
	<td>&nbsp;</td>
	{/if}
	<td>
		{if session_perm("gameslot/edit/`$s.slot_id`")}<a href="{lr_url path="slot/availability/`$s.slot_id`"}">change avail</a>{/if}
		{if session_perm("gameslot/delete/`$s.slot_id`")}<a href="{lr_url path="slot/delete/`$s.slot_id`"}">delete</a>{/if}
		{if session_perm("game/reschedule/`$s.game_id`")}<a href="{lr_url path="game/reschedule/`$s.game_id`"}">reschedule/move</a>{/if}
	</td>
   </tr>
   {/foreach}
   <tfoot></tfoot>
</table>
{include file=footer.tpl}
