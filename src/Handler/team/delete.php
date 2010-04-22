<?php
require_once('Handler/TeamHandler.php');

class team_delete extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','delete',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Delete Team";

		$this->setLocation(array(
			$this->team->name => "team/view/" . $this->team->team_id,
			$this->title => 0
		));

		switch($_POST['edit']['step']) {
			case 'perform':
				if ( $this->team->delete() ) {
					local_redirect(url("league/view/1"));
				} else {
					error_exit("Failure deleting team");
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
		$rows[] = array("Team Name:", check_form($this->team->name, ENT_NOQUOTES));
		if($this->team->website) {
			$rows[] = array("Website:", l($this->team->website, $this->team->website));
		}
		$rows[] = array("Shirt Colour:", check_form($this->team->shirt_colour, ENT_NOQUOTES));
		$rows[] = array("League/Tier:", l($this->team->league_name, "league/view/" . $this->team->league_id));

		$rows[] = array("Team Status:", $this->team->status);

		/* and, grab roster */
		$sth = $dbh->prepare('SELECT COUNT(r.player_id) as num_players FROM teamroster r WHERE r.team_id = ?');
		$sth->execute( array( $this->team->team_id) );

		$rows[] = array("Num. players on roster:", $sth->fetchColumn());

		$output = form_hidden('edit[step]', 'perform');
		$output .= "<p>Do you really wish to delete this team?</p>";
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= form_submit('submit');

		return form($output);
	}
}

?>
