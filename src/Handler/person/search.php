<?php
class person_search extends Handler
{
	function initialize ()
	{
		global $lr_session;
		$this->ops = array(
			'view' => 'person/view'
		);

		$this->title = "Player Search";

		$this->extra_where = '';
		if( $lr_session->has_permission('person','delete') ) {
			$this->ops['delete'] = 'person/delete';
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
		$search = &$_GET['search'];

		$this->template_name = 'pages/person/search.tpl';

		if( ! $search || $search == '' ) {
			// no search yet...
			return true;
		}

		$query = array(
			'lastname_wildcard' => $search,
			'_order' => 'p.lastname, p.firstname',
		);
		if( strlen($this->extra_where) ) {
			$query['_extra'] = $this->extra_where;
		}

		$this->smarty->assign('lastname', $search);

		$result = Person::query( $query );
		$result->setFetchMode(PDO::FETCH_CLASS, 'Person', array(LOAD_OBJECT_ONLY));

		$people = array();
		$hits = 0;
		while($person = $result->fetch() ) {
			if( ++$hits > 1000 ) {
				error_exit("Too many search results; query terminated");
			}
			$people[] = $person;
		}
		$this->smarty->assign('people', $people);

		$this->smarty->assign('ops', $this->ops);

		return true;
	}
}

?>
