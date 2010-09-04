{include file=header.tpl}
<h1>{$title}</h1>
<p>
	Scores are listed with the first score belonging the team whose name appears on the left.<br />
	Green backgrounds means row team is winning season series, red means
	column team is winning series. Defaulted games are not counted.
</p>
<table id="headtohead">
    <thead>
	<tr>
	    <th>&nbsp;</th>
	    {foreach from=$teams item=team}
	    <th><a href="{lr_url path="team/view/`$team->team_id`"}" title="{$team->name|escape} Rank:{counter name=header} Rating:{$team->rating}">{$team->name|truncate:6|escape}</a></th>
	    {/foreach}
	</tr>
    </thead>
    <tbody>
	{foreach from=$teams item=team}
	<tr>
	    <th><a href="{lr_url path="team/view/`$team->team_id`"}" title="{$team->name|escape} Rank:{counter name=left} Rating:{$team->rating}">{$team->name|truncate:6|escape}</a></th>
	    {foreach from=$team->headtohead item=opponent}
	    <td {if $opponent.class}class="{$opponent.class}"{/if}>{$opponent.data}</td>
	    {/foreach}
	    <th><a href="{lr_url path="team/view/`$team->team_id`"}" title="{$team->name|escape} Rank:{counter name=right} Rating:{$team->rating}">{$team->name|truncate:6|escape}</a></th>
	</tr>
	{/foreach}
    </tbody>
</table>
{include file=footer.tpl}
