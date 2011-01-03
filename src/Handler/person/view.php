<?php

require_once('Handler/PersonHandler.php');

class person_view extends PersonHandler
{
	function has_permission ()
	{
		global $lr_session;

		return $lr_session->has_permission('person','view', $this->person->user_id);
	}

	function process ()
	{
		$this->title = $this->person->fullname;

		$this->template_name = 'pages/person/view.tpl';
		$this->smarty->assign('person', $this->person);

		/* Display teams */
		// TODO: do at load time?
		$rosterPositions = Team::get_roster_positions();
		$teams = array();
		foreach($this->person->teams as $team) {
			$team->rendered_position = $rosterPositions[$team->position];
			$teams[] = $team;
		}
		$this->smarty->assign('teams', $teams);

		return true;
	}
}
?>
