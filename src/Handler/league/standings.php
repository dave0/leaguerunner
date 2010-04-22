<?php
require_once('Handler/LeagueHandler.php');
class league_standings extends LeagueHandler
{
	private $teamid;
	private $showall;

	function __construct ( $id, $teamid = null, $showall = 0 )
	{
		parent::__construct( $id );
		$this->teamid  = $teamid;
		$this->showall = $showall;
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id);
	}

	function process ()
	{
		global $lr_session;

		$id = $this->league->league_id;

		$this->title = "Standings";

		if($this->league->schedule_type == 'none') {
			error_exit("This league does not have a schedule or standings.");
		}

		$s = new Spirit;
		$s->entry_type = $this->league->enter_sotg;
		$s->display_numeric_sotg = $this->league->display_numeric_sotg();

		$round = $_GET['round'];
		if(! isset($round) ) {
			$round = $this->league->current_round;
		}
		// check to see if this league is on round 2 or higher...
		// if so, set the $current_round so that the standings table is split up
		if ($round > 1) {
			$current_round = $round;
		}

		$this->setLocation(array(
			$this->league->fullname => "league/view/$id",
			$this->title => 0,
		));

		list($order, $season, $round) = $this->league->calculate_standings(array( 'round' => $current_round ));


 		// let's add "seed" into the mix:
		$seeded_order = array();
		for ($i = 0; $i < count($order); $i++) {
			$seeded_order[$i+1] = $order[$i];
		}
		//reset($order);
		$order = $seeded_order;

		// if this is a ratings ladder league and  we're asking for "team" standings, only show
		// the 5 teams above and 5 teams below this team ... don't bother if there are
		// 24 teams or less (24 is probably the largest fall league size)... and, if $showall
		// is set, don't remove items from $order.
		$more_before = 0;
		$more_after = 0;

		if ( !$this->showall && $this->teamid
		    && ( $this->league->schedule_type == "ratings_ladder"
			 || $this->league->schedule_type == "ratings_wager_ladder")
			&& count($order) > 24) {
			$index_of_this_team = 0;
			foreach ($order as $i => $value) {
				if ($value == $this->teamid) {
					$index_of_this_team = $i;
					break;
				}
			}
			reset($order);
			$count = count($order);
			// use "unset($array[$index])" to remove unwanted elements of the order array
			for ($i = 1; $i < $count+1; $i++) {
				if ($i < $index_of_this_team - 5 || $i > $index_of_this_team + 5) {
					unset($order[$i]);
					if ($i < $index_of_this_team - 5) {
						$more_before = 1;
					}
					if ($i > $index_of_this_team + 5) {
						$more_after = 1;
					}
				}
			}
			reset($order);
		}

		/* Build up header */
		$header = array( array('data' => 'Seed', 'rowspan' => 2) );
		$header[] = array( 'data' => 'Team', 'rowspan' => 2 );
		if( $this->league->schedule_type == "ratings_ladder"
		    || $this->league->schedule_type == "ratings_wager_ladder" ) {
			$header[] = array('data' => "Rating", 'rowspan' => 2);
		}

		$subheader = array();

		if( variable_get('narrow_display', '0') ) {
			$win = 'W';
			$loss = 'L';
			$tie = 'T';
			$default = 'D';
			$for = 'PF';
			$against = 'PA';
		} else {
			$win = 'Win';
			$loss = 'Loss';
			$tie = 'Tie';
			$default = 'Dfl';
			$for = 'PF';
			$against = 'PA';
		}

		// Ladder leagues display standings differently.
		// Eventually this should just be a brand new object.
		if( $this->league->schedule_type == "ratings_ladder"
		    || $this->league->schedule_type == "ratings_wager_ladder" ) {
			$header[] = array('data' => 'Season To Date', 'colspan' => 7);
			foreach(array($win, $loss, $tie, $default, $for, $against, "+/-") as $text) {
				$subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
			}
		} else {
			if($current_round) {
				$header[] = array('data' => "Current Round ($current_round)", 'colspan' => 7);
				foreach(array($win, $loss, $tie, $default, $for, $against, "+/-") as $text) {
					$subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
				}
			}

			$header[] = array('data' => 'Season To Date', 'colspan' => 7);
			foreach(array($win, $loss, $tie, $default, $for, $against, "+/-") as $text) {
				$subheader[] = array('data' => $text, 'class'=>'subtitle', 'valign'=>'bottom');
			}
		}

		$header[] = array('data' => "Streak", 'rowspan' => 2);
		$header[] = array('data' => "Avg.<br>SOTG", 'rowspan' => 2);

		$rows[] = $subheader;

		if ($more_before) {
			$rows[] = array(array( 'data' => l("... ... ...", "league/standings/$id/$this->teamid/1"), 'colspan' => 13, 'align' => 'center'));
		}

		// boolean for coloration of standings table
		$colored = false;
		$firsttimethrough = true;

		while(list($seed, $tid) = each($order)) {

			if ($firsttimethrough) {
				$firsttimethrough = false;
				for ($i = 1; $i < $seed; $i++) {
					if ($i %8 == 0) {
						$colored = !$colored;
					}
				}
			}
			$rowstyle = "none";
			if ($colored) {
				$rowstyle = "tierhighlight";
			}
			if ($seed % 8 == 0) {
				$colored = !$colored;
			}
			if ($this->teamid == $tid) {
				if ($rowstyle == "none") {
					$rowstyle = "teamhighlight";
				} else {
					$rowstyle = "tierhighlightteam";
				}
			}
			$row = array( array('data'=>"$seed", 'class'=>"$rowstyle"));
			$row[] = array( 'data'=>l(display_short_name($season[$tid]->name, 35), "team/view/$tid"), 'class'=>"$rowstyle");

			// Don't need the current round for a ladder schedule.
			if ($this->league->schedule_type == "roundrobin") {
				if($current_round) {
					$old_rowstyle = $rowstyle;
					$rowstyle = "standings";
					if ($tid == $this->teamid) {
						$rowstyle = "teamhighlight";
					}
					$row[] = array( 'data' => $round[$tid]->win, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->loss, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->tie, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->defaults_against, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->points_for, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->points_against, 'class'=>"$rowstyle");
					$row[] = array( 'data' => $round[$tid]->points_for - $round[$tid]->points_against, 'class'=>"$rowstyle");
					$rowstyle = $old_rowstyle;
				}
			}

			if ($this->league->schedule_type == "ratings_ladder"
			    || $this->league->schedule_type == "ratings_wager_ladder" ) {
				$row[] = array( 'data' => $season[$tid]->rating, 'class'=>"$rowstyle");
			}
			$row[] = array( 'data' => $season[$tid]->win, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->loss, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->tie, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->defaults_against, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->points_for, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->points_against, 'class'=>"$rowstyle");
			$row[] = array( 'data' => $season[$tid]->points_for - $season[$tid]->points_against, 'class'=>"$rowstyle");

			if( count($season[$tid]->streak) > 1 ) {
				$row[] = array( 'data' => count($season[$tid]->streak) . $season[$tid]->streak[0], 'class'=>"$rowstyle");
			} else {
				$row[] = array( 'data' => '-', 'class'=>"$rowstyle");
			}


			$avg = $s->average_sotg( $season[$tid]->spirit, false);
			$symbol = $s->full_spirit_symbol_html( $avg );
			$row[] = array(
				'data' => $symbol,
				'class'=>"$rowstyle");
			$rows[] = $row;
		}

		if ($more_after) {
			$rows[] = array(array( 'data' => l("... ... ...", "league/standings/$id/$this->teamid/1"), 'colspan' => 13, 'align' => 'center'));
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}

?>
