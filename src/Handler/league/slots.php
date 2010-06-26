<?php
require_once('Handler/schedule/view.php');

class league_slots extends schedule_view
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','edit', $this->league->league_id);
	}

	function process ()
	{
		global $dbh;

		$this->template_name = 'pages/league/slots.tpl';

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
		$this->title = "Field Availability Report &raquo; {$this->league->fullname} &raquo; $formattedDay";

		$sth = $dbh->prepare('SELECT
			g.slot_id,
			COALESCE(f.code, pf.code) AS field_code,
			COALESCE(f.num, pf.num)   AS field_num,
			COALESCE(f.region, pf.region) AS field_region,
			g.fid,
			t.region_preference AS home_region_preference,
			IF(g.fid = t.home_field,
				1,
				COALESCE(f.region,pf.region) = t.region_preference) AS is_preferred,
			g.game_id

		FROM
			league_gameslot_availability l,
			gameslot g
				LEFT JOIN schedule s ON (g.game_id = s.game_id)
				LEFT JOIN team t ON (s.home_team = t.team_id),
			field f LEFT JOIN field pf ON (f.parent_fid = pf.fid)
		WHERE l.league_id = ?
			AND g.game_date = ?
			AND g.slot_id = l.slot_id
			AND f.fid = g.fid
			ORDER BY field_code, field_num');
		$sth->execute( array ($this->league->league_id,
				sprintf('%d-%d-%d', $year, $month, $day)) );

		$num_open = 0;
		$slots = array();
		while($g = $sth->fetch()) {
			// load game info, if game scheduled
			if ($g['game_id']) {
				$g['game'] = game_load( array('game_id' => $g['game_id']) );
			} else {
				$num_open++;
			}
			$slots[] = $g;
		}

		$allDays = array('sunday' => 0, 'monday' => 1, 'tuesday' => 2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6);
		$day_indexes = array();
		$league_days = explode(',',$this->league->day);
		foreach($league_days as $day) {
			$day_indexes[] = $allDays[strtolower($day)];
		}

		$this->smarty->assign('league_days', implode(',', $day_indexes));

		$this->smarty->assign('slots', $slots);
		$this->smarty->assign('num_fields', count($slots));
		$this->smarty->assign('num_open', $num_open);
		return true;
	}
}

?>
