{include file=header.tpl title=$title}
<h1>{$title}</h1>
<p>
	You are attempting to change player status for <b>{$player->fullname}</b> on team <b>{$team->name}</b>
</p>
<p>
	Current status is <b>{$current_status}</b>
</p>
{include file=pages/team/components/roster_form.tpl}
{include file=footer.tpl}
