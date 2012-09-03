<?php
require_once('Handler/EventHandler.php');
require_once('Handler/PaypalHandler.php');
require_once('Handler/person/search.php');

class event_register extends EventHandler
{
	private $registrant_id = null;
	private $registrant    = null;

	function __construct ( $event_id, $registrant_id = null)
	{
		global $lr_session;

		if( $lr_session->is_admin() && !is_null($registrant_id) ) {
			$this->registrant_id = $registrant_id;
			if( $this->registrant_id != 'choose' ) {
				$this->registrant = Person::load( array('user_id' => $registrant_id) );
			}
		} else {
			$this->registrant_id = $lr_session->user->user_id;
			$this->registrant    = $lr_session->user;
		}

		parent::__construct( $event_id, $this->registrant );
	}
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','register', $this->registrant_id);
	}

	function process ()
	{
		global $CONFIG;
		$this->title = "Registration &raquo; {$this->event->name}";

		if( $this->registrant_id == 'choose' ) {
			$new_handler = new person_search;
			$new_handler->smarty = &$this->smarty;
			$new_handler->initialize();
			$new_handler->ops['Register for event'] = 'event/register/' . $this->event->registration_id;
			$new_handler->process();
			$this->template_name = $new_handler->template_name;
			return true;

		}

		$edit = $_POST['edit'];

		switch($edit['step']) {
			default:
				// If we have a form, prompt user with it.
				if( $this->formbuilder ) {
					$this->template_name = 'pages/event/register/form.tpl';
					$this->smarty->assign('formbuilder_editable', $this->formbuilder->render_editable (false));
					return true;
				}

				// Otherwise, fall through to register automatically.
			case 'submit':
				$this->save();  // dies on failure
				$rc = $this->generatePay();
				break;
			case 'confirm':
				$rc = $this->generateConfirm();
				break;
		}

		return $rc;
	}

	function generateConfirm()
	{
		if (! $this->formbuilder ) {
			error_exit( 'Error: No event survey found!' );
		}

		$dataInvalid = $this->isDataInvalid();
		if( $dataInvalid ) {
			info_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->template_name = 'pages/event/register/confirm.tpl';
		$this->smarty->assign('formbuilder_viewable', $this->formbuilder->render_viewable() );
		$this->smarty->assign('formbuilder_hidden', $this->formbuilder->render_hidden() );

		return true;
	}

	function save()
	{
		$dataInvalid = $this->isDataInvalid();
		if( $dataInvalid ) {
			info_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->registration = new Registration;
		$this->registration->set('user_id', $this->registrant_id);
		$this->registration->set('registration_id', $this->event->registration_id);
		$this->registration->set('total_amount', $this->event->total_cost());
		if( $this->event->cost == 0 ) {
			$this->registration->set('payment', 'Paid');
		}

		// TODO: transaction, so that we roll back the registration if we can't save_answers()

		if (! $this->registration->save() ) {
			error_exit('Could not create registration record.');
		}

		if( $this->formbuilder && !$this->registration->save_answers( $this->formbuilder, $_POST[$this->event->formkey()] ) ) {
			error_exit('Error saving registration question answers.');
		}

		return;
	}

	function isDataInvalid ( )
	{

		$errors = '';
		$process_func = "confirm_{$this->event->type}";
		if( method_exists( $this, $process_func ) ) {
			$errors .= $this->$process_func();
		}

		if( $this->formbuilder ) {
			$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
			$errors .= $this->formbuilder->answers_invalid();
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}

	/**
	 * The following functions are for doing any type-specific handling.
	 * Types that have no specific handling do not need to be implemented.
	 */
	function confirm_team_league()
	{
		$team_id = $_POST[$this->event->formkey()]['__auto__team_id'];

		if( $this->registrant->has_position_on( $team_id, array('captain') ) ) {
			return false;
		}
		return "<ul><li>You do not captain team $team_id</li></ul>";
	}

	function confirm_team_event()
	{
		return $this->confirm_team_league();
	}

	/**
	 * Generate the page about payment information.
	 */
	function generatePay()
	{
		global $lr_session;
		global $CONFIG;

		$this->smarty->assign('order_number', $this->registration->formatted_order_id());

		if( $this->event->cost == 0 ) {
			$this->template_name = 'pages/event/register/done_no_cost.tpl';
			return true;
		}

		if ( variable_get('paypal','')) {
			$paypal = new PaypalHandler();
			$this->smarty->assign('paypal','pages/event/register/paypal_payment.tpl');
			$this->smarty->assign('shopping_url',$paypal->shopping_url);
			$this->smarty->assign('return_url', $paypal->return_url.$this->registration->order_id);
			$this->smarty->assign('paypal_url', $paypal->submit_url);
			$this->smarty->assign('paypal_email', $paypal->account_email);
			$this->smarty->assign('registration', $this->registration);
			$this->smarty->assign('event', $this->event);

			// include user details for auto fill forms
			// Paypal wants country codes, not names, so rewrite country value in user
			$lr_session->user->addr_country = getCountryCode($lr_session->user->addr_country);
			$this->smarty->assign('user', $lr_session->user);
		}

		$this->template_name = 'pages/event/register/offline_payment.tpl';

		// TODO: should probably just be a sub-template
		$this->smarty->assign('offline_payment_text',
			strtr(
				variable_get('offline_payment_text', ''),
				array( '%order_num' => $this->registration->formatted_order_id())
			)
		);
		$this->smarty->assign('refund_policy_text', variable_get('refund_policy_text', ''));
		$this->smarty->assign('partner_info_text', variable_get('partner_info_text', ''));
		return true;
	}
}
?>