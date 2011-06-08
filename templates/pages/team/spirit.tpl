{include file=header.tpl}
<h1>{$title}</h1>
<table>
  <tr>
    <td><img src="/leaguerunner/image/icons/perfect.png" align="left" title="Exceptional SOTG"/>= Exceptional SOTG </td>
    <td><img src="/leaguerunner/image/icons/ok.png" align="left" title="Good SOTG"/>=Good SOTG </td>
    <td><img src="/leaguerunner/image/icons/caution.png" align="left" title="Below Average SOTG"/>=Below Average SOTG</td>
    <td><img src="/leaguerunner/image/icons/not_ok.png" align="left" title="Poor SOTG"/>=Poor SOTG</td>
  </tr>
<table>

<table id="spirit_detail" style="font-size: 80%">
  <thead>
    <tr>
      <th>Game</th>
      <th>Date</th>
      <th>Opponent</th>
      <th>Game Avg</th>
      {foreach from=$question_headings item=heading}
      <th>{$heading}</th>
      {/foreach}
    </tr>
  </thead>
  <tbody>
  {foreach from=$spirit_detail item=row}
  {cycle values="even,odd" assign="rowclass"}
  <tr class="{$rowclass}">
    <td><a href="{lr_url path="game/view/`$row.game_id`"}">{$row.game_id}</a></td>
    <td>{$row.day_id|date_format:"%a %b %d %Y"}</td>
    <td><a href="{lr_url path="team/view/`$row.given_by_id`"}">{$row.given_by_name}</a></td>
    {if $row.no_spirit}
    <td colspan="{$num_spirit_columns}">
    	Team did not submit a spirit rating
    </td>
    {else}
      {foreach from=$question_keys item=key}
      <td>{$row[$key]}</td>
      {/foreach}
    {/if}
  </tr>
  {if $row.comments}
  <tr class="{$rowclass}">
     <td colspan="2"><b>Comment for entry above:</b></td>
     <td colspan="{$num_comment_columns}">{$row.comments}</td>
  </tr>
  {/if}
  {/foreach}
  </tbody>
</table>
{include file=footer.tpl}
