<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "", $breadcrumbs = array())
{
	global $session, $APP_NAME;
	
	if ($title) {
		$title = $title . " - " . $APP_NAME;
	} else {
		$title = $APP_NAME;
	}
// <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
?>
<!-- start header -->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php print $title ?></title>
    <link rel='stylesheet' href="style.css" type="text/css">
  </head>
  <body topmargin="0" leftmargin="0" marginheight="0" marginwidth="0" bgcolor="#FFFFFF">
  <table border='0' cellpadding='3' cellspacing='0' width='760' bgcolor='white'><?php
  	print tr(
		td("<h1>$APP_NAME</h1>", array("class" => "toprow", "colspan" => 2))
	);

	print theme_navbar($breadcrumbs);
	print "<tr><td colspan='2' class='main'>";
	print "\n<!-- end header -->\n";
}

function theme_navbar($breadcrumbs = array()) 
{
	global $session;
	print "\n<!-- start navbar -->\n";
	print "<tr class='lr_navbar'>" ;
	if($session->is_valid()) {
		$crumbs = array();

		foreach($breadcrumbs as $thiscrumb) {
			if($thiscrumb['target']) {
				$crumbs[] = l($thiscrumb['name'], $thiscrumb['target']);
			} else {
				$crumbs[] = $thiscrumb['name'];
			}
		}
		print td( "&nbsp;" . theme_links($crumbs, "&nbsp;&raquo;&nbsp;"), array("class" => "lr_navbar"));
		
	} else {
		print td("&nbsp;", array("class" => "lr_navbar"));
	}

?><td class='lr_navbar' align='right' valign='top'><?php
	if($session->is_valid()) {
		print l("main menu", "op=menu", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
		print l("log out", "op=logout", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
	} else {
		print l("log in", "op=login", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
	}
	print l("help", "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/howto.html", array( 'class' => 'topbarlink' ));
	print "</td></tr>";
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
<tr><td><a href="howto.html" target="_new"><b><font color="red">Help</font></b></a></td>
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
