<?php

class FieldHandler extends Handler
{
	protected $field;

	function __construct ( $id )
	{
		$this->field = field_load( array('fid' => $id) );

		if(!$this->field) {
			error_exit("That field does not exist");
		}

		field_add_to_menu( $this->field );
	}
}
?>
