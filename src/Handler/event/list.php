<?php
class event_list extends Handler
{
	private $query_args;

	function __construct ( )
	{
		global $lr_session;
		parent::__construct();

		if( $lr_session->is_admin() ) {
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

		$season_id = $_GET['season'];
		if( $season_id > 0 ) {
			$current_season = Season::load(array( 'id' => $season_id ));
			$this->query_args['season_id'] = $season_id;
			$this->title = "{$current_season->display_name} Registration";
		} else {
			$this->title = "Registration";
			$season_id = -1;
		}
		$this->template_name = 'pages/event/list.tpl';

		$pulldown_choices = getOptionsFromQuery(
			"SELECT s.id AS theKey, s.display_name AS theValue FROM season s, registration_events e WHERE e.season_id = s.id GROUP BY s.id HAVING count(*) > 0 ORDER BY s.year, s.season"
		);
		$pulldown_choices[-1] = "All open events";
		$this->smarty->assign('seasons', $pulldown_choices);
		$this->smarty->assign('season_id', $season_id);

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
