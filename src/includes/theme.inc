<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "", $breadcrumbs = array())
{
	global $session, $BASE_URL;

	$app_name = variable_get('app_name','Leaguerunner');
	
	if ($title) {
		$title = "$title - $app_name";
	} else {
		$title = $app_name;
	}
?>
<!-- start header -->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php print $title ?></title>
    <base href="<?php print $BASE_URL ?>/" />
	<style type="text/css">
      @import url(style.css);
	</style>
  </head>
  <body bgcolor="#FFFFFF">
  <table border='0' cellpadding='1' cellspacing='0' width="99%" bgcolor='white'><?php
  	print "<tr><td colspan='2' class='toprow'><h1>$app_name</h1></td></tr>\n";

	print theme_navbar($breadcrumbs);
	print "<tr><td colspan='2' class='main'>";
	print "\n<!-- end header -->\n";
	
	/* DMO: TODO: fix this evil hack 
	 * Here we nest another table to not screw up the column widths, etc,
	 * above.  Instead, all should be merged in one table, the breadcrumbs
	 * fixed, and the top menubar removed.
	 */
	print "<table width='100%'><tr>";
	if ($session->is_valid()) {
		print "<td width='180'><div class='menu'>" . menu_render('_root') . "</div></td>";
	} else {
		print "<td></td>";
	}
	print "<td class='main'>";
	/* DMO: TODO: end evil hack */
}

function theme_navbar($breadcrumbs = array()) 
{
	global $session;

	print "\n<!-- start navbar -->\n";
	print "<tr><td colspan='2'>" ;
	print "<div class='lr_topbar'><table>";
	$rightLink = l("help", "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/data/howto.html", array( 'class' => 'topbarlink', 'target' => '_new' ));

	$trail = menu_get_trail();
	$section = $trail[1]['title'];
	if($session->is_valid()) {
		$crumbs = array(
			"<b>" . $session->attr_get("fullname") . "</b>",
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
	
	print "</table></div></td></tr>\n";
	print "\n<!-- end navbar -->\n";
}

function theme_error( $message ) 
{
	return "<div class=\"error\">$message</div>";
}

function theme_links($links, $delimiter = " | ")
{
	return implode($delimiter, $links);
}

function theme_footer()
{
	/* DMO: TODO: fix this evil hack 
	 * Here we nest another table to not screw up the column widths, etc,
	 * above.  Instead, all should be merged in one table, the breadcrumbs
	 * fixed, and the top menubar removed.
	 */
	print "</td></tr></table>";
	/* DMO: TODO: end evil hack */
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
