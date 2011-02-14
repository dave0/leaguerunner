<?php
require_once('Handler/SeasonHandler.php');
class season_edit extends SeasonHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->season->name} &raquo; Edit";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('season','edit',$this->season->id);
	}

	function process ()
	{

		$edit = &$_POST['edit'];

		$this->template_name = 'pages/season/edit.tpl';

		$this->smarty->assign('yes_no', array( 'No', 'Yes') );
		$this->smarty->assign('seasons', getOptionsFromEnum('season','season') );

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("season/view/" . $this->season->id));

		} else {
			/* Deal with multiple days and start times */
			if(strpos($season->day, ",")) {
				$season->day = explode(',',$season->day);
			}
			$this->season->archived = $this->season->archived ? 1 : 0;
			$this->smarty->assign('edit', (array)$this->season);
		}
		return true;
	}

	function perform ( $edit )
	{
		$this->season->set('display_name', $edit['display_name']);

		$this->season->set('year', $edit['year']);
		$this->season->set('season', $edit['season']);
		$this->season->set('archived', $edit['archived']);

		if( !$this->season->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function check_input_errors ( $edit )
	{
		$errors = array();

		if ( ! validate_nonhtml($edit['display_name'])) {
			$errors[] = "A valid season name must be entered";
		}

		if( !validate_yyyymmdd_input($edit['year'] . '-01-01') ) {
			$errors[] = 'You must provide a valid year';
		}

		return $errors;
	}
}

?>
