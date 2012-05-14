{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully removed note {$note->id}
{else}
<p>Please confirm that you wish to delete this note:</p>
{include file='pages/note/view_inner.tpl'}
<form method="POST">
  <input type="submit" name="submit" value="Delete" />
</form>
{/if}
{include file='footer.tpl'}
