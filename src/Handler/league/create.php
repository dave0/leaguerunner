<?php
require_once('Handler/league/edit.php');

class league_create extends league_edit
{
	function __construct() { }

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','create');
	}

	function process ()
	{
		$id = -1;
		$edit = $_POST['edit'];
		$this->title = "Create League";

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->league = new League;
				$this->perform( $edit );
				local_redirect(url("league/view/" . $this->league->league_id));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm( $edit );
		}
		return $rc;
	}

	function perform ( $edit )
	{
		global $lr_session;

		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->league->set('name',$lr_session->attr_get('user_id'));
		$this->league->add_coordinator($lr_session->user);

		return parent::perform($edit);
	}
}

?>
