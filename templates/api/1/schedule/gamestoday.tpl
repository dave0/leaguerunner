{strip}
<html><body><div id="gamestoday"><b>
{if $game_count}
<a href="{lr_url path="schedule/day/`$timestamp|date_format:'%Y/%m/%d'`"}" target=\"_top\">{$game_count} games today</a>
{else}
No games today
{/if}
</b>
{if $timecap}
&nbsp;<span id="timecap">Timecap is <b>{$timecap}</b>
    {if $multiple_end_times}
    &nbsp;(some games may differ)
    {/if}
</span>
{/if}
</div></body></html>
{/strip}
