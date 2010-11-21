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

		$leagues = array();
		$sth = $dbh->prepare("SELECT
			l.league_id,
			l.name,
			l.tier
			FROM league l
			WHERE l.schedule_type != 'none'
			AND (FIND_IN_SET(?, l.day) > 0)
			AND l.status = 'open'
			ORDER BY l.day,l.name,l.tier"
		);
		$weekday = strftime("%A", $this->slot->date_timestamp);
		$sth->execute( array( $weekday) );

		while($league = $sth->fetch(PDO::FETCH_OBJ) ) {
			if( $league->tier ) {
				$league->fullname = sprintf("$league->name Tier %02d", $league->tier);
			} else {
				$league->fullname = $league->name;
			}
			$leagues[$league->league_id] = $league->fullname;
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
