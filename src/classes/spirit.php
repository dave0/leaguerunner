<?php
class Spirit
{
	var $questions = array(
		'timeliness'      => array(
			'heading'  => 'Timeliness',
			'out_of'   => 1,
			'question' => "Our opponents' timeliness",
			'type'     => 'multiplechoice',
			'answers'  => array(
				'met expectations'          => 1,
				'did not meet expectations' => 0,
			),
			'use_for'  => 'survey_only',
		),
		'rules_knowledge' => array(
			'heading'  => 'Rules',
			'out_of'   => 3,
			'question' => "Our opponents' rules knowledge was",
			'type'     => 'multiplechoice',
			'answers'  => array(
				'exceptional'   => 3,
				'good'          => 2,
				'below average' => 1,
				'bad'           => 0
			),
			'use_for'  => 'survey_only',
		),
		'sportsmanship'   => array(
			'heading'  => 'Sportsmanship',
			'out_of'   => 3,
			'question' => "Our opponents' sportsmanship was",
			'type'     => 'multiplechoice',
			'answers'  => array(
				'exceptional'   => 3,
				'good'          => 2,
				'below average' => 1,
				'poor'          => 0
			),
			'use_for'  => 'survey_only',
		),
		'rating_overall'  => array(
			'heading'  => 'Overall',
			'out_of'   => 3,
			'question' => "Ignoring the score and based on the opponents' spirit of the game, what was your overall assessment of the game?",
			'type'     => 'multiplechoice',
			'answers'  => array(
				'This was an exceptionally great game' => 3,
				'This was an enjoyable game'           => 2,
				'This was a mediocre game'             => 1,
				'This was a very bad game'             => 0
			),
			'use_for'  => 'survey_only',
		),
		'entered_sotg' => array(
			'heading'  => 'Spirit Rating',
			'question' => 'Spirit rating (1 to 10)',
			'type'     => 'textfield',
			'use_for'  => 'numeric_only',
		),
		'score_entry_penalty' => array(
			'heading'  => 'Score Submitted?',
			'out_of'   => -3,
			'question' => 'Penalty for failure to submit score',
			'type'     => 'hidden',
			'answers'  => array(
				'Score was entered on time'     => 0,
				'Score was not entered on time' => -3,
			),
		),
		'comments'  => array(
			'heading'  => 'Comments',
			'question' => "Do you have any comments on this game?",
			'type'     => 'freetext',
		),
	);

	// NOTE: must leave array keys in ' ' if we want to have non-integer values.
	var $icons_by_max_value = array(
		'-3' => array(
			'-3'     => 'not_ok.png',
			'-1.5' => 'caution.png',
			'-0.75' => 'ok.png',
			0     => 'perfect.png',
		),
		1 => array(
			0     => 'not_ok.png',
			'0.5' => 'caution.png',
			'0.75' => 'ok.png',
			1     => 'perfect.png',
		),
		3 => array(
			0 => 'not_ok.png',
			1 => 'caution.png',
			2 => 'ok.png',
			3 => 'perfect.png',
		),
		10 => array(
			0 => 'not_ok.png',
			4 => 'caution.png',
			6 => 'ok.png',
			9 => 'perfect.png',
		),
	);

	/*
	 * Whether or not to display
	 */
	var $display_numeric_sotg = 0;

	/*
	 * How to enter spirit (numeric or survey)
	 */
	var $entry_type           = 'survey_only';

	function question_headings ( )
	{
		$headings = array();
		while( list($qkey, $qdata) = each($this->questions) ) {
			if( array_key_exists('use_for', $qdata) && $qdata['use_for'] != $this->entry_type ) {
				continue;
			}
			if( array_key_exists('out_of', $qdata)  ) {
				$headings[] = $qdata['heading'];
			}
		}
		reset($this->questions);
		return $headings;
	}

	function question_keys ( )
	{
		$keys = array();
		while( list($qkey, $qdata) = each($this->questions) ) {
			if( array_key_exists('use_for', $qdata) && $qdata['use_for'] != $this->entry_type ) {
				continue;
			}
			if(  $qdata['type'] == 'multiplechoice' ) {
				$keys[] = $qkey;
			}
		}
		reset($this->questions);
		return $keys;
	}

	function question_maximums ( )
	{
		$out_of = array();
		while( list($qkey, $qdata) = each($this->questions) ) {
			if( array_key_exists('use_for', $qdata) && $qdata['use_for'] != $this->entry_type ) {
				continue;
			}
			if( $qdata['out_of'] ) {
				$out_of[$qkey] = $qdata['out_of'];
			}
		}
		reset($this->questions);
		return $out_of;
	}

	function question_total ( )
	{
		$sum = 0;
		while( list($qkey, $qdata) = each($this->questions) ) {
			if( array_key_exists('use_for', $qdata) && $qdata['use_for'] != $this->entry_type ) {
				continue;
			}
			if( $qdata['out_of'] && !$qdata['type'] != 'hidden') {
				$sum += $qdata['out_of'];
			}
		}
		reset($this->questions);
		return $sum;
	}

	/**
	 * Drop the highest and lowest SOTG score if specified, and return the average SOTG.
	 */
	function average_sotg ( $scores , $drop = false ) {
		// $scores is an array of SOTG scores
		if (sizeof( $scores ) == 0) {
			return 0;
		}
		$high  = 0;
		$low   = 10;
		$total = 0;
		$count = 0;

		foreach ($scores as $value) {
			if( is_null($value) ) {
				continue;
			}

			if ($value > $high) {
				$high = $value;
			}
			if ($value < $low) {
				$low = $value;
			}
			$total += $value;
			$count++;
		}
		// can only drop highest and lowest if the count is 3 or more
		if ($count >= 3 && $drop) {
			$total = $total - $high - $low;
			$count = $count - 2;
		}

		return $total / $count;
	}

	/*
	 * 0-3.99 = X
	 * 4.00 - 5.99 = Caution
	 * 6.00 - 8.99 = Check
	 * 9.00+ = Star
	 */
	function full_spirit_symbol_html ( $score )
	{
		return $this->question_spirit_symbol_html( $score, 10);
	}

	function question_spirit_symbol_html ( $answer_value, $max_value = 3 )
	{
		global $CONFIG;
		$icon_url = $CONFIG['paths']['file_url'] . '/image/icons';

		$by_value = $this->icons_by_max_value[$max_value];

		ksort( $by_value, SORT_NUMERIC );
		$found = 'not_ok.png';
		foreach(array_keys($by_value) as $num) {
			if( $num > $answer_value) {
				break;
			}
			$found = $by_value[$num];
		}

		$output = "<img src='$icon_url/$found' />";
		if( $this->display_numeric_sotg ) {
			return sprintf("%s (%.2f)", $output, $answer_value);
		}
		return $output;
	}

	/**
	 * Force the spirit form into a FormBuilder object so it can be rendered
	 */
	function as_formbuilder ( )
	{
		$fb = new FormBuilder;
		$fb->_name = 'spirit';
		$fb->_answers = array();
		$fb->_answer_validity = 'false';
		$fb->_questions = array();
		$sorder = 0;
		while( list($qkey, $qdata) = each($this->questions) ) {

			if( array_key_exists('use_for', $qdata) && $qdata['use_for'] != $this->entry_type ) {
				continue;
			}

			$new_q = array(
				'qkey' => $qkey,
				'qtype' => $qdata['type'],
				'question' => $qdata['question'],
				'sorder' => $sorder++,
			);
			switch( $qdata['type'] ) {
				case 'multiplechoice':
					$new_q['answers'] = array();
					while( list($answer, $value) = each($this->questions[$qkey]['answers']) ){
						$new_q['answers'][$value] = (object)array(
							'akey'   => $value,
							'qkey'   => $qkey,
							'answer' => $answer,
							'value'  => $value,
							'sorder' => 0 - $value,
						);
					}
					break;
				case 'freetext':
					$new_q['lines']   = 5;
					$new_q['columns'] = 80;
					break;
				case 'textfield':
					$new_q['columns'] = 2;
					break;
			}

			$fb->_questions[$qkey] = (object)$new_q;
		}
		reset($this->questions);

		return $fb;
	}

	function league_sotg_averages ( $league )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			AVG( s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall + s.score_entry_penalty),
			AVG(s.timeliness) AS timeliness,
			AVG(s.rules_knowledge) AS rules_knowledge,
			AVG(sportsmanship) AS sportsmanship,
			AVG(rating_overall) AS rating_overall,
			AVG(score_entry_penalty) AS score_entry_penalty
			FROM spirit_entry s, schedule g WHERE s.gid = g.game_id AND g.league_id = ?");
		$sth->execute(array($league->league_id));
		$result = $sth->fetch(PDO::FETCH_NUM);
		array_unshift($result, 'Average');
		return $result;
	}

	function league_sotg_std_dev ( $league )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			STDDEV( s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall + s.score_entry_penalty),
			STDDEV(s.timeliness) AS timeliness,
			STDDEV(s.rules_knowledge) AS rules_knowledge,
			STDDEV(s.sportsmanship) AS sportsmanship,
			STDDEV(s.rating_overall) AS rating_overall,
			STDDEV(s.score_entry_penalty) AS score_entry_penalty
			FROM spirit_entry s, schedule g WHERE s.gid = g.game_id AND g.league_id = ?");
		$sth->execute(array($league->league_id));
		$result = $sth->fetch(PDO::FETCH_NUM);
		array_unshift($result, 'Std. Dev');
		return $result;
	}

	function league_sotg( $league )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			t.name AS team_name,
			s.tid,
			AVG(
				COALESCE(
					s.entered_sotg,
					s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall + s.score_entry_penalty
				)
			) AS total,
			AVG(s.timeliness) AS timeliness,
			AVG(s.rules_knowledge) AS rules_knowledge,
			AVG(sportsmanship) AS sportsmanship,
			AVG(rating_overall) AS rating_overall,
			AVG(score_entry_penalty) AS score_entry_penalty
		FROM
			schedule g,
			spirit_entry s
				LEFT JOIN team t ON (s.tid = t.team_id)
		WHERE s.gid = g.game_id
			AND g.league_id = ?
		GROUP BY s.tid
		ORDER BY total DESC");

		$sth->execute(array($league->league_id));

		$rows = array();

		$out_of = $this->question_maximums();
		while( $row = $sth->fetch() ) {
			$thisrow = array(
				l($row['team_name'],"team/view/" . $row['tid']),
				$this->full_spirit_symbol_html( $row['total'] ),
			);

			reset($out_of);
			foreach( array_keys($out_of) as $qkey) {
				$thisrow[] = $this->question_spirit_symbol_html( $row[$qkey], $out_of[$qkey] );
			}

			$rows[] = $thisrow;
		}

		return $rows;
	}

	function league_sotg_distribution ( $league )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
				s.tid,
				AVG( s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall ) AS total
			FROM spirit_entry s, schedule g
			WHERE s.gid = g.game_id AND g.league_id = ? GROUP BY s.tid"
		);
		$sth->execute(array($league->league_id));


		$bins = array();
		$total_teams = 0;
		while( $row = $sth->fetch() ) {
			$bins[floor($row['total'])]++;
			$total_teams++;
		}

		$rows = array();
		for ($spirit = 10; $spirit >= 0; $spirit--) {
			$percentage = round($bins[$spirit] / $total_teams * 100);
			$rows[] = array( $spirit == 10 ? '10' : $spirit .' - '. ($spirit+1),
							$bins[$spirit],
							$percentage > 0 ? "$percentage%" : "&nbsp;");
		}

		return $rows;

	}

	/**
	 * Spirit values to use when automatically populating the spirit form or results
	 */
	function default_spirit_answers ( )
	{
		$spirit = array (
			'entered_sotg' => 7,
			'timeliness' => 1,
			'rules_knowledge' => 2,
			'sportsmanship' => 2,
			'rating_overall' => 2,
			'comments' => ''
		);
		return $spirit;
	}

	function team_sotg_averages ( $team )
	{
		global $dbh;
		$sth = $dbh->prepare("SELECT
			AVG(
				COALESCE(
					s.entered_sotg,
					s.timeliness + s.rules_knowledge + s.sportsmanship + s.rating_overall + s.score_entry_penalty
				)
			) AS entered_sotg,
			AVG(s.timeliness) AS timeliness,
			AVG(s.rules_knowledge) AS rules_knowledge,
			AVG(s.sportsmanship) AS sportsmanship,
			AVG(s.rating_overall) AS rating_overall,
			AVG(s.score_entry_penalty) AS score_entry_penalty
			FROM spirit_entry s WHERE s.tid = ?");
		$sth->execute(array($team->team_id));
		$result = $sth->fetch();

		return $this->render_game_spirit( $result );
	}

	/*
	 * render SOTG for single game
	 */
	function render_game_spirit ( $spirit )
	{
		$out_of = $this->question_maximums();

		if( ! $spirit['entered_sotg'] ) {
			$spirit['entered_sotg'] = (
				$spirit['timeliness'] + $spirit['rules_knowledge'] + $spirit['sportsmanship'] + $spirit['rating_overall'] + $spirit['score_entry_penalty']
			);
		}

		$result = array(
			$this->full_spirit_symbol_html( $spirit['entered_sotg'] ),
		);
		if( $this->entry_type == 'survey_only' ) {
			$result[] = $this->question_spirit_symbol_html( $spirit['timeliness'], $out_of['timeliness'] );
			$result[] = $this->question_spirit_symbol_html( $spirit['rules_knowledge'], $out_of['rules_knowledge'] );
			$result[] = $this->question_spirit_symbol_html( $spirit['sportsmanship'], $out_of['sportsmanship'] );
			$result[] = $this->question_spirit_symbol_html( $spirit['rating_overall'], $out_of['rating_overall'] );
		}
		$result[] = $this->question_spirit_symbol_html( $spirit['score_entry_penalty'], $out_of['score_entry_penalty'] );

		return $result;
	}

	/**
	 * Save a spirit entry for the given team
	 */
	function store_spirit_entry ( $game, $team_id, $enterer_id, $spirit )
	{
		global $dbh;

		if( !is_array($spirit) ) {
			die("Spirit argument to store_spirit_entry() must be an array");
		}

		// Store in object
		$game->_spirit_entries[$team_id] = $spirit;

		$opponent_id = $game->get_opponent_id( $team_id );

		// save in DB
		$sth = $dbh->prepare('REPLACE INTO spirit_entry (tid_created,tid,gid,
			entered_by,
			entered_sotg,
			timeliness,rules_knowledge,sportsmanship,rating_overall, comments) VALUES (?,?,?,?,?,?,?,?,?,?)');

		if( $this->entry_type == 'survey_only' ) {
			$spirit['entered_sotg'] = null;
		}
		$sth->execute( array(
			$opponent_id,
			$team_id,
			$game->game_id,
			$enterer_id,
			$spirit['entered_sotg'],
			$spirit['timeliness']      || 0,
			$spirit['rules_knowledge'] || 0,
			$spirit['sportsmanship']   || 0,
			$spirit['rating_overall']  || 0,
			$spirit['comments'],
		));
		if( $sth->rowCount() < 1) {
			return false;
		}

		return true;
	}

}
?>
