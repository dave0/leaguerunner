<?php
require_once('Handler/NoteHandler.php');

class note_edit extends NoteHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "Note {$this->note->id} &raquo; Edit";
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('note','edit',$this->note->id);
	}

	function process ()
	{
		$edit = &$_POST['edit'];

		$this->template_name = 'pages/note/edit.tpl';

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("note/view/" . $this->note->id));

		} else {
			$this->smarty->assign('note', $this->note);
			$this->smarty->assign('edit', (array)$this->note);
		}
		return true;
	}

	function perform ($edit = array())
	{
		global $lr_session;
		$this->note->set('note', $edit['note']);
		if( !$this->note->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function check_input_errors ( $edit )
	{
		$errors = array();
		if( !validate_nonhtml($edit['note']) ) {
			$errors['edit[note]'] = 'You must not enter HTML in the note field';
		}

		return $errors;
	}
}
?>
