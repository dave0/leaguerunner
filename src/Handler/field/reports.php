<?php

require_once('Handler/FieldHandler.php');

class field_reports extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view reports', $this->field->fid);
	}

	function process ()
	{
		$this->title = "Reports: {$this->field->fullname}";

		if (!$this->field) {
			error_exit("That field does not exist");
		}

		$this->template_name = 'pages/field/reports.tpl';

		$this->smarty->assign('reports', FieldReport::load_many(array('field_id' => $this->field->fid, '_order' => 'created DESC' )));
		return true;
	}
}
?>
