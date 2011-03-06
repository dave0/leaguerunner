<?php
require_once('Handler/LeagueHandler.php');
class league_spirit extends LeagueHandler
{
	function has_permission ()
	{
		global $lr_session;
		return $lr_session->has_permission('league','view', $this->league->league_id, 'spirit');
	}

	function process ()
	{
		$this->title = "{$this->league->fullname} Spirit";
		$this->template_name = 'pages/league/spirit.tpl';

		$s = new Spirit;
		$s->entry_type           = $this->league->enter_sotg;
		$s->display_numeric_sotg = $this->league->display_numeric_sotg();

		$this->smarty->assign('question_headings', $s->question_headings() );
		$this->smarty->assign('spirit_summary', $s->league_sotg( $this->league ) );
		$this->smarty->assign('spirit_avg',     $s->league_sotg_averages( $this->league ) );
		$this->smarty->assign('spirit_dev',     $s->league_sotg_std_dev( $this->league ) );

		return true;
	}
}

?>
