<?php
register_page_handler('player_view', 'PlayerView');

/**
 * Player viewing handler
 *
 * @package Leaguerunner
 * @version $Id $
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PlayerView extends Handler
{

	/* Permissions bits for various items of interest */
	var $_permissions;
	
	/** 
	 * Initializer for PlayerView class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "View Player";
		$this->_permissions = array(
			'email'		=> false,
			'phone'		=> false,
			'username'	=> false,
			'address'	=> false,
			'gender'	=> false,
			'skill' 	=> false,
			'name' 		=> false,
		);
	}

	/**
	 * Check if the current session has permission to view this player.
	 *
	 * check that the session is valid (return false if not)
	 * check if the session user is the target player (return true)
	 * check if the session user is the system admin  (return true)
	 * Now, check permissions of session to view this user
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $DB, $session, $userid;
		if(!$session->is_valid()) {
			return false;
		}

		/* Anyone with a valid session can see your name */
		$this->_permissions['name'] = true;

		/* Can always view self */
		if($session->attr_get('user_id') == $userid) {
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		/* Administrator can view all */
		if($session->attr_get('class') == 'administrator') {
			while(list($key,) = each($this->_permissions)) {
				$this->_permissions[$key] = true;
			}
			return true;
		}

		/* 
		 * TODO: 
		 * See if we're looking at a volunteer or team captain
		 */

		/*
		 * See if we're looking at a regular player with possible restrictions
		 */
		$sth = $DB->prepare( "SELECT class, allow_publish_email, allow_publish_phone, FROM person WHERE user_id = ?");
		$res = $DB->execute($sth,$userid);
		if(DB::isError($res)) {
		 	/* TODO: Handle database error */
			return false;
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC, 0);
		$res->free();
		if($row['allow_publish_email'] == 'yes') {
			$this->_permissions['email'] = true;
		}
		if($row['allow_publish_phone'] == 'yes') {
			$this->_permissions['phone'] = true;
		}

		return true;
	}

	function process ()
	{
	}
}

?>
