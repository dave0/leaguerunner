<?php
class fieldreport_day extends Handler
{
	private $yyyy;
	private $mm;
	private $dd;

	function __construct( $year = null, $month = null, $day = null)
	{
		parent::__construct();
		$today = getdate();

		$this->yyyy = is_numeric($year)  ? $year  : $today['year'];
		$this->mm   = is_numeric($month) ? $month : $today['mon'];
		$this->dd   = is_numeric($day)   ? $day   : null;


	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view reports');
	}

	function process ()
	{
		$this->title = "Field Reports";

		if( $this->dd ) {
			if( !validate_date_input($this->yyyy, $this->mm, $this->dd) ) {
				return 'That date is not valid';
			}
			$formattedDay = strftime('%A %B %d %Y', mktime (6,0,0,$this->mm,$this->dd,$this->yyyy));
			$this->title = "$this->title &raquo; $formattedDay";
			return $this->displayReportsForDay( $this->yyyy, $this->mm, $this->dd );
		} else {
			$output = para('Select a date below on which to view field reports');
			$output .= generateCalendar( $this->yyyy, $this->mm, $this->dd, 'fieldreport/day', 'fieldreport/day');
			return $output;
		}
	}

	function displayReportsForDay ( $year, $month, $day )
	{
		$sth = field_report_query ( array(
			'date_played' => sprintf('%d-%d-%d', $year, $month, $day),
			'_order' => 'field_id ASC') );

		$header = array("Date Played", "Time Reported", "Field","Game","Reported By","Report");
		$rows = array();
		while($r = $sth->fetchObject('FieldReport') ) {
			$field    = field_load(array('fid' => $r->field_id));
			$rows[] = array(
				$r->date_played,
				$r->created,
				l( "$field->code$field->num", url('field/view/' . $r->field_id) ),
				l( $r->game_id,  url("game/view/" . $r->game_id)),
				l( $r->reporting_user_fullname,  url("person/view/" . $r->reporting_user_id)),
				$r->report_text,
			);
		}

		$output .= "<div class='listtable'>" . table($header, $rows) . "</div>";
		return $output;
	}
}

?>
