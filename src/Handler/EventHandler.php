<?php

class EventHandler extends Handler
{
	protected $event;

	protected $event_types = array(
		'membership'        => 'Individual membership',
		'individual_league' => 'Individual for league',
		'team_league'       => 'Team for league',
		'individual_event'  => 'Individual for tournament or other one-time event',
		'team_event'        => 'Team for tournament or other one-time event'
	);

	function __construct ( $id )
	{
		$this->event = event_load( array('registration_id' => $id) );

		if(!$this->event) {
			error_exit("That event does not exist");
		}

		event_add_to_menu( $this->event );
	}
}
?>
