{include file='header.tpl'}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}

<label for="edit[display_name]">Display Name</label>
	<input id="edit[display_name]" type="text" maxlength="200" name="edit[display_name]" size="35" value="" />
	<div class="description">The name to use for this season</div>

<label for="edit[year]">Year</label>
	<input id="edit[year]" type="text" maxlength="4" name="edit[year]" size="4" value="{$smarty.now|date_format:"%Y"}" />
	<div class="description">Year of play.</div>

<label for="edit[season]">Season</label>
	{html_options name="edit[season]" options=$seasons}<div class="description">Season of play for this league. Choose 'none' for administrative groupings and comp teams.</div>

<label for="edit[archived]">Archive this season</label>
	{html_radios name="edit[archived]" options=$yes_no}
	<div class="description">If this season is closed -- no more games to play and no further registrations to be performed -- it should be archived.  If there is any doubt, wait until you're sure because archiving cannot be undone simply by changing this setting.</div>

{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
