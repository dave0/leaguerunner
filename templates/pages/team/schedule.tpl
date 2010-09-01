{include file=header.tpl}
<h1>{$title}</h1>
<div class="schedule">
<table>
<tr>
	<th class="column-heading">Date/Time</th>
	<th class="column-heading">Field</th>
	<th class="column-heading" colspan="2">Home</th>
	<th class="column-heading" colspan="2">Away</th>
	<th class="column-heading">Status</th>
</tr>
{foreach from=$games item=game}
	<tr>
		<td><a href="{lr_url path="game/view/`$game->game_id`"}" title="Details for game {$game->game_id}">{$game->timestamp|date_format:"%Y/%m/%d"} {$game->game_start} - {$game->display_game_end()}</a></td>
		<td><a href="{lr_url path="field/view/`$game->fid`"}">{$game->field_code}</a></td>
		<td {if $game->status == "home_default"}style="background-color:red"{/if}><a href="{lr_url path="team/view/`$game->home_id`"}">{if $game->home_id == $team->team_id}<span style="font-weight:bold; font-size:1.1em">{/if}{$game->home_name|truncate:20}{if $game->home_id == $team->team_id}</span>{/if}</a></td>
		<td {if $game->status == "home_default"}style="background-color:red"{/if}>{$game->home_score}</td>
		<td {if $game->status == "away_default"}style="background-color:red"{/if}><a href="{lr_url path="team/view/`$game->away_id`"}">{if $game->away_id == $team->team_id}<span style="font-weight:bold; font-size:1.1em">{/if}{$game->away_name|truncate:20}{if $game->away_id == $team->team_id}</span>{/if}</a></td>
		<td {if $game->status == "away_default"}style="background-color:red"{/if}>{$game->away_score}</td>
		<td>{$game->score_type}</td>
	</tr>
{/foreach}
</table>
</div>
<p>
	You may also download your team schedule in <a href="{lr_url path="team/ical/`$team->team_id`/team.ics"}"><img style="display: inline" src="{$base_url}/image/icons/ical.gif" alt="iCalendar" /></a> format
</p>
{include file=footer.tpl}
