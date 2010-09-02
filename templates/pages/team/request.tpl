{include file=header.tpl title=$title}
<h1>{$title}</h1>
<p>
	You have been invited to join the team <b>{$team->name}</b>. To ensure
	up-to-date rosters, you must either accept or decline this invitation.
	Please select your desired level of participation on this team from the
	list below:
</p>
{include file=pages/team/components/roster_form.tpl}
{include file=footer.tpl}
