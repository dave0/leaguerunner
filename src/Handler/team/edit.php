<?php
require_once('Handler/TeamHandler.php');

class team_edit extends TeamHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('team','edit',$this->team->team_id);
	}

	function process ()
	{
		$this->title = "Edit Team";
		$edit = &$_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'perform':
				$this->perform($edit);
				local_redirect(url("team/view/" . $this->team->team_id));
				break;
			default:
				$edit = object2array($this->team);
				$rc = $this->generateForm($edit);
		}
		$this->setLocation(array($edit['name']  => "team/view/" . $this->team->team_id, $this->title => 0));
		return $rc;
	}

	function generateForm (&$formData)
	{
		global $lr_session;

		$output = form_hidden("edit[step]", 'confirm');

		$rows = array();
		$rows[] = array("Team Name:", form_textfield('', 'edit[name]', $formData['name'], 35,200, "The full name of your team.  Text only, no HTML"));
		$rows[] = array("Website:", form_textfield('', 'edit[website]', $formData['website'], 35,200, "Your team's website (optional)"));
		$rows[] = array("Shirt Colour:", form_textfield('', 'edit[shirt_colour]', $formData['shirt_colour'], 35,200, "Shirt colour of your team.  If you don't have team shirts, pick 'light' or 'dark'"));

		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$rows[] = array("Home Field", form_textfield('','edit[home_field]', $formData['home_field'], 3,3,"Home field, if you happen to have one"));

		}

		$rows[] = array("Region Preference", form_select('','edit[region_preference]', $formData['region_preference'], getOptionsFromEnum('field', 'region'), "Area of city where you would prefer to play"));

		$rows[] = array("Team Status:",
			form_select("", "edit[status]", $formData['status'], getOptionsFromEnum('team','status'), "Is your team open (others can join) or closed (only captain can add players)"));

		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit") . form_reset("reset"));

		return form($output);
	}

	function generateConfirm ($edit = array() )
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$output = para("Confirm that the data below is correct and click 'Submit' to make your changes");
		$output .= form_hidden("edit[step]", 'perform');

		$rows[] = array("Team Name:", form_hidden('edit[name]',$edit['name']) .  $edit['name']);
		$rows[] = array("Website:", form_hidden('edit[website]',$edit['website']) .  $edit['website']);
		$rows[] = array("Shirt Colour:", form_hidden('edit[shirt_colour]',$edit['shirt_colour']) .  $edit['shirt_colour']);

		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$rows[] = array("Home Field:", form_hidden('edit[home_field]',$edit['home_field']) .  $edit['home_field']);
		}

		$rows[] = array("Region Preference:", form_hidden('edit[region_preference]',$edit['region_preference']) .  $edit['region_preference']);

		$rows[] = array("Team Status:", form_hidden('edit[status]',$edit['status']) .  $edit['status']);
		$output .= "<div class='pairtable'>" . table(null, $rows) . "</div>";
		$output .= para(form_submit("submit"));

		return form($output);
	}

	function perform ($edit = array())
	{
		global $lr_session;
		$dataInvalid = $this->isDataInvalid( $edit );
		if($dataInvalid) {
			error_exit($dataInvalid . "<br>Please use your back button to return to the form, fix these errors, and try again");
		}

		$this->team->set('name', $edit['name']);
		$this->team->set('website', $edit['website']);
		$this->team->set('shirt_colour', $edit['shirt_colour']);
		$this->team->set('status', $edit['status']);
		if( $lr_session->has_permission('team','edit', $this->team->team_id, 'home_field')) {
			$this->team->set('home_field', $edit['home_field']);
		}
		$this->team->set('region_preference', $edit['region_preference']);

		if( !$this->team->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		if( !validate_nonhtml($edit['name']) ) {
			$errors .= '<li>You must enter a valid team name';
		}
		else if( !$this->team->validate_unique($edit['name']) ) {
			$errors .= '<li>You must enter a unique team name';
		}

		if( !validate_nonhtml($edit['shirt_colour']) ) {
			$errors .= '<li>Shirt colour cannot be left blank';
		}

		if(validate_nonblank($edit['website'])) {
			if( ! validate_nonhtml($edit['website']) ) {
				$errors .= '<li>If you provide a website URL, it must be valid. Otherwise, leave the website field blank.';
			}
		}

		if(strlen($errors) > 0) {
			return "<ul>$errors</ul>";
		} else {
			return false;
		}
	}
}
?>
