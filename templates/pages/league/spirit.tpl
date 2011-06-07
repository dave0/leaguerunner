{include file=header.tpl}
<style>
{literal}
#main table td { font-size: 80% }
{/literal}
</style>
<h1>{$title}</h1>
<table id="spirit">
  <thead>
    <tr>
      <th>Team</th>
      <th>Average</th>
      {foreach from=$question_headings item=heading}
      <th>{$heading}</th>
      {/foreach}
    </tr>
  </thead>
  <tbody>
  {foreach from=$spirit_summary item=row}
  <tr>
    {foreach from=$row item=col}
    <td>{$col}</td>
    {/foreach}
  </tr>
  {/foreach}
  </tbody>
  <tfoot>
  <tr>
    {foreach from=$spirit_avg item=col}
    <th>{$col}</th>
    {/foreach}
  </tr>
  <tr>
    {foreach from=$spirit_dev item=col}
    <th>{$col}</th>
    {/foreach}
  </tr>
  </tfoot>
</table>


{if $spirit_detail}
<h1>Detailed Per-game Spirit</h1>
<table id="spirit_detail" style="font-size: 80%">
  <thead>
    <tr>
      <th>Game</th>
      <th>Entry By</th>
      <th>Given To</th>
      <th>Score</th>
      {foreach from=$question_headings item=heading}
      <th>{$heading}</th>
      {/foreach}
    </tr>
  </thead>
  <tbody>
  {assign var='current_day'  value=''}
  {foreach from=$spirit_detail item=row}
  {cycle values="even,odd" assign="rowclass"}
  {if $row.day_id != $current_day}
  <tr>
     <td colspan="3"><h2>{$row.day_id|date_format:"%a %b %d %Y"}</h2></td>
     <td colspan="{$num_spirit_columns}"></td>
  </tr>
	{assign var='current_day' value=$row.day_id}
  {/if}
  <tr class="{$rowclass}">
    <td><a href="{lr_url path="game/view/`$row.game_id`"}">{$row.game_id}</a></td>
    <td><a href="{lr_url path="team/view/`$row.given_by_id`"}">{$row.given_by_name}</a></td>
    <td><a href="{lr_url path="team/view/`$row.given_to_id`"}">{$row.given_to_name}</a></td>
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
{/if}


<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#spirit').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 1, "desc" ], [0, "asc"] ],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "html" },
{/literal}
      {foreach from=$question_headings item=heading name=aocolumns}
      			{literal}{ "sType" : "html" }{/literal}{if !$smarty.foreach.aocolumns.last},{/if}
      {/foreach}
{literal}
		],
	});
})
{/literal}
</script>
{include file=footer.tpl}
