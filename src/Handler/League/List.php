<?php
register_page_handler('league_list', 'LeagueList');

/**
 * League list handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class LeagueList extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("List Leagues");
		$this->_permissions = array(
		);

		return true;
	}

	function has_permission ()
	{
		global $DB, $session, $id;

		/* TODO! */
		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->set_template_file("common/generic_list.tmpl");

		$found = $DB->getAll(
			"SELECT 
				CONCAT_WS(' ',name, ratio, 'Tier', tier) AS value, 
				league_id AS id_val 
			 FROM league",
			array(), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'league_view'
			),
		));
		$this->tmpl->assign("page_op", "league_list");
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

?>
