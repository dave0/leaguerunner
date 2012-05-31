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
	// TODO: BUG: PHP5 doesn't let us use objects in sess_destroy (see docs)
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

	if( $CONFIG['session']['cookie_domain'] ) {
		$cookie_domain = $CONFIG['session']['cookie_domain'];
		if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.',      '', $cookie_domain))) {
			ini_set('session.cookie_domain', $cookie_domain);
		}
	}

	session_name('leaguerunner');
	session_set_save_handler("sess_open","sess_close","sess_read","sess_write","sess_destroy","sess_gc");
	session_start();
}

/**
 * User Session class for Leaguerunner
 *
 * This encapsulates all the user-session handling code.
 *
 * @package	Leaguerunner
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
			$user = Person::load( array( 'session_cookie' => $cookie, 'client_ip' => $client_ip ) );
		} else {
			$user = Person::load( array( 'session_cookie' => $cookie ) );
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

		$user = Person::load( array( 'username' => $username ) );

		if( ! $user ) {
			return false;
		}

		# Check password
		if( ! $user->check_password( $password ) ) {
			return false;
		}

		/* Ok, the user is good.  Now we need to save the user
		 * and generate a session key.
		 */
		$this->user = $user;

		$this->session_key = session_id();

		if( ! $this->user->log_in( $this->session_key, $client_ip, $password ) ) {
			return false;
		}

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
		// TODO: this definitely needs a cleanup
		$function = $module . '_permissions';
		return $function( $this->user, $action, $a1, $a2);
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
