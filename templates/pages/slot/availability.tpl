{include file='header.tpl'}
<h2>{$title}</h1>
<p>Availability for {$slot->game_date} {$slot->game_start}</p>
<form method="POST" id="availability">
<input type="hidden" name="edit[step]" value="perform" />
{include file='components/errormessage.tpl' message=$message}
<fieldset><legend>Make Gameslot Available To:</legend>
{html_checkboxes labels=false name="edit[availability]" options=$leagues selected=$current_leagues separator="<br />"}
</fieldset>
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
