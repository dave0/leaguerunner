{include file=header.tpl}
<h1>{$title}</h1>
{if $successful}
You have successfully removed the season {$season->display_name}
{else}
<p>Please confirm that you wish to delete this season:</p>
<div class='pairtable'><table>
{include file=pages/season/components/short_view.tpl}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file=footer.tpl}
