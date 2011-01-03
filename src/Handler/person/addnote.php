<?php

require_once('Handler/note/edit.php');

class person_addnote extends note_edit
{
	function __construct ( $user_id )
	{
		global $lr_session;
		$this->title = "Create Note";
		$this->note  = new Note;
		$this->note->assoc_type = 'person';
		$this->note->assoc_id   = $user_id;
		$this->note->creator    = $lr_session->user;
		$this->note->creator_id = $lr_session->user_id;
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('note','create');
	}
}

?>
