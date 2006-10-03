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
?>
