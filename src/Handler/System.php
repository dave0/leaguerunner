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
	
	function process ()
	{

		$file = var_from_getorpost('file');

		switch($file) {
			case 'player_waiver':
				$this->set_title("Informed Consent Form For League Play");
				$filename = "data/waiver_form.html";
				break;
			case 'dog_waiver':
				$this->set_title("Informed Consent Form For Dog Owners");
				$filename = "data/dog_waiver_form.html";
				break;
			default:
				$this->error_exit("You cannot view that file");
		}
		$this->get_header();
		readfile($filename);
		$this->get_footer();

		return true;
	}
}
?>
