<?php

# do not resort this list or things will break
require_once("Handler/Login.php");
require_once("Handler/Logout.php");
require_once("Handler/Menu.php");
require_once("Handler/NotFound.php");

require_once("Handler/Person.php");
require_once("Handler/Team.php");

require_once("lib/smarty_extensions.php");

/**
 * This is the base class for all operation handlers used in the web UI.
 * 
 * It exports a method, get_page_handler() that is used as a factory to create
 * appropriate handler instances for the given operation.
 *
 * It also provides the Handler base class, which implements an API that
 * must be followed by each page handler subclass.
 *
 * @package 	Leaguerunner
 * @version		$Id$
 * @author		Dave O'Neill <dmo@acm.org>
 * @access		public
 * @copyright	Dave O'Neill <dmo@acm.org>; GPL.
 */
class Handler 
{
	/**
	 * The page name to display.
	 * 
	 * @access private
	 * @var string
	 */
	var $name;

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
	 * Constructor.  This is called by every handler.
	 * Data that should be initialized for the subclass goes in here.
	 */
	function Handler ()
	{
		$this->tmpl = new Smarty;
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
	 *
	 * This checks whether or not the user has authorization to perform
	 * the given operation.  Returns true/false indicating success/failure
	 * This MUST be overridden by the subclass.
	 * 
	 * @access public
	 * @return boolean 	Permission success/fail
	 */
	function has_permission() 
	{
		return false;
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
		$this->tmpl->assign("app_cgi_location", $GLOBALS['APP_CGI_LOCATION']);
		$this->tmpl->assign("app_graphics_dir", $GLOBALS['APP_DIR_GRAPHICS'] . "/$current_language");
		$this->tmpl->assign("app_stylesheet_file", $GLOBALS['APP_STYLESHEET']);
		$this->tmpl->assign("app_template_dir", $current_language);
		
		$this->tmpl->assign("page_title", $this->name);
	
		if(isset($session) && $session->is_valid()) {
			$this->tmpl->assign("page_user_name", join(" ",array(
				$session->attr_get("firstname"),
				$session->attr_get("lastname")
			)));
		}
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
		$this->set_global_template_vars();
		register_smarty_extensions($this->tmpl);
		/*      
		 * The following line needs to be set to 'true' for development
		 * purposes.  It controls whether or not templates are checked for
		 * recompilation.  If you don't set this to 'true' when doing
		 * development, your changes to the templates will not be noticed!
		 */

		$this->tmpl->compile_check = true;
		$this->tmpl->display($this->tmplfile);
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
		/* TODO: If we're going to use error codes, display them too. */
		
		$this->tmpl = new Smarty;
		$this->set_template_file("ErrorMessage.tmpl");
		$this->name = "Error";
		
		if(is_null($this->error_text)) {
			$this->error_text = "Unknown Error";
		}
		
		$this->tmpl->assign("error_message", 
			$this->error_text);
			
		$this->set_global_template_vars();
		$this->tmpl->display($this->tmplfile);
	}

	/**
	 * Perform page finalization
	 * 
	 * Deals with any final close-off that needs to be done for the 
	 * page.
	 * 
	 * @access public
	 */
	function end_page ()
	{
		/* TODO: */
	}

	/** 
	 * Set the template file to be used
	 *
	 * This sets the path to the appropriate template file, relative to
	 * the template root.  This also inserts the appropriate language
	 * directory into the pathname.
	 *
	 * @access public
	 * @param string $template_file  Filename of the template file to use.  Should be relative to the language directories.
	 *
	 */
	function set_template_file( $template_file )
	{
		global $current_language;
		$this->tmplfile = $current_language . "/" . $template_file;
	}
}

?>
