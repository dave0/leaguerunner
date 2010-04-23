<?php
class event_create extends event_edit
{
	function __construct ( )
	{
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','create');
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = 'Create Event';

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->event = new Event;
				$this->perform($this->event, $edit);
				local_redirect(url("event/view/" . $this->event->registration_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}
}

?>
