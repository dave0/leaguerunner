{include file=header.tpl}
<h1>{$title}</h1>
<form method="POST" id="create">
<input type="hidden" name="edit[step]" value="confirm" />

    <label>First Game Date</label> <div class='labeldata'>{$start_date|date_format:"%A %B %d %Y"}</div>
	<input type="hidden" name="edit[date]" value="{$start_date|date_format:"%Y/%m/%d"}" />
	<br />

    <label>Start Time</label>
	{html_options name="edit[start_time]" options=$start_end_times selected=$start_time}
	<div class="description">Time for games in this timeslot to start</div>

    <label>Timecap</label>
    	{html_options name="edit[end_time]" options=$start_end_times selected=$end_time}
    	<div class="description">Time for games in this timeslot to end.  Choose "---" to assign the default timecap (dark) for that week.</div>

   <label>Weeks to repeat</label>
	{html_options name="edit[repeat_for]" options=$repeat_options selected="1"}
	<div class="description">Number of weeks to repeat this gameslot</div>

<fieldset><legend>Make Gameslot Available To:</legend>
{html_checkboxes labels=false name="edit[availability]" options=$leagues separator="<br />"}
</fieldset>

<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />

</form>
{include file=footer.tpl}
