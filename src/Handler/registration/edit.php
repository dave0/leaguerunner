<?php
require_once('Handler/RegistrationHandler.php');
class registration_edit extends RegistrationHandler
{
	function __construct ( $id )
	{
		parent::__construct($id);
		$this->form_load(true);
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('registration','edit', $this->registration->order_id);
	}

	function process ()
	{
		$this->title = 'Registration ' . $this->registration->formatted_order_id();

		$edit = $_POST['edit'];

		switch($edit['step']) {
			case 'confirm':
				$rc = $this->generateConfirm( $edit );
				break;
			case 'submit':
				$this->perform( $edit );
				local_redirect(url("registration/view/" . $this->registration->order_id));
				break;
			default:
				$rc = $this->generateForm();
		}

		return $rc;
	}

	function generateForm()
	{
		$output = form_hidden('edit[step]', 'confirm');

		$player = $this->registration->user();
		if( ! $player ) {
			return false;
		}

		$noneditable = array();
		$noneditable[] = array ('Name', l($player->fullname, url("person/view/{$player->id}")) );
		$noneditable[] = array ('Member&nbsp;ID', $player->member_id);
		$noneditable[] = array ('Event', l($this->event->name, url("event/view/{$this->event->registration_id}")));
		$noneditable[] = array ('Registered Price', $this->registration->total_amount);
		$form = '<div class="pairtable">' . table(NULL, $noneditable) . '</div>';
		$pay_opts = getOptionsFromEnum('registrations', 'payment');
		array_shift($pay_opts);
		$form .= form_select('Registration Status', 'edit[payment]', $this->registration->payment, $pay_opts);
		$form .= form_textarea('Notes', 'edit[notes]', $this->registration->notes, 45, 5);
		$output .= form_group('Registration details', $form);

		if ( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers_sql(
				'SELECT qkey, akey FROM registration_answers WHERE order_id = ?',
				array( $this->registration->order_id)
			);

			if( count($this->formbuilder->_answers) > 0 ) {
				$output .= form_group('Registration answers', $this->formbuilder->render_editable (true));
			} else {
				$output .= form_group('Registration answers', $this->formbuilder->render_editable (false));
			}
		}

		$output .= form_submit('Submit') .  form_reset('Reset');

		return form($output);
	}

	function generateConfirm ( $edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );

		if( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			info_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}
		$output = para('Please confirm that this data is correct and click the submit button to proceed to the payment information page.');
		$output .= form_hidden('edit[step]', 'submit');
		$fields = array(
			'Registration Status' => 'payment',
			'Notes' => 'notes'
		);

		$rows = array();
		foreach ($fields as $display => $column) {
			array_push( $rows,
				array( $display, form_hidden("edit[$column]", $edit[$column]) . check_form($edit[$column])));
		}
		$output .= form_group('Registration details', "<div class='pairtable'>" . table(null, $rows) . '</div>');

		if( $this->formbuilder )
		{
			$form = $this->formbuilder->render_viewable();
			$form .= $this->formbuilder->render_hidden();
			$output .= form_group('Registration answers', $form);
		}

		$output .= para(form_submit('submit'));

		return form($output);
	}

	function perform ( &$edit )
	{
		$dataInvalid = $this->isDataInvalid( $edit );

		if( $this->formbuilder )
		{
			$this->formbuilder->bulk_set_answers( $_POST[$this->event->formkey()] );
			$dataInvalid .= $this->formbuilder->answers_invalid();
		}

		if( $dataInvalid ) {
			info_exit($dataInvalid . '<br>Please use your back button to return to the form, fix these errors, and try again.');
		}

		$this->registration->set('payment', $edit['payment']);
		$this->registration->set('notes', $edit['notes']);

		if( !$this->registration->save() ) {
			error_exit("Internal error: couldn't save changes to the registration details");
		}

		if( $this->formbuilder )
		{
			if( !$this->registration->save_answers( $this->formbuilder, $_POST[$this->event->formkey()] ) ) {
				error_exit('Error saving registration question answers.');
			}
		}

		return true;
	}

	function isDataInvalid ( $edit )
	{
		$errors = '';

		// nonhtml also checks that the string is not blank, so we'll just
		// tack on a trailing letter so that it will only check for HTML...
		if( !validate_nonhtml($edit['notes'] . 'a' ) ) {
			$errors .= '<li>Notes cannot contain HTML';
		}

		if(strlen($errors) > 0) {
			return $errors;
		} else {
			return false;
		}
	}
}

?>
