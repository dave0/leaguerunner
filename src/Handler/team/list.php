<?php

class team_list extends Handler
{
	private $letter;

	function __construct ( $letter = 'A' )
	{
		parent::__construct();

		if( preg_match('/^[A-Z0-9\."]$/', $letter) ) {
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

		$this->template_name = 'pages/team/list.tpl';

		$ops = array(
			'view' => 'team/view'
		);
		if($lr_session->has_permission('team','delete')) {
			$ops['delete'] = 'team/delete';
		}

		$this->title = 'List Teams';

		$sth = $dbh->prepare("SELECT DISTINCT UPPER(SUBSTRING(t.name,1,1)) as letter
			FROM team t
			LEFT JOIN leagueteams lt ON t.team_id = lt.team_id
			ORDER BY letter asc");
		$sth->execute();
		$letters = $sth->fetchAll(PDO::FETCH_COLUMN);

		$this->smarty->assign('current_letter', $this->letter);
		$this->smarty->assign('letters', $letters);

		$query = "SELECT t.name, t.team_id FROM team t WHERE t.name LIKE ? ORDER BY t.name";

		$sth = $dbh->prepare( $query );
		$sth->execute( array( "$this->letter%" ) );
		$sth->setFetchMode(PDO::FETCH_CLASS, 'Person', array(LOAD_OBJECT_ONLY));

		$teams = array();
		$hits = 0;
		while($team = $sth->fetch() ) {
			if( ++$hits > 1000 ) {
				error_exit("Too many search results; query terminated");
			}
			$teams[] = $team;
		}

		$this->smarty->assign('teams', $teams);
		$this->smarty->assign('ops', $ops);
		return true;
	}
}
?>
