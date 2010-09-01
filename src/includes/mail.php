<?php
/**
 * Method for handling sending of email.
 * This is intended to be expandable, so that new implementations,
 * e.g. using Pear::Mail or some such, can be plugged in without
 * changes to any other code.
 */
// TODO: refactor to take Person objects instead of name/addr for from, and arrays of Person for to/cc
function send_mail($to_list, $from_person, $cc_list, $subject, $message)
{
	if (empty ($message)) {
		return false;
	}

	$crlf = "\n";
	if( ! is_array( $to_list ) ) {
		$to_list = array( $to_list );
	}
	$to = player_rfc2822_address_list($to_list);

	if( !$from_person ) {
		$from_person = new Person;
		$from_person->email    = variable_get('app_admin_email','webmaster@localhost');
		$from_person->fullname = variable_get('app_admin_name', 'Leaguerunner Administrator');
	}
	$from = $from_person->rfc2822_address();
	$headers = "From: $from$crlf";

	if( $cc_list ) {
		$cc = player_rfc2822_address_list($cc_list);
		$headers .= "Cc: $cc$crlf";
	}

	if( empty( $to ) && empty( $cc ) ) {
		return false;
	}

	return mail($to, $subject, $message, $headers, "-f $from_person->email");
}

/**
 * Create a string with an RFC2822-compliant address list.
 */
function player_rfc2822_address_list( $players, $for_html = false )
{
	// If this is being created for embedding in HTML, we separate with
	// a semi-colon and linefeed, and HTML-encode it. This works nicely
	// with both mailto links and pre-formatted output of addresses.
	if($for_html) {
		$join = ";\n";
	} else {
		// Otherwise, we make a truly RFC-compliant list, suitable for
		// sending to the mail function.
		$join = ', ';
	}

	$list = array();
	foreach($players as $player) {
		$list[] = $player->rfc2822_address();
	}

	$output = join($join, $list);

	if( $for_html ) {
		return htmlentities( $output );
	}
	return $output;
}

?>
