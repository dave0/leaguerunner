<?php
register_page_handler('game_submitscore', 'GameSubmit');
register_page_handler('game_finalize', 'GameFinalizeScore');

class GameSubmit extends Handler
{
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

		$this->op = 'game_submitscore';

		return true;
	}

	function process ()
	{
		global $DB;
		
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

		$step = var_from_getorpost('step');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm($id, $team_id);
				break;
			case 'perform':
				$rc = $this->perform($id, $team_id);
				break;
			default:
				$rc = $this->generateForm($id, $team_id);
		}
		
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
	
	function perform ($id, $team_id)
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
			array($id), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($schedule_entry)) {
			return false;
		}

		$opponent_entry = $DB->getRow("SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE game_id = ?", array($id),DB_FETCHMODE_ASSOC);
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
			if($this->save_one_score($id, $team_id, $our_entry) == false) {
				return false;
			}
			$resultMessage ="This score has been saved.  Once your opponent has entered their score, it will be officially posted";
		} else {
			/* See if we agree with opponent score */
			if( defaults_agree($our_entry, $opponent_entry) ) {
				// Both teams agree that a default has occurred. 
				if(
					($team_id == $schedule_entry['home_id'])
					&& ($our_entry['defaulted'] == 'us')
				) {
					$data = array( 0, 6, 'home', $id);
				} else {
					$data = array( 6, 0, 'away', $id);
				}

				$res = $DB->query("UPDATE schedule SET home_score = ?, away_score = ?, defaulted = ?, approved_by = -1 WHERE game_id = ?", $data);
				if($this->is_database_error($res)) {
					return false;
				}

				$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($id));
				if($this->is_database_error($res)) {
					return false;
				}
				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
			} else if( scores_agree($our_entry, $opponent_entry) ) {
				/* Agree. Make it official */
				if($team_id == $schedule_entry['home_id']) {
					$data = array(
						$our_entry['score_for'],
						$our_entry['score_against'],
						$opponent_entry['spirit'],
						$our_entry['spirit'],
						$id);
				} else {
					$data = array(
						$our_entry['score_against'],
						$our_entry['score_for'],
						$our_entry['spirit'],
						$opponent_entry['spirit'],
						$id);
				}

				$res = $DB->query("UPDATE schedule SET home_score = ?, away_score = ?, home_spirit = ?, away_spirit = ?, approved_by = -1 WHERE game_id = ?", $data);
				if($this->is_database_error($res)) {
					return false;
				}

				$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($id));
				if($this->is_database_error($res)) {
					return false;
				}

				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
			} else {
				if($this->save_one_score($id, $team_id, $our_entry) == false) {
					return false;
				}
				$resultMessage = "This score doesn't agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.";
			}

		}
		
		print $this->get_header();
		print h1($this->title);
		print para($resultMessage);
		print $this->get_footer();

		return true;
	}

	function generateConfirm ($id, $team_id)
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
		
		$gameInfo = $DB->getRow(
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
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($gameInfo)) {
			return false;
		}

		if($gameInfo['home_id'] == $team_id) {
			$myName = $gameInfo['home_name'];
			$opponentName = $gameInfo['away_name'];
			$opponentId = $gameInfo['away_id'];
		} else {
			$myName = $gameInfo['away_name'];
			$opponentName = $gameInfo['home_name'];
			$opponentId = $gameInfo['home_id'];
		}

		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$gameInfo['timestamp']);

		$output = para( "You have entered the following score for the $datePlayed game between $myName and $opponentName.");
		$output .= para("If this is correct, please click 'Submit' to continue.  "
			. "If not, use your back button to return to the previous page and correct the score."
		);

		$scoreFor = var_from_getorpost('score_for');
		$scoreAgainst = var_from_getorpost('score_against');
		$sotg = var_from_getorpost('sotg');
		$defaulted = var_from_getorpost('defaulted');

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('team_id', $team_id);
		$output .= form_hidden('defaulted', $defaulted);
		
		$output .= "<table border='1' cellpadding='3' cellspacing='0'>";

		if($defaulted == 'us') {
			$output .= simple_row($myName .":", "0 (defaulted)");
			$output .= simple_row($opponentName .":", 6);
		} else if($defaulted == 'them') {
			$output .= simple_row($myName .":", 6);
			$output .= simple_row($opponentName .":", "0 (defaulted)");
		} else {
			$output .= simple_row($myName .":", $scoreFor . form_hidden('score_for', $scoreFor));
			$output .= simple_row($opponentName .":", $scoreAgainst . form_hidden('score_against', $scoreAgainst));
			$output .= simple_row("SOTG for $opponentName:", $sotg . form_hidden('sotg', $sotg));
		}


		$output .= "</table>";
		$output .= para(form_submit('submit'));

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();

		return true;
	}

	function generateForm ( $id, $team_id ) 
	{
		global $DB;
		
		$gameInfo = $DB->getRow(
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
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($gameInfo)) {
			return false;
		}

		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
    if (document.scoreform.defaulted[0].checked == true) {
        document.scoreform.score_for.value = "0";
        document.scoreform.score_for.disabled = true;
        document.scoreform.score_against.value = "6";
        document.scoreform.score_against.disabled = true;
        document.scoreform.sotg.disabled = true;
        document.scoreform.defaulted[1].disabled = true;
    } else if (document.scoreform.defaulted[1].checked == true) {
        document.scoreform.score_for.value = "6";
        document.scoreform.score_for.disabled = true;
        document.scoreform.score_against.value = "0";
        document.scoreform.score_against.disabled = true;
        document.scoreform.sotg.disabled = true;
        document.scoreform.defaulted[0].disabled = true;
    } else {
        document.scoreform.score_for.disabled = false;
        document.scoreform.score_against.disabled = false;
        document.scoreform.sotg.disabled = false;
        document.scoreform.defaulted[0].disabled = false;
        document.scoreform.defaulted[1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		if($gameInfo['home_id'] == $team_id) {
			$myName = $gameInfo['home_name'];
			$opponentName = $gameInfo['away_name'];
			$opponentId = $gameInfo['away_id'];
		} else {
			$myName = $gameInfo['away_name'];
			$opponentName = $gameInfo['home_name'];
			$opponentId = $gameInfo['home_id'];
		}

		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$gameInfo['timestamp']);

		$output = para( "Submit the score for the $datePlayed game between $myName and $opponentName.");
		$output .= para("If your opponent has already entered a score, it will be displayed below.  "
  			. "If the score you enter does not agree with this score, posting of the score will "
			. "be delayed until your coordinator can confirm the correct score.");

		
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= form_hidden('team_id', $team_id);
		$output .= "<table border='1' cellpadding='3' cellspacing='0'>";
		
		$opponent = $DB->getRow(
			"SELECT 
				score_for, score_against, defaulted
				FROM score_entry WHERE game_id = ? AND team_id = ?", 
			array($id,$opponentId), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($opponent)) {
			return false;
		}
		if(!is_null($opponent)) {
			$opponentScoreFor = $opponent['score_for'];
			$opponentScoreAgainst = $opponent['score_against'];
			
			if($opponent['defaulted'] == 'us') {
				$opponentScoreFor .= " (defaulted)"; 
			} else if ($opponent['defaulted'] == 'them') {
				$opponentScoreAgainst .= " (defaulted)"; 
			}
			
		} else {
			$opponentScoreFor = "not yet entered";
			$opponentScoreAgainst = "not yet entered";
		}
		
		$output .= tr(
			td("Team Name", array( 'class' => 'schedule_title' ))
			. td("Defaulted?", array( 'class' => 'schedule_title' ))
			. td("Your Score Entry", array( 'class' => 'schedule_title' ))
			. td("Opponent's Score Entry", array( 'class' => 'schedule_title' ))
			. td("SOTG", array( 'class' => 'schedule_title' ))
		);
		$output .= tr(
			td($myName, array( 'class' => 'row_title' ))
			. td("<input type='checkbox' name='defaulted' value='us' onclick='defaultCheckboxChanged()'>", array( 'class' => 'row_title' ))
			. td(form_textfield("","score_for","",2,2), array( 'class' => 'row_title' ))
			. td( $opponentScoreAgainst, array( 'class' => 'row_title' ))
			. td( "&nbsp;", array( 'class' => 'row_title' ))
		);
		$output .= tr(
			td($opponentName, array( 'class' => 'row_title' ))
			. td("<input type='checkbox' name='defaulted' value='them' onclick='defaultCheckboxChanged()'>", array( 'class' => 'row_title' ))
			. td(form_textfield("","score_against","",2,2), array( 'class' => 'row_title' ))
			. td( $opponentScoreFor, array( 'class' => 'row_title' ))
			. td( 
				form_select("", "sotg", "--", getOptionsFromRange(1,10))
				. "<font size='-2'>(<a href='/leagues/spirit_guidelines.html' target='_new'>spirit guideline</a>)</font>",
				array( 'class' => 'row_title' ))
		);

		$output .= "</table>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		

		print $this->get_header();
		print $script;
		print h1($this->title);
		print form($output, "POST", 0, "name='scoreform'");
		print $this->get_footer();
	
		return true;
	}

	function save_one_score ( $id, $team_id, $our_entry ) 
	{
		global $DB, $session;

		if($our_entry['defaulted'] == 'us') {
			$our_entry['score_for'] = 0;
			$our_entry['score_against'] = 6;
			$our_entry['spirit'] = 0;
		} else if($our_entry['defaulted'] == 'them') {
			$our_entry['score_for'] = 6;
			$our_entry['score_against'] = 0;
			$our_entry['spirit'] = 0;
		} else {
			$our_entry['defaulted'] = 'no';
		} 
		
		$res = $DB->query("INSERT INTO score_entry 
			(game_id,team_id,entered_by,score_for,score_against,spirit,defaulted)
				VALUES(?,?,?,?,?,?,?)",
				array($id, $team_id, $session->attr_get('user_id'), $our_entry['score_for'], $our_entry['score_against'], $our_entry['spirit'], $our_entry['defaulted']));
		if($this->is_database_error($res)) {
			return false;
		}
		return true;
	}
}

class GameFinalizeScore extends Handler
{
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
		$this->op = 'game_finalize';

		return true;
	}

	function process ()
	{
		global $DB;

		$step = var_from_getorpost('step');
		$id = var_from_getorpost('id');
		
		switch($step) {
			case 'confirm':
				$rc = $this->generateConfirm( $id );
				break;
			case 'perform':
				$this->perform( $id );
				break;
			default:
				$rc = $this->generateForm($id);
		}
		
		return $rc;
	}

	function isDataInvalid()
	{
		$errors = "";

		$defaulted = var_from_getorpost('defaulted');
		if($defaulted != 'home' && $defaulted != 'away') {
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
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ( $id )
	{
		global $DB, $session;
	
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$defaulted = var_from_getorpost('defaulted');

		if($defaulted == 'home') {
			$res = $DB->query("UPDATE schedule SET 
				home_score = ?, away_score = ?, 
				defaulted = ?, approved_by = ? 
				WHERE game_id = ?", array( 
					0, 6, 'home', 
					$session->attr_get('user_id'), $id)
			);
		} else if($defaulted == 'away') { 
			$res = $DB->query("UPDATE schedule SET 
				home_score = ?, away_score = ?, 
				defaulted = ?, approved_by = ? 
				WHERE game_id = ?", array( 
					6, 0, 'away', 
					$session->attr_get('user_id'), $id)
			);
		} else {
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
					$id
			));
		}

		if($this->is_database_error($res)) {
			return false;
		}

		/* And remove any score_entry fields */
		$res = $DB->query("DELETE FROM score_entry WHERE game_id = ?", array($id));
		if($this->is_database_error($res)) {
			return false;
		}

		$league_id = $DB->getOne("SELECT league_id FROM schedule WHERE game_id = ?", array($id));
		local_redirect("op=league_verifyscores&id=$league_id");
	}

	function generateConfirm( $id )
	{
		global $DB;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$gameInfo = $DB->getRow(
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
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($gameInfo)) {
			return false;
		}
		
		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$gameInfo['timestamp']);
		
		$home_score = var_from_getorpost('home_score');
		$away_score = var_from_getorpost('away_score');
		$home_sotg = var_from_getorpost('home_sotg');
		$away_sotg = var_from_getorpost('away_sotg');
		$defaulted = var_from_getorpost('defaulted');
		

		$output = para( "You have entered the following score for the $datePlayed game between " . $gameInfo['home_name'] . " and " . $gameInfo['away_name'] . ".  ");
		$output .= para( "If this is correct, please click 'Submit' to continue.  If not, use your back button to return to the previous page and correct the score.");

		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'perform');
		$output .= form_hidden('id', $id);
		if($defaulted == "home" || $defaulted == "away") {
			$output .= form_hidden('defaulted', $defaulted);		
		} else {
			$output .= form_hidden('home_score', $home_score);		
			$output .= form_hidden('away_score', $away_score);		
			$output .= form_hidden('home_sotg', $home_sotg);		
			$output .= form_hidden('away_sotg', $away_sotg);		
		}
		$output .= "<table border='1' cellpadding='3' cellspacing='0'>";
		$output .= tr(
			td("Team")
			. td("Score")
			. td("SOTG"),
			array('class' => 'schedule_title')
		);

		if($defaulted == 'home') {
			$home_score = "0 (defaulted)";
			$away_score = "6";
			$home_sotg = "n/a";
			$away_sotg = "n/a";
		} else if ($defaulted == 'away') {
			$home_score = "6";
			$away_score = "0 (defaulted)";
			$home_sotg = "n/a";
			$away_sotg = "n/a";
		}
		
		$output .= tr(
			td($gameInfo['home_name'] . ":")
			.td($home_score)
			.td($home_sotg),
			array('class' => 'standings_item')
		);
		$output .= tr(
			td($gameInfo['away_name'] . ":")
			.td($away_score)
			.td($away_sotg),
			array('class' => 'standings_item')
		);

	
		$output .= "</table>";

		$output .= para(form_submit('submit'));

		print $this->get_header();
		print h1($this->title);
		print form($output);
		print $this->get_footer();
		return true;
	}

	function generateForm ( $id ) 
	{
		global $DB;
		
		$gameInfo = $DB->getRow(
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
			array($id), DB_FETCHMODE_ASSOC);

		if($this->is_database_error($gameInfo)) {
			return false;
		}
		
		$se_query = "SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE team_id = ? AND game_id = ?";
		$home = $DB->getRow($se_query,
			array($gameInfo['home_id'],$id),DB_FETCHMODE_ASSOC);
		if(!isset($home)) {
			$home = array(
				'score_for' => 'not entered',
				'score_against' => 'not entered',
				'spirit' => 'not entered',
				'defaulted' => 'no' 
			);
		}
		$away = $DB->getRow($se_query,
			array($gameInfo['away_id'],$id),DB_FETCHMODE_ASSOC);
		if(!isset($away)) {
			$away = array(
				'score_for' => 'not entered',
				'score_against' => 'not entered',
				'spirit' => 'not entered',
				'defaulted' => 'no' 
			);
		}
		
		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$gameInfo['timestamp']);

		$output = para( "Finalize the score for the $datePlayed game between " . $gameInfo['home_name'] . " and " . $gameInfo['away_name'] . ".");
		
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= "<table border='1' cellpadding='3' cellspacing='0'>";
		$output .= tr(
			td("Game Date")
			. td("Home Team Submission", array('colspan' => 2))
			. td("Away Team Submission", array('colspan' => 2)),
			array('class' => 'schedule_title'));
		$output .= tr(
			td($datePlayed, array('rowspan' => 5))
			. td($gameInfo['home_name'], array('colspan' => 2))
			. td($gameInfo['away_name'], array('colspan' => 2)),
			array('class' => 'schedule_item'));
		$output .= tr(
			td("Home Score:")
			. td($home['score_for'])
			. td("Home Score:")
			. td($away['score_against']),
			array('class' => 'schedule_item'));
		$output .= tr(
			td("Away Score:")
			. td($home['score_against'])
			. td("Away Score:")
			. td($away['score_for']),
			array('class' => 'schedule_item'));
		$output .= tr(
			td("Defaulted?")
			. td($home['defaulted'])
			. td("Defaulted?")
			. td($away['defaulted']),
			array('class' => 'schedule_item'));
		$output .= tr(
			td("Away SOTG:")
			. td($home['spirit'])
			. td("Home SOTG:")
			. td($away['spirit']),
			array('class' => 'schedule_item'));

		$output .= tr(
			td("Score Approval", array('colspan' => 5, 'align' => 'center')),
			array('class' => 'schedule_title'));
		$output .= tr(
			td("Final score should be:", array('rowspan' => 2))
			. td("Home Team")
			. td(form_textfield("","home_score","",2,2))
			. td("Away Team")
			. td(form_textfield("","away_score","",2,2)),
			array('class' => 'schedule_item'));

		$output .= tr(
			td("or default: "
				. "<input type='checkbox' name='defaulted' value='home' onclick='defaultCheckboxChanged()'>", array('colspan' => 2, 'align' => 'right'))
			. td("or default: "
				. "<input type='checkbox' name='defaulted' value='away' onclick='defaultCheckboxChanged()'>", array('colspan' => 2, 'align' => 'right')),
			array('class' => 'schedule_item'));

		$output .= tr(
			td("Spririt scores should be:")
			. td("Home Team")
			. td(form_select("", "home_sotg", $away['spirit'], getOptionsFromRange(1,10)))
			. td("Away Team")
			. td(form_select("", "away_sotg", $home['spirit'], getOptionsFromRange(1,10))),
			array('class' => 'schedule_item'));
			
		$output .= "</table>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
    if (document.scoreform.defaulted[0].checked == true) {
        document.scoreform.home_score.value = '0';
        document.scoreform.home_score.disabled = true;
        document.scoreform.away_score.value = '6';
        document.scoreform.away_score.disabled = true;
        document.scoreform.home_sotg.disabled = true;
        document.scoreform.away_sotg.disabled = true;
        document.scoreform.defaulted[1].disabled = true;
    } else if (document.scoreform.defaulted[1].checked == true) {
        document.scoreform.home_score.value = '6';
        document.scoreform.home_score.disabled = true;
        document.scoreform.away_score.value = '0';
        document.scoreform.away_score.disabled = true;
        document.scoreform.home_sotg.disabled = true;
        document.scoreform.away_sotg.disabled = true;
        document.scoreform.defaulted[0].disabled = true;
    } else {
        document.scoreform.home_score.disabled = false;
        document.scoreform.away_score.disabled = false;
        document.scoreform.home_sotg.disabled = false;
        document.scoreform.away_sotg.disabled = false;
        document.scoreform.defaulted[0].disabled = false;
        document.scoreform.defaulted[1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		print $this->get_header();
		print $script;
		print h1($this->title);
		print form($output, "POST", 0, "name='scoreform'");
		print $this->get_footer();
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
