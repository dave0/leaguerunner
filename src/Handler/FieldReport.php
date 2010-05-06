<?php
function fieldreport_dispatch()
{
	$op = arg(1);
	$id = arg(2);
	switch($op) {
		case 'day':
			$obj = new FieldReportViewDay;
			break;
		default:
			$obj = null;
	}

	return $obj;
}

class FieldReportViewDay extends Handler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view reports');
	}

	function process ()
	{
		$this->title = "Field Reports";

		$today = getdate();

		$year  = arg(2);
		$month = arg(3);
		$day   = arg(4);

		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}

		if( $day ) {
			if( !validate_date_input($year, $month, $day) ) {
				return "That date is not valid";
			}
			$formattedDay = strftime("%A %B %d %Y", mktime (6,0,0,$month,$day,$year));
			$this->setLocation(array(
				"$this->title &raquo; $formattedDay" => 0));
			return $this->displayReportsForDay( $year, $month, $day );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para("Select a date below on which to view all field reports");
			$output .= generateCalendar( $year, $month, $day, 'fieldreport/day', 'fieldreport/day');
			return $output;
		}
	}

	/**
	 * List all games on a given day.
	 */
	function displayReportsForDay ( $year, $month, $day )
	{
		$sth = field_report_query ( array(
			'date_played' => sprintf('%d-%d-%d', $year, $month, $day),
			'_order' => 'field_id ASC') );

		$header = array("Date Played", "Time Reported", "Field","Game","Reported By","Report");
		$rows = array();
		while($r = $sth->fetchObject('FieldReport') ) {
			$field    = field_load(array('field_id' => $r->field_id));
			$rows[] = array(
				$r->date_played,
				$r->created,
				l( $field->code, url('field/view/' . $r->field_id) ),
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
