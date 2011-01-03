<?php

class NoteHandler extends Handler
{
	protected $note;

	function __construct ( $id )
	{
		$this->note = Note::load( array('id' => $id) );

		if(!$this->note) {
			error_exit("That note does not exist");
		}

		$this->note->load_creator();

		note_add_to_menu( $this->note );
	}
}
?>
