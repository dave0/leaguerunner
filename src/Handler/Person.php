<?php

/*
 * Here, we require_once() each of our handlers for dealing with Person
 * entities.
 */
require_once("Handler/Person/View.php");

/*
 * Helper functions for person.  These are used amongst multiple handlers.
 */

/**
 * Return array of team information for the given userid
 * 
 * @param integer $userid  User ID
 * @return array Array of all teams with this player, with id, name, and position of player for each team.
 */
function get_teams_for_user($userid) 
{
	global $DB;
	$sth = $DB->prepare("SELECT 
            t.team_id AS id,
            t.name AS name, 
            if(t.captain_id = r.player_id, 
                'captain', 
                if(t.assistant_id = r.player_id, 
                    'assistant',
                    if(r.status = 'confirmed',
                        'player',
                        'unconfirmed'))) as position
        FROM 
            team t,
            teamroster r
        WHERE 
            r.team_id = t.team_id AND 
            r.player_id = ?");
	$res = $DB->execute($sth,$userid);
	if(DB::isError($res)) {
	 	/* TODO: Handle database error */
		return false;
	}
	$rows = array();
	while($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
		$rows[] = $row;
	}
	$res->free();
	return $rows;
}

/**
 * Return array of all information for the given userid
 * @param integer $userid  User ID
 * @return array Associative array of all DB info on given person
 */
function get_info_for_user($userid) 
{
	global $DB;
	$sth = $DB->prepare("SELECT * from person where user_id = ?");
	$res = $DB->execute($sth,$userid);
	if(DB::isError($res)) {
	 	/* TODO: Handle database error */
		return false;
	}
	$rows = array();
	while($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
		$rows[] = $row;
	}
	$res->free();
	return $rows;
}


?>
