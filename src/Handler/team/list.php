<?php

class team_list extends Handler
{
	private $letter;

	function __construct ( $letter = 'A' )
	{
		parent::__construct();

		if( preg_match('/^[A-Z]$/', $letter) ) {
			$this->letter = $letter;
		} else {
			$this->letter = 'A';
		}
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','list');
	}

	function process ()
	{
		global $lr_session, $dbh;
		$ops = array(
			array(
				'name' => 'view',
				'target' => 'team/view/'
			),
		);
		if($lr_session->has_permission('team','delete')) {
			$ops[] = array(
				'name' => 'delete',
				'target' => 'team/delete/'
			);
		}

		$this->title = 'List Teams';

		$sth = $dbh->prepare("SELECT DISTINCT UPPER(SUBSTRING(t.name,1,1)) as letter
			FROM team t
			LEFT JOIN leagueteams lt ON t.team_id = lt.team_id
			LEFT JOIN league l       ON lt.league_id = l.league_id
			WHERE l.status = 'open'
			ORDER BY letter asc");
		$sth->execute();
		$letters = $sth->fetchAll(PDO::FETCH_COLUMN);

		$letterLinks = array();
		foreach($letters as $curLetter) {
			if($curLetter == $this->letter) {
				$letterLinks[] = "<b>$curLetter</b>";
			} else {
				$letterLinks[] = l($curLetter, url("team/list/$curLetter"));
			}
		}
		$output = para(theme_links($letterLinks, "&nbsp;&nbsp;"));
		$query = "SELECT
				t.name AS value,
				t.team_id AS id
			FROM team t
			LEFT JOIN leagueteams lt ON t.team_id = lt.team_id
			LEFT JOIN league l       ON lt.league_id = l.league_id
			WHERE l.status = 'open'
			AND
				t.name LIKE ?
			ORDER BY t.name";
		$output .= $this->generateSingleList($query, $ops, array("$this->letter%"));
		return $output;
	}
}
?>
