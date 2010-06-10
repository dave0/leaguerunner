<?php
require_once('Handler/TeamHandler.php');

class team_move extends TeamHandler
{
	var $team;

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','move',$this->team->team_id);
	}

	function process ()
	{
		global $lr_session;

		# Nuke HTML just in case
		$team_name = check_form($this->team->name, ENT_NOQUOTES);
		$this->title = "{$team_name} &raquo; Move";

		$edit = $_POST['edit'];
		if( $edit['step'] ) {
			if($edit['target'] < 1) {
				error_exit("That is not a valid league to move to");
			}

			if( ! $lr_session->has_permission('league','manage teams', $edit['target']) ) {
				error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
			}

			$targetleague = league_load( array('league_id' => $edit['target']));
			if( !$targetleague ) {
				error_exit("You must supply a valid league to move to");
			}

			if( $targetleague->league_id == $this->team->league_id ) {
				error_exit("You can't move a team to the league it's currently in!");
			}
		}

		if( $edit['swaptarget'] ) {
			$target_team = team_load( array('team_id' => $edit['swaptarget'] ) );
			if( !$target_team ) {
				error_exit("You must supply a valid target team ID");
			}

			if( $target_team->league_id == $this->team->league_id ) {
				error_exit("You can't swap with a team that's already in the same league!");
			}

			if( $target_team->league_id != $targetleague->league_id ) {
				error_exit("You can't swap with a team that's not in the league you want to move to!");
			}

			if( ! $lr_session->has_permission('league','manage teams', $target_team->league_id ) ) {
				error_exit("Sorry, you cannot move teams to leagues you do not coordinate");
			}
		}

		switch($edit['step']) {
			case 'perform':
				$sourceleague = league_load( array('league_id' => $this->team->league_id));
				$this->perform($targetleague, $target_team);
				local_redirect(url("league/view/" . $sourceleague->league_id));
			case 'confirm':
				return $this->confirm( $targetleague, $target_team);
			case 'swaptarget':
				return $this->choose_swaptarget($targetleague);
			default:
				return $this->choose_league();
		}

		error_exit("Error: This code should never be reached.");

	}

	function perform ($targetleague, $target_team)
	{
		global $lr_session;

		$rc = null;
		if( $target_team ) {
			$rc = $this->team->swap_team_with( $target_team );
		} else {
			$rc = $this->team->move_team_to( $targetleague->league_id );
		}

		if( !$rc  ) {
			error_exit("Couldn't move team between leagues");
		}
		return true;
	}

	function confirm ( $targetleague, $target_team )
	{
		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[target]', $targetleague->league_id);

		if( $target_team ) {
			$output .= form_hidden('edit[swaptarget]', $target_team->team_id);
		}

		$sourceleague = league_load( array('league_id' => $this->team->league_id));
		$output .= para(
			"You are attempting to move the team <b>" . $this->team->name . "</b> to <b>$targetleague->fullname</b>");
		if( $target_team ) {
			$output .= para("This team will be swapped with <b>$target_team->name</b>, which will be moved to <b>$sourceleague->fullname</b>.");
			$output .= para("Both teams' schedules will be adjusted so that each team faces any opponents the other had been scheduled for");
		}
		$output .= para("If this is correct, please click 'Submit' below.");
		$output .= form_submit("Submit");
		return form($output);
	}

	function choose_swaptarget ( $targetleague )
	{
		$output = form_hidden('edit[step]', 'confirm');
		$output .= form_hidden('edit[target]', $targetleague->league_id);
		$output .= para("You are attempting to move the team <b>" . $this->team->name . "</b> to <b>$targetleague->fullname</b>.");
		$output .= para("Using the list below, you may select a team to replace this one with. If chosen, the two teams will be swapped between leagues.  Any future games already scheduled will also be swapped so that each team takes over the existing schedule of the other");

		$teams = $targetleague->teams_as_array();
		$teams[0] = "No swap, just move";
		ksort($teams);
		reset($teams);

		$output .= form_select('', 'edit[swaptarget]', '', $teams);
		$output .= form_submit("Submit");
		$output .= form_reset("Reset");

		return form($output);
	}

	function choose_league ( )
	{
		global $lr_session, $dbh;

		$leagues = array();
		$leagues[0] = '-- select from list --';
		if( $lr_session->is_admin() ) {
			# TODO: league_load?
			$sth = $dbh->prepare("
				SELECT
					league_id as theKey,
					IF(tier,CONCAT(name,' Tier ',IF(tier>9,tier,CONCAT('0',tier))), name) as theValue
				FROM league
				WHERE league.status = 'open'
				ORDER BY season,TheValue,tier");
			$sth->execute();
			while($row = $sth->fetch()) {
				$leagues[$row['theKey']] = $row['theValue'];
			}
		} else {
			$leagues[1] = 'Inactive Teams';
			foreach( $lr_session->user->leagues as $league ) {
				$leagues[$league->league_id] = $league->fullname;
			}
		}

		$output = form_hidden('edit[step]', 'swaptarget');
		$output .=
			para("You are attempting to move the team <b>" . $this->team->name . "</b>. Select the league you wish to move it to");

		$output .= form_select('', 'edit[target]', '', $leagues);
		$output .= form_submit("Submit");
		$output .= form_reset("Reset");

		return form($output);
	}
}
?>
