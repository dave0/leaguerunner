<?php

require_once('Handler/NoteHandler.php');

class note_delete extends NoteHandler
{
	function has_permission()
	{
		global $lr_session;

		return $lr_session->has_permission('note','delete', $this->note->id);
	}

	function process ()
	{
		global $lr_session;

		$this->title = "Note {$this->note->id} &raquo; Delete";

		$this->template_name = 'pages/note/delete.tpl';

		$this->smarty->assign('note', $this->note);

		if( $_POST['submit'] == 'Delete' ) {
			if( ! $this->note->delete() ) {
				error_exit('Failure deleting note');
			}
			$this->smarty->assign('successful', true);
		}
		return true;
	}
}

?>
