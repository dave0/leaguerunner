<?php

class PersonHandler extends Handler
{
	protected $person;

	function __construct ( $id )
	{
		$this->person = person_load( array('user_id' => $id) );

		if(!$this->person) {
			error_exit("That user does not exist");
		}

		person_add_to_menu( $this->person );
	}
}
?>
