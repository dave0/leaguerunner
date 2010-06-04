<?php
require_once('Handler/FieldHandler.php');
class slot_create extends FieldHandler
{
	private $yyyy;
	private $mm;
	private $dd;

	function __construct( $field_id, $year, $month, $day )
	{
		parent::__construct( $field_id );
		$today = getdate();

		$this->yyyy = is_numeric($year)  ? $year  : $today['year'];
		$this->mm   = is_numeric($month) ? $month : $today['mon'];
		$this->dd   = is_numeric($day)   ? $day   : null;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('gameslot','create');
	}

	function process()
	{
		$this->title = "Create Game Slot";

		if($field->status != 'open') {
			error_exit("That field is closed");
		}

		if ( $this->dd ) {
			if( !validate_date_input($this->yyyy, $this->mm, $this->dd) ) {
				error_exit("That date is not valid");
			}
			$datestamp = mktime(6,0,0,$this->mm,$this->dd,$this->yyyy);
		} else {
			return $this->datePick($field, $this->yyyy, $this->mm, $this->dd);
		}

		$edit = &$_POST['edit'];
		switch($edit['step']) {
			case 'perform':
			# Processing should:
			#   - check for overlaps with existing slots
			#   - insert into gameslot table
			#   - insert availability for gameslot into availability table.
			# the overlap-checking probably belongs in slot.inc
				if ( $this->perform( $field, $edit, $datestamp) ) {
					local_redirect(url("field/view/$field->fid"));
				} else {
					error_exit("Aieee!  Bad things happened in gameslot create");
				}
				break;
			case 'confirm':
				$this->setLocation(array(
					"$field->fullname $field_num" => "field/view/$field->fid",
					$this->title => 0
				));
				return $this->generateConfirm($field, $edit, $datestamp);
				break;
			default:
				$this->setLocation(array(
					$field->fullname => "field/view/$field->fid",
					$this->title => 0
				));
				return $this->generateForm($field, $datestamp);
				break;
		}
		error_exit("Error: This code should never be reached.");
	}

	function datePick ( &$field, $year, $month, $day)
	{
		$output = para("Select a date below to start adding gameslots.");

		$today = getdate();

		if(! validate_number($month)) {
			$month = $today['mon'];
		}

		if(! validate_number($year)) {
			$year = $today['year'];
		}

		$output .= generateCalendar( $year, $month, $day, "slot/create/$field->fid", "slot/create/$field->fid");

		return $output;
	}

	# TODO: Processing should:
	#   - check for overlaps with existing slots
	#   - insert into gameslot table
	#   - insert availability for gameslot into availability table.
	# the overlap-checking probably belongs in slot.inc
	function perform ( &$field, $edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		for( $i = 0; $i < $edit['repeat_for']; $i++) {
			$slot = new GameSlot;
			$slot->set('fid', $field->fid);
			$slot->set('game_date', strftime("%Y-%m-%d",$datestamp));
			$slot->set('game_start', $edit['start_time']);
			if( $edit['end_time'] != '---' ) {
				$slot->set('game_end', $edit['end_time']);
			}

			foreach($edit['availability'] as $league_id) {
				$slot->add_league($league_id);
			}

			if ( ! $slot->save() ) {
				// if we fail, bail
				return false;
			}
			$datestamp += (7 * 24 * 60 * 60);  // advance by a week
		}

		return true;
	}

	function generateForm ( &$field, $datestamp )
	{
		global $dbh;
		$output = form_hidden('edit[step]', 'confirm');

		$group = form_item("Date", strftime("%A %B %d %Y", $datestamp));
		$group .= form_select('Game Start Time','edit[start_time]', '18:30', getOptionsFromTimeRange(0000,2400,5), 'Time for games in this timeslot to start');
		$group .= form_select('Game Timecap','edit[end_time]', '---', getOptionsFromTimeRange(0000,2400,5), 'Time for games in this timeslot to end.  Choose "---" to assign the default timecap (dark) for that week.');
		$output .= form_group("Gameslot Information", $group);

		$weekday = strftime("%A", $datestamp);
		$leagues = array();
		// TODO: Pull into get_league_checkbox();
		$sth = $dbh->prepare("SELECT
			l.league_id,
			l.name,
			l.tier,
			l.year
			FROM league l
			WHERE l.schedule_type != 'none'
			AND (FIND_IN_SET(?, l.day) > 0)
			AND l.status = 'open'
			ORDER BY l.day,l.name,l.tier");
		$sth->execute( array( $weekday) );

		while($league = $sth->fetch(PDO::FETCH_OBJ) ) {
			if( $league->tier ) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$league->fullname .= ' ' . $league->year;
			$chex .= form_checkbox($league->fullname, 'edit[availability][]', $league->league_id, $league->selected);
		}
		$output .= form_group('Make Gameslot Available To:', $chex);

		$group = form_select('Weeks to repeat', 'edit[repeat_for]', '1', getOptionsFromRange(1,24),'Number of weeks to repeat this gameslot');
		$output .= form_group("Repetition", $group);

		$output .= form_submit('submit') . form_reset('reset');

		return form($output);
	}

	function generateConfirm ( &$field, &$edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = form_hidden('edit[step]', 'perform');

		$output .= para("Please confirm that this information is correct");

		$group = form_item("Date", strftime("%A %B %d %Y", $datestamp));
		$group .= form_item('Game Start Time',
			form_hidden('edit[start_time]', $edit['start_time']) . $edit['start_time']);
			$group .= form_item('Game Timecap',
			form_hidden('edit[end_time]', $edit['end_time']) . $edit['end_time']);
		$output .= form_group("Gameslot Information", $group);

		$group = '';
		foreach( $edit['availability'] as $league_id ) {
			$league = league_load( array('league_id' => $league_id) );
			$group .= $league->fullname . form_hidden('edit[availability][]', $league_id) . "<br />";
		}
		$output .= form_group('Make Gameslot Available To:', $group);

		$group = form_item('Weeks to repeat', form_hidden('edit[repeat_for]', $edit['repeat_for']) . $edit['repeat_for']);
		$output .= form_group("Repetition", $group);

		$output .= form_submit('submit');

		return form($output);
	}

	function isDataInvalid ( $edit = array() )
	{;
		$errors = "";
		if($edit['repeat_for'] > 52) {
			$errors .= "\n<li>You cannot repeat a schedule for more than 52 weeks.";
		}

		if( !is_array($edit['availability']) ) {
			$errors .= "\n<li>You must make this gameslot available to at least one league.";
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

?>
