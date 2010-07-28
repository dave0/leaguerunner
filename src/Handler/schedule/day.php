<?php

class schedule_day extends Handler
{
	function has_permission ()
	{
		// Everyone gets to see the schedule
		return true;
	}

	function process ()
	{
		$this->title = "Daily Schedule";

		list( $year, $month, $day) = preg_split("/[\/-]/", $_POST['edit']['date']);
		$today = getdate();

		$yyyy = is_numeric($year)  ? $year  : $today['year'];
		$mm   = is_numeric($month) ? $month : $today['mon'];
		$dd   = is_numeric($day)   ? $day   : $today['mday'];

		if( !validate_date_input($yyyy, $mm, $dd) ) {
			error_exit( 'That date is not valid' );
		}

		$this->smarty->assign('date', sprintf("%4d/%02d/%02d", $yyyy, $mm, $dd));

		$formattedDay = strftime('%A %B %d %Y', mktime (6,0,0,$mm,$dd,$yyyy));
		$this->title .= " &raquo; $formattedDay";
		$this->template_name = 'pages/schedule/day.tpl';

		$sth = Game::query ( array(
			'game_date' => sprintf('%d-%d-%d', $yyyy, $mm, $dd),
			'published' => true,
			'_order' => 'g.game_start, field_code') );

		while($g = $sth->fetchObject('Game') ) {
			if( ! ($g->published || $lr_session->has_permission('league','edit schedule', $this->league->league_id) ) ) {
				continue;
			}
			$games[] = $g;
		}
		$this->smarty->assign('games', $games);
	}
}

?>
