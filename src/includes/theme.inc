<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "", $section = "", $breadcrumbs = array())
{
	global $session, $APP_NAME;
	
	if ($title) {
		$title = $title . " - " . $APP_NAME;
	} else {
		$title = $APP_NAME;
	}
?>
<!-- start header -->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php print $title ?></title>
    <link rel='stylesheet' href="style.css" type="text/css">
  </head>
  <body topmargin="0" leftmargin="0" marginheight="0" marginwidth="0" bgcolor="#FFFFFF">
  <table border='0' cellpadding='0' cellspacing='0' width='760' bgcolor='white'><?php
  	print tr(
		td("<h1>$APP_NAME</h1>", array("class" => "toprow", "colspan" => 2))
	);

	print theme_navbar($section, $breadcrumbs);
	print "<tr><td colspan='2' class='main'>";
	print "\n<!-- end header -->\n";
}

function theme_navbar($section, $breadcrumbs = array()) 
{
	global $session;

	/* TODO: Put these somewhere else (global var? config var?) */
	$sectionTabs = array(
		'myaccount' => 'My Account',
		'person' => 'People',
		'team' => 'Teams',
		'league' => 'Leagues',
		'field' => 'Fields',
// TODO: 'game' handlers should encompass the following:
//   - list all games for a given date (anyone)
//   - view a game (anyone, coord/admin has extra privs)
//   - edit a game (coord/admin)
//   - submit a game score (captain/coord/admin)
//   - submit an incident report (anyone)
//		'game' => 'Games',
		'admin' => 'Admin',
		'logout' => 'Log Out'
	);
	$numTabs = count($sectionTabs);

	$rightLink = l("help", "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/data/howto.html", array( 'class' => 'topbarlink', 'target' => '_new' ));

	print "\n<!-- start navbar -->\n";
	print "<tr><td colspan='2'>" ;
	print "<div class='lr_topbar'><table>";
	if($session->is_valid()) {
		$crumbs = array(
			"<b>" 
			. $session->attr_get("firstname") 
			. " "
			. $session->attr_get("lastname")
			. "</b>",
		);
		
		$crumbs[] = l($sectionTabs[$section], "op=$section");

		$crumbs = array_merge($crumbs, $breadcrumbs);
		
		print tr(
			td(theme_links($crumbs, "&nbsp;&raquo;&nbsp;"), array('class' => 'nav', 'colspan' => ceil( 2 * ($numTabs / 3))))
			. td($rightLink, array('class' => 'nav', 'align'=>'right', 'colspan' => floor($numTabs / 2)))
		);

		$tdWidth = floor( 100 / $numTabs);
		print "<tr>";
		while(list($key,$name) = each($sectionTabs)) {
			if($key == $section) {
				$class = 'on';
			} else {
				$class = 'off';
			}
			print td( l($name,"op=$key"), array('class' => $class, 'width' => $tdWidth . '%'));
		}
		print "</tr>";
		
	} else {
		$rightLink = theme_links( array(
			l('log in','op=login'),
			$rightLink));
		print tr(
			td("&nbsp;", array('class' => 'nav'))
			. td( $rightLink, array('class' => 'nav', 'align' => 'right'))
		);
	}
	
	print "</table></div></td></tr>\n";
	print "\n<!-- end navbar -->\n";
}

function theme_links($links, $delimiter = " | ")
{
	return implode($delimiter, $links);
}

function theme_footer()
{
?>
<!-- start footer -->
<p />
<hr noshade />
<table border="0" width="100%">
<tr><td><a href="data/howto.html" target="_new"><b><font color="red">Help</font></b></a></td>
<td align="right">
<i>Powered by <a href="http://www.dmo.ca/projects/leaguerunner/">Leaguerunner</a></i>
</td></tr></table>
    </td>  
  </tr>
</table>
</body>
</html>
<!-- end footer -->
<?php
}

?>
