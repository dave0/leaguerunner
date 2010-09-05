<?php

require_once('Handler/team/edit.php');

class team_create extends team_edit
{
	function __construct ()
	{
		$this->title = "Create Team";
		$this->team  = new Team;
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','create');
	}

	function perform ($edit = array() )
	{
		global $lr_session, $dbh;

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
