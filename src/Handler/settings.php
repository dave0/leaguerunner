<?php

class settings extends Handler
{
	function __construct ( $type )
	{
		if( ! preg_match('/^[a-z]+$/', $type)) {
			error_exit('invalid type');
		}

		$this->title = 'Settings';
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		global $conf;

		$edit = $_POST['edit'];

		$this->template_name = 'pages/settings/'.$this->type.'/edit.tpl';

		$this->generateForm( $edit );
		$this->smarty->assign('settings', $conf);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect('settings/' . $this->type);
		} else {
			$this->smarty->assign('edit', $conf);
		}

		return true;
	}

	function perform( &$edit )
	{
		foreach ( $edit as $name => $value ) {
			if ($name == 'step') {
				continue;
			}
			variable_set($name, $value);
		}

		return true;
	}

	function generateForm( &$edit )
	{
		if( !isset($edit['app_admin_email'] ) ) {
			$edit['app_admin_email'] = $_SERVER['SERVER_ADMIN'];
		}
		if( !isset($edit['privacy_policy'] ) ) {
			$edit['privacy_policy'] = $_SERVER['SERVER_NAME']."/privacy";
		}
		if( !isset($edit['password_reset'] ) ) {
			$edit['password_reset'] = url('person/forgotpassword');
		}

		$this->smarty->assign('app_org_province', $edit['app_org_province']);

		$this->smarty->assign('province_names', getProvinceNames());
		$this->smarty->assign('state_names', getStateNames());
		$this->smarty->assign('country_names',  getCountryNames());

		$this->smarty->assign('seasons', getOptionsFromQuery("SELECT id AS theKey, display_name AS theValue FROM season ORDER BY year, id"));

		$this->smarty->assign('enable_disable', array('Disabled', 'Enabled'));

		$this->smarty->assign('questions', array(
			'team_spirit' => 'team_spirit',
			'ocua_team_spirit' => 'ocua_team_spirit',
		));

		$this->smarty->assign('log_levels', array(
			KLogger::EMERG=>'Emergency',
			KLogger::ALERT=>'Alert',
			KLogger::CRIT=>'Critical' ,
			KLogger::ERR=>'Error',
			KLogger::WARN=>'Warning',
			KLogger::NOTICE=>'Notice' ,
			KLogger::INFO=>'Information',
			KLogger::DEBUG=>'Debug' ,
		));

		$this->smarty->assign('live_sandbox', array('Live', 'Sandbox'));
	}

	function check_input_errors ( $edit = array() )
	{
		$errors = array();

		return $errors;
	}
}
?>
