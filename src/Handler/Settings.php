<?php

/*
 * Handlers for leaguerunner settings
 */
function settings_dispatch() 
{
	$mod = arg(1);

	if (module_hook($mod,'settings')) {
		return new SettingsHandler;
	}
	return null;
}

function settings_menu()
{
	global $session;
	if($session->is_admin()) {
		menu_add_child('_root','settings','Settings');
	}
}

function settings_save( $newsettings = array() )
{
	foreach ( $newsettings as $name => $value ) {
		variable_set($name, $value);
	}
}

function settings_form( &$form )
{
	$form .= form_hidden('op', 'save');
	$form .= form_submit("Save configuration");
	return form($form);
}

class SettingsHandler extends Handler
{
	function initialize ()
	{
		$this->title = 'Settings';
		$this->_required_perms = array(
			'require_valid_session',
			'admin_sufficient',
			'deny'
		);
		return true;
	}

	function process ()
	{
		$mod = arg(1);
		$op = $_POST['op'];
		
		switch($op) {
			case 'save':
				settings_save($_POST['edit']);
				return module_invoke($mod, 'settings', "Settings saved");
			default:
				return module_invoke($mod, 'settings', "Make your changes below");
		}
	}
}

?>
