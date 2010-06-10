<?php
require_once('Handler/EventHandler.php');
class event_delete extends EventHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('event','delete',$this->event->registration_id);
	}

	function process ()
	{
		$this->title = "Delete Event: {$this->event->name}";

		switch($_POST['edit']['step']) {
			case 'perform':
				if ( $this->event->delete() ) {
					local_redirect(url("event/list"));
				} else {
					error_exit("Failure deleting event");
				}
				break;
			case 'confirm':
			default:
				return $this->generateConfirm();
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function generateConfirm ()
	{
		$rows = array();
		$rows[] = array('Event&nbsp;Name:', $this->event->name);
		$rows[] = array('Description:', $this->event->description);
		$rows[] = array('Event type:', $this->event_types[$this->event->type]);
		$rows[] = array('Cost:', '$' . $this->event->total_cost());
		$rows[] = array('Opens on:', $this->event->open);
		$rows[] = array('Closes on:', $this->event->close);
		if ($this->event->cap_female == -2)
		{
			$rows[] = array('Registration cap:', $this->event->cap_male);
		}
		else
		{
			if ($this->event->cap_male > 0)
			{
				$rows[] = array('Male cap:', $this->event->cap_male);
			}
			if ($this->event->cap_female > 0)
			{
				$rows[] = array('Female cap:', $this->event->cap_female);
			}
		}
		$rows[] = array('Multiples:', $this->event->multiple ? 'Allowed' : 'Not allowed');
		if( $this->event->anonymous ) {
			$rows[] = array('Survey:', 'Results of this event\'s survey will be anonymous.');
		}

		$output = form_hidden('edit[step]', 'perform');
		$output .= "<p>Do you really wish to delete this event?</p>";
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= form_submit('submit');

		return form($output);
	}
}

?>
