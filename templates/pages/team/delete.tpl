{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully removed the team {$team->fullname}
{else}
<p>Please confirm that you wish to delete this team:</p>
<div class='pairtable'><table>
{include file='pages/team/components/short_view.tpl'}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file='footer.tpl'}
