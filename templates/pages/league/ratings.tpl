{include file='header.tpl'}
<h1>{$title}</h1>

<p>
	Use the links below to adjust a team's ratings for 'better' or for
	'worse'.  Alternatively, you can enter a new rating into the box beside
	each team then click 'Adjust Ratings' below.  Multiple teams can have
	the same ratings, and likely will at the start of the season.
</p>
<p>
	For the rating values, a <b>HIGHER</b> numbered rating is <b>BETTER</b>, and
	a <b>LOWER</b> numbered rating is <b>WORSE</b>
</p>
<p>
	<b>WARNING: </b> Adjusting ratings while the league is already under
	way is possible, but you'd better know what you are doing!!!
</p>
<form method="POST" id="ratings_form">
    <table id="teams">
        <thead>
	   <tr>
           <th>Rating</th>
	   <th>Team Name</th>
	   <th>Avg.<br/>Skill</th>
	   <th>New Rating</th>
	   </tr>
	</thead>
	<tbody>
        {foreach from=$teams item=t}
            <tr>
	      <td>{$t->rating}</td>
	      <td>{$t->name}</td>
	      <td>{$t->avg_skill()}</td>
	      <td><font size='-4'><a href='#' onClick='document.getElementById("ratings_form").elements["edit[{$t->team_id}]"].value++; return false'> better </a>
	      <input type='text' size='3' name='edit[{$t->team_id}]' value='{$t->rating}' />
	      <a href='#' onClick='document.getElementById("ratings_form").elements["edit[{$t->team_id}]"].value--; return false'> worse</a></font>
	      </td>
	    </tr>
	{/foreach}
    </table>
    <input type="hidden" name="edit[step]" value="perform" />
    <input type='reset' />&nbsp;<input type='submit' value='Adjust Ratings' />
</form>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#teams').dataTable( {
		bPaginate: false,
		bAutoWidth: false,
		sDom: 'lfrtip',
		bFilter: false,
		bInfo: false,
		bJQueryUI: true,
		aaSorting: [[ 0, "desc" ]],
		aoColumns: [
			{ bSortable : false },
			{ bSortable : false },
			{ bSortable : false },
			{ bSortable : false }
		]
	} );
})
{/literal}
</script>
{include file='footer.tpl'}
