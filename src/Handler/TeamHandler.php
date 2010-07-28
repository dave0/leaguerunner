<?php

class TeamHandler extends Handler
{
	protected $team;

	function __construct ( $id )
	{
		$this->team = Team::load( array('team_id' => $id) );

		if(!$this->team) {
			error_exit("That team does not exist");
		}

		team_add_to_menu( $this->team );
	}
}
?>
