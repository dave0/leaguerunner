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
		$this->waiver_text = 'pages/person/consent_waiver.tpl';
		$this->querystring = 'UPDATE person SET waiver_signed=NOW() where user_id = ?';

		return true;
	}

	function has_permission()
	{
		global $lr_session;
		return ($lr_session->is_valid());
	}

	function process ()
	{
		$edit = $_POST['edit'];
		$next = $_POST['next'];

		if(is_null($next)) {
			$next = $_GET['next'];
			if(is_null($next)) {
				$next = queryPickle('home');
			}
		}

		switch($edit['step']) {
			case 'perform':
				$this->perform( $edit );
				local_redirect( queryUnpickle($next) );
			default:
				$this->template_name = 'pages/person/waiver.tpl';
				$this->smarty->assign('next_page', $next);
				$this->smarty->assign('waiver_text', $this->waiver_text);
				$rc = true;
		}

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
}

?>
