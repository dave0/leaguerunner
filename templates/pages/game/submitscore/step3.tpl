{include file='header.tpl' title=$title}
<h1>{$title}</h1>
{$interim_game_result}
<p>
Now you must rate your opponent's Spirit of the Game.
</p>
<p>
Please fill out the questions below.
</p>

<form method="post" id='spirit_form'>
{$spirit_form_questions}
{hidden_fields fields=$hidden_fields}
<input type="hidden" name="edit[step]" value="confirm" />
<input type="submit" name="submit" value="Next Step" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
