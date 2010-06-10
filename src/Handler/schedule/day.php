<?php

require_once('Handler/schedule/view.php');
class schedule_day extends schedule_view
{
	private $yyyy;
	private $mm;
	private $dd;

	function __construct( $year = null, $month = null, $day = null)
	{
		$today = getdate();

		$this->yyyy = is_numeric($year)  ? $year  : $today['year'];
		$this->mm   = is_numeric($month) ? $month : $today['mon'];
		$this->dd   = is_numeric($day)   ? $day   : null;


	}

	function has_permission ()
	{
		// Everyone gets to see the schedule
		return true;
	}

	function process ()
	{
		$this->title = "View Day";

		if( $this->dd ) {
			if( !validate_date_input($this->yyyy, $this->mm, $this->dd) ) {
				return 'That date is not valid';
			}
			$formattedDay = strftime('%A %B %d %Y', mktime (6,0,0,$this->mm,$this->dd,$this->yyyy));
			$this->title .= " &raquo; $formattedDay";
			return $this->displayGamesForDay( $this->yyyy, $this->mm, $this->dd );
		} else {
			$output = para('Select a date below on which to view all scheduled games');
			$output .= generateCalendar( $this->yyyy, $this->mm, $this->dd, 'schedule/day', 'schedule/day');
			return $output;
		}
	}

	/**
	 * List all games on a given day.
	 */
	function displayGamesForDay ( $year, $month, $day )
	{
		$sth = game_query ( array(
			'game_date' => sprintf('%d-%d-%d', $year, $month, $day),
			'published' => true,
			'_order' => 'g.game_start, field_code') );

		$rows = array(
			$this->schedule_heading(strftime('%a %b %d %Y',mktime(6,0,0,$month,$day,$year))),
			$this->schedule_subheading( ),
		);
		while($g = $sth->fetchObject('Game') ) {
			if( ! ($g->published || $lr_session->has_permission('league','edit schedule', $this->league->league_id) ) ) {
				continue;
			}
			$rows[] = $this->schedule_render_viewable($g);
		}
		$output .= "<div class='schedule'>" . table($header, $rows) . "</div>";
		return $output;
	}
}

?>
