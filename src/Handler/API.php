<?php
function api_dispatch()
{
	$version = arg(1);
	$op      = arg(2);

	/* Currently, version for API is 1.0 */
	if( $version != '1.0' ) {
		return null;
	}

	switch($op) {
		case 'schedule':
			$subop = arg(3);
			switch($subop) {
				case 'gamestoday':
					$obj = new APIGamesToday;
					break;
				default:
					$obj = null;
			}
			break;
		default:
			$obj = null;
	}

	return $obj;
}

/**
 * return HTML suitable for the "Games Today" box
 */
class APIGamesToday extends Handler
{
	function has_permission ()
	{
		/* Everyone can view this box */
		return true;
	}

	function process ()
	{
		global $dbh;

		$now = time();
		$day_url = url('schedule/day/' . strftime("%Y/%m/%d", $now));
		$sth = $dbh->prepare('SELECT COUNT(*), COUNT(DISTINCT(game_end)) from gameslot WHERE game_date = ? AND NOT ISNULL(game_id)');
		$sth->execute( array( strftime('%Y-%m-%d', $now) ) );
		list($game_count, $distinct_end_times) = $sth->fetch();

		$timecap_html = '';
		if( ! $game_count ) {
			$gamecount_html = '<b>No games today</b>';
		} else {
			/* Also, take a stab at guessing timecap.  Since only summer usually
			* has a "default" timecap, we will only display for that season.
			*/
			$season = strtolower(variable_get('current_season', "Summer"));
			if( $season == 'summer' ) {
				$timecap_html .= '<span id="timecap">Timecap is <b>';
				$timecap_html .= local_sunset_for_date( $now );
				$timecap_html .= '</b>';
				if( $distinct_end_times > 1 ) {
					$timecap_html .= ' (some games may differ)';
				}
				$timecap_html .= '</span>';
			}
			$gamecount_html = "<b><a href=\"$day_url\" target=\"_top\">$game_count games today</a></b>";
		}

		print <<<END_HTML
<html><body><div id="gamestoday">$gamecount_html $timecap_html</div></body></html>
END_HTML;
		exit; // Don't return -- we don't want themed HTML wrapped around our output
	}
}

?>
