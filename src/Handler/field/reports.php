<?php

require_once('Handler/FieldHandler.php');

class field_reports extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->field) {
			error_exit("That field does not exist");
		}
		return $lr_session->has_permission('field','view reports', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;

		$this->setLocation(array(
			'Reports' => "field/view/" . $this->field->fid,
			$this->field->fullname => 0
		));

		$sth = field_report_query(array('field_id' => $this->field->fid, '_order' => 'created DESC' ));

		$header = array("Date Played","Time Reported", "Game","Reported By","Report");
		$rows = array();
		while($r = $sth->fetchObject('FieldReport') ) {
			$rows[] = array(
				$r->date_played,
				$r->created,
				l( $r->game_id,  url("game/view/" . $r->game_id)),
				l( $r->reporting_user_fullname,  url("person/view/" . $r->reporting_user_id)),
				$r->report_text,
			);
		}

		return "<div class='listtable'>" . table($header, $rows) . "</div>";
	}
}
?>
