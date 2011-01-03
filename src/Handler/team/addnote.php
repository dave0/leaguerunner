<?php

require_once('Handler/note/edit.php');

class team_addnote extends note_edit
{
	function __construct ( $team_id )
	{
		$this->title = "Create Note";
		$this->note  = new Note;
		$this->note->assoc_type = 'team';
		$this->note->assoc_id   = $team_id;
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
