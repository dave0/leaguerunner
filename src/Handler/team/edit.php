<?php
require_once('Handler/TeamHandler.php');

class team_edit extends TeamHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "{$this->team->name} &raquo; Edit";
	}

	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','edit',$this->team->team_id);
	}

	function process ()
	{
		$edit = &$_POST['edit'];

		$this->template_name = 'pages/team/edit.tpl';

		$this->smarty->assign('status', getOptionsFromEnum('team', 'status'));

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect(url("team/view/" . $this->team->team_id));

		} else {
			$this->smarty->assign('edit', (array)$this->team);
		}
		return true;
	}

	function perform ($edit = array())
	{
		global $lr_session;
		$this->team->set('name', $edit['name']);
		$this->team->set('website', $edit['website']);
		$this->team->set('shirt_colour', $edit['shirt_colour']);
		$this->team->set('status', $edit['status']);
		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$this->team->set('home_field', $edit['home_field']);
		}

		if( !$this->team->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function check_input_errors ( $edit )
	{
		$errors = array();
		if( !validate_nonhtml($edit['name']) ) {
			$errors['edit[name]'] = 'You must enter a valid team name';
		}
		else if( !$this->team->validate_unique($edit['name']) ) {
			$errors['edit[name]'] = 'You must enter a unique team name';
		}

		if( !validate_nonhtml($edit['shirt_colour']) ) {
			$errors['edit[shirt_colour]'] = 'Shirt colour cannot be left blank';
		}

		if(validate_nonblank($edit['website'])) {
			if( ! validate_nonhtml($edit['website']) ) {
				$errors['edit[website]'] = 'If you provide a website URL, it must be valid. Otherwise, leave the website field blank.';
			}
		}

		return $errors;
	}
}
?>
