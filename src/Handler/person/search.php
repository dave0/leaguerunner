<?php
class person_search extends Handler
{
	function initialize ()
	{
		global $lr_session;
		$this->ops = array(
			'view' => 'person/view/%d'
		);

		$this->title = "Player Search";

		$this->extra_where = '';
		if( $lr_session->has_permission('person','delete') ) {
			$this->ops['delete'] = 'person/delete/%d';
		}
		return true;
	}

	function has_permission ()
	{
		global $lr_session;
	 	return $lr_session->has_permission('person','search');
	}

	function process ()
	{
		$edit = &$_POST['edit'];

		$this->max_results = variable_get('items_per_page', 25);

		switch($edit['step']) {
			case 'perform':
				$rc = $this->perform( $edit );
				break;
			default:
				$rc = $this->form();
		}
		$this->setLocation( array($this->title => 0 ));
		return $rc;
	}

	function form ( $data = '' ) 
	{

		$output = para("Enter last name of person to search for and click 'submit'.  You may use '*' as a wildcard");

		$output .= form_hidden('edit[step]', 'perform');
		$output .= "<div class='form-item'><label>Last Name: </label><input type='textfield' size='25' name = 'edit[lastname]' value='$data' />";
		$output .= "<input type='submit' value='search' /></div>";
		return form($output);
	}

	function perform ( &$edit )
	{
		global $lr_session;

		if( $edit['lastname'] == '' ) {
			error_exit("You must provide a last name");
		}

		$offset = $edit['offset'];
		if( !$offset ) {
			$limit = $this->max_results + 1;
		} else {
			$limit = "$offset," . ($offset + $this->max_results + 1);
		}

		$search = array(
			'lastname_wildcard' => $edit['lastname'],
			'_order' => 'p.lastname, p.firstname',
			'_limit' => $limit
		);
		if( strlen($this->extra_where) ) {
			$search['_extra'] = $this->extra_where;
		}

		$result = person_query( $search );

		$output = $this->form( $edit['lastname' ]);

		$output .= "<table><tr><td>";

		if( $offset > 0 ) {
			$output .= form( 
				form_hidden("edit[step]",'perform')
				. form_hidden('edit[lastname]', $edit['lastname'])
				. form_hidden('edit[offset]', $offset - $this->max_results )
				. form_submit("Prev")
			);
		}

		$count = 0;
		$people = '';
		$result->setFetchMode(PDO::FETCH_CLASS, 'Person', array(LOAD_OBJECT_ONLY));
		while($person = $result->fetch() ) {
			if(++$count > $this->max_results) {
				break;
			}
			$people .= "<tr><td>$person->lastname, $person->firstname</td>";
			while ( list($key, $value) = each($this->ops)) {
				$people .= '<td>' .l($key,sprintf($value, $person->user_id)) . "</td>";
			}
			reset($this->ops);
			$people .= "</tr>";
		}

		$output .= "</td><td align='right'>";

		if( $count > $this->max_results ) {
			$output .= form( 
				form_hidden("edit[step]",'perform')
				. form_hidden('edit[lastname]', $edit['lastname'])
				. form_hidden('edit[offset]', $edit['offset'] + $this->max_results )
				. form_submit("Next")
			);
		}
		$output .= "</td></tr>$people</table>";

		return $output;
	}
}

?>
