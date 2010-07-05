{include file=header.tpl}
<h1>{$title}</h1>
<script type="text/javascript">
{literal}
	$(document).ready(function() {
		$('#datepicker').datepicker({
			changeMonth: true,
			dateFormat: 'yy/mm/dd'
		});

		$("#datepicker").change(function() {
			$("#create").submit();
		});
	});
{/literal}
</script>

<form method="POST" id="create">
   <input type="hidden" name="edit[step]" value="details" />
   <p>Select a date to start adding gameslots.</p>
   <label>Date: <input type="text" maxlength="15" name="edit[date]" size="15" value="{$date}" id="datepicker" /></label>
</form>
{include file=footer.tpl}
