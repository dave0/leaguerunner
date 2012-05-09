<?php
require_once('Handler/league/edit.php');

class league_create extends league_edit
{
	function __construct()
	{
		$this->title = "Create League";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('league','create');
	}

	function process ()
	{
		$this->league = new League;

		return parent::process();
	}

	function perform ( $edit )
	{
		global $lr_session;

		$this->league->set('name',$lr_session->attr_get('user_id'));
		$this->league->add_coordinator($lr_session->user);

		return parent::perform($edit);
	}
}

?>