<?php
class event_list extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','list');
	}

	function process ()
	{
		global $lr_session, $CONFIG;

		$links = $lr_session->has_permission('event','list');

		$this->title = 'Registration Event List';
		$this->setLocation(array($this->title => 0));

		$output = '';
		ob_start();
		$retval = @readfile(trim ($CONFIG['paths']['file_path'], '/') . "/data/registration_notice.html");
		if (false !== $retval) {
			$output = ob_get_contents();
		}
		ob_end_clean();

		if( $lr_session->is_admin() ) {
			$sth = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 1 YEAR)', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		} else {
			$sth = event_query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		}

		$type_desc = event_types();
		$last_type = '';
		$rows = array();

		while( $event = $sth->fetchObject('Event') ) {
			if ($event->type != $last_type) {
				$rows[] = array( array('colspan' => 4, 'data' => h2($type_desc[$event->type])));
				$last_type = $event->type;
			}

			if ($links) {
				$name = l($event->name, "event/view/$event->registration_id", array('title' => 'View event details'));
			}
			else {
				$name = $event->name;
			}
			$rows[] = array($name,
							'$' . ($event->total_cost()),
							$event->open,
							$event->close);
		}

		$header = array( 'Registration', 'Cost', 'Opens on', 'Closes on');
		$output .= table ($header, $rows, array('alternate-colours' => true));

		return $output;
	}
}
?>
