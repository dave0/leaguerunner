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
	global $lr_session;
	if($lr_session->is_admin()) {
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

	$group .= form_textfield("Organization name", 'edit[app_org_name]', variable_get('app_org_name', 'Ottawa Carleton Ultimate Association'), 60, 120, "Your organization's full name.");

	$group .= form_textfield("Organization short name", 'edit[app_org_short_name]', variable_get('app_org_short_name', ''), 60, 120, "Your organization's abbreviated name or acronym.");

	$group .= form_textfield("Administrator name", 'edit[app_admin_name]', variable_get('app_admin_name', 'Leaguerunner Administrator'), 60, 120, "The name (or descriptive role) of the system administrator. Mail from Leaguerunner will come from this name.");
	
	$group .= form_textfield("Administrator e-mail address", 'edit[app_admin_email]', variable_get('app_admin_email', 'webmaster@localhost'), 60, 120, "The e-mail address of the system administrator.  Mail from Leaguerunner will come from this address.");
	
	$group .= form_textfield("Base location of static league files (filesystem)", 'edit[league_file_base]', variable_get('league_file_base', '/opt/websites/www.ocua.ca/static-content/leagues'), 60, 120, "The filesystem location where files for permits, exported standings, etc, shall live.");

	$group .= form_textfield("Base location of static league files (URL)", 'edit[league_url_base]', variable_get('league_url_base', 'http://www.ocua.ca/leagues'), 60, 120, "The web-accessible URL where files for permits, exported standings, etc, shall live.");
	
	$group .= form_textfield("Location of privacy policy (URL)", 'edit[privacy_policy]', variable_get('privacy_policy', 'http://www.ocua.ca/node/17'), 60, 120, "The web-accessible URL where the organization's privacy policy is located. Leave blank if you don't have an online privacy policy.");

	$group .= form_radios("Dog Questions", "edit[dog_questions]", variable_get("dog_questions", 1), array("Disabled", "Enabled"), "Enable or disable questions and options about dogs");

	$group .= form_radios("Wards", "edit[wards]", variable_get("wards", 1), array("Disabled", "Enabled"), "Enable or disable use of city wards");

	$group .= form_textfield("Google Maps API Key", 'edit[gmaps_key]', variable_get('gmaps_key', ''), 60, 120, "An API key for the Google Maps API - see http://www.google.com/apis/maps/signup.html.  Required for rendering custom Google Maps");

	$output = form_group("Site configuration", $group);

	$group = form_radios("Clean URLs", "edit[clean_url]", variable_get("clean_url", 1), array("Disabled", "Enabled"), "Enable or disable clean URLs.  If enabled, you'll need <code>ModRewrite</code> support.  See also the <code>.htaccess</code> file in Leaguerunner's top-level directory.");
	
	$group = form_radios("Lock sessions to initiating IP address", "edit[session_requires_ip]", variable_get("session_requires_ip", 1), array("Disabled", "Enabled"), "If enabled, session cookies are only accepted if they come from the same IP as the initial login.  This adds a bit of security against cookie theft, but causes problems for users behind a firewall that routes HTTP requests out through multiple IP addresses.  Recommended setting is to enable unless you notice problems.");
	$output .= form_group("General configuration", $group);

	$group = form_select("Current Season", "edit[current_season]", variable_get("current_season","Summer"), getOptionsFromEnum('league','season'), "Season of play currently in effect");

	$output .= form_group("Season Information", $group);
	
	return settings_form($output);
}

class SettingsHandler extends Handler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = 'Settings';
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
