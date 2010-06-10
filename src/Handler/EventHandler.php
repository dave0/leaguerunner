<?php
class EventHandler extends Handler
{
	protected $event;

	protected $event_types;

	function __construct ( $id )
	{
		$this->event = event_load( array('registration_id' => $id) );
		$this->event_types = event_types();

		if(!$this->event) {
			error_exit("That event does not exist");
		}

		$this->formbuilder = $this->event->load_survey( true, $user);

		// Other code relies on the formbuilder variable not being set if there
		// are no questions.
		if( ! count( $this->formbuilder->_questions ) ) {
			unset( $this->formbuilder );
		}

		event_add_to_menu( $this->event );
	}
}
?>
