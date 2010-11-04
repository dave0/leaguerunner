{include file=header.tpl}
<style>
{literal}
#main table td { font-size: 80% }
{/literal}
</style>
<h1>{$title}</h1>
<table id="spirit">
  <thead>
    <tr>
      <th>Team</th>
      <th>Average</th>
      {foreach from=$question_headings item=heading}
      <th>{$heading}</th>
      {/foreach}
    </tr>
  </thead>
  <tbody>
  {foreach from=$spirit_summary item=row}
  <tr>
    {foreach from=$row item=col}
    <td>{$col}</td>
    {/foreach}
  </tr>
  {/foreach}
  </tbody>
  <tfoot>
  <tr>
    {foreach from=$spirit_avg item=col}
    <th>{$col}</th>
    {/foreach}
  </tr>
  <tr>
    {foreach from=$spirit_dev item=col}
    <th>{$col}</th>
    {/foreach}
  </tr>
  </tfoot>
</table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#spirit').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 1, "desc" ], [0, "asc"] ],
		aoColumns: [
			{ "sType" : "html" },
			{ "sType" : "html" },
{/literal}
      {foreach from=$question_headings item=heading name=aocolumns}
      			{literal}{ "sType" : "html" }{/literal}{if !$smarty.foreach.aocolumns.last},{/if}
      {/foreach}
{literal}
		],
	});
})
{/literal}
</script>
{include file=footer.tpl}
