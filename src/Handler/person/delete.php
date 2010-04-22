<?php

require_once('Handler/person/view.php');

class person_delete extends person_view
{
	function has_permission()
	{
		global $lr_session;

		return $lr_session->has_permission('person','delete', $this->person->user_id);
	}

	function process ()
	{
		global $lr_session;
		$this->title = 'Delete';
		$edit = $_POST['edit'];

		/* Safety check: Don't allow us to delete ourselves */
		if($lr_session->attr_get('user_id') == $this->person->user_id) {
			error_exit("You cannot delete your own account!");
		}

		if($edit['step'] == 'perform') {
			$this->person->delete();
			local_redirect(url("person/search"));
			return $rc;
		}

		$this->setLocation(array(
			$this->person->fullname => "person/view/" . $this->person->user_id,
			$this->title => 0));

		return 
			para("Confirm that you wish to delete this user from the system.")
			. $this->generateView($this->person)
			. form( 
				form_hidden('edit[step]', 'perform')
				. form_submit("Delete")
			);
	}
}

?>
