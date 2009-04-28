<?php

/**
 * Session functions.
 */
function sess_open($save_path, $session_name)
{
	return TRUE;
}

function sess_close()
{
	return TRUE;
}

function sess_read($key)
{
	global $lr_session;
	$lr_session = new UserSession;

	$lr_session->create_from_cookie($key, $_SERVER['REMOTE_ADDR']);
	return $lr_session->is_valid();
}

function sess_write($key, $value)
{
	return '';
}

function sess_destroy($key)
{
	global $dbh;
	$sth = $dbh->prepare("UPDATE person SET session_cookie = '' WHERE session_cookie = ?");
	$sth->execute(array($key));
}

function sess_gc($lifetime)
{
	return TRUE;
}

function lr_configure_sessions ()
{
	global $CONFIG;

	if ($CONFIG['session']['session_name']) {
		$session_name = $CONFIG['session']['session_name'];
	}
	else {
		// Otherwise use $base_url as session name, without the protocol
		// to use the same session identifiers across http and https.
		list( , $session_name) = explode('://', $CONFIG['paths']['base_url'], 2);
	}
	session_name('SESS'. md5($session_name));
	session_set_save_handler("sess_open","sess_close","sess_read","sess_write","sess_destroy","sess_gc");
	session_start();
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
	var $user;
	var $session_key;

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

		if( variable_get("session_requires_ip", 1) ) {
			$user = person_load( array( 'session_cookie' => $cookie, 'client_ip' => $client_ip ) );
		} else {
			$user = person_load( array( 'session_cookie' => $cookie ) );
		}

		if( ! $user ) {
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user
		 * and generate a session key.
		 */
		$this->user = $user;

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

		$user = person_load( array( 'username' => $username, 'password' => $password ) );

		if( ! $user ) {
			return false;
		}


		/* Ok, the user is good.  Now we need to save the user
		 * and generate a session key.
		 */
		$this->user = $user;

		$this->session_key = $this->set_session_key($client_ip);

		return true;
	}

	/**
	 * Expire a session.
	 *
	 */
	function expire ()
	{
		global $dbh;

		$user_id = $this->attr_get('user_id');
		if(is_null($user_id)) {
			return false;
		}

		$sth = $dbh->prepare('UPDATE person SET session_cookie = NULL WHERE user_id = ?');
		return $sth->execute(array($user_id));
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
		if(!isset($this->user)) {
			return null;
		}

		if(!isset($this->user->$attr)) {
			return null;
		}

		return $this->user->$attr;

	}

	/**
	 * Set the session key
	 */
	function set_session_key ( $client_ip )
	{
		global $dbh;

		$sesskey = session_id();

		$sth = $dbh->prepare('UPDATE person SET session_cookie = ?, last_login = NOW(), client_ip = ? WHERE user_id = ?');
		$sth->execute(array($sesskey, $client_ip, $this->user->user_id));
		if( $sth->rowCount != 1 ) {
			return false;
		}

		return $sesskey;
	}

	/**
	 * Check to see if the current user session is loaded.
	 * Note that this session may not be valid, as that is determined by its
	 * account status.
	 *
	 * We assume that a session is loaded if it contains a user with a
	 * user_id.
	 */
	function is_loaded ()
	{
		return !is_null($this->attr_get('user_id'));
	}

	/**
	 * Check to see if the current user session is valid.
	 *
	 * @return boolean valid or not
	 */
	function is_valid ()
	{
		if( ! $this->is_loaded() ) {
			return false;
		}

		return ($this->attr_get('status') == 'active');
	}

	/**
	 * See if this session user has a completed record
	 */
	function is_complete()
	{
		// TUC has code that checks a database flag that OCUA
		// doesn't have.  The flag is useful for leagues that
		// import an existing database that doesn't have all
		// of the player data that Leaguerunner tracks.  For
		// OCUA's purposes, all players have complete records.
		return true;
	}

	/**
	 * See if this session user is a player
	 */
	function is_player()
	{
		return ($this->user && $this->user->is_player() );
	}

	function has_permission( $module, $action, $a1 = NULL, $a2 = NULL )
	{
		// Admin always has permission.
		if( $this->is_valid() && $this->is_admin()) {
			return true;
		}
		return module_invoke($module, 'permissions', $this->user, $action, $a1, $a2);
	}

	/**
	 * See if this session user is an administrator
	 */
	function is_admin ()
	{
		return ($this->user && $this->user->is_admin());
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
		return ($this->user && $this->user->is_captain_of($team_id));
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
		return ($this->user && $this->user->is_coordinator_of($league_id));
	}

	/**
	 * See if this session user is the coordinator of a league containing
	 * this team
	 */
	function coordinates_league_containing($team_id)
	{
		return ($this->user && $this->user->coordinates_league_containing($team_id));
	}
}
?>
