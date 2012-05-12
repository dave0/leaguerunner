<?php
require_once('Handler/season/edit.php');

class season_create extends season_edit
{
	function __construct()
	{
		$this->title = "Create Season";
		$this->season = new Season;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('season','create');
	}
}

?>
