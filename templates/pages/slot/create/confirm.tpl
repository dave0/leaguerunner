
{include file='header.tpl'}
<h1>{$title}</h1>
<form method="POST">
   <input type="hidden" name="edit[step]" value="perform" />
   <p>Please confirm that this information is correct</p>
<div>
    <b>First Game Date</b> {$start_date|date_format:"%A %B %d %Y"}
   	<input type="hidden" name="edit[date]" value="{$start_date|date_format:"%Y/%m/%d"}" />
	<br />

    <b>Start Time</b> {$edit.start_time}
	<input type="hidden" name="edit[start_time]" value="{$edit.start_time}" />
	<br />

    <b>Timecap</b> {$edit.end_time}
	<input type="hidden" name="edit[end_time]" value="{$edit.end_time}" />
	<br />

    <b>Weeks to repeat</b> {$edit.repeat_for}
       <input type="hidden" name="edit[repeat_for]" value="{$edit.repeat_for}" />
       <br />
</div>

<fieldset><legend>Make Gameslot Available To:</legend>
{foreach key=league_id item=league_name from=$leagues}
  {$league_name}<input type="hidden" name="edit[availability][]" value="{$league_id}" /><br />
{/foreach}
</fieldset>

<input type="submit" name="submit" value="submit" />

</form>
{include file='footer.tpl'}
