{include file=header.tpl}
<h1>{$title}</h1>
  <div class='pairtable'>
    <table>
	<tr><td>Season:</td><td>{$season->season}</td></tr>
	<tr><td>Year:</td><td>{$season->year}</td></tr>
     </table>
    </div>
    {include file="pages/season/components/league_list.tpl"}
{include file=footer.tpl}
