<?php
/*
 * Handle operations specific to a single game
 */

function game_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'submitscore':
			return new GameSubmit; // TODO
		case 'finalize':
			return new GameFinalizeScore; // TODO
		case 'view':
			return new GameView; // TODO
	}
	return null;
}

class GameSubmit extends Handler
{
	function initialize ()
	{
		$this->title = "Submit Game Score";
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
		$this->section = 'admin';
		return true;
	}

	function process ()
	{
		$id = var_from_getorpost('id');
		$result = db_query(
			"SELECT home_score, away_score FROM schedule WHERE game_id = %d",  $id);
		$row = db_fetch_array($result);

		if(!isset($row)) {
			$this->error_exit("That game does not exist");
		}
		if(!is_null($row['home_score']) && !is_null($row['away_score']) ) {
			$this->error_exit("The score for that game has already been submitted.");
		}
		
		$team_id = var_from_getorpost('team_id');
		$result = db_query(
			"SELECT entered_by FROM score_entry WHERE game_id = %d AND team_id = %d", $id,$team_id);

		if(db_num_rows($result) > 0) {
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
	
		$this->setLocation(array($this->title => 0));
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
		global $session;

		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$result = db_query(
			"SELECT 
				s.home_team AS home_id,
				s.away_team AS away_id
			 FROM schedule s 
			 WHERE s.game_id = %d",$id);

		if( ! db_num_rows($result) ) {
			return false;
		}
		$schedule_entry = db_fetch_array($result);
		
		$our_entry = array(
			'score_for' => var_from_getorpost('score_for'),
			'score_against' => var_from_getorpost('score_against'),
			'spirit' => var_from_getorpost('sotg'),
			'defaulted' => var_from_getorpost('defaulted'),
		);
		
		$result = db_query("SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE game_id = %d",$id);
		$opponent_entry = db_fetch_array($result);

		if( ! db_num_rows($result) ) {
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

				db_query("UPDATE schedule SET home_score = %d, away_score = %d, defaulted = '%s', approved_by = -1 WHERE game_id = %d", $data);
				if(1 != db_affected_rows()) {
					return false;
				}

				db_query("DELETE FROM score_entry WHERE game_id = %d",$id);
				if(2 != db_affected_rows()) {
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

				db_query("UPDATE schedule SET home_score = %d, away_score = %d, home_spirit = %d, away_spirit = %d, approved_by = -1 WHERE game_id = %d", $data);
				if(1 != db_affected_rows()) {
					return false;
				}

				db_query("DELETE FROM score_entry WHERE game_id = %d",$id);
				if(2 != db_affected_rows()) {
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
		
		return para($resultMessage);
	}

	function generateConfirm ($id, $team_id)
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
	
		$result = db_query(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}

		$gameInfo = db_fetch_array($result);

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
		

		$rows = array();
		if($defaulted == 'us') {
			$rows[] = array("$myName:", "0 (defaulted)");
			$rows[] = array("$opponentName:", 6);
		} else if($defaulted == 'them') {
			$rows[] = array("$myName:", 6);
			$rows[] = array("$opponentName:", "0 (defaulted)");
		} else {
			$rows[] = array("$myName:", $scoreFor . form_hidden('score_for', $scoreFor));
			$rows[] = array("$opponentName:", $scoreAgainst . form_hidden('score_against', $scoreAgainst));
			$rows[] = array("SOTG for $opponentName:", $sotg . form_hidden('sotg', $sotg));
		}

		$output .= '<div class="pairtable">' . table(null, $rows) . "</div>";
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $id, $team_id ) 
	{
		$result = db_query(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}

		$gameInfo = db_fetch_array($result);

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

		$result = db_query("SELECT score_for, score_against, defaulted FROM score_entry WHERE game_id = %d AND team_id = %d", $id, $opponentId);
				
		$opponent = db_fetch_array($result);

		if($opponent) {
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
		
		$rows = array();
		$header = array( "Team Name", "Defaulted?", "Your Score Entry", "Opponent's Score Entry", "SOTG");
	
	
		$rows[] = array(
			$myName,
			"<input type='checkbox' name='defaulted' value='us' onclick='defaultCheckboxChanged()'>",
			form_textfield("","score_for","",2,2),
			$opponentScoreAgainst,
			"&nbsp;"
		);
		
		$rows[] = array(
			$opponentName,
			"<input type='checkbox' name='defaulted' value='them' onclick='defaultCheckboxChanged()'>",
			form_textfield("","score_against","",2,2),
			$opponentScoreFor,
			form_select("", "sotg", "--", getOptionsFromRange(1,10))
				. "<font size='-2'>(<a href='/leagues/spirit_guidelines.html' target='_new'>spirit guideline</a>)</font>"
		);

		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		return $script . form($output, "POST", 0, "name='scoreform'");
	}

	function save_one_score ( $id, $team_id, $our_entry ) 
	{
		global $session;

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
		
		db_query("INSERT INTO score_entry 
			(game_id,team_id,entered_by,score_for,score_against,spirit,defaulted)
				VALUES(%d,%d,%d,%d,%d,%d,'%s')",
				$id, $team_id, $session->attr_get('user_id'), $our_entry['score_for'], $our_entry['score_against'], $our_entry['spirit'], $our_entry['defaulted']);

		if( 1 != db_affected_rows() ) {
			return false;
		}
		return true;
	}
}

class GameFinalizeScore extends Handler
{
	function initialize ()
	{
		$this->title = "Finalize Game Score";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'coordinate_game:id',
			'deny',
		);
		$this->op = 'game_finalize';
		$this->section = 'admin';
		return true;
	}

	function process ()
	{
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
		$this->setLocation(array($this->title => 0));	
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
		global $session;
	
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$defaulted = var_from_getorpost('defaulted');

		if($defaulted == 'home') {
		
			$data = array( 0, 6, 'home', $session->attr_get('user_id'), $id);
			db_query("UPDATE schedule SET home_score = %d, away_score = %d, defaulted = '%s', approved_by = %d WHERE game_id = %d", $data);
		} else if($defaulted == 'away') { 
			$data = array( 6, 0, 'away', $session->attr_get('user_id'), $id);
			db_query("UPDATE schedule SET home_score = %d, away_score = %d, defaulted = '%s', approved_by = %d WHERE game_id = %d", $data);
		} else {
			$data = array(
					var_from_getorpost('home_score'),
					var_from_getorpost('away_score'),
					var_from_getorpost('home_sotg'),
					var_from_getorpost('away_sotg'),
					$session->attr_get('user_id'),
					$id
			);
			db_query("UPDATE schedule SET home_score = %d, away_score = %d, home_spirit = %d, away_spirit = %d, approved_by = %d WHERE game_id = %d", $data);
		}

		if( 1 != db_affected_rows() ) {
			return false;
		}

		/* And remove any score_entry fields */
		db_query("DELETE FROM score_entry WHERE game_id = %d", $id);

		$league_id = db_result(db_query("SELECT league_id FROM schedule WHERE game_id = %d", $id));
		local_redirect("op=league_verifyscores&id=$league_id");
	}

	function generateConfirm( $id )
	{
		$dataInvalid = $this->isDataInvalid();
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$result = db_query(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		$gameInfo = db_fetch_array($result);

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
	
		$header = array( "Team", "Score", "SOTG");
		$rows = array(
			array($gameInfo['home_name'], $home_score, $home_sotg),
			array($gameInfo['away_name'], $away_score, $away_sotg)
		);
	
		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";

		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $id ) 
	{
		$result = db_query(
			"SELECT 
				s.game_id,
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team,
				h.name AS home_name, 
				s.away_team,
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}

		$game = db_fetch_object($result);
		
		$output = para( "Finalize the score for <b>Game $id</b> of $datePlayed between <b>$game->home_name</b> and <b>$game->away_name</b>.");
		
		$output .= form_hidden('op', $this->op);
		$output .= form_hidden('step', 'confirm');
		$output .= form_hidden('id', $id);
		$output .= "<h2>Score as entered:</h2>";
		
		$output .= game_score_entry_display( $game );
		
		$output .= "<h2>Score as approved:</h2>";
		
		$rows = array();
		
		$rows[] = array(
			"$game->home_name (home) score:",
			form_textfield('','home_score','',2,2)
				. "or default: <input type='checkbox' name='defaulted' value='home' onclick='defaultCheckboxChanged()'>"
		);
		$rows[] = array(
			"$game->away_name (away) score:",
			form_textfield('','away_score','',2,2)
				. "or default: <input type='checkbox' name='defaulted' value='away' onclick='defaultCheckboxChanged()'>"
		);
		
		$rows[] = array(
			"$game->home_name (home) assigned spirit:",
			form_select("", "home_sotg", '', getOptionsFromRange(1,10))
		);
		$rows[] = array(
			"$game->away_name (away) assigned spirit:",
			form_select("", "away_sotg", '', getOptionsFromRange(1,10))
		);

		$output .= '<div class="pairtable">' . table(null, $rows) . '</div>';
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

		return $script . form($output, "POST", 0, "name='scoreform'");
	}
}

class GameView extends Handler
{
	function initialize ()
	{
		$this->title = "View Game";
		$this->_required_perms = array(
			'require_valid_session',
			'require_var:id',
			'admin_sufficient',
			'allow'
		);
		$this->_permissions = array(
			'view_entered_scores' => false,
		);
		$this->op = 'game_view';
		$this->section = 'game';
		return true;
	}
	
	function set_permission_flags($type)
	{
		if($type == 'administrator') {
			$this->enable_all_perms();
		}
	}

	function process ()
	{

		$id = $_GET['id'];
		if(! $id) {
			return false;
		}
		
		$result = db_query(
			"SELECT 
				s.*,
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				h.name AS home_name, 
				a.name AS away_name
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		if( 1 != db_num_rows($result) ) {
			return false;
		}
		
		$game = db_fetch_object($result);
		$formattedDate = strftime("%A %B %d %Y",$game->timestamp);
		$formattedTime = strftime("%H%Mh",$game->timestamp);
		$rows[] = array("Game ID:", $game->game_id);
		$rows[] = array("Date:", $formattedDate);
		$rows[] = array("Time:", $formattedTime);

		/* TODO: league_load() */
		$league = db_fetch_object(db_query("SELECT * FROM league WHERE league_id = %d", $game->league_id));
		$leagueName = $league->name;
		if($league->tier) {
			$leagueName .= " Tier $league->tier";
		}
		$rows[] = array("League/Division:",
			l($leagueName, "op=league_view&id=$league->league_id")
		);
		
		$rows[] = array("Home Team:", 
			l($game->home_name, "op=team_view&id=$game->home_team"));
		$rows[] = array("Away Team:", 
			l($game->away_name, "op=team_view&id=$game->away_team"));

		$rows[] = array("Field:",
			l(get_field_name($game->field_id), "op=field_view&id=$game->field_id"));
			
		$rows[] = array("Round:", $game->round);

		if($game->home_score || $game->away_score) {
			$rows[] = array("Score:", "$game->home_name: $game->home_score<br /> $game->away_name: $game->away_score");
		} else {
			if( $this->_permissions['view_entered_scores'] ) {
				$rows[] = array("Score:", game_score_entry_display( $game ));
			} else {
				$rows[] = array("Score:","not yet entered");
			}
		}

		$this->setLocation(array(
			"$this->title &raquo; $game->home_name vs. $game->away_name" => 0));
		return "<div class='pairtable'>" . table(null, $rows) . "</div>";
	}
}

function game_score_entry_display( $game )
{
	$se_query = "SELECT * FROM score_entry WHERE team_id = %d AND game_id = %d";
	$home = db_fetch_array(db_query($se_query,$game->home_team,$game->game_id));
	if(!$home) {
		$home = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'spirit' => 'not entered',
			'defaulted' => 'no' 
		);
	}
	
	$away = db_fetch_array(db_query($se_query,$game->away_team,$game->game_id));
	if(!$away) {
		$away = array(
			'score_for' => 'not entered',
			'score_against' => 'not entered',
			'spirit' => 'not entered',
			'defaulted' => 'no' 
		);
	}
		
	$header = array(
		"&nbsp;",
		"$game->home_name (home)",
		"$game->away_name (away)"
	);
	
	$rows = array();
	
	$rows[] = array( "Home Score:", $home['score_for'], $away['score_against'],);	
	$rows[] = array( "Away Score:", $home['score_against'], $away['score_for'],);
	$rows[] = array( "Defaulted?", $home['defaulted'], $away['defaulted'],);
	$rows[] = array( "Opponent SOTG:", $home['spirit'], $away['spirit'],);
	
	return'<div class="listtable">' . table($header, $rows) . "</div>";
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
