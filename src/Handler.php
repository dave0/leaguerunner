<?php

# do not resort this list or things will break
module_register("Handler/Login.php", 'login'); // TODO: also contains logout handler!
module_register("Handler/Menu.php", 'home'); // TODO: make this go away

module_register("Handler/Settings.php", 'settings');
module_register("Handler/Statistics.php", 'statistics');
module_register("Handler/Person.php", 'person');
module_register("Handler/Team.php", 'team');
module_register("Handler/League.php", 'league');
module_register("Handler/Schedule.php", 'schedule');
module_register("Handler/Field.php", 'field');
module_register("Handler/Ward.php", 'ward');
module_register("Handler/Game.php",'game');
module_register("Handler/GameSlot.php",'slot');
module_register("Handler/Cron.php",'cron');
module_register("Handler/Season.php",'season');
module_register("Handler/Graph.php",'graph');

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
	var $title;

	/**
	 * The operation this handler deals with.  Used for generating
	 * self-referential links, form submission targets, etc.
	 */
	var $op;

	/**
	 * Breadcrumbs.  Used for creating a trail of actions so that
	 * users can backtrack.
	 */
	var $breadcrumbs;

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
	 * Right now, the main Handler class just needs to check that the acount
	 * is active and the waiver has been signed as appropriate.
	 *
	 * This should be overridden by subclass when performing these checks is
	 * not appropriate (Login/Logout, PersonCreate, etc)
	 */
	function checkPrereqs ( $next )
	{
		global $session;

		if( ! $session->is_loaded() ) {
			return false;
		}
		
		// TODO: This belongs as a config option
		$maxTimeBetweenSignings = 60 * 60 * 24 * 365;

		if( $session->is_player() ) {
			$time = $session->attr_get('waiver_timestamp');
			if( is_null($time) || ((time() - $time) >= $maxTimeBetweenSignings)) {
				return url("person/signwaiver","next=$next");
			}

			$time = $session->attr_get('dog_waiver_timestamp');
			if(($session->attr_get('has_dog') =='Y') 
				&& ( is_null($time) || ((time() - $time) >= $maxTimeBetweenSignings) )) {
				return url("person/signdogwaiver","next=$next");
			}
		}

# DMO: Disable the survey.
#		if( $session->attr_get('survey_completed') != 'Y' ) {
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
		global $session;

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
	 * display the template filled in for this op.
	 *
	 * This displays the HTML output for this operation.  Normally,
	 * this base function gets called to display the contents of the
	 * template as filled by the process() method.
	 *
	 * Individual subclasses can override it as necessary if they need custom
	 * output.
	 * 
	 * @access public
	 * @see process()
	 */
	function display ()
	{
		return true;
	}

	/**
	 * Generates list output.  Query should generate rows with two
	 * fields; one named 'id' containing the ID of the object listed,
	 * and 'value', containing a name or descriptive text for each
	 * object
	 */
	function generateSingleList($query, $ops, $dbParams = array())
	{
		
		$result = db_query($query, $dbParams);
		$rows = array();
		while($thisRow = db_fetch_array($result)) {
			$rows[] = array (
				$thisRow['value'],
				theme_links( $this->generateOpsLinks($ops, $thisRow['id'])));
		}
		return table(null, &$rows);
	}

	/**
	 * Generate a list, similar to generateSingleList, but separated into
	 * pages based on the first letter of a given field.
	 */
	function generateAlphaList($query, $ops, $letterField, $fromWhere, $listOp, $letter = null, $dbParams = array(), $query_append = '')
	{

		$letters = array();
		$letterQuery = db_query("select distinct UPPER(SUBSTRING($letterField,1,1)) as letter from $fromWhere ORDER BY letter asc");
		while($l = db_fetch_object($letterQuery)) {
			$letters[] = $l->letter;
		}
		if(!isset($letter)) {
			$letter = $letters[0];
		}

		$letterLinks = array();
		foreach($letters as $curLetter) {
			if($curLetter == $letter) {
				$letterLinks[] = "<b>$curLetter</b>";
			} else {
				$letterLinks[] = l($curLetter, url("$listOp", "letter=$curLetter$query_append"));
			}
		}
		$output = para(theme_links($letterLinks, "&nbsp;&nbsp;"));
		$dbParams[] = $letter;
		$output .= $this->generateSingleList($query, $ops, $dbParams);
		return $output;
	
		
		if(!isset($letter)) {
			$letter = $letters[0];
		}
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
