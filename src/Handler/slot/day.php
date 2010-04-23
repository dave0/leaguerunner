<?php
// TODO:  I don't think this feature is linked from anywhere?!
class slot_day extends Handler
{
	private $yyyy;
	private $mm;
	private $dd;

	function __construct( $year, $month, $day )
	{
		parent::__construct();
		$today = getdate();

		$this->yyyy = is_numeric($year)  ? $year  : $today['year'];
		$this->mm   = is_numeric($month) ? $month : $today['mon'];
		$this->dd   = is_numeric($day)   ? $day   : null;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('slot', 'day');
	}

	function process ()
	{
		$this->title = "View Day";

		if( $this->dd ) {
			if( !validate_date_input($this->yyyy, $this->mm, $this->dd) ) {
				error_exit("That date is not valid");
			}
			$formattedDay = strftime("%A %B %d %Y", mktime (6,0,0,$this->mm,$this->dd,$this->yyyy));
			$this->setLocation(array(
				"$this->title &raquo; $formattedDay" => 0));
			return $this->display_for_day( $this->yyyy, $this->mm, $this->dd );
		} else {
			$this->setLocation(array( "$this->title" => 0));
			$output = para("Select a date below on which to view all available fields");
			$output .= generateCalendar( $this->yyyy, $this->mm, $this->dd, 'slot/day', 'slot/day');
			return $output;
		}
	}

	function display_for_day ( $year, $month, $day )
	{
		global $lr_session;
		$sth = slot_query ( array( 'game_date' => sprintf('%d-%d-%d', $year, $month, $day), '_order' => 'g.game_start, field_code, field_num') );

		if( ! $sth ) {
			error_exit("Nothing available on that day");
		}

		$header = array("Field","Start Time","End Time","Booking", "Actions");
		$rows = array();
		while($slot = $sth->fetch(PDO::FETCH_OBJ) ) {
			$booking = '';

			$field = field_load( array('fid' => $slot->fid));

			$actions = array();
			if( $lr_session->has_permission('gameslot','edit', $slot->slot_id)) {
				$actions[] = l('change avail', "slot/availability/$slot->slot_id");
			}
			if( $lr_session->has_permission('gameslot','delete', $slot->slot_id)) {
				$actions[] = l('delete', "slot/delete/$slot->slot_id");
			}
			if($slot->game_id) {
				$game = game_load( array('game_id' => $slot->game_id) );
				$booking = l($game->league_name,"game/view/$slot->game_id");
				if( $lr_session->has_permission('game','reschedule', $slot->game_id)) {
					$actions[] = l('reschedule/move', "game/reschedule/$slot->game_id");
				}
			}
			$rows[] = array(l("$field->code $field->num","field/view/$field->fid"), $slot->game_start, $slot->game_end, $booking, theme_links($actions));
		}
		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}

?>
