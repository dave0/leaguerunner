<?php
// TODO:  I don't think this feature is linked from anywhere?!
class slot_day extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('gameslot', 'day');
	}

	function process ()
	{
		$this->template_name = 'pages/slot/day.tpl';

		list( $year, $month, $day) = preg_split("/[\/-]/", $_GET['date']);
		$today = getdate();

		$yyyy = is_numeric($year)  ? $year  : $today['year'];
		$mm   = is_numeric($month) ? $month : $today['mon'];
		$dd   = is_numeric($day)   ? $day   : $today['mday'];

		if( !validate_date_input($yyyy, $mm, $dd) ) {
			error_exit( 'That date is not valid' );
		}

		$this->smarty->assign('date', sprintf("%4d/%02d/%02d", $yyyy, $mm, $dd));

		$formattedDay = strftime('%A %B %d %Y', mktime (6,0,0,$mm,$dd,$yyyy));
		$this->title = "Field Availability Report &raquo; $formattedDay";

		$sth = GameSlot::query ( array( 'game_date' => sprintf('%d-%d-%d', $year, $month, $day), '_order' => 'g.game_start, field_code, field_num') );
		$num_open = 0;
		$slots = array();
		while($g = $sth->fetch()) {
			// load game info, if game scheduled
			if ($g['game_id']) {
				$g['game'] = Game::load( array('game_id' => $g['game_id']) );
			} else {
				$num_open++;
			}
			$slots[] = $g;
		}

		$this->smarty->assign('slots', $slots);
		$this->smarty->assign('num_fields', count($slots));
		$this->smarty->assign('num_open', $num_open);
		return true;
	}
}

?>
