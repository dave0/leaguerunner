{include file=header.tpl}
<h1>{$title}</h1>
<p>
{foreach item=letter from=$letters}
{if $letter == $current_letter}
<b>{$letter}</b>
{else}
<a href="{lr_url path="team/list/`$letter`"}">{$letter}</a>
{/if}
{/foreach}
</p>
<table id="teams" style="width: 100%">
	<thead>
	  <tr>
	    <th>Team Name</th>
	    <th>actions</th>
	  </tr>
	</thead>
	<tbody>
	{foreach from=$teams item=t}
	<tr>
	  <td>{$t->name}</td>
	  <td>{foreach key=name item=actionurl from=$ops}
	  [&nbsp;<a href="{lr_url path="`$actionurl`/`$t->team_id`"}">{$name}</a>&nbsp;] 
	  {/foreach}
	  </td>
	</tr>
	{/foreach}
	</tbody>
</table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#teams').dataTable( {
		bFilter: false,
		bJQueryUI: true,
		iDisplayLength: 50,
		sPaginationType: "full_numbers",
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			null,
			{ bSortable : false }
		]
	} );
})
{/literal}
</script>

{include file=footer.tpl}
