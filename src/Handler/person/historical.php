<?php
/**
 * Player historical data handler
 */
require_once('Handler/PersonHandler.php');

class person_historical extends PersonHandler
{
	protected $person;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','view', $this->person->user_id);
	}

	function process ()
	{
		$this->title = "{$this->person->fullname} Historical Teams";

		return $this->generateView($this->person);
	}

	function generateView (&$person)
	{
		global $lr_session;

		$rosterPositions = getRosterPositions();
		$rows = array();
		$last_year = $last_season = '';

		foreach($person->historical_teams as $team) {
			if( $team->year == $last_year ) {
				$year = '';
				if( $team->season == $last_season ) {
					$season = '';
				} else {
					$season = $team->season;
				}
			} else {
				$year = $team->year;
				$season = $team->season;
			}
			$last_year = $team->year;
			$last_season = $team->season;

			$rows[] = array(
				$year,
				$season,
				$rosterPositions[$team->position],
				'on',
				l($team->name, "team/view/$team->id"),
				"(" . l($team->league_name, "league/view/$team->league_id") . ")"
			);
		}

		return table(null, $rows);
	}
}

?>
