{include file=header.tpl}
<h1>{$title}</h1>
<div class='splash'>
{if $rss_feed_items}
<table>
<tr><th colspan="2">{$rss_feed_title}</th></tr>
{foreach item=f from=$rss_feed_items}
<tr><td><a href="{$f.link}">{$f.title}</a></td></tr>
{/foreach}
</table>
{/if}

<table>
<tr><th colspan="2">My Teams</th></tr>
{foreach item=t from=$teams}
<tr><td><a href="{lr_url path="team/view/`$t->team_id`"}">{$t->name}</a> ({$t->rendered_position})</td><td align="right"><a href="{lr_url path="team/schedule/`$t->team_id`"}">schedule</a> | <a href="{lr_url path="league/standings/`$t->league_id`/`$t->team_id`"}">standings</a></td></tr>
{foreachelse}
<tr><td colspan="2">You are not yet on any teams</td></tr>
{/foreach}
</table>

{if $leagues}
<table>
<tr><th colspan="2">Leagues</th></tr>
{foreach item=l from=$leagues}
<tr>
  <td><a href="{lr_url path="league/view/`$l->league_id`"}">{$l->fullname}</a></td>
  <td align="right">
      <a href="{lr_url path="league/edit/`$l->league_id`"}">edit</a> 
      {if $l->schedule_type != 'none'}
      | <a href="{lr_url path="schedule/view/`$l->league_id`"}">schedule</a> 
      | <a href="{lr_url path="league/standings/`$l->league_id`"}">standings</a>
      | <a href="{lr_url path="league/approvescores/`$l->league_id`"}">approve scores</a>
      {/if}
  </td>
</tr>
{/foreach}
</table>
{/if}

<div class='schedule'><table alternate-colours="1">
<tr><th colspan="3">Recent and Upcoming Games</th></tr>
{foreach item=g from=$games}
{* TODO: jQuery for alternating row colours *}
<tr>
  <td><a href="{lr_url path="game/view/`$g->game_id`"}">{$g->timestamp|date_format:"%a %b %d"}, {$g->game_start}-{$g->display_game_end()}</a></td>
  <td><a href="{lr_url path="team/view/`$g->home_id`"}">{$g->home_name}</a> (home) vs. <a href="{lr_url path="team/view/`$g->away_id`"}">{$g->away_name}</a> (away) at <a href="{lr_url path="field/view/`$g->fid`}">{$g->field_code}</a></td>
  <td>{if $g->is_finalized()}
  	{$g->home_score} - {$g->away_score}
  {else}
  	{if $g->score_entered}
		{$g->score_entered}
	{elseif $g->user_can_submit}
		<a href="{lr_url path="game/submitscore/`$g->game_id`/`$g->user_team_id`"}">submit score</a>
	{/if}
  {/if}
  </td>
</tr>
{foreachelse}
{/foreach}
</table>
</div></div>

{include file=footer.tpl}
