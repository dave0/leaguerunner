<?php

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
	function create_from_cookie ($cookie)
	{
		global $DB;
		
		if( !isset($cookie) ) {
			return false;
		}

		$sth = $DB->prepare("SELECT * FROM person WHERE session_cookie = ?");
		$res = $DB->execute($sth,$cookie);
		if(DB::isError($res)) {
			/* TODO: Handle database error */
			error_log("ERROR: Couldn't fetch user info from db with cookie");
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
	function create_from_login($user,$pass)
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
			/* TODO: Handle database error */
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
		$cryptpass = crypt($pass, $row['password']);
		if ($cryptpass != $row['password']) {
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user data 
		 * and generate a session key.
		 */
		
		/* TODO: We may wish to be selective here */
		$this->data = $row;
		
		$this->session_key = $this->build_session_key();

		return true;
	}

	/**
	 * Expire a session.
	 *
	 * TODO: WRite me!
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
			die($res->getMessage());
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
	function build_session_key ()
	{
		global $DB;
		
		$sesskey = strftime("%Y%m%d%H%M%S");
		$timestamp = strftime("%Y%m%d%H%M%S");

		$sth = $DB->prepare("UPDATE person SET session_cookie = ?, last_login = ? WHERE user_id = ?");
		$res = $DB->execute($sth,array($sesskey,$timestamp,$this->data['user_id']));
		if(DB::isError($res)) {
			/* TODO: Handle database error */
			echo "Error: ", $res->getMessage();
			return false;
		}
		
		return $sesskey;
	}

	/**
	 * Check to see if the current user session is valid.
	 * 
	 * @return boolean valid or not
	 */
	function is_valid ()
	{
		if( !isset($this->data) ) {
			return false;
		}
		if( !isset($this->data['user_id']) ) {
			return false;
		}
		return true;
	}

	/**
	 * TODO: Needed anymore?
	 */
	function get_failure_message ()
	{
		return true;
	}
}
?>
