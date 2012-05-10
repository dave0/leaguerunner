<?php
require_once('Handler/EventHandler.php');
class event_edit extends EventHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->event->name} &raquo; Edit";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('event','edit', $this->event->registration_id);
	}

	function process ()
	{
		$edit = $_POST['edit'];

		$this->template_name = 'pages/event/edit.tpl';

		$this->smarty->assign('event_types', $this->event_types);
		$this->smarty->assign('currency_codes', getCurrencyCodes());
		$this->smarty->assign('time_choices', getOptionsFromTimeRange(0000,2400,30));
		$this->smarty->assign('yes_no', array( 'No', 'Yes') );
		$this->smarty->assign('seasons', getOptionsFromQuery(
			"SELECT id AS theKey, display_name AS theValue FROM season ORDER BY year, id")
		);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("event/view/" . $this->event->registration_id));
		} else {

			# Smarty form auto-fill can't handle booleans directly,
			# so we substitute:
			$this->event->multiple = $this->event->multiple ? 1 : 0;
			$this->event->anonymous = $this->event->anonymous ? 1 : 0;

			$this->smarty->assign('edit', (array)$this->event);
		}
		return true;
	}

	function perform ( &$edit )
	{

		$this->event->set('name', $edit['name']);
		$this->event->set('description', $edit['description']);
		$this->event->set('type', $edit['type']);
		$this->event->set('season_id', $edit['season_id']);
		$this->event->set('currency_code', $edit['currency_code']);
		$this->event->set('cost', $edit['cost']);
		$this->event->set('gst', $edit['gst']);
		$this->event->set('pst', $edit['pst']);

		$time = $edit['open_time'];
		if ($time == '24:00') { $time = '23:59:00'; }
		$this->event->set('open', $edit['open_date'] . ' ' . $time);

		$time = $edit['close_time'];
		if ($time == '24:00') { $time = '23:59:00'; }
		$this->event->set('close', $edit['close_date'] . ' ' . $time);

		$this->event->set('cap_male', $edit['cap_male']);
		$this->event->set('cap_female', $edit['cap_female']);

		$this->event->set('multiple', $edit['multiple']);
		$this->event->set('anonymous', $edit['anonymous']);

		if( !$this->event->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function check_input_errors ( $edit )
	{
		$errors = array();

		if( !validate_nonhtml($edit['name'] ) ) {
			$errors[] = '<li>Name cannot be left blank, and cannot contain HTML';
		}

		if( !validate_currency_code($edit['currency_code']) ) {
			$errors[] = '<li>You must provide a valid currency code';
		}

		if( !validate_number($edit['cost'] ) ) {
			$errors[] = '<li>Invalid cost: not a number';
		}
		else if( $edit['cost'] < 0 ) {
			$errors[] = '<li>Invalid cost: cannot be negative';
		}

		if( !validate_number($edit['gst'] ) ) {
			$errors[] = '<li>Invalid GST: not a number';
		}
		else if( $edit['gst'] < 0 ) {
			$errors[] = '<li>Invalid GST: cannot be negative';
		}

		if( !validate_number($edit['pst'] ) ) {
			$errors[] = '<li>Invalid PST: not a number';
		}
		else if( $edit['pst'] < 0 ) {
			$errors[] = '<li>Invalid PST: cannot be negative';
		}

		if( !validate_yyyymmdd_input($edit['open_date']) ) {
			$errors[] = '<li>You must provide a valid open date';
		}

		if( !validate_yyyymmdd_input($edit['close_date']) ) {
			$errors[] = '<li>You must provide a valid close date';
		}

		if( !validate_number($edit['cap_male'] ) ) {
			$errors[] = '<li>Invalid male cap: not a number';
		}
		else if( $edit['cap_male'] < -1 ) {
			$errors[] = '<li>Invalid male cap: cannot be less than -1';
		}

		if( !validate_number($edit['cap_female'] ) ) {
			$errors[] = '<li>Invalid female cap: not a number';
		}
		else if( $edit['cap_female'] < -2 ) {
			$errors[] = '<li>Invalid female cap: cannot be less than -2';
		}
		else if( $edit['cap_female'] == -2 && $edit['cap_male'] <= 0 ) {
			$errors[] = '<li>Invalid female cap: can only be -2 if male cap is > 0';
		}

		return $errors;
	}
}

?>
