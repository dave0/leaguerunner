<?php
register_page_handler('team_standings', 'TeamStandings');

/**
 * Team standings view handler
 *
 * @package Leaguerunner
 * @version $Id$
 * @author Dave O'Neill <dmo@acm.org>
 * @access public
 * @copyright GPL
 */
class TeamStandings extends Handler
{
	/** 
	 * Initializer for TeamStandings class
	 *
	 * @access public
	 */
	function initialize ()
	{
		$this->_league_id = null;
		return true;
	}

	/**
	 * Check if the current session has permission to view team standings
	 *
	 * We really don't need to check perms, as we're just redirecting.
	 *
	 * @access public
	 * @return boolean success/fail
	 */
	function has_permission ()
	{
		global $id;
		
		$id = var_from_getorpost('id');
		if(is_null($id)) {
			$this->error_text = gettext("You must provide an ID to view");
			return false;
		}
		
		return true;
	}

	function process ()
	{
		global $DB, $id;

		$this->_league_id = $DB->getOne("
			SELECT 
				league_id
			FROM 
				leagueteams
			WHERE 
				team_id = ?", 
		array($id));

		if($this->is_database_error($this->_league_id)) {
			$this->error_text .= gettext("The team [$id] may not exist");
			return false;
		}

		return true;
	}

	/** 
	 * Display method for TeamStandings
	 *
	 * Overrides the parent class display() method to output a
	 * redirection header instead of an HTML page.
	 * 
	 * @access public
	 */
	function display()
	{
		return $this->output_redirect("op=league_standings;id=" . $this->_league_id);
	}
}

?>
