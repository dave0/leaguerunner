<leaguerunner>
    <auth>
	<status>fail</status>
	<error>{$error}</error>
	{if $reactivate}
	<reactivate>{$reactivate}</reactivate>
	{/if}
	{if $needwaiver}
	<needwaiver>{$needwaiver}</needwaiver>
	{/if}
	{if $needdogwaiver}
	<needdogwaiver>{$needdogwaiver}</needdogwaiver>
	{/if}
    </auth>
</leaguerunner>
