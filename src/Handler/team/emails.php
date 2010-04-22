<?php
require_once('Handler/TeamHandler.php');

class team_emails extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','email',$this->team->team_id);
	}

	function process ()
	{
		global $lr_session, $dbh;
		$this->title = 'Player Emails';
		$sth = $dbh->prepare('SELECT
				p.firstname, p.lastname, p.email
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?
			AND
				p.user_id != ?
			ORDER BY
				p.lastname, p.firstname');
		$sth->execute( array( $this->team->team_id, $lr_session->user->user_id) );

		$emails = array();
		$names = array();
		while($user = $sth->fetch(PDO::FETCH_OBJ)) {
			$names[] = "$user->firstname $user->lastname";
			$emails[] = $user->email;
		}
		if( count($names) <= 0 ) {
			return false;
		}

		$team = team_load( array('team_id' => $this->team->team_id) );

		$this->setLocation(array(
			$team->name => "team/view/" . $this->team->team_id,
			$this->title => 0));

		$list = create_rfc2822_address_list($emails, $names, true);
		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', "mailto:$list") . " right away.");

		$output .= pre($list);
		return $output;
	}
}

?>
