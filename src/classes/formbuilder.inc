<?php
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
		$result = db_query("SELECT q.* from question q WHERE genre = '%s' order by q.sorder", $name);

		if(db_num_rows($result) < 1) {
			return false;
		}

		$this->_name = $name;

		$this->_questions = array();
		while( $question = db_fetch_object( $result ) ) {

			switch( $question->qtype ) {
				case 'multiplechoice':
					$question->answers = array();
					$answers = db_query("SELECT a.* from multiplechoice_answers a WHERE a.qkey = '%s' ORDER BY a.sorder", $question->qkey);
					while($ans = db_fetch_object( $answers ) ) {
						$question->answers[$ans->akey] = $ans;
					}
					break;
				case 'freetext':
					// TODO encode in $q->restrictions
					$question->lines = 5;
					$question->columns = 60;
					break;
				default:
					die("Unsupported question type in FormBuilder::load()");
			}


			// Fixups for boolean values
			if( $question->required == 'Y' ) {
				$question->required == true;
			} else {
				$question->required == false;
			}
			
			$this->_questions[$question->qkey] = $question;
		}

		// Initialise answer validity.  Since none have been given, it's
		// unknown.
		$this->_answer_validity = 'false';

		$this->_answers = array();

		return true;
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
		if( $this->answers_invalid() ) {
			return false;
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
	 * Answer a given question.  Stores answer temporarily for validation.
	 */
	function set_answer ( $qkey, $answer )
	{
		// Check that the question exists, die if not
		if( !array_key_exists( $qkey, $this->_questions ) ) {
			die("Answer provided for invalid question $qkey");
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
				$text .= "A value is required for <i>" . $q->question . "</i><br>";
			}
		}
		reset($this->_questions);

		// Loop through all provided answers in _answers
		while( list($qkey,$answer) = each($this->_answers) ) {
			$q = $this->_questions[$qkey];
			// Check that answer is valid
			switch( $q->qtype ) {
				case 'multiplechoice':	
					$invalid = question_validate_multiplechoice( $q, $answer);
					break;
				case 'freetext':
					$invalid = question_validate_freetext( $q, $answer);
					break;
				default:
					die("Unsupported question type in FormBuilder::answers_invalid()");
			}
			if( $invalid ) {
				$text .= $invalid . '<br />';
				$invalid_flag = true;
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
		case 'freetext':
			$output .= question_render_editable_freetext( $q, $editgroup, $value);
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
		case 'freetext':
			$output .= question_render_viewable_freetext( $q, $value);
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
		if( count($q->answers) > 4 ) {
			$formtype = 'select';
		} else {
			$formtype = 'radio';
		}
	}

	$form = '';
	switch( $formtype ) {
		case 'radio':
			$radio = "";
			while( list(,$ans) = each($q->answers) ) {
				$radio .= form_radio( $ans->answer, $editgroup."[".$ans->qkey."]", $ans->akey, ($ans->akey == $value), '');
			}
			reset( $q->answers );
			$form = form_item($q->question, $radio);	
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
 * Render textbox for entry
 */
function question_render_editable_freetext( &$q, $editgroup, $value = '')
{
	return form_textarea( $q->question, $editgroup."[$q->qkey]", $value, $q->columns, $q->lines);
}

function question_render_viewable_freetext( &$q, $value = '')
{
	return form_item( $q->question, $value );
}

function question_validate_freetext( &$q, $value = '' )
{
	if ( preg_match("/</", $value) ) {
		return "Answer for <b>".$q->question."</b> cannot contain HTML";
	}
	return false;
}

?>
