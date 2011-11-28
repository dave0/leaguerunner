{* TODO: div necessary? *}
<div class="schedule">
{if $edit_week}<form method='POST'>{/if}
<table>
{assign var='current_day'  value=''}
{foreach from=$games item=game}
{if $game->day_id != $current_day}
	{if $edit_week && $current_day == $edit_week}
	<tr>
		<td colspan="9"><label><input type="checkbox" name="edit[published]" value="yes" {if $game->published}checked="true"{/if} />Set as published for player viewing?</label></td>
	</tr><tr>
		<td colspan="9"><p>
			<input type="hidden" name="edit[step]", value="confirm" />
			<input type="submit" value="Submit"/>
			<input type="reset" />
		</p></td>
	</tr>
	{/if}

	{assign var='current_day' value=$game->day_id}
	{* schedule_heading *}
	<tr>
		<th class="gamedate" colspan="{if $can_edit}5{else}7{/if}">{$game->timestamp|date_format:"%a %b %e, %G"}</th>
		{if $can_edit}
		<th class="gamedate" colspan="2" style="text-align:right">
			{assign var='formatted_date' value=$current_day|date_format:"%a %b %e, %G"}
			<a href="{lr_url path="league/slots/`$game->league_id`?date=`$formatted_date`"}">fields</a>&nbsp;|&nbsp;
			<a href="{lr_url path="schedule/edit/`$game->league_id`/`$game->day_id`"}">edit week</a>&nbsp;|&nbsp;
			<a href="{lr_url path="game/reschedule/`$game->league_id`/`$game->day_id`"}">reschedule</a>
		</th>
		{/if}
	</tr>
	{* end schedule_heading *}
	{* schedule_subheading *}
	<tr>
		<th class="column-heading">Game</th>
		<th class="column-heading" colspan="2">Time/Place</th>
		<th class="column-heading" colspan="2">Home</th>
		<th class="column-heading" colspan="2">Away</th>
	</tr>
	{* end schedule_subheading *}
{/if}
	{* schedule_render_viewable *}
	{if $edit_week && $game->day_id == $edit_week}
	<tr>
		<td>
			{if $league->schedule_type == 'roundrobin'}{html_options name="edit[games][`$game->game_id`][round]" selected=$game->round options=$rounds}{/if}
			<input type="hidden" name="edit[games][{$game->game_id}][game_id]" value="{$game->game_id}" />
		</td>
		<td colspan="2">
			{html_options name="edit[games][`$game->game_id`][slot_id]" selected=$game->slot_id options=$gameslots}
		</td>
		<td colspan="2">
			{html_options name="edit[games][`$game->game_id`][home_id]" selected=$game->home_id options=$teams}
		</td>
		<td colspan="2">
			{html_options name="edit[games][`$game->game_id`][away_id]" selected=$game->away_id options=$teams}
		</td>
	</tr>
	{else}
	<tr {if ! $game->published} style="background-color: yellow"{/if}>
		<td><a href="{lr_url path="game/view/`$game->game_id`"}">{$game->game_id}</a></td>
		<td>{$game->game_start} - {$game->display_game_end()}</td>
		<td><a href="{lr_url path="field/view/`$game->fid`"}">{$game->field_code}</a></td>
		<td {if $game->status == "home_default"}style="background-color:red"{/if}><a href="{lr_url path="team/view/`$game->home_id`"}">{$game->home_name|truncate:20}</a></td>
		<td {if $game->status == "home_default"}style="background-color:red"{/if}>{$game->home_score}</td>
		<td {if $game->status == "away_default"}style="background-color:red"{/if}><a href="{lr_url path="team/view/`$game->away_id`"}">{$game->away_name|truncate:20}</a></td>
		<td {if $game->status == "away_default"}style="background-color:red"{/if}>{$game->away_score}</td>
	</tr>
	{/if}
	{* end schedule_render_viewable *}
{/foreach}
	{if $edit_week && $current_day == $edit_week}
	<tr>
		<td colspan="9"><label><input type="checkbox" name="edit[published]" value="yes" {if $game->published}checked="true"{/if} />Set as published for player viewing?</label></td>
	</tr><tr>
		<td colspan="9"><p>
			<input type="hidden" name="edit[step]", value="confirm" />
			<input type="submit" value="Submit"/>
			<input type="reset" />
		</p></td>
	</tr>
	{/if}
</table>
{if $edit_week}</form>{/if}
</div>
