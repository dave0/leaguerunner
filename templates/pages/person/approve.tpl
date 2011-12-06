{include file='header.tpl'}
<h1>{$title}</h1>
{if $duplicates}
<div class="warning">
The following users may be duplicates of this account:
<ul>
{foreach item=d from=$duplicates}
  <li>{$d->firstname} {$d->lastname} [&nbsp;<a href="{lr_url path="person/view/`$d->user_id`"}">view</a>&nbsp;]
{/foreach}
</ul>
</div>
{/if}
<form method="POST">
<p>
  <input type="hidden" name="edit[step]" value="perform" />
  <label for="edit[disposition]">This user should be</label>
  <select name="edit[disposition]">
     <option value="---">- Select One -</option>
     <option value="approve_player">Approved as player account</option>
     <option value="approve_visitor">Approved as visitor account (cannot register or join teams)</option>
     {foreach item=d from=$duplicates}
     <option value="delete_duplicate:{$d->user_id}">Deleted as duplicate of {$d->firstname} {$d->lastname} ({$d->user_id})</option>
     <option value="merge_duplicate:{$d->user_id}">Merged backwards into {$d->firstname} {$d->lastname} ({$d->user_id})</option>
     {/foreach}
     <option value="delete">Deleted silently</option>
  </select>
  <input type="submit" />
</p>
</form>
{include file='pages/person/view_inner.tpl'}
{include file='footer.tpl'}
