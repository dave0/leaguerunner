{include file='header.tpl'}
<h1>{$title}</h1>
<p>
The following games have not been finalized:
</p>
<div class="listtable">
<table>
<thead>
  <tr>
    <th>Game Date</th>
    <th colspan="2">Home Submission</th>
    <th colspan="2">Away Submission</th>
    <th>&nbsp;</th>
  </tr>
</thead>
<tbody>
{foreach from=$games item=game}
<tr>
  <td rowspan="3">
	<b>{$game->timestamp|date_format:"%A %B %d %Y, %H%Mh"}</b><br />
	&nbsp;&nbsp;&raquo;&nbsp;<a href="{lr_url path="game/view/`$game->game_id`"}">view game</a><br />
	&nbsp;&nbsp;&raquo;&nbsp;<a href="{lr_url path="game/approve/`$game->game_id`"}">approve score</a><br />
	&nbsp;&nbsp;&raquo;&nbsp;<a href="{lr_url path="game/removeresults/`$game->game_id`"}">remove results</a><br />
  	&nbsp;&nbsp;&raquo;&nbsp;<a href="mailto:{$game->captains_email_list}">email captains</a><br />
  </td>
  <td colspan="2">{$game->home_name}</td>
  <td colspan="2">{$game->away_name}</td>
</tr>
<tr>
  <td>Home Score</td> <td>{$game->home_score_for}</td>
  <td>Home Score</td> <td>{$game->away_score_against}</td>
</tr>
<tr>
  <td>Away Score</td> <td>{$game->home_score_against}</td>
  <td>Away Score</td> <td>{$game->away_score_for}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>

{include file='footer.tpl'}
