<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "", $breadcrumbs = array())
{
	global $lr_session, $CONFIG;

	$app_name = variable_get('app_name','Leaguerunner');

	if ($title) {
		$title = "$title - $app_name";
	} else {
		$title = $app_name;
	}
?>
<!-- start header -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php print $title ?></title>
    <base href="<?php print 'http://' . $_SERVER["HTTP_HOST"] . $CONFIG['paths']['file_url'] ?>/" />
    <link rel="stylesheet" type="text/css" href="/themes/ocua_2004/style.css" />
    <link rel="stylesheet" type="text/css" href="style.css" />
    <link rel="shortcut icon" href="/favicon.ico" />
  </head>
  <body>
<table id="primary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
<tr valign="bottom">
    <td rowspan="2" width="401" valign="bottom"><a href="/"><img src="/themes/ocua_2004/ocua-logo-top-half.png" width="399" height="37" border="0" alt="Ottawa-Carleton Ultimate Association"></a></td>
	<td></td>
</tr>
<tr>
   <td class="primary links" align="right" valign="bottom">
   <?php
	if($lr_session->is_valid()) {
		print "You are logged in as <b>" . $lr_session->attr_get("fullname") . "</b> | ";
		print l('Log Out', 'logout');
	} else {
		print l('Log In', 'login');
	}
  ?>
   </td>
</tr>
</table>
<table id="secondary-menu" border="0" cellpadding="0" cellspacing="0" width="100%">
	<tr height="22">
		<td background="/themes/ocua_2004/menu-background.png" align="left" height="22" width="130" valign="top"><a href="/"><img src="/themes/ocua_2004/ocua-logo-bottom-half.png" width="130" height="22" border="0" alt="["></a></td>
	<td class="secondary-links" background="/themes/ocua_2004/menu-background.png" align='left'>
	    <a href="/node/14">About OCUA</a> | <a href="/node/1">About Ultimate</a> | <a href="/leagues">Leagues</a> | <a href="/node/40">Partners</a> | <a href="/article/">Articles</a> | <a href="/event/">Calendar</a> | <a href="/weblink/">Links</a> | <a href="/forum/">Forums</a> | <a href="/node/22">Contact</a> | <a href="/search">Search</a>
 	</td>
	<td class="secondary-links" background="/themes/ocua_2004/menu-background.png" width="1" align="right" height="22"><img src="/themes/ocua_2004/rt_edge.png" width="1" height="22" border="0" alt="]"></td>
	</tr>
</table>
<?php
	print theme_navbar($breadcrumbs);
	print "\n<!-- end header -->\n";
	print "<table width='100%'><tr>";
	if ($lr_session->is_valid()) {
		print "<td id='sidebar-left' width='160'><div class='menu'>" . menu_render('_root') . "</div></td>";
	} else {
		print "<td></td>";
	}
	print "<td valign='top'><div id='main'>";
	/* DMO: TODO: end evil hack */
}

function theme_navbar($breadcrumbs = array()) 
{
	global $lr_session, $CONFIG;

	print "\n<!-- start navbar -->\n";
    print "<div class='lr_topbar'><table border='0' cellpadding='0' cellspacing='0' width='99%' bgcolor='white'>\n";
	$rightLink = l("help", "{$CONFIG['paths']['file_url']}/data/howto.html", array( 'class' => 'topbarlink' ));

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
	print "<tr>";
	print "<td class='nav'>$leftLink</td>";
	print "<td class='nav' align='right'>$rightLink</td>";
	print "</tr>";
	print "</table>";
	print "</div>\n";
	print "\n<!-- end navbar -->\n";
}

function theme_error( $message ) 
{
	return "<span class=\"error\">$message</span>";
}

function theme_links($links, $delimiter = " | ")
{
	return implode($delimiter, $links);
}

function theme_footer()
{
?>
</div>
</td></tr></table>
<!-- start footer -->
<p />
<hr noshade="noshade" />
<i>Powered by <a href="http://code.google.com/p/leaguerunner/">Leaguerunner</a>, version 2.6</i>
</body>
</html>
<!-- end footer -->
<?php
}

?>
