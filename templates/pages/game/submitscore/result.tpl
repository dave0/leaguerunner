{include file='header.tpl' title=$title}
<h1>{$title}</h1>
{if $have_opponent_entry}
  {if $finalized}
<p>This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.</p>
  {else}
<p>This score doesn\'t agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.</p>
  {/if}
{else}
<p>This score has been saved.  Once your opponent has entered their score, it will be officially posted.</p>
{/if}
{include file='footer.tpl'}
