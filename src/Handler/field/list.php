<?php

class field_list extends Handler
{

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','list');
	}

	function process ()
	{
		global $lr_session;

		$this->template_name = 'pages/field/list.tpl';

		$this->title = 'List Fields';

		if( $lr_session->has_permission('field','list','closed') ) {
			$status = '';
		} else {
			$status = "AND (status = 'open' OR ISNULL(status))";
		}
		$sth = Field::query( array( '_extra' => "ISNULL(parent_fid) $status", '_order' => 'f.region,f.name') );

		$fields = array();
		while($field = $sth->fetch(PDO::FETCH_OBJ) ) {
			if(! array_key_exists( $field->region, $fields) ) {
				$fields[$field->region] = array();
			}
			array_push( $fields[$field->region], $field );
		}

		$this->smarty->assign('fields_by_region', $fields);

		return true;
	}
}
?>
