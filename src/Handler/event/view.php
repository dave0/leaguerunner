<?php
require_once('Handler/EventHandler.php');
class event_view extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','view', $this->event->registration_id);
	}

	function process ()
	{
		global $dbh;
		$this->title = "Event: {$this->event->name}";

		$rows = array();
		$rows[] = array('Description:', $this->event->description);
		$rows[] = array('Event type:', $this->event_types[$this->event->type]);
		$rows[] = array('Cost:', '$' . $this->event->total_cost());
		$rows[] = array('Opens on:', $this->event->open);
		$rows[] = array('Closes on:', $this->event->close);
		if ($this->event->cap_female == -2)
		{
			$rows[] = array('Registration cap:', $this->event->cap_male);
		}
		else
		{
			if ($this->event->cap_male > 0)
			{
				$rows[] = array('Male cap:', $this->event->cap_male);
			}
			if ($this->event->cap_female > 0)
			{
				$rows[] = array('Female cap:', $this->event->cap_female);
			}
		}
		$rows[] = array('Multiples:', $this->event->multiple ? 'Allowed' : 'Not allowed');
		if( $this->event->anonymous ) {
			$rows[] = array('Survey:', 'Results of this event\'s survey will be anonymous.');
		}

		$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";

		$output .= para('');

		$output .= $this->check_prereq();

		return $output;
	}

	function check_prereq()
	{
		global $lr_session, $dbh;
		$output = $payment = '';

		// Make sure the user is allowed to register for anything!
		if( ! $lr_session->user->is_active() ) {
			return para('You may not register for an event until your account is activated');
		}
		if( ! $lr_session->user->is_player() ) {
			return para('Your account is marked as a non-player account. Only players are allowed to register. Please ' . l('edit your account', "person/edit/{$lr_session->user->user_id}") . ' to enable this.');
		}

		$where  = '';
		$params = array( $this->event->registration_id );
		// We need these numbers in a couple of places below
		if ($this->event->cap_female == -2)
		{
			$applicable_cap = $this->event->cap_male;
		}
		else
		{
			$applicable_cap = ( $lr_session->user->gender == 'Male' ?
									$this->event->cap_male :
									$this->event->cap_female );
			$where = ' AND p.gender = ?';
			array_push( $params, $lr_session->user->gender );
		}
		$sth = $dbh->prepare("SELECT COUNT(order_id)
			FROM registrations r
			LEFT JOIN person p ON r.user_id = p.user_id
			WHERE registration_id = ?
			AND (payment = 'Paid' OR payment = 'Pending')
			$where");
		$sth->execute( $params );
		$registered_count = $sth->fetchColumn();

		// TODO: why are we not using the registrations class?
		// Check if the user has already registered for this event
		$sth = $dbh->prepare("SELECT *
				FROM registrations
				WHERE user_id = ?
				AND registration_id = ?
				AND payment != 'Refunded'
				ORDER BY payment");
		$sth->execute( array( 
			$lr_session->user->user_id,
			$this->event->registration_id)
		);
		$row = $sth->fetch(PDO::FETCH_OBJ);
		if ($row)
		{
			// If there's an unpaid registration, we may want to allow the
			// option to pay it.  However, the option may be displayed after
			// other text, so we'll build it here and save it for later.
			if( is_unpaid ($row) )
			{
				$payment = para('You have already registered for this event, but not yet paid.');
				$payment .= para('If you registered in error, or have changed your mind about participating, or want to change your previously selected preferences, you can ' . l('unregister', 'registration/unregister/' . $row->order_id) . '.');

				// An unpaid registration might have been pre-empted by someone
				// who paid.
				if ( $row->payment == 'Unpaid' &&
					$applicable_cap > 0 && $registered_count >= $applicable_cap )
				{
					$payment .= para('Your payment was not received in time, so your registration has been moved to a waiting list. If you have any questions about this, please contact the head office.');
				}
				else
				{
					$reg = registration_load( array('order_id' => $row->order_id ) );
					$order_num = sprintf(variable_get('order_id_format', '%d'), $reg->order_id);

					$payment .= h2('Payment');
					if( variable_get( 'online_payments', 1 ) ) {
						$payment .= generatePayForm($this->event, $order_num);
					}

					$payment .= OfflinePaymentText($order_num);
					$payment .= RefundPolicyText();
				}
			}

			// If the record is considered paid, and we allow multiple
			// registrations, show that.
			if( is_paid ($row) && $this->event->multiple )
			{
				$output = para('You have already registered for this event. However, this event allows multiple registrations (e.g. the same person can register teams to play on different nights).');
			}

			// Multiples are not allowed.  If the registration is actually paid
			// for (not pending payment), just exit now, with no extra
			// description required.
			else if( $row->payment == 'Paid' ) {
				return para('You have already registered and paid for this event.');
			}

			// Only way to get here is if multiple registrations are not
			// allowed, and a registration exists with a pending payment.  Let
			// the user make the payment, and nothing else.
			else {
				return $payment;
			}
		}

		$currentTime = date ('Y-m-d H:i:s', time());
		// Admins can test registration before it opens...
		if (! $lr_session->is_admin())
		{
			if ($this->event->open_timestamp > time()) {
				return para('This event is not yet open for registration.');
			}
		}
		if (time() > $this->event->close_timestamp) {
			// There may be a payment-pending registration already done,
			// so we allow for payment to be made.
			return para('Registration for this event has closed.') . $payment;
		}

		// 0 means that nobody of this gender is allowed
		if ( $applicable_cap == 0 )
		{
			// No way for a payment-pending registration to have been done.
			return para( 'This event is for the opposite gender only.' );
		}

		// -1 means there is no cap, so don't even check the database
		else if ( $applicable_cap > 0 )
		{
			// Check if this event is already full
			if ( $registered_count >= $applicable_cap )
			{
				// TODO: Allow people to put themselves on a waiting list
				$admin_name = variable_get('app_admin_name', 'Leaguerunner Admin');
				$admin_addr = variable_get('app_admin_email','webmaster@localhost');
				$output .= para( "This event is already full.  You may email the <a href=\"mailto:$admin_addr\">$admin_name</a> or phone the head office to be put on a waiting list in case others drop out." );
				// There may be a payment-pending registration already done,
				// if multiples are allowed, so we allow for payment to be made.
				return $output . $payment;
			}
		}

		$output .= h2(l('Register now!', 'event/register/' . $this->event->registration_id, array('title' => 'Register for ' . $this->event->name, 'style' => 'text-decoration: underline;')));
		// There may be a payment-pending registration already done,
		// if multiples are allowed, so we allow for payment to be made.
		return $output . $payment;
	}
}

// These functions tell whether a registration record is considered to be paid,
// and whether it can still be paid.  They are not necessarily exclusive; if
// tentative registrations are allowed, then a record can be considered both
// paid (for the purposes of allowing further registrations) but also unpaid
// (for the purposes of deciding whether to allow the user to pay for it).
function is_paid ($record)
{
	return( $record->payment == 'Paid' || 
		( variable_get('allow_tentative', 0) && $record->payment == 'Pending' ) );
}

function is_unpaid ($record)
{
	return( $record->payment == 'Unpaid' || $record->payment == 'Pending' );
}


?>
