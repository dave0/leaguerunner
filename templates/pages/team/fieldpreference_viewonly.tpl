{include file=header.tpl}
<h1>{$title}</h1>
<p>
	Leaguerunner now allows teams to rank field sites in order of
	desirability for your team.  Leaguerunner will attempt to schedule your
	home games at one of your preferred sites, however no guarantees can be made.
</p>
<p>
	Your team captain(s) have made the choices shown below.  If you wish to
	provide input or change these choices, contact your team captain or coach.
</p>

<ol>
	{foreach from=$selected item=f}
	<li><a href="{lr_url path="field/view/`$f->fid`"}">{$f->name}</a> ({$f->region})</li>
	{/foreach}
</ol>


