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
		global $dbh;
		$edit = &$_POST['edit'];

		$this->template_name = 'pages/slot/availability.tpl';
		$this->title = "{$this->slot->field->fullname} &raquo; Gameslot {$this->slot->slot_id} Availability";

		if( $edit['step'] == 'perform' ) {
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

			$this->smarty->assign('message', 'Changes saved');
		}

		$this->smarty->assign('slot', $this->slot);

		$weekday = strftime("%A", $this->slot->date_timestamp);
		$sth = League::query( array( '_day' => $weekday, 'status' => 'open', '_order' => 'l.league_id'));
		$leagues = array();
		while($league = $sth->fetchObject('League', array(LOAD_OBJECT_ONLY)) ) {
			if( $league->tier ) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$leagues[$league->league_id] = "($league->season_name) $league->fullname";
		}

		$this->smarty->assign('leagues', $leagues);

		$current_selections = array();
		foreach($this->slot->leagues as $league) {
			$current_selections[] = $league->league_id;
		}
		$this->smarty->assign('current_leagues', $current_selections);

		return true;
	}
}

?>
