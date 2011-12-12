{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#bookings').dataTable( {
			"bAutoWidth": false,
			"sDom": 'lfrtip',
			"bJQueryUI": true,
			"bPaginate": false,
			"bFilter": false,
			"bInfo": false,
			"aoColumns": [
				null,
				null,
				null,
				{ "sType" : "html" },
				{ bSortable : false }
			]
		});
	});
{/literal}
</script>

<table id="bookings">
<thead>
	<tr>
		<th>Date</th>
		<th>Start Time</th>
		<th>End Time</th>
		<th>Booking</th>
		<th>Actions</th>
	</tr>
</thead>
<tbody>
{foreach from=$slots item=s}
<tr>
  <td>{$s->game_date}</td>
  <td>{$s->game_start}</td>
  <td>{$s->display_game_end()}</td>
  <td>{if $s->game}<a href="{lr_url path="game/view/`$s->game_id`"}">{$s->game->league_name}</a>{/if}</td>
  <td>
  	{if session_perm("gameslot/edit/`$s->slot_id`")}
	<a href="{lr_url path="slot/availability/`$s->slot_id`"}">change avail</a> |
	{/if}
  	{if session_perm("gameslot/delete/`$s->slot_id`")}
	<a href="{lr_url path="slot/delete/`$s->slot_id`"}">delete</a> |
	{/if}
  	{if session_perm("game/reschedule/`$s->slot_id`")}
	<a href="{lr_url path="game/reschedule/`$s->game_id`"}">reschedule</a>
	{/if}
  </td>
</tr>
{/foreach}
</tbody>
<tfoot></tfoot>
</table>
{include file='footer.tpl'}
