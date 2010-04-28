<?php

function home_dispatch() 
{
	return new MainMenu;
}

function home_menu() 
{
	menu_add_child('_root','home','Home', array('link' => 'home', 'weight' => '-20'));
}

class MainMenu extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return ( $lr_session->is_valid() );
	}

	function process ()
	{
		global $lr_session;
		$this->setLocation(array( $lr_session->attr_get('fullname') => 0 ));
		return "<div class='splash'>" . join("",module_invoke_all('splash')) . "</div>";
	}
}

function home_splash ()
{
	# Init curl
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, variable_get('rss_feed_url', 'http://www.ocua.ca/taxonomy/term/140/all/feed') );
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);

	# Fetch RSS data
	$xml = curl_exec($curl);
	curl_close($curl);

	$xmlObj = simplexml_load_string( $xml );

	$count = 0;
	$limit = variable_get('rss_feed_items', 2);
	$rows = array();

	foreach ( $xmlObj->channel[0]->item as $item )
	{
		$rows[] = array(
			l($item->title, $item->link)
		);
		if( ++$count >= $limit ) {
			break;
		}
	}
	if( $count > 0 ) {
		return table( array( array('data' => variable_get('rss_feed_title', 'OCUA Volunteer Opportunities'), 'colspan' => 2),), $rows);
	} else {
		return '';
	}
}

?>
