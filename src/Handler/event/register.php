<?php
require_once('Handler/EventHandler.php');
class event_register extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		if (!$this->event) {
			error_exit('That event does not exist');
		}
		return $lr_session->has_permission('registration','register');
	}

	function process ()
	{
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm();
				break;

			case 'submit':
				$rc = $this->save();
				$rc .= $this->generatePay();
				break;

			default:
				if( $this->formbuilder ) {
					$rc = $this->generateForm();
				}
				else {
					$rc = $this->save();
					$rc .= $this->generatePay();
				}
		}

		$this->setLocation(array(
			$this->event->name => "event/view/" .$this->event->registration_id,
			$this->title => 0
		));
		return $rc;
	}

	function generateForm ()
	{
		global $CONFIG;

		$this->title = 'Registration';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		ob_start();
		$retval = @readfile(trim ($CONFIG['paths']['file_path'], '/') . "/data/registration_notice.html");
		if (false !== $retval) {
			$output = ob_get_contents();
		}
		ob_end_clean();

		$output .= form_hidden('edit[step]', 'confirm');

		$output .= $this->formbuilder->render_editable (false);
		$output .= para(form_submit('submit', 'submit') . form_reset('reset'));

		return form($output);
	}

	function generateConfirm()
	{
		global $lr_session;

		$output = '';
		$this->title = 'Confirm preferences';

		// This shouldn't happen...
		if (! $this->formbuilder )
		{
			return para( 'Error: No event survey found!' );
		}

		$process_func = "confirm_{$this->event->type}";
		$dataInvalid = '';
		if( method_exists( $this, $process_func ) ) {
			$dataInvalid .= $this->$process_func();
		}

		$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
		$dataInvalid .= $this->formbuilder->answers_invalid();
		if( $dataInvalid ) {
			error_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$form = form_hidden('edit[step]', 'submit');
		$form .= $this->formbuilder->render_viewable();
		$form .= $this->formbuilder->render_hidden();
		$output = form_group('Registration', $form);

		$output .= para('Please confirm that this data is correct and click the submit button to proceed to the payment information page.');
		$output .= para(form_submit('submit'));

		return form($output);
	}

	function save()
	{
		global $lr_session;

		$output = para('');

		$process_func = "save_{$this->event->type}";
		if( method_exists( $this, $process_func ) ) {
			$output .= $this->$process_func();
		}

		$this->registration = new Registration;
		$this->registration->set('user_id', $lr_session->user->user_id);
		$this->registration->set('registration_id', $this->event->registration_id);
		$this->registration->set('total_amount', $this->event->total_cost());

		if (! $this->registration->save() ) {
			error_exit('Could not create registration record.');
		}

		if( $this->formbuilder && !$this->registration->save_answers( $this->formbuilder, $_POST[$this->event->formkey()] ) ) {
			error_exit('Error saving registration question answers.');
		}

		return $output . para(theme_error('Your registration has been recorded.  See Payment Details below.'));
	}

	/**
	 * The following functions are for doing any type-specific handling.
	 * Types that have no specific handling do not need to be implemented.
	 */
	function confirm_team_league()
	{

		global $lr_session;

		$team_id = $_POST[$this->event->formkey()]['__auto__team_id'];

		if( $lr_session->user->has_position_on( $team_id, array('captain') ) ) {
			return false;
		}
		return "<ul><li>You do not captain team $team_id</li></ul>";
	}

	function confirm_team_event()
	{
		return $this->confirm_team_league();
	}

	function save_team_league()
	{
		global $lr_session;

		$team_id = $_POST[$this->event->formkey()]['__auto__team_id'];
		if( $lr_session->user->has_position_on( $team_id, array('captain') ) ) {
			return false;
		}
		if( $team->perform( $auto_data ) ) {
			return para( theme_error( 'A team record has been created with you as captain.' ) );
		}
		else {
			return para( theme_error( 'Failed to create the team record. Contact ' . variable_get('app_admin_email', 'webmaster@localhost') . ' to ensure that this situation is resolved.' ) );
		}
	}

	function save_team_event()
	{
		return $this->save_team_league();
	}

	/**
	 * Generate the page about payment information.
	 */
	function generatePay()
	{
		global $lr_session;
		global $dbh;

		$order_num = sprintf(variable_get('order_id_format', '%d'), $this->registration->order_id);

		if( $this->event->cost == 0 ) {
			$sth = $dbh->prepare("UPDATE registrations SET payment = 'Paid' 
					WHERE order_id = ?");
			$sth->execute( array( $this->registration->order_id) );
			if ( 1 != $sth->rowCount() ) {
				$errors .= para( theme_error( "Your registration was received, but there was an error updating the database. Contact the office to ensure that your information is updated, quoting order #<b>$order_num</b>, or you may not be allowed to be added to rosters, etc." ) );
			}

			$this->title = 'Registration complete';

			return para('Since there is no payment associated with this event, your registration is now complete.');
		}

		$this->title = 'Arrange for payment';

		if( variable_get( 'online_payments', 1 ) )
		{
			$output .= generatePayForm($this->event, $order_num);

			$output .= OfflinePaymentText($order_num);

			$output .= para('Alternately, if you choose not to complete the payment process at this time, you will be able to start the registration process again at a later time and it will pick up where you have left off.');
		} else {
			$output .= h2 ('Payment Details');
			$output .= strtr( variable_get('offline_payment_text', ''),
						array( '%order_num' => $order_num ) );
		}
		$output .= RefundPolicyText();

		return $output;
	}
}

