{include file=header.tpl}
<h1>{$title}</h1>
    {foreach from=$seasons item=s name=seasons}
    	<a href="{lr_url path="league/list/`$s`}">{$s}</a>
    	{if ! $smarty.foreach.seasons.last}
	&nbsp;|&nbsp;
	{/if}
    {/foreach}
    <table id="seasons">
        <thead>
           <tr>
		<th>Season</th>
		<th>Year</th>
                <th>Name</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$leagues item=l}
            <tr>
		<td>{$l->season}</td>
		<td>{$l->year}</td>
                <td><a href="{lr_url path="league/view/`$l->league_id`}">{$l->name}</a></td>
                <td>{if $l->schedule_type != 'none'}
                        <a href="{lr_url path="schedule/view/`$l->league_id`}">schedule</a> &nbsp;
                        <a href="{lr_url path="league/standings/`$l->league_id`}">standings</a> &nbsp;
                    {/if}
                    {if session_perm("league/delete/`$l->league_id`")}
                        <a href="{lr_url path="league/delete/`$l->league_id`}">delete</a> &nbsp;
                    {/if}
                </td>
            </tr>
        {/foreach}
	</tbody>
    </table>
	
{include file=footer.tpl}
