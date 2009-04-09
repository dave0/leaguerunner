<?php
// $Id: mail.inc $

/**
 * Method for handling sending of email.
 * This is intended to be expandable, so that new implementations,
 * e.g. using Pear::Mail or some such, can be plugged in without
 * changes to any other code.
 */

function send_mail($to_addr, $to_name, $from_addr, $from_name, $cc_addr, $cc_name, $subject, $message)
{
	if (empty ($message))
		return false;

	$crlf = "\n";
	$to = create_rfc2822_address_list($to_addr, $to_name, false);

	if( !$from_addr )
	{
		$from_addr = variable_get('app_admin_email','webmaster@localhost');
		$from_name = variable_get('app_admin_name', 'Leaguerunner Administrator');
	}
	$from = create_rfc2822_address_list($from_addr, $from_name, false, false);
	$headers = "From: $from$crlf";

	if( $cc_addr )
	{
		$cc = create_rfc2822_address_list($cc_addr, $cc_name, false);
		$headers .= "Cc: $cc$crlf";
	}

	if( empty( $to ) && empty( $cc ) )
	{
		return false;
	}

	return mail($to, $subject, $message, $headers, "-f $from_addr");
}

/**
 * Create a string with an RFC2822-compliant address or address-list.
 */
function create_rfc2822_address_list($addrs, $names, $for_html, $multiple_allowed = true)
{
	// If this is being created for embedding in HTML, we separate with
	// a semi-colon and linefeed, and HTML-encode it. This works nicely
	// with both mailto links and pre-formatted output of addresses.
	if($for_html)
	{
		$join = ";\n";
	}
	// Otherwise, we make a truly RFC-compliant list, suitable for sending
	// to the mail function.
	else
	{
		$join = ', ';
	}

	$output = '';

	if(is_array($addrs))
	{
		if($multiple_allowed)
		{
			if(is_array($names))
			{
				if(count($addrs) != count($names))
				{
					error_exit('create_rfc2822_address_list: number of addresses does not match number of names.');
				}

				$list = array_combine($addrs, $names);
				foreach($list as $addr => $name)
				{
					$list[$addr] = create_rfc2822_address($name, $addr);
				}
				$output = join($join, $list);
			}
			else if(!$names)
			{
				$output = join($join, $addrs);
			}
			else
			{
				error_exit('create_rfc2822_address_list: array of addresses provided but only a single name.');
			}
		}
		else
		{
			error_exit('create_rfc2822_address_list: array of addresses not allowed.');
		}
	}

	else
	{
		if(is_array($names))
		{
			error_exit('create_rfc2822_address_list: array of names provided but only a single address.');
		}

		$output = create_rfc2822_address($names, $addrs);
	}

	if( $for_html )
	{
		return htmlentities( $output );
	} else {
		return $output;
	}
}

function create_rfc2822_address($name, $addr)
{
	if(!$addr)
	{
		error_exit('create_rfc2822_address: blank address given');
	}
	else if(!$name)
	{
		return $addr;
	}
	else if(strpos($name, ' '))
	{
		return "\"$name\" <$addr>";
	}
	else
	{
		return "$name <$addr>";
	}
}

?>
