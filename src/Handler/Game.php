<?php
/*
 * Handle operations specific to a single game
 */

function game_dispatch() 
{
	$op = arg(1);
	switch($op) {
		case 'submitscore':
			return new GameSubmit;
		case 'view':
			return new GameView;
/* TODO:
		case 'edit':
			return new GameEdit;
*/
	}
	return null;
}

class GameSubmit extends Handler
{
	function initialize ()
	{
		$this->title = "Submit Game Score";
		$this->section = 'admin';
		return true;
	}

	function has_permission ()
	{
		global $session;
		if(!$session->is_valid()) {
			$this->error_exit("You do not have a valid session");
			return false;
		}

		$gameID = arg(2);
		$teamID = arg(3);
		if( !$gameID ) {
			return false;
		}

		if( !$teamID ) {
			/* TODO: write code to allow coordinators/admins to submit
			 * final scores for entire games, not just one team.  Probably
			 * should do it as a game/edit/ handler.
			 */
			return false;
		}
		
		$result = db_query("SELECT home_team, away_team, league_id FROM schedule WHERE game_id = %d", $gameID);
		$scheduleInfo = db_fetch_array($result);

		if( $teamID != $scheduleInfo['home_team'] && $teamID != $scheduleInfo['away_team'] ) {
			$this->error_exit("That team did not play in that game!");
		}
		
		if($session->is_admin()) {
			$this->set_permission_flags('administrator');
			return true;
		}

		if($session->is_coordinator_of($scheduleInfo['league_id'])) {
			$this->set_permission_flags('coordinator');
			return true;
		}

		if($session->is_captain_of($teamID)) {
			$this->set_permission_flags('captain');
			return true;
		}

		return false;
	}

	function process ()
	{
		$gameID = arg(2);
		$teamID = arg(3);

		$result = db_query(
			"SELECT 
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				s.home_team AS home_id,
				h.name AS home_name, 
				s.away_team AS away_id,
				a.name AS away_name,
				s.home_score,
				s.away_score
			 FROM schedule s 
			 	LEFT JOIN team h ON (h.team_id = s.home_team) 
				LEFT JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $gameID);

		if( 1 != db_num_rows($result) ) {
			$this->error_exit("That game does not exist");
		}

		$scheduleInfo = db_fetch_array($result);
		
		if(!is_null($scheduleInfo['home_score']) && !is_null($scheduleInfo['away_score']) ) {
			$this->error_exit("The score for that game has already been submitted.");
		}
		
		$result = db_query(
			"SELECT entered_by FROM score_entry WHERE game_id = %d AND team_id = %d", $gameID, $teamID);

		if(db_num_rows($result) > 0) {
			$this->error_exit("The score for your team has already been entered.");
		}

		$edit = $_POST['edit'];
		
		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($gameID, $teamID, $scheduleInfo, $edit);
				break;
			case 'perform':
				$rc = $this->perform($gameID, $teamID, $scheduleInfo, $edit);
				break;
			default:
				$rc = $this->generateForm($gameID, $teamID, $scheduleInfo);
		}
	
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function isDataInvalid( $edit )
	{
		$errors = "";
		
		if( $edit['defaulted'] ) {
			switch($defaulted) {
				case 'us':
				case 'them':
				case '':
					return false;  // Ignore other data in cases of default.
				default:
					return "An invalid value was specified for default.";
			}
		}
		
		if( !validate_number($edit['score_for']) ) {
			$errors .= "<br>You must enter a valid number for your score";
		}

		if( !validate_number($edit['score_against']) ) {
			$errors .= "<br>You must enter a valid number for your opponent's score";
		}

		if( !validate_number($edit['sotg']) ) {
			$errors .= "<br>You must enter a valid number for your opponent's SOTG";
		}
		
		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
	
	function perform ($gameID, $teamID, $scheduleInfo, $edit)
	{
		global $session;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$our_entry = array(
			'score_for' => $edit['score_for'],
			'score_against' => $edit['score_against'],
			'spirit' => $edit['sotg'],
			'defaulted' => $edit['defaulted'],
		);
		
		$result = db_query("SELECT score_for, score_against, spirit, defaulted FROM score_entry WHERE game_id = %d",$gameID);
		$opponent_entry = db_fetch_array($result);

		if( ! db_num_rows($result) ) {
			// No opponent entry, so just add to the score_entry table
			if($this->save_one_score($gameID, $teamID, $our_entry) == false) {
				return false;
			}
			$resultMessage ="This score has been saved.  Once your opponent has entered their score, it will be officially posted";
		} else {
			/* See if we agree with opponent score */
			if( defaults_agree($our_entry, $opponent_entry) ) {
				// Both teams agree that a default has occurred. 
				if(
					($teamID == $scheduleInfo['home_id'])
					&& ($our_entry['defaulted'] == 'us')
				) {
					$data = array( 0, 6, 'home', $gameID);
				} else {
					$data = array( 6, 0, 'away', $gameID);
				}

				db_query("UPDATE schedule SET home_score = %d, away_score = %d, defaulted = '%s', approved_by = -1 WHERE game_id = %d", $data);
				if(1 != db_affected_rows()) {
					return false;
				}

				db_query("DELETE FROM score_entry WHERE game_id = %d",$gameID);
				if(1 != db_affected_rows()) {
					return false;
				}
				
				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
			} else if( scores_agree($our_entry, $opponent_entry) ) {
				/* Agree. Make it official */
				if($teamID == $scheduleInfo['home_id']) {
					$data = array(
						$our_entry['score_for'],
						$our_entry['score_against'],
						$opponent_entry['spirit'],
						$our_entry['spirit'],
						$gameID);
				} else {
					$data = array(
						$our_entry['score_against'],
						$our_entry['score_for'],
						$our_entry['spirit'],
						$opponent_entry['spirit'],
						$gameID);
				}

				db_query("UPDATE schedule SET home_score = %d, away_score = %d, home_spirit = %d, away_spirit = %d, approved_by = -1 WHERE game_id = %d", $data);
				if(1 != db_affected_rows()) {
					return false;
				}

				db_query("DELETE FROM score_entry WHERE game_id = %d",$gameID);
				if(1 != db_affected_rows()) {
					return false;
				}

				$resultMessage = "This score agrees with the score submitted by your opponent.  It will now be posted as an official game result.";
			} else {
				if($this->save_one_score($gameID, $teamID, $our_entry) == false) {
					return false;
				}
				$resultMessage = "This score doesn't agree with the one your opponent submitted.  Because of this, the score will not be posted until your coordinator approves it.";
			}
		}
		
		return para($resultMessage);
	}

	function generateConfirm ($gameID, $teamID, $scheduleInfo, $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			$this->error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}
	
		if($scheduleInfo['home_id'] == $teamID) {
			$myName = $scheduleInfo['home_name'];
			$opponentName = $scheduleInfo['away_name'];
			$opponentId = $scheduleInfo['away_id'];
		} else {
			$myName = $scheduleInfo['away_name'];
			$opponentName = $scheduleInfo['home_name'];
			$opponentId = $scheduleInfo['home_id'];
		}

		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$scheduleInfo['timestamp']);

		$output = para( "You have entered the following score for the $datePlayed game between $myName and $opponentName.");
		$output .= para("If this is correct, please click 'Submit' to continue.  "
			. "If not, use your back button to return to the previous page and correct the score."
		);

		$output .= form_hidden('edit[step]', 'perform');
		$output .= form_hidden('edit[defaulted]', $edit['defaulted']);

		$rows = array();
		switch($edit['defaulted']) {
		case 'us':
			$rows[] = array("$myName:", "0 (defaulted)");
			$rows[] = array("$opponentName:", 6);
			break;
		case 'them':
			$rows[] = array("$myName:", 6);
			$rows[] = array("$opponentName:", "0 (defaulted)");
			break;
		default:
			$rows[] = array("$myName:", $edit['score_for'] . form_hidden('edit[score_for]', $edit['score_for']));
			$rows[] = array("$opponentName:", $edit['score_against'] . form_hidden('edit[score_against]', $edit['score_against']));
			$rows[] = array("SOTG for $opponentName:", $edit['sotg'] . form_hidden('edit[sotg]', $edit['sotg']));
			break;
		}

		$output .= '<div class="pairtable">' . table(null, $rows) . "</div>";
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function generateForm ( $gameID, $teamID, $scheduleInfo ) 
	{

		if($scheduleInfo['home_id'] == $teamID) {
			$myName = $scheduleInfo['home_name'];
			$opponentName = $scheduleInfo['away_name'];
			$opponentId = $scheduleInfo['away_id'];
		} else {
			$myName = $scheduleInfo['away_name'];
			$opponentName = $scheduleInfo['home_name'];
			$opponentId = $scheduleInfo['home_id'];
		}

		$datePlayed = strftime("%A %B %d %Y, %H%Mh",$scheduleInfo['timestamp']);

		$output = para( "Submit the score for the $datePlayed game between $myName and $opponentName.");
		$output .= para("If your opponent has already entered a score, it will be displayed below.  "
  			. "If the score you enter does not agree with this score, posting of the score will "
			. "be delayed until your coordinator can confirm the correct score.");

		$output .= form_hidden('edit[step]', 'confirm');

		$result = db_query("SELECT score_for, score_against, defaulted FROM score_entry WHERE game_id = %d AND team_id = %d", $gameID, $opponentId);
				
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
			"<input type='checkbox' name='edit[defaulted]' value='us' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_for]","",2,2),
			$opponentScoreAgainst,
			"&nbsp;"
		);
		
		$rows[] = array(
			$opponentName,
			"<input type='checkbox' name='edit[defaulted]' value='them' onclick='defaultCheckboxChanged()'>",
			form_textfield("","edit[score_against]","",2,2),
			$opponentScoreFor,
			form_select("", "edit[sotg]", "--", getOptionsFromRange(1,10))
				. "<font size='-2'>(<a href='/leagues/spirit_guidelines.html' target='_new'>spirit guideline</a>)</font>"
		);

		$output .= '<div class="listtable">' . table($header, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));
		
		$script = <<<ENDSCRIPT
<script type="text/javascript"> <!--
  function defaultCheckboxChanged() {
    if (document.forms[0].elements['edit[defaulted]'][0].checked == true) {
        document.forms[0].elements['edit[score_for]'].value = "0";
        document.forms[0].elements['edit[score_for]'].disabled = true;
        document.forms[0].elements['edit[score_against]'].value = "6";
        document.forms[0].elements['edit[score_against]'].disabled = true;
        document.forms[0].elements['edit[sotg]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][1].disabled = true;
    } else if (document.forms[0].elements['edit[defaulted]'][1].checked == true) {
        document.forms[0].elements['edit[score_for]'].value = "6";
        document.forms[0].elements['edit[score_for]'].disabled = true;
        document.forms[0].elements['edit[score_against]'].value = "0";
        document.forms[0].elements['edit[score_against]'].disabled = true;
        document.forms[0].elements['edit[sotg]'].disabled = true;
        document.forms[0].elements['edit[defaulted]'][0].disabled = true;
    } else {
        document.forms[0].elements['edit[score_for]'].disabled = false;
        document.forms[0].elements['edit[score_against]'].disabled = false;
        document.forms[0].elements['edit[sotg]'].disabled = false;
        document.forms[0].elements['edit[defaulted]'][0].disabled = false;
        document.forms[0].elements['edit[defaulted]'][1].disabled = false;
    }
  }
// -->
</script>
ENDSCRIPT;

		return $script . form($output);
	}

	function save_one_score ( $gameID, $teamID, $our_entry ) 
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
				$gameID, $teamID, $session->attr_get('user_id'), $our_entry['score_for'], $our_entry['score_against'], $our_entry['spirit'], $our_entry['defaulted']);

		if( 1 != db_affected_rows() ) {
			return false;
		}
		
		return true;
	}
}

class GameView extends Handler
{
	function initialize ()
	{
		$this->title = "View Game";
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'allow'
		);
		
		$this->_permissions = array(
			'view_entered_scores' => false,
		);
		
		$this->section = 'league';
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

		$id = arg(2);

		$result = db_query(
			"SELECT 
				s.*,
				UNIX_TIMESTAMP(s.date_played) as timestamp, 
				h.name AS home_name, 
				a.name AS away_name
			 FROM schedule s 
			 	INNER JOIN team h ON (h.team_id = s.home_team) 
				INNER JOIN team a ON (a.team_id = s.away_team)
			 WHERE s.game_id = %d", $id);
			 
		if( 1 != db_num_rows($result) ) {
			$this->error_exit('That game does not exist');
		}
		
		$game = db_fetch_object($result);
		$formattedDate = strftime("%A %B %d %Y",$game->timestamp);
		$formattedTime = strftime("%H%Mh",$game->timestamp);
		$rows[] = array("Game ID:", $game->game_id);
		$rows[] = array("Date:", $formattedDate);
		$rows[] = array("Time:", $formattedTime);

		$league = league_load( array('league_id' => $game->league_id) );
		$rows[] = array("League/Division:",
			l($league->fullname, "league/view/$league->league_id")
		);
		
		$rows[] = array("Home Team:", 
			l($game->home_name, "team/view/$game->home_team"));
		$rows[] = array("Away Team:", 
			l($game->away_name, "team/view/$game->away_team"));

		$rows[] = array("Field:",
			l(get_field_name($game->field_id), "field/view/$game->field_id"));
			
		$rows[] = array("Round:", $game->round);

		if($game->home_score || $game->away_score) {
			// TODO: show default status here.
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
