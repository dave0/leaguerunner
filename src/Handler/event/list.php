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

		$this->title = 'Registration Event List';
		$this->template_name = 'pages/event/list.tpl';

		if( $lr_session->is_admin() ) {
			$sth = Event::query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 1 YEAR)', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		} else {
			$sth = Event::query( array( '_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()', '_order' => 'e.type,e.open,e.close,e.registration_id') );
		}

		$type_desc = event_types();
		$events = array();

		while( $e = $sth->fetchObject('Event') ) {
			$e->full_type = $type_desc[$e->type];
			$events[] = $e;
		}
		$this->smarty->assign('events', $events);

		return true;
	}
}
?>
