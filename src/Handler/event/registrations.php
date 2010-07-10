<?php
require_once('Handler/EventHandler.php');

class event_registrations extends EventHandler
{
	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','download');
	}

	function process ( )
	{
		global $dbh;

		$id = $this->event->registration_id;

		$this->title = $this->event->name;
		$this->template_name = 'pages/event/registrations.tpl';

		$this->smarty->assign('event', $this->event);
		$this->smarty->assign('gender_counts', $this->event->get_gender_stats());
		$this->smarty->assign('payment_counts', $this->event->get_payment_stats());

		if( $this->formbuilder ) {
			// TODO: move fetching of answer summary to classes/event.inc
			$survey_questions = array();
			foreach ($this->formbuilder->_questions as $question) {
				// We don't want to see text answers here, they won't group
				// well
				if ($question->qtype == 'multiplechoice' ) {
					$sth = $dbh->prepare('SELECT
							akey,
							COUNT(registration_answers.order_id)
						FROM registration_answers
							LEFT JOIN registrations ON registration_answers.order_id = registrations.order_id
						WHERE registration_id = ?
							AND qkey = ?
							AND payment != "Refunded"
						GROUP BY akey
						ORDER BY akey');
					$sth->execute( array( $this->event->registration_id, $question->qkey) );

					$question_counts = array();
					while($row = $sth->fetch(PDO::FETCH_NUM)) {
						$question_counts[ $row[0] ] = $row[1];
					}

					$questions[$question->qkey] = $question_counts;
				}
			}

			$this->smarty->assign('survey_questions', $questions);
		}

		$this->smarty->assign('order_id_format', variable_get('order_id_format', '%d'));
		$this->smarty->assign('registrations', $this->event->get_registrations());

		return true;
	}
}
?>
