{include file=header.tpl}
<h1>{$title}</h1>
  <div class='pairtable'><table>
	<tr>
                <td>Coordinators:</td>
                <td>
                {strip}
                {foreach from=$league->coordinators item=c}
                <a href="{lr_url path="person/view/`$c->user_id`"}">{$c->fullname}</a>
                {if_session_permission path="league/edit/`$league->league_id`"}
                &nbsp;[&nbsp;<a href="{lr_url path="league/member/`$league->league_id`/`$c->user_id`?edit[status]=remove"}">remove coordinator</a>&nbsp;]
                {/if_session_permission}
                <br />
                {/foreach}
                {/strip}
                </td>
        </tr>
	{if $league->coord_list}<tr><td>Coordinator Email List:</td><td><a href="mailto:{$league->coord_list}">{$league->coord_list}</a></td></tr>{/if}
	{if $league->capt_list}<tr><td>Captain Email List:</td><td><a href="mailto:{$league->capt_list}">{$league->capt_list}</a></td></tr>{/if}
	{if $league->schedule_type == 'roundrobin'}<tr><td>Current Round:</td><td>{$league->current_round}</td></tr>{/if}
	<tr><td>Status:</td><td>{$league->status}</td></tr>
	{if $league->year}<tr><td>Year:</td><td>{$league->year}</td></tr>{/if}
	<tr><td>Season:</td><td>{$league->season}</td></tr>
	{if $league->day}<tr><td>Day(s):</td><td>{$league->day}</td></tr>{/if}
	{if $league->roster_deadline}<tr><td>Roster deadline:</td><td>{$league->roster_deadline}</td></tr>{/if}
        {if $league->tier}<tr><td>Tier:</td><td>{$league->tier}</td></tr>{/if}
	<tr><td>Type:</td><td>{$league->schedule_type}</td></tr>
        {if $league->schedule_type != 'none'}
	        <tr><td>League SBF:</td><td>{$league->calculate_sbf()}</td></tr>
        {/if}
        {if_session_permission path="league/view/`$league->league_id`/delays"}
                {if $league->email_after}<tr><td>Scoring reminder delay:</td><td>{$league->email_after} hours</td></tr>{/if}
                {if $league->finalize_after}<tr><td>Game finalization delay:</td><td>{$league->finalize_after} hours</td></tr>{/if}
        {/if_session_permission}
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
                {if_session_permission path="league/manage teams/`$league->league_id`"}
                <th>Region</th>
                {/if_session_permission}
            </tr>
        </thead>
        <tbody>
        {foreach from=$teams item=t}
            <tr>
                <td>{counter}</td>
                <td><a href="{lr_url path="team/view/`$t->team_id`}">{$t->name|truncate:35}</a></td>
                <td>{$t->count_players()}</td>
                <td>{$t->rating}</td>
                <td>{$t->avg_skill()}</td>
                <td>{if $t->status == 'open'}
                        <a href="{lr_url path="team/roster/`$t->team_id`/`$session_userid`}">join</a> &nbsp;
                    {/if}
                    {if_session_permission path="league/edit/`$league->league_id`"}
                        <a href="{lr_url path="league/edit/`$league->league_id`}">edit</a> &nbsp;
                    {/if_session_permission}
                    {if_session_permission path="team/delete/`$t->team_id`"}
                        <a href="{lr_url path="team/delete/`$t->team_id`}">delete</a> &nbsp;
                    {/if_session_permission}
                </td>
                {if_session_permission path="league/manage teams/`$league->league_id`"}
                <td>{$t->region_preference}</td>
                {/if_session_permission}
            </tr>
        {/foreach}
    </table>
<script type="text/javascript">
{literal}
$(document).ready(function() {
	$('#teams').dataTable( {
		bPaginate: false,
		bFilter: false,
		bInfo: false,
		aaSorting: [[ 0, "asc" ]],
	} );
	$('#teams tr:even').addClass('even');
})
{/literal}
</script>
{include file=footer.tpl}
