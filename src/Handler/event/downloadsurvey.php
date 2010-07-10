<?php
require_once('Handler/EventHandler.php');
class event_downloadsurvey extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ( )
	{
		global $dbh;

		$data = array('Order ID');
		foreach ($this->formbuilder->_questions as $question) {
			$data[] = $question->qkey;
		}

		if( empty( $data ) ) {
			return para( 'No details available for download.' );
		}

		header('Content-type: text/x-csv');
		header("Content-Disposition: attachment; filename=\"{$this->event->name}_survey.csv\"");

		$out = fopen('php://output', 'w');
		fputcsv($out, $data);

		$sth = $dbh->prepare('SELECT order_id FROM registrations r WHERE r.registration_id = ?  ORDER BY order_id');
		$sth->execute( array($this->event->registration_id) );

		while($row = $sth->fetch() ) {
			$data = array(
				sprintf(variable_get('order_id_format', '%d'), $row['order_id']),
			);

			// Add all of the answers
			$fsth = $dbh->prepare('SELECT akey FROM registration_answers WHERE order_id = ?  AND qkey = ?');
			foreach ($this->formbuilder->_questions as $question) {
				$fsth->execute( array( $row['order_id'], $question->qkey));
				$data[] = $fsth->fetchColumn();
			}

			fputcsv($out, $data);
		}

		fclose($out);

		exit;
	}
}
?>
