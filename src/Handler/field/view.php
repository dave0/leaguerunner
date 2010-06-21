<?php
require_once('Handler/FieldHandler.php');

class field_view extends FieldHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','view', $this->field->fid);
	}

	function process ()
	{
		global $lr_session;
		$this->title = $this->field->fullname;

		$this->template_name = 'pages/field/view.tpl';

		$ratings = field_rating_values();
		$this->field->rating_description = $ratings[$this->field->rating];

		$this->smarty->assign('field', $this->field);

		// list other fields at this site
		$sth = $this->field->find_others_at_site();
		$other_fields = array();
		while( $related = $sth->fetch(PDO::FETCH_OBJ)) {
			if ($related->fid != $this->field->fid) {
				$other_fields[] = $related;
			}
		}
		$this->smarty->assign('other_fields', $other_fields);

		return true;
	}
}
?>
