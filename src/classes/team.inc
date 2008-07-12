<?php
class Team extends LeaguerunnerObject
{
	function __construct( $array = array() ) 
	{
		global $dbh;

		// Fixups 
		if($this->league_tier) {
			$this->league_name = sprintf("$this->league_name Tier %02d", $this->league_tier);
		}

		// Clean up website
		if( $this->website ) {
			if(strncmp($this->website, "http://", 7) != 0) {
				$this->website = "http://" .$this->website;
			}
		}

		// Set default for calculated values to -1, so that load-on-demand
		// will work
		$this->_avg_skill = null;
		$this->_player_count = null;
		$this->home_games = -1;
		$this->away_games = -1;

		return true;
	}

	/**
	 * Add a player to the roster, with the given status
	 */
	function add_player( &$player, $status )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	/**
	 * Update status of a player currently on the roster
	 */
	function set_player_status( &$player, $status )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	/**
	 * Remove a player from the roster.
	 */
	function remove_player( &$player )
	{
		if( !is_object($player) ) {
			$object->user_id = $player;
			$player = &$object;
		}

		// TODO 
		return false;
	}

	function count_players()
	{
		global $dbh;
		
		if( is_null($this->_player_count) ) {
			$sth = $dbh->prepare("SELECT COUNT(r.player_id) FROM teamroster r WHERE r.status IN ('player', 'captain', 'assistant') AND r.team_id = ?");
			$sth->execute(array( $this->team_id));
			$this->_player_count = $sth->fetchColumn();
		}

		return $this->_player_count;
	}

	function save ()
	{
		global $dbh;

		if(! count($this->_modified_fields)) {
			// No modifications, no need to save
			return true;
		}

		if( ! $this->_in_database ) {
			if( ! $this->create() ) {
				error_exit("Couldn't create team record");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			$fields[] = "$key = ?";
			$fields_data[] = $this->{$key};
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}


		$sth = $dbh->prepare('UPDATE team SET '
			. join(", ", $fields)
			. ' WHERE team_id = ?');

		$fields_data[] = $this->team_id;

		$sth->execute( $fields_data );
		if(1 < $sth->rowCount()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);

		# TODO: process roster list and add/remove as necessary

		return true;
	}

	// TODO: this belongs in league.inc
	function validate_unique ($name)
	{
		global $dbh;
		$opt = '';
		$params = array(
			$name,
		);

		$sth = $dbh->prepare('SELECT year, season, day
			FROM league
			WHERE league_id = ?');
	
		$sth->execute( array( $this->league_id ) );

		$league_data = $sth->fetch(PDO::FETCH_ASSOC);
		foreach( array_keys( $league_data ) as $key )
		{
			if( isset( $league_data[$key] ) && $league_data[$key] != '' ) {
				$opt .= " AND league.$key = ?";
				$params[] = $league_data[$key];
			} else {
				$opt .= " AND league.$key IS NULL";
			}
		}

		if (isset ($this->team_id)) {
			$opt .= ' AND team.team_id != ?';
			$params[] = $this->team_id;
		}

		$sth = $dbh->prepare('SELECT COUNT(*) FROM team, leagueteams, league
					WHERE team.name = ? 
						AND team.team_id = leagueteams.team_id
						AND leagueteams.league_id = league.league_id
					' . $opt);
		$sth->execute( $params );

		return ($sth->fetchColumn() == 0 );
	}

	function create ()
	{
		global $dbh;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->name ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT INTO team (name) VALUES(?)'); 
		$sth->execute( array( $this->name ) );
		if( 1 != $sth->rowCount() ) {
			return false;
		}
		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM team');
		$sth->execute();
		$this->team_id = $sth->fetchColumn();

		return true;
	}

	/*
	 * Wow, this would be so much safer if we used transactions...
	 */
	function delete()
	{
		global $dbh;
		if ( ! $this->_in_database ) {
			return false;
		}

		// Can only delete inactive teams
		if ( $this->league_id != 1 ) {
			error_exit("Cannot delete team: Team must be in the 'Inactive Teams' league to be deleted");
		}

		// Check that this team is not scheduled for any games.  If so, we
		// bail
		$sth = $dbh->prepare('SELECT COUNT(*) FROM schedule WHERE home_team = :team_id OR away_team = :team_id');
		$sth->execute( array( team_id => $this->team_id ) );
		if( $sth->fetchColumn() > 0) {
			error_exit("Cannot delete team: Team must not have any games on the schedule");
		}

		// If we have anyone on the roster, remove 'em
		$sth = $dbh->prepare('DELETE FROM teamroster WHERE team_id = ?');
		$sth-> execute( array($this->team_id) );

		// If we're in a league, remove ourself
		$sth = $dbh->prepare('DELETE FROM leagueteams WHERE team_id = ?');
		$sth-> execute( array($this->team_id) );

		// Delete from the team table
		$sth = $dbh->prepare('DELETE FROM team WHERE team_id = ?');
		$sth-> execute( array($this->team_id) );

		return true;
	}

	/**
	 * Calculates the "Spence Balancing Factor" or SBF for the team.
	 * This is the average of all score differentials for games played 
	 * to-date.  A lower value indicates more even match-ups with opponents.
	 *
	 * The team SBF can be compared to the SBF for the division.  If it's too far
	 * off from the division/league SBF, it's an indication that the team is
	 * involved in too many blowout wins/losses.
	 */
	function calculate_sbf()
	{
		global $dbh;
		$sth = $dbh->prepare('SELECT ROUND(AVG(ABS(s.home_score - s.away_score)),2) FROM schedule s WHERE s.home_team = :id OR s.away_team = :id');
		$sth->execute(array( ':id' => $this->team_id) );

		$sbf = $sth->fetchColumn();

		if( $sbf == "") {
			$sbf = "n/a";
		}

		return $sbf;
	}

	/** 
	 * Calculate the average skill for this team
	 */
	function avg_skill()
	{
		global $dbh;
		
		if( is_null($this->_avg_skill) ) {
			$sth = $dbh->prepare('SELECT ROUND(AVG(p.skill_level),2) FROM teamroster r INNER JOIN person p ON (r.player_id = p.user_id) WHERE r.team_id = ?');
			$sth->execute(array( $this->team_id));
			$this->_avg_skill = $sth->fetchColumn();
		}

		return $this->_avg_skill;
	}


	/**
	 * Home/away ratio.
	 * If > 1, team has more home games.  if < 1, team has more away games
	 */
	function home_away_ratio()
	{
		global $dbh;

		if( $this->home_games == -1 ) {
			$sth = $dbh->prepare('SELECT count(*) FROM schedule WHERE home_team = ?');
			$sth->execute( array( $this->team_id ) );
			$this->home_games = $sth->fetchColumn();

			$sth = $dbh->prepare('SELECT count(*) FROM schedule WHERE away_team = ?');
			$sth->execute( array( $this->team_id ) );
			$this->away_games = $sth->fetchColumn();
		}

		# avoid divide-by-zero
		if( $this->away_games == 0) {
			return 2;
		}
		return ($this->home_games / $this->away_games);
	}

	/**
	 * Move team to another league.
	 */
	function move_team_to( $league_id, $rank = 0 ) 
	{
		global $dbh;

		$sth = $dbh->prepare('UPDATE leagueteams SET league_id = ?, rank = ? WHERE team_id = ? AND league_id = ?');
		$sth->execute( array($league_id, $rank, $this->team_id, $this->league_id) );

		if( 1 != $sth->rowCount() ) {
			error_exit("Couldn't move team between leagues");
		}

		$this->league_id = $league_id;
		$this->rank = $rank;

		return true;
	}


	/**
	 * Swap this team with another team, updating schedules
	 */
	function swap_team_with( $other )
	{
		if( ! $other ) {
			return false;
		}

		# Get league ID and rank from $this and $other
		$this_rank = $this->rank;
		$this_league_id = $this->league_id;
		$other_rank = $other->rank;
		$other_league_id = $other->league_id;

		// Moving to same league is silly
		if( $this_league_id == $other_league_id ) {
			return false;
		}

		# Move team to other league
		$this->move_team_to( $other_league_id, $other_rank);

		$other->move_team_to( $this_league_id, $this_rank);

		# Get future games for $this and $other
		$this_games = game_load_many(array('either_team' => $this->team_id, 'game_date_future' => 1));
		$other_games = game_load_many(array('either_team' => $other->team_id, 'game_date_future' => 1));

		# Update future unplayed games in both leagues
		foreach ($this_games as $game) {
			if( $game->home_id == $this->team_id ) {
				$game->set('home_team', $other->team_id);
			} else {
				$game->set('away_team', $other->team_id);
			}
			$game->save();
		}
		foreach ($other_games as $game) {
			if( $game->home_id == $other->team_id ) {
				$game->set('home_team', $this->team_id);
			} else {
				$game->set('away_team', $this->team_id);
			}
			$game->save();
		}

		return true;
	}
}

function team_query( $array = array() )
{
	global $TZ_ADJUST, $dbh;

	$order = '';
	$query = array();
	$params = array();

	foreach ($array as $key => $value) {
		switch( $key ) {
			case '_extra':
				/* Just slap on any extra query fields desired */
				$query[] = $value;
				break;
			case '_order':
				$order = ' ORDER BY ' . $value;
				break;
			case 'league_id':
				$query[]  = "l.$key = ?";
				$params[] = $value;
				break;
			default:
				$query[]  = "t.$key = ?";
				$params[] = $value;
		}
	}


	$sth = $dbh->prepare("SELECT 
		t.*,
		1 AS _in_database,
		s.rank,
		l.name AS league_name,
		l.tier AS league_tier,
		l.day AS league_day, 
		l.season AS league_season, 
		l.league_id,
		(UNIX_TIMESTAMP(CONCAT(roster_deadline,' 23:59:59')) + ($TZ_ADJUST * 60)) AS roster_deadline
		FROM team t
		INNER JOIN leagueteams s ON (s.team_id = t.team_id)
		INNER JOIN league l ON (s.league_id = l.league_id)
		WHERE " . implode(' AND ',$query) . $order);
	$sth->execute( $params );

	return $sth;
}

function team_load( $array = array() )
{
	$result = team_query( $array );
	if( $result->rowCount() != 1 ) {
		return null;
	}
	return $result->fetchObject('Team');
}

function team_load_many( $array = array() )
{
	$sth = team_query( &$array );

	$teams = array();
	while( $t = $sth->fetchObject('Team', array(LOAD_RELATED_DATA))) {
		$teams[$t->team_id] = $t;
	}

	return $teams;
}

?>
