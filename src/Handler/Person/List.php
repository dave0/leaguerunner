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
		$this->name = "List Users";
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
		$this->tmpl->assign("view_op", "person_view");
		$this->tmpl->assign("page_op", "person_list");
		$foo = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		
		$this->tmpl->assign("letters", $foo);
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}
}

?>
