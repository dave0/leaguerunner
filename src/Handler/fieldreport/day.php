<?php
class fieldreport_day extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view reports');
	}

	function process ()
	{
		$this->template_name = 'pages/fieldreport/day.tpl';

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
		$this->title = "Field Reports &raquo; $formattedDay";

		$sth = FieldReport::query( array(
			'date_played' => sprintf('%d-%d-%d', $yyyy, $mm, $dd),
			'_order' => 'field_id ASC') );

		$reports = array();
		while($r = $sth->fetchObject('FieldReport') ) {
			$r->field  = Field::load(array('fid' => $r->field_id));
			$reports[] = $r;
		}

		$this->smarty->assign('reports', $reports);

		return true;
	}
}

?>
