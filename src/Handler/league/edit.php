<?php
require_once('Handler/LeagueHandler.php');
class league_edit extends LeagueHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->league->name} &raquo; Edit";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit',$this->league->league_id);
	}

	function process ()
	{
		$edit = &$_POST['edit'];
		$this->template_name = 'pages/league/edit.tpl';

		// If Registrations are enabled, assign required events to the league
		if(variable_get('registration','')) {
			$this->smarty->assign('allevents', getOptionsFromQuery(
				"SELECT registration_id AS theKey, name AS theValue FROM registration_events e ".
				"WHERE e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()")
			);
		}

		$this->smarty->assign('status',  getOptionsFromEnum('league','status') );
		$this->smarty->assign('seasons', getOptionsFromQuery(
			"SELECT id AS theKey, display_name AS theValue FROM season ORDER BY year, id")
		);
		$this->smarty->assign('days',    getOptionsFromEnum('league','day') );
		$this->smarty->assign('ratios',  getOptionsFromEnum('league','ratio') );
		$this->smarty->assign('schedule_types',  getOptionsFromEnum('league','schedule_type') );
		$this->smarty->assign('display_sotg',  getOptionsFromEnum('league','display_sotg') );
		$this->smarty->assign('excludeTeams',  getOptionsFromEnum('league','excludeTeams') );

		/* TODO: 10 is a magic number.  Make it a config variable */
		$this->smarty->assign('tiers', getOptionsFromRange(0, 10) );
		/* TODO: 5 is a magic number.  Make it a config variable */
		$this->smarty->assign('rounds', getOptionsFromRange(0, 5) );

		$this->smarty->assign('games_before_repeat', getOptionsFromRange(0, 9) );

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("league/view/" . $this->league->league_id));

		} else {
			/* Deal with multiple days and start times */
			if(strpos($this->league->day, ",")) {
				$this->league->day = explode(',',$this->league->day);
			}
			$this->smarty->assign('edit', (array)$this->league);
		}
		return true;
	}

	function perform ( $edit )
	{
		$this->league->set('name', $edit['name']);
		$this->league->set('status', $edit['status']);

		if(is_array($edit['day'])) {
			$edit['day'] = join(",",$edit['day']);
		}
		$this->league->set('day', $edit['day']);

		// Have any registration events been deleted?
		foreach ($this->league->events as $key => $value) {
			if (! in_array($key, $edit['events'])) {
				$this->league->events[$key] ="delete";
			}
		}
		// loop against $edit a second time to check for registration additions
		foreach ($edit['events'] as $index=>$value) {
			if (! in_array_keys($value, $this->league->events)) {
				$this->league->events[$value] = "add";
			}
		}

		$this->league->set('season', $edit['season']);
		$this->league->set('roster_deadline', $edit['roster_deadline'] );
		$this->league->set('min_roster_size', $edit['min_roster_size']);
		$this->league->set('tier', $edit['tier']);
		$this->league->set('ratio', $edit['ratio']);
		$this->league->set('current_round', $edit['current_round']);
		$this->league->set('schedule_type', $edit['schedule_type']);

		if (   $edit['schedule_type'] == 'ratings_ladder'
		    || $edit['schedule_type'] == 'ratings_wager_ladder') {
			$this->league->set('games_before_repeat', $edit['games_before_repeat']);
		}

		$this->league->set('display_sotg', $edit['display_sotg']);
		$this->league->set('coord_list', $edit['coord_list']);
		$this->league->set('capt_list', $edit['capt_list']);
		$this->league->set('excludeTeams', $edit['excludeTeams']);

		$this->league->set('finalize_after', $edit['finalize_after']);

		if( !$this->league->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	/* TODO: Properly validate other data */
	function check_input_errors ( $edit )
	{
		$errors = array();

		if ( ! validate_nonhtml($edit['name'])) {
			$errors[] = "A valid league name must be entered";
		}

		if( !validate_yyyymmdd_input($edit['roster_deadline']) ) {
			$errors[] = 'You must provide a valid roster deadline';
		}

		switch($edit['schedule_type']) {
			case 'none':
			case 'roundrobin':
				break;
			case 'ratings_ladder':
			case 'ratings_wager_ladder':
				if ($edit['games_before_repeat'] == null || $edit['games_before_repeat'] == 0) {
					$errors[] = "Invalid 'Games Before Repeat' specified!";
				}
				break;
			default:
				$errors[] = "Values for allow schedule are none, roundrobin, ratings_ladder, and ratings_wager_ladder";
		}

		if($edit['schedule_type'] != 'none') {
			if( !$edit['day'] ) {
				$errors[] = "One or more days of play must be selected";
			}
		}

		if ( ! validate_number($edit['finalize_after']) || $edit['finalize_after'] < 0 ) {
			$errors[] = "A valid number must be entered for the game finalization delay";
		}

		return $errors;
	}
}

?>
