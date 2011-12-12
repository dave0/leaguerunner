{include file='header.tpl'}
<h1>{$title}</h1>
{if $successful}
You have successfully removed registration {$reg->formatted_order_id()} for {$event->name}
{else}
<p>Please confirm that you wish to remove this registration:</p>
<div class='pairtable'><table>
{include file='pages/registration/components/short_view.tpl' registrant=$registrant event=$event reg=$reg}
</table></div>
<form method="POST">
  <input type="submit" name="submit" value="Unregister" />
</form>
{/if}
{include file='footer.tpl'}
