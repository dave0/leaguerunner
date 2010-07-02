{include file=header.tpl}
<h1>{$title}</h1>
<p>
	Enter information on which to search and click 'submit'.  You may use '*' as a wildcard
</p>
<form method="GET">
<label>Last Name:<input type='textfield' size='25' name = 'search' value="{$lastname|escape}"/></label>
<input type="submit" value="search" />
</form>
<table id="players" style="width: 100%">
	<thead>
	  <tr>
	    <th>Last Name</th>
	    <th>First Name</th>
	    <th>actions</th>
	  </tr>
	</thead>
	<tbody>
	{foreach from=$people item=p}
	<tr>
	  <td>{$p->lastname}</td>
	  <td>{$p->firstname}</td>
	  <td>{foreach key=name item=actionurl from=$ops}
	  [&nbsp;<a href="{lr_url path="`$actionurl`/`$p->user_id`"}">{$name}</a>&nbsp;] 
	  {/foreach}
	  </td>
	</tr>
	{/foreach}
	</tbody>
</table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#players').dataTable( {
		bFilter: false,
		bJQueryUI: true,
		iDisplayLength: 50,
		sPaginationType: "full_numbers",
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			null,
			null,
			{ bSortable : false }
		]
	} );
})
{/literal}
</script>

{include file=footer.tpl}
