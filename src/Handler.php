<?php

# do not resort this list or things will break
module_register("Handler/login.php", 'login'); // TODO: also contains logout handler!
module_register("Handler/home.php", 'home'); // TODO: make this go away

module_register("Handler/Event.php",'event');
module_register("Handler/Registration.php",'registration');
module_register("Handler/Schedule.php", 'schedule');
module_register("Handler/GameSlot.php",'slot');
module_register("Handler/GoogleMaps.php",'gmaps');
module_register("Handler/API.php", 'api');

/**
 * This is the base class for all operation handlers used in the web UI.
 * 
 * It exports a method, get_page_handler() that is used as a factory to create
 * appropriate handler instances for the given operation.
 *
 * It also provides the Handler base class, which implements an API that
 * must be followed by each page handler subclass.
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
	 * The operation this handler deals with.  Used for generating
	 * self-referential links, form submission targets, etc.
	 */
	protected $op;

	/**
	 * Breadcrumbs.  Used for creating a trail of actions so that
	 * users can backtrack.
	 */
	public $breadcrumbs;

	function __construct ( )
	{
	}

	/**
	 * Initialize our data
	 * This is where stuff that shouldn't be inherited should go.
	 */
	function initialize ()
	{
		$this->breadcrumbs = array();
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

		// TODO: This belongs as a config option
		$maxTimeBetweenSignings = 60 * 60 * 24 * 365;

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

# DMO: Disable the survey.
#		if( $lr_session->attr_get('survey_completed') != 'Y' ) {
#			return url("person/survey","next=$next");
#		}

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
	 * Set any perms flags needed for a particular handler
	 * Should be overridden by subclass if needed.
	 *
	 * @param $type Type of flag to set.  Valid values are * 'administrator', 'coordinator', 'captain'
	 */
	function set_permission_flags($type = '')
	{
		return true;
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

	/** 
	 * Set both page title and breadcrumbs
	 * Array consists of several key-value pairs.  If there's a nonzero value,
	 * it should be a link component that can be passed to l().
	 */
	function setLocation( $ary ) 
	{
		$titleComponents = array();
		$this->breadcrumbs = array();
		while(list($key,$val) = each($ary)) {
			if($val) {
				$this->breadcrumbs[] = l($key,$val);
			} else {
				$this->breadcrumbs[] = $key;
			}
			array_unshift($titleComponents, $key);
		}
		$this->title = join(' &raquo; ', $titleComponents);
	}

	/**
	 * Generates list output.  Query should generate rows with two
	 * fields; one named 'id' containing the ID of the object listed,
	 * and 'value', containing a name or descriptive text for each
	 * object
	 */
	function generateSingleList($query, $ops, $dbParams = array())
	{
		global $dbh;
		$sth = $dbh->prepare( $query );
		$sth->execute( $dbParams );
		$output = "<table>\n";
		while($thisRow = $sth->fetch()) {
			$output .= "<tr><td>" . $thisRow['value'] . "</td><td>";
			$output .= theme_links( $this->generateOpsLinks($ops, $thisRow['id']));
			$output .= "</td></tr>\n";
		}
		$output .= "</table>";
		return $output;
	}

	function generateOpsLinks($opsList, $idValue)
	{
		$opsLinks = array();
		foreach($opsList as $op) {
			$opsLinks[] = l($op['name'], $op['target'] . $idValue);
		}
		return $opsLinks;
	}
}
?>
