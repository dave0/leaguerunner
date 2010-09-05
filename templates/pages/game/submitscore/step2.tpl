{include file=header.tpl title=$title}
<h1>{$title}</h1>
{$interim_game_result}
<p>
Since {$league_name} is unable to do daily inspections of all of the fields it
uses, we need your feedback.  Do there appear to be any changes to the field
(damage, water etc) that {$league_name} should be aware of?
</p>
<form method="post" id="field_report_form">

<div class="description">Were there any issues to report?</div>
<input type="radio" name="enable_textarea" value="Yes" /> Yes
<input type="radio" name="enable_textarea" value="No" /> No

<div id="fieldreport"><textarea id="fieldreport_text" wrap="virtual" cols="70" rows="5" name="edit[field_report]"></textarea><div class="description">Please enter a description of any issues, or leave blank if there is nothing to report</div></div>
<p>

{hidden_fields fields=$hidden_fields}
<input type="hidden" name="edit[step]" value="{$next_step}" />
<input type="submit" name="submit" value="Next Step" />
<input type="reset" name="reset" value="reset" />
</form>

<script language="javascript">
{literal}
$(document).ready(function(){
	$("#fieldreport").hide();
});

$('input[type=radio][name=enable_textarea]').click(function(){
	if($(this).val() == 'Yes') {
		$("#fieldreport").show();
	} else {
		$("#fieldreport").hide();
	}
});

$('#field_report_form').submit(function(){
	var want_report = $('input[type=radio][name=enable_textarea]:checked').val();
	if(want_report == 'No') {
		$('textarea#fieldreport_text').val("");
	}
	return true;
});

{/literal}
</script>
{include file=footer.tpl}
