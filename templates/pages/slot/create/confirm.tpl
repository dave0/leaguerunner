
{include file=header.tpl}
<h1>{$title}</h1>
<form method="POST">
   <input type="hidden" name="edit[step]" value="perform" />
   <p>Please confirm that this information is correct</p>

   <label>First Game Date:</label> {$start_date|date_format:"%A %B %d %Y"}
   	<input type="hidden" name="edit[date]" value="{$start_date|date_format:"%Y/%m/%d"}" />

    <label>Start Time:</label> {$edit.start_time}
	    <input type="hidden" name="edit[start_time]" value="{$edit.start_time}" />

    <label>Timecap:</label> {$edit.end_time}
	    <input type="hidden" name="edit[end_time]" value="{$edit.end_time}" />

   <label>Weeks to repeat:</label> {$edit.repeat_for}
	   <input type="hidden" name="edit[repeat_for]" value="{$edit.repeat_for}" />

<fieldset><legend>Make Gameslot Available To:</legend>
{foreach key=league_id item=league_name from=$leagues}
  {$league_name}<input type="hidden" name="edit[availability][]" value="{$league_id}" /><br />
{/foreach}
</fieldset>

<input type="submit" name="submit" value="submit" />

</form>
{include file=footer.tpl}
