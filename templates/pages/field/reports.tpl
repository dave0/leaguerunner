{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#reports').dataTable( {
			"bAutoWidth": false,
			"sDom": 'lfrtip',
			"bJQueryUI": true,
			"bPaginate": false,
			"bFilter": false,
			"bInfo": false,
			"aoColumns": [
				{ "sType" : "date" },
				null,
				{ "sType" : "html" },
				{ "sType" : "html" },
				{ bSortable : false }
			]
		});
	});
{/literal}
</script>

<table id="reports">
<thead>
	<tr>
		<th>Date Played</th>
		<th>Time Reported</th>
		<th>Game</th>
		<th>Reported By</th>
		<th>Report</th>
	</tr>
</thead>
<tbody>
{foreach from=$reports item=r}
<tr>
  <td>{$r->date_played}</td>
  <td>{$r->created}</td>
  <td><a href="{lr_url path="game/view/`$r->game_id`"}">{$r->game_id}</a>
  <td><a href="{lr_url path="person/view/`$r->reporting_user_id`"}">{$r->reporting_user_fullname}</a>
  <td>{$r->report_text}</td>
</tr>
{/foreach}
</tbody>
<tfoot></tfoot>
</table>
{include file='footer.tpl'}
