{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully removed the game {$game->name}
{else}
<p>Please confirm that you wish to delete this game:</p>
<div class='pairtable'><table>
{include file='pages/game/components/short_view.tpl'}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file='footer.tpl'}
