<?php
require_once('Handler/team/roster.php');

/**
 * Handler for forced roster updates, can't check prereqs, because that's
 * how we got here in the first place!
 */
class team_request extends team_roster
{
	function checkPrereqs( $next )
	{
		return false;
	}

	function formPrompt()
	{
		return para("You have been invited to join the team <b>{$this->team->name}</b>. To ensure up-to-date rosters, you must either accept or decline this invitation. Please select your desired level of participation on this team from the list below:");
	}
}

?>
