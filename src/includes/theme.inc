<?php
/*
 * Look and feel for Leaguerunner 
 */

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
