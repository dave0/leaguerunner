<?php

require_once("Handler/Person/View.php");

/**
 * Helper functions for person
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


?>
