<?php

session_set_save_handler("sess_open","sess_close","sess_read","sess_write","sess_destroy","sess_gc");
session_start();

/** 
 * Session functions.  
 */
function sess_open($save_path, $session_name) 
{
	return 1;
}

function sess_close() 
{
	return 1;
}

function sess_read($key)
{
	global $session;
	$session = new UserSession;
	$session->create_from_cookie($key, $_SERVER['REMOTE_ADDR']);
	return $session->is_valid();
}

function sess_write($key, $value)
{
	return '';
}

function sess_destroy($key)
{
	db_query("UPDATE person SET session_cookie = '' WHERE session_cookie = '%s'", $key);
}

function sess_gc($lifetime)
{
	return 1;
}

/**
 * User Session class for Leaguerunner
 *
 * This encapsulates all the user-session handling code.
 *
 * @package	Leaguerunner
 * @version		$Id$
 * @author		Dave O'Neill <dmo@acm.org>
 * @access		public
 * @copyright	GPLv2; Dave O'Neill <dmo@acm.org>
 */
class UserSession
{
	/**
	 * Constructor
	 */
	function UserSession ()
	{
		/* Yay, empty */
	}

	/**
	 * Create the user session from the given cookie
	 *
	 * @return boolean status of session creation
	 */
	function create_from_cookie ($cookie, $client_ip)
	{
		if( !isset($cookie) ) {
			return false;
		}

		if( !isset($client_ip) ) {
			return false;
		}

		$result = db_query("SELECT *, UNIX_TIMESTAMP(waiver_signed) as waiver_timestamp, UNIX_TIMESTAMP(dog_waiver_signed) as dog_waiver_timestamp FROM person WHERE session_cookie = '%s' AND client_ip = '%s'", $cookie, $client_ip);

		$row = db_fetch_array($result);

		if( $cookie != $row['session_cookie']) {
			/* Failed sanity check - either we didn't get a row, or the row
			 * contains crap.
			 */
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user data 
		 * and generate a session key.
		 */
		
		$this->data = $row;
		
		$this->session_key = $cookie;

		return true;
	}
	
	/**
	 * Create the user session from the given username and password
	 *
	 * @return boolean status of session creation
	 */
	function create_from_login($username,$password,$client_ip)
	{
		if( !isset($username) ) {
			return false;
		}
		
		if( !isset($password) ) {
			return false;
		}
	
		$result = db_query("SELECT * FROM person WHERE username = '%s'",$username);
		
		## So, we assume that the first username we get back is the only one =)
		$row = db_fetch_array($result);

		if( $username != $row['username']) {
			/* Failed sanity check - either we didn't get a row, or the row
			 * contains crap.
			 */
			return false;
		}

		/* Now, check password */
		$cryptpass = md5($password);
		if ($cryptpass != $row['password']) {
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user data 
		 * and generate a session key.
		 */
		
		$this->data = $row;
		
		$this->session_key = $this->set_session_key($client_ip);

		return true;
	}

	/**
	 * Expire a session.
	 *
	 */
	function expire ()
	{
		$user_id = $this->attr_get('user_id');
		if(is_null($user_id)) {
			return false;
		}
	
	    db_query("UPDATE person SET session_cookie = NULL WHERE user_id = %d", $user_id);

		return true;
	}

	/**
	 * Return the session key for this session
	 */
	function get_session_key ()
	{
		if( !isset($this->session_key) ) {
			return null;
		}
		return $this->session_key;
	}

	/**
	 * Return the requested attribute
	 */
	function attr_get ($attr)
	{
		if(!isset($this->data)) {
			return null;
		}
		
		if(!isset($this->data[$attr])) {
			return null;
		}

		return $this->data[$attr];
		
	}

	/**
	 * Set the session key
	 */
	function set_session_key ( $client_ip )
	{
		$sesskey = session_id();

		$result = db_query("UPDATE person SET session_cookie = '%s', last_login = NOW(), client_ip = '%s' WHERE user_id = %d", $sesskey, $client_ip, $this->data['user_id']);
		if(!db_affected_rows()) {
			echo "Error: ", db_error();
			return false;
		}
		
		return $sesskey;
	}

	/**
	 * Check to see if the current user session is loaded.
	 * Note that this session may not be valid, as that is determined by its
	 * account status.
	 *
	 * We assume that a session is loaded if it contains a user_id in its data
	 * member.
	 */
	function is_loaded ()
	{
		global $session;

		if( !isset($this->data) ) {
			return false;
		}
		if( !isset($this->data['user_id']) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check to see if the current user session is valid.
	 * 
	 * @return boolean valid or not
	 */
	function is_valid ()
	{
		global $session;

		if( ! $session->is_loaded() ) {
			return false;
		}

		return ($session->attr_get('status') == 'active');
	}

	/** 
	 * See if this session user is an administrator
	 */
	function is_admin ()
	{
		return ($this->attr_get('class') == 'administrator');
	}

	/** 
	 * See if this session user is captain of given team
	 *
	 * @param team_id Team identifier.
	 *
	 * @return boolean 
	 */
	function is_captain_of ($team_id)
	{
		$result = db_query("SELECT status FROM teamroster where player_id = %d AND team_id = %d",$this->data['user_id'], $team_id);

		$status = db_result($result);

		if( $status == 'captain' || $status == 'assistant') {
			return true;
		}
		
		return false;
	}
	
	/** 
	 * See if this session user is coordinator of a given league
	 *
	 * @param team_id League identifier.
	 *
	 * @return boolean 
	 */
	function is_coordinator_of ($league_id)
	{
		if($this->is_admin()) { return true; }

		if($league_id == 1) {
			/* All coordinators can coordinate "Inactive Teams" */
			if ($this->data['class'] == 'volunteer') {
				return true;
			}
		}

		$result = db_query("SELECT coordinator_id, alternate_id from league where league_id = %d", $league_id);

		$coordInfo = db_fetch_object($result);

		if( ($this->data['user_id'] == $coordInfo->coordinator_id) || ($this->data['user_id'] == $coordInfo->alternate_id)) {
			return true;
		}
		
		return false;
	}

	/** 
	 * Check if this session might coordinate a league.  Used 
	 * as a preliminary check to avoid db queries.
	 */
	function may_coordinate_league()
	{
		return (
			($this->attr_get('class') == 'administrator')
			|| ($this->attr_get('class') == 'volunteer')
		);
	}

	/**
	 * See if this session user is the coordinator of a league containing
	 * this team
	 */
	function coordinates_league_containing($team_id)
	{
		if($this->is_admin()) { return true; }

		$result = db_query("SELECT l.league_id, l.coordinator_id, l.alternate_id FROM league l, leagueteams t WHERE t.team_id = %d and t.league_id = l.league_id", $team_id);

		$league = db_fetch_object($result);	
		
		if($league->league_id == 1) {
			/* All coordinators can coordinate "Inactive Teams" */
			return true;
		}

		if( ($this->data['user_id'] == $league->coordinator_id) || ($this->data['user_id'] == $league->alternate_id)) {
			return true;
		}

		return false;
	}
}
?>
