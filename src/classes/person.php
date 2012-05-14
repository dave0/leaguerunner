<?php
class Person extends LeaguerunnerObject
{
	var $user_id;
	var $username;
	var $password;
	var $member_id;
	var $firstname;
	var $lastname;
	var $email;
	var $allow_publish_email;
	var $home_phone;
	var $publish_home_phone;
	var $work_phone;
	var $publish_work_phone;
	var $mobile_phone;
	var $publish_mobile_phone;
	var $addr_street;
	var $addr_city;
	var $addr_prov;
	var $addr_country;
	var $addr_postalcode;
	var $gender;
	var $birthdate;
	var $height;
	var $skill_level;
	var $year_started;
	var $shirtsize;
	var $session_cookie;
	var $class;
	var $status;
	var $waiver_signed;
	var $has_dog;
	var $dog_waiver_signed;
	var $survey_completed;
	var $willing_to_volunteer;
	var $contact_for_feedback;
	var $show_gravatar;
	var $last_login;
	var $client_ip;

	var $is_a_coordinator;

	var $notes;

	/*
	 * Load related tables, and perform some post-load cleanups
	 */
	function __construct ( $load_mode = LOAD_RELATED_DATA )
	{
		global $dbh;

		/* set any defaults for unset values */
		if(!$this->height) {
			$this->height = 0;
		}

		if( ! $this->user_id ) {
			return;
		}

		/* set derived attributes */
		$this->fullname = "$this->firstname $this->lastname";

		if( $this->user_id == 'new' || $load_mode == LOAD_OBJECT_ONLY ) {
			return;
		}

		/* Now fetch team info */
		$sth = $dbh->prepare(
			"SELECT
				r.status AS position,
				r.team_id,
				t.name,
				l.league_id,
				IF(l.tier,CONCAT_WS(' ',l.name,'Tier',l.tier),l.name) AS league_name,
				l.season,
				l.day,
				l.status AS league_status
			FROM
				teamroster r
				INNER JOIN team t ON (r.team_id = t.team_id)
				INNER JOIN leagueteams lt ON (lt.team_id = t.team_id)
				INNER JOIN league l ON (lt.league_id = l.league_id)
			WHERE
				r.player_id = ?
			ORDER BY
				l.season DESC, l.day");

		$sth->setFetchMode(PDO::FETCH_CLASS, 'Team', array(LOAD_OBJECT_ONLY));
		$sth->execute(array($this->user_id));

		$this->teams = array();
		while($team = $sth->fetch() ) {
			if($team->position == 'captain' || $team->position == 'assistant' || $team->position == 'coach') {
				# TODO: evil hack.
				$this->is_a_captain = true;
			}
			$this->teams[ $team->team_id ] = $team;
			$this->teams[ $team->team_id ]->id = $team->team_id;
		}

		/* Fetch league info.  Can't use League::load as it calls Person::load,
		 * which makes this recursively painful.
		 */
		$sth = $dbh->prepare(
			"SELECT
				l.league_id,
				l.name,
				l.tier,
				l.season,
				l.day,
				l.schedule_type,
				l.status AS league_status,
				m.status
			FROM
			 	leaguemembers m
				INNER JOIN league l ON (m.league_id = l.league_id)
			WHERE
				m.status = 'coordinator'
			AND
				m.player_id = ?
			 ORDER BY l.season, l.day, l.name, l.tier");
		$sth->setFetchMode(PDO::FETCH_CLASS, 'League', array(LOAD_OBJECT_ONLY));
		$sth->execute(array($this->user_id));
		$this->leagues = array();
		while($league = $sth->fetch() ) {
			# TODO: evil hack... this belongs in the league constructor, no?
			$this->is_a_coordinator = true;
			if($league->tier) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$this->leagues[ $league->league_id ] = $league;
		}

		/* Evil hack to get 'Inactive Teams' into menu */
		if( $this->is_a_coordinator ) {
			$sth = League::query( array( 'league_id' => 1 ) );
			$this->leagues[1] = $sth->fetchObject('League', array(LOAD_OBJECT_ONLY));
		}

		return true;
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
				error_exit("Couldn't create user account");
			}
		}

		$fields      = array();
		$fields_data = array();

		foreach ( $this->_modified_fields as $key => $value) {
			$fields[] = "$key = ?";
			if( empty($this->{$key}) ) {
				$fields_data[] = null;
			} else {
				$fields_data[] = $this->{$key};
			}
		}

		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}

		$sql = "UPDATE person SET ";
		$sql .= join(", ", $fields);
		$sql .= " WHERE user_id = ?";

		$fields_data[] = $this->user_id;

		$sth = $dbh->prepare( $sql );
		if( ! $sth->execute( $fields_data ) ) {
			$arr = $sth->errorInfo();
			error_exit("Couldn't save: " . join(' ', $arr));
		}

		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		global $dbh;

		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->username ) {
			return false;
		}

		if( ! $this->password ) {
			return false;
		}

		$sth = $dbh->prepare('INSERT into person (username, password, status) VALUES(?,?,?)');
		$sth->execute( array($this->username, $this->password, 'new') );
		if( 1 != $sth->rowCount() ) {
			return false;
		}
		$sth = $dbh->prepare('SELECT LAST_INSERT_ID() FROM person');
		$sth->execute();
		$this->user_id = $sth->fetchColumn();

		return true;
	}

	/**
	 * Delete a user account from the system.
	 *
	 * Here, we need to not only remove the user account, but
	 * 	- ensure user is not a team captain or assistant or coach
	 * 	- ensure user is not a league coordinator
	 * 	- remove user from all team rosters
	 */
	function delete ()
	{
		global $dbh;

		if( ! $this->_in_database ) {
			return false;
		}

		if ($this->is_a_captain) {
			error_exit("Account cannot be deleted while player is a team captain.");
		}

		if ( $this->is_a_coordinator ) {
			error_exit("Account cannot be deleted while player is a league coordinator.");
		}

		/* Don't remove users that have registration records, for
		 * accounting purposes.
		 */
		$sth = $dbh->prepare('SELECT COUNT(*) FROM registrations WHERE user_id = ?');
		$sth->execute( array( $this->user_id ));
		if ( $sth->fetchColumn() > 0 ) {
			error_exit("Account cannot be deleted while player has registration records.");
		}

		$queries = array(
			'DELETE FROM teamroster WHERE player_id = ?',
			'DELETE FROM person WHERE user_id = ?',
		);

		return $this->generic_delete( $queries, $this->user_id );
	}

	function generate_member_id()
	{
		// we add 21000000 to the user_id to arrive at a member_id
		// value that won't clash with pre-existing ones that were
		// based on YYYYGNNN where G = gender, and NNN was a count of
		// players registered from that year.
		return $this->set('member_id', 21000000 + $this->user_id);
	}

	/*
	 * Check functions
	 */

	function is_admin()
	{
		return ($this->class == 'administrator');
	}

	function is_player()
	{
		return (
			$this->class == 'player'
			|| $this->class == 'volunteer'
			|| $this->class == 'administrator'
		);
	}

	function is_active()
	{
		return( $this->status == 'active' );
	}

	function has_position_on( $team_id, $wanted_positions )
	{
		if( ! array_key_exists($team_id, $this->teams) ) {
			return false;
		}

		// !== identity comparison is necessary -- array_search()
		// returns array index, which may frequently be zero.
		return (array_search($this->teams[$team_id]->position, $wanted_positions) !== FALSE);
	}

	function is_captain_of ($team_id)
	{
		return $this->has_position_on($team_id, array('captain', 'assistant', 'coach'));
	}

	function is_player_on ($team_id)
	{
		return $this->has_position_on($team_id, array('coach','captain', 'assistant', 'player'));
	}

	function is_coordinator_of($league_id)
	{
		if($this->is_admin()) { return true; }

		if(!$this->is_a_coordinator) { return false; }

		if($league_id == 1) {
			/* All coordinators can coordinate "Inactive Teams" */
			return true;
		}

		if( array_key_exists($league_id, $this->leagues) ) {
			return true;
		}

		return false;
	}

	function coordinates_league_containing( $team_id )
	{
		global $dbh;
		if($this->is_admin()) { return true; }

		$sth = $dbh->prepare('SELECT league_id FROM leagueteams WHERE team_id = ?');
		$sth->execute(array($team_id));
		$league = $sth->fetchObject('League', array(LOAD_OBJECT_ONLY));

		return $this->is_coordinator_of( $league->league_id );
	}

	/**
	 *
	 * Checks if $this player has registered for all prerequisites for a given team in a league
	 * @param integer $team_id ID of Team to test eligibility
	 * @return mixed true or array of registration_events that the player must sign up for
	 */
	function is_eligible_for($team_id)
	{
		global $dbh;
		$query = array();
		$params = array();
		$events = array();
		$sth = $dbh->prepare('SELECT league_id FROM leagueteams WHERE team_id = ?');
		$sth->execute(array($team_id));
		$league = $sth->fetchObject('League');

		// check if player has registered for necessary events
		// $query will contain registration_event ids
		foreach ($league->events as $key=>$value) {
			$query[$key] = $value;
		}

		$sth = $dbh->prepare("SELECT
			e.registration_id AS id, e.payment AS payment
			FROM registrations e
			WHERE e.user_id = ?
			AND e.registration_id IN (".implode(', ', array_keys($query)).")");
		$sth->execute(array($this->user_id));

		// If matching entries are found, remove  from $query list
		// as the player has registered for the events in question
		while($row = $sth->fetch()) {
			if(array_key_exists($row['id'],$query)) {
				unset($query[$row['id']]);
			}
		}

		// If the query list still contains registration_ids, then the player
		// is not eligible for the team
		return $empty = empty($query) ? true : $query;
	}


	function find_duplicates ( )
	{
		global $dbh;
		/* Check to see if there are any duplicate users */
		$query = 'SELECT
			p.user_id,
			p.firstname,
			p.lastname
			FROM person p, person q
			WHERE q.user_id = ?
				AND p.user_id <> q.user_id
				AND (
					p.email = q.email
					OR p.home_phone = q.home_phone
					OR p.work_phone = q.work_phone
					OR p.mobile_phone = q.mobile_phone
					OR p.addr_street = q.addr_street
					OR (p.firstname = q.firstname AND p.lastname = q.lastname)
				)';
		$sth = $dbh->prepare( $query );
		if( ! $sth->execute (array($this->user_id)) ) {
			return null;
		}

		return $sth;
	}

	function send_membership_letter()
	{
		global $dbh;

		// Send the email
		$variables = array(
			'%fullname' => $this->fullname,
			'%firstname' => $this->firstname,
			'%lastname' => $this->lastname,
			'%adminname' => variable_get('app_admin_name', 'Leaguerunner Admin'),
			'%site' => variable_get('app_org_name','league'),
			'%year' => date('Y'));
		$message = _person_mail_text('member_letter_body', $variables);

		return send_mail($this,
			false, // from the administrator
			false,
			_person_mail_text('member_letter_subject', $variables),
			$message);
	}

	function teams_for_pulldown ( $position = 'captain' )
	{
		$teams = array();

		foreach ($this->teams as $key => $team) {
			if( $this->has_position_on( $key, array($position) ) ) {
				$teams[$key] = $team->name;
			}
		}

		return $teams;
	}

	function fetch_upcoming_games ( $want_num = 3 )
	{
		global $dbh;

		// TODO: Find a way to make this work within Game::query()
		$sth = $dbh->prepare("SELECT
			distinct s.*,
			1 as _in_database,
			IF(l.tier, CONCAT(l.name, ' ', l.tier), l.name) AS league_name,
			s.home_team AS home_id,
			h.name AS home_name,
			h.rating AS rating_home,
			s.away_team AS away_id,
			a.name AS away_name,
			a.rating AS rating_away,
			g.slot_id,
			g.game_date,
			TIME_FORMAT(g.game_start, '%H:%i') AS game_start,
			TIME_FORMAT(g.game_end, '%H:%i') AS game_end,
			g.fid,
			UNIX_TIMESTAMP(g.game_date) as day_id,
			IF(f.parent_fid, CONCAT(pf.code, ' ', f.num), CONCAT(f.code, ' ', f.num)) AS field_code,
			UNIX_TIMESTAMP(CONCAT(g.game_date, ' ', g.game_start)) + 0 as timestamp
		FROM
			teamroster hr,
			teamroster ar,
			team h,
			team a,
			league l,
			schedule s,
			gameslot g,
			field f LEFT JOIN field pf ON (pf.fid = f.parent_fid)
		WHERE
			l.league_id = s.league_id
			AND s.home_team = h.team_id
			AND s.away_team = a.team_id
			AND s.game_id = g.game_id
			AND g.fid = f.fid
			AND hr.team_id = h.team_id
			AND ar.team_id = a.team_id
				AND (hr.player_id = ? OR ar.player_id = ?)
				AND g.game_date >= CURRENT_DATE()
			ORDER BY g.game_date, g.game_start
			LIMIT $want_num");

		$sth->execute( array( $this->user_id, $this->user_id ) );

		if( !$sth ) {
			return array();
		}

		$games = array();
		while( $row = $sth->fetchObject('Game') ) {
			$games[] = $row;
		}
		return $games;
	}

	static function query ( $array = array() )
	{
		global $dbh;

		$query  = array();
		$params = array();
		$order  = '';
		$limit  = '';

		foreach ($array as $key => $value) {
			switch( $key ) {
				case 'lastname_wildcard':
					$value = strtr( $value, array('*'=>'%') );
					$query[]  = "p.lastname LIKE ?";
					$params[] = $value;
					break;
				case 'password':
					$query[]  = 'p.password = ?';
					$params[] = md5($value);
					break;
				case '_extra':
					/* Just slap on any extra query fields desired */
					$query[] = $value;
					break;
				case '_order':
					$order = " ORDER BY $value ";
					break;
				case '_limit':
					$limit = " LIMIT $value ";
					break;
				default:
					$query[]  = "p.$key = ?";
					$params[] = $value;
			}
		}

		$sth = $dbh->prepare("SELECT
			p.*,
			1 AS _in_database,
			UNIX_TIMESTAMP(p.waiver_signed) AS waiver_timestamp,
			UNIX_TIMESTAMP(p.dog_waiver_signed) AS dog_waiver_timestamp
			FROM person p
			WHERE " . implode(' AND ',$query) . $order . $limit);

		$sth->execute( $params );

		return $sth;
	}

	static function load ( $array = array() )
	{
		$result = self::query( $array );
		return $result->fetchObject( get_class() );
	}

	static function count( $array = array() )
	{
		global $dbh;
		$query = array();
		$params = array();
		foreach ($array as $key => $value) {
			$query[]  = "$key = ?";
			$params[] = $value;
		}
		$sth = $dbh->prepare('SELECT COUNT(*) FROM person WHERE ' . implode( ' AND ', $query ));
		$sth->execute( $params );
		return $sth->fetchColumn();
	}

	function rfc2822_address()
	{
		if(!$this->email) {
			return;
		}

		$name = $this->fullname;
		if(!$name) {
			$name = "$this->firstname $this->lastname";
		}

		if(!$name) {
			return $this->email;
		}

		return "\"$name\" <$this->email>";
	}

	/**
	* Get a Gravatar URL for this user
	*
	* @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
	* @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
	* @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
	* @param boole $img True to return a complete IMG tag False for just the URL
	* @param array $atts Optional, additional key/value attributes to include in the IMG tag
	* @return String containing either just a URL or a complete image tag
	* @source http://gravatar.com/site/implement/images/php/
	*/
	function get_gravatar( $s = 80, $d = 'mm', $r = 'pg' ) {
		$url = 'http://www.gravatar.com/avatar/';
		if( $this->show_gravatar ) {
			$url .= md5( strtolower( trim( $this->email ) ) );
		} else {
			$url .= '00000000000000000000000000000000';
		}
		$url .= "?s=$s&d=$d&r=$r";
		return $url;
	}

	function get_notes ( )
	{
		if( ! $this->notes ) {
			$this->notes = Note::load_many( array( 'assoc_type' => 'person', 'assoc_id' => $this->user_id ) );
			foreach($this->notes as $n) {
				$n->load_creator();
			}
		}
		return $this->notes;
	}

	function set_password( $password )
	{
		$this->set('password', $this->crypt_password( $password ) );
	}

	function crypt_password ( $password, $resalt = false )
	{
		if( !$resalt && substr( $this->password, 0, 3) == '$1$' ) {
			$salt = substr( $this->password, 0, 12);
		} else {
			$base64_alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789./';
			$salt='$1$';
			for($i=0; $i<=8; $i++){
				$salt.=$base64_alphabet[rand(0,63)];
			}
			$salt .= '$';
		}
		return crypt( $password, $salt );
	}

	function check_password ( $password )
	{
		/* HACK: old-format passwords were unsalted md5().  New ones
		 * are properly using crypt(), though only the salted md5
		 * version due to PHP limitations.
		 */
		if( substr( $this->password, 0, 3) == '$1$' ) {
			return ( $this->crypt_password( $password ) == $this->password );
		} else {
			return ( md5( $password ) == $this->password );
		}
	}

	function log_in ( $session, $client_ip, $password )
	{
		global $dbh;

		$sth = $dbh->prepare('UPDATE person SET
			session_cookie = ?,
			last_login     = NOW(),
			client_ip      = ?,
			password       = ?
			WHERE user_id = ?');
		$sth->execute(array($session, $client_ip, $this->crypt_password( $password, true ), $this->user_id) );
		$count = $sth->rowCount();
		if( $count != 1) {
			return false;
		}
		return true;
	}

}

?>
