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
		global $CONFIG;
		$query_args = array(
			'_order' => 'e.type,e.open,e.close,e.registration_id'
		);

		$season_id = $_GET['season'];
		if( $season_id > 0 ) {
			$current_season = Season::load(array( 'id' => $season_id ));
			$query_args['season_id'] = $season_id;
			$this->title = "{$current_season->display_name} Registration";
		} else {
			$this->title = "Registration";
			$query_args['_extra'] = 'e.open < DATE_ADD(NOW(), INTERVAL 1 WEEK) AND e.close > NOW()';
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
		$sth = Event::query( $query_args );
		while( $e = $sth->fetchObject('Event') ) {
			$e->full_type = $type_desc[$e->type];
			$events[] = $e;
		}
		$this->smarty->assign('events', $events);

		return true;
	}
}
?>
