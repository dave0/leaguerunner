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
		menu_add_child('settings','settings/global','global settings', array('link' => 'settings/global'));
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

function global_settings()
{
	$group = form_textfield("Name of application", 'edit[app_name]', variable_get('app_name', 'Leaguerunner'), 60, 120, "The name this application will be known as to your users.");

	$group .= form_textfield("Administrator name", 'edit[app_admin_name]', variable_get('app_admin_name', 'Leaguerunner Administrator'), 60, 120, "The name (or descriptive role) of the system administrator. Mail from Leaguerunner will come from this name.");
	
	$group .= form_textfield("Administrator e-mail address", 'edit[app_admin_email]', variable_get('app_admin_email', 'webmaster@localhost'), 60, 120, "The e-mail address of the system administrator.  Mail from Leaguerunner will come from this address.");
	
	$output = form_group("Site configuration", $group);

	$group = form_radios("Clean URLs", "edit[clean_url]", variable_get("clean_url", 1), array("Disabled", "Enabled"), "Enable or disable clean URLs.  If enabled, you'll need <code>ModRewrite</code> support.  See also the <code>.htaccess</code> file in Leaguerunner's top-level directory.");
	$output .= form_group("General configuration", $group);

	
	$group = form_select("Current Season", "edit[current_season]", variable_get("current_season","Summer"), getOptionsFromEnum('league','season'), "Season of play currently in effect");
	$output .= form_group("Season Information", $group);
	
	return settings_form($output);
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
				$output = "Settings saved";
				$output .= module_invoke($mod, 'settings', "Settings saved");
				return $output;
			default:
				$output = "Make your changes below";
				$output .= module_invoke($mod, 'settings');
				return $output;
		}
	}
}

?>
