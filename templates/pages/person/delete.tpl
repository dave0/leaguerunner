{include file=header.tpl}
<h1>{$title}</h1>
{if $successful}
You have successfully removed the account for {$person->fullname}
{else}
<p>Please confirm that you wish to delete this account:</p>
<div class='pairtable'><table>
{include file=pages/person/components/short_view.tpl}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file=footer.tpl}
