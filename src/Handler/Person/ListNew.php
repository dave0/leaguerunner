<?php
register_page_handler('person_listnew', 'PersonListNewAccounts');

/**
 * Player list handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonListNewAccounts extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->set_title("List New Accounts");
		return true;
	}

	function has_permission ()
	{
		global $DB, $session;
		
		if(!$session->is_valid()) {
			return false;
		}

		if($session->attr_get('class') != 'administrator') {
			return false;
		}

		return true;
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("common/generic_list.tmpl");

		$found = $DB->getAll(
			"SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id_val 
			 FROM person 
			 WHERE
			 	class = 'new'
			 ORDER BY lastname",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("available_ops", array(
			array(
				'description' => 'view',
				'action' => 'person_view'
			),
			array(
				'description' => 'approve',
				'action' => 'person_approvenew'
			),
			array(
				'description' => 'delete',
				'action' => 'person_delete'
			),
		));
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}
}

?>
