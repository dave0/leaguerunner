<?php

require_once('Handler/PersonHandler.php');

class person_delete extends PersonHandler
{
	function has_permission()
	{
		global $lr_session;

		return $lr_session->has_permission('person','delete', $this->person->user_id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "{$this->person->fullname} &raquo; Delete";

		/* Safety check: Don't allow us to delete ourselves */
		if($lr_session->attr_get('user_id') == $this->person->user_id) {
			error_exit("You cannot delete your own account!");
		}

		$this->template_name = 'pages/person/delete.tpl';

		$this->smarty->assign('person', $this->person);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->person->delete() ) {
				error_exit('Failure deleting person');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
