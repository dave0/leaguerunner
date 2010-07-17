<?php
require_once('Handler/person/search.php');
class person_listnew extends person_search
{

	// TODO: why do we have an initialize() anyway?  Shouldn't we just override the constructor?
	function initialize ( )
	{
		$this->title = 'New Accounts';

		$this->ops = array(
			'view'    => 'person/view',
			'approve' => 'person/approve',
			'delete'  => 'person/delete'
		);

		$this->extra_where = "p.status = 'new'";

		return true;
	}

	function has_permission ()
	{
		global $lr_session;
	 	return $lr_session->has_permission('person','listnew');
	}

	function process ()
	{
		$_GET['search'] = '*';
		return parent::process();
	}
}

?>
