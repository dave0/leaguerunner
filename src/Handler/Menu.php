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
	function initialize ()
	{
		$this->_required_perms = array(
			'require_valid_session',
			'allow'
		);
		return true;
	}

	function process ()
	{
		global $session;
		$this->setLocation(array( $session->attr_get('fullname') => 0 ));
		return join("",module_invoke_all('splash'));
	}
}
?>
