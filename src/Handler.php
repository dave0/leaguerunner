<?php

# do not resort this list or things will break
require_once("Handler/Login.php");
require_once("Handler/Menu.php");
require_once("Handler/System.php");

require_once("Handler/Person.php");
require_once("Handler/Team.php");
require_once("Handler/League.php");
require_once("Handler/Field.php");
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
	var $_page_title;

	/**
	 * Instance of Smarty template file
	 * 
	 * @access private
	 * @var object Smarty
	 */
	var $tmpl;

	/**
	 * Text for error message, if any
	 *
	 * @access private
	 * @var string
	 */
	var $error_text;

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
		$this->tmpl = new Smarty;
		$this->_required_perms = null;
		$this->_permissions = array();
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
			$this->error_text = "You do not have permission to perform that operation";
			return false;
		}
		
		/* Now check particular items, in order */
		foreach($this->_required_perms as $perm_type) {
		
			if($perm_type == 'allow') {
				return true;
			} else if($perm_type == 'deny') {
				$this->error_text = "You do not have permission to perform that operation";
				return false;
			} else if($perm_type == 'require_valid_session') {
				if(!$session->is_valid()) {
					$this->error_text = "You do not have a valid session";
					return false;
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
					$this->error_text = "You do not have permission to perform that operation";
					return false;
				} else {
					$this->set_permission_flags('coordinator');
				}
			} else if($perm_type == 'coordinator_sufficient') {
				$id = var_from_getorpost('id');
				if($session->is_coordinator_of($id)) {
					$this->set_permission_flags('coordinator');
					return true;
				}
			} else if($perm_type == 'coordinate_league_containing:') {
				$id_field = substr($perm_type, 29);
				$id_data = var_from_getorpost($id_field);
				if($session->coordinates_league_containing($id_data)) {
					$this->set_permission_flags('coordinator');
					return true;
				}
			} else if($perm_type == 'coordinate_game:id') {
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
					$this->error_text = "Value missing for $wanted_var in URL";
					return false;
				}
			}
		}

		$this->error_text = "You do not have permission to perform that operation";
		return false;
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
	 * Set template variables for a template
	 *
	 * This sets any template variables that should be set for every 
	 * output page.  Things like app_name, cgi location, and page title
	 * should be set in here.  DO NOT set any handler-specific variables
	 * in here.
	 * 
	 * @access public
	 */
	function set_global_template_vars()
	{
		global $current_language, $session;

		$this->tmpl->assign("app_name", $GLOBALS['APP_NAME']);
		$this->tmpl->assign("app_cgi_location", $_SERVER['PHP_SELF']);
		$this->tmpl->assign("app_graphics_dir", $GLOBALS['APP_DIR_GRAPHICS'] . "/$current_language");
		$this->tmpl->assign("page_title", $this->_page_title);
	
		if(isset($session) && $session->is_valid()) {
			$this->tmpl->assign("page_user_name", join(" ",array(
				$session->attr_get("firstname"),
				$session->attr_get("lastname")
			)));
		}
	}
	
	/**
	 * Set the page title
	 */
	function set_title( $title )
	{
		$this->_page_title = $title;
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
		global $current_language;

		$this->set_global_template_vars();
		register_smarty_extensions($this->tmpl);

		/*      
		 * The following line needs to be set to 'true' for development
		 * purposes.  It controls whether or not templates are checked for
		 * recompilation.  If you don't set this to 'true' when doing
		 * development, your changes to the templates will not be noticed!
		 */
		$this->tmpl->compile_check = true;
		$this->tmpl->template_dir  = "./templates/" . $current_language;
		$this->tmpl->display($this->tmplfile);
	}

	/**
	 * Output a redirect to ourself with the given bits appended
	 */
	function output_redirect ( $append )
	{
		Header(join("",array(
			"Location: ",
			$_SERVER['PHP_SELF'],
			"?",
			$append
		)));
		return true;
	}
	
	/**
	 * Display the error message.
	 *
	 * Generates an error message using the ErrorMessage.tmpl template.
	 * Caller should fill in $this->error_text before calling.
	 *
	 * Note that we don't use display() to do this, but instead call the
	 * underlying template displaying stuff ourselves.
	 *
	 * @access public
	 */
	function display_error()
	{
		global $current_language;

		$this->tmpl = new Smarty;
		$this->set_template_file("ErrorMessage.tmpl");
		$this->name = "Error";
		
		if(is_null($this->error_text)) {
			$this->error_text = "Unknown Error";
		}
		
		$this->tmpl->assign("error_message", 
			$this->error_text);
			
		$this->set_global_template_vars();
		
		$this->tmpl->template_dir  = "./templates/" . $current_language;
		$this->tmpl->display($this->tmplfile);
	}

	/** 
	 * Set the template file to be used
	 *
	 * This sets the path to the appropriate template file, relative to
	 * the template root.  
	 *
	 * @access public
	 * @param string $template_file  Filename of the template file to use.  Should be relative to the language directories.
	 *
	 */
	function set_template_file( $template_file )
	{
		$this->tmplfile = $template_file;
	}

	/**
	 * Check for a database error
	 */
	function is_database_error( &$res ) 
	{
		global $DB;
		if(DB::isError($res)) {
			$this->error_text = $res->getMessage();
			$this->error_text .= ": " . $res->getUserinfo();
			return true;
		}
		
		return false;
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
	 * Helper fn to fetch 'allowed' values for a set or enum from a MySQL
	 * database.
	 */
	function get_enum_options ( $table, $col_name) 
	{
		global $DB;
		
		$row = $DB->getRow("SHOW COLUMNS from $table LIKE ?",
			array($col_name),
			DB_FETCHMODE_ASSOC);
			
		if($this->is_database_error($row)) {
			return false;
		}

		$str = preg_replace("/^(enum|set)\(/","",$row['Type']);
		$str = str_replace(")","",$str);
		$str = str_replace("'","",$str);
		$ary = preg_split("/,/",$str);
		
		return array_map("map_array_callback", $ary);
	}

	/** 
	 * Helper fn to generate an option-listable sequence of numbers
	 */
	function get_numeric_options ( $start, $finish )
	{
		/* Yuck */
		$foo = array();
		for($i = $start; $i <= $finish; $i++) {
			$foo[] = $i;
		}
		return array_map("map_array_callback", $foo);
	}

}

function map_array_callback($a) {
   	return array ( 'value' => $a, 'output' => $a);
}

?>
