<?php
register_page_handler('team_list', 'TeamList');

/**
 * Team list handler
 *
 * @package Teamrunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamList extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "List Teams";
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
				name AS value, 
				team_id AS id_val 
			 FROM team 
			 WHERE name LIKE ? ORDER BY name",
			array($letter . "%"), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("letter", $letter);
		$this->tmpl->assign("view_op", "team_view");
		$this->tmpl->assign("page_op", "team_list");
		$foo = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
		
		$this->tmpl->assign("letters", $foo);
		$this->tmpl->assign("list", $found);
			
		
		return true;
	}

}

?>
