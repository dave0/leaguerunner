<?php
require_once('Handler/field/edit.php');

class field_create extends field_edit
{
	function __construct ( )
	{
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','create');
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$this->title = "Create Field";

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm($edit);
				break;
			case 'perform':
				$this->field = new Field;
				$this->perform($this->field, $edit);
				local_redirect(url("field/view/" . $this->field->fid));
				break;
			default:
				$edit = array();
				$rc = $this->generateForm($edit);
		}
		return $rc;
	}
}
?>
