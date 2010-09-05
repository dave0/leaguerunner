{include file=header.tpl}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
    <label for="edit[name]">Team Name</label>
	<input type="text" maxlength="200" class="form-text" name="edit[name]" size="35" value="" /><div class="description">The full name of your team.  Text only, no HTML</div>

    <label for="edit[website]">Website</label>
	<input id="edit[website]" type="text" maxlength="200" class="form-text" name="edit[website]" size="35" value="" /><div class="description">Your team's website (optional)</div>

    <label for="edit[shirt_colour]">Shirt Colour</label>
	<input type="text" maxlength="200" class="form-text" name="edit[shirt_colour]" size="35" value="" /><div class="description">Shirt colour of your team.  If you don't have team shirts, pick 'light' or 'dark'</div>

{if session_perm("team/edit/`$team->team_id`")}
    <label for="edit[home_field]">Home Field</label>
    	<input type="text" maxlength="3" class="form-text" name="edit[home_field]" size="3" value="" /><div class="description">Home field, if you happen to have one</div>
{/if}

    <label for="edit[region_preference]">Region</label>
        {html_options name="edit[region_preference]" options=$regions}<div class="description">Area of city where you would prefer to play</div>

    <label for="edit[status]">Team Status</label>
    	{html_options name="edit[status]" options=$status}<div class="description">Is your team open (others can join) or closed (only captain can add players)</div>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" class="form-submit" name="submit" value="submit" />
<input type="reset" class="form-reset" name="reset" value="reset" />
</form>
{include file=footer.tpl}
