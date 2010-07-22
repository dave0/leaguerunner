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
		global $lr_session, $dbh;

		$this->title = $this->team->name;

		$this->template_name = 'pages/team/view.tpl';
		$this->smarty->assign('team', $this->team);

		if($this->team->home_field) {
			$field = field_load(array('fid' => $this->team->home_field));
			$this->smarty->assign('home_field', $field);
		}

		$teamSBF = $this->team->calculate_sbf( );
		if( $teamSBF ) {
			$this->smarty->assign('team_sbf', $teamSBF);
			$league = league_load( array('league_id' => $this->team->league_id) );
			$this->smarty->assign('league_sbf', $league->calculate_sbf());
		}

		if( $lr_session->has_permission('team','player shirts', $this->team->team_id) ) {
			$this->smarty->assign('display_shirts', true);
		}
		$rosterCount = 0;
		$rosterPositions = Team::get_roster_positions();
		$this->smarty->assign('roster_positions', $rosterPositions );

		$this->team->get_roster();
		$this->team->check_roster_conflict();
		foreach ($this->team->roster as $player) {
			if($lr_session->has_permission('team','player status', $this->team->team_id, $player->id) ) {
				$player->_modify_status = 1;
			} else {
				$player->_modify_status = 0;
			}
			if( $player->status == 'captain' ||
				$player->status == 'assistant' ||
				$player->status == 'player'
			) {
				++$rosterCount;
			}
			$player->status = $rosterPositions[$player->status];
		}

		# TODO: this should be smartyfied
		if( $rosterCount < 12 && $lr_session->is_captain_of($this->team->team_id) && $this->team->roster_deadline > 0 ) {
			$rc .= "<p><p class='error'>Your team currently has only $rosterCount full-time players listed. Your team roster must be completed (minimum of 12 rostered players) by the team roster deadline (" . strftime ('%Y-%m-%d', $this->team->roster_deadline) . "), and all team members must be listed as a 'regular player'.  If an individual has not replied promptly to your request to join, we suggest that you contact them to remind them to respond.</p>";
		}

		return $rc;
	}
}
?>
