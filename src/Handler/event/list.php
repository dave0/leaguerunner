<?php
class event_list extends Handler
{
	private $query_args;

	function __construct ( $all = null )
	{
		global $lr_session;
		parent::__construct();

		if( (is_null($all) && $lr_session->is_admin())
		    || ($all == 'all') ) {
			$this->query_args = array(
				'_extra' => 'e.open < e.close',
				'_order' => 'e.type,e.open,e.close,e.registration_id'
			);
		} else {
			$this->query_args = array(
				'_extra' => 'e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()',
				'_order' => 'e.type,e.open,e.close,e.registration_id'
			);
		}
	}

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

		$type_desc = event_types();

		$events = array();
		$sth = Event::query( $this->query_args );
		while( $e = $sth->fetchObject('Event') ) {
			$e->full_type = $type_desc[$e->type];
			$events[] = $e;
		}
		$this->smarty->assign('events', $events);
		$this->smarty->assign('is_admin', $lr_session->is_admin());

		return true;
	}
}
?>
