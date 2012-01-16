<?php
require_once('Handler/TeamHandler.php');

class team_view extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','view', $this->team->team_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = $this->team->name;

		$this->template_name = 'pages/team/view.tpl';
		$this->smarty->assign('team', $this->team);

		if($this->team->home_field) {
			$field = Field::load(array('fid' => $this->team->home_field));
			$this->smarty->assign('home_field', $field);
		}

		$teamSBF = $this->team->calculate_sbf( );
		if( $teamSBF ) {
			$this->smarty->assign('team_sbf', $teamSBF);
			$league = League::load( array('league_id' => $this->team->league_id) );
			$this->smarty->assign('league_sbf', $league->calculate_sbf());
		}

		if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
			$this->smarty->assign('display_shirts', true);
		}
		
		if ($lr_session->has_permission('team', 'player rating', $this->team->team_id)) {
			$this->smarty->assign('display_rating', true);
		}
		$rosterPositions = Team::get_roster_positions();
		$this->smarty->assign('roster_positions', $rosterPositions );

		$this->team->get_roster();
		$this->team->check_roster_conflict();
		foreach ($this->team->roster as $player) {
			$player->status = $rosterPositions[$player->status];
		}

		$min_roster = $league->min_roster_size;
		if( $this->team->roster_count < $min_roster && ($lr_session->is_captain_of($this->team->team_id) || $lr_session->is_admin()) && $this->team->roster_deadline > 0 ) {
			$this->smarty->assign('roster_requirement', $min_roster);
			$this->smarty->assign('display_roster_note', true);
		}

		return true;
	}
}
?>
