<?php

# do not resort this list or things will break
require_once("Handler/Login.php");
require_once("Handler/Menu.php");
require_once("Handler/System.php");

require_once("Handler/Person.php");
require_once("Handler/Team.php");
require_once("Handler/League.php");
require_once("Handler/Field.php");
require_once("Handler/Site.php");
require_once("Handler/Game.php");

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
	 * The page name to display.
	 * 
	 * @access private
	 * @var string
	 */
	var $title;

	/**
	 * The operation this handler deals with
	 */
	var $op;

	/**
	 * Breadcrumbs
	 */
	var $breadcrumbs;

	/**
	 * Instance of Smarty template file
	 * 
	 * @access private
	 * @var object Smarty
	 */
	var $tmpl;

	/**
	 * Things to check for general access permission
	 */
	var $_required_perms;
	
	/**
	 * Permissions bits for various items of interest
	 * @access private
	 * @var array
	 */
	var $_permissions;

	/**
	 * Constructor.  This is called by every handler.
	 * Data that should be initialized for the subclass goes in here.
	 */
	function Handler ()
	{
		global $session;
		$this->tmpl = new Smarty;
		$this->_required_perms = null;
		$this->_permissions = array();
		if($session->is_valid()) {
			$this->breadcrumbs = array(
				array('name' => "<b>" 
					. $session->attr_get("firstname") 
					. " "
					. $session->attr_get("lastname")
					. "</b>"),
				array('name' => "Main Menu",
					  'target' => "op=menu")
			);
		}
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
	 * Check if the logged-in user has permission for the current op
	 * Returns true/false indicating success/failure.
	 * 
	 * @access public
	 * @return boolean 	Permission success/fail
	 */
	function has_permission() 
	{
		global $session, $DB;
		
		if(is_null($this->_required_perms)) {
			$this->error_exit("You do not have permission to perform that operation");
		}
		
		/* Now check particular items, in order */
		foreach($this->_required_perms as $perm_type) {
		
			if($perm_type == 'allow') {
				return true;
			} else if($perm_type == 'deny') {
				$this->error_exit("You do not have permission to perform that operation");
			} else if($perm_type == 'require_valid_session') {
				if(!$session->is_valid()) {
					$this->error_exit("You do not have a valid session");
				}
			} else if($perm_type == 'admin_sufficient') {
				if($session->is_admin()) {
					$this->set_permission_flags('administrator');
					return true;
				}
			} else if($perm_type == 'volunteer_sufficient') {
				if($session->attr_get('class') == 'volunteer') {
					$this->set_permission_flags('volunteer');
					return true;
				}
			} else if($perm_type == 'self_sufficient') {
				$id = var_from_getorpost('id');
				if($session->attr_get('user_id') == $id) {
					$this->set_permission_flags('self');
					return true;
				}
			} else if($perm_type == 'require_coordinator') {
				$id = var_from_getorpost('id');
				if(!$session->is_coordinator_of($id)) {
					$this->error_exit("You do not have permission to perform that operation");
				} else {
					$this->set_permission_flags('coordinator');
				}
			} else if($perm_type == 'coordinator_sufficient') {
				$id = var_from_getorpost('id');
				if($session->is_coordinator_of($id)) {
					$this->set_permission_flags('coordinator');
					return true;
				}
			} else if(strncmp($perm_type,'coordinate_league_containing:',28) == 0) {
				$id_field = substr($perm_type, 29);
				$id_data = var_from_getorpost($id_field);
				if($session->coordinates_league_containing($id_data)) {
					$this->set_permission_flags('coordinator');
					return true;
				}
			} else if(strncmp($perm_type,'coordinate_game:',15) == 0) {
				$id_field = substr($perm_type, 16);
				$id_data = var_from_getorpost($id_field);
				$league_id = $DB->getOne("SELECT league_id FROM schedule WHERE game_id = ?", array($id_data));
				if($session->is_coordinator_of($league_id)) {
					$this->set_permission_flags('coordinator');
					return true;
				}
			} else if(strncmp($perm_type,'captain_of:',10) == 0) {
				$id_field = substr($perm_type, 11);
				$id_data = var_from_getorpost($id_field);
				if($session->is_captain_of($id_data)) {
					$this->set_permission_flags('captain');
					return true;
				}
			} else if(strncmp($perm_type,'require_var:',11) == 0) {
				$wanted_var = substr($perm_type, 12);
				$got_var = var_from_getorpost($wanted_var);
				if(is_null($got_var)) {
					$this->error_exit("Value missing for $wanted_var in URL");
				}
			}
		}

		$this->error_exit("You do not have permission to perform that operation");
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
	 * Set the page title
	 */
	function set_title( $title )
	{
		$this->title = $title;
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
		register_smarty_extensions($this->tmpl);

		$this->tmpl->assign("app_cgi_location", $_SERVER['PHP_SELF']);
		$this->tmpl->assign("page_title", $this->title);
	
		/*      
		 * The following line needs to be set to 'true' for development
		 * purposes.  It controls whether or not templates are checked for
		 * recompilation.  If you don't set this to 'true' when doing
		 * development, your changes to the templates will not be noticed!
		 */
		$this->tmpl->compile_check = true;
		$this->tmpl->template_dir  = "./templates/en_CA";
		$this->get_header();
		$this->tmpl->display($this->tmplfile);
		$this->get_footer();
	}

	function get_header($title = NULL)
	{
		$title = $title ? $title : $this->title;
		if($this->breadcrumbs[count($this->breadcrumbs)-1]['name'] != $title) {
			$this->breadcrumbs[] = array('name' => $title);
		}
		return theme_header($title, $this->breadcrumbs);	
	}

	function get_footer()
	{
		return theme_footer();
	}

	/**
	 * Display the error message and exit.
	 *
	 * Generates an error message page with the given error.
	 *
	 * @access public
	 */
	function error_exit($error = NULL)
	{
		global $DB;
		$title = $this->title ? $this->title : "Error";
		$error = $error ? $error : "An unknown error has occurred.";

		print $this->get_header($title);
		print h1($title, array('align' => 'left'));
		print simple_tag("blockquote",$error);
		print $this->get_footer();
		$DB->disconnect();
		exit;
	}

	/** 
	 * TODO Delete me after Smarty removal
	 */
	function set_template_file( $template_file )
	{
		$this->tmplfile = $template_file;
	}

	/**
	 * Check for a database error
	 * TODO: Delete from Handler.php when cleaned up
	 */
	function is_database_error( &$res ) 
	{
		$err = isDatabaseError( $res );
		if($err == false) {
			return false;
		}
		$this->error_exit($err);
	}

	/**
	 * Helper fn to turn on all permissions
	 */
	function enable_all_perms()
	{
		reset($this->_permissions);
		while(list($key,) = each($this->_permissions)) {
			$this->_permissions[$key] = true;
		}
		reset($this->_permissions);
	}
	
	/**
	 * Generates list output.  Query should generate rows with two
	 * fields; one named 'id' containing the ID of the object listed,
	 * and 'value', containing a name or descriptive text for each
	 * object
	 */
	function generateSingleList($preparedQuery, $ops, $dbParams = array())
	{
		global $DB;
		$result = $DB->execute($preparedQuery, $dbParams);
		if($this->is_database_error($result)) {
			return false;
		}
		$output = "<table border='0'>";
		while($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
			$opsLinks = $this->generateOpsLinks($ops, $row['id']);
			$output .= tr( td($row['value']) . td(theme_links($opsLinks)) );
		}
		$output .= "</table>";
		return $output;
	}

	/**
	 * Generate a list, similar to generateSingleList, but separated into
	 * pages based on the first letter of a given field.
	 */
	function generateAlphaList($query, $ops, $letterField, $fromWhere, $listOp, $letter = null, $dbParams = array())
	{
		global $DB;
		$letters = $DB->getCol("select distinct UPPER(SUBSTRING($letterField,1,1)) as letter from $fromWhere ORDER BY letter asc");
		if($this->is_database_error($letters)) {
			return false;
		}
		if(!isset($letter)) {
			$letter = $letters[0];
		}
		$output = "<p>";
		foreach($letters as $curLetter) {
			if($curLetter == $letter) {
				$output .= $curLetter;
			} else {
				$output .= l($curLetter, "op=$listOp&letter=$curLetter");
			}
			$output .= "&nbsp;";
		}
		$output .= "</p>\n";
		$dbParams[] = "$letter%";
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
			$opsLinks[] = l($op['name'], $op['target'] . "&id=$idValue");			
		}
		return $opsLinks;
	}
}
?>
