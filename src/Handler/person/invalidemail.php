<?php

require_once('Handler/PersonHandler.php');

class person_invalidemail extends PersonHandler
{
	function has_permission()
	{
		global $lr_session;

		return $lr_session->has_permission('person','delete', $this->person->user_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->person->fullname} &raquo; Invalidate Email";

		$this->template_name = 'pages/person/invalidemail.tpl';

		$this->smarty->assign('person', $this->person);

		if( $_POST['submit'] == 'Submit' ) {
			$this->person->set('email', null);
			$this->person->set('status', 'inactive');
			if( ! $this->person->save() ) {
				error_exit('Failure saving account');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
