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
	{if $league->schedule_type == 'roundrobin'}<tr><td>Current Round:</td><td>{$league->current_round}</td></tr>{/if}
	<tr><td>Status:</td><td>{$league->status}</td></tr>
	{if $league->year}<tr><td>Year:</td><td>{$league->year}</td></tr>{/if}
	<tr><td>Season:</td><td>{$league->season}</td></tr>
	{if $league->day}<tr><td>Day(s):</td><td>{$league->day}</td></tr>{/if}
        {if $league->tier}<tr><td>Tier:</td><td>{$league->tier}</td></tr>{/if}
