<?php
register_page_handler('system_viewfile','SystemViewFile');

class SystemViewFile extends Handler
{
	/**
	 * Initializes the template for this handler. 
	 */
	function initialize ()
	{
		$this->_required_perms = array(
			'allow'
		);
		return true;
	}
	
	/**
	 * Generate the menu
	 *
	 * @access public
	 * @return boolean success or failure.
	 */
	function process ()
	{

		$file = var_from_getorpost('file');

		switch($file) {
			case 'player_waiver':
				$this->set_template_file("Person/waiver_form.tmpl");
				$this->tmpl->assign('view_only', true);
				$rc = true;
				break;
			case 'dog_waiver':
				$this->set_template_file("Person/dog_waiver_form.tmpl");
				$this->tmpl->assign('view_only', true);
				$rc = true;
				break;
			default:
				$this->error_text = "You cannot view that file";
				$rc = false;
		}

		return $rc;
	}
}
?>
