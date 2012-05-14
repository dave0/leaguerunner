{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully invalidated the email address for {$person->fullname}
{else}
<p>Please confirm that you wish to invalidate the email address for:</p>
<div class='pairtable'><table>
{include file='pages/person/components/short_view.tpl'}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Submit" />
</form>
{/if}
{include file='footer.tpl'}
