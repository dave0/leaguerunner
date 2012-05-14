{include file='header.tpl'}
<h1>{$title}</h1>
  <div class='pairtable'><table>
	{include file='pages/league/components/short_view.tpl'}
	{if $league->coord_list}<tr><td>Coordinator Email List:</td><td><a href="mailto:{$league->coord_list}">{$league->coord_list}</a></td></tr>{/if}
	{if $league->capt_list}<tr><td>Captain Email List:</td><td><a href="mailto:{$league->capt_list}">{$league->capt_list}</a></td></tr>{/if}
	{if $league->roster_deadline}<tr><td>Roster deadline:</td><td>{$league->roster_deadline}</td></tr>{/if}
	{if $league->min_roster_size}<tr><td>Minimum Roster size:</td><td>{$league->min_roster_size}</td></tr>{/if}
	<tr><td>Type:</td><td>{$league->schedule_type}</td></tr>
        {if $league->schedule_type != 'none'}
	        <tr><td>League SBF:</td><td>{$league->calculate_sbf()}</td></tr>
        {/if}
        {if $league->finalize_after}<tr><td>Scores must be entered within:</td><td>{$league->finalize_after} hours of game end</td></tr>{/if}
     </table>
    </div>
    <table id="teams">
        <thead>
           <tr>
                <th>Seed</th>
                <th>Team Name</th>
                <th>Players</th>
                <th>Rating</th>
                <th>Avg. Skill</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
        {foreach from=$teams item=t}
            <tr>
                <td>{counter}</td>
                <td><a href="{lr_url path="team/view/`$t->team_id`"}">{$t->name|truncate:35}</a></td>
                <td>{$t->count_players()}</td>
                <td>{$t->rating}</td>
                <td>{$t->avg_skill()}</td>
                <td>{if $t->status == 'open'}
                        <a href="{lr_url path="team/roster/`$t->team_id`/`$session_userid`"}">join</a> &nbsp;
                    {/if}
                    {if session_perm("team/edit/`$t->team_id`")}
                        <a href="{lr_url path="team/edit/`$t->team_id`"}">edit</a> &nbsp;
                    {/if}
                    {if session_perm("team/delete/`$t->team_id`")}
                        <a href="{lr_url path="team/delete/`$t->team_id`"}">delete</a> &nbsp;
                    {/if}
                </td>
            </tr>
        {/foreach}
	</tbody>
    </table>
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
		aaSorting: [[ 0, "asc" ]],
		aoColumns: [
			null,
			null,
			null,
			null,
			null,
			{ bSortable : false }
		]
	} );
})
{/literal}
</script>
{include file='footer.tpl'}
