<?php

class GameHandler extends Handler
{
	protected $game;
	protected $league;

	function __construct ( $id )
	{
		$this->game = game_load( array('game_id' => $id) );

		if(!$this->game) {
			error_exit("That game does not exist");
		}

		game_add_to_menu( $this->get_league(), $this->game );
	}

	function get_league ( )
	{
		if( ! $this->league ) {
			$this->league = league_load( array('league_id' => $this->game->league_id) );
			league_add_to_menu( $this->league );
		}
		return $this->league;
	}

	function score_entry_display( )
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT * FROM score_entry WHERE team_id = ? AND game_id = ?');
		$sth->execute(array($this->game->home_team, $this->game->game_id));
		$home = $sth->fetch();

		if(!$home) {
			$home = array(
				'score_for' => 'not entered',
				'score_against' => 'not entered',
				'defaulted' => 'no'
			);
		} else {
			$entry_person = person_load( array('user_id' => $home['entered_by']));
			$home['entered_by'] = l($entry_person->fullname, "person/view/$entry_person->user_id");
		}

		$sth->execute(array($this->game->away_team, $this->game->game_id));
		$away = $sth->fetch();
		if(!$away) {
			$away = array(
				'score_for' => 'not entered',
				'score_against' => 'not entered',
				'defaulted' => 'no'
			);
		} else {
			$entry_person = person_load( array('user_id' => $away['entered_by']));
			$away['entered_by'] = l($entry_person->fullname, "person/view/$entry_person->user_id");
		}

		$header = array(
			"&nbsp;",
			$this->game->home_name . ' (home)',
			$this->game->away_name . ' (away)'
		);

		$rows = array();

		$rows[] = array( "Home Score:", $home['score_for'], $away['score_against'],);
		$rows[] = array( "Away Score:", $home['score_against'], $away['score_for'],);
		$rows[] = array( "Defaulted?", $home['defaulted'], $away['defaulted'],);
		$rows[] = array( "Entered By:", $home['entered_by'], $away['entered_by'],);
		$rows[] = array( "Entry time:", $home['entry_time'], $away['entry_time'],);
		return'<div class="listtable">' . table($header, $rows) . "</div>";
	}


}
?>
