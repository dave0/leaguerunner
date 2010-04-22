<?php
require_once('Handler/LeagueHandler.php');
// TODO: Common email-list displaying, should take query as argument, return
// formatted list.
class league_captemail extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view',$this->league->league_id, 'captain emails');
	}

	function process ()
	{
		global $dbh;

		$this->title = 'Captain Emails';
		global $lr_session;

		$sth = $dbh->prepare(
			"SELECT
				p.firstname, p.lastname, p.email
			FROM
				leagueteams l, teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				l.league_id = ?
				AND l.team_id = r.team_id
				AND (r.status = 'coach' OR r.status = 'captain' OR r.status = 'assistant')
				AND p.user_id != ?
			ORDER BY
				p.lastname, p.firstname");

		$sth->execute(array(
			$this->league->league_id,
			$lr_session->user->user_id));


		$emails = array();
		$names = array();
		while($user = $sth->fetchObject() ) {
			$names[] = "$user->firstname $user->lastname";
			$emails[] = $user->email;
		}

		if( ! count( $emails ) ) {
			error_exit("That league contains no teams.");
		}

		$this->setLocation(array(
			$this->league->fullname => "league/view/" . $this->league->league_id,
			$this->title => 0
		));

		$list = create_rfc2822_address_list($emails, $names, true);
		$output = para("You can cut and paste the emails below into your addressbook, or click " . l('here to send an email', "mailto:$list") . " right away.");

		$output .= pre($list);
		return $output;
	}
}

?>
