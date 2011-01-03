<div class='pairtable'><table>
    <tr><td>Note on:</td><td><a href="{lr_url path="`$note->assoc_type`/view/`$note->assoc_id`"}">{$note->assoc_name()}</a></td></tr>
    <tr><td>Created By:</td><td><a href="{lr_url path="person/view/`$note->creator->user_id`"}">{$note->creator->fullname}</a></td></tr>
    <tr><td>Created On:</td><td>{$note->created}</td></tr>
    <tr><td>Edited On:</td><td>{$note->edited}</td></tr>
    <tr><td>Note:</td><td>{$note->note}</td></tr>
</table></div>
