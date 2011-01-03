{include file=header.tpl}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
<div class='pairtable'><table>
    <tr><td>Note on:</td><td><a href="{lr_url path="`$note->assoc_type`/view/`$note->assoc_id`"}">{$note->assoc_name()}</a></td></tr>
    <tr><td>Created By:</td><td><a href="{lr_url path="person/view/`$note->creator->user_id`"}">{$note->creator->fullname}</a></td></tr>
    <tr><td>Created On:</td><td>{$note->created}</td></tr>
    <tr><td>Edited On:</td><td>{$note->edited}</td></tr>
</table></div>
    <label for="edit[note]">Note</label>
	<textarea wrap="virtual" cols="70" rows="5" name="edit[note]" ></textarea><div class="description">Your note.  Text only, no HTML</div>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file=footer.tpl}
