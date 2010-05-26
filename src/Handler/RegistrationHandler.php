<?php

class RegistrationHandler extends Handler
{
	protected $registration;
	protected $event;
	protected $formkey;
	protected $formbuilder;

	function __construct ( $id )
	{
		$this->registration = registration_load( array('order_id' => $id) );

		if(!$this->registration) {
			error_exit("That registration does not exist");
		}

		$this->event = event_load( array('registration_id' => $this->registration->registration_id) );

		registration_add_to_menu( $this->registration );
	}

	function form_load ()
	{
		global $lr_session;

		if( ! $this->registration->registration_id ) {
			return;
		}

		$user = $lr_session->user;
		if( $this->registration ) {
			$user = $this->registration->user();
		}

		$this->formbuilder = $this->event->load_survey( true, $user);

		// Other code relies on the formbuilder variable not being set if there
		// are no questions.
		if( ! count( $this->formbuilder->_questions ) ) {
			unset( $this->formbuilder );
		}
	}
}

?>
