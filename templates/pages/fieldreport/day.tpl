{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">
	var page_date = {$date};
{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy/mm/dd',
			maxDate:    '+0d'
		});

		$("#datepicker").change(function() {
			$("#dateform").submit();
		});

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
				{ "sType" : "html" },
				{ bSortable : false }
			]
		});
	});

{/literal}
</script>

<form method="GET" id="dateform">
    <label>Reports for date: <input type="text" maxlength="15" name="date" size="15" value="{$date}" id="datepicker"/></label>
</form>
<p></p>

<table id="reports">
<thead>
  <tr><th>Date Played</th><th>Time Reported</th><th>Field</th><th>Game</th><th>Reported By</th><th>Report</th></tr>
</thead>
<tbody>
   {foreach from=$reports item=r}
   <tr>
       <td>{$r->date_played}</td>
       <td>{$r->created}</td>
       <td><a href="{lr_url path="field/view/`$r->field_id`"}">{$r->field->code}{$r->field->num}</a></td>
       <td><a href="{lr_url path="game/view/`$r->game_id`"}">{$r->game_id}</a></td>
       <td><a href="{lr_url path="person/view/`$r->reporting_user_id`"}">{$r->reporting_user_fullname}</a></td>
       <td>{$r->report_text}</td>
   </tr>
   {/foreach}
   <tfoot></tfoot>
</table>
{include file='footer.tpl'}
