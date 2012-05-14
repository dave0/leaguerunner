<?php
require_once('Handler/FieldHandler.php');
class slot_create extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('gameslot','create');
	}

	function process()
	{
		$this->title = "{$this->field->fullname} Create Game Slot";

		if($this->field->status != 'open') {
			error_exit("That field is closed");
		}

		$edit = &$_POST['edit'];

		if ( $edit['date'] ) {
			list( $year, $month, $day) = preg_split("/[\/-]/", $edit['date']);
			$today = getdate();

			$yyyy = is_numeric($year)  ? $year  : $today['year'];
			$mm   = is_numeric($month) ? $month : $today['mon'];
			$dd   = is_numeric($day)   ? $day   : $today['mday'];

			if( !validate_date_input($yyyy, $mm, $dd) ) {
				error_exit( 'That date is not valid' );
			}
			$datestamp = mktime(6,0,0,$mm,$dd,$yyyy);
		}

		switch($edit['step']) {
			case 'perform':
				if ( ! $this->perform( $edit, $datestamp) ) {
					error_exit("Aieee!  Bad things happened in gameslot create");
				}
				local_redirect(url("field/view/{$this->field->fid}"));
				break;
			case 'confirm':
				$this->template_name = 'pages/slot/create/confirm.tpl';
				return $this->generateConfirm($edit, $datestamp);
				break;
			case 'details':
				$this->template_name = 'pages/slot/create/step2.tpl';
				return $this->generateForm($datestamp);
				break;
			default:
				$this->template_name = 'pages/slot/create/step1.tpl';
				return true;
		}
		error_exit("Error: This code should never be reached.");
	}

	# TODO: Processing should:
	#   - check for overlaps with existing slots
	#   - insert into gameslot table
	#   - insert availability for gameslot into availability table.
	# the overlap-checking probably belongs in slot.inc
	# TODO: transaction!
	function perform ( $edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			info_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		for( $i = 0; $i < $edit['repeat_for']; $i++) {
			$slot = new GameSlot;
			$slot->set('fid', $this->field->fid);
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

	function generateForm ( $datestamp )
	{
		$this->smarty->assign('field',      $this->field);
		$this->smarty->assign('start_date', $datestamp);

		$this->smarty->assign('start_time', '18:30');
		$this->smarty->assign('end_time', '---');
		$this->smarty->assign('start_end_times', getOptionsFromTimeRange(0000,2400,5) );

		$weekday = strftime("%A", $datestamp);
		$leagues = array();
		$sth = League::query( array( '_day' => $weekday, 'status' => 'open' ));
		$leagues = array();
		while($league = $sth->fetchObject('League', array(LOAD_OBJECT_ONLY)) ) {
			$leagues[$league->league_id] = "$league->season_name - $league->fullname";
		}
		$this->smarty->assign('leagues', $leagues);

		$this->smarty->assign('repeat_options', getOptionsFromRange(1,24));

		return true;
	}

	function generateConfirm ( &$edit, $datestamp )
	{
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			info_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->smarty->assign('start_date', $datestamp);
		$this->smarty->assign('edit', $edit);
		$leagues = array();
		foreach( $edit['availability'] as $league_id ) {
			$league = League::load( array('league_id' => $league_id) );
			$leagues[ $league_id] = $league->fullname;
		}
		$this->smarty->assign('leagues', $leagues);

		return true;
	}

	function isDataInvalid ( $edit = array() )
	{
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
