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
		if( $lr_session->has_permission('person','invalidateemail') ) {
			$this->ops['invalidate email'] = 'person/invalidemail';
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
		global $lr_session;

		$this->template_name = 'pages/person/search.tpl';

		$query = array();

		if( $_GET['lastname'] ) {
			$query['lastname_wildcard'] = $_GET['lastname'];
		} elseif ( $lr_session->has_permission('person','search','email') && $_GET['email']) {
			$query['email'] = $_GET['email'];
		} elseif ( $lr_session->has_permission('person','search','member_id') && $_GET['member_id']) {
			$query['member_id'] = $_GET['member_id'];
		}

		if( ! count($query) ) {
			// no search yet...
			return true;
		}

		$query['_order'] = 'p.lastname, p.firstname';

		if( strlen($this->extra_where) ) {
			$query['_extra'] = $this->extra_where;
		}

		$this->smarty->assign('lastname', $_GET['lastname']);
		$this->smarty->assign('email', $_GET['email']);
		$this->smarty->assign('member_id', $_GET['member_id']);

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
