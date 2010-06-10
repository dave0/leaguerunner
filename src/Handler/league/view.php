<?php
require_once('Handler/LeagueHandler.php');
class league_view extends LeagueHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->league->fullname} &raquo; View";

		foreach( $this->league->coordinators as $c ) {
			$coordinator = l($c->fullname, "person/view/$c->user_id");
			if($lr_session->has_permission('league','edit',$this->league->league_id)) {
				$coordinator .= "&nbsp;[&nbsp;" . l('remove coordinator', url("league/member/" . $this->league->league_id."/$c->user_id", 'edit[status]=remove')) . "&nbsp;]";
			}
			$coordinators[] = $coordinator;
		}
		reset($this->league->coordinators);

		$rows = array();
		if( count($coordinators) ) {
			$rows[] = array('Coordinators:',
				join('<br />', $coordinators));
		}

		if ($this->league->coord_list != null && $this->league->coord_list != '') {
			$rows[] = array('Coordinator Email List:', l($this->league->coord_list, "mailto:" . $this->league->coord_list));
		}
		if ($this->league->capt_list != null && $this->league->capt_list != '') {
			$rows[] = array('Captain Email List:', l($this->league->capt_list, "mailto:" . $this->league->capt_list));
		}

		$rows[] = array('Status:', $this->league->status);
		if($this->league->year) {
			$rows[] = array('Year:', $this->league->year);
		}
		$rows[] = array('Season:', $this->league->season);
		if($this->league->day) {
			$rows[] = array('Day(s):', $this->league->day);
		}
		if($this->league->roster_deadline) {
			$rows[] = array('Roster deadline:', $this->league->roster_deadline);
		}
		if($this->league->tier) {
			$rows[] = array('Tier:', $this->league->tier);
		}
		$rows[] = array('Type:', $this->league->schedule_type);

		// Certain things should only be visible for certain types of league.
		if($this->league->schedule_type != 'none') {
			$rows[] = array('League SBF:', $this->league->calculate_sbf());
		}

		if($this->league->schedule_type == 'roundrobin') {
			$rows[] = array('Current Round:', $this->league->current_round);
		}

		if($lr_session->has_permission('league','view', $league->league_id, 'delays') ) {
			if( $this->league->email_after )
				$rows[] = array('Scoring reminder delay:', $this->league->email_after . ' hours');
			if( $this->league->finalize_after )
				$rows[] = array('Game finalization delay:', $this->league->finalize_after . ' hours');
		}

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$header = array( 'Team Name', 'Players', 'Rating', 'Avg. Skill', '&nbsp;',);
		if ($this->league->schedule_type == "ratings_ladder" || $this->league->schedule_type == "ratings_wager_ladder" ) {
			array_unshift($header, 'Seed');
		}
		if($lr_session->has_permission('league','manage teams',$this->league->league_id)) {
			$header[] = 'Region';
		}

		$this->league->load_teams();

		if( count($this->league->teams) > 0 ) {
			$rows = array();
			list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $this->league->current_round ));
			$counter = 0;
			foreach($season as $team) {
				$counter++;
				$team_links = array();
				if($team->status == 'open') {
					$team_links[] = l('join', "team/roster/$team->team_id/" . $lr_session->attr_get('user_id'));
				}
				if($lr_session->has_permission('league','edit',$this->league->league_id)) {
					$team_links[] = l('move', "team/move/$team->team_id");
				}
				if($this->league->league_id == 1 && $lr_session->has_permission('team','delete',$team->team_id)) {
					$team_links[] = l('delete', "team/delete/$team->team_id");
				}

				$row = array();
				if ($this->league->schedule_type == "ratings_ladder"
					|| $this->league->schedule_type == "ratings_wager_ladder" ) {
					$row[] = $counter;
				}

				$row[] = l(display_short_name($team->name, 35), "team/view/$team->team_id");
				$row[] = $team->count_players();
				$row[] = $team->rating;
				$row[] = $team->avg_skill();
				$row[] = theme_links($team_links);
				if($lr_session->has_permission('league','manage teams',$this->league->league_id)) {
					$row[] = $team->region_preference;
				}

				$rows[] = $row;
			}

			$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		}

		return $output;
	}
}

?>
