<?php
require_once('Handler/team/roster.php');

/**
 * Handler for forced roster updates, can't check prereqs, because that's
 * how we got here in the first place!
 */
class team_request extends team_roster
{
	function __construct ( $id, $player_id = null )
	{
		parent::__construct( $id, $player_id );
		$this->template_name = 'pages/team/request.tpl';
	}

	function checkPrereqs( $next )
	{
		return false;
	}
}

?>
