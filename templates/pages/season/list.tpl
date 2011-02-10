{include file=header.tpl}
<h1>{$title}</h1>
    <table id="seasons">
        <thead>
           <tr>
                <th>Name</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$seasons item=s}
            <tr>
                <td><a href="{lr_url path="season/view/`$s->id`}">{$s->display_name}</a></td>
                <td>
                    {if session_perm("league/list")}
                        <a href="{lr_url path="league/list?season=`$s->id`"}">list leagues</a> &nbsp;
                    {/if}
                    {if session_perm("season/delete/`$s->id`")}
                        <a href="{lr_url path="season/delete/`$s->id`}">delete</a> &nbsp;
                    {/if}
                </td>
            </tr>
        {/foreach}
	</tbody>
    </table>
{include file=footer.tpl}
