<form method="POST">
<p>
You may change status to:
</p>
<p>
{foreach from=$states key=value item=text}
  <input type="radio" name="edit[status]" value="{$value}" /> {$text} <br />
{/foreach}
</p>

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="Submit" />
</form>
