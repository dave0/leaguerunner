{include file='header.tpl' title=$title}
<h1>{$title}</h1>
{$interim_game_result}
{if $spirit_answers}
<p>
The following spirit answers will be shown to your coordinator:
</p>
{$spirit_answers}
{/if}
<p>
If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the problems.
</p>

<form method="post" id='spirit_form'>
{$spirit_form_questions}
{hidden_fields group="edit" fields=$edit_hidden_fields}
{hidden_fields group="spirit" fields=$spirit_hidden_fields}
<input type="hidden" name="edit[step]" value="save" />
<input type="submit" name="submit" value="Submit" />
</form>
{include file='footer.tpl'}
