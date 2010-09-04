<?php

class person_changepassword extends Handler
{
	protected $person;

	function __construct ( $id )
	{
		global $lr_session;
		if( $id ) {
			$this->person = Person::load( array('user_id' => $id) );
		}

		if( ! $this->person ) {
			$this->person =& $lr_session->user;
		}
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('person','password_change', $this->person->user_id);
	}

	function process()
	{
		global $lr_session;
		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'perform':
				if($edit['password_one'] != $edit['password_two']) {
					error_exit("You must enter the same password twice.");
				}
				$this->person->set('password', md5($edit['password_one']));
				if( ! $this->person->save() ) {
					error_exit("Couldn't change password due to internal error");
				}
				local_redirect(url("person/view/" . $this->person->user_id));
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm( )
	{
		$this->title = "{$this->person->fullname} &raquo; Change Password";
		$this->template_name = 'pages/person/changepassword.tpl';
		$this->smarty->assign('person', $this->person);

		return true;
	}
}

?>
