<?php
require_once('Handler/season/edit.php');

class season_create extends season_edit
{
	function __construct()
	{
		$this->title = "Create Season";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('season','create');
	}

	function process ()
	{
		$id = -1;

		$this->season = new Season;

		return parent::process();
	}
}

?>
