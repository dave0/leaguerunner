{include file='header.tpl'}
<h1>{$title}</h1>
<script type="text/javascript">

{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy/mm/dd'
		});

		$("#datepicker").change(function() {
			$("#dateform").submit();
		});
	});
{/literal}
</script>

<form method="POST" id="dateform">
    <label>Games for date: <input type="text" maxlength="15" name="edit[date]" size="15" value="{$date}" id="datepicker"/></label>
</form>
{if $games}
{include file='components/schedule_table.tpl'}
{else}
<p>No games scheduled</p>
{/if}
{include file='footer.tpl'}
