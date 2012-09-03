<?php
require_once('Handler/field/edit.php');

class field_create extends field_edit
{
	function __construct ( )
	{
		$this->title = 'Create Field';
		$this->field = new Field;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','create');
	}
}
?>
