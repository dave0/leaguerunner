<?php

require_once('Handler/person/signwaiver.php');

class person_signdogwaiver extends person_signwaiver
{
	function initialize ()
	{
		$this->title = 'Consent Form For Dog Owners';
		$this->waiver_text = 'pages/person/dog_waiver.tpl';
		$this->querystring = 'UPDATE person SET dog_waiver_signed=NOW() where user_id = ?';
		return true;
	}
}

?>
