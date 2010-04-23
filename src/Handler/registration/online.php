<?php
// Online payment response
class registration_online extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process ()
	{
		global $CONFIG;

		$base_url = $CONFIG['paths']['base_url'];
		$org = variable_get('app_org_name','league');
		print <<<HTML_HEADER
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>$org - Online Transaction Result</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="stylesheet" type="text/css" href="http://{$_SERVER["SERVER_NAME"]}$base_url/style.css">
<script type="text/javascript">
<!--
function close_and_redirect(url)
{
	window.opener.location.href = url;
	window.close();
}
-->
</script>
</head>
<body>
HTML_HEADER;

		handlePaymentResponse();

		print para("Click <a href=\"/\" onClick=\"close_and_redirect('http://{$_SERVER["SERVER_NAME"]}$base_url/event/list')\">here</a> to close this window.");

		// Returning would cause the Leaguerunner menus to be added
		exit;
	}
}

?>
