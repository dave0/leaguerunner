<?php

class SeasonHandler extends Handler
{
	protected $season;

	function __construct ( $id )
	{
		$this->season = Season::load( array('id' => $id) );

		if(!$this->season) {
			error_exit("That season does not exist");
		}

		season_add_to_menu( $this->season );
	}
}
?>
