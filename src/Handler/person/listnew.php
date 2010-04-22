<?php
/* List all new accounts */
class person_listnew extends Handler
{
	function has_permission ()
	{
		global $lr_session;
	 	return $lr_session->has_permission('person','listnew');
	}

	function process ()
	{
		$this->title = "New Accounts";
		$search = array(
			'status' => 'new',
			'_order' => 'p.lastname, p.firstname'
		);
		$ops = array(
			'view' => 'person/view/%d',
			'approve' => 'person/approve/%d',
			'delete' => 'person/delete/%d'
		);

		$sth = person_query( $search );

		$output = "<table>";
		while( $person = $sth->fetchObject('Person', array(LOAD_OBJECT_ONLY)) ) {
			$output .= '<tr><td>';
			$dup_sth = $person->find_duplicates();
			if( $dup_sth->fetch() ) {
				$output .= "<span class='error'>$person->lastname, $person->firstname</span>";
			} else {
				$output .= "$person->lastname, $person->firstname";
			}
			$output .= '</td><td>';
			while ( list($key, $value) = each($ops)) {
				$output .= '[&nbsp;' .l($key,sprintf($value, $person->user_id)) . '&nbsp;]';
				$output .= "&nbsp;";
			}
			reset($ops);
			$output .= "</td></tr>";
		}
		$output .= "</table>";


		$this->setLocation(array( $this->title => 'person/listnew' ));

		return $output;
	}
}

?>
