<?php
/*
 * $Id$
 * Look and feel for Leaguerunner 
 */

function theme_header($title = "")
{
	global $session, $APP_NAME;
	
	if ($title) {
		$title = $title . " - " . $APP_NAME;
	} else {
		$title = $APP_NAME;
	}
// <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php print $title ?></title>
    <link rel='stylesheet' href="style.css" type="text/css">
  </head>
  <body topmargin="0" leftmargin="0" marginheight="0" marginwidth="0" bgcolor="#FFFFFF">
  <table border='0' cellpadding='3' cellspacing='0' width='760' bgcolor='white'><?php

?><tr class='toprow'><td colspan='2'><h1><?php print $APP_NAME ?></h1></td></tr><tr class='toprow'><td><?php
	if($session->is_valid()) {
		print "<b>" 
			. $session->attr_get("firstname") 
			. " "
			. $session->attr_get("lastname")
			. "</b>";
	}
	/* TODO: put breadcrumbs here */
?></td><td align='right' valign='top'><font color='white'><?php
	if($session->is_valid()) {
		print l("main menu", "op=menu", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
		print l("log out", "op=logout", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
	} else {
		print l("log in", "op=login", array( 'class' => 'topbarlink' ));
		print "&nbsp;|&nbsp;";
	}
	print l("help", "op=help", array( 'class' => 'topbarlink' ));
?></font></td></tr><tr><td colspan='2' class='main'>

<?php
}

function theme_links($links, $delimiter = " | ")
{
	return implode($delimiter, $links);
}

function theme_footer()
{
?>
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
<?php
}

?>
