<?php

require_once('Handler/team/edit.php');

class team_create extends team_edit
{
	function __construct ()
	{
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','create');
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Create Team";
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$this->team = new Team;
				$this->team->league_id = 1;	// inactive teams
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->team = new Team;
				$this->team->league_id = 1;	// inactive teams
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm( $edit );
		}
		$this->setLocation(array($this->title => 0));
		return $rc;
	}

	function perform ($edit = array() )
	{
		global $lr_session, $dbh;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		if( ! parent::perform($edit) ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT INTO leagueteams (league_id, team_id) VALUES(?, ?)');
		$sth->execute( array(1, $this->team->team_id) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		# TODO: Replace with $team->add_player($lr_session->user,'captain')
		#       and call before parent::perform()
		$sth = $dbh->prepare('INSERT INTO teamroster (team_id, player_id, status, date_joined) VALUES(?, ?, ?, NOW())');
		$sth->execute( array($this->team->team_id, $lr_session->attr_get('user_id'), 'captain'));
		if( 1 != $sth->rowCount() ) {
			return false;
		}

		return true;
	}
}

?>
