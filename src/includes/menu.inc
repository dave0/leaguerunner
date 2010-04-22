<?php
/** 
 * Add a child to a parent menu. 
 * parentName, thisKey, and title are required
 * in $args, we can have:
 *   link (the URL we link to.  If absent, this is a pure container, 
 *         and this item will always be expanded if it has children)
 *   weight (lower = floats, higher = sinks)
 *   expanded (true = always open)
 * Menu should be started with something like:
 *   menu_add_child( null, '_root', 'Root of Menu', array( 'expanded' =>
 *   true));
 */
function menu_add_child( $parentName, $name, $title, $args = array() )
{
	global $_menu;

	if(!is_array($_menu) ) {
		$_menu = array();
	}

	if(array_key_exists($name, $_menu)) {
		return;
	}

	/* Concat parent/child names together to prevent collisions */
	$item = array(
		'name' 	   => $name,
		'title'    => $title,
		'parent'   => $parentName,
		'weight'   => 0,  // Default weight
		'children' => array(),
		'expanded' => false,
	); 

	$_menu[$name] = array_merge($item, $args);

	/* and add to children of parent if parent exists */
	if($parentName) { 
		$_menu[$parentName]['children'][] = $name;
	}

}

/**
 * Returns an array with the menu keys for all parent menu items
 * of the given path.
 */
function _menu_get_parents( $path ) {
	global $_menu;
	static $parents;
	
	if( empty($parents) ) {
		$parents = array();

		$itemKey = null;
		while(list($key, $item) = each($_menu)) {
			if( array_key_exists( 'link', $item ) && $item['link'] == $path) {
				$itemKey = $key;
				break;
			}
		}
		reset($_menu);

		while( $itemKey ) {
			$itemKey = $_menu[$itemKey]['parent'];
			$parents[] = $itemKey;
		}
	}

	return $parents;
}

/**
 * Returns active menu item corresponding to current page
 */
function _menu_get_active( $path ) {
	global $_menu;
	static $itemKey;
	if( empty($itemKey) ) {
		$itemKey = null;
		while(list($key, $item) = each($_menu)) {
			if( array_key_exists( 'link', $item ) && $item['link'] == $path) {
				$itemKey = $key;
				break;
			}
		}
		reset($_menu);
	}
	return $itemKey;
}

/**
 * Returns a rendered menu tree.
 */
function menu_render($parentKey = '_root', $depth = 0) {
	global $_menu;

	$parents = _menu_get_parents($_GET["q"]);
	foreach($parents as $key) {
			$_menu[$key]['expanded'] = true;
		}
	
	$active  = _menu_get_active($_GET["q"]);
	$_menu[$active]['expanded'] = true;

	$output = '';
	if ($_menu[$parentKey]['children']) {
		usort($_menu[$parentKey]['children'], "_menu_internal_sort");
		foreach ($_menu[$parentKey]['children'] as $itemKey) {
			/* Always expand in this case because otherwise the children
			 * become unreachable.
			 */
			$mustExpand = $_menu[$itemKey]['expanded'] || ($_menu[$itemKey]['children'] && !array_key_exists('link', $_menu[$itemKey]));
			
			if($mustExpand) {
				$style = ($_menu[$itemKey]['children'] ? 'expanded' : 'leaf');
			} else {
				$style = ($_menu[$itemKey]['children'] ? 'collapsed' : 'leaf');
			}
			if( $itemKey == $active ) {
				$style .= ' active';
			}

			$output .= "<li class='$style'>" . _menu_render_item($_menu[$itemKey], ($itemKey == $active));
			if ($mustExpand) {
				$output .= menu_render($itemKey, ($depth + 1));
			}
			$output .= "</li>";
		}
		$output = "<ul>$output</ul>";
	}
	return $output;
}

/**
 * Sort function for ordering menu children
 */
function _menu_internal_sort($a, $b) {
	global $_menu;

	$a = &$_menu[$a];
	$b = &$_menu[$b];

	if( $a["weight"] < $b["weight"] ) {
		return -1;
	} else if ($a["weight"] > $b["weight"]) {
		return 1;
	} 

	return $a["name"] < $b["name"] ? -1 : 1;
}
function _menu_render_item(&$item, $isActive) {

	if($isActive) {
		$attrs = array('class' => 'active');
	} else {
		$attrs = array();
	}

	if ( isset($item['link']) ) {
		return l($item['title'], $item['link'], $attrs);
	} else {
		return $item["title"];
	}
}

/**
 * Get the parent menu items that lead to a given page
 */
function menu_get_trail( $path = 0 )
{
	global $_menu;

	if(!$path) {
		$path = $_GET['q'];
	}

	$trail = array();
	$parents = _menu_get_parents($path);
	while( list(,$key) = each($parents) ) {
		if( ! $key ) {
			continue;
		}
		if(!is_null($_menu[$key])) {
			$trail[] = $_menu[$key];
		}
	}
	$trail = array_reverse($trail);
	$trail[] = $_menu[_menu_get_active($path)];
	return $trail;
}

/**
 * Construct our hierarchical menu
 */
# TODO: needs cleanup
function menu_build( )
{

	global $lr_session;

	menu_add_child('','_root','Root of Menus');

	# Handler/home.php
	menu_add_child('_root','home','Home', array('link' => 'home', 'weight' => '-20'));

	# Handler/login.php
	menu_add_child('_root','logout','Log Out', array('link' => 'logout', 'weight' => 20));

	if($lr_session->is_admin()) {
		# Handler/settings.php
		menu_add_child('_root','settings','Settings');
		menu_add_child('settings','settings/global','global settings', array('link' => 'settings/global'));
		menu_add_child('settings','settings/feature','feature settings', array('link' => 'settings/feature'));
		menu_add_child('settings','settings/rss','rss settings', array('link' => 'settings/rss'));

		# Handler/statistics.php
		menu_add_child('_root','statistics','Statistics');
	}

	# Handler/field.php
	menu_add_child('_root','field','Fields');
	menu_add_child('field','field/list','list fields', array('link' => 'field/list') );
	if( $lr_session->has_permission('field','list','closed') ) {
		menu_add_child('field','field/list/closed','list closed fields', array('weight' => 3, 'link' => 'field/list/closed') );
	}
	if( $lr_session->has_permission('field','create') ) {
		menu_add_child('field','field/create','create field', array('weight' => 6, 'link' => 'field/create') );
	}

	if( $lr_session->has_permission('field','view reports') ) {
		menu_add_child('field','fieldreport/day','field reports', array('weight' => 5, 'link' => 'fieldreport/day') );
	}

	# Handler/league.php
	if( $lr_session->is_player() ) {
		menu_add_child('_root','league','Leagues');
		menu_add_child('league','league/list','list leagues', array('link' => 'league/list') );
		if( $lr_session->is_valid() ) {
			while(list(,$league) = each($lr_session->user->leagues) ) {
				league_add_to_menu($league);
			}
			reset($lr_session->user->leagues);
		}
		if($lr_session->has_permission('league','create') ) {
			menu_add_child('league', 'league/create', "create league", array('link' => "league/create", 'weight' => 1));
		}
	}

	# Handler/event.php
	if( variable_get('registration', 0) ) {
		if( $lr_session->has_permission('event','list') ) {
			menu_add_child('_root','event','Registration');
			menu_add_child('event','event/list','list events', array('link' => 'event/list') );
		}

		if( $lr_session->has_permission('event','create') ) {
			menu_add_child('event','event/create','create event', array('weight' => 5, 'link' => 'event/create') );
		}
	}

	# Handler/person.php
	$id = $lr_session->attr_get('user_id');
	menu_add_child('_root', 'myaccount','My Account', array('weight' => -10, 'link' => "person/view/$id"));
	menu_add_child('myaccount', 'myaccount/edit','edit account', array('weight' => -10, 'link' => "person/edit/$id"));
	menu_add_child('myaccount', 'myaccount/pass', 'change password', array( 'link' => "person/changepassword/$id"));
	menu_add_child('myaccount', 'myaccount/signwaiver', 'view/sign player waiver', array( 'link' => "person/signwaiver", 'weight' => 3));
	if (variable_get('dog_questions', 1) && $lr_session->attr_get('has_dog') == 'Y') {
		menu_add_child('myaccount', 'myaccount/signdogwaiver', 'view/sign dog waiver', array( 'link' => "person/signdogwaiver", 'weight' => 4));
	}
	if( $lr_session->is_player() ) {
		menu_add_child('_root','person',"Players", array('weight' => -9));
		if($lr_session->has_permission('person','list') ) {
			menu_add_child('person','person/search',"search players", array('link' => 'person/search'));
		}

		if($lr_session->is_admin()) {
			$newUsers = person_count(array( 'status' => 'new' ));
			if($newUsers) {
				menu_add_child('person','person/listnew',"approve new accounts ($newUsers pending)", array('link' => "person/listnew"));
			}

			menu_add_child('person', 'person/create', "create account", array('link' => "person/create", 'weight' => 1));

			# Admin menu
			menu_add_child('settings', 'settings/person', 'user settings', array('link' => 'settings/person'));
			menu_add_child('statistics', 'statistics/person', 'player statistics', array('link' => 'statistics/person'));
		}
	}

	# Handler/registration.php
	if( variable_get('registration', 0) ) {
		if( $lr_session->has_permission('registration','history') ) {
			menu_add_child('event', 'registration/history/'.$lr_session->user->user_id, 'view history', array('link' => 'registration/history/' . $lr_session->user->user_id) );
		}

		if( $lr_session->is_admin() ) {
			menu_add_child('settings', 'settings/registration', 'registration settings', array('link' => 'settings/registration'));
			menu_add_child('statistics','statistics/registration','registration statistics', array('link' => 'statistics/registration') );
			menu_add_child('event','registration/registrations','download registrations', array('link' => 'registration/download') );
			menu_add_child('event','registration/unpaid','unpaid registrations', array('link' => 'statistics/registration/unpaid') );
		}
	}

	# Handler/team.php
	if( $lr_session->is_player() ) {
		menu_add_child('_root','team','Teams', array('weight' => -8));
		menu_add_child('team','team/list','list teams', array('link' => 'team/list') );
		menu_add_child('team','team/create','create team', array('link' => 'team/create', 'weight' => 1) );

		if( $lr_session->is_valid() ) {
			while(list(,$team) = each($lr_session->user->teams) ) {
				team_add_to_menu($team);
			}
			reset($lr_session->user->teams);
		}
		if( count($lr_session->user->historical_teams) ) {
			menu_add_child('team','person/historical','my historical teams', array('link' => "person/historical/{$lr_session->user->user_id}", 'weight' => 5) );
		}

		if($lr_session->has_permission('team','statistics')) {
			menu_add_child('statistics', 'statistics/team', 'team statistics', array('link' => 'statistics/team'));
		}
	}
}

/**
 * Add view/edit/delete links to the menu for the given league
 */
function league_add_to_menu( &$league, $parent = 'league' )
{
	global $lr_session;

	menu_add_child($parent, $league->fullname, $league->fullname, array('weight' => -10, 'link' => "league/view/$league->league_id"));

	if($league->schedule_type != 'none') {
		menu_add_child($league->fullname, "$league->fullname/standings",'standings', array('weight' => -1, 'link' => "league/standings/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/schedule",'schedule', array('weight' => -1, 'link' => "schedule/view/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/scores",'scores', array('weight' => -1, 'link' => "league/scores/$league->league_id"));
		if($lr_session->has_permission('league','add game', $league->league_id) ) {
			menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/edit", 'add games', array('link' => "game/create/$league->league_id"));
		}
		if($lr_session->has_permission('league','approve scores', $league->league_id) ) {
			menu_add_child($league->fullname, "$league->fullname/approvescores",'approve scores', array('weight' => 1, 'link' => "league/approvescores/$league->league_id"));
		}
	}

	if($lr_session->has_permission('league','edit', $league->league_id) ) {
		menu_add_child($league->fullname, "$league->fullname/edit",'edit league', array('weight' => 1, 'link' => "league/edit/$league->league_id"));
		if ( $league->schedule_type == "ratings_ladder" || $league->schedule_type == 'ratings_wager_ladder' ) {
			menu_add_child($league->fullname, "$league->fullname/ratings",'adjust ratings', array('weight' => 1, 'link' => "league/ratings/$league->league_id"));
		}
		menu_add_child($league->fullname, "$league->fullname/member",'add coordinator', array('weight' => 2, 'link' => "league/member/$league->league_id"));
	}

	if($lr_session->has_permission('league','view', $league->league_id, 'captain emails') ) {
		menu_add_child($league->fullname, "$league->fullname/captemail",'captain emails', array('weight' => 3, 'link' => "league/captemail/$league->league_id"));
	}

	if($lr_session->has_permission('league','view', $league->league_id, 'spirit') ) {
		menu_add_child($league->fullname, "$league->fullname/spirit",'spirit', array('weight' => 3, 'link' => "league/spirit/$league->league_id"));
	}
	if($lr_session->has_permission('league', 'download', $league->league_id, 'spirit') ) {
		menu_add_child($league->fullname, "$league->fullname/spirit_download",'spirit report', array('weight' => 3, 'link' => "league/spiritdownload/$league->league_id"));
	}
	if($lr_session->has_permission('league','edit', $league->league_id) ) {
		if ( $league->schedule_type == "ratings_ladder" || $league->schedule_type == 'ratings_wager_ladder' ) {
			menu_add_child($league->fullname, "$league->fullname/status",'status report', array('weight' => 1, 'link' => "league/status/$league->league_id"));
		}
	}
	if($lr_session->has_permission('league','edit', $league->league_id) ) {
		menu_add_child($league->fullname, "$league->fullname/fields",'field distribution', array('weight' => 1, 'link' => "league/fields/$league->league_id"));
		menu_add_child($league->fullname, "$league->fullname/slots",'available fields', array('weight' => 1, 'link' => "league/slots/$league->league_id"));
	}
}

function person_add_to_menu( &$person ) 
{
	global $lr_session;
	if( ! ($lr_session->attr_get('user_id') == $person->user_id) ) {
		// These links already exist in the 'My Account' section if we're
		// looking at ourself
		menu_add_child('person', $person->fullname, $person->fullname, array('weight' => -10, 'link' => "person/view/$person->user_id"));
		if($lr_session->has_permission('person', 'edit', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/edit",'edit account', array('weight' => -10, 'link' => "person/edit/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'delete', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/delete",'delete account', array('weight' => -10, 'link' => "person/delete/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'password_change', $person->user_id) ) {
			menu_add_child($person->fullname, "$person->fullname/changepassword",'change password', array('weight' => -10, 'link' => "person/changepassword/$person->user_id"));
		}

		if($lr_session->has_permission('person', 'password_reset') ) {
			menu_add_child($person->fullname, "$person->fullname/forgotpassword", 'send new password', array( 'link' => "person/forgotpassword?edit[username]=$person->username&amp;edit[step]=perform"));
		}
	}
}

/**
 * Add view/edit links to the menu for the given registration
 */
function registration_add_to_menu( &$registration )
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		$order_num = sprintf(variable_get('order_id_format', '%d'), $registration->order_id);

		menu_add_child('event', $order_num, $order_num, array('weight' => -10, 'link' => "registration/view/$registration->order_id"));

		if($lr_session->has_permission('registration','edit', $registration->order_id) ) {
			menu_add_child($order_num, "$order_num/edit",'edit registration', array('weight' => 1, 'link' => "registration/edit/$registration->order_id"));
		}
		if($registration->payment == 'Unpaid' || $registration->payment == 'Pending') {
			if ($lr_session->has_permission('registration','unregister', null, $registration) ) {
				menu_add_child($order_num, "$order_num/unregister",'unregister', array('weight' => 1, 'link' => "registration/unregister/$registration->order_id"));
			}
		}
	}
}

/**
 * Add view/edit/delete links to the menu for the given team
 */
function team_add_to_menu( &$team ) 
{
	global $lr_session;

	// Now that team names aren't unique, we need a unique id for the menu
	$menu_name = $team->name . $team->team_id;

	menu_add_child('team', $menu_name, $team->name, array('weight' => -10, 'link' => "team/view/$team->team_id"));
	menu_add_child($menu_name, "$menu_name/standings",'standings', array('weight' => -1, 'link' => "league/standings/$team->league_id/$team->team_id"));
	menu_add_child($menu_name, "$menu_name/schedule",'schedule', array('weight' => -1, 'link' => "team/schedule/$team->team_id"));

	if( $lr_session->user && !array_key_exists( $team->team_id, $lr_session->user->teams ) ) {
		if($team->status != 'closed') {
			menu_add_child($menu_name, "$menu_name/join",'join team', array('weight' => 0, 'link' => "team/roster/$team->team_id/" . $lr_session->attr_get('user_id')));
		}
	} 

	menu_add_child($menu_name, "$menu_name/spirit", "spirit", array('weight' => 1, 'link' => "team/spirit/$team->team_id"));

	if( $lr_session->has_permission('team','edit',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/edit",'edit team', array('weight' => 1, 'link' => "team/edit/$team->team_id"));
		menu_add_child($menu_name, "$menu_name/add",'add player', array('weight' => 0, 'link' => "team/roster/$team->team_id"));
	}

	if( $lr_session->has_permission('team','email',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/emails",'player emails', array('weight' => 2, 'link' => "team/emails/$team->team_id"));
	}

	if( $lr_session->has_permission('team','delete',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/delete",'delete team', array('weight' => 1, 'link' => "team/delete/$team->team_id"));
	}

	if( $lr_session->has_permission('team','move',$team->team_id)) {
		menu_add_child($menu_name, "$menu_name/move",'move team', array('weight' => 1, 'link' => "team/move/$team->team_id"));
	}
}

/**
 * Add view/edit/delete links to the menu for the given event
 */
function event_add_to_menu( &$event ) 
{
	global $lr_session;

	if( variable_get('registration', 0) ) {
		menu_add_child('event', $event->name, $event->name, array('weight' => -10, 'link' => "event/view/$event->registration_id"));

		if($lr_session->has_permission('event','edit', $event->registration_id) ) {
			menu_add_child($event->name, "$event->name/edit",'edit event', array('weight' => 1, 'link' => "event/edit/$event->registration_id"));
			menu_add_child($event->name, "$event->name/survey",'edit survey', array('weight' => 1, 'link' => "event/survey/$event->registration_id"));
		}
		if($lr_session->has_permission('event','delete', $event->registration_id) ) {
			menu_add_child($event->name, "$event->name/delete",'delete event', array('weight' => 1, 'link' => "event/delete/$event->registration_id"));
		}

		if( $lr_session->has_permission('event','create') ) {
			menu_add_child($event->name, "$event->name/copy",'copy event', array('weight' => 1, 'link' => "event/copy/$event->registration_id"));
		}

		if( $lr_session->is_admin() ) {
			menu_add_child($event->name, "$event->name/registrations", 'registration summary', array('weight' => 2, 'link' => "statistics/registration/summary/$event->registration_id"));
		}
	}
}

/**
 * Add view/edit/delete links to the menu for the given field
 */
function field_add_to_menu( &$field ) 
{
	global $lr_session;

	menu_add_child('field', $field->fullname, $field->fullname, array('weight' => -10, 'link' => "field/view/$field->fid"));

	if($lr_session->has_permission('field','view bookings', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname bookings", "view bookings", array('link' => "field/bookings/$field->fid"));
	}

	if($lr_session->has_permission('field','edit', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname/edit",'edit field', array('weight' => 1, 'link' => "field/edit/$field->fid"));
		menu_add_child($field->fullname, "$field->fullname/layout",'edit layout', array('weight' => 1, 'link' => "gmaps/edit/$field->fid"));
	}

	if( $lr_session->has_permission('field','view reports') ) {
		menu_add_child($field->fullname,"$field->fullname reports" ,'view reports', array('weight' => 2, 'link' => "field/reports/$field->fid") );
	}

	if($lr_session->has_permission('slot','create', $field->fid) ) {
		menu_add_child($field->fullname, "$field->fullname gameslot", 'new gameslot', array('link' => "slot/create/$field->fid"));
	}
}

/**
 * Add game information to menu
 */
function game_add_to_menu( &$league, &$game )
{
	global $lr_session;
	menu_add_child("$league->fullname/schedule", "$league->fullname/schedule/$game->game_id", "Game $game->game_id", array('link' => "game/view/$game->game_id"));

	if( $lr_session->has_permission('league','edit game', $game->league_id) ) {
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/edit", "edit game", array('link' => "game/edit/$game->game_id"));
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/delete", "delete game", array('link' => "game/delete/$game->game_id"));
		menu_add_child("$league->fullname/schedule/$game->game_id", "$league->fullname/schedule/$game->game_id/removeresults", "remove results", array('link' => "game/removeresults/$game->game_id"));
	}
}

?>
