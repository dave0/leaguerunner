<?php
register_page_handler('game_submitscore', 'GameSubmit');
register_page_handler('game_finalize', 'GameFinalizeScore');

class GameSubmit extends Handler
{

	var $_id;
	var $_team_id;

	function initialize ()
	{
		$this->set_title("Submit Game Score");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'require_var:team_id',
			'admin_sufficient',
			'coordinate_game:id',
			'captain_of:team_id',
			'deny',
		);

		return true;
	}

	function has_permission () 
	{
		global $DB;

		$rc = parent::has_permission();
		if($rc == false) {
			return false;
		}
		
		$id = var_from_getorpost('id');
		$row = $DB->getRow(
			"SELECT home_score, away_score FROM schedule WHERE game_id = ?", 
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		if(is_null($row)) {
			$this->error_exit("That game does not exist");
		}
		if(!is_null($row['home_score']) && !is_null($row['away_score']) ) {
			$this->error_exit("The score for that game has already been submitted.");
		}
		
		$team_id = var_from_getorpost('team_id');
		$row = $DB->getRow(
			"SELECT entered_by FROM score_entry WHERE game_id = ? AND team_id = ?", 
			array($id,$team_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		if(count($row) > 0) {
			$this->error_exit("The score for your team has already been entered.");
		}

		$this->_id = $id;
		$this->_team_id = $team_id;

		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		
		switch($step) {
			case 'confirm':
				$this->set_template_file("Game/score_submit_confirm.tmpl");
				$this->tmpl->assign('page_step','perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$this->set_template_file("Game/score_submit_done.tmpl");
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Game/score_submit_form.tmpl");
				$this->tmpl->assign('page_step','confirm');
				$rc = $this->generate_form();
		}
		
		$this->tmpl->assign('page_op',var_from_getorpost('op'));
		return $rc;
	}

	function isDataInvalid()
	{
		$errors = "";
		
		$defaulted = var_from_getorpost('defaulted');
		if( ! (is_null($defaulted) || (strlen($defaulted) == 0))) {
			switch($defaulted) {
				case 'us':
				case 'them':
					return false;  // Ignore other data in cases of default.
				default:
					return "An invalid value was specified for default.";
			}
		}
		
		$score_for = var_from_getorpost('score_for');
		if( !validate_number($score_for) ) {
			$errors .= "<br>You must enter a valid number for your score";
		}

		$score_against = var_from_getorpost('score_against');
		if( !validate_number($score_against) ) {
			$errors .= "<br>You must enter a valid number for your opponent's score";
		}

		$sotg = var_from_getorpost('sotg');
		if( !validate_number($sotg) ) {
			$errors .= "<br>You must enter a valid number for your opponent's SOTG";
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ()
	{
		global $DB, $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$schedule_entry = $DB->getRow(
			"SELECT 
				s.home_team AS home_id,
				s.away_team AS away_id
			 FROM schedule s 
			 WHERE s.game_id = ?",
			array($this->_id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($schedule_entry)) {
			return false;
		}

		$opponent_entry = $DB->getRow("SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE game_id = ?", array($this->_id),DB_FETCHMODE_ASSOC);
		if($this->is_database_error($opponent_entry)) {
			return false;
		}

		$our_entry = array(
			'score_for' => var_from_getorpost('score_for'),
			'score_against' => var_from_getorpost('score_against'),
			'spirit' => var_from_getorpost('sotg'),
			'defaulted' => var_from_getorpost('defaulted'),
		);
		
		if( count($opponent_entry) <= 0 ) {
			// No opponent entry, so just add to the score_entry table
			if($this->save_one_score($our_entry) == false) {
				return false;
			}
			$this->tmpl->assign("message", "This score has been saved.  Once your opponent has entered their score, it will be officially posted");
		} else {
			/* See if we agree with opponent score */
			if( defaults_agree($our_entry, $opponent_entry) ) {
				// Both teams agree that a default has occurred. 
				if(
					($this->_team_id == $schedule_entry['home_id'])
					&& ($our_entry['defaulted'] == 'us')
				) {
					$data = array( 0, 6, 'home', $this->_id);
				} else {
					$data = array( 6, 0, 'away', $this->_id);
				}

				$res = $DB->query("UPDATE schedule SET home_score = ?, away_score = ?, defaulted = ?, approved_by = -1 WHERE game_id = ?", $data);
				if($this->is_database_error($res)) {
					return false;
				}

				$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($this->_id));
				if($this->is_database_error($res)) {
					return false;
				}
				$this->tmpl->assign("message", "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.");
			} else if( scores_agree($our_entry, $opponent_entry) ) {
				/* Agree. Make it official */
				if($this->_team_id == $schedule_entry['home_id']) {
					$data = array(
						$our_entry['score_for'],
						$our_entry['score_against'],
						$opponent_entry['spirit'],
						$our_entry['spirit'],
						$this->_id);
				} else {
					$data = array(
						$our_entry['score_against'],
						$our_entry['score_for'],
						$our_entry['spirit'],
						$opponent_entry['spirit'],
						$this->_id);
				}

				$res = $DB->query("UPDATE schedule SET home_score = ?, away_score = ?, home_spirit = ?, away_spirit = ?, approved_by = -1 WHERE game_id = ?", $data);
				if($this->is_database_error($res)) {
					return false;
				}

				$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($this->_id));
				if($this->is_database_error($res)) {
					return false;
				}

				$this->tmpl->assign("message", "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.");
			} else {
				if($this->save_one_score($our_entry) == false) {
					return false;
				}
				$this->tmpl->assign("message", "This score doesn't agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.");
			}

		}

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$row = $DB->getRow(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = ?",
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		if($row['home_id'] == $this->_team_id) {
			$this->tmpl->assign('my_name',$row['home_name']);
			$this->tmpl->assign('opponent_name',$row['away_name']);
			$opponent_id = $row['away_id'];
		} else {
			$this->tmpl->assign('my_name',$row['away_name']);
			$this->tmpl->assign('opponent_name',$row['home_name']);
			$opponent_id = $row['home_id'];
		}
		
		$this->tmpl->assign('id',$this->_id);
		$this->tmpl->assign('team_id',$this->_team_id);
		$this->tmpl->assign('date_played',strftime("%A %B %d %Y, %H%Mh",$row['timestamp']));

		$this->tmpl->assign('score_for', var_from_getorpost('score_for'));
		$this->tmpl->assign('score_against', var_from_getorpost('score_against'));
		$this->tmpl->assign('sotg', var_from_getorpost('sotg'));
		$this->tmpl->assign('defaulted', var_from_getorpost('defaulted'));
		return true;
	}

	function generate_form () 
	{
		global $DB;
		
		$row = $DB->getRow(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = ?",
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		if($row['home_id'] == $this->_team_id) {
			$this->tmpl->assign('my_name',$row['home_name']);
			$this->tmpl->assign('opponent_name',$row['away_name']);
			$opponent_id = $row['away_id'];
		} else {
			$this->tmpl->assign('my_name',$row['away_name']);
			$this->tmpl->assign('opponent_name',$row['home_name']);
			$opponent_id = $row['home_id'];
		}
		
		$opponent = $DB->getRow(
			"SELECT 
				score_for, score_against 
				FROM score_entry WHERE game_id = ? AND team_id = ?", 
			array($this->_id,$opponent_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($opponent)) {
			return false;
		}
		if(!is_null($opponent)) {
			$this->tmpl->assign('opponent_for',$opponent['score_for']);
			$this->tmpl->assign('opponent_against',$opponent['score_against']);
		} else {
			$this->tmpl->assign('opponent_for',"not yet entered");
			$this->tmpl->assign('opponent_against',"not yet entered");
		}

		$this->tmpl->assign('id',$this->_id);
		$this->tmpl->assign('team_id',$this->_team_id);
		$this->tmpl->assign('date_played',strftime("%A %B %d %Y, %H%Mh",$row['timestamp']));
	
		return true;
	}

	function save_one_score ( $our_entry ) 
	{
		global $DB, $session;

		if($our_entry['defaulted'] == 'us') {
			$our_entry['score_for'] = 0;
			$our_entry['score_against'] = 6;
		} else if($our_entry['defaulted'] == 'them') {
			$our_entry['score_for'] = 6;
			$our_entry['score_against'] = 0;
		} else {
			$our_entry['defaulted'] = 'no';
		} 
		
		$res = $DB->query("INSERT INTO score_entry 
			(game_id,team_id,entered_by,score_for,score_against,spirit,defaulted)
				VALUES(?,?,?,?,?,?,?)",
				array($this->_id, $this->_team_id, $session->attr_get('user_id'), $our_entry['score_for'], $our_entry['score_against'], $our_entry['spirit'], $our_entry['defaulted']));
		if($this->is_database_error($res)) {
			return false;
		}
		return true;
	}
}

class GameFinalizeScore extends Handler
{
	var $_id;

	function initialize ()
	{
		$this->set_title("Finalize Game Score");
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinate_game:id',
			'deny',
		);

		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$this->_id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$this->set_template_file("Game/score_finalize_confirm.tmpl");
				$this->tmpl->assign('page_step','perform');
				$rc = $this->generate_confirm();
				break;
			case 'perform':
				$rc = $this->perform();
				break;
			default:
				$this->set_template_file("Game/score_finalize_form.tmpl");
				$this->tmpl->assign('page_step','confirm');
				$rc = $this->generate_form();
		}
		
		$this->tmpl->assign('page_op',var_from_getorpost('op'));
		return $rc;
	}
	
	function display ()
	{
		$step = var_from_getorpost('step');
		if($step == 'perform') {
			local_redirect("op=league_verifyscores&id=".$this->_league_id);
		}
		return parent::display();
	}

	function isDataInvalid()
	{
		$errors = "";
		
		$home_score = var_from_getorpost('home_score');
		if( !validate_number($home_score) ) {
			$errors .= "<br>You must enter a valid number for the home score";
		}
		$away_score = var_from_getorpost('away_score');
		if( !validate_number($away_score) ) {
			$errors .= "<br>You must enter a valid number for the away score";
		}
		$home_sotg = var_from_getorpost('home_sotg');
		if( !validate_number($home_sotg) ) {
			$errors .= "<br>You must enter a valid number for the home SOTG";
		}
		$away_sotg = var_from_getorpost('away_sotg');
		if( !validate_number($away_sotg) ) {
			$errors .= "<br>You must enter a valid number for the away SOTG";
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ()
	{
		global $DB, $session;
	
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$res = $DB->query("UPDATE schedule SET 
			home_score = ?, away_score = ?,
			home_spirit = ?, away_spirit = ?, 
			approved_by = ? WHERE game_id = ?",
			array(
				var_from_getorpost('home_score'),
				var_from_getorpost('away_score'),
				var_from_getorpost('home_sotg'),
				var_from_getorpost('away_sotg'),
				$session->attr_get('user_id'),
				$this->_id
		));
		if($this->is_database_error($res)) {
			return false;
		}

		/* And remove any score_entry fields */
		$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($this->_id));
		if($this->is_database_error($res)) {
			return false;
		}

		$this->_league_id = $DB->getOne("SELECT league_id FROM schedule WHERE game_id = ?", array($this->_id));

		return true;
	}

	function generate_confirm ()
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$row = $DB->getRow(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				h.name AS home_name, 
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = ?",
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}
		
		$this->tmpl->assign('id',$this->_id);
		$this->tmpl->assign('date_played',strftime("%A %B %d %Y, %H%Mh",$row['timestamp']));

		$this->tmpl->assign('home_name', $row['home_name']);
		$this->tmpl->assign('away_name', $row['away_name']);
		$this->tmpl->assign('home_score', var_from_getorpost('home_score'));
		$this->tmpl->assign('away_score', var_from_getorpost('away_score'));
		$this->tmpl->assign('home_sotg', var_from_getorpost('home_sotg'));
		$this->tmpl->assign('away_sotg', var_from_getorpost('away_sotg'));
		return true;
	}

	function generate_form () 
	{
		global $DB;
		
		$row = $DB->getRow(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = ?",
			array($this->_id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($row)) {
			return false;
		}

		$this->tmpl->assign('id',$this->_id);
		$this->tmpl->assign('date',strftime("%A %B %d %Y, %H%Mh",$row['timestamp']));
		$this->tmpl->assign("home_id", $row['home_id']);
		$this->tmpl->assign("home_name", $row['home_name']);
		$this->tmpl->assign("away_id", $row['away_id']);
		$this->tmpl->assign("away_name", $row['away_name']);
		
		$se_query = "SELECT score_for, score_against, spirit FROM score_entry WHERE team_id = ? AND game_id = ?";
		$home = $DB->getRow($se_query,
			array($row['home_id'],$this->_id),DB_FETCHMODE_ASSOC);
		if(isset($home)) {
			$this->tmpl->assign('home_self_score', $home['score_for']);
			$this->tmpl->assign('home_opp_score', $home['score_against']);
			$this->tmpl->assign('home_opp_sotg', $home['spirit']);
		} else {
			$this->tmpl->assign('home_self_score', "not entered");
			$this->tmpl->assign('home_opp_score', "not entered");
			$this->tmpl->assign('home_opp_sotg', "not entered");
		}
		$away = $DB->getRow($se_query,
			array($row['away_id'],$this->_id),DB_FETCHMODE_ASSOC);
		if(isset($away)) {
			$this->tmpl->assign('away_self_score', $away['score_for']);
			$this->tmpl->assign('away_opp_score', $away['score_against']);
			$this->tmpl->assign('away_opp_sotg', $away['spirit']);
		} else {
			$this->tmpl->assign('away_self_score', "not entered");
			$this->tmpl->assign('away_opp_score', "not entered");
			$this->tmpl->assign('away_opp_sotg', "not entered");
		}

		return true;
	}
}
function scores_agree( $one, $two )
{
	if(($one['score_for'] == $two['score_against']) && ($one['score_against'] == $two['score_for']) ) {
		return true;
	} 
	return false;
}
function defaults_agree( $one, $two )
{
	if(
		($one['defaulted'] == 'us' && $two['defaulted'] == 'them')
		||
		($one['defaulted'] == 'them' && $two['defaulted'] == 'us')
	) {
		return true;
	} 
	return false;
}
