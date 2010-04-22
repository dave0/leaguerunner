<?php
class person_signwaiver extends Handler
{
	protected $formFile;
	protected $querystring;

	function checkPrereqs ( $op )
	{
		return false;
	}

	function initialize ()
	{
		$this->title = 'Consent Form for League Play';
		$this->formFile = 'waiver_form.html';
		$this->querystring = 'UPDATE person SET waiver_signed=NOW() where user_id = ?';

		return true;
	}

	function has_permission()
	{
		global $lr_session;
		if (variable_get('registration', 0) && variable_get('allow_tentative', 0)) {
			return ($lr_session->is_loaded());
		} else {
			return ($lr_session->is_valid());
		}
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$next = $_POST['next'];

		if(is_null($next)) {
			$next = $_GET['next'];
			if(is_null($next)) {
				$next = queryPickle("menu");
			}
		}

		switch($edit['step']) {
			case 'perform':
				$this->perform( $edit );
				local_redirect( queryUnpickle($next) );
			default:
				$rc = $this->generateForm( $next );
		}

		$this->setLocation( array($this->title => 0 ));

		return $rc;
	}

	/**
	 * Process input from the waiver form.
	 *
	 * User will not be permitted to log in if they have not signed the
	 * waiver.
	 */
	function perform( $edit = array() )
	{
		global $lr_session, $dbh;

		if('yes' != $edit['signed']) {
			error_exit("Sorry, your account may only be activated by agreeing to the waiver.");
		}

		/* otherwise, it's yes.  Perform the appropriate query to mark the
		 * waiver as signed.
		 */
		$sth = $dbh->prepare( $this->querystring );
		$sth->execute(array($lr_session->attr_get('user_id')));
		return (1 == $sth->rowCount() );
	}

	function generateForm( $next )
	{
		global $CONFIG;

		$output = form_hidden('next', $next);
		$output .= form_hidden('edit[step]', 'perform');

		ob_start();
		$retval = @readfile( trim($CONFIG['paths']['file_path'], '/') . "/data/{$this->formFile}");
		if (false !== $retval) {
			$output .= ob_get_contents();
		}
		ob_end_clean();

		$output .= para(form_submit('submit') . form_reset('reset'));

		return form($output);
	}
}

?>
