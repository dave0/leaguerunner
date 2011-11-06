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
		global $lr_session;
		$this->title = $this->event->name;

		$this->template_name = 'pages/event/view.tpl';
		$this->smarty->assign('event', $this->event);

		// Make sure the user is allowed to register for anything!
		if( ! $lr_session->user->is_active() ) {
			$this->smarty->assign('message', 'You may not register for an event until your account is activated');
			return;
		}
		if( ! $lr_session->user->is_player() ) {
			$this->smarty->assign('message', 'Your account is marked as a non-player account. Only players are allowed to register.');
			return;
		}

		list($event_register_cap, $event_register_count) = $this->event->get_applicable_cap( $lr_session->user );

		// 0 means that nobody of this gender is allowed
		if ( $event_register_cap == 0 ) {
			$this->smarty->assign('message', 'This event is for the opposite gender only.' );
			return;
		}

		$r = $this->event->get_registration_for( $lr_session->user->user_id );

		if ($r) {
			$this->smarty->assign('registration', $r);

			if( ! $r->payments_on_file() ) {
				// If anything is paid (including deposit) don't allow player to unregister
				$this->smarty->assign('allow_unregister', true);
			}

			// An unpaid registration might have been pre-empted by someone
			// who paid.
			if ( $r->payment == 'Unpaid' && $event_register_cap > 0 && $event_register_count >= $event_register_cap ) {
				$this->smarty->assign('message', 'Your payment was not received in time, so your registration has been moved to a waiting list. If you have any questions about this, please contact the head office.');
				return;
			}

			// If there's an unpaid registration, we may want to allow the
			// option to pay it.  However, the option may be displayed after
			// other text, so we'll build it here and save it for later.
			if( $r->payment != 'Paid' ) {
				$this->smarty->assign('message', 'You have already registered for this event, but not yet paid.  See below for payment information.');

				
				// include paypal as payment option if configured
				if (variable_get('paypal',''))
				{
					$this->smarty->assign('paypal','pages/event/register/paypal_payment.tpl');
					$this->smarty->assign('paypal_email', variable_get('paypal_email',''));
					
					// include user details for auto fill forms
					$this->smarty->assign('user', $lr_session->user);
				}
				
				$this->smarty->assign('offline_payment_text',
					strtr(
						variable_get('offline_payment_text', ''),
						array( '%order_num' => $r->formatted_order_id())
					)
				);

				$this->smarty->assign('refund_policy_text',
					variable_get('refund_policy_text', '')
				);
			}

			if( $this->event->multiple ) {
				// If we allow multiple registrations, show that.
				$this->smarty->assign('message', 'You have already registered for this event. However, this event allows multiple registrations (e.g. the same person can register teams to play on different nights).');
			} else if( $r->payment == 'Paid' ) {
				// Multiples are not allowed.  If the
				// registration is actually paid for (not
				// pending payment), just exit now, with no
				// extra description required.
				$this->smarty->assign('message', 'You have already registered and paid for this event.');
				return;
			} else {
				// Only way to get here is if multiple registrations are not
				// allowed, and a registration exists with a pending payment.  Let
				// the user make the payment, and nothing else.
				return;
			}
		}

		/* The time checks come _after_ the check for an existing
		 * registration so that payment can be dealt with after reg
		 * close.
		 */
		$time_now = time();
		// Admins can test registration before it opens...
		if (! $lr_session->is_admin() && ($this->event->open_timestamp > $time_now) ) {
			$this->smarty->assign('message', 'This event is not yet open for registration.');
			return;
		}

		if ($this->event->close_timestamp <= $time_now) {
			// There may be a payment-pending registration already done,
			// so we allow for payment to be made.
			$this->smarty->assign('message', 'Registration for this event has closed.');
			return;
		}

		// -1 means there is no cap, so don't even check the database
		if ( $event_register_cap > 0 ) {
			// Check if this event is already full
			if ( $event_register_count >= $event_register_cap ) {
				$admin_name = variable_get('app_admin_name', 'Leaguerunner Admin');
				$admin_addr = variable_get('app_admin_email','webmaster@localhost');
				$this->smarty->assign('message', "This event is already full.  You may email the <a href=\"mailto:$admin_addr\">$admin_name</a> or phone the head office to be put on a waiting list in case others drop out." );
				// There may be a payment-pending registration already done,
				// if multiples are allowed, so we allow for payment to be made.
				return;
			}
		}

		$this->smarty->assign('allow_register', true);

		// There may be a payment-pending registration already done,
		// if multiples are allowed, so we allow for payment to be made.
		return;
	}
}

?>
