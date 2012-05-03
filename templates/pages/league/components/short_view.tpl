	<tr>
                <td>Coordinators:</td>
                <td>
                {strip}
                {foreach from=$league->coordinators item=c}
                <a href="{lr_url path="person/view/`$c->user_id`"}">{$c->fullname}</a>
                {if session_perm("league/edit/`$league->league_id`")}
                &nbsp;[&nbsp;<a href="{lr_url path="league/member/`$league->league_id`/`$c->user_id`?edit[status]=remove"}">remove coordinator</a>&nbsp;]
                {/if}
                <br />
                {/foreach}
                {/strip}
                </td>
        </tr>
	{if !empty($league->events)}
		<tr>
			<td>Registration Events:</td>
			<td>
			{foreach from=$league->events key=k item=r}
			<a href="{lr_url path="event/view/`$k`"}">{$r}</a><br />
			{/foreach}
			</td>
		</tr>
	{/if}
	{if $league->schedule_type == 'roundrobin'}<tr><td>Current Round:</td><td>{$league->current_round}</td></tr>{/if}
	<tr><td>Status:</td><td>{$league->status}</td></tr>
	<tr><td>Season:</td><td><a href="{lr_url path="season/view/`$league->season`"}">{$league->season_name}</a></td></tr>
	{if $league->day}<tr><td>Day(s):</td><td>{$league->day}</td></tr>{/if}
        {if $league->tier}<tr><td>Tier:</td><td>{$league->tier}</td></tr>{/if}
