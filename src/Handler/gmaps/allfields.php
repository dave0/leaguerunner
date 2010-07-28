<?php
class gmaps_allfields extends Handler
{
	function has_permission()
	{
		return true;
	}

	function process()
	{
		header("Content-type: text/xml");
		$this->template_name = 'pages/gmaps/allfields.tpl';


		$sth = Field::query( array( '_extra' => 'ISNULL(f.parent_fid) AND f.status = "open"', '_order' => 'f.fid') );

		$fields = array();
		while( $field = $sth->fetchObject('Field') ) {
			if(!$field->latitude || !$field->longitude) {
				continue;
			}
			$fields[] = $field;
		}

		$this->smarty->assign( 'fields', $fields );
	}
}

?>
