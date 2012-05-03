<form method="POST">
<p>
You may change status to:
</p>
<p>
{foreach from=$states key=value item=text}
  <input type="radio" name="edit[status]" value="{$value}" {$disabled} /> {$text} <br />
{/foreach}
</p>
{if $prerequisites}
<p class="error">You have not registered for all the necessary events to be eligible to play in this League.
Please register for the following events:
<ul>
	{foreach item=text key=value from=$prerequisites}
	<li><a href="{lr_url path="event/view/`$value`"}">{$text}</a></li>
	{/foreach}
</ul>
</p>
{/if}
<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="Submit" {$disabled} />
</form>
