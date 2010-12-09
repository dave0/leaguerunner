<?php

class api_1_schedule_gamestoday extends Handler
{
	function has_permission ()
	{
		/* Everyone can view this box */
		return true;
	}

	function process ()
	{
		global $dbh;

		$this->template_name = 'api/1/schedule/gamestoday.tpl';

		$now = time();

		$sth = $dbh->prepare('SELECT COUNT(*), COUNT(DISTINCT(game_end)) from gameslot WHERE game_date = ? AND NOT ISNULL(game_id)');
		$sth->execute( array( strftime('%Y-%m-%d', $now) ) );
		list($game_count, $distinct_end_times) = $sth->fetch();

		$this->smarty->assign('game_count', $game_count);
		$this->smarty->assign('timestamp', $now);

		/* Also, take a stab at guessing timecap.  Since only summer usually
		 * has a "default" timecap, we will only display for that season.
		 */
		if( $game_count > 0 ) {
			$season = strtolower(variable_get('current_season', "Summer"));
			if( $season == 'summer' ) {
				$this->smarty->assign('timecap', local_sunset_for_date( time() ));
				$this->smarty->assign('multiple_end_times', ($distinct_end_times > 1));
			}
		}

		return true;
	}
}

?>
