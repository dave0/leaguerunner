{include file=header.tpl}
<h1>{$title}</h1>
    <form method="GET">
    {html_options name="season" selected=$current_season->id options=$seasons onChange="form.submit()"}
    </form>
    <table id="leagues">
        <thead>
           <tr>
                <th>Name</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$leagues item=l}
            <tr>
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
