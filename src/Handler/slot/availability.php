<?php
require_once('Handler/SlotHandler.php');
class slot_availability extends SlotHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('gameslot', 'availability', $this->slot->slot_id);
	}

	function process()
	{
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				foreach( $this->slot->leagues as $league) {
					if( !count( $edit['availability']) || !in_array( $league->league_id, $edit['availability']) ) {
						$this->slot->remove_league( $league );
					}
				}
				if( count($edit['availability']) > 0 ) {
					foreach ( $edit['availability'] as $league ) {
						$this->slot->add_league($league);
					}
				}
				if( ! $this->slot->save() ) {
					error_exit("Internal error: couldn't save gameslot");
				}
				local_redirect(url("slot/availability/" . $this->slot->slot_id));
				break;
			default:
				$this->setLocation(array(
					$this->slot->field->fullname => "field/view/" . $this->slot->fid,
					$this->title => 0
				));
				return $this->generateForm( $this->slot );
		}
	}

	function generateForm()
	{
		global $dbh;
		# What day is this weekday?
		$weekday = strftime("%A", $this->slot->date_timestamp);

		$output = "<p>Availability for " . $this->slot->game_date . " " . $this->slot->game_start. "</p>";

		$leagues = array();
		// TODO: Pull into get_league_checkbox();
		$sth = $dbh->prepare("SELECT
			l.league_id,
			l.name,
			l.tier
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
			$league->selected = false;
			$leagues[$league->league_id] = $league;
		}

		foreach($this->slot->leagues as $league) {
			$leagues[$league->league_id]->selected = true;
		}

		while(list($id, $league) = each($leagues) ) {
			$chex .= form_checkbox($league->fullname, 'edit[availability][]', $id, $league->selected);
		}

		$chex .= form_submit('submit') . form_reset('reset');
		$output .= form_hidden('edit[step]', 'perform');

		$output .= form_group('Make Gameslot Available To:', $chex);

		return form($output);
	}
}

?>
