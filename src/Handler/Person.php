<?php

/*
 * Here, we require_once() each of our handlers for dealing with Person
 * entities.  The indentation of certain lines below is not mandatory, but is
 * used to indicate the inheritance structure.  However, it _is_ mandatory
 * that the inherited classes get require()'ed after their parents.
 */
require_once("Handler/Person/View.php");
  require_once("Handler/Person/Delete.php");
  require_once("Handler/Person/ApproveAccount.php");
require_once("Handler/Person/Edit.php");
  require_once("Handler/Person/Create.php");
  require_once("Handler/Person/ActivateAccount.php");
require_once("Handler/Person/List.php");
require_once("Handler/Person/ListNew.php");
require_once("Handler/Person/ChangePassword.php");

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
	$rows = $DB->getAll(
		"SELECT 
            t.team_id AS id,
            t.name AS name, 
            if(t.captain_id = r.player_id, 
                'captain', 
                if(t.assistant_id = r.player_id, 
                    'assistant',
                    if(r.status = 'confirmed',
                        'player',
                        r.status))) as position
        FROM 
            team t,
            teamroster r
        WHERE 
            r.team_id = t.team_id AND 
            r.player_id = ?",
	array($userid), DB_FETCHMODE_ASSOC);
	return $rows;
}

?>
