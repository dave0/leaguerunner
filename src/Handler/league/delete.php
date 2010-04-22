<?php
require_once('Handler/LeagueHandler.php');
class league_delete extends Handler
{
	function has_permission ()
	{
		global $lr_session;

		if(!$this->league) {
			error_exit("That league does not exist");
		}

		return $lr_session->has_permission('league','delete',$this->league->league_id);
	}

	function process ()
	{
		$this->title = "Delete League";

		$this->setLocation(array(
			$this->team->name => "league/view/" . $this->league->team_id,
			$this->title => 0
		));

		switch($_POST['edit']['step']) {
			case 'perform':
				if ( $this->league->delete() ) {
					local_redirect(url("league/list"));
				} else {
					error_exit("Failure deleting league");
				}
				break;
			case 'confirm':
			default:
				return $this->generateConfirm();
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function generateConfirm ()
	{
		global $dbh;
		$rows = array();
		$rows[] = array("League Name:", check_form($this->league->name, ENT_NOQUOTES));
		$output = form_hidden('edit[step]', 'perform');
		$output .= "<p>Do you really wish to delete this league?</p>";
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= form_submit('submit');

		return form($output);
	}
}

?>
