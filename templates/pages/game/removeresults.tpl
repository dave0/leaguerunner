{include file=header.tpl}
<h1>{$title}</h1>
<h2>{$game->home_name} vs {$game->away_name}</h2>

<table class='pairtable'>
{include file=pages/game/components/short_view.tpl}
</table>

<p>
You have requested to <b>remove results</b> for this game.  
If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page.
</p>

<form method="POST">
  <input type="hidden" name="step" value="perform" />
  <input type="submit" name="submit" value="Submit" />
  <input type="reset" name="reset" value="reset" />
</form>

{include file=footer.tpl}
