<?php

class LeagueHandler extends Handler
{
	protected $league;

	function __construct ( $id )
	{
		$this->league = League::load( array('league_id' => $id) );

		if(!$this->league) {
			error_exit("That league does not exist");
		}

		league_add_to_menu( $this->league );
	}
}
?>
