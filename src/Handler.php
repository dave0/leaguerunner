<?php

/**
 * This is the base class for all operation handlers used in the web UI.
 */
class Handler
{
	/**
	 * The page title, for display
	 *
	 * @access private
	 * @var string
	 */
	public $title;

	/**
	 * Smarty instance
	 */
	public $smarty;

	/**
	 * Template name.  Should be a relative path to the template to use.
	 */
	public $template_name;

	function __construct ( )
	{
	}

	/**
	 * Initialize our data
	 * This is where stuff that shouldn't be inherited should go.
	 */
	function initialize ()
	{
		return true;
	}

	/**
	 * Check for unsatisified prerequisites for this operation
	 *
	 * Right now, the main Handler class just needs to check that the account
	 * is active and the waiver has been signed as appropriate.
	 *
	 * This should be overridden by subclass when performing these checks is
	 * not appropriate (Login/Logout, PersonCreate, etc)
	 */
	function checkPrereqs ( $next )
	{
		global $lr_session;

		if( ! $lr_session->is_loaded() ) {
			return false;
		}

		// Time between signings modified by number of days selected in global options
		$maxTimeBetweenSignings = 60 * 60 * 24 * variable_get('days_between_waiver', 365);

		if( $lr_session->is_player() ) {
			$time = $lr_session->attr_get('waiver_timestamp');
			if( is_null($time) || ((time() - $time) >= $maxTimeBetweenSignings)) {
				return url("person/signwaiver","next=$next");
			}

			$time = $lr_session->attr_get('dog_waiver_timestamp');
			if(($lr_session->attr_get('has_dog') =='Y')
				&& ( is_null($time) || ((time() - $time) >= $maxTimeBetweenSignings) )) {
				return url("person/signdogwaiver","next=$next");
			}

			if( variable_get('force_roster_request', 0) ) {
				foreach( $lr_session->user->teams as $team ) {
					if( $team->position == 'captain_request' ) {
						return url("team/request/{$team->id}/{$lr_session->user->user_id}","next=$next");
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if the logged-in user has permission for the current op
	 * Returns true/false indicating success/failure.
	 *
	 * This must be overridden by subclasses.
	 *
	 * @access public
	 * @return boolean 	Permission success/fail
	 */
	function has_permission()
	{
		global $lr_session;

		error_exit("Old permissions code somehow triggered.  You should contact dmo@dmo.ca if you see this error message");
	}

	/**
	 * Process this operation
	 *
	 * This must be overridden by the subclass.
	 *
	 * @access public
	 *
	 */
	function process ()
	{
		trigger_error("Missing handler for process() in this class");
		return false;
	}
}
?>
