<?php

require_once("Handler/Team/View.php");
require_once("Handler/Team/Edit.php");
  require_once("Handler/Team/Create.php");
require_once("Handler/Team/List.php");
require_once("Handler/Team/ViewSchedule.php");
require_once("Handler/Team/Standings.php");
require_once("Handler/Team/PlayerStatus.php");
require_once("Handler/Team/AddPlayer.php");

/**
 * Format roster status as human-readable.
 */
function display_roster_status( $short_form )
{
	switch($short_form) {
	case 'captain':
		return "captain";
	case 'player':
		return "player";
	case 'substitute':
		return "substitute";
	case 'captain_request':
		return "requested by captain";
	case 'player_request':
		return "request to join by player";
	case 'none':
		return "not on team";
	default:
		trigger_error("invalid status: $short_form");
		return "ERROR: invalid status";
	}
}

?>
