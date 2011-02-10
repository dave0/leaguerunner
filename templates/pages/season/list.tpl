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
            </tr>
        {/foreach}
	</tbody>
    </table>
{include file=footer.tpl}
