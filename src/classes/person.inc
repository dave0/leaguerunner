<?php
class Person extends LeaguerunnerObject
{
	/**
	 * Load a single user account object from the database using the 
	 * supplied query data.  If more than one account matches, we will
	 * return only the first one.  If fewer than one matches, this object
	 * remains empty.
 	 * @param	array 	$array key-value pairs that identify the user to be loaded.
 	 */
	function load ( $array = array() )
	{
		$query = array();

		foreach ($array as $key => $value) {
			if ($key == 'password') {
				$query[] = "p.$key = '" . md5($value) . "'";
			} else {
				$query[] = "p.$key = '" . check_query($value) . "'";
			}
		}
	
		$result = db_query_range("SELECT 
			p.*,
			UNIX_TIMESTAMP(p.waiver_signed) AS waiver_timestamp,
			UNIX_TIMESTAMP(p.dog_waiver_signed) AS dog_waiver_timestamp,
			w.name AS ward_name, 
			w.num AS ward_number, 
			w.city AS ward_city 
			FROM person p 
			LEFT JOIN ward w ON (p.ward_id = w.ward_id)
			WHERE " . implode(' AND ',$query),0,1);

		/* TODO: we may want to abort here instead */
		if(1 != db_num_rows($result)) {
			return null;
		}

		$this->_in_database = true;

		$user = db_fetch_array($result);
		foreach ($user as $key => $value) {
			$this->{$key} = $value;
		}

		/* set any defaults for unset values */
		if(!$this->height) {
			$this->height = 0;
		}

		/* set derived attributes */
		$this->fullname = "$this->firstname $this->lastname";

		/* Now fetch team info */
		$result = db_query(
			"SELECT 
				r.status AS position,
				r.team_id,
				t.name,
				l.league_id
			FROM 
				teamroster r 
				INNER JOIN team t ON (r.team_id = t.team_id)
				INNER JOIN leagueteams l ON (l.team_id = t.team_id)
			WHERE 
				r.player_id = %d", $this->user_id);

		$this->teams = array();
		while($team = db_fetch_object($result)) {
			if($team->position == 'captain' || $team->position == 'assistant') {
				# TODO: evil hack.
				$this->is_a_captain = true;
			}
			$this->teams[ $team->team_id ] = $team;
			$this->teams[ $team->team_id ]->id = $team->team_id;
		}

		/* Fetch league info.  Can't use league_load as it calls person_load,
		 * which makes this recursively painful.
		 */
		$result = db_query(
			"SELECT 
				l.league_id, 
				l.name,
				l.tier,
				m.status
			 FROM 
			 	leaguemembers m 
				INNER JOIN league l ON (m.league_id = l.league_id)
			 WHERE m.status = 'coordinator' AND m.player_id = %d", $this->user_id);
		$this->leagues = array();
		while($league = db_fetch_object($result)) {
			# TODO: evil hack.
			$this->is_a_coordinator = true;
			if($league->tier) {
				$league->fullname = "$league->name Tier $league->tier";
			} else {
				$league->fullname = $league->name;
			}
			$this->leagues[ $league->league_id ] = $league;
		}

		/* Evil hack to get 'Inactive Teams' into menu */
		if( $this->is_a_coordinator ) {
			$result = db_query(
				"SELECT l.* FROM league l where league_id = 1");
			$league = db_fetch_object($result);
			$league->fullname = $league->name;
			$this->leagues[ 1 ] = $league;
		}

		return true;
	}

	function save ()
	{
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
			if( !isset($this->{$key}) || ('' == $this->{$key}) ) {
				$fields[] = "$key = %s";
				$fields_data[] = 'NULL';
			} else {
				$fields[] = $key . " = " . $this->get_placeholder($key, $this->{$key});
				$fields_data[] = $this->{$key};
			}
		}
		
		if(count($fields_data) != count($fields)) {
			error_exit("Internal error: Incorrect number of fields set");
		}
		
		$sql = "UPDATE person SET ";
		$sql .= join(", ", $fields);	
		$sql .= " WHERE user_id = %d";

		$fields_data[] = $this->user_id;

		db_query( $sql, $fields_data);
		if(1 < db_affected_rows()) {
			# Affecting zero rows is possible
			error_exit("Internal error: Strange number of rows affected");
		}
		
		unset($this->_modified_fields);
		return true;
	}

	function create ()
	{
		if( $this->_in_database ) {
			return false;
		}

		if( ! $this->username ) {
			return false;
		}
		
		if( ! $this->password ) {
			return false;
		}

		db_query("INSERT into person (username, password, status) VALUES('%s','%s','new')", $this->username, $this->password);
		if( 1 != db_affected_rows() ) {
			return false;
		}

		$this->user_id = db_result(db_query("SELECT LAST_INSERT_ID() from person"));
		
		return true;
	}

	/**
	 * Delete a user account from the system.
	 *
	 * Here, we need to not only remove the user account, but
	 * 	- ensure user is not a team captain or assistant
	 * 	- ensure user is not a league coordinator
	 * 	- remove user from all team rosters
	 */
	function delete ()
	{
		if( ! $this->_in_database ) {
			return false;
		}

		if ($this->is_a_captain) {
			$this->error_exit("Account cannot be deleted while player is a team captain.");
		}

		if ( $this->is_a_coordinator ) {
			$this->error_exit("Account cannot be deleted while player is a league coordinator.");
		}
		
		/* remove user from team rosters.  Don't check for affected
		 * rows, as there may not be any
		 */
		db_query("DELETE from teamroster WHERE player_id = %d",$this->user_id);
		
		/* remove user account */
		db_query("DELETE from person WHERE user_id = %d", $this->user_id);
		
		return (1 == db_affected_rows());
	}

	function get_placeholder( $key, $value )
	{
		if(0 == strcasecmp($value,'NULL')) {
			return "%s";
		}

		// Hack for NOW() timestamps
		if(0 == strcasecmp($value,'NOW()')) {
			return "%s";
		}
	
		switch($key) {
			case 'user_id':
			case 'member_id':
			case 'ward_id':
			case 'height':
			case 'skill_level':
			case 'year_started':
				return "%d";
			default:
				return "'%s'";
		}
	}

	function generate_member_id()
	{
		$result = db_query("UPDATE member_id_sequence SET id=LAST_INSERT_ID(id+1) where year = %d AND gender = '%s'", 
			$this->year_started, $this->gender);
		$rows = db_affected_rows();
		if($rows == 1) {
			// Got a result, so we will use it.
			$result = db_query("SELECT LAST_INSERT_ID() from member_id_sequence");
			$member_id = db_result($result);
			if( !isset($member_id)) {
				return false;
			}
		} else if($rows == 0) {
			// Possible empty, so fill it
			$lockname = "member_id_" 
				. $this->year_started
				. "_" 
				. $this->gender 
				. "_lock";
			$result = db_query("SELECT GET_LOCK('$lockname',10)");
			$lock = db_result($result);
					
			if(!isset($lock) || $lock == 0) {
				return false;
			}
			db_query( "REPLACE INTO member_id_sequence values(%d,'%s',1)", 
				$this->year_started, $this->gender);

			db_query("SELECT RELEASE_LOCK('${lockname}')");
					
			$member_id = 1;
		} else {
			// Something bad happened
			return false;
		}

		/* Now, that's really not the full member ID.  We need to build that
		 * from other info too.
		 */
		$real_id = sprintf("%.4d%.1d%03d", 
			$this->year_started,
			($this->gender == "Male") ? 0 : 1,
			$member_id);
		$this->set('member_id', $real_id);
		return true;
	}
}

/**
 * Wrapper for convenience and backwards-compatibility.
 * TODO: Make this go away.
 */
function person_load( $array = array() )
{
	$p = new Person;
	if($p->load($array)) {
		return $p;
	} else {
		return null;
	}
}

?>
