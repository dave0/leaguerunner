<?php
register_page_handler('person_list', 'PersonList');

/**
 * Player list handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class PersonList extends Handler
{
	/** 
	 * Initializer for PersonList class
	 *
	 * @access public
	 */
	function initialize ()
	{
		global $session;
		$this->set_title("List Users");
		$this->_permissions = array(
			'delete' => false,
		);

		/* Administrator can do all */
		if($session->attr_get('class') == 'administrator') {
			$this->enable_all_perms();
			return true;
		}

		return true;
	}

	function has_permission ()
	{
		global $DB, $session;
		
		/* Anyone with a valid session id has permission */
		if(!$session->is_valid()) {
			return false;
		}

		return true;
	}

	function process ()
	{
		global $DB;

		$this->set_template_file("common/generic_list.tmpl");

		$letter = var_from_getorpost("letter");
		if(!isset($letter)) {
			$letter = "A";
		}
		$letter = strtoupper($letter);

		$found = $DB->getAll(
			"SELECT 
				CONCAT(lastname,', ',firstname) AS value, 
				user_id AS id_val 
			 FROM person 
			 WHERE lastname LIKE ? ORDER BY lastname",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("letter", $letter);
		$ops = array(
			array(
				'description' => 'view',
				'action' => 'person_view'
			),
		);
		if($this->_permissions['delete']) {
			$ops[] = array(
				'description' => 'delete',
				'action' => 'person_delete'
			);
		}
		$this->tmpl->assign("available_ops", $ops);
		
		$this->tmpl->assign("page_op", "person_list");
		$foo = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		
		$this->tmpl->assign("letters", $foo);
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}
}

?>
