<?php
register_page_handler('league_schedule_edit', 'LeagueScheduleEdit');

/**
 * Edit league schedule
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueScheduleEdit extends Handler
{
	function initialize ()
	{
		$this->set_title("League Schedule View");
		$this->_permissions = array(
		);
		return true;
	}
	
	function has_permission ()
	{
		global $DB, $session, $id;

		if(!$session->is_valid()) {
			$this->error_text = gettext("You do not have a valid session");
			return false;
		}
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide a league ID");
			return false;
		}
	
		if($session->attr_get('class') == 'administrator') {
			return true;
		}

		if($session->is_coordinator_of($id)) { 
			return true;	
		}

		return true;
	}
	
	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		switch($step) {
			case 'perform':
				return $this->perform();
				break;
			case 'confirm':
			default:
				$this->set_template_file("League/schedule_edit_confirm.tmpl");
				$this->tmpl->assign("page_step", 'perform');
				$rc = $this->generate_confirm();
				break;
		}
		$this->tmpl->assign("page_op", var_from_getorpost('op'));
		
		return $rc;
	}
	
	/**
	 * Override parent display to redirect to 'view' on success
	 */
	function display ()
	{
		global $id;
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			return $this->output_redirect("op=league_schedule_view&id=$id");
		}
		return parent::display();
	}

	function validate_data () 
	{
		$games = var_from_post('games');
		if(!is_array($games) ) {
			$this->error_text = gettext("Invalid data supplied for games");
			return false;
		}
	
		$rc = true;
		foreach($games as $game) {
			/* TODO:
			 * Validate that each game has real looking data..  Each must
			 * have:
			 * 	game_id
			 * 	round
			 * 	start_time
			 * 	home_id
			 * 	away_id
			 * 	field_id
			 * All except start_time should be integer values.
			 */
		}
		
		return $rc;
	}

	function generate_confirm () 
	{
		global $DB, $id;
		
		if(! $this->validate_data()) {
			return false;
		}
		
		$games = var_from_post('games');
		
		$row = $DB->getRow(
			"SELECT 
				l.name,
				l.tier
			FROM league l
			WHERE l.league_id = ?",
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$this->tmpl->assign("league_name", $row['name']);
		$this->tmpl->assign("league_tier",   $row['tier']);

		while (list ($game_id, $game_info) = each ($games) ) {
			$games[$game_id]['home_name'] = $DB->getOne("SELECT name from team where team_id = ?", array($game_info['home_id']));
			$games[$game_id]['away_name'] = $DB->getOne("SELECT name from team where team_id = ?", array($game_info['away_id']));
			$games[$game_id]['field_name'] = $DB->getOne("SELECT name from field_info where field_id = ?", array($game_info['field_id']));
		}
		reset($games);
		
		$this->tmpl->assign("games",     $games);
		$this->tmpl->assign("league_id", $id);

		return true;

	}
	
	function perform () 
	{
		global $DB, $id;
		
		if(! $this->validate_data()) {
			return false;
		}
		
		$games = var_from_post('games');

		$sth = $DB->prepare("UPDATE schedule SET home_team = ?, away_team = ?, field_id = ?, round = ?, date_played = ? WHERE game_id = ?");
		
		while (list ($game_id, $game_info) = each ($games) ) {

			/* 
			 * TODO: Fix this
			 * This is intolerably stupid.  Date and time should be split into
			 * two fields, in order to allow them to be easily set
			 * independantly.
			 */
			$date = $DB->getOne('SELECT DATE_FORMAT(date_played, "%Y-%m-%d") FROM schedule WHERE game_id = ?', array($game_id));
			if($this->is_database_error($date)) {
				return false;
			}

			$res = $DB->execute($sth, array(
				$game_info['home_id'],
				$game_info['away_id'],
				$game_info['field_id'],
				$game_info['round'],
				$date . " " . $game_info['start_time'],
				$game_id));
			if($this->is_database_error($res)) {
				return false;
			}
		}
		reset($games);

		return true;
		
	}
}
