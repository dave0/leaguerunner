{include file=header.tpl}
<h1>{$title}</h1>
    <form method="GET">
    {html_options name="season" selected=$current_season->id options=$seasons onChange="form.submit()"}
    </form>
    {include file="pages/season/components/league_list.tpl"}
{include file=footer.tpl}
