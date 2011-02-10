<?php
require_once('Handler/event/edit.php');

class event_copy extends event_edit
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->event->name} &raquo; Copy";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','create');
	}

	function perform ( &$edit )
	{
		$old_event = $this->event;
		$this->event = new Event;

		parent::perform( $edit );
		$this->event->copy_survey_from( $old_event );

		return true;
	}
}

?>
