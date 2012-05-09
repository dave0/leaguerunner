<?php
class EventHandler extends Handler
{
	protected $event;

	protected $event_types;

	function __construct ( $id, $user = null )
	{
		global $lr_session;

		$this->event = Event::load( array('registration_id' => $id) );
		$this->event_types = event_types();

		if(!$this->event) {
			error_exit("That event does not exist");
		}

		$this->formbuilder = $this->event->load_survey( true, $user ? $user : $lr_session->user);

		// Other code relies on the formbuilder variable not being set if there
		// are no questions.
		if( ! count( $this->formbuilder->_questions ) ) {
			unset( $this->formbuilder );
		}

		event_add_to_menu( $this->event );
	}

	// Players required to register for an event to join a team should be allowed to
	// register, even if force_roster_requests are turned on.
	function checkPrereqs()
	{
		return false;
	}
}
?>
