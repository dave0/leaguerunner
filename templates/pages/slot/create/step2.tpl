{include file=header.tpl}
<h1>{$title}</h1>
<form method="POST" id="create">
<input type="hidden" name="edit[step]" value="confirm" />
<div class="form-item">
    <label>First Game Date:</label> {$start_date|date_format:"%A %B %d %Y"}
    <input type="hidden" name="edit[date]" value="{$start_date|date_format:"%Y/%m/%d"}" />
</div>
<div class="form-item">
    <label>Start Time:</label>
    {html_options name="edit[start_time]" options=$start_end_times selected=$start_time}
    <div class="description">Time for games in this timeslot to start</div>
</div>
<div class="form-item">
    <label>Timecap:</label>
    {html_options name="edit[end_time]" options=$start_end_times selected=$end_time}
    <div class="description">Time for games in this timeslot to end.  Choose "---" to assign the default timecap (dark) for that week.</div>
</div>
<div class="form-item">
   <label>Weeks to repeat:</label>
   {html_options name="edit[repeat_for]" options=$repeat_options selected="1"}
   <div class="description">Number of weeks to repeat this gameslot</div>
</div>

<fieldset><legend>Make Gameslot Available To:</legend><div class="form-item">
{html_checkboxes name="edit[availability]" options=$leagues separator="<br />"}
</fieldset>

<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />

</form>
{include file=footer.tpl}
