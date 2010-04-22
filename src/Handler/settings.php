<?php

/*
 * Handlers for leaguerunner settings
 */
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
	$group = form_textfield('Organization name', 'edit[app_org_name]', variable_get('app_org_name', ''), 60, 120, 'Your organization\'s full name.');

	$group .= form_textfield('Organization short name', 'edit[app_org_short_name]', variable_get('app_org_short_name', ''), 60, 120, 'Your organization\'s abbreviated name or acronym.');

	$group .= form_textfield('Address', 'edit[app_org_address]', variable_get('app_org_address', ''), 60, 120, 'Your organization\'s street address.');

	$group .= form_textfield('Unit', 'edit[app_org_address2]', variable_get('app_org_address2', ''), 60, 120, 'Your organization\'s unit number, if any.');

	$group .= form_textfield('City', 'edit[app_org_city]', variable_get('app_org_city', ''), 60, 120, 'Your organization\'s city.');

	$group .= form_select('Province/State', 'edit[app_org_province]', variable_get('app_org_province', ''), getProvinceNames(), 'Your organization\'s province or state.');

	$group .= form_textfield('Postal code', 'edit[app_org_postal]', variable_get('app_org_postal', ''), 60, 120, 'Your organization\'s postal code.');

	$group .= form_textfield('Phone', 'edit[app_org_phone]', variable_get('app_org_phone', ''), 60, 120, 'Your organization\'s phone number.');

	$group .= form_textfield('Administrator name', 'edit[app_admin_name]', variable_get('app_admin_name', 'Leaguerunner Administrator'), 60, 120, 'The name (or descriptive role) of the system administrator. Mail from Leaguerunner will come from this name.');

	$group .= form_textfield('Administrator e-mail address', 'edit[app_admin_email]', variable_get('app_admin_email', $_SERVER['SERVER_ADMIN']), 60, 120, 'The e-mail address of the system administrator.  Mail from Leaguerunner will come from this address.');

	$output = form_group('Organization Details', $group);

	$group = form_textfield('Latitude', 'edit[location_latitude]', variable_get('location_latitude', ''), 10, 10, 'Latitude in decimal degrees for game location (center of city).  Used for calculating sunset times.');

	$group .= form_textfield('Longitude', 'edit[location_longitude]', variable_get('location_longitude', ''), 10, 10, 'Longitude in decimal degrees for game location (center of city).  Used for calculating sunset times.');

	$output .= form_group('Location Details', $group);

	$group = form_textfield('Name of application', 'edit[app_name]', variable_get('app_name', 'Leaguerunner'), 60, 120, 'The name this application will be known as to your users.');

	$group .= form_textfield('Items per page', 'edit[items_per_page]', variable_get('items_per_page', 25), 10, 10, 'The number of items that will be shown per page on long reports, 0 for no limit (not recommended).');

	$group .= form_textfield('Base location of static league files (filesystem)', 'edit[league_file_base]', variable_get('league_file_base', '/opt/websites/www.ocua.ca/static-content/leagues'), 60, 120, 'The filesystem location where files for permits, exported standings, etc, shall live.');

	$group .= form_textfield('Base location of static league files (URL)', 'edit[league_url_base]', variable_get('league_url_base', 'http://www.ocua.ca/leagues'), 60, 120, 'The web-accessible URL where files for permits, exported standings, etc, shall live.');

	$group .= form_textfield('Location of privacy policy (URL)', 'edit[privacy_policy]', variable_get('privacy_policy', "{$_SERVER['SERVER_NAME']}/privacy"), 60, 120, 'The web-accessible URL where the organization\'s privacy policy is located. Leave blank if you don\'t have an online privacy policy.');

	$group .= form_textfield('Location of password reset (URL)', 'edit[password_reset]', variable_get('password_reset', url('person/forgotpassword')), 60, 120, 'The web-accessible URL where the password reset page is located.');

	$group .= form_textfield('Google Maps API Key', 'edit[gmaps_key]', variable_get('gmaps_key', ''), 60, 120, 'An API key for the <a href="http://www.google.com/apis/maps/signup.html">Google Maps API</a>. Required for rendering custom Google Maps');

	$output .= form_group('Site configuration', $group);

	$group = form_select('Current Season', 'edit[current_season]', variable_get('current_season','Summer'), getOptionsFromEnum('league','season'), 'Season of play currently in effect');

	$output .= form_group('Season Information', $group);

	$group = form_textfield('Spirit penalty for not entering score', 'edit[missing_score_spirit_penalty]', variable_get('missing_score_spirit_penalty', 3), 10, 10);

	$group .= form_textfield('Winning score to record for defaults', 'edit[default_winning_score]', variable_get('default_winning_score', 6), 10, 10);

	$group .= form_textfield('Losing score to record for defaults', 'edit[default_losing_score]', variable_get('default_losing_score', 0), 10, 10);

	$group .= form_radios('Transfer ratings points for defaults', 'edit[default_transfer_ratings]', variable_get('default_transfer_ratings', 0), array('Disabled', 'Enabled'));

	$group .= form_select('Spirit Questions', 'edit[spirit_questions]', variable_get('spirit_questions','team_spirit'), array(
		'team_spirit' => 'team_spirit',
		'ocua_team_spirit' => 'ocua_team_spirit',
	), 'Type of spirit questions to use.');

	$output .= form_group('Game Finalization', $group);

	return settings_form($output);
}

function feature_settings()
{
	$group = form_radios('Handle registration', 'edit[registration]', variable_get('registration', 0), array('Disabled', 'Enabled'), 'Enable or disable processing of registrations');

	$group .= form_radios('Dog questions', 'edit[dog_questions]', variable_get('dog_questions', 1), array('Disabled', 'Enabled'), 'Enable or disable questions and options about dogs');

	$group .= form_radios('Clean URLs', 'edit[clean_url]', variable_get('clean_url', 0), array('Disabled', 'Enabled'), 'Enable or disable clean URLs.  If enabled, you\'ll need <code>ModRewrite</code> support.  See also the <code>.htaccess</code> file in Leaguerunner\'s top-level directory.');

	$group .= form_radios('Use Zikula authentication', 'edit[postnuke]', variable_get('postnuke', 0), array('Disabled', 'Enabled'), 'If enabled, Leaguerunner will use your Zikula user database for user names, passwords and email addresses. Everything to do with passwords and logins will be hidden.');

	$group .= form_radios('Narrow display', 'edit[narrow_display]', variable_get('narrow_display', 0), array('Disabled', 'Enabled'), 'If enabled, various displays will be adjusted for horizontal compactness. This is most useful when using Zikula, as its left menu block eats up valuable real estate.');

	$group .= form_radios('Lock sessions to initiating IP address', 'edit[session_requires_ip]', variable_get('session_requires_ip', 1), array('Disabled', 'Enabled'), 'If enabled, session cookies are only accepted if they come from the same IP as the initial login.  This adds a bit of security against cookie theft, but causes problems for users behind a firewall that routes HTTP requests out through multiple IP addresses.  Recommended setting is to enable unless you notice problems. This setting is ignored if Zikula authentication is enabled.');

	$group .= form_radios('Force roster request responses', 'edit[force_roster_request]', variable_get('force_roster_request', 0), array('Disabled', 'Enabled'), 'Should players be forced to respond to roster requests immediately?');

	$group .= form_radios('Generate roster request emails', 'edit[generate_roster_email]', variable_get('generate_roster_email', 0), array('Disabled', 'Enabled'), 'Should emails be sent to players invited to join rosters, and captains who have players request to join their teams?');

	$output = form_group('Feature configuration', $group);

	return $output;
}

function rss_settings()
{
	$group = form_textfield('RSS feed title', 'edit[rss_feed_title]', variable_get('rss_feed_title', 'OCUA Volunteer Opportunities'), 60, 120, 'Title to use for RSS display.');
	$group .= form_textfield('RSS feed URL', 'edit[rss_feed_url]', variable_get('rss_feed_url', 'http://www.ocua.ca/taxonomy/term/140/all/feed'), 60, 120, 'The full URL from which we pull RSS items.');
	$group .= form_textfield('RSS feed item limit', 'edit[rss_feed_items]', variable_get('rss_feed_items', 2), 4, 4, 'Number of feed items to display');

	$output = form_group('RSS configuration', $group);

	return settings_form($output);
}

class settings extends Handler
{
	function __construct ( $type )
	{
		if ( ! module_hook($type,'settings') ) {
			error_exit('Operation not found');
		}
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = 'Settings';
		$op = $_POST['op'];

		switch($op) {
			case 'save':
				settings_save($_POST['edit']);
				$output = para('Settings saved');
				$output .= module_invoke($this->type, 'settings', "Settings saved");
				return $output;
			default:
				$output = para('Make your changes below');
				$output .= module_invoke($this->type, 'settings');
				return $output;
		}
	}
}

function person_settings ( )
{
	$group = form_textfield('Subject of account approval e-mail', 'edit[person_mail_approved_subject]', _person_mail_text('approved_subject'), 70, 180, 'Customize the subject of your approval e-mail, which is sent after account is approved. Available variables are: %username, %site, %url.');
 
	$group .= form_textarea('Body of account approval e-mail (player)', 'edit[person_mail_approved_body_player]', _person_mail_text('approved_body_player'), 70, 10, 'Customize the body of your approval e-mail, to be sent to players after accounts are approved. Available variables are: %fullname, %memberid, %adminname, %username, %site, %url.');

	$group .= form_textarea('Body of account approval e-mail (visitor)', 'edit[person_mail_approved_body_visitor]', _person_mail_text('approved_body_visitor'), 70, 10, 'Customize the body of your approval e-mail, to be sent to a non-player visitor after account is approved. Available variables are: %fullname, %adminname, %username, %site, %url.');

	$group .= form_textfield('Subject of membership letter e-mail', 'edit[person_mail_member_letter_subject]', _person_mail_text('member_letter_subject'), 70, 180, 'Customize the subject of your membership letter e-mail, which is sent annually after membership is paid for. Available variables are: %fullname, %firstname, %lastname, %site, %year.');
 
	$group .= form_textarea('Body of membership letter e-mail (player)', 'edit[person_mail_member_letter_body]', _person_mail_text('member_letter_body'), 70, 10, 'Customize the body of your membership letter e-mail, which is sent annually after membership is paid for. If registrations are disabled, or this field is empty, no letters will be sent. Available variables are: %fullname, %firstname, %lastname, %adminname, %site, %year.');

	$group .= form_textfield('Subject of password reset e-mail', 'edit[person_mail_password_reset_subject]', _person_mail_text('password_reset_subject'), 70, 180, 'Customize the subject of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %site.');
 
	$group .= form_textarea('Body of password reset e-mail', 'edit[person_mail_password_reset_body]', _person_mail_text('password_reset_body'), 70, 10, 'Customize the body of your password reset e-mail, which is sent when a user requests a password reset. Available variables are: %fullname, %adminname, %username, %password, %site, %url.');

	$group .= form_textfield('Subject of duplicate account deletion e-mail', 'edit[person_mail_dup_delete_subject]', _person_mail_text('dup_delete_subject'), 70, 180, 'Customize the subject of your account deletion mail, sent to a user who has created a duplicate account. Available variables are: %site.');
 
	$group .= form_textarea('Body of duplicate account deletion e-mail', 'edit[person_mail_dup_delete_body]', _person_mail_text('dup_delete_body'), 70, 10, 'Customize the body of your account deletion e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.');

	$group .= form_textfield('Subject of duplicate account merge e-mail', 'edit[person_mail_dup_merge_subject]', _person_mail_text('dup_merge_subject'), 70, 180, 'Customize the subject of your account merge mail, sent to a user who has created a duplicate account. Available variables are: %site.');
 
	$group .= form_textarea('Body of duplicate account merge e-mail', 'edit[person_mail_dup_merge_body]', _person_mail_text('dup_merge_body'), 70, 10, 'Customize the body of your account merge e-mail, sent to a user who has created a duplicate account. Available variables are: %fullname, %adminname, %existingusername, %existingemail, %site, %passwordurl.');

	$group .= form_textfield('Subject of captain request e-mail', 'edit[person_mail_captain_request_subject]', _person_mail_text('captain_request_subject'), 70, 180, 'Customize the subject of your captain request mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %league, %day, %adminname.');
 
	$group .= form_textarea('Body of captain request e-mail', 'edit[person_mail_captain_request_body]', _person_mail_text('captain_request_body'), 70, 10, 'Customize the body of your captain request e-mail, sent to a user who has been invited to join a team. Available variables are: %site, %fullname, %captain, %team, %teamurl, %league, %day, %adminname.');

	$group .= form_textfield('Subject of player request e-mail', 'edit[person_mail_player_request_subject]', _person_mail_text('player_request_subject'), 70, 180, 'Customize the subject of your player request mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %team, %league, %day, %adminname.');
 
	$group .= form_textarea('Body of player request e-mail', 'edit[person_mail_player_request_body]', _person_mail_text('player_request_body'), 70, 10, 'Customize the body of your player request e-mail, sent to captains when a player asks to join their team. Available variables are: %site, %fullname, %captains, %team, %teamurl, %league, %day, %adminname.');

	$group .= form_textfield('Subject of score reminder e-mail', 'edit[person_mail_score_reminder_subject]', _person_mail_text('score_reminder_subject'), 70, 180, 'Customize the subject of your score reminder mail, sent to captains when they have not submitted a score in a timely fashion. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');
 
	$group .= form_textarea('Body of score reminder e-mail', 'edit[person_mail_score_reminder_body]', _person_mail_text('score_reminder_body'), 70, 10, 'Customize the body of your score reminder e-mail, sent to captains when they have not submitted a score in a timely fashion. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');

	$group .= form_textfield('Subject of approval notice e-mail', 'edit[person_mail_approval_notice_subject]', _person_mail_text('approval_notice_subject'), 70, 180, 'Customize the subject of your approval notice mail, sent to captains when a game has been approved without a score submission from them. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');
 
	$group .= form_textarea('Body of approval notice e-mail', 'edit[person_mail_approval_notice_body]', _person_mail_text('approval_notice_body'), 70, 10, 'Customize the body of your approval notice e-mail, sent to captains when a game has been approved without a score submission from them. Available variables are: %site, %fullname, %team, %opponent, %league, %gamedate, %scoreurl, %adminname.');

	$output = form_group('User email settings', $group);

	return settings_form($output);
}


?>
