<?php
require_once('Handler/event/edit.php');

class event_copy extends event_edit
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','create');
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = 'Copy Event';
		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$new_event = new Event;
				$this->perform($new_event, $edit);
				$new_event->copy_survey_from( $this->event );
				local_redirect(url("event/view/" . $new_event->registration_id));
				break;
			default:
				$edit = object2array($this->event);
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

?>
