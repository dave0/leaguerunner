<?php
register_page_handler('field_list', 'FieldList');

/**
 * Field list handler
 *
 * @package Leaguerunner
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class FieldList extends Handler
{
	/** 
	 * Initializer
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->name = "List Fields";
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
				name AS value, 
				field_id AS id_val 
			 FROM field_info",
			array(), DB_FETCHMODE_ASSOC);
		if($this->is_database_error($found)) {
			return false;
		}
		
		$this->tmpl->assign("view_op", "field_view");
		$this->tmpl->assign("page_op", "field_list");
		$this->tmpl->assign("list", $found);
		
		return true;
	}
}

?>
