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
	db_query("UPDATE person SET session_cookie = '' WHERE session_cookie = '$key'");
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
		global $DB;
		
		if( !isset($cookie) ) {
			return false;
		}

		if( !isset($client_ip) ) {
			return false;
		}

		$sth = $DB->prepare("SELECT *, UNIX_TIMESTAMP(waiver_signed) as waiver_timestamp, UNIX_TIMESTAMP(dog_waiver_signed) as dog_waiver_timestamp FROM person WHERE session_cookie = ? AND client_ip = ?");
		$res = $DB->execute($sth, array($cookie, $client_ip));
		if(DB::isError($res)) {
			error_log( "Error: Couldn't fetch user info from db: " . $res->getMessage() );
			return false;
		}
		
		## So, we assume that the first username we get back is the only one =)
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC,0);
		$res->free();

		if( $cookie != $row['session_cookie']) {
			/* Failed sanity check - either we didn't get a row, or the row
			 * contains crap.
			 */
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user data 
		 * and generate a session key.
		 */
		
		/* TODO: We may wish to be selective here */
		$this->data = $row;
		
		$this->session_key = $cookie;

		return true;
	}
	
	/**
	 * Create the user session from the given username and password
	 *
	 * @return boolean status of session creation
	 */
	function create_from_login($user,$pass,$client_ip)
	{
		global $DB;

		if( !isset($user) ) {
			return false;
		}
		
		if( !isset($pass) ) {
			return false;
		}
		
		$sth = $DB->prepare("SELECT * FROM person WHERE username = ?");
		$res = $DB->execute($sth,$user);
		if(DB::isError($res)) {
			error_log( "Error: Couldn't fetch user info from db: " . $res->getMessage() );
			return false;
		}
		
		## So, we assume that the first username we get back is the only one =)
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC,0);
		$res->free();

		if( $user != $row['username']) {
			/* Failed sanity check - either we didn't get a row, or the row
			 * contains crap.
			 */
			return false;
		}

		/* Now, check password */
		$cryptpass = md5($pass);
		if ($cryptpass != $row['password']) {
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user data 
		 * and generate a session key.
		 */
		
		/* TODO: We may wish to be selective here */
		$this->data = $row;
		
		$this->session_key = $this->build_session_key($client_ip);

		return true;
	}

	/**
	 * Expire a session.
	 *
	 */
	function expire ()
	{
		global $DB;

		$user_id = $this->attr_get('user_id');
		if(is_null($user_id)) {
			return false;
		}
		
		$sth = $DB->prepare("UPDATE person SET session_cookie = NULL WHERE user_id = ?");
		$res = $DB->execute($sth,$user_id);
		if(DB::isError($res)) {
			/* TODO: Handle database error */
			error_log( "Error: Couldn't expire user session due to DB error: " . $res->getMessage() );
			return false;
		}
		
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
	 * Build a session key
	 */
	function build_session_key ( $client_ip )
	{
		global $DB;
		
		$sesskey = session_id();
		$timestamp = strftime("%Y%m%d%H%M%S");

		$sth = $DB->prepare("UPDATE person SET session_cookie = ?, last_login = ?, client_ip = ? WHERE user_id = ?");
		$res = $DB->execute($sth,array($sesskey,$timestamp,$client_ip,$this->data['user_id']));
		if(DB::isError($res)) {
			/* TODO: Handle database error */
			echo "Error: ", $res->getMessage();
			return false;
		}
		
		return $sesskey;
	}

	/**
	 * Check to see if the current user session is loaded.
	 * Note that this session may not be valid, as that is determined by its
	 * account class.
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

		/* 
		 * 'new', 'inactive' and 'locked' accounts are not considered valid
		 * and are handled as special cases
		 */
		switch($session->attr_get('class')) {
			case 'new':
			case 'inactive':
			case 'locked':
				return false;
		}
		
		return true;
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
		global $DB;

		$res = $DB->getOne("SELECT status FROM teamroster where player_id = ? AND team_id = ?",
			array($this->data['user_id'], $team_id),
			DB_FETCHMODE_ASSOC
		);
		if(DB::isError($res)) {
			return false;
		}

		if( $res == 'captain' || $res == 'assistant') {
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
		global $DB;

		if($this->is_admin()) { return true; }

		if($league_id == 1) {
			/* All coordinators can coordinate "Inactive Teams" */
			if ($this->data['class'] == 'volunteer') {
				return true;
			}
		}

		$res = $DB->getRow("SELECT coordinator_id, alternate_id from league where league_id = ?",
			array($league_id),
			DB_FETCHMODE_ASSOC
		);
		if(DB::isError($res)) {
			return false;
		}

		if( ($this->data['user_id'] == $res['coordinator_id']) || ($this->data['user_id'] == $res['alternate_id'])) {
			return true;
		}
		
		return false;
	}

	/**
	 * See if this session user is the coordinator of a league containing
	 * this team
	 */
	function coordinates_league_containing($team_id)
	{
		global $DB;
		
		if($this->is_admin()) { return true; }

		$res = $DB->getRow("SELECT l.league_id, l.coordinator_id, l.alternate_id FROM league l, leagueteams t WHERE t.team_id = ? and t.league_id = l.league_id", array($team_id), DB_FETCHMODE_ASSOC);
		if(DB::isError($res)) {
			return false;
		}

		if( ($this->data['user_id'] == $res['coordinator_id']) || ($this->data['user_id'] == $res['alternate_id'])) {
			return true;
		}

		if($res['league_id'] == 1) {
			/* All coordinators can coordinate "Inactive Teams" */
			return true;
		}
		return false;
	}
}
?>
