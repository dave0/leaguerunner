{include file='header.tpl'}
<h1>{$title}</h1>

    <div class='pairtable'>
      <table>
	{include file='pages/season/components/short_view.tpl'}
      </table>
    </div>

    <p></p>
    <h3>Leagues</h3>
    {include file="pages/season/components/league_list.tpl"}

    <p></p>
    <h3>Registration Events</h3>
    {include file="pages/season/components/event_list.tpl"}
{include file='footer.tpl'}
