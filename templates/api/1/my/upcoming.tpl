<html><body><div id="lr_upcoming">
{if $error}
{$error}
{else}
{foreach item=game from=$games name=games}
{if $smarty.foreach.games.first}<ul>{/if}

 <li><b>
 	{$game->timestamp|date_format:'%a %d %b, %H:%M'}<br />
	({$game->time_until()})
     </b><br />
     <a href="{lr_url path="game/view/`$game->game_id`"}"><nobr>{$game->home_name}</nobr> vs. <nobr>{$game->away_name}</nobr></a>
     <nobr>at <a href="{lr_url path="field/view/`$game->fid`"}">{$game->field_code}</a></nobr>

{if $smarty.foreach.games.last}</ul>{/if}
{foreachelse}
No games scheduled
{/foreach}
{/if}
</div></body></html>
