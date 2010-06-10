<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "", $extra = null)
{
	global $lr_session, $CONFIG;

	$app_name = variable_get('app_name','Leaguerunner');

	if ($title) {
		$title = "$title - $app_name";
	} else {
		$title = $app_name;
	}

	print <<<END_OF_HEADER
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>$title</title>
	<base href="http://{$_SERVER["HTTP_HOST"]}{$CONFIG['paths']['base_url']}/" />
	<link rel="stylesheet" type="text/css" href="/themes/ocua_2004/style.css" />
	<link rel="stylesheet" type="text/css" href="style.css" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<meta http-equiv="content-type" content="text/html; charset=utf-8"/>
$extra
</head>

END_OF_HEADER;
}

function theme_navbar($breadcrumbs = array()) 
{
	global $lr_session, $CONFIG;

	$rightLink = l("help", "{$CONFIG['paths']['base_url']}/data/howto.html", array( 'class' => 'topbarlink' ));

	$trail = menu_get_trail();
	$section = $trail[1]['title'];
	if($lr_session->is_valid()) {
		$crumbs = array(
			"<b>" . $lr_session->attr_get("fullname") . "</b>",
			$section
		);

		$crumbs = array_merge($crumbs, $breadcrumbs);
		$leftLink = theme_links($crumbs, "&nbsp;&raquo;&nbsp;");
	} else {
		$leftLink = "&nbsp;";
		$rightLink = theme_links( array( l('log in','login'), $rightLink));
	}

	return "<td class='nav'>$leftLink</td>\n<td class='nav' align='right'>$rightLink</td>";

}

function theme_error( $message ) 
{
	return "<span class=\"error\">$message</span>";
}

function theme_links($links, $delimiter = " | ")
{
	return implode($delimiter, $links);
}

?>
