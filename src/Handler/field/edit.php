<?php
require_once('Handler/FieldHandler.php');

class field_edit extends FieldHandler
{
	function __construct ( $id )
	{
		parent::__construct( $id );
		$this->title = "Edit Field: {$this->field->fullname}";
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->has_permission('field','edit', $this->field->fid);
	}

	function process ()
	{
		$edit = $_POST['edit'];

		$this->template_name = 'pages/field/edit.tpl';

		$this->generateForm( $edit );
		$this->smarty->assign('field', $this->field);

		if( $edit['step'] == 'perform' ) {
			$errors = $this->check_input_errors( $edit );
			if(count($errors) > 0) {
				$this->smarty->assign('edit', $edit);
				$this->smarty->assign('formErrors', $errors);
				return true;
			}
			$this->perform($edit);
			local_redirect('field/view/' . $this->field->fid);
		} else {
			$this->smarty->assign('edit', (array)$this->field);
		}

		return true;
	}

	function generateForm( &$edit )
	{
		$this->smarty->assign('field_statuses', array('open' => 'open', 'closed' => 'closed'));
		$this->smarty->assign('ratings', field_rating_values());

		// TODO: Should become Field::get_eligible_parents()
		$sth = Field::query( array('_extra' => 'ISNULL(parent_fid)', '_order' => 'f.name,f.num') );
		$parents = array();
		$parents[0] = "---";
		while($p = $sth->fetch(PDO::FETCH_OBJ) ) {
			$parents[$p->fid] = $p->fullname;
		}

		$this->smarty->assign('parents', $parents);

		$this->smarty->assign('regions', getOptionsFromEnum('field', 'region'));
		$this->smarty->assign('noyes', array( 0 => 'No', 1 => 'Yes'));

		$this->smarty->assign('province_names', getProvinceNames());
		$this->smarty->assign('state_names', getStateNames());
		$this->smarty->assign('country_names',  getCountryNames());

		return true;
	}

	function perform ( &$edit )
	{
		$this->field->set('num', $edit['num']);
		$this->field->set('status', $edit['status']);
		$this->field->set('rating', $edit['rating']);

		if( isset($edit['parent_fid']) ) {
			$this->field->set('parent_fid', $edit['parent_fid']);
		}

		if( $edit['parent_fid'] == 0 ) {
			$this->field->set('parent_fid', '' );
			$this->field->set('name', $edit['name']);
			$this->field->set('code', $edit['code']);
			$this->field->set('location_street', $edit['location_street']);
			$this->field->set('location_city', $edit['location_city']);
			$this->field->set('location_province', $edit['location_province']);
			$this->field->set('location_country', $edit['location_country']);
			$this->field->set('location_postalcode', $edit['location_postalcode']);
			$this->field->set('is_indoor', $edit['is_indoor']);

			$this->field->set('region', $edit['region']);
			$this->field->set('location_url', $edit['location_url']);
			$this->field->set('layout_url', $edit['layout_url']);
			$this->field->set('driving_directions', $edit['driving_directions']);
			$this->field->set('transit_directions', $edit['transit_directions']);
			$this->field->set('biking_directions', $edit['biking_directions']);
			$this->field->set('parking_details', $edit['parking_details']);
			$this->field->set('washrooms', $edit['washrooms']);
			$this->field->set('public_instructions', $edit['public_instructions']);
			$this->field->set('site_instructions', $edit['site_instructions']);
			$this->field->set('sponsor', $edit['sponsor']);
		}

		if( !$this->field->save() ) {
			error_exit("Internal error: couldn't save changes");
		}

		return true;
	}

	function check_input_errors ( $edit = array() )
	{
		$errors = array();

		if( ! validate_number($edit['num']) ) {
			$errors[] = "Number of field must be provided";
		}

		$rating = field_rating_values();
		if( ! array_key_exists($edit['rating'], $rating) ) {
			$errors[] = "Rating must be provided";
		}

		if( $edit['parent_fid'] > 0 ) {
			if( ! validate_number($edit['parent_fid']) ) {
				$errors[] = "Parent must be a valid value";
			}
			
			return $errors;
		}

		if( !validate_nonhtml($edit['name'] ) ) {
			$errors[] = "Name cannot be left blank, and cannot contain HTML";
		}
		if( !validate_nonhtml($edit['code'] ) ) {
			$errors[] = "Code cannot be left blank and cannot contain HTML";
		}

		if( ! validate_nonhtml($edit['region']) ) {
			$errors[] = "Region cannot be left blank and cannot contain HTML";
		}

		if(validate_nonblank($edit['location_url'])) {
			if( ! validate_nonhtml($edit['location_url']) ) {
				$errors[] = "If you provide a location URL, it must be valid.";
			}
		}

		if(validate_nonblank($edit['layout_url'])) {
			if( ! validate_nonhtml($edit['layout_url']) ) {
				$errors[] = "If you provide a site layout URL, it must be valid.";
			}
		}

		return $error;
	}
}
?>
