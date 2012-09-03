<?php

function sort_by_sorder($a, $b)
{
	if ($a == $b) {
		return 0;
	}
	return ($a->sorder < $b->sorder) ? -1 : 1;
}

class FormBuilder
{
	var $_questions;
	var $_answers;
	var $_name;

	var $_answer_validity;

	/**
	 * Load appropriate form data (questions and answers)
	 */
	function load ( $name )
	{
		global $dbh;

		// Initialise variables before anything else, so that this is done
		// even in case of early exit.
		$this->_name = $name;
		$this->_questions = array();
		$this->_answers = array();

		// Initialise answer validity.  Since none have been given, it's
		// unknown.
		$this->_answer_validity = 'false';

		$sth = $dbh->prepare('SELECT q.* from question q WHERE genre = ? order by q.sorder');
		$sth->execute(array($name));

		while( $question = $sth->fetch(PDO::FETCH_OBJ) ) {
			switch( $question->qtype ) {
				case 'multiplechoice':
					$question->answers = array();
					$a_sth = $dbh->prepare('SELECT akey,qkey,answer,value,sorder from multiplechoice_answers WHERE qkey = ? ORDER BY sorder');
					$a_sth->execute(array( $question->qkey));
					while($row = $a_sth->fetch(PDO::FETCH_OBJ)) {
						$question->answers[ $row->akey ] = $row;
					}
					break;
				case 'checkbox':
				case 'label':
				case 'description':
					break;
				case 'freetext':
					// TODO encode in $q->restrictions
					$question->lines = 5;
					$question->columns = 60;
					break;
				case 'textfield':
					// TODO encode in $q->restrictions
					$question->columns = 60;
					break;
				default:
					die("Unsupported question type '$question->qtype' in FormBuilder::load()");
			}

			// Fixups for boolean values
			if( $question->required == 'Y' ) {
				$question->required = true;
			} else {
				$question->required = false;
			}

			$this->_questions[$question->qkey] = $question;
		}

		return true;
	}

	/**
	 * Add a question to the form
	 */
	function add_question($name, $question, $desc, $type, $required, $sorder, $answers = array())
	{
		$q = new stdClass;
		$q->qkey = $name;
		$q->genre = $this->_name;
		$q->question = $question;
		$q->desc = $desc;
		$q->qtype = $type;
		$q->restrictions = '';
		$q->required = $required;
		$q->sorder = $sorder;

		// Add answers
		if( $type == 'multiplechoice' ) {
			$sorder = 0;
			foreach ($answers as $key => $answer) {
				if( $key != '---' ) {	// from getOptionsFrom functions
					$a = new stdClass;
					$a->akey = $key;
					$a->qkey = $name;
					$a->answer = $answer;
					$a->value = $answer;
					$a->sorder = ++ $sorder;
					$q->answers[$key] = $a;
				}
			}
		}

		$this->_questions[$name] = $q;

		// Make sure that the form is properly sorted afterwards
		uasort($this->_questions, 'sort_by_sorder');
	}

	/**
	 * Save the form data (questions and answers)
	 */
	function save( $reload )
	{
		global $dbh;
		// Save the data for the questions and answers
		$all_questions = array();
		$all_avalues = array();
		$qkeys = array();

		foreach( $this->_questions as $q ) {
			$all_questions[] = array( $q->qkey, $this->_name, $q->question, $q->qtype, $q->restrictions, ($q->required ? 'Y' : 'N'), $q->sorder );
			$qkeys[] = $q->qkey;

			if( $q->answers ) {
				foreach( $q->answers as $a ) {
					$all_avalues[] = array( $a->akey, $a->qkey, $a->answer, $a->value, $a->sorder );
				}
			}
		}

		// Execute the queries: delete the old data, put in the new.
		// This is harsh, better to somehow update only what's needed, but
		// it will do for now.
		$sth = $dbh->prepare('DELETE FROM question WHERE genre = ?');
		$sth->execute( array( $this->_name) );

		if( count( $qkeys ) ) {
			$sth = $dbh->prepare('DELETE FROM multiplechoice_answers WHERE qkey = ?');
			foreach ($qkeys as $key) {
				$sth->execute( array( $key ) );
			}
		}

		if( count( $all_questions ) ) {
			$sth = $dbh->prepare('INSERT INTO question (qkey, genre, question, qtype, restrictions, required, sorder)
				VALUES (?, ?, ?, ?, ?, ?, ?)');
			foreach ($all_questions as $question ) {
				$sth->execute( $question );
			}
		}

		if( count( $all_avalues ) ) {
			$sth = $dbh->prepare('INSERT INTO multiplechoice_answers (akey, qkey, answer, value, sorder) VALUES (?, ?, ?, ?, ?)');
			foreach( $all_avalues as $avalues ) {
				$sth->execute( $avalues );
			}
		}

		// After saving, we might reload, so that any sort order changes get
		// reflected. Also, answers for duplicated multiple choice questions
		// will be loaded in.
		if( $reload ) {
			$this->load( $this->_name );
		}

		return '';
	}

	/**
	 * Keys of active questions
	 */
	function question_keys ( )
	{
		return array_keys($this->_questions);
	}


	/**
	 * Render as a form.  If want_results is false, we print the blank form
	 * instead.
	 * TODO: allow specifying an array of question keys that will be rendered
	 * as viewable-only or hidden instead of editable.
	 */
	function render_editable ( $want_results = true, $suffix = null )
	{
		if ( $want_results ) {
			if( $this->answers_invalid() ) {
				return false;
			}
		}

		$name = $this->_name;
		if( ! is_null($suffix) ) {
			$name .= "_$suffix";
		}

		while( list(,$q) = each($this->_questions) ) {
			if( $want_results ) {
				$answer = $this->get_answer( $q->qkey );
			} else {
				$answer = null;
			}
			$output .= question_render_editable( $q, $name, $answer );
		}
		reset($this->_questions);
		return $output;
	}

	/**
	 * Render form as viewable-only.
	 */
	function render_viewable ()
	{
		$invalid = $this->answers_invalid();
		if( $invalid ) {
			return $invalid;
		}

		while( list(,$q) = each($this->_questions) ) {
			$output .= question_render_viewable( $q, $this->get_answer( $q->qkey) );
		}
		reset($this->_questions);
		return $output;
	}

	/**
	 * Render form as hidden form elements.
	 */
	function render_hidden ( $suffix = null )
	{
		if( $this->answers_invalid() ) {
			return false;
		}

		$name = $this->_name;
		if( ! is_null($suffix) ) {
			$name .= "_$suffix";
		}

		while( list(,$q) = each($this->_questions) ) {
			$output .= form_hidden($name."[$q->qkey]", $this->get_answer($q->qkey));
		}
		reset($this->_questions);
		return $output;
	}

	/**
	 * Render for maintenance of the form.
	 */
	function render_maintenance ()
	{
		// Remember the last sort order found, new question defaults to one past
		$sorder = 0;

		$output = form_hidden('edit[step]', 'confirm');

		while( list(,$q) = each($this->_questions) ) {
			$output .= question_render_maintenance( $q );
			$sorder = $q->sorder; // assume they're in order
		}

		// Add a form group for adding a new element
		$element = form_textfield( 'Element name', "data[_new][name]", '', 50, 60, 'If this is blank, no new element will be added' );
		$element .= form_textarea( 'Element text', "data[_new][question]", '', 60, 5 );
		$element .= form_textfield( 'Sort order', "data[_new][sorder]", $sorder + 1, 10, 10 );
		$element .= form_checkbox( 'Required', "data[_new][required]", 1, false, 'Does this element require an answer? (Ignored for checkboxes, labels and descriptions)' );

		$type = form_radio( 'Text field', 'data[_new][type]', 'textfield', true, 'A single line of text' );
		$type .= form_radio( 'Text area', 'data[_new][type]', 'freetext', false, 'A 60x5 text box' );
		$type .= form_radio( 'Multiple choice', 'data[_new][type]', 'multiplechoice', false, 'Multiple choice, answers can be defined later.' );
		$type .= form_radio( 'Checkbox', 'data[_new][type]', 'checkbox', false, 'A true/false checkbox.' );
		$type .= form_radio( 'Label', 'data[_new][type]', 'label', false, 'Not a question, used for inserting a label anywhere (e.g. before a checkbox group).' );
		$type .= form_radio( 'Description', 'data[_new][type]', 'description', false, 'Not a question, a block of descriptive text.' );
		$element .= form_item( 'Element type', $type );

		$output .= form_group ("Add a new element", $element);

		$output .= form_submit('Submit');
		$output .= form_reset('Reset');
		return form ($output);
	}

	/**
	 * Answer a given question.  Stores answer temporarily for validation.
	 */
	function set_answer ( $qkey, $answer )
	{
		// Ignore invalid keys
		if( !array_key_exists( $qkey, $this->_questions ) ) {
			return;
		}

		// Set the answer
		$this->_answers[$qkey] = $answer;

		// Mark validity as 'unknown' and return
		$this->_answer_validity = 'unknown';
	}

	/**
	 * Answer several questions at once. Stores answer temporarily for
	 * validation
	 */
	function bulk_set_answers ( $answers )
	{
		while(list($key, $value) = each($answers)) {
			$this->set_answer( $key, $value );
		}
	}

	/**
	 * Load answers with SQL. Stores answer for validation.  Query must return (key,value)
	 */
	function bulk_set_answers_sql ( $sql, $params )
	{
		global $dbh;

		$sth = $dbh->prepare( $sql );
		$sth->execute( $params );
		while($row = $sth->fetch() ) {
			$this->set_answer( $row[0], $row[1] );
		}
	}

	/**
	 * Delete stored answers
	 */
	function clear_answers()
	{
		$this->_answers = array();
		$this->_answer_validity = 'unknown';
	}

	/**
	 * Validates provided answers.  Returns string containing errors, or
	 * 'false' if no invalid or missing answers were given.
	 *
	 * By this point, all 'unsafe' answers should have been caught by
	 * valid_input_data(), so there's no need to check for that.
	 */
	function answers_invalid()
	{
		$invalid_flag = false;

		// If already checked, just return
		if( $this->_answer_validity == 'valid' ) {
			return false;
		}

		// Loop through all questions
		while( list(,$q) = each($this->_questions) ) {
			// Check that all 'required' questions have an answer
			if( $q->required && !array_key_exists( $q->qkey, $this->_answers) ) {
				$invalid_flag = true;
				$text .= "A value is required for <i>" . $q->question . "</i><br />";
			}
		}
		reset($this->_questions);

		// Loop through all provided answers in _answers
		while( list($qkey,$answer) = each($this->_answers) ) {
			$q = $this->_questions[$qkey];
			if( $q->required ) {
				// Check that answer is valid
				switch( $q->qtype ) {
					case 'multiplechoice':
						$invalid = question_validate_multiplechoice( $q, $answer);
						break;
					case 'checkbox':
					case 'label':
					case 'description':
						break;
					case 'freetext':
						$invalid = question_validate_freetext( $q, $answer);
						break;
					case 'textfield':
						$invalid = question_validate_textfield( $q, $answer);
						break;
					default:
						die("Unsupported question type '$q->qtype' in FormBuilder::answers_invalid()");
				}
				if( $invalid ) {
					$text .= $invalid . '<br />';
					$invalid_flag = true;
				}
			}
		}
		reset($this->_answers);

		if( !$invalid_flag ) {
			$this->_answer_validity = 'valid';
		}

		return $text;
	}

	/**
	 * Retrieves answers as an associative array.  Returns false if answers
	 * are invalid (you must call answers_invalid() directly to get the
	 * reasons)
	 */
	function bulk_get_answers()
	{
		if( $this->answers_invalid() ) {
			return false;
		}
		return $this->_answers;
	}

	function get_answer( $key )
	{
		if( $this->answers_invalid() ) {
			return false;
		}

		if( array_key_exists( $key, $this->_answers) ) {
			$answer = $this->_answers[$key];
		} else {
			$answer = null;
		}

		return $answer;
	}

	/**
	 * Generate confirmation page for the requested maintenance
	 */
	function render_confirmation( $data )
	{
	}

	/**
	 * Perform the requested maintenance
	 */
	function perform_maintenance( $data )
	{
		global $dbh;
		$output = '';

		foreach( $data as $qkey => $qdata )
		{
			if( $qkey == '_new' )
			{
				if( $qdata['name'] != '' ) {
					$q = new stdClass;
					$q->qkey = $qdata['name'];
					$q->genre = $this->_name;
					$q->question = $qdata['question'];
					$q->qtype = $qdata['type'];
					$q->restrictions = '';
					$q->required = $qdata['required'];
					$q->sorder = $qdata['sorder'];

					// Read in any answers, in case this is an existing question
					if( $q->qtype == 'multiplechoice' ) {
						$q->answers = array();
						$sth = $dbh->prepare('SELECT a.* from multiplechoice_answers a WHERE a.qkey = ? ORDER BY a.sorder');
						$sth->execute( array($q->qkey) );
						while($ans = $sth->fetch(PDO::FETCH_OBJ) ) {
							$q->answers[$ans->akey] = $ans;
						}
					}

					$this->_questions[$qdata['name']] = $q;
				}
			}
			else
			{
				$q = $this->_questions[$qkey];
				if( isset( $q ) ) {
					if( $qdata['delete'] ) {
						unset( $this->_questions[$qkey] );
					}
					else {
						$this->perform_question_maintenance( $qkey, $qdata );
					}
				}
				else {
					$output .= "<p class='error'>Question '$qkey' was not found!</p>";
				}
			}
		}

		return '<p>Changes accepted</p>';
	}

	function perform_question_maintenance( $qkey, $qdata )
	{
		$q =& $this->_questions[$qkey];

		// TODO data validation
		$q->question = $qdata['question'];
		$q->sorder = $qdata['sorder'];
		$q->required = $qdata['required'];

		if( $q->qtype == 'multiplechoice' ) {
			foreach( $qdata['answer'] as $akey => $adata )
			{
				if( $akey == '_new' )
				{
					if( $adata['name'] != '' ) {
						$a = new stdClass;
						$a->akey = $adata['name'];
						$a->qkey = $qkey;
						$a->answer = $adata['answer'];
						$a->value = $adata['value'];
						$a->sorder = $adata['sorder'];
						$q->answers[$adata['name']] = $a;
					}
				}
				else
				{
					$o =& $q->answers[$akey];
					if( isset( $o ) ) {
						if( $adata['delete'] ) {
							unset( $q->answers[$akey] );
						}
						else {
							// TODO data validation
							$o->answer = $adata['answer'];
							$o->value = $adata['value'];
							$o->sorder = $adata['sorder'];
						}
					}
					else {
						$output .= "<p class='error'>Answer '$akey' was not found in question '$qkey'!</p>";
					}
				}
			}
		}
	}

}

/**
 * Wrapper for convenience
 */
function formbuilder_load( $name )
{
	$obj = new FormBuilder;
	if($obj->load($name)) {
		return $obj;
	} else {
		return null;
	}
}

function formbuilder_maintain( $name )
{
	// We need the object whether or not there are any questions in it
	$formbuilder = new FormBuilder;
	$formbuilder->load( $name );

	$data = $_POST['data'];

	switch($_POST['edit']['step']) {
		// Generate confirmation page
		case 'confirm':
			// TODO generate the confirmation; for now, just save changes
			//$rc = $formbuilder->perform_maintenance( $data );
			//$rc .= $formbuilder->render_confirmation();
			//break;

		case 'perform':
			$rc = $formbuilder->perform_maintenance( $data );
			$rc .= $formbuilder->save( true );
			$rc .= $formbuilder->render_maintenance();
			break;

		default:
			$rc = $formbuilder->render_maintenance();
	}

	return $rc;
}

/**
 * TODO: The things below should be full-fledged "question" objects, but I'm too
 * lazy to do all that extra work right now, plus it's probably a big
 * performance hit.
 *
 */

/**
 * Render questions as editable.  When this is made a proper class, this will
 * be unnecessary due to polymorphism, as each question type will know how to
 * render itself via its own ->render_editable() method.
 */
function question_render_editable( &$q, $editgroup, $value = '', $formtype = 'auto')
{
	switch($q->qtype) {
		case 'multiplechoice':
			$output .= question_render_editable_multiplechoice( $q, $editgroup, $value );
			break;
		case 'checkbox':
			$output .= question_render_editable_checkbox( $q, $editgroup, $value);
			break;
		case 'freetext':
			$output .= question_render_editable_freetext( $q, $editgroup, $value);
			break;
		case 'textfield':
			$output .= question_render_editable_textfield( $q, $editgroup, $value);
			break;
		case 'hidden':
			$output .= question_render_editable_hidden( $q, $editgroup, $value );
			break;
		case 'label':
			$output .= question_render_editable_label( $q );
			break;
		case 'description':
			$output .= question_render_editable_description( $q );
			break;
		default:
			$output .= "Error: question <i>$q->question</i> is of unsupported type<br />";
	}

	return $output;
}

/**
 * Render questions as viewable.  When this is made a proper class, this will
 * be unnecessary due to polymorphism, as each question type will know how to
 * render itself via its own ->render_viewable() method.
 */
function question_render_viewable( &$q, $value = '' )
{
	switch($q->qtype) {
		case 'multiplechoice':
			$output .= question_render_viewable_multiplechoice( $q, $value );
			break;
		case 'checkbox':
			$output .= question_render_viewable_checkbox( $q, $value);
			break;
		case 'freetext':
			$output .= question_render_viewable_freetext( $q, $value);
			break;
		case 'textfield':
			$output .= question_render_viewable_textfield( $q, $value);
			break;
		case 'hidden':
			$output .= question_render_viewable_hidden( $q, $value);
			break;
		case 'label':
			$output .= question_render_viewable_label( $q );
			break;
		case 'description':
			$output .= question_render_viewable_description( $q );
			break;
		default:
			$output .= "Error: question <i>$q->question</i> is of unsupported type<br />";
	}

	return $output;
}

/**
 * Render questions for form maintenance.  When this is made a proper class,
 * this will be unnecessary due to polymorphism, as each question type will
 * know how to render itself via its own ->render_maintenance() method.
 */
function question_render_maintenance( &$q )
{
	switch($q->qtype) {
		case 'multiplechoice':
			$output .= question_render_maintenance_multiplechoice( $q );
			break;
		case 'checkbox':
			$output .= question_render_maintenance_checkbox( $q );
			break;
		case 'freetext':
			$output .= question_render_maintenance_freetext( $q );
			break;
		case 'textfield':
			$output .= question_render_maintenance_textfield( $q );
			break;
		case 'label':
			$output .= question_render_maintenance_label( $q );
			break;
		case 'description':
			$output .= question_render_maintenance_description( $q );
			break;
		default:
			$output .= "Error: question <i>$q->question</i> is of unsupported type<br />";
	}

	return $output;
}

/**
 * Render a multiple choice question for form output.
 */
function question_render_editable_multiplechoice( &$q, $editgroup, $value = '', $formtype = 'auto' )
{
	if( $formtype == 'auto' ) {
		$formtype = 'radio';
	}

	$form = '';
	switch( $formtype ) {
		case 'radio':
			$radio = "";
			if(is_array($q->answers)) {
				while( list(,$ans) = each($q->answers) ) {
					$radio .= form_radio( $ans->answer, $editgroup."[".$ans->qkey."]", $ans->akey, ($ans->akey == $value), '') . "<br />";
				}
				reset( $q->answers );
			}
			$form = form_item($q->question, $radio, $q->desc);
			break;
		default:
			die("Unsupported formtype of $formtype given to question_render_editable_multiplechoice");
	}

	return $form;
}

/**
 * Render a multiple choice question for question/answer output
 */
function question_render_viewable_multiplechoice( &$q, $akey = '' )
{
	return form_item($q->question, $q->answers[$akey]->answer);
}

/**
 * Render a multiple choice question for form maintenance.
 */
function question_render_maintenance_multiplechoice( &$q, $formtype = 'auto' )
{
	if( $formtype == 'auto' ) {
		$formtype = 'radio';
	}

	$group = form_textarea( 'Question', "data[{$q->qkey}][question]", $q->question, 50, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );
	$group .= form_checkbox( 'Required', "data[{$q->qkey}][required]", 1, $q->required, 'Does this question require an answer?' );
	$group .= form_checkbox( 'Delete this question entirely', "data[{$q->qkey}][delete]", 1, false );
	$group .= '<p class="error">Note that any changes to the answers below will affect all surveys that share this question.</p>';

	// Remember the last sort order found, new answer defaults to one past
	$sorder = 0;

	switch( $formtype ) {
		case 'radio':
			// Add a form group for each answer
			while( list(,$ans) = each($q->answers) ) {
				$answer = form_textfield( 'Answer text', "data[{$q->qkey}][answer][$ans->akey][answer]", $ans->answer, 60, 60 );
				$answer .= form_textfield( 'Value', "data[{$q->qkey}][answer][$ans->akey][value]", $ans->value, 10, 10 );
				$answer .= form_textfield( 'Sort order', "data[{$q->qkey}][answer][$ans->akey][sorder]", $ans->sorder, 10, 10 );
				$answer .= form_checkbox( "Remove this answer", "data[{$q->qkey}][answer][$ans->akey][delete]", 1, false );
				$group .= form_group ("Answer name: $ans->akey", $answer);
				$sorder = $ans->sorder; // assume they're in order
			}

			break;
		default:
			die("Unsupported formtype of $formtype given to question_render_maintenance_multiplechoice");
	}

	// Add a form group for adding a new answer
	$answer = form_textfield( 'Answer name', "data[{$q->qkey}][answer][_new][name]", '', 60, 60, 'If this is blank, no new answer will be added' );
	$answer .= form_textfield( 'Answer text', "data[{$q->qkey}][answer][_new][answer]", '', 60, 60 );
	$answer .= form_textfield( 'Value', "data[{$q->qkey}][answer][_new][value]", '', 10, 10 );
	$answer .= form_textfield( 'Sort order', "data[{$q->qkey}][answer][_new][sorder]", $sorder + 1, 10, 10 );
	$group .= form_group ("Add a new answer", $answer);

	return form_group( "Question name: $q->qkey", $group );
}

function question_validate_multiplechoice( &$q, $answer )
{
	// For a multiple choice answer to be valid, it must exist in
	// the list of valid answers.  That's it.
	if( array_key_exists( $answer, $q->answers ) ) {
		return false;
	} else {
		return "You must select a valid value for <i>$q->question</i>";
	}
}

/*
 * Render checkbox for entry
 */
function question_render_editable_checkbox( &$q, $editgroup, $value = '')
{
	return form_checkbox( $q->question, $editgroup."[$q->qkey]", 1, $value, $q->desc);
}

function question_render_viewable_checkbox( &$q, $value = '')
{
	return form_item( $q->question, ($value ? 'Yes' : 'No') );
}

function question_render_maintenance_checkbox( &$q )
{
	$group = form_textarea( 'Question', "data[{$q->qkey}][question]", $q->question, 50, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );

	$group .= form_checkbox( 'Delete this checkbox', "data[{$q->qkey}][delete]", 1, false );

	return form_group( "Question name: $q->qkey", $group );
}

/*
 * Render textbox for entry
 */
function question_render_editable_freetext( &$q, $editgroup, $value = '')
{
	return form_textarea( $q->question, $editgroup."[$q->qkey]", $value, $q->columns, $q->lines, $q->desc);
}

function question_render_viewable_freetext( &$q, $value = '')
{
	return form_item( $q->question, $value );
}

function question_render_maintenance_freetext( &$q )
{
	$group = form_textarea( 'Question', "data[{$q->qkey}][question]", $q->question, 50, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );
	$group .= form_checkbox( 'Required', "data[{$q->qkey}][required]", 1, $q->required, 'Does this question require an answer?' );

	$group .= form_checkbox( 'Delete this text area', "data[{$q->qkey}][delete]", 1, false );

	return form_group( "Question name: $q->qkey", $group );
}

function question_validate_freetext( &$q, $value = '' )
{
	if ( preg_match("/</", $value) ) {
		return "Answer for <b>".$q->question."</b> cannot contain HTML<br />";
	}
	return false;
}

/*
 * Render text field for entry
 */
function question_render_editable_textfield( &$q, $editgroup, $value = '')
{
	return form_textfield( $q->question, $editgroup."[$q->qkey]", $value, $q->columns, $q->columns, $q->desc);
}

function question_render_viewable_textfield( &$q, $value = '')
{
	return form_item( $q->question, $value );
}

function question_render_maintenance_textfield( &$q )
{
	$group = form_textarea( 'Question', "data[{$q->qkey}][question]", $q->question, 50, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );
	$group .= form_checkbox( 'Required', "data[{$q->qkey}][required]", 1, $q->required, 'Does this question require an answer?' );

	$group .= form_checkbox( 'Delete this text field', "data[{$q->qkey}][delete]", 1, false );

	return form_group( "Question name: $q->qkey", $group );
}

function question_validate_textfield( &$q, $value = '' )
{
	if ( preg_match("/</", $value) ) {
		return "Answer for <b>".$q->question."</b> cannot contain HTML";
	}
	else if ( $q->required && strlen( $value ) == 0 ) {
		return "A value is required for <i>" . $q->question . "</i><br />";
	}
	return false;
}

/*
 * Render label for entry
 */
function question_render_editable_label( &$q )
{
	return form_item( $q->question, '', '' );
}

function question_render_viewable_label( &$q, $value = '')
{
	return form_item( $q->question, '', '' );
}

function question_render_maintenance_label( &$q )
{
	$group = form_textarea( 'Element', "data[{$q->qkey}][question]", $q->question, 50, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );

	$group .= form_checkbox( 'Delete this label', "data[{$q->qkey}][delete]", 1, false );

	return form_group( "Element name: $q->qkey", $group );
}

/*
 * Render description for entry
 */
function question_render_editable_description( &$q )
{
	return form_item( '', '', $q->question );
}

function question_render_viewable_description( &$q, $value = '')
{
	return form_item( '', '', $q->question );
}

function question_render_maintenance_description( &$q )
{
	$group = form_textarea( 'Element', "data[{$q->qkey}][question]", $q->question, 60, 5 );
	$group .= form_textfield( 'Sort order', "data[{$q->qkey}][sorder]", $q->sorder, 10, 10 );

	$group .= form_checkbox( 'Delete this description', "data[{$q->qkey}][delete]", 1, false );

	return form_group( "Element name: $q->qkey", $group );
}

function question_render_editable_hidden( &$q, $editgroup, $value = '')
{
	return form_hidden( $editgroup."[$q->qkey]", $value);
}

function question_render_viewable_hidden( &$q, $value = '')
{
	return '';
}

?>
