<?php
/**
 * Periodic tasks to perform.  This should handle any internal checkpointing
 * necessary, as the cron task may be called more or less frequently than we
 * expect.
 */

class cron extends Handler
{
	function has_permission ()
	{
		// Always have permission to run cron.  In the future,we may want to
		// restrict this to 127.0.0.1 or something.
		return true;
	}

	function process ()
	{
		return join("", array(
			league_cron()
		));
	}
}

function league_cron()
{
	global $dbh;

	$sth = $dbh->prepare('SELECT DISTINCT league_id FROM league WHERE status = ? AND season != ? ORDER BY season, day, tier, league_id');
	$sth->execute( array('open', 'none') );
	while( $id = $sth->fetchColumn() ) {
		$league = League::load( array('league_id' => $id) );

		// Find all games older than our expiry time, and finalize them
		$league->finalize_old_games();

		// If schedule is round-robin, possibly update the current round
		if($league->schedule_type == 'roundrobin') {
			$league->update_current_round();
		}
	}

	return "<pre>Completed league_cron run</pre>";
}

?>
