<?php

class home extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return ( $lr_session->is_valid() );
	}

	function process ()
	{
		global $lr_session, $dbh;

		$this->title =  $lr_session->attr_get('fullname');
		$this->template_name = 'pages/home.tpl';

		/* Handle display of RSS feed itmes, if enabled */
		$feed_url =  variable_get('rss_feed_url', null);
		if( $feed_url ) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $feed_url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);

			# Fetch RSS data
			$xml = curl_exec($curl);
			curl_close($curl);

			$xmlObj = simplexml_load_string( $xml );

			$count = 0;
			$limit = variable_get('rss_feed_items', 2);
			$items = array();

			foreach ( $xmlObj->channel[0]->item as $item )
			{
				$items[] = array(
					'title' => $item->title,
					'link'  => $item->link,
				);
				if( ++$count >= $limit ) {
					break;
				}
			}
			if( $count > 0 ) {
				$this->smarty->assign('rss_feed_title', variable_get('rss_feed_title', 'OCUA Volunteer Opportunities'));
				$this->smarty->assign('rss_feed_items', $items);
			}
		}

		/* Display teams */
		$rosterPositions = getRosterPositions();
		$teams = array();
		foreach($lr_session->user->teams as $team) {
			$team->rendered_position = $rosterPositions[$team->position];
			$teams[] = $team;
		}
		reset($lr_session->user->teams);
		$this->smarty->assign('teams', $teams);

		/* Display leagues */
		// TODO: For each league, need to display # of missing scores,
		// pending scores, etc.
		$this->smarty->assign('leagues', $lr_session->user->leagues);

		/* Display recent and upcoming games */
		$games = array();
		// TODO: query should be moved to person object, or a helper in game.inc
		$sth = $dbh->prepare('SELECT s.game_id, t.team_id, t.status FROM schedule s, gameslot g, teamroster t WHERE s.published AND ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = ?) AND g.game_id = s.game_id AND g.game_date < CURDATE() ORDER BY g.game_date desc, g.game_start desc LIMIT 4');
		$sth->execute( array($lr_session->user->user_id) );

		while($row = $sth->fetch(PDO::FETCH_OBJ) ) {
			$game = game_load(array('game_id' => $row->game_id));
			$game->user_team_id = $row->team_id;
			$games[] = $game;
		}
		$games = array_reverse($games);

		$sth = $dbh->prepare('SELECT s.game_id, t.team_id, t.status FROM schedule s, gameslot g, teamroster t WHERE s.published AND ((s.home_team = t.team_id OR s.away_team = t.team_id) AND t.player_id = ?) AND g.game_id = s.game_id AND g.game_date >= CURDATE() ORDER BY g.game_date asc, g.game_start asc LIMIT 4');
		$sth->execute( array($lr_session->user->user_id) );

		while($row = $sth->fetch(PDO::FETCH_OBJ) ) {
			$game = game_load(array('game_id' => $row->game_id));
			$game->user_team_id = $row->team_id;
			$games[] = $game;
		}

		foreach($games as $game) {
			$score = '';
			if( $game->is_finalized() ) {
				$score = "$game->home_score - $game->away_score"	;
			} else {
				/* Not finalized yet, so we will either:
				*   - display entered score if present
				*   - display score entry link if game date has passed
				*   - display a blank otherwise
				*/
				$entered = $game->get_score_entry( $game->user_team_id );
				if($entered) {
					// need to match entered score order to displayed team order!
					if ($entered->team_id == $game->home_id) {
						$score = "$entered->score_for - $entered->score_against";
					} else {
						$score = "$entered->score_against - $entered->score_for";
					}
					$score .= " (unofficial, waiting for opponent)";
					$game->score_entered = $score;

				} else if($lr_session->has_permission('game','submit score', $game)
					&& ($game->timestamp < time()) ) {
					$game->user_can_submit = true;
				}
			}

		}

		$this->smarty->assign('games', $games);
		return;
	}

}

?>
