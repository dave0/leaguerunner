<?php

class Team extends LeaguerunnerObject
{
	var $team_id;
	var $name;
	var $website;
	var $shirt_colour;
	var $home_field;
	var $region_preference;
	var $status;
	var $rating;
	var $league_name;
	var $league_tier;
	var $roster;
	var $roster_count;
	var $preferred_ratio;
	var $notes;

	public static $roster_positions = array(
		'player'            => "regular player",
		'substitute'        => "substitute player",
		'captain_request'   => "request to join by captain",
		'player_request'    => "request to join by player",
		'coach'	            => "coach",
		'captain'	    => "captain",
		'assistant'	    => "assistant captain",
		'none'              => "not on team",
	);

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
		$this->preferred_ratio = -1;

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

	function get_roster()
	{
		global $dbh, $lr_session;

		$sth = $dbh->prepare(
			"SELECT
				p.user_id as id,
				CONCAT(p.firstname, ' ', p.lastname) as fullname,
				p.firstname,
				p.lastname,
				p.email,
				p.gender,
				p.shirtsize,
				p.skill_level,
				p.status AS player_status,
				r.status,
				r.date_joined,
				p.show_gravatar
			FROM
				teamroster r
				LEFT JOIN person p ON (r.player_id = p.user_id)
			WHERE
				r.team_id = ?
			ORDER BY r.status, p.gender, p.lastname");
		$sth->execute(array($this->team_id));

		$this->roster = array();
		$this->roster_count = 0;
		while($player = $sth->fetchObject('Person') ) {

			if($lr_session->has_permission('team','player status', $this->team_id, $player->id) ) {
				$player->_modify_status = 1;
			} else {
				$player->_modify_status = 0;
			}
			if( $player->player_status == 'active' && ($player->status == 'captain' || $player->status == 'assistant' || $player->status == 'player') ) {
				$this->roster_count++;
			}

			$this->roster[] = $player;
		}
	}

	function check_roster_conflict()
	{
		global $dbh;

		foreach($this->roster as $player) {
			$sth = $dbh->prepare("SELECT COUNT(*) from
					league l, leagueteams t, teamroster r
				WHERE
					l.year = ? AND l.season = ? AND l.day = ?
					AND r.status != 'substitute'
					AND l.schedule_type != 'none'
					AND l.league_id = t.league_id
					AND l.status = 'open'
					AND t.team_id = r.team_id
					AND r.player_id = ?");
			$sth->execute(array(
				$this->league_year,
				$this->league_season,
				$this->league_day,
				$player->id
			));

			$player->roster_conflict = ($sth->fetchColumn() > 1);
		}
	}

	function count_players()
	{
		global $dbh;

		if( is_null($this->_player_count) ) {
			$sth = $dbh->prepare(
			    "SELECT COUNT(p.user_id)
			     FROM teamroster r LEFT JOIN person p ON (p.user_id = r.player_id)
			     WHERE
			        p.status = 'active'
				AND r.status IN ('player', 'captain', 'assistant')
				AND r.team_id = ?");
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
				# not entirely correct to guess this, but it's
				# the only plausible answer for ->create()
				# failure at this point.
				error_exit("A team with that name already exists");
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
		if(1 != $sth->rowCount()) {
			# Affecting zero rows is possible but usually unwanted
			error_exit("Internal error: Strange number of rows affected");
		}

		unset($this->_modified_fields);

		# TODO: process roster list and add/remove as necessary

		return true;
	}

	function validate_unique ($name)
	{
		global $dbh;
		$opt = '';
		$params = array(
			$name,
		);

		if (isset ($this->team_id)) {
			$opt .= ' AND team.team_id != ?';
			$params[] = $this->team_id;
		}

		$sth = $dbh->prepare('SELECT COUNT(*) FROM team WHERE team.name = ?  ' . $opt);
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

		// Check that this team is not scheduled for any games.  If so,
		// we bail
		$sth = $dbh->prepare('SELECT COUNT(*) FROM schedule WHERE home_team = :team_id OR away_team = :team_id');
		$sth->execute( array( 'team_id' => $this->team_id ) );
		if( $sth->fetchColumn() > 0) {
			error_exit("Cannot delete team: Team must not have any games on the schedule");
		}

		$queries = array(
			'DELETE FROM teamroster WHERE team_id = ?',
			'DELETE FROM leagueteams WHERE team_id = ?',
			'DELETE FROM team WHERE team_id = ?',
		);

		return $this->generic_delete( $queries, $this->team_id );
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
		$sth->execute(array( 'id' => $this->team_id) );

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
	 * Home/away ratio
	 *
	 * Returns ratio (between 0 and 1) of games for which this team was the
	 * home team.
	 */
	function home_away_ratio()
	{
		global $dbh;

		if( $this->home_games < 0 || $this->away_games < 0 ) {
			$sth = $dbh->prepare('SELECT home_team = ? AS is_home, count(*) AS num_games FROM schedule WHERE home_team = ? or away_team = ? GROUP BY is_home ORDER BY is_home');
			// Grr... if we were using a better database, we could pass one
			// parameter and use it multiple times.  Alas, mysql doesn't
			// allow this, so we have to pass team_id three times.
			$sth->execute( array($this->team_id, $this->team_id, $this->team_id) );
			while($row = $sth->fetch( PDO::FETCH_ASSOC ) ) {
				if($row['is_home']) {
					$this->home_games = $row['num_games'];
				} else {
					$this->away_games = $row['num_games'];
				}
			}

			// Make sure we have a value >= 0 for each, so we don't hit the db again
			$this->home_games = ($this->home_games < 0 ? 0 : $this->home_games);
			$this->away_games = ($this->away_games < 0 ? 0 : $this->away_games);
		}

		if( $this->home_games + $this->away_games < 1 ) {
			# Avoid divide-by-zero
			return 0;
		}

		return( $this->home_games / ($this->home_games + $this->away_games) );
	}

	function preferred_field_ratio()
	{
		global $dbh;

		if( $this->preferred_ratio >= 0 ) {
			return $this->preferred_ratio;
		}

		if( ! $this->region_preference
			|| $this->region_preference == '---' ) {
			// No preference means they're always happy.  We set
			// this to over 100% to force them to sort last when
			// ordering by ratio, so that teams with a preference
			// always appear before them.
			$this->preferred_ratio = 2;
			return ($this->preferred_ratio);
		}

		// It's not the most evil SQL hack in Leaguerunner, but it's
		// probably a runner-up.  The idea is to get a count of the
		// games played in the preferred region or on a home field, and
		// a count played outside.
		$sth = $dbh->prepare(
			'SELECT
				IF(g.fid = t.home_field, 1, COALESCE(f.region,p.region) = t.region_preference) AS is_preferred,
				COUNT(*) AS num_games
				FROM schedule s
				LEFT JOIN gameslot g USING (game_id)
				LEFT JOIN field f USING (fid)
				LEFT JOIN field p ON (f.parent_fid = p.fid),
				team t
				WHERE (s.home_team = t.team_id OR s.away_team = t.team_id)
				AND t.team_id = ? GROUP BY is_preferred');
		$sth->execute( array( $this->team_id) );

		$preferred     = 0;
		$not_preferred = 0;
		while($row = $sth->fetch( PDO::FETCH_ASSOC ) ) {
			if($row['is_preferred']) {
				$preferred = $row['num_games'];
			} else {
				$not_preferred = $row['num_games'];
			}
		}

		if( $preferred + $not_preferred < 1 ) {
			# Avoid divide-by-zero
			return 0;
		}


		$this->preferred_ratio = $preferred / ($preferred + $not_preferred);
		return ($this->preferred_ratio);
	}

	/**
	 * Move team to another league.
	 */
	function move_team_to( $league_id )
	{
		global $dbh;

		$sth = $dbh->prepare('UPDATE leagueteams SET league_id = ? WHERE team_id = ? AND league_id = ?');
		$sth->execute( array($league_id, $this->team_id, $this->league_id) );

		if( 1 != $sth->rowCount() ) {
			error_exit("Couldn't move team between leagues");
		}

		$this->league_id = $league_id;

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

		$this_league_id = $this->league_id;
		$other_league_id = $other->league_id;

		// Moving to same league is silly
		if( $this_league_id == $other_league_id ) {
			return false;
		}

		# Move team to other league
		$this->move_team_to( $other_league_id );

		$other->move_team_to( $this_league_id );

		# Get future games for $this and $other
		$this_games = Game::load_many(array('either_team' => $this->team_id, 'game_date_future' => 1));
		$other_games = Game::load_many(array('either_team' => $other->team_id, 'game_date_future' => 1));

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

	function get_roster_status ( $player_id )
	{
		global $dbh;

		$sth = $dbh->prepare('SELECT status FROM teamroster WHERE team_id = ? AND player_id = ?');
		$sth->execute( array( $this->team_id, $player_id) );
		$current_status = $sth->fetchColumn();

		if(!$current_status) {
			$current_status = 'none';
		}

		return $current_status;
	}

	function set_roster_status ( $player_id, $status, $current_status = null )
	{
		global $dbh;

		if( is_null($current_status) ) {
			$current_status = $this->get_roster_status( $player_id );
		}

		if( $status == $current_status ) {
			// No-op
			return true;
		}

		if( $status == 'none' ) {
			$sth = $dbh->prepare('DELETE FROM teamroster WHERE team_id = ? AND player_id = ?');
			$sth->execute( array($this->team_id, $player_id));

		} elseif ($current_status != 'none') {
			$sth = $dbh->prepare('UPDATE teamroster SET status = ? WHERE team_id = ? AND player_id = ?');
			$sth->execute( array($status, $this->team_id, $player_id) );

		} else {
			$sth = $dbh->prepare('INSERT INTO teamroster VALUES(?,?,?,NOW())');
			$sth->execute( array($this->team_id, $player_id, $status));
		}

		return ( 1 == $sth->rowCount() );
	}

	function getStatesForAdministrator($current_status)
	{
		return array_keys( Team::get_roster_positions() );
	}

	function getStatesForCaptain($current_status)
	{
		global $dbh;

		$generally_allowed = array( 'none', 'coach', 'captain', 'assistant', 'player', 'substitute');

		switch($current_status) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster where status = ? AND team_id = ?');
			$sth->execute( array('captain', $this->team_id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return $generally_allowed;
		case 'coach':
		case 'assistant':
		case 'player':
		case 'substitute':
		case 'player_request':
			return $generally_allowed;
		case 'captain_request':
			// Can only remove players when in this state
			return array( 'none' );
		case 'none':
			return array( 'captain_request' );
		default:
			error_exit("Internal error in player status");
		}
	}

	function getStatesForPlayer($current_status)
	{
		global $dbh;

		switch($current_status) {
		case 'captain':
			$sth = $dbh->prepare('SELECT COUNT(*) FROM teamroster WHERE status = ? AND team_id = ?');
			$sth->execute( array('captain', $this->team_id));

			if($sth->fetchColumn() <= 1) {
				error_exit("All teams must have at least one player with captain status.");
			}

			return array( 'none', 'coach', 'assistant', 'player', 'substitute');
		case 'coach':
			return array( 'none', 'captain', 'assistant', 'player', 'substitute');
		case 'assistant':
			return array( 'none', 'player', 'substitute');
		case 'player':
			return array( 'none', 'substitute');
		case 'substitute':
			return array( 'none' );
		case 'captain_request':
			return array( 'none', 'player', 'substitute');
		case 'player_request':
			return array( 'none' );
		case 'none':
			$sth = $dbh->prepare('SELECT status FROM team WHERE team_id = ?');
			$sth->execute( array( $this->team_id ));
			if($sth->fetchColumn() != 'open') {
				error_exit("Sorry, this team is not open for new players to join");
			}
			return array( 'player_request' );
		default:
			error_exit("Internal error in player status");
		}
	}


	/**
	 * Compare two teams by their home field ratio.  Returns teams ordered
	 * lowest ratio to highest ratio.
	 */
	static function cmp_home_field_ratio ( $a, $b )
	{
		$a_ratio = $a->preferred_field_ratio();
		$b_ratio = $b->preferred_field_ratio();
		if( $a_ratio == $b_ratio ) {
			return 0;
		}

		return ($a_ratio < $b_ratio) ? 1 : -1;
	}

	static function get_roster_positions()
	{
		return self::$roster_positions;
	}

	static function query( $array = array() )
	{
		global $CONFIG, $dbh;

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

		$local_adjust_secs = $CONFIG['localization']['tz_adjust'] * 60;

		$sth = $dbh->prepare("SELECT
			t.*,
			1 AS _in_database,
			l.name AS league_name,
			l.tier AS league_tier,
			l.day AS league_day, 
			l.season AS league_season, 
			l.year AS league_year, 
			l.league_id,
			(UNIX_TIMESTAMP(CONCAT(roster_deadline,' 23:59:59')) + $local_adjust_secs) AS roster_deadline
			FROM team t
			INNER JOIN leagueteams s ON (s.team_id = t.team_id)
			INNER JOIN league l ON (s.league_id = l.league_id)
			WHERE " . implode(' AND ',$query) . $order);
		$sth->execute( $params );

		return $sth;
	}

	static function load( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject(get_class());
	}

	static function load_many( $array = array() )
	{
		$sth = self::query( $array );
		$teams = array();
		while( $t = $sth->fetchObject(get_class(), array(LOAD_RELATED_DATA))) {
			$teams[$t->team_id] = $t;
		}

		return $teams;
	}

	function get_notes ( )
	{
		if( ! $this->notes ) {
			$this->notes = Note::load_many( array( 'assoc_type' => 'team', 'assoc_id' => $this->team_id ) );
			foreach($this->notes as $n) {
				$n->load_creator();
			}
		}
		return $this->notes;
	}
}
?>
