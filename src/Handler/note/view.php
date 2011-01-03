<?php
require_once('Handler/NoteHandler.php');

class note_view extends NoteHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('note','view', $this->note->id);
	}

	function process ()
	{
		$this->title = 'Note View';

		$this->template_name = 'pages/note/view.tpl';
		$this->smarty->assign('note', $this->note);

		return true;
	}
}
?>
